/* 6.0.0: rows are rendered once per view mode; count an id once ( shared definition lives in shell-v2.js ). */window.ecv2_row_check_count = window.ecv2_row_check_count || function() { var seen = {}, n = 0; jQuery( '.ecv2-row-check:checked' ).each( function() { if ( ! seen[ this.value ] ) { seen[ this.value ] = true; n++; } } ); return n; };
/**
 * WP EasyCart Admin — Order List V2 (FREE)
 *
 * Contains the generic ecv2 table interactions (toast, undo, confirm dialog,
 * filter drawer, search, view toggle, stat cards, bulk selection) plus the
 * order-specific features: inline status change, viewed dot, item popovers,
 * copy-to-clipboard, the bulk change-status modal and PRO gate stubs.
 *
 * PRO (orders-v2-pro.js) overrides the window.ecv2_open_fulfill /
 * saved-view stubs and adds its own popovers. Only one v2 list script loads
 * per admin page, so the shared function names never collide with
 * products-v2.js.
 *
 * @since 6.0.0
 */
jQuery( function( $ ) {
	'use strict';

	if ( ! $( '.ecv2-wrap[data-table-id="ec_admin_order_list_v2"]' ).length ) {
		return;
	}

	var ecv2_undo_stack = [];
	var ECV2_UNDO_MAX = 20;

	/* ===================================================================== */
	/* Generic helpers                                                        */
	/* ===================================================================== */

	function ecv2_toast( message, type ) {
		type = type || 'success';
		var icon = type === 'success' ? 'yes' : ( type === 'error' ? 'no' : 'info-outline' );
		var $toast = $( '<div class="ecv2-toast ecv2-toast-' + type + '">' +
			'<span class="dashicons dashicons-' + icon + '"></span> ' +
			'<span>' + ecv2_esc_html( message ) + '</span></div>' );
		$( '#ecv2-toast-container' ).append( $toast );
		setTimeout( function() {
			$toast.fadeOut( 300, function() { $( this ).remove(); } );
		}, 3500 );
	}
	window.ecv2_toast = ecv2_toast;

	function ecv2_esc_html( str ) {
		return String( str === null || typeof str === 'undefined' ? '' : str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}
	window.ecv2_esc_html = ecv2_esc_html;

	function ecv2_push_undo( action_data ) {
		ecv2_undo_stack.push( action_data );
		if ( ecv2_undo_stack.length > ECV2_UNDO_MAX ) {
			ecv2_undo_stack.shift();
		}
		$( '#ecv2-undo-message' ).text( action_data.message || ecv2_lang.undo_available );
		$( '#ecv2-undo-bar' ).fadeIn( 200 );
		clearTimeout( window.ecv2_undo_timer );
		window.ecv2_undo_timer = setTimeout( function() {
			$( '#ecv2-undo-bar' ).fadeOut( 200 );
		}, 15000 );
	}

	$( document ).on( 'click', '#ecv2-undo-button', function() {
		if ( ecv2_undo_stack.length === 0 ) { return; }
		var action = ecv2_undo_stack.pop();
		if ( action && action.undo_fn ) {
			action.undo_fn();
		}
		if ( ecv2_undo_stack.length === 0 ) {
			$( '#ecv2-undo-bar' ).fadeOut( 200 );
		}
	});

	/* Confirm dialog (base markup: #ecv2-confirm-dialog). */
	var ecv2_confirm_callback = null;
	function ecv2_show_confirm( title, message, on_ok ) {
		$( '#ecv2-confirm-title' ).text( title );
		$( '#ecv2-confirm-message' ).text( message );
		ecv2_confirm_callback = on_ok || null;
		$( '#ecv2-confirm-dialog' ).css( 'display', 'flex' );
	}
	window.ecv2_show_confirm = ecv2_show_confirm;
	window.ecv2_confirm_ok = function() {
		$( '#ecv2-confirm-dialog' ).hide();
		if ( typeof ecv2_confirm_callback === 'function' ) {
			var cb = ecv2_confirm_callback;
			ecv2_confirm_callback = null;
			cb();
		}
	};
	window.ecv2_confirm_cancel = function() {
		ecv2_confirm_callback = null;
		$( '#ecv2-confirm-dialog' ).hide();
	};

	/* ===================================================================== */
	/* View toggle + per page + pagination                                    */
	/* ===================================================================== */

	$( document ).on( 'click', '.ecv2-view-btn:not(.ecv2-view-btn-locked)', function() {
		var mode = $( this ).data( 'mode' );
		$( '.ecv2-view-btn' ).removeClass( 'ecv2-view-btn-active' );
		$( this ).addClass( 'ecv2-view-btn-active' );
		$( '.ecv2-view' ).hide();
		$( '.ecv2-view-' + mode ).show();
		$( '.ecv2-wrap' ).attr( 'data-view-mode', mode );
		document.cookie = 'wpeasycart_admin_view_mode=' + mode + ';path=/;max-age=31536000';
	});



	$( document ).on( 'change', '.ecv2-perpage-select', function() {
		$( '#ecv2-posts-filter' ).submit();
	});

	$( document ).on( 'keydown', '.ecv2-page-input', function( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			$( '#ecv2-posts-filter' ).submit();
		}
	});

	/* ===================================================================== */
	/* Stat cards + visibility toggle                                         */
	/* ===================================================================== */

	$( document ).on( 'click', '.ecv2-stat-card', function() {
		if ( $( this ).hasClass( 'ecv2-stat-static' ) ) { return; }
		var filter_val = $( this ).data( 'filter' );
		$( '#ecv2-health-filter-input' ).val( filter_val );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( filter_val !== '' ) {
			$( this ).addClass( 'ecv2-stat-active' );
		}
		$( '.ecv2-stat-card' ).addClass( 'ecv2-stat-loading-dim' );
		$( this ).removeClass( 'ecv2-stat-loading-dim' ).addClass( 'ecv2-stat-loading' );
		$( this ).find( '.ecv2-stat-value' ).html( '<span class="dashicons dashicons-update ecv2-spin ecv2-stat-spinner"></span>' );
		$( '#ecv2-search-input' ).val( '' );
		$( '#ecv2-posts-filter' ).submit();
	});

	$( document ).on( 'click', '#ecv2-stat-toggle-btn', function() {
		var $panel = $( '#ecv2-stat-toggle-panel' );
		var expanded = $panel.hasClass( 'ecv2-stat-toggle-open' );
		$panel.toggleClass( 'ecv2-stat-toggle-open' );
		$( this ).attr( 'aria-expanded', ! expanded );
	});

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
	});

	$( document ).on( 'change', '.ecv2-stat-toggle-individual', function() {
		var key = $( this ).data( 'stat-key' );
		var visible = $( this ).is( ':checked' );
		$( '.ecv2-stat-card[data-stat-key="' + key + '"]' ).toggleClass( 'ecv2-stat-hidden', ! visible );
		ecv2_save_stat_visibility();
	});

	function ecv2_save_stat_visibility() {
		var hidden = [];
		var hide_all = $( '.ecv2-stat-toggle-cb[data-stat-key="__all"]' ).is( ':checked' );
		if ( hide_all ) {
			hidden.push( '__all' );
		} else {
			$( '.ecv2-stat-toggle-individual' ).each( function() {
				if ( ! $( this ).is( ':checked' ) ) {
					hidden.push( $( this ).data( 'stat-key' ) );
				}
			});
		}
		var nonce = $( '#ecv2-stat-toggle-nonce' ).val();
		var table_id = $( '#ecv2-stat-toggle-table-id' ).val();
		if ( ! nonce || ! table_id ) { return; }
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_save_stat_visibility',
			wp_easycart_nonce: nonce,
			table_id: table_id,
			hidden: hidden
		});
	}

	/* ===================================================================== */
	/* Filter drawer                                                          */
	/* ===================================================================== */

	window.ecv2_open_filter_drawer = function() {
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).addClass( 'ecv2-drawer-open' );
		ecv2_init_filter_selects();
	};

	window.ecv2_close_filter_drawer = function() {
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).removeClass( 'ecv2-drawer-open' );
	};

	$( document ).on( 'click', '#ecv2-drawer-backdrop, #ecv2-drawer-close', function() {
		window.ecv2_close_filter_drawer();
	});

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
			if ( $( this ).hasClass( 'select2-hidden-accessible' ) ) { return; }
			var $select = $( this );
			var opts = {
				width: '100%',
				dropdownParent: $( '#ecv2-filter-drawer' ),
				placeholder: $select.data( 'placeholder' ) || ''
			};
			var ajax = ecv2_filter_ajax_opts( $select );
			if ( ajax ) {
				opts.ajax = ajax;
				opts.allowClear = true;
				opts.minimumInputLength = parseInt( $select.data( 'ajax-min' ), 10 ) || 0;
			}
			$select.select2( opts );
		});
	}

	window.ecv2_apply_drawer_filters = function() {
		/* Pills already synced hidden inputs; sync selects + ranges then submit. */
		$( '.ecv2-filter-select' ).each( function() {
			var filter_name = $( this ).data( 'filter' );
			$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( $( this ).val() );
		});
		$( '.ecv2-drawer-range-input[data-range="min"]' ).each( function() {
			var filter_name = $( this ).data( 'filter' );
			var min = $( this ).val();
			var max = $( '.ecv2-drawer-range-input[data-range="max"][data-filter="' + filter_name + '"]' ).val();
			var val = ( min !== '' || max !== '' ) ? ( min + '-' + max ) : '';
			$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( val );
		});
		$( 'input[name="pagenum"]' ).val( 1 );
		$( '#ecv2-posts-filter' ).submit();
	};

	window.ecv2_clear_filters = function() {
		$( 'input[id^="ecv2-filter-input-"]' ).val( '' );
		$( '#ecv2-health-filter-input' ).val( '' );
		$( '#ecv2-search-input' ).val( '' );
		$( 'input[name="pagenum"]' ).val( 1 );
		$( '#ecv2-posts-filter' ).submit();
	};

	/* Drawer pill clicks (non-health). */
	$( document ).on( 'click', '.ecv2-drawer-pill:not(.ecv2-drawer-health-pill)', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).data( 'value' );
		$( this ).siblings( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( filter_val );
	});

	/* Drawer health pills. */
	$( document ).on( 'click', '.ecv2-drawer-health-pill', function() {
		var val = $( this ).data( 'health-value' );
		$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-health-filter-input' ).val( val );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( val !== '' ) {
			$( '.ecv2-stat-card[data-filter="' + val + '"]' ).addClass( 'ecv2-stat-active' );
		}
	});

	/* Clear buttons inside drawer groups. */
	$( document ).on( 'click', '.ecv2-drawer-filter-clear', function() {
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		if ( $( this ).hasClass( 'ecv2-drawer-health-clear' ) ) {
			$( '#ecv2-health-filter-input' ).val( '' );
			$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
			$( '.ecv2-drawer-health-pill[data-health-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
			$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		} else {
			var filter_name = $( this ).data( 'filter' );
			$( '#ecv2-filter-input-' + String( filter_name ).replace( 'filter_', '' ) ).val( '' );
			$group.find( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
			$group.find( '.ecv2-drawer-pill[data-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
			$group.find( '.ecv2-filter-select' ).val( '' );
			if ( typeof $.fn.select2 === 'function' ) {
				$group.find( '.ecv2-filter-select' ).trigger( 'change.select2' );
			}
			$group.find( '.ecv2-drawer-range-input' ).val( '' );
		}
		$group.removeClass( 'ecv2-drawer-filter-group-active' );
		$( this ).remove();
	});

	/* Toolbar active tag remove. */
	$( document ).on( 'click', '.ecv2-active-tag-remove', function() {
		var filter = $( this ).data( 'filter' );
		if ( filter === 'health_filter' ) {
			$( '#ecv2-health-filter-input' ).val( '' );
		} else {
			$( '#ecv2-filter-input-' + String( filter ).replace( 'filter_', '' ) ).val( '' );
		}
		$( '#ecv2-posts-filter' ).submit();
	});

	/* ===================================================================== */
	/* Search                                                                 */
	/* ===================================================================== */

	function ecv2_search_submit() {
		$( '#ecv2-search-loading' ).show();
		$( 'input[name="pagenum"]' ).val( 1 );
		$( '#ecv2-posts-filter' ).submit();
	}

	$( document ).on( 'click', '#ecv2-search-submit', ecv2_search_submit );
	$( document ).on( 'keydown', '#ecv2-search-input', function( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			ecv2_search_submit();
		}
	});
	$( document ).on( 'input', '#ecv2-search-input', function() {
		$( '#ecv2-search-clear' ).toggle( $( this ).val() !== '' );
	});
	$( document ).on( 'click', '#ecv2-search-clear', function() {
		$( '#ecv2-search-input' ).val( '' );
		ecv2_search_submit();
	});

	/* ===================================================================== */
	/* Row menus                                                              */
	/* ===================================================================== */

	window.ecv2_toggle_row_menu = function( trigger ) {
		var $menu = $( trigger ).siblings( '.ecv2-row-menu' );
		var was_open = $menu.hasClass( 'ecv2-row-menu-open' );
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
		if ( ! was_open ) {
			$menu.addClass( 'ecv2-row-menu-open' );
			/* Flip up when near the bottom of the viewport. */
			var rect = trigger.getBoundingClientRect();
			$menu.toggleClass( 'ecv2-row-menu-up', ( window.innerHeight - rect.bottom ) < 320 );
		}
	};

	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap' ).length ) {
			$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
		}
		if ( ! $( e.target ).closest( '.ecv2-order-status-wrap' ).length ) {
			$( '.ecv2-order-status-menu' ).removeClass( 'ecv2-order-status-menu-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		}
		if ( ! $( e.target ).closest( '.ecv2-order-items-wrap' ).length ) {
			$( '.ecv2-order-items-pop' ).removeClass( 'ecv2-order-items-pop-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		}
		if ( ! $( e.target ).closest( '#ecv2-stat-toggle-btn, #ecv2-stat-toggle-panel' ).length ) {
			$( '#ecv2-stat-toggle-panel' ).removeClass( 'ecv2-stat-toggle-open' );
		}
	});

	/* ===================================================================== */
	/* Bulk selection + apply                                                 */
	/* ===================================================================== */

	function ecv2_update_bulk_count() {
		var count = ecv2_row_check_count();
		$( '#ecv2-selected-count' ).text( count );
		$( '.ecv2-bulk-count' ).toggle( count > 0 );
	}

	$( document ).on( 'change', '#ecv2-select-all, #ecv2-ss-select-all', function() {
		var checked = $( this ).is( ':checked' );
		$( '.ecv2-row-check' ).prop( 'checked', checked );
		ecv2_update_bulk_count();
	});
	$( document ).on( 'change', '.ecv2-row-check', ecv2_update_bulk_count );

	function ecv2_bulk_collect_ids() {
		var ids = [];
		$( '.ecv2-row-check:checked' ).each( function() {
			var v = $( this ).val();
			if ( ids.indexOf( v ) === -1 ) { ids.push( v ); }
		});
		return ids;
	}

	window.ecv2_bulk_apply_validate = function() {
		var action = $( '#ecv2-bulk-action' ).val();
		if ( ! action ) {
			ecv2_toast( ecv2_lang.select_bulk_action || 'Please choose a bulk action.', 'error' );
			return false;
		}

		var needs_selection = ( action.indexOf( '-all' ) === -1 && action.indexOf( 'mark-all' ) === -1 );
		var ids = ecv2_bulk_collect_ids();
		if ( needs_selection && ids.length === 0 ) {
			ecv2_toast( ecv2_lang.select_orders || 'Please select at least one order.', 'error' );
			return false;
		}

		if ( action === 'change-order-status' ) {
			ecv2_order_open_status_modal( ids.length );
			return false;
		}

		if ( action === 'delete-order' ) {
			ecv2_show_confirm(
				ecv2_lang.delete_orders_title || 'Delete Orders',
				( ecv2_lang.delete_orders_confirm || 'Permanently delete the selected orders? This cannot be undone.' ),
				function() { ecv2_bulk_submit( action ); }
			);
			return false;
		}

		if ( action === 'print-receipt' || action === 'print-packing-slip' ) {
			/* Open the print output in a new tab; keep the list in place. 6.0.1: the list never reloads, so also clear
			   admin.js's double-submit flag, or the next bulk print ( or search, filter, bulk action ) did nothing. */
			var $form = $( '#ecv2-posts-filter' );
			$form.attr( 'target', '_blank' );
			ecv2_bulk_submit( action );
			setTimeout( function() { $form.removeAttr( 'target' ).removeData( 'submitted' ); $( '#ecv2-bulk-action' ).val( '' ); }, 500 );
			return false;
		}

		ecv2_bulk_submit( action );
		return false;
	};

	function ecv2_bulk_submit( action ) {
		$( '#ecv2-bulk-action' ).val( action );
		$( '#ecv2-posts-filter' ).submit();
	}

	/* Bulk edit modal stubs (no bulk_edit_fields on orders in free). */
	window.ecv2_open_bulk_edit = function() {};
	window.ecv2_close_bulk_edit = function() { $( '#ecv2-bulk-edit-modal' ).hide(); };
	window.ecv2_apply_bulk_edit = function() {};

	/* ===================================================================== */
	/* Spreadsheet column picker (used when PRO enables spreadsheet view)     */
	/* ===================================================================== */

	window.ecv2_toggle_ss_column_picker = function() {
		$( '#ecv2-ss-col-picker' ).toggle();
	};

	window.ecv2_toggle_ss_column = function( col_name, visible ) {
		if ( visible ) {
			$( '.ecv2-ss-col-' + col_name ).show();
		} else {
			$( '.ecv2-ss-col-' + col_name ).hide();
		}
		var hidden = [];
		$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
			var col = $( this ).data( 'ss-col' );
			if ( col && ! this.disabled && ! $( this ).is( ':checked' ) ) {
				hidden.push( col );
			}
		});
		document.cookie = 'wpeasycart_ss_hidden_cols=' + hidden.join( ',' ) + ';path=/;max-age=31536000';
	};

	$( document ).ready( function() {
		var match = document.cookie.match( /wpeasycart_ss_hidden_cols=([^;]*)/ );
		if ( match ) {
			var hidden = match[1] ? match[1].split( ',' ) : [];
			$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
				var col = $( this ).data( 'ss-col' );
				if ( ! col || this.disabled ) { return; }
				var should_hide = hidden.indexOf( col ) !== -1;
				$( this ).prop( 'checked', ! should_hide );
				$( '.ecv2-ss-col-' + col ).toggle( ! should_hide );
			});
		}
	});

	/* ===================================================================== */
	/* Order: viewed dot                                                      */
	/* ===================================================================== */

	window.ecv2_order_toggle_viewed = function( btn ) {
		var $btn = $( btn );
		var order_id = parseInt( $btn.data( 'order-id' ), 10 );
		var currently_viewed = $btn.hasClass( 'ecv2-order-dot-viewed' );
		var new_viewed = currently_viewed ? 0 : 1;

		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_toggle_viewed',
			order_id: order_id,
			viewed: new_viewed,
			wp_easycart_nonce: $btn.data( 'nonce' )
		}, function( response ) {
			if ( response && response.success ) {
				ecv2_order_sync_viewed( order_id, !! response.data.viewed );
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.error, 'error' );
		});
	};

	function ecv2_order_sync_viewed( order_id, viewed ) {
		var $dots = $( '.ecv2-order-dot[data-order-id="' + order_id + '"]' );
		$dots.toggleClass( 'ecv2-order-dot-viewed', viewed );
		$( '.ecv2-row[data-id="' + order_id + '"]' ).toggleClass( 'ecv2-row-unviewed', ! viewed );
		$( '.ecv2-order-card[data-id="' + order_id + '"]' ).toggleClass( 'ecv2-order-card-unviewed', ! viewed );
	}

	/* Opening an order marks it viewed (fire and forget). */
	window.ecv2_order_open_marks_viewed = function( order_id ) {
		var $dot = $( '.ecv2-order-dot[data-order-id="' + order_id + '"]' ).first();
		if ( ! $dot.length || $dot.hasClass( 'ecv2-order-dot-viewed' ) ) { return true; }
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_toggle_viewed',
			order_id: order_id,
			viewed: 1,
			wp_easycart_nonce: $dot.data( 'nonce' )
		});
		return true;
	};

	/* ===================================================================== */
	/* Order: inline status change                                            */
	/* ===================================================================== */

	/* Popups inside the table are position:fixed while open so the row / scroll
	   wrapper can't clip them; coordinates come from the anchor's viewport rect. */
	function ecv2_place_fixed_pop( $pop, anchor, est_height ) {
		var rect = anchor.getBoundingClientRect();
		var vw = document.documentElement.clientWidth, vh = window.innerHeight;
		var w  = $pop.outerWidth() || 240;
		var h  = $pop.outerHeight() || est_height;
		var left = Math.min( Math.max( 8, rect.left ), vw - w - 8 );
		var up   = ( vh - rect.bottom ) < ( h + 12 ) && rect.top > ( h + 12 );
		var top  = up ? rect.top - h - 5 : rect.bottom + 5;
		$pop.css({ position:'fixed', left:left + 'px', top:top + 'px', bottom:'auto' });
	}
	function ecv2_clear_fixed_pop( $pop ) {
		$pop.css({ position:'', left:'', top:'', bottom:'' });
	}
	$( window ).on( 'scroll resize', function() {
		if ( $( '.ecv2-order-status-menu-open, .ecv2-order-items-pop-open' ).length ) {
			$( '.ecv2-order-status-menu' ).removeClass( 'ecv2-order-status-menu-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
			$( '.ecv2-order-items-pop' ).removeClass( 'ecv2-order-items-pop-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		}
	});

	window.ecv2_order_open_status_menu = function( btn ) {
		var $menu = $( btn ).siblings( '.ecv2-order-status-menu' );
		var was_open = $menu.hasClass( 'ecv2-order-status-menu-open' );
		$( '.ecv2-order-status-menu' ).removeClass( 'ecv2-order-status-menu-open ecv2-order-status-menu-up' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		if ( ! was_open ) {
			$menu.addClass( 'ecv2-order-status-menu-open' );
			ecv2_place_fixed_pop( $menu, btn, 320 );
		}
	};

	window.ecv2_order_set_status = function( item ) {
		var $item = $( item );
		var $wrap = $item.closest( '.ecv2-order-status-wrap' );
		var order_id = parseInt( $wrap.data( 'order-id' ), 10 );
		var old_status_id = parseInt( $wrap.data( 'status-id' ), 10 );
		var status_id = parseInt( $item.data( 'status-id' ), 10 );

		$wrap.find( '.ecv2-order-status-menu' ).removeClass( 'ecv2-order-status-menu-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		if ( status_id === old_status_id ) { return; }

		ecv2_order_send_status( $wrap, order_id, status_id, function( data ) {
			ecv2_toast( ecv2_lang.order_status_updated || 'Order status updated.', 'success' );
			ecv2_push_undo({
				message: ( ecv2_lang.order_status_updated || 'Order status updated.' ) + ' ' + ( ecv2_lang.click_undo || '' ),
				undo_fn: function() {
					ecv2_order_send_status( $wrap, order_id, data.old_status_id, function() {
						ecv2_toast( ecv2_lang.undone || 'Change reverted.', 'info' );
					});
				}
			});
		});
	};

	function ecv2_order_send_status( $wrap, order_id, status_id, on_success ) {
		$wrap.addClass( 'ecv2-order-status-saving' );
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_set_status',
			order_id: order_id,
			status_id: status_id,
			wp_easycart_nonce: $wrap.data( 'nonce' )
		}, function( response ) {
			$wrap.removeClass( 'ecv2-order-status-saving' );
			if ( response && response.success ) {
				ecv2_order_sync_status_chip( order_id, response.data );
				if ( typeof on_success === 'function' ) { on_success( response.data ); }
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
			}
		}).fail( function() {
			$wrap.removeClass( 'ecv2-order-status-saving' );
			ecv2_toast( ecv2_lang.error, 'error' );
		});
	}

	function ecv2_order_sync_status_chip( order_id, data ) {
		$( '.ecv2-order-status-wrap[data-order-id="' + order_id + '"]' ).each( function() {
			var $wrap = $( this );
			$wrap.attr( 'data-status-id', data.status_id ).data( 'status-id', data.status_id );
			$wrap.find( '.ecv2-order-status-chip .ecv2-order-status-label' ).text( data.label );
			$wrap.find( '.ecv2-order-status-chip .ecv2-order-status-swatch' ).css( 'background', data.color );
			$wrap.find( '.ecv2-order-status-chip' ).css( 'border-color', data.color );
			$wrap.find( '.ecv2-order-status-item' ).each( function() {
				var active = parseInt( $( this ).data( 'status-id' ), 10 ) === parseInt( data.status_id, 10 );
				$( this ).toggleClass( 'ecv2-order-status-item-active', active );
				$( this ).find( '.ecv2-order-status-check' ).remove();
				if ( active ) {
					$( this ).append( '<span class="dashicons dashicons-yes ecv2-order-status-check"></span>' );
				}
			});
		});
		ecv2_order_sync_fulfill_for_status( order_id, data.status_id );
	}

	/*
	 * 6.0.0: Order Shipped / Picked Up counts as fulfilled without a tracking number ( same rule as the order details
	 * banner ). Rows with tracking or nothing to ship keep their chip; a fulfilled chip turns back into the Fulfill button
	 * when the status moves off shipped ( or a dash for refunded / cancelled ).
	 */
	function ecv2_order_sync_fulfill_for_status( order_id, status_id ) {
		var L = window.ecv2_lang || {};
		var done_ids = ( L.fulfilled_status_ids || [ 2, 18 ] ).map( function( v ) { return parseInt( v, 10 ); } );
		status_id = parseInt( status_id, 10 );
		$( '.ecv2-order-fulfill-wrap[data-order-id="' + order_id + '"]' ).each( function() {
			var $wrap = $( this ), $state = $wrap.find( '.ecv2-order-fulfill-state' );
			/* 6.0.1: "Picked up" when the status is Order Picked Up; a Free Local Pickup row offers "Mark picked up". */
			var pickup = '1' === String( $wrap.attr( 'data-pickup' ) || '' );
			var chip = ( 18 === status_id && L.fulfilled_chip_pickup ) ? L.fulfilled_chip_pickup : L.fulfilled_chip;
			var button = ( pickup && L.fulfill_button_pickup ) ? L.fulfill_button_pickup : L.fulfill_button;
			if ( $wrap.attr( 'data-tracking' ) || $state.find( '.ecv2-order-fulfill-digital' ).length ) { return; }
			if ( done_ids.indexOf( status_id ) !== -1 ) {
				if ( chip ) { $state.html( chip ); }
			} else if ( $state.find( '.ecv2-order-fulfill-done' ).length ) {
				$state.html( ( 16 === status_id || 19 === status_id || ! button ) ? '<span class="ecv2-sku-empty">&mdash;</span>' : button );
			}
		});
	}
	window.ecv2_order_sync_fulfill_for_status = ecv2_order_sync_fulfill_for_status;

	/* ===================================================================== */
	/* Order: bulk status modal (feeds V1 GET handler)                        */
	/* ===================================================================== */

	function ecv2_order_open_status_modal( count ) {
		$( '#ecv2-order-status-modal-count' ).text( '(' + count + ')' );
		$( '#ecv2-order-status-modal' ).css( 'display', 'flex' );
	}
	window.ecv2_order_open_status_modal = ecv2_order_open_status_modal;

	window.ecv2_order_close_status_modal = function() {
		$( '#ecv2-order-status-modal' ).hide();
	};

	window.ecv2_order_apply_status_modal = function() {
		var status_id = $( '#ecv2-order-status-modal-select' ).val();
		$( 'input[name="bulk_order_status"]' ).val( status_id );
		$( '#ecv2-order-status-modal' ).hide();
		$( '#ecv2-bulk-action' ).val( 'change-order-status' );
		$( '#ecv2-posts-filter' ).submit();
	};

	/* ===================================================================== */
	/* Order: items popover, copy buttons                                     */
	/* ===================================================================== */

	window.ecv2_order_toggle_items = function( btn ) {
		var $pop = $( btn ).siblings( '.ecv2-order-items-pop' );
		var was_open = $pop.hasClass( 'ecv2-order-items-pop-open' );
		$( '.ecv2-order-items-pop' ).removeClass( 'ecv2-order-items-pop-open' ).each( function() { ecv2_clear_fixed_pop( $( this ) ); } );
		if ( ! was_open ) {
			$pop.addClass( 'ecv2-order-items-pop-open' );
			ecv2_place_fixed_pop( $pop, btn, 200 );
		}
	};

	window.ecv2_order_copy = function( btn ) {
		var text = $( btn ).data( 'copy' );
		var done = function() {
			ecv2_toast( ecv2_lang.copied || 'Copied to clipboard.', 'info' );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done );
		} else {
			var $tmp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
			try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
			$tmp.remove();
		}
	};

	/* ===================================================================== */
	/* Quick Edit drawer (V2) — replaces the legacy slideout                  */
	/* ===================================================================== */

	var $qe = $( '#ecv2-order-qe' );
	var qe = { order_id: 0, data: null, email_touched: false, all_items: false, initial: '' };

	function qe_lang( key ) { return $qe.data( 'lang-' + key ) || ''; }

	function qe_snapshot() {
		return [
			$( '#ecv2-qe-status' ).val(),
			$( '#ecv2-qe-carrier' ).val(),
			$( '#ecv2-qe-tracking' ).val(),
			$( '#ecv2-qe-method' ).val(),
			$( '#ecv2-qe-expedited' ).is( ':checked' ) ? 1 : 0
		].join( '|' );
	}

	/* Overrides the legacy global from orders.js/admin.js for the V2 list. */
	window.wp_easycart_open_order_quick_edit = function( order_id ) {
		if ( ! $qe.length ) { return false; }
		qe.order_id = parseInt( order_id, 10 );
		qe.data = null;
		qe.email_touched = false;
		qe.all_items = false;

		$( '#ecv2-qe-order-id' ).text( '#' + qe.order_id );
		$( '#ecv2-qe-content' ).hide();
		$( '#ecv2-qe-loading' ).show();
		$( '#ecv2-order-qe-backdrop' ).addClass( 'ecv2-drawer-open' );
		$qe.addClass( 'ecv2-drawer-open' );
		$( 'body' ).addClass( 'ecv2-qe-open' );

		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_quick_edit_get',
			order_id: qe.order_id,
			wp_easycart_nonce: $qe.data( 'nonce' )
		}, function( response ) {
			if ( response && response.success ) {
				qe.data = response.data;
				qe_render( response.data );
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
				window.ecv2_order_qe_close();
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.error, 'error' );
			window.ecv2_order_qe_close();
		});
		return false;
	};

	window.ecv2_order_qe_close = function() {
		$( '#ecv2-order-qe-backdrop' ).removeClass( 'ecv2-drawer-open' );
		$qe.removeClass( 'ecv2-drawer-open' );
		$( 'body' ).removeClass( 'ecv2-qe-open' );
		qe.order_id = 0;
	};

	function qe_render( d ) {
		var e = ecv2_esc_html;

		$( '#ecv2-qe-customer-name' ).text( d.customer_name || d.email || '' );
		$( '#ecv2-qe-customer-company' ).text( d.customer_company || '' ).toggle( !! d.customer_company );
		$( '#ecv2-qe-email' ).text( d.email || '' );
		$( '#ecv2-qe-email-line' ).toggle( !! d.email ).find( '.ecv2-order-copy-btn' ).attr( 'data-copy', d.email || '' );
		$( '#ecv2-qe-phone' ).text( d.phone || '' ).toggle( !! d.phone );
		$( '#ecv2-qe-total' ).text( d.total || '' );
		$( '#ecv2-qe-refund' ).text( d.refund_total ? '−' + d.refund_total : '' ).toggle( !! d.refund_total );
		$( '#ecv2-qe-payment' ).text( d.payment || '' ).toggle( !! d.payment );
		$( '#ecv2-qe-date' ).text( d.date || '' );

		var flags = '';
		if ( d.flags && d.flags.pickup )       { flags += '<span class="ecv2-order-badge ecv2-order-badge-flag"><span class="dashicons dashicons-store"></span>Pickup</span>'; }
		if ( d.flags && d.flags.preorder )     { flags += '<span class="ecv2-order-badge ecv2-order-badge-flag"><span class="dashicons dashicons-backup"></span>Preorder</span>'; }
		if ( d.flags && d.flags.subscription ) { flags += '<span class="ecv2-order-badge ecv2-order-badge-flag"><span class="dashicons dashicons-controls-repeat"></span>Subscription</span>'; }
		$( '#ecv2-qe-flags' ).html( flags ).toggle( flags !== '' );

		$( '#ecv2-qe-customer-notes' ).toggle( !! d.customer_notes ).find( 'span:last' ).text( d.customer_notes || '' );

		qe_render_items( d );

		var addr = ( d.ship_to || [] ).map( e ).join( '<br>' );
		if ( d.ship_phone ) { addr += '<br><span class="ecv2-qe-muted">' + e( d.ship_phone ) + '</span>'; }
		$( '#ecv2-qe-address' ).html( addr || '<span class="ecv2-qe-muted">—</span>' );
		$( '#ecv2-qe-copy-address' ).attr( 'data-copy', ( d.ship_to || [] ).join( '\n' ) ).toggle( ( d.ship_to || [] ).length > 0 );

		$( '#ecv2-qe-status' ).val( String( d.status_id ) );
		$( '#ecv2-qe-carrier' ).val( d.carrier || '' );
		$( '#ecv2-qe-tracking' ).val( d.tracking || '' );
		$( '#ecv2-qe-method' ).val( d.method || '' );
		$( '#ecv2-qe-expedited' ).prop( 'checked', !! parseInt( d.expedited, 10 ) );
		$( '#ecv2-qe-send-email' ).prop( 'checked', false );
		$( '#ecv2-qe-full-link' ).attr( 'href', d.edit_url || '#' );

		qe_sync_status_pill();
		qe_sync_email_hint();
		/* PRO fields populate themselves from the same payload. */
		$( document ).trigger( 'ecv2_order_qe_loaded', [ d ] );
		qe.initial = qe_snapshot();

		$( '#ecv2-qe-loading' ).hide();
		$( '#ecv2-qe-content' ).show();
		setTimeout( function() { $( '#ecv2-qe-status' ).trigger( 'focus' ); }, 50 );
	}

	function qe_render_items( d ) {
		var e = ecv2_esc_html;
		var items = d.items || [];
		var limit = qe.all_items ? items.length : 4;
		var html = '';
		for ( var i = 0; i < Math.min( limit, items.length ); i++ ) {
			var it = items[ i ];
			html += '<li class="ecv2-qe-item">';
			html += '<span class="ecv2-qe-item-qty">' + e( it.qty ) + '×</span>';
			html += '<span class="ecv2-qe-item-main"><span class="ecv2-qe-item-title">' + e( it.title ) + '</span>';
			if ( it.sku ) { html += '<span class="ecv2-sku-chip">' + e( it.sku ) + '</span>'; }
			if ( it.refunded > 0 ) { html += '<span class="ecv2-order-badge ecv2-order-badge-refund">' + e( qe_lang( 'refunded' ).replace( '%d', it.refunded ) ) + '</span>'; }
			html += '</span>';
			html += '<span class="ecv2-qe-item-total">' + e( it.total ) + '</span>';
			html += '</li>';
		}
		$( '#ecv2-qe-items' ).html( html );
		$( '#ecv2-qe-item-count' ).text( d.item_qty ? d.item_qty : items.length );
		var $more = $( '#ecv2-qe-items-more' );
		if ( items.length > limit ) {
			$more.text( qe_lang( 'more' ).replace( '%d', items.length - limit ) ).show();
		} else {
			$more.hide();
		}
	}

	window.ecv2_order_qe_show_all_items = function() {
		qe.all_items = true;
		if ( qe.data ) { qe_render_items( qe.data ); }
	};

	function qe_sync_status_pill() {
		var $opt = $( '#ecv2-qe-status option:selected' );
		var color = $opt.data( 'color' ) || '#e5e7eb';
		$( '#ecv2-qe-status-swatch' ).css( 'background', color );
		$( '#ecv2-qe-status-pill' ).css( 'border-color', color ).find( '.ecv2-order-status-swatch' ).css( 'background', color );
		$( '#ecv2-qe-status-pill .ecv2-qe-status-pill-label' ).text( $opt.text() );
	}

	function qe_is_shipped_status() {
		return parseInt( $( '#ecv2-qe-status' ).val(), 10 ) === parseInt( $qe.data( 'shipped-id' ), 10 );
	}

	function qe_sync_email_hint() {
		var on = $( '#ecv2-qe-send-email' ).is( ':checked' );
		$( '#ecv2-qe-email-hint' ).text( on ? qe_lang( 'email-hint-on' ) : qe_lang( 'email-hint-off' ) );
		$( '#ecv2-qe-notify' ).toggleClass( 'ecv2-qe-notify-on', on );
	}

	/* Auto-suggest the shipping email when the admin adds tracking or marks shipped — until they touch the box themselves. */
	function qe_auto_email() {
		if ( qe.email_touched || ! qe.data ) { return; }
		var tracking_added = $( '#ecv2-qe-tracking' ).val() !== '' && $( '#ecv2-qe-tracking' ).val() !== ( qe.data.tracking || '' );
		var newly_shipped  = qe_is_shipped_status() && parseInt( qe.data.status_id, 10 ) !== parseInt( $( '#ecv2-qe-status' ).val(), 10 );
		$( '#ecv2-qe-send-email' ).prop( 'checked', tracking_added || newly_shipped );
		qe_sync_email_hint();
	}

	window.ecv2_order_qe_status_changed = function() { qe_sync_status_pill(); qe_auto_email(); };
	window.ecv2_order_qe_tracking_changed = function() { qe_auto_email(); };
	window.ecv2_order_qe_email_touched = function() { qe.email_touched = true; qe_sync_email_hint(); };

	window.ecv2_order_qe_save = function() {
		if ( ! qe.order_id ) { return; }
		var send_email = $( '#ecv2-qe-send-email' ).is( ':checked' ) ? 1 : 0;
		if ( qe_snapshot() === qe.initial && ! send_email ) {
			ecv2_toast( qe_lang( 'nochange' ), 'info' );
			return;
		}
		var $btn = $( '#ecv2-qe-save' ).prop( 'disabled', true );
		var payload = {
			action: 'ecv2_order_quick_edit_save',
			order_id: qe.order_id,
			status_id: $( '#ecv2-qe-status' ).val(),
			carrier: $( '#ecv2-qe-carrier' ).val(),
			tracking: $( '#ecv2-qe-tracking' ).val(),
			method: $( '#ecv2-qe-method' ).val(),
			expedited: $( '#ecv2-qe-expedited' ).is( ':checked' ) ? 1 : 0,
			send_email: send_email,
			wp_easycart_nonce: $qe.data( 'nonce' )
		};
		/* PRO fields can append to the payload. */
		$( document ).trigger( 'ecv2_order_qe_before_save', [ payload, qe.order_id ] );

		$.post( wpeasycart_admin_ajax_object.ajax_url, payload, function( response ) {
			$btn.prop( 'disabled', false );
			if ( response && response.success ) {
				var d = response.data;
				ecv2_order_sync_status_chip( d.order_id, d );
				qe_sync_row_fulfillment( d );
				ecv2_toast( d.email_sent ? qe_lang( 'saved-email' ) : qe_lang( 'saved' ), 'success' );
				$( document ).trigger( 'ecv2_order_qe_saved', [ d ] );
				window.ecv2_order_qe_close();
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
			}
		}).fail( function() {
			$btn.prop( 'disabled', false );
			ecv2_toast( ecv2_lang.error, 'error' );
		});
	};

	/* Update the list row in place so no reload is needed. */
	function qe_sync_row_fulfillment( d ) {
		$( '.ecv2-order-fulfill-wrap[data-order-id="' + d.order_id + '"]' ).each( function() {
			var $wrap = $( this );
			$wrap.attr( 'data-tracking', d.tracking ).data( 'tracking', d.tracking );
			$wrap.attr( 'data-carrier', d.carrier ).data( 'carrier', d.carrier );

			var $state = $wrap.find( '.ecv2-order-fulfill-state' );
			if ( d.chip_html ) {
				$state.html( d.chip_html );
			} else if ( $state.find( '.ecv2-order-track-chip' ).not( '.ecv2-order-fulfill-digital, .ecv2-order-fulfill-done' ).length ) {
				/* Tracking was cleared; row will show the Fulfill button again on reload. */
				$state.html( '<span class="ecv2-sku-empty">&mdash;</span>' );
			}
			/* No tracking: a shipped / picked up status still reads as fulfilled. */
			if ( ! d.chip_html && d.status_id ) { ecv2_order_sync_fulfill_for_status( d.order_id, d.status_id ); }

			var $line2 = $wrap.find( '.ecv2-order-fulfill-line2' );
			var $meta  = $line2.find( '.ecv2-order-fulfill-meta' );
			if ( d.method ) {
				if ( ! $line2.length ) { $line2 = $( '<div class="ecv2-order-fulfill-line2"></div>' ).insertAfter( $state ); }
				if ( ! $meta.length ) { $meta = $( '<span class="ecv2-order-fulfill-meta"></span>' ).prependTo( $line2 ); }
				$meta.text( d.method ).attr( 'title', d.method );
			} else {
				$meta.remove();
			}
		});
	}

	/* Keyboard: Esc closes, Ctrl/Cmd+Enter saves. */
	$( document ).on( 'keydown', function( ev ) {
		if ( ! $qe.hasClass( 'ecv2-drawer-open' ) ) { return; }
		if ( ev.key === 'Escape' ) { window.ecv2_order_qe_close(); }
		if ( ev.key === 'Enter' && ( ev.ctrlKey || ev.metaKey ) ) { ev.preventDefault(); window.ecv2_order_qe_save(); }
	});

	/* ===================================================================== */
	/* Duplicate order drawer (V2) — replaces the legacy slideout             */
	/* ===================================================================== */

	var $dup = $( '#ecv2-order-dup' );
	var dup = { order_id: 0, data: null, fmt: null };

	function dup_lang( key ) { return $dup.data( 'lang-' + key ) || ''; }

	/* Build a money formatter from the server's sample of 1234.56. */
	function dup_build_formatter( sample ) {
		sample = String( sample || '$1,234.56' );
		var m = sample.match( /^(.*?)(\d[\d\s.,'’]*\d)(.*)$/ );
		if ( ! m ) { return function( n ) { return n.toFixed( 2 ); }; }
		var prefix = m[ 1 ], body = m[ 2 ], suffix = m[ 3 ];
		var seps = body.replace( /\d/g, '' );
		var dec_sep = seps.length ? seps.charAt( seps.length - 1 ) : '.';
		var thou_sep = seps.length > 1 ? seps.charAt( 0 ) : ( seps.length === 1 && body.split( seps ).pop().length === 3 ? seps : '' );
		if ( thou_sep === dec_sep && seps.length === 1 ) { dec_sep = body.split( seps ).pop().length === 3 ? '' : dec_sep; }
		var decimals = dec_sep ? body.split( dec_sep ).pop().length : 0;
		if ( decimals > 4 ) { decimals = 2; }
		return function( n ) {
			var neg = n < 0; n = Math.abs( n );
			var parts = n.toFixed( decimals ).split( '.' );
			var int_part = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, thou_sep );
			return ( neg ? '−' : '' ) + prefix + int_part + ( decimals ? dec_sep + parts[ 1 ] : '' ) + suffix;
		};
	}

	window.wp_easycart_open_order_duplicate = function( order_id ) {
		if ( ! $dup.length ) { return false; }
		dup.order_id = parseInt( order_id, 10 );
		dup.data = null;
		$( '#ecv2-dup-order-id' ).text( '#' + dup.order_id );
		$( '#ecv2-dup-content' ).hide();
		$( '#ecv2-dup-loading' ).show();
		$( '#ecv2-order-dup-backdrop' ).addClass( 'ecv2-drawer-open' );
		$dup.addClass( 'ecv2-drawer-open' );
		$( 'body' ).addClass( 'ecv2-qe-open' );

		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecv2_order_duplicate_get',
			order_id: dup.order_id,
			wp_easycart_nonce: $dup.data( 'nonce' )
		}, function( response ) {
			if ( response && response.success ) {
				dup.data = response.data;
				dup.fmt = dup_build_formatter( response.data.currency_sample );
				dup_render( response.data );
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
				window.ecv2_order_dup_close();
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.error, 'error' );
			window.ecv2_order_dup_close();
		});
		return false;
	};

	/* 6.0.0: the order details menu links here with ?ecv2_duplicate=ID ( the drawer only exists on the list ). */
	var dup_auto = ( window.location.search.match( /[?&]ecv2_duplicate=(\d+)/ ) || [] )[ 1 ];
	if ( dup_auto ) {
		window.wp_easycart_open_order_duplicate( dup_auto );
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', window.location.href.replace( /([?&])ecv2_duplicate=\d+&?/, '$1' ).replace( /[?&]$/, '' ) );
		}
	}

	window.ecv2_order_dup_close = function() {
		$( '#ecv2-order-dup-backdrop' ).removeClass( 'ecv2-drawer-open' );
		$dup.removeClass( 'ecv2-drawer-open' );
		$( 'body' ).removeClass( 'ecv2-qe-open' );
		dup.order_id = 0;
	};

	function dup_render( d ) {
		var e = ecv2_esc_html;
		$( '#ecv2-dup-customer' ).text( d.customer_name || d.email || '' );
		$( '#ecv2-dup-email' ).text( d.email || '' ).toggle( !! d.email );
		$( '#ecv2-dup-source-meta' ).text( '#' + d.order_id + ' · ' + ( d.date || '' ) + ' · ' + ( d.status_label || '' ) );
		$( '#ecv2-dup-address' ).html( ( d.ship_to || [] ).map( e ).join( ', ' ) || '<span class="ecv2-qe-muted">—</span>' );

		var html = '';
		for ( var i = 0; i < d.items.length; i++ ) {
			var it = d.items[ i ];
			var checked = it.default_qty > 0 ? ' checked' : '';
			html += '<li class="ecv2-dup-item" data-id="' + it.id + '" data-unit="' + it.unit_price + '">';
			html += '<label class="ecv2-dup-item-check"><input type="checkbox" class="ecv2-dup-item-cb"' + checked + ' onchange="ecv2_order_dup_recalc();" /></label>';
			html += '<div class="ecv2-dup-item-main"><span class="ecv2-qe-item-title">' + e( it.title ) + '</span>';
			if ( it.sku ) { html += '<span class="ecv2-sku-chip">' + e( it.sku ) + '</span>'; }
			if ( it.is_download ) { html += '<span class="ecv2-order-badge ecv2-order-badge-flag">' + e( dup_lang( 'download' ) ) + '</span>'; }
			if ( it.is_giftcard ) { html += '<span class="ecv2-order-badge ecv2-order-badge-flag">' + e( dup_lang( 'giftcard' ) ) + '</span>'; }
			if ( it.refunded > 0 ) { html += '<span class="ecv2-order-badge ecv2-order-badge-refund">' + e( dup_lang( 'refunded' ).replace( '%d', it.refunded ) ) + '</span>'; }
			html += '<span class="ecv2-qe-muted ecv2-dup-item-unit">' + e( it.unit_display ) + '</span>';
			html += '</div>';
			html += '<div class="ecv2-dup-item-qty"><button type="button" class="ecv2-dup-qty-btn" onclick="ecv2_order_dup_qty( this, -1 );" aria-label="−">−</button>';
			html += '<input type="number" class="ecv2-dup-qty-input" min="0" step="1" value="' + Math.max( 1, it.default_qty ) + '" oninput="ecv2_order_dup_recalc();" />';
			html += '<button type="button" class="ecv2-dup-qty-btn" onclick="ecv2_order_dup_qty( this, 1 );" aria-label="+">+</button></div>';
			html += '<span class="ecv2-dup-item-total" data-role="line-total"></span>';
			html += '</li>';
		}
		$( '#ecv2-dup-items' ).html( html );

		$( '#ecv2-dup-shipping-amt' ).text( '(' + dup.fmt( d.totals.shipping_total ) + ')' );
		$( '#ecv2-dup-discount-amt' ).text( d.totals.discount_total > 0 ? '(−' + dup.fmt( d.totals.discount_total ) + ( d.totals.promo_code ? ' · ' + d.totals.promo_code : '' ) + ')' : '' );
		$( '#ecv2-dup-discount-row' ).toggle( d.totals.discount_total > 0 );
		$( '#ecv2-dup-method-label' ).text( d.shipping.method ? '(' + d.shipping.method + ')' : '' );
		$( '#ecv2-dup-keep-method' ).prop( 'checked', !! d.shipping.method );
		$( '#ecv2-dup-copy-cust-notes' ).closest( 'label' ).toggle( !! d.has_customer_notes );
		$( '#ecv2-dup-note' ).val( '' );
		$( '#ecv2-dup-send-receipt' ).prop( 'checked', false );
		$( 'input[name="ecv2_dup_reason"][value="reorder"]' ).prop( 'checked', true );

		window.ecv2_order_dup_reason_changed();
		$( '#ecv2-dup-loading' ).hide();
		$( '#ecv2-dup-content' ).show();
	}

	/* Presets: each reason sets sensible defaults; "custom" leaves everything alone. */
	window.ecv2_order_dup_reason_changed = function() {
		var reason = $( 'input[name="ecv2_dup_reason"]:checked' ).val();
		var $status = $( '#ecv2-dup-status' );
		if ( reason === 'reorder' ) {
			$( 'input[name="ecv2_dup_pricing"][value="copy"]' ).prop( 'checked', true );
			$( '#ecv2-dup-include-shipping, #ecv2-dup-include-discount' ).prop( 'checked', true );
			/* Unpaid: pick "Pending Approval" (12) if present, else the original status. */
			$status.val( $status.find( 'option[value="12"]' ).length ? '12' : String( dup.data.status_id ) );
			$( '#ecv2-dup-send-receipt' ).prop( 'checked', false );
		} else if ( reason === 'replacement' ) {
			$( 'input[name="ecv2_dup_pricing"][value="zero"]' ).prop( 'checked', true );
			$( '#ecv2-dup-include-shipping, #ecv2-dup-include-discount' ).prop( 'checked', false );
			$status.val( String( $dup.data( 'default-status' ) ) );
			$( '#ecv2-dup-send-receipt' ).prop( 'checked', false );
		}
		window.ecv2_order_dup_status_changed();
		window.ecv2_order_dup_recalc();
	};

	window.ecv2_order_dup_status_changed = function() {
		var color = $( '#ecv2-dup-status option:selected' ).data( 'color' ) || '#e5e7eb';
		$( '#ecv2-dup-status-swatch' ).css( 'background', color );
	};

	window.ecv2_order_dup_qty = function( btn, delta ) {
		var $input = $( btn ).siblings( '.ecv2-dup-qty-input' );
		var v = Math.max( 0, ( parseInt( $input.val(), 10 ) || 0 ) + delta );
		$input.val( v );
		if ( v > 0 ) { $input.closest( '.ecv2-dup-item' ).find( '.ecv2-dup-item-cb' ).prop( 'checked', true ); }
		window.ecv2_order_dup_recalc();
	};

	window.ecv2_order_dup_toggle_all = function() {
		var $cbs = $( '.ecv2-dup-item-cb' );
		var all = $cbs.length === $cbs.filter( ':checked' ).length;
		$cbs.prop( 'checked', ! all );
		window.ecv2_order_dup_recalc();
	};

	function dup_collect_items() {
		var items = {};
		$( '.ecv2-dup-item' ).each( function() {
			var $li = $( this );
			var qty = parseInt( $li.find( '.ecv2-dup-qty-input' ).val(), 10 ) || 0;
			if ( $li.find( '.ecv2-dup-item-cb' ).is( ':checked' ) && qty > 0 ) {
				items[ $li.data( 'id' ) ] = qty;
			}
		});
		return items;
	}

	window.ecv2_order_dup_recalc = function() {
		if ( ! dup.data ) { return; }
		var d = dup.data, t = d.totals;
		var zero = $( 'input[name="ecv2_dup_pricing"]:checked' ).val() === 'zero';
		var inc_ship = $( '#ecv2-dup-include-shipping' ).is( ':checked' ) && ! zero;
		var inc_disc = $( '#ecv2-dup-include-discount' ).is( ':checked' ) && ! zero;
		$( '#ecv2-dup-pricing-opts' ).toggleClass( 'ecv2-dup-opts-disabled', zero );

		var sub = 0, selected = 0;
		$( '.ecv2-dup-item' ).each( function() {
			var $li = $( this );
			var on = $li.find( '.ecv2-dup-item-cb' ).is( ':checked' );
			var qty = parseInt( $li.find( '.ecv2-dup-qty-input' ).val(), 10 ) || 0;
			var line = ( on && ! zero ) ? qty * parseFloat( $li.data( 'unit' ) ) : 0;
			$li.toggleClass( 'ecv2-dup-item-off', ! on || qty === 0 );
			$li.find( '[data-role="line-total"]' ).text( on ? ( zero ? dup_lang( 'free' ) : dup.fmt( line ) ) : '' );
			if ( on && qty > 0 ) { selected++; sub += line; }
		});

		var ratio = ( t.sub_total > 0 && ! zero ) ? Math.min( 1, sub / t.sub_total ) : 0;
		var tax  = t.tax_total * ratio;
		var ship = inc_ship ? t.shipping_total : 0;
		var disc = inc_disc ? t.discount_total * ratio : 0;
		var grand = Math.max( 0, sub + tax + ship - disc );

		$( '#ecv2-dup-t-sub' ).text( dup.fmt( sub ) );
		$( '#ecv2-dup-t-tax' ).text( dup.fmt( tax ) );
		$( '#ecv2-dup-t-ship' ).text( dup.fmt( ship ) );
		$( '#ecv2-dup-t-disc' ).text( disc > 0 ? '−' + dup.fmt( disc ) : dup.fmt( 0 ) );
		$( '#ecv2-dup-t-disc-row' ).toggle( t.discount_total > 0 );
		$( '#ecv2-dup-t-grand' ).text( dup.fmt( grand ) );
		$( '#ecv2-dup-tax-hint' ).toggle( ! zero && ratio > 0 && ratio < 0.999 );

		$( '#ecv2-dup-item-count' ).text( dup_lang( 'items-selected' ).replace( '%1$d', selected ).replace( '%2$d', d.items.length ) );
		$( '#ecv2-dup-toggle-all' ).text( selected === d.items.length ? dup_lang( 'clear-all' ) : dup_lang( 'select-all' ) );
		$( '#ecv2-dup-footer-summary' ).text( selected ? dup.fmt( grand ) : '' );
		$( '#ecv2-dup-create' ).prop( 'disabled', selected === 0 );
	};

	window.ecv2_order_dup_create = function() {
		if ( ! dup.order_id ) { return; }
		var items = dup_collect_items();
		if ( $.isEmptyObject( items ) ) { ecv2_toast( dup_lang( 'no-items' ), 'error' ); return; }

		var $btn = $( '#ecv2-dup-create' ).prop( 'disabled', true );
		var open_after = $( '#ecv2-dup-open-after' ).is( ':checked' );
		var payload = {
			action: 'ecv2_order_duplicate_create',
			order_id: dup.order_id,
			reason: $( 'input[name="ecv2_dup_reason"]:checked' ).val(),
			items: items,
			status_id: $( '#ecv2-dup-status' ).val(),
			pricing: $( 'input[name="ecv2_dup_pricing"]:checked' ).val(),
			include_shipping: $( '#ecv2-dup-include-shipping' ).is( ':checked' ) ? 1 : 0,
			include_discount: $( '#ecv2-dup-include-discount' ).is( ':checked' ) ? 1 : 0,
			keep_method: $( '#ecv2-dup-keep-method' ).is( ':checked' ) ? 1 : 0,
			copy_customer_notes: $( '#ecv2-dup-copy-cust-notes' ).is( ':checked' ) ? 1 : 0,
			send_receipt: $( '#ecv2-dup-send-receipt' ).is( ':checked' ) ? 1 : 0,
			mark_viewed: $( '#ecv2-dup-mark-viewed' ).is( ':checked' ) ? 1 : 0,
			note: $( '#ecv2-dup-note' ).val(),
			wp_easycart_nonce: $dup.data( 'nonce' )
		};
		$( document ).trigger( 'ecv2_order_dup_before_create', [ payload, dup.order_id ] );

		$.post( wpeasycart_admin_ajax_object.ajax_url, payload, function( response ) {
			$btn.prop( 'disabled', false );
			if ( response && response.success ) {
				var r = response.data;
				$( document ).trigger( 'ecv2_order_dup_created', [ r ] );
				window.ecv2_order_dup_close();
				if ( open_after && r.edit_url ) {
					window.location.href = r.edit_url;
					return;
				}
				var msg = ( r.email_sent ? dup_lang( 'created-email' ) : dup_lang( 'created' ) ).replace( '%d', r.order_id );
				ecv2_toast( msg + ' ', 'success' );
				/* Refresh so the new row appears at the top of the list. */
				setTimeout( function() { window.location.reload(); }, 900 );
			} else {
				ecv2_toast( ( response && response.data && response.data.message ) || ecv2_lang.error, 'error' );
			}
		}).fail( function() {
			$btn.prop( 'disabled', false );
			ecv2_toast( ecv2_lang.error, 'error' );
		});
	};

	$( document ).on( 'keydown', function( ev ) {
		if ( ! $dup.hasClass( 'ecv2-drawer-open' ) ) { return; }
		if ( ev.key === 'Escape' ) { window.ecv2_order_dup_close(); }
		if ( ev.key === 'Enter' && ( ev.ctrlKey || ev.metaKey ) ) { ev.preventDefault(); window.ecv2_order_dup_create(); }
	});

	/* ===================================================================== */
	/* PRO stubs — orders-v2-pro.js replaces these when licensed              */
	/* ===================================================================== */

	if ( typeof window.ecv2_open_fulfill !== 'function' ) {
		window.ecv2_open_fulfill = function() {
			return window.wpec_gate.locked_action( ecv2_lang.order_pro_gate );
		};
	}
	if ( typeof window.ecv2_order_apply_saved_view !== 'function' ) {
		window.ecv2_order_apply_saved_view = function() {
			return window.wpec_gate.locked_action( ecv2_lang.order_pro_gate );
		};
	}
	if ( typeof window.ecv2_order_save_current_view !== 'function' ) {
		window.ecv2_order_save_current_view = function() {
			return window.wpec_gate.locked_action( ecv2_lang.order_pro_gate );
		};
	}
	if ( typeof window.ecv2_order_delete_saved_view !== 'function' ) {
		window.ecv2_order_delete_saved_view = function() {
			return window.wpec_gate.locked_action( ecv2_lang.order_pro_gate );
		};
	}

	ecv2_update_bulk_count();
});