/**
 * WP EasyCart Admin Product List V2 - JavaScript
 *
 * Handles: view mode switching, inline editing, status toggle,
 * row menus, filter pills, health dashboard clicks, bulk edit modal,
 * schedule sale modal, toast notifications, undo stack,
 * spreadsheet mode interactions.
 *
 * @since 5.x.x
 */

(function( $ ) {
	'use strict';

	var ecv2_undo_stack = [];
	var ECV2_UNDO_MAX = 20;

	/* ====== Square Sync Lock Helpers ====== */

	/**
	 * Check if a product is Square-synced by looking for data-square-synced="1"
	 * on the nearest row, card, or spreadsheet row. Falls back to checking
	 * the corresponding main table row when in spreadsheet view.
	 */
	function ecv2_is_square_product( identifier ) {
		var $el;
		if ( typeof identifier === 'number' || ( typeof identifier === 'string' && /^\d+$/.test( identifier ) ) ) {
			$el = $( 'tr[data-id="' + identifier + '"], .ecv2-card[data-id="' + identifier + '"], .ecv2-ss-row[data-id="' + identifier + '"]' );
		} else {
			$el = $( identifier );
		}
		var $container = $el.closest( '[data-square-synced="1"]' );
		if ( $container.length ) {
			return true;
		}
		// Fallback: spreadsheet rows don't carry the attr — check main table row.
		var row_id = $el.closest( '[data-id]' ).attr( 'data-id' );
		if ( row_id ) {
			var $main = $( 'tr.ecv2-row[data-id="' + row_id + '"]' );
			if ( $main.length && $main.attr( 'data-square-synced' ) === '1' ) {
				return true;
			}
		}
		return false;
	}
	window.ecv2_is_square_product = ecv2_is_square_product;

	function ecv2_square_blocked_alert() {
		ecv2_toast(
			ecv2_lang.square_locked || 'Quick edit disabled — this product is synced with Square. Edit the full product by clicking the title, or make changes in your Square dashboard.',
			'info'
		);
	}
	window.ecv2_square_blocked_alert = ecv2_square_blocked_alert;

	function ecv2_toast( message, type ) {
		type = type || 'success';
		var icon = type === 'success' ? 'yes' : ( type === 'error' ? 'no' : 'info-outline' );
		var $toast = $( '<div class="ecv2-toast ecv2-toast-' + type + '">' +
			'<span class="dashicons dashicons-' + icon + '"></span> ' +
			'<span>' + message + '</span></div>' );
		$( '#ecv2-toast-container' ).append( $toast );
		setTimeout( function() {
			$toast.fadeOut( 300, function() { $( this ).remove(); } );
		}, 3500 );
	}
	window.ecv2_toast = ecv2_toast;

	function ecv2_esc_html( str ) {
		if ( ! str ) return '';
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}
	window.ecv2_esc_html = ecv2_esc_html;

	/* --- Image onerror fallback. Replaces broken thumbnails with a placeholder. */
	window.ecv2_image_error = function( img ) {
		if ( ! img || img.dataset.ecv2Fallback === '1' ) return;
		img.dataset.ecv2Fallback = '1';
		var ph_class = img.getAttribute( 'data-ecv2-placeholder-class' ) || 'ecv2-product-thumb-placeholder';
		var title = ( typeof ecv2_lang !== 'undefined' && ecv2_lang.image_missing ) ? ecv2_lang.image_missing : 'Image could not be loaded';
		var placeholder = document.createElement( 'div' );
		placeholder.className = ph_class + ' ecv2-thumb-missing';
		placeholder.setAttribute( 'title', title );
		var icon = document.createElement( 'span' );
		icon.className = 'dashicons dashicons-format-image';
		placeholder.appendChild( icon );
		if ( img.parentNode ) {
			img.parentNode.replaceChild( placeholder, img );
		}
	};

	/* --- Spreadsheet Column Picker. Free feature — column visibility on the
	 * spreadsheet view of the product list. Persisted in a cookie. */
	window.ecv2_toggle_ss_column_picker = function( btn ) {
		var $picker = $( '#ecv2-ss-col-picker' );
		if ( $picker.is( ':visible' ) ) {
			$picker.hide();
			$( document ).off( 'click.ecv2-ss-col-picker' );
		} else {
			$picker.show();
			// Close on outside click — persistent listener until picker closes.
			setTimeout( function() {
				$( document ).on( 'click.ecv2-ss-col-picker', function( e ) {
					if ( ! $( e.target ).closest( '#ecv2-ss-col-picker, #ecv2-ss-col-toggle-btn' ).length ) {
						$picker.hide();
						$( document ).off( 'click.ecv2-ss-col-picker' );
					}
				});
			}, 10 );
		}
	};

	window.ecv2_toggle_ss_column = function( col_name, visible ) {
		var $cells = $( '.ecv2-ss-col-' + col_name );
		if ( visible ) {
			$cells.show();
		} else {
			$cells.hide();
		}
		var hidden = [];
		$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
			if ( ! this.checked && ! this.disabled ) {
				hidden.push( $( this ).data( 'ss-col' ) );
			}
		});
		document.cookie = 'wpeasycart_ss_hidden_cols=' + hidden.join( ',' ) + ';path=/;max-age=31536000';
	};

	// Restore column visibility from cookie on page load.
	$( document ).ready( function() {
		var match = document.cookie.match( /wpeasycart_ss_hidden_cols=([^;]*)/ );
		if ( match ) {
			var hidden = match[1] ? match[1].split( ',' ) : [];
			$( '#ecv2-ss-col-picker input[type="checkbox"]' ).each( function() {
				var col = $( this ).data( 'ss-col' );
				if ( ! col || this.disabled ) return;
				var should_hide = hidden.indexOf( col ) !== -1;
				$( this ).prop( 'checked', ! should_hide );
				if ( should_hide ) {
					$( '.ecv2-ss-col-' + col ).hide();
				} else {
					$( '.ecv2-ss-col-' + col ).show();
				}
			});
		}
		$( 'tr.ecv2-ss-row' ).each( function() {
			var $row = $( this );
			var $cb = $row.find( '.ecv2-toggle input[type="checkbox"]' );
			if ( $cb.length && ! $cb.is( ':checked' ) ) {
				$row.addClass( 'ecv2-ss-row-inactive' );
			}
		});
	});

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
		if ( ecv2_undo_stack.length === 0 ) return;
		var action = ecv2_undo_stack.pop();
		if ( action && action.undo_fn ) {
			action.undo_fn();
		}
		if ( ecv2_undo_stack.length === 0 ) {
			$( '#ecv2-undo-bar' ).fadeOut( 200 );
		}
	});

	$( document ).on( 'click', '.ecv2-view-btn', function() {
		var mode = $( this ).data( 'mode' );
		$( '.ecv2-view-btn' ).removeClass( 'ecv2-view-btn-active' );
		$( this ).addClass( 'ecv2-view-btn-active' );
		$( '.ecv2-view' ).hide();
		$( '.ecv2-view-' + mode ).show();
		$( '.ecv2-wrap' ).attr( 'data-view-mode', mode );
		// Save in cookie.
		document.cookie = 'wpeasycart_admin_view_mode=' + mode + ';path=/;max-age=31536000';
	});

	$( document ).on( 'click', '.ecv2-stat-card', function() {
		var filter_val = $( this ).data( 'filter' );
		$( '#ecv2-health-filter-input' ).val( filter_val );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( filter_val !== '' ) {
			$( this ).addClass( 'ecv2-stat-active' );
		}

		// Show loading state: spinner in clicked card, dim the rest.
		$( '.ecv2-stat-card' ).addClass( 'ecv2-stat-loading-dim' );
		$( this ).removeClass( 'ecv2-stat-loading-dim' ).addClass( 'ecv2-stat-loading' );
		$( this ).find( '.ecv2-stat-value' ).html( '<span class="dashicons dashicons-update ecv2-spin ecv2-stat-spinner"></span>' );

		// Clear the search box so it doesn't combine with the health filter.
		$( '#ecv2-search-input' ).val( '' );
		$( '#ecv2-posts-filter' ).submit();
	});

	/* ====== Stat Card Visibility Toggle ====== */
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

		var nonce    = $( '#ecv2-stat-toggle-nonce' ).val();
		var table_id = $( '#ecv2-stat-toggle-table-id' ).val();

		if ( ! nonce || ! table_id ) {
			return;
		}

		$.post( ajaxurl, {
			action:             'ecv2_save_stat_visibility',
			wp_easycart_nonce:  nonce,
			table_id:           table_id,
			hidden:             hidden
		});
	}

	$( document ).on( 'click', '.ecv2-pill', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).data( 'value' );
		$( '#' + filter_name.replace( 'filter_', 'ecv2-filter-input-' ) ).val( filter_val );
		$( this ).siblings( '.ecv2-pill' ).removeClass( 'ecv2-pill-active' );
		$( this ).addClass( 'ecv2-pill-active' );
		$( '#ecv2-posts-filter' ).submit();
	});

	window.ecv2_toggle_filters = function() {
		$( '#ecv2-filter-panel' ).slideToggle( 200, function() {
			ecv2_init_filter_selects();
		});
	};

	window.ecv2_clear_filters = function() {
		$( '[id^="ecv2-filter-input-"]' ).val( '' );
		$( '#ecv2-health-filter-input' ).val( '' );
		// Reset select2 filter selects.
		$( '.ecv2-filter-select' ).val( '' );
		if ( typeof $.fn.select2 === 'function' ) {
			$( '.ecv2-filter-select' ).trigger( 'change.select2' );
		}
		// Clear range inputs.
		$( '.ecv2-drawer-range-input' ).val( '' );
		// Clear stat card highlights.
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		$( '#ecv2-search-input' ).val( '' );
		ecv2_show_filter_loader();
		$( '#ecv2-posts-filter' ).submit();
	};

	/* ====== Filter Drawer ====== */
	var ecv2_drawer_filter_snapshot = {};

	function ecv2_capture_filter_state() {
		var state = {};
		$( '[id^="ecv2-filter-input-"]' ).each( function() {
			state[ this.id ] = $( this ).val() || '';
		});
		state['ecv2-health-filter-input'] = $( '#ecv2-health-filter-input' ).val() || '';
		return state;
	}

	function ecv2_filters_changed( before, after ) {
		var all_keys = {};
		var key;
		for ( key in before ) {
			if ( before.hasOwnProperty( key ) ) {
				all_keys[ key ] = true;
			}
		}
		for ( key in after ) {
			if ( after.hasOwnProperty( key ) ) {
				all_keys[ key ] = true;
			}
		}
		for ( key in all_keys ) {
			if ( all_keys.hasOwnProperty( key ) ) {
				if ( ( before[ key ] || '' ) !== ( after[ key ] || '' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	function ecv2_show_filter_loader() {
		if ( ! $( '#ecv2-filter-overlay' ).length ) {
			$( 'body' ).append(
				'<div id="ecv2-filter-overlay" class="ecv2-filter-overlay">' +
					'<div class="ecv2-filter-overlay-inner">' +
						'<span class="dashicons dashicons-update ecv2-spin ecv2-filter-overlay-spinner"></span>' +
						'<span class="ecv2-filter-overlay-text">' + ( ecv2_lang.applying_filters || 'Applying filters...' ) + '</span>' +
					'</div>' +
				'</div>'
			);
		}
		$( '#ecv2-filter-overlay' ).addClass( 'ecv2-filter-overlay-visible' );
	}

	$( document ).on( 'change', '.ecv2-perpage-select', function() {
		ecv2_show_filter_loader();
		$( this ).closest( 'form' ).submit();
	});

	window.ecv2_open_filter_drawer = function() {
		ecv2_drawer_filter_snapshot = ecv2_capture_filter_state();
		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).addClass( 'ecv2-drawer-open' );
		ecv2_init_filter_selects();
	};

	window.ecv2_close_filter_drawer = function( force_apply ) {
		// Sync any range filter inputs to their hidden fields before comparing state.
		$( '.ecv2-drawer-range' ).each( function() {
			var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
			var $apply_btn = $( this ).find( '.ecv2-drawer-range-apply' );
			if ( $apply_btn.length ) {
				var filter_name = $apply_btn.data( 'filter' );
				var min_val = $( this ).find( '.ecv2-drawer-range-input[data-range="min"]' ).val() || '';
				var max_val = $( this ).find( '.ecv2-drawer-range-input[data-range="max"]' ).val() || '';
				var combined = min_val + '-' + max_val;
				if ( combined === '-' ) combined = '';
				$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( combined );
			}
		});

		var current_state = ecv2_capture_filter_state();
		var changed = ecv2_filters_changed( ecv2_drawer_filter_snapshot, current_state );

		$( '#ecv2-filter-drawer, #ecv2-drawer-backdrop' ).removeClass( 'ecv2-drawer-open' );

		if ( changed || force_apply ) {
			ecv2_show_filter_loader();
			$( '#ecv2-posts-filter' ).submit();
		}
	};

	$( document ).on( 'click', '#ecv2-drawer-close, #ecv2-drawer-backdrop', function() {
		ecv2_close_filter_drawer();
	});

	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' && $( '#ecv2-filter-drawer' ).hasClass( 'ecv2-drawer-open' ) ) {
			ecv2_close_filter_drawer();
		}
	});

	// Drawer pill clicks (non-health filters).
	$( document ).on( 'click', '.ecv2-drawer-pill:not(.ecv2-drawer-health-pill)', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).data( 'value' );
		$( this ).siblings( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( filter_val );
	});

	// Drawer health pill clicks.
	$( document ).on( 'click', '.ecv2-drawer-health-pill', function() {
		var val = $( this ).data( 'health-value' );
		$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( this ).addClass( 'ecv2-drawer-pill-active' );
		$( '#ecv2-health-filter-input' ).val( val );
		// Also sync stat card highlights.
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		if ( val !== '' ) {
			$( '.ecv2-stat-card[data-filter="' + val + '"]' ).addClass( 'ecv2-stat-active' );
		}
		// Toggle active highlight and (x) button on the group.
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		var $label = $group.find( '.ecv2-drawer-filter-label' );
		if ( val !== '' ) {
			$group.addClass( 'ecv2-drawer-filter-group-active' );
			if ( ! $label.find( '.ecv2-drawer-health-clear' ).length ) {
				$label.append(
					'<button type="button" class="ecv2-drawer-filter-clear ecv2-drawer-health-clear" title="' + ( ecv2_lang.clear_filter || 'Clear this filter' ) + '">' +
					'<span class="dashicons dashicons-dismiss"></span></button>'
				);
			}
		} else {
			$group.removeClass( 'ecv2-drawer-filter-group-active' );
			$label.find( '.ecv2-drawer-health-clear' ).remove();
		}
	});

	// Drawer health clear button.
	$( document ).on( 'click', '.ecv2-drawer-health-clear', function() {
		$( '#ecv2-health-filter-input' ).val( '' );
		$( '.ecv2-drawer-health-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$( '.ecv2-drawer-health-pill[data-health-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
		$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		// Remove active highlight and the (x) button — consistent with other filter groups.
		$( this ).closest( '.ecv2-drawer-filter-group' ).removeClass( 'ecv2-drawer-filter-group-active' );
		$( this ).remove();
	});

	// Drawer individual filter clear button.
	$( document ).on( 'click', '.ecv2-drawer-filter-clear:not(.ecv2-drawer-health-clear)', function() {
		var filter_name = $( this ).data( 'filter' );
		$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( '' );
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		$group.find( '.ecv2-drawer-pill' ).removeClass( 'ecv2-drawer-pill-active' );
		$group.find( '.ecv2-drawer-pill[data-value=""]' ).addClass( 'ecv2-drawer-pill-active' );
		$group.find( '.ecv2-filter-select' ).val( '' );
		if ( typeof $.fn.select2 === 'function' ) {
			$group.find( '.ecv2-filter-select' ).trigger( 'change.select2' );
		}
		// Clear range inputs (price range etc.).
		$group.find( '.ecv2-drawer-range-input' ).val( '' );
		$( this ).closest( '.ecv2-drawer-filter-group' ).removeClass( 'ecv2-drawer-filter-group-active' );
		$( this ).remove();
	});

	// Drawer range filter apply.
	$( document ).on( 'click', '.ecv2-drawer-range-apply', function() {
		var filter_name = $( this ).data( 'filter' );
		var $group = $( this ).closest( '.ecv2-drawer-filter-group' );
		var min_val = $group.find( '.ecv2-drawer-range-input[data-range="min"]' ).val() || '';
		var max_val = $group.find( '.ecv2-drawer-range-input[data-range="max"]' ).val() || '';
		var combined = min_val + '-' + max_val;
		if ( combined === '-' ) combined = '';
		$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( combined );
	});

	// Drawer "Show Results" — submit form.
	window.ecv2_apply_drawer_filters = function() {
		// Sync range filter inputs to hidden fields before submitting.
		$( '.ecv2-drawer-range' ).each( function() {
			var $apply_btn = $( this ).find( '.ecv2-drawer-range-apply' );
			if ( $apply_btn.length ) {
				var filter_name = $apply_btn.data( 'filter' );
				var min_val = $( this ).find( '.ecv2-drawer-range-input[data-range="min"]' ).val() || '';
				var max_val = $( this ).find( '.ecv2-drawer-range-input[data-range="max"]' ).val() || '';
				var combined = min_val + '-' + max_val;
				if ( combined === '-' ) combined = '';
				$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( combined );
			}
		});
		ecv2_close_filter_drawer();
		$( '#ecv2-posts-filter' ).submit();
	};

	/* --- Active Filter Tag Removal --- */
	$( document ).on( 'click', '.ecv2-active-tag-remove', function() {
		var filter_name = $( this ).data( 'filter' );
		if ( filter_name === 'health_filter' ) {
			$( '#ecv2-health-filter-input' ).val( '' );
			$( '.ecv2-stat-card' ).removeClass( 'ecv2-stat-active' );
		} else {
			$( '#ecv2-filter-input-' + filter_name.replace( 'filter_', '' ) ).val( '' );
		}
		// Show loading overlay while filters are re-applied.
		ecv2_show_filter_loader();
		$( '#ecv2-posts-filter' ).submit();
	});

	/* --- Filter Selects (for large data sets like categories/manufacturers) --- */
	$( document ).on( 'change', '.ecv2-filter-select', function() {
		var filter_name = $( this ).data( 'filter' );
		var filter_val = $( this ).val() || '';
		$( '#' + filter_name.replace( 'filter_', 'ecv2-filter-input-' ) ).val( filter_val );
		if ( ! $( '#ecv2-filter-drawer' ).hasClass( 'ecv2-drawer-open' ) ) {
			$( '#ecv2-posts-filter' ).submit();
		}
	});

	// Initialize select2 on filter selects.
	function ecv2_init_filter_selects() {
		if ( typeof $.fn.select2 === 'function' ) {
			$( '.ecv2-filter-select' ).each( function() {
				if ( ! $( this ).data( 'select2' ) ) {
					var $select = $( this );
					var opts = {
						width: '240px',
						allowClear: true,
						placeholder: $select.data( 'placeholder' ) || '',
						minimumResultsForSearch: 10
					};
					// When inside the filter drawer, attach dropdown to the drawer
					// so it renders above the backdrop overlay.
					var $drawer = $select.closest( '#ecv2-filter-drawer' );
					if ( $drawer.length ) {
						opts.dropdownParent = $drawer;
					}
					$select.select2( opts );
				}
			});
		}
	}

	// Init on page load if filter panel is already visible.
	$( document ).ready( function() {
		if ( $( '#ecv2-filter-panel' ).is( ':visible' ) ) {
			ecv2_init_filter_selects();
		}
	});

	function ecv2_search_submit() {
		$( '#ecv2-search-clear' ).hide();
		$( '#ecv2-search-loading' ).show();
		$( '#ecv2-search-submit' ).prop( 'disabled', true );
		$( '#ecv2-search-input' ).prop( 'readonly', true ).addClass( 'ecv2-search-loading-state' );
		$( '#ecv2-posts-filter' ).submit();
	}

	// Submit form on Enter key in search input.
	$( document ).on( 'keydown', '#ecv2-search-input', function( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			ecv2_search_submit();
		}
	});

	// Show/hide clear button as user types.
	$( document ).on( 'input', '#ecv2-search-input', function() {
		if ( $( this ).val().length > 0 ) {
			$( '#ecv2-search-clear' ).show();
		} else {
			$( '#ecv2-search-clear' ).hide();
		}
	});

	// Search submit button click.
	$( document ).on( 'click', '#ecv2-search-submit', function() {
		ecv2_search_submit();
	});

	// Search clear button — clear and resubmit.
	$( document ).on( 'click', '#ecv2-search-clear', function() {
		$( '#ecv2-search-input' ).val( '' );
		$( '#ecv2-search-clear' ).hide();
		ecv2_search_submit();
	});

	$( document ).on( 'change', '#ecv2-select-all, #ecv2-ss-select-all', function() {
		var checked = $( this ).is( ':checked' );
		$( '.ecv2-row-check' ).prop( 'checked', checked );
		ecv2_update_bulk_count();
	});
	$( document ).on( 'change', '.ecv2-row-check', function() {
		ecv2_update_bulk_count();
	});

	function ecv2_update_bulk_count() {
		var count = $( '.ecv2-row-check:checked' ).length;
		if ( count > 0 ) {
			$( '#ecv2-selected-count' ).text( count );
			$( '.ecv2-bulk-count' ).show();
			$( '#ecv2-bulk-edit-btn' ).show();
		} else {
			$( '.ecv2-bulk-count' ).hide();
			$( '#ecv2-bulk-edit-btn' ).hide();
		}
	}

	window.ecv2_toggle_row_menu = function( trigger ) {
		var $menu = $( trigger ).siblings( '.ecv2-row-menu' );
		$( '.ecv2-row-menu' ).not( $menu ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css({ top: '', right: '', left: '' });
		$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });

		// Reset z-index on all cards, then raise the active one.
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );

		// If inside a card, use fixed positioning to escape overflow.
		if ( $( trigger ).closest( '.ecv2-card, .ecv2-table, .ecv2-spreadsheet' ).length ) {
			var rect = trigger.getBoundingClientRect();
			$menu.addClass( 'ecv2-menu-fixed' );
			var menu_top = rect.bottom + 4;
			var menu_right = window.innerWidth - rect.right;
			$menu.css({ top: menu_top + 'px', right: menu_right + 'px', left: 'auto' });

			$menu.toggleClass( 'ecv2-row-menu-open' );

			// Check viewport overflow when opening.
			if ( $menu.hasClass( 'ecv2-row-menu-open' ) ) {
				$( trigger ).closest( '.ecv2-card' ).addClass( 'ecv2-card-menu-active' );
				var menu_rect = $menu[0].getBoundingClientRect();
				if ( menu_rect.bottom > window.innerHeight - 8 ) {
					var above_top = rect.top - menu_rect.height - 4;
					if ( above_top > 8 ) {
						$menu.css({ top: above_top + 'px' });
					}
				}
			}
		} else {
			$menu.removeClass( 'ecv2-menu-fixed' );
			$menu.css({ top: '', right: '', left: '' });
			$menu.toggleClass( 'ecv2-row-menu-open' );
		}
	};

	// Close menus on outside click.
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap' ).length ) {
			$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css({ top: '', right: '', left: '' });
			$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
		}
	});

	// Close fixed-position menus on scroll (they don't follow the page).
	$( window ).on( 'scroll', function() {
		$( '.ecv2-menu-fixed' ).removeClass( 'ecv2-row-menu-open ecv2-stock-menu-open ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', right: '', left: '' });
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
		if ( ecv2_cat_active_cell && $( '.ecv2-cat-popover.ecv2-menu-fixed' ).length ) {
			ecv2_close_category_editor();
		}
	});

	window.ecv2_sync_product_field = function( product_id, field, payload ) {
		if ( ! product_id || ! field ) { return; }
		var pid = String( product_id );
		switch ( field ) {
			case 'is_visible':
			case 'activate_in_store':
			case 'status':
				var active_val;
				if ( typeof payload === 'object' && payload !== null ) {
					if ( payload.active !== undefined )           { active_val = payload.active; }
					else if ( payload.is_visible !== undefined ) { active_val = payload.is_visible; }
					else if ( payload.status !== undefined )     { active_val = payload.status; }
					else                                         { active_val = 0; }
				} else {
					active_val = payload;
				}
				ecv2_sync_status_visual( pid, !! active_val );
				break;

			case 'title':
				ecv2_sync_title( pid, payload );
				break;

			case 'model_number':
				ecv2_sync_sku( pid, payload );
				break;

			case 'price':
			case 'list_price':
			case 'prices':
				ecv2_sync_price( pid, payload );
				break;

			case 'stock_quantity':
			case 'stock':
				ecv2_sync_stock( pid, payload );
				break;
		}
	};

	function ecv2_sync_status_visual( pid, active ) {
		active = !! active;

		// Sync every toggle checkbox for this product across views.
		$(
			'tr.ecv2-row[data-id="' + pid + '"] .ecv2-toggle input[type="checkbox"], ' +
			'.ecv2-card[data-id="' + pid + '"] .ecv2-toggle input[type="checkbox"], ' +
			'tr.ecv2-ss-row[data-id="' + pid + '"] .ecv2-toggle input[type="checkbox"]'
		).prop( 'checked', active );

		// Card overlay fadeout.
		$( '.ecv2-card[data-id="' + pid + '"]' ).toggleClass( 'ecv2-card-inactive', ! active );
		// Matching subtle dim on table / spreadsheet rows.
		$( 'tr.ecv2-row[data-id="' + pid + '"]' ).toggleClass( 'ecv2-row-inactive', ! active );
		$( 'tr.ecv2-ss-row[data-id="' + pid + '"]' ).toggleClass( 'ecv2-ss-row-inactive', ! active );
	}
	window.ecv2_sync_status_visual = ecv2_sync_status_visual;

	function ecv2_sync_title( pid, payload ) {
		var title = ( payload && payload.title !== undefined ) ? String( payload.title ) : '';

		// Table cell: standard rows use .ecv2-product-title-text.
		$( 'tr.ecv2-row[data-id="' + pid + '"] td[data-field="title"]' ).each( function() {
			var $cell = $( this );
			if ( $cell.hasClass( 'ecv2-cell-editing' ) ) { return; }
			var $text = $cell.find( '.ecv2-product-title-text' );
			if ( $text.length ) { $text.text( title ); }
			// Square-locked rows render the title inside .ecv2-square-title-badge
			// after the logo + lock icon. Replace its trailing text node only.
			var $badge = $cell.find( '.ecv2-square-title-badge' );
			if ( $badge.length ) {
				$badge.contents().filter( function() {
					return this.nodeType === 3; // text nodes only
				} ).remove();
				$badge[0].appendChild( document.createTextNode( title ) );
			}
		});

		// Card title link.
		$( '.ecv2-card[data-id="' + pid + '"] .ecv2-card-title a' ).text( title );

		// Spreadsheet cell.
		$( 'tr.ecv2-ss-row[data-id="' + pid + '"] td[data-field="title"]' ).each( function() {
			var $cell = $( this );
			if ( $cell.hasClass( 'ecv2-ss-editing' ) ) { return; }
			$cell.text( title );
		});
	}

	function ecv2_sync_sku( pid, payload ) {
		var sku = ( payload && payload.model_number !== undefined ) ? String( payload.model_number ) : '';
		var chip_html;
		if ( sku ) {
			chip_html = '<span class="ecv2-sku-chip">' + ecv2_esc_html( sku ) + '</span>';
		} else {
			chip_html = '<span class="ecv2-sku-empty">\u2014</span>';
		}

		// Table cell — chip directly inside the td.
		$( 'tr.ecv2-row[data-id="' + pid + '"] td[data-field="model_number"]' ).each( function() {
			var $cell = $( this );
			if ( $cell.hasClass( 'ecv2-cell-editing' ) ) { return; }
			$cell.html( chip_html );
		});

		// Card SKU wrap.
		$( '.ecv2-card[data-id="' + pid + '"] .ecv2-card-sku-wrap' ).each( function() {
			var $wrap = $( this );
			if ( $wrap.hasClass( 'ecv2-card-sku-editing' ) ) { return; }
			$wrap.html( chip_html );
		});

		// Spreadsheet cell — plain text, no chip styling.
		$( 'tr.ecv2-ss-row[data-id="' + pid + '"] td[data-field="model_number"]' ).each( function() {
			var $cell = $( this );
			if ( $cell.hasClass( 'ecv2-ss-editing' ) ) { return; }
			$cell.text( sku );
		});
	}

	function ecv2_sync_price( pid, payload ) {
		if ( ! payload ) { return; }

		var has_price       = ( payload.price !== undefined );
		var has_list_price  = ( payload.list_price !== undefined );
		var price           = has_price ? Number( payload.price ) : null;
		var list_price      = has_list_price ? Number( payload.list_price ) : null;
		var price_fmt       = payload.price_formatted || '';
		var list_fmt        = payload.list_price_formatted || '';
		var is_on_sale      = !! payload.is_on_sale;
		var discount_pct    = payload.discount_pct || 0;
		var off_label       = ecv2_lang.off_label || 'off';

		$( '.ecv2-price-cell[data-product-id="' + pid + '"]' ).each( function() {
			var $cell = $( this );

			// Update cell data attributes used by other UI hooks (advanced pricing slideout etc).
			if ( has_price )      { $cell.attr( 'data-price', price ).data( 'price', price ); }
			if ( has_list_price ) { $cell.attr( 'data-list-price', list_price ).data( 'list-price', list_price ); }

			var is_compact = $cell.hasClass( 'ecv2-price-cell-compact' );
			var is_variant = String( $cell.attr( 'data-has-variant-pricing' ) || '0' ) === '1';
			var $btn       = $cell.find( '.ecv2-price-badge-btn' ).first();
			if ( ! $btn.length || ! price_fmt ) { return; }

			if ( is_variant ) {
				// Variant cells: only the BASE label changes — variant range stays.
				var $base = $btn.find( '.ecv2-price-base-value' );
				if ( $base.length ) {
					$base.text( price_fmt );
				} else if ( price > 0 ) {
					$btn.append(
						'<span class="ecv2-price-base-label">' +
						ecv2_esc_html( ecv2_lang.base_label || 'Base:' ) +
						' <span class="ecv2-price-base-value">' + ecv2_esc_html( price_fmt ) + '</span></span>'
					);
				}
			} else if ( is_compact ) {
				// Spreadsheet compact — no sale tag, just current + optional list.
				var compact_html = '<span class="ecv2-price-current">' + ecv2_esc_html( price_fmt ) + '</span>';
				if ( list_price > 0 && list_price !== price && list_price > price && list_fmt ) {
					compact_html += ' <span class="ecv2-price-list">' + ecv2_esc_html( list_fmt ) + '</span>';
				}
				$btn.html( compact_html );
			} else {
				// Standard cell — rebuild the price-wrap inside the button.
				var wrap_html = '<div class="ecv2-price-wrap">';
				wrap_html += '<span class="ecv2-price-current">' + ecv2_esc_html( price_fmt ) + '</span>';
				if ( is_on_sale && list_fmt ) {
					wrap_html += ' <span class="ecv2-price-list">' + ecv2_esc_html( list_fmt ) + '</span>';
					wrap_html += ' <span class="ecv2-sale-tag">' + ecv2_esc_html( discount_pct + '% ' + off_label ) + '</span>';
				} else if ( list_fmt ) {
					wrap_html += ' <span class="ecv2-price-list">' + ecv2_esc_html( list_fmt ) + '</span>';
				}
				wrap_html += '</div>';
				$btn.html( wrap_html );
			}

			// Re-baseline price-menu inputs so the next open shows current values.
			var $price_input = $cell.find( '.ecv2-price-menu-input[data-field="price"]' );
			var $list_input  = $cell.find( '.ecv2-price-menu-input[data-field="list_price"]' );
			if ( has_price && $price_input.length ) {
				var pstr = ( price > 0 ) ? price.toFixed( 2 ) : '0.00';
				$price_input.val( pstr ).data( 'original', pstr );
			}
			if ( has_list_price && $list_input.length ) {
				var lstr = ( list_price > 0 ) ? list_price.toFixed( 2 ) : '';
				$list_input.val( lstr ).data( 'original', lstr );
			}
		});
	}

	function ecv2_sync_stock( pid, payload ) {
		if ( ! payload ) { return; }

		$( '.ecv2-stock-wrap[data-product-id="' + pid + '"]' ).each( function() {
			var $wrap = $( this );
			// Skip Square-locked wraps — their markup is read-only and intentionally different.
			if ( $wrap.hasClass( 'ecv2-stock-wrap-locked' ) ) { return; }

			ecv2_rebuild_stock_badge( $wrap, {
				tracking_type:  payload.tracking_type  !== undefined ? payload.tracking_type  : ( $wrap.data( 'tracking' )    || 'basic' ),
				stock_quantity: payload.stock_quantity !== undefined ? payload.stock_quantity : ( $wrap.data( 'stock-qty' )   || 0 ),
				option_total:   payload.option_total   !== undefined ? payload.option_total   : ( $wrap.data( 'option-total' ) || 0 ),
				product_id:     pid
			});
		});
	}

	window.ecv2_toggle_product_status = function( product_id, checked, nonce ) {
		var status = checked ? 1 : 0;
		ecv2_sync_product_field( product_id, 'is_visible', { active: status } );
		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_toggle_status',
				product_id: product_id,
				status: status,
				wp_easycart_nonce: nonce
			},
			success: function( response ) {
				if ( response.success ) {
					var label = status ? ecv2_lang.activated : ecv2_lang.deactivated;
					ecv2_toast( label, 'success' );
					ecv2_push_undo({
						message: label + ' - ' + ecv2_lang.click_undo,
						undo_fn: function() {
							// Re-toggle.
							ecv2_toggle_product_status( product_id, ! checked, nonce );
						}
					});
				} else {
					ecv2_sync_product_field( product_id, 'is_visible', { active: ! status } );
					ecv2_toast( ( response.data && response.data.message ) ? response.data.message : ecv2_lang.error, 'error' );
				}
			},
			error: function() {
				ecv2_sync_product_field( product_id, 'is_visible', { active: ! status } );
				ecv2_toast( ecv2_lang.error, 'error' );
			}
		});
	};

	$( document ).on( 'dblclick', '.ecv2-cell[data-editable="true"]', function( e ) {
		var $cell = $( this );
		if ( $cell.hasClass( 'ecv2-cell-editing' ) ) return;
		if ( ecv2_is_square_product( $cell ) ) { ecv2_square_blocked_alert(); return; }

		var field = $cell.data( 'field' );

		// For the title column, only allow double-click on the title text itself.
		if ( field === 'title' ) {
			if ( ! $( e.target ).hasClass( 'ecv2-product-title-text' ) ) {
				return;
			}
		}

		var row_id = $cell.closest( 'tr' ).data( 'id' );

		// Get current value based on field type.
		var current_text;
		if ( field === 'title' ) {
			current_text = $cell.find( '.ecv2-product-title-text' ).text().trim();
		} else if ( field === 'model_number' ) {
			var $chip = $cell.find( '.ecv2-sku-chip' );
			current_text = $chip.length ? $chip.text().trim() : '';
		} else {
			current_text = $cell.text().trim();
		}

		// Store row actions HTML before replacing content (title column).
		var $row_actions = null;
		if ( field === 'title' ) {
			$row_actions = $cell.find( '.ecv2-product-row-actions' ).detach();
		}

		$cell.addClass( 'ecv2-cell-editing' );
		var $input = $( '<input type="text" />' ).val( current_text );

		if ( field === 'title' ) {
			$cell.find( '.ecv2-product-title-wrap' ).html( $input );
			if ( $row_actions ) {
				$cell.find( '.ecv2-product-title-wrap' ).append( $row_actions );
			}
		} else {
			$cell.html( $input );
		}
		$input.focus().select();

		function restore_title( text ) {
			var $wrap = $cell.find( '.ecv2-product-title-wrap' );
			if ( ! $wrap.length ) {
				$wrap = $( '<div class="ecv2-product-title-wrap"></div>' );
				$cell.html( $wrap );
			}
			$wrap.find( 'input' ).remove();
			$wrap.find( '.ecv2-product-title-text' ).remove();
			$wrap.prepend( '<span class="ecv2-product-title-text">' + $( '<span>' ).text( text ).html() + '</span>' );
			if ( $row_actions && ! $wrap.find( '.ecv2-product-row-actions' ).length ) {
				$wrap.append( $row_actions );
			}
		}

		function restore_sku( text ) {
			if ( text ) {
				$cell.html( '<span class="ecv2-sku-chip">' + $( '<span>' ).text( text ).html() + '</span>' );
			} else {
				$cell.html( '<span class="ecv2-sku-empty">\u2014</span>' );
			}
		}

		function restore_cell( text ) {
			if ( field === 'title' ) {
				restore_title( text );
			} else if ( field === 'model_number' ) {
				restore_sku( text );
			} else {
				$cell.text( text );
			}
		}

		function save_edit() {
			var new_val = $input.val().trim();
			$cell.removeClass( 'ecv2-cell-editing' );

			restore_cell( new_val || current_text );

			if ( new_val !== current_text && new_val !== '' ) {
				// Strip currency symbol for price fields.
				var submit_val = new_val;
				if ( field === 'price' || field === 'list_price' ) {
					submit_val = new_val.replace( /[^0-9.\-]/g, '' );
				}

				$.ajax({
					url: wpeasycart_admin_ajax_object.ajax_url,
					type: 'POST',
					data: {
						action: 'ecv2_product_inline_update',
						product_id: row_id,
						field: field,
						value: submit_val,
						wp_easycart_nonce: ecv2_nonces.inline_update
					},
					success: function( response ) {
						if ( response.success ) {
							restore_cell( response.data.display_value );
							var sync_payload = {};
							sync_payload[ field ] = response.data.display_value;
							ecv2_sync_product_field( row_id, field, sync_payload );
							ecv2_toast( ecv2_lang.saved, 'success' );
							ecv2_push_undo({
								message: ecv2_lang.field_updated + ' \u2014 ' + ecv2_lang.click_undo,
								undo_fn: function() {
									$.ajax({
										url: wpeasycart_admin_ajax_object.ajax_url,
										type: 'POST',
										data: {
											action: 'ecv2_product_inline_update',
											product_id: row_id,
											field: field,
											value: response.data.old_value,
											wp_easycart_nonce: ecv2_nonces.inline_update
										},
										success: function( r2 ) {
											if ( r2.success ) {
												restore_cell( r2.data.display_value );
												var undo_payload = {};
												undo_payload[ field ] = r2.data.display_value;
												ecv2_sync_product_field( row_id, field, undo_payload );
												ecv2_toast( ecv2_lang.undone, 'info' );
											}
										}
									});
								}
							});
						} else {
							restore_cell( current_text );
							ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
						}
					},
					error: function() {
						restore_cell( current_text );
						ecv2_toast( ecv2_lang.error, 'error' );
					}
				});
			}
		}

		$input.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); save_edit(); }
			if ( e.key === 'Escape' ) {
				$cell.removeClass( 'ecv2-cell-editing' );
				restore_cell( current_text );
			}
		});
		$input.on( 'blur', save_edit );
	});

	/* --- Card View: SKU Inline Editing (double-click) --- */
	$( document ).on( 'dblclick', '.ecv2-card-sku-wrap', function( e ) {
		var $wrap = $( this );
		if ( $wrap.hasClass( 'ecv2-card-sku-editing' ) ) return;
		if ( ecv2_is_square_product( $wrap ) ) { ecv2_square_blocked_alert(); return; }

		var product_id = $wrap.data( 'product-id' );
		var $chip = $wrap.find( '.ecv2-sku-chip' );
		var current_text = $chip.length ? $chip.text().trim() : '';

		$wrap.addClass( 'ecv2-card-sku-editing' );
		var $input = $( '<input type="text" class="ecv2-card-sku-input" />' ).val( current_text );
		$wrap.html( $input );
		$input.focus().select();

		function restore_sku( text ) {
			$wrap.removeClass( 'ecv2-card-sku-editing' );
			if ( text ) {
				$wrap.html( '<span class="ecv2-sku-chip">' + $( '<span>' ).text( text ).html() + '</span>' );
			} else {
				$wrap.html( '<span class="ecv2-sku-empty">\u2014</span>' );
			}
		}

		function save_edit() {
			var new_val = $input.val().trim();
			restore_sku( new_val || current_text );
			if ( new_val !== current_text && new_val !== '' ) {
				$.ajax({
					url: wpeasycart_admin_ajax_object.ajax_url,
					type: 'POST',
					data: {
						action: 'ecv2_product_inline_update',
						product_id: product_id,
						field: 'model_number',
						value: new_val,
						wp_easycart_nonce: ecv2_nonces.inline_update
					},
					success: function( response ) {
						if ( response.success ) {
							restore_sku( response.data.display_value );
							ecv2_sync_product_field( product_id, 'model_number', { model_number: response.data.display_value } );
							ecv2_toast( ecv2_lang.saved, 'success' );
						} else {
							restore_sku( current_text );
							ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
						}
					},
					error: function() {
						restore_sku( current_text );
						ecv2_toast( ecv2_lang.error, 'error' );
					}
				});
			}
		}

		$input.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); save_edit(); }
			if ( e.key === 'Escape' ) { restore_sku( current_text ); }
		});
		$input.on( 'blur', save_edit );
	});

	$( document ).on( 'dblclick', '.ecv2-ss-editable', function() {
		var $cell = $( this );
		if ( $cell.hasClass( 'ecv2-ss-editing' ) ) return;
		if ( ecv2_is_square_product( $cell ) ) { ecv2_square_blocked_alert(); return; }

		var field = $cell.data( 'field' );
		var row_id = $cell.closest( 'tr' ).data( 'id' );
		var current_text = $cell.text().trim();

		$cell.addClass( 'ecv2-ss-editing' );
		var $input = $( '<input type="text" />' ).val( current_text );
		$cell.html( $input );
		$input.focus().select();

		function save_ss_edit() {
			var new_val = $input.val().trim();
			$cell.removeClass( 'ecv2-ss-editing' );
			$cell.text( new_val || current_text );

			if ( new_val !== current_text && new_val !== '' ) {
				var submit_val = new_val;
				if ( field === 'price' || field === 'list_price' ) {
					submit_val = new_val.replace( /[^0-9.\-]/g, '' );
				}
				$.ajax({
					url: wpeasycart_admin_ajax_object.ajax_url,
					type: 'POST',
					data: {
						action: 'ecv2_product_inline_update',
						product_id: row_id,
						field: field,
						value: submit_val,
						wp_easycart_nonce: ecv2_nonces.inline_update
					},
					success: function( response ) {
						if ( response.success ) {
							$cell.text( response.data.display_value );
							var ss_sync_payload = {};
							ss_sync_payload[ field ] = response.data.display_value;
							ecv2_sync_product_field( row_id, field, ss_sync_payload );
							ecv2_toast( ecv2_lang.saved, 'success' );
						} else {
							$cell.text( current_text );
							ecv2_toast( ( response.data && response.data.message ) ? response.data.message : ecv2_lang.error, 'error' );
						}
					},
					error: function() {
						$cell.text( current_text );
						ecv2_toast( ecv2_lang.error, 'error' );
					}
				});
			}
		}

		$input.on( 'keydown', function( e ) {
			if ( e.key === 'Enter' ) { e.preventDefault(); save_ss_edit(); }
			if ( e.key === 'Escape' ) { $cell.removeClass( 'ecv2-ss-editing' ); $cell.text( current_text ); }
			if ( e.key === 'Tab' ) {
				e.preventDefault();
				save_ss_edit();
				var $next = e.shiftKey ? $cell.prev( '.ecv2-ss-editable' ) : $cell.next( '.ecv2-ss-editable' );
				if ( ! $next.length ) {
					var $row = e.shiftKey ? $cell.closest( 'tr' ).prev() : $cell.closest( 'tr' ).next();
					$next = e.shiftKey ? $row.find( '.ecv2-ss-editable' ).last() : $row.find( '.ecv2-ss-editable' ).first();
				}
				if ( $next.length ) { $next.trigger( 'dblclick' ); }
			}
		});
		$input.on( 'blur', save_ss_edit );
	});

	var ECV2_BULK_MAX = 500;

	function ecv2_bulk_default_apply_text() {
		return ecv2_lang.apply || 'Apply';
	}

	function ecv2_bulk_refresh_apply_button() {
		var count = $( '.ecv2-row-check:checked' ).length;
		var $apply = $( '#ecv2-bulk-apply' );
		if ( ! $apply.length || $apply.data( 'ecv2-busy' ) ) return;

		if ( count > 0 ) {
			var tpl = ecv2_lang.bulk_apply_to || 'Apply to %d selected';
			$apply.text( tpl.replace( '%d', count ) ).prop( 'disabled', false );
		} else {
			$apply.text( ecv2_bulk_default_apply_text() ).prop( 'disabled', false );
		}
	}

	$( document ).on( 'change', '.ecv2-row-check, #ecv2-select-all, #ecv2-ss-select-all', function() {
		// Defer so the original handler runs first and sets .ecv2-selected-count.
		setTimeout( ecv2_bulk_refresh_apply_button, 0 );
	});
	$( function() { ecv2_bulk_refresh_apply_button(); } );

	function ecv2_bulk_collect_ids() {
		var ids = [];
		var seen = {};
		$( '.ecv2-row-check:checked' ).each( function() {
			var id = parseInt( $( this ).val(), 10 );
			if ( id > 0 && ! seen[ id ] ) {
				seen[ id ] = true;
				ids.push( id );
			}
		});
		return ids;
	}

	function ecv2_bulk_reset_selection() {
		$( '.ecv2-row-check' ).prop( 'checked', false );
		$( '#ecv2-select-all, #ecv2-ss-select-all' ).prop( 'checked', false ).prop( 'indeterminate', false );
		$( '.ecv2-bulk-count' ).hide();
		$( '#ecv2-bulk-edit-btn' ).hide();
		$( '#ecv2-bulk-action' ).val( '' );
		ecv2_bulk_refresh_apply_button();
	}

	function ecv2_bulk_set_busy( busy ) {
		var $apply = $( '#ecv2-bulk-apply' );
		if ( busy ) {
			$apply.data( 'ecv2-busy', true )
				.prop( 'disabled', true )
				.text( ecv2_lang.bulk_working || ecv2_lang.processing || 'Working…' );
		} else {
			$apply.data( 'ecv2-busy', false );
			ecv2_bulk_refresh_apply_button();
		}
	}

	window.ecv2_bulk_apply_validate = function( btn ) {
		var $select = $( '#ecv2-bulk-action' );
		var action  = $select.val();

		// Action selected?
		if ( ! action ) {
			$select.addClass( 'ecv2-bulk-highlight' );
			setTimeout( function() { $select.removeClass( 'ecv2-bulk-highlight' ); }, 1500 );
			ecv2_toast( ecv2_lang.bulk_no_action || 'Please select a bulk action.', 'error' );

			return;
		}

		// Export All is special: no selection needed, and we intentionally keep
		// the existing GET-based exporter (per product requirements). Just
		// navigate the form so filters are honored by the exporter template.
		if ( action === 'export-all-products-csv' ) {
			// Clear any stray bulk[] from the form to keep the GET URL short.
			$( 'input[name="bulk[]"]' ).prop( 'checked', false );
			$( btn ).closest( 'form' ).submit();
			return;
		}

		var ids = ecv2_bulk_collect_ids();

		if ( ids.length === 0 ) {
			ecv2_toast( ecv2_lang.bulk_none_selected || 'Please select products first.', 'error' );
			return;
		}

		if ( ids.length > ECV2_BULK_MAX ) {
			ecv2_toast( ecv2_lang.bulk_max_exceeded || 'You can only process up to 500 products at a time.', 'error' );
			return;
		}

		// Destructive-action confirmation.
		if ( action === 'delete-product' ) {
			var msg_tpl = ids.length === 1
				? ( ecv2_lang.bulk_confirm_delete_one  || 'Permanently delete this product? This action cannot be undone.' )
				: ( ecv2_lang.bulk_confirm_delete_many || 'Permanently delete %d products? This action cannot be undone.' );
			ecv2_show_confirm(
				ecv2_lang.bulk_confirm_delete_title || 'Delete products?',
				msg_tpl.replace( '%d', ids.length )
			).then( function( ok ) {
				if ( ok ) ecv2_bulk_run_delete( ids );
			});
			return;
		}

		if ( action === 'deactivate-product' && ids.length > 1 ) {
			ecv2_show_confirm(
				ecv2_lang.bulk_confirm_deactivate_title || 'Deactivate products?',
				( ecv2_lang.bulk_confirm_deactivate || 'Deactivate %d products?' ).replace( '%d', ids.length )
			).then( function( ok ) {
				if ( ok ) ecv2_bulk_run_status( ids, 0 );
			});
			return;
		}

		// Dispatch non-destructive actions immediately.
		switch ( action ) {
			case 'activate-product':
				ecv2_bulk_run_status( ids, 1 );
				break;
			case 'deactivate-product':
				ecv2_bulk_run_status( ids, 0 );
				break;
			case 'export-products-csv':
				ecv2_bulk_run_export( ids );
				break;
			default:
				// Unknown / extension-registered action — fall back to legacy form submit,
				// but only if the selection is small enough to survive the URL.
				if ( ids.length > 100 ) {
					ecv2_toast( ecv2_lang.bulk_max_exceeded || 'Too many selected for this action.', 'error' );
					return;
				}
				$( btn ).closest( 'form' ).submit();
		}
	};

	function ecv2_bulk_run_delete( ids ) {
		ecv2_bulk_set_busy( true );

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action:            'ecv2_product_bulk_delete',
				wp_easycart_nonce: ecv2_nonces.bulk_delete,
				product_ids:       ids
			}
		}).done( function( response ) {
			if ( response && response.success ) {
				// Remove rows from all views (table, card, spreadsheet).
				ids.forEach( function( id ) {
					$( '.ecv2-row[data-id="' + id + '"], .ecv2-card[data-id="' + id + '"], .ecv2-ss-row[data-id="' + id + '"]' ).fadeOut( 200, function() { $( this ).remove(); } );
				});
				ecv2_toast( response.data.message || ecv2_lang.saved, 'success' );
				ecv2_bulk_reset_selection();

				// Update the record count chip if present.
				var $rc = $( '.ecv2-record-count' );
				if ( $rc.length ) {
					var txt = $rc.text();
					var m = txt.match( /\d+/ );
					if ( m ) {
						var newCount = Math.max( 0, parseInt( m[0], 10 ) - ( response.data.deleted || 0 ) );
						$rc.text( txt.replace( /\d+/, newCount ) );
					}
				}
			} else {
				var errMsg = ( response && response.data && response.data.message ) ? response.data.message : ecv2_lang.error;
				ecv2_toast( errMsg, 'error' );
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.bulk_network_error || ecv2_lang.error, 'error' );
		}).always( function() {
			ecv2_bulk_set_busy( false );
		});
	}

	function ecv2_bulk_run_status( ids, new_status ) {
		ecv2_bulk_set_busy( true );

		var ajax_action = new_status ? 'ecv2_product_bulk_activate'   : 'ecv2_product_bulk_deactivate';
		var nonce       = new_status ? ecv2_nonces.bulk_activate      : ecv2_nonces.bulk_deactivate;

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action:            ajax_action,
				wp_easycart_nonce: nonce,
				product_ids:       ids
			}
		}).done( function( response ) {
			if ( response && response.success ) {
				// Update each row's status across every view, without a reload.
				var updated_ids = response.data.updated_ids || ids;
				updated_ids.forEach( function( id ) {
					ecv2_sync_product_field( id, 'is_visible', { active: new_status } );
				});

				ecv2_toast( response.data.message || ecv2_lang.saved, 'success' );

				// Offer an undo for the opposite operation.
				ecv2_push_undo({
					message: response.data.message + ' — ' + ecv2_lang.click_undo,
					undo_fn: function() {
						ecv2_bulk_run_status( updated_ids, new_status ? 0 : 1 );
					}
				});

				ecv2_bulk_reset_selection();
			} else {
				var errMsg = ( response && response.data && response.data.message ) ? response.data.message : ecv2_lang.error;
				ecv2_toast( errMsg, 'error' );
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.bulk_network_error || ecv2_lang.error, 'error' );
		}).always( function() {
			ecv2_bulk_set_busy( false );
		});
	}

	function ecv2_bulk_run_export( ids ) {
		ecv2_bulk_set_busy( true );

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action:            'ecv2_product_bulk_export_prepare',
				wp_easycart_nonce: ecv2_nonces.bulk_export,
				product_ids:       ids
			}
		}).done( function( response ) {
			if ( response && response.success && response.data && response.data.download_url ) {
				ecv2_show_export_banner( response.data );
				// Reset selection immediately — the banner holds the download link
				// independently, so the user can't accidentally re-submit a giant
				// bulk[] URL from a stale form state.
				ecv2_bulk_reset_selection();
			} else {
				var errMsg = ( response && response.data && response.data.message ) ? response.data.message : ecv2_lang.error;
				ecv2_toast( errMsg, 'error' );
			}
		}).fail( function() {
			ecv2_toast( ecv2_lang.bulk_network_error || ecv2_lang.error, 'error' );
		}).always( function() {
			ecv2_bulk_set_busy( false );
		});
	}

	/**
	 * Render the download-ready banner. The download URL is a nonced GET with
	 * only the token — short, safe for any server. The transient is one-use
	 * server-side so this link cannot be replayed.
	 */
	function ecv2_show_export_banner( data ) {
		// Remove any existing banner so only one is visible at a time.
		$( '#ecv2-export-banner' ).remove();

		var title = ecv2_lang.bulk_export_ready_title  || 'Your export is ready';
		var msg   = ( ecv2_lang.bulk_export_ready_message || '%d products ready for download.' ).replace( '%d', data.count );
		var dlTxt = ecv2_lang.bulk_export_download || 'Download CSV';
		var disTxt = ecv2_lang.bulk_export_dismiss || 'Dismiss';

		var $banner = $(
			'<div id="ecv2-export-banner" class="ecv2-export-banner" role="status" aria-live="polite">' +
				'<div class="ecv2-export-banner-icon"><span class="dashicons dashicons-download"></span></div>' +
				'<div class="ecv2-export-banner-body">' +
					'<div class="ecv2-export-banner-title"></div>' +
					'<div class="ecv2-export-banner-message"></div>' +
				'</div>' +
				'<div class="ecv2-export-banner-actions">' +
					'<a href="#" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" target="_blank" rel="noopener" id="ecv2-export-download-btn">' +
						'<span class="dashicons dashicons-download"></span> <span class="ecv2-export-download-label"></span>' +
					'</a>' +
					'<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" id="ecv2-export-dismiss-btn"></button>' +
				'</div>' +
			'</div>'
		);

		// Use .text() for all user-visible strings (XSS-safe).
		$banner.find( '.ecv2-export-banner-title' ).text( title );
		$banner.find( '.ecv2-export-banner-message' ).text( msg );
		$banner.find( '.ecv2-export-download-label' ).text( dlTxt );
		$banner.find( '#ecv2-export-dismiss-btn' ).text( disTxt );
		// Setting href via .attr() and then reading it back is XSS-safe; the URL
		// itself was generated by add_query_arg on the server with a nonced token.
		$banner.find( '#ecv2-export-download-btn' ).attr( 'href', data.download_url );

		// Insert at top of the wrap so it's immediately visible.
		var $wrap = $( '.ecv2-wrap' );
		if ( $wrap.length ) {
			$wrap.prepend( $banner );
		} else {
			$( 'body' ).append( $banner );
		}
		$banner.hide().slideDown( 200 );

		// Download click handler — we let the native <a target="_blank"> fire the
		// download (browsers keep the current tab when the response is
		// Content-Disposition: attachment), then auto-dismiss the banner after
		// a short delay so the user isn't left with stale UI.
		$banner.find( '#ecv2-export-download-btn' ).on( 'click', function() {
			ecv2_toast( ecv2_lang.bulk_export_downloading || 'Starting download…', 'info' );
			setTimeout( function() {
				$banner.slideUp( 200, function() { $( this ).remove(); } );
			}, 2000 );
		});

		$banner.find( '#ecv2-export-dismiss-btn' ).on( 'click', function() {
			$banner.slideUp( 200, function() { $( this ).remove(); } );
		});

		// Auto-dismiss after transient expiry so the link isn't clickable when dead.
		var expireMs = ( data.expires_in || 600 ) * 1000;
		setTimeout( function() {
			if ( $banner.parent().length ) {
				$banner.slideUp( 200, function() { $( this ).remove(); } );
			}
		}, expireMs );
	}

	/* ------------------------------------------------------------
	 * Escape key clears selection — small quality-of-life touch.
	 * ------------------------------------------------------------ */
	$( document ).on( 'keydown', function( e ) {
		// Ignore if a modal is open or focus is in an input/textarea.
		if ( e.key !== 'Escape' ) return;
		if ( $( '.ecv2-modal-overlay:visible' ).length ) return;
		if ( $( e.target ).is( 'input, textarea, select, [contenteditable="true"]' ) ) return;
		if ( $( '.ecv2-row-check:checked' ).length > 0 ) {
			ecv2_bulk_reset_selection();
		}
	});

	window.ecv2_open_bulk_edit = function() {
		var $checked = $( '.ecv2-row-check:checked' );
		var count = $checked.length;
		$( '#ecv2-bulk-edit-count' ).text( '(' + count + ' ' + ecv2_lang.products + ')' );

		// Count Square-synced products in the selection.
		var square_count = 0;
		$checked.each( function() {
			var pid = $( this ).val();
			if ( ecv2_is_square_product( pid ) ) {
				square_count++;
			}
		});

		// Remove any previous banner before re-opening.
		$( '#ecv2-bulk-square-notice' ).remove();

		var $priceField = $( '#ecv2-be-price-mode' ).closest( '.ecv2-modal-field' );
		var $stockField = $( '#ecv2-be-stock_quantity-mode' ).closest( '.ecv2-modal-field' );

		// Reset any prior locks.
		$priceField.removeClass( 'ecv2-modal-field-locked' ).find( '.ecv2-modal-field-lock-note' ).remove();
		$stockField.removeClass( 'ecv2-modal-field-locked' ).find( '.ecv2-modal-field-lock-note' ).remove();
		$( '#ecv2-be-price-mode, #ecv2-be-price-value, #ecv2-be-stock_quantity-mode, #ecv2-be-stock_quantity-value' ).prop( 'disabled', false );

		if ( square_count > 0 ) {
			var msg;
			if ( square_count === count ) {
				msg = ecv2_lang.bulk_square_all || 'All selected products are synced with Square. Price and stock changes are managed by Square and cannot be edited here.';
			} else {
				msg = ( ecv2_lang.bulk_square_some || '%d of %s selected products are synced with Square. Price and stock changes will be skipped for those products.' )
					.replace( '%d', square_count )
					.replace( '%s', count );
			}

			// Inject banner at top of modal body.
			var banner_html = '<div id="ecv2-bulk-square-notice" class="ecv2-bulk-notice ecv2-bulk-notice-square">' +
				'<span class="dashicons dashicons-lock"></span> ' +
				'<span>' + ecv2_esc_html( msg ) + '</span>' +
				'</div>';
			$( '#ecv2-bulk-edit-modal .ecv2-modal-body' ).prepend( banner_html );

			// If every selected product is Square, disable the price and stock fields entirely.
			if ( square_count === count ) {
				$priceField.addClass( 'ecv2-modal-field-locked' );
				$stockField.addClass( 'ecv2-modal-field-locked' );
				$( '#ecv2-be-price-mode, #ecv2-be-price-value, #ecv2-be-stock_quantity-mode, #ecv2-be-stock_quantity-value' ).prop( 'disabled', true ).val( '' );
				var lock_note = '<span class="ecv2-modal-field-lock-note"><span class="dashicons dashicons-lock"></span> ' + ecv2_esc_html( ecv2_lang.square_managed || 'Managed by Square' ) + '</span>';
				$priceField.find( '.ecv2-modal-label' ).append( ' ' + lock_note );
				$stockField.find( '.ecv2-modal-label' ).append( ' ' + lock_note );
			}
		}

		$( '#ecv2-bulk-edit-modal' ).fadeIn( 200 );
	};

	window.ecv2_close_bulk_edit = function() {
		$( '#ecv2-bulk-edit-modal' ).fadeOut( 200 );
	};

	window.ecv2_apply_bulk_edit = function() {
		var product_ids = [];
		$( '.ecv2-row-check:checked' ).each( function() {
			product_ids.push( $( this ).val() );
		});

		var changes = {};
		// Status.
		var status_val = $( '#ecv2-be-activate_in_store' ).val();
		if ( status_val !== '' ) changes.activate_in_store = status_val;
		// Manufacturer.
		var mfr_val = $( '#ecv2-be-manufacturer_id' ).val();
		if ( mfr_val !== '' ) changes.manufacturer_id = mfr_val;
		// Price.
		var price_mode = $( '#ecv2-be-price-mode' ).val();
		if ( price_mode !== '' ) {
			changes.price_mode = price_mode;
			changes.price_value = $( '#ecv2-be-price-value' ).val();
		}
		// Stock.
		var stock_mode = $( '#ecv2-be-stock_quantity-mode' ).val();
		if ( stock_mode !== '' ) {
			changes.stock_quantity_mode = stock_mode;
			changes.stock_quantity_value = $( '#ecv2-be-stock_quantity-value' ).val();
		}

		if ( Object.keys( changes ).length === 0 ) {
			ecv2_toast( ecv2_lang.no_changes, 'info' );
			return;
		}

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_bulk_edit',
				product_ids: product_ids,
				changes: changes,
				wp_easycart_nonce: ecv2_nonces.bulk_edit
			},
			success: function( response ) {
				if ( response.success ) {
					var d = response.data || {};
					var updated = d.updated || 0;
					var skipped = d.square_fully_skipped || 0;

					var toast_msg = updated + ' ' + ecv2_lang.products_updated;
					if ( skipped > 0 ) {
						toast_msg += ' ' + ( ecv2_lang.square_skipped_suffix || '(%d Square-synced product(s) skipped — managed by Square)' ).replace( '%d', skipped );
					}
					ecv2_toast( toast_msg, skipped > 0 ? 'info' : 'success' );
					ecv2_close_bulk_edit();
					setTimeout( function() { location.reload(); }, 1200 );
				} else {
					ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
				}
			},
			error: function() { ecv2_toast( ecv2_lang.error, 'error' ); }
		});
	};

	var ecv2_sale_current_price = 0;

	window.ecv2_open_schedule_sale = function( product_id ) {
		$( '#ecv2-sale-product-id' ).val( product_id );
		$( '#ecv2-sale-price' ).val( '' );
		$( '#ecv2-sale-start' ).val( '' );
		$( '#ecv2-sale-end' ).val( '' );
		$( '#ecv2-sale-discount-badge' ).hide();
		$( '#ecv2-sale-warning' ).hide();
		$( '#ecv2-sale-preview' ).hide();
		$( '#ecv2-sale-timing' ).hide();
		$( '#ecv2-sale-status-callout' ).hide();
		$( '#ecv2-sale-remove-btn' ).hide();
		$( '#ecv2-sale-save-btn' ).text( ecv2_lang.schedule_sale );

		// Close any open row menu.
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );

		// Fetch current price.
		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_get_sale_data',
				product_id: product_id,
				wp_easycart_nonce: ecv2_nonces.get_sale_data
			},
			success: function( response ) {
				if ( response.success ) {
					ecv2_sale_current_price = parseFloat( response.data.price );
					$( '#ecv2-sale-current-price' ).text( response.data.price_formatted );

					if ( parseFloat( response.data.list_price ) > 0 && parseFloat( response.data.list_price ) !== ecv2_sale_current_price ) {
						$( '#ecv2-sale-status-callout' )
							.html( '<strong>' + ecv2_lang.sale_active + '</strong>' )
							.css({ background: 'var(--ecv2-primary-light)', color: 'var(--ecv2-primary)' })
							.show();
						$( '#ecv2-sale-remove-btn' ).show();
						$( '#ecv2-sale-price' ).val( ecv2_sale_current_price );
						ecv2_update_sale_preview();
					}

					$( '#ecv2-schedule-sale-modal' ).fadeIn( 200 );
				}
			}
		});
	};

	window.ecv2_close_schedule_sale = function() {
		$( '#ecv2-schedule-sale-modal' ).fadeOut( 200 );
	};

	window.ecv2_update_sale_preview = function() {
		var sale_price = parseFloat( $( '#ecv2-sale-price' ).val() );
		if ( isNaN( sale_price ) || sale_price <= 0 ) {
			$( '#ecv2-sale-discount-badge' ).hide();
			$( '#ecv2-sale-preview' ).hide();
			$( '#ecv2-sale-warning' ).hide();
			return;
		}

		var discount = ( ( ecv2_sale_current_price - sale_price ) / ecv2_sale_current_price * 100 ).toFixed( 0 );
		$( '#ecv2-sale-discount-badge' ).text( discount + '% off' ).show();

		// Warnings.
		if ( sale_price >= ecv2_sale_current_price ) {
			$( '#ecv2-sale-warning' ).text( ecv2_lang.sale_price_higher ).show();
		} else if ( discount > 50 ) {
			$( '#ecv2-sale-warning' ).text( ecv2_lang.large_discount ).show();
		} else {
			$( '#ecv2-sale-warning' ).hide();
		}

		// Preview.
		$( '#ecv2-sale-preview-content' ).html(
			'<span style="text-decoration:line-through; color:#9ca3af; margin-right:8px;">' + ecv2_sale_current_price.toFixed(2) + '</span>' +
			'<strong style="color:var(--ecv2-primary); font-size:18px;">' + sale_price.toFixed(2) + '</strong>' +
			' <span style="background:var(--ecv2-primary-light); color:var(--ecv2-primary); padding:2px 6px; border-radius:8px; font-size:12px;">' + discount + '% off</span>'
		);
		$( '#ecv2-sale-preview' ).show();

		// Timing.
		var start = $( '#ecv2-sale-start' ).val();
		var end = $( '#ecv2-sale-end' ).val();
		var timing_text = '';
		if ( start && end ) timing_text = ecv2_lang.scheduled + ': ' + start + ' — ' + end;
		else if ( start ) timing_text = ecv2_lang.starts + ' ' + start;
		else if ( end ) timing_text = ecv2_lang.active_until + ' ' + end;
		else timing_text = ecv2_lang.starts_immediately;

		if ( timing_text ) {
			$( '#ecv2-sale-timing' ).text( timing_text ).show();
		}

		// Update button text.
		if ( start && new Date( start ) > new Date() ) {
			$( '#ecv2-sale-save-btn' ).text( ecv2_lang.schedule_sale );
		} else {
			$( '#ecv2-sale-save-btn' ).text( ecv2_lang.activate_sale );
		}
	};

	$( document ).on( 'change', '#ecv2-sale-start, #ecv2-sale-end', function() {
		ecv2_update_sale_preview();
	});

	window.ecv2_save_schedule_sale = function() {
		var product_id = $( '#ecv2-sale-product-id' ).val();
		var sale_price = $( '#ecv2-sale-price' ).val();

		if ( ! sale_price || parseFloat( sale_price ) <= 0 ) {
			ecv2_toast( ecv2_lang.enter_sale_price, 'error' );
			return;
		}

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_schedule_sale',
				product_id: product_id,
				sale_price: sale_price,
				wp_easycart_nonce: $( '#ecv2_sale_nonce' ).val()
			},
			success: function( response ) {
				if ( response.success ) {
					ecv2_toast( ecv2_lang.sale_saved, 'success' );
					ecv2_close_schedule_sale();
					setTimeout( function() { location.reload(); }, 800 );
				} else {
					ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
				}
			},
			error: function() { ecv2_toast( ecv2_lang.error, 'error' ); }
		});
	};

	window.ecv2_remove_sale = function() {
		if ( ! confirm( ecv2_lang.confirm_remove_sale ) ) return;
		var product_id = $( '#ecv2-sale-product-id' ).val();
		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_remove_sale',
				product_id: product_id,
				wp_easycart_nonce: ecv2_nonces.remove_sale
			},
			success: function( response ) {
				if ( response.success ) {
					ecv2_toast( ecv2_lang.sale_removed, 'success' );
					ecv2_close_schedule_sale();
					setTimeout( function() { location.reload(); }, 800 );
				}
			}
		});
	};

	$( document ).on( 'click', '.ecv2-modal-overlay', function( e ) {
		if ( $( e.target ).is( '.ecv2-modal-overlay' ) ) {
			$( this ).fadeOut( 200 );
		}
	});

	/* --- Close modals on Escape --- */
	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' ) {
			// Close stock menus first.
			$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			$( '.ecv2-modal-overlay:visible' ).fadeOut( 200 );
		}
	});

	/* --- Stock Badge Menu --- */

	window.ecv2_open_stock_menu = function( btn ) {
		var $wrap = $( btn ).closest( '.ecv2-stock-wrap' );
		if ( ecv2_is_square_product( $wrap ) ) { ecv2_square_blocked_alert(); return; }
		var $menu = $wrap.find( '.ecv2-stock-menu' );

		// Close all other stock menus.
		$( '.ecv2-stock-menu' ).not( $menu ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
		// Close row menus too.
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css({ top: '', right: '', left: '' });

		// Reset z-index on all cards, then raise the active one.
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );

		// If inside a card, use fixed positioning to escape overflow.
		if ( $( btn ).closest( '.ecv2-card, .ecv2-table, .ecv2-spreadsheet' ).length ) {
			var rect = btn.getBoundingClientRect();
			$menu.addClass( 'ecv2-menu-fixed' );
			var menu_top = rect.bottom + 4;
			// Align left edge to button left, but ensure it doesn't overflow viewport right.
			var menu_left = rect.left;
			$menu.css({ top: menu_top + 'px', left: menu_left + 'px', right: 'auto' });

			// After display, check if menu overflows viewport right edge.
			$menu.toggleClass( 'ecv2-stock-menu-open' );
			if ( $menu.hasClass( 'ecv2-stock-menu-open' ) ) {
				$( btn ).closest( '.ecv2-card' ).addClass( 'ecv2-card-menu-active' );
				var menu_rect = $menu[0].getBoundingClientRect();
				if ( menu_rect.right > window.innerWidth - 8 ) {
					$menu.css({ left: 'auto', right: '8px' });
				}
				// Check if menu overflows viewport bottom edge.
				if ( menu_rect.bottom > window.innerHeight - 8 ) {
					var above_top = rect.top - menu_rect.height - 4;
					if ( above_top > 8 ) {
						$menu.css({ top: above_top + 'px' });
					}
				}
			}
		} else {
			$menu.removeClass( 'ecv2-menu-fixed' );
			$menu.css({ top: '', left: '', right: '' });
			$menu.toggleClass( 'ecv2-stock-menu-open' );
		}

		// Focus the input if present.
		setTimeout( function() {
			$menu.find( '.ecv2-stock-menu-input' ).focus().select();
		}, 50 );
	};

	// Close stock menus on outside click.
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecv2-stock-wrap' ).length ) {
			$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap, .ecv2-price-cell' ).length ) {
				$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
			}
		}
		if ( ! $( e.target ).closest( '.ecv2-price-cell' ).length ) {
			$( '.ecv2-price-menu' ).removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			if ( ! $( e.target ).closest( '.ecv2-row-menu-wrap, .ecv2-stock-wrap' ).length ) {
				$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
			}
		}
	});

	/* --- Price Editor Popover (non-variant products) --- */

	window.ecv2_open_price_editor = function( btn ) {
		var $cell = $( btn ).closest( '.ecv2-price-cell' );
		if ( ecv2_is_square_product( $cell ) ) { ecv2_square_blocked_alert(); return; }
		var $menu = $cell.find( '.ecv2-price-menu' );

		// Close all other price menus and stock menus.
		$( '.ecv2-price-menu' ).not( $menu ).removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
		$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' ).css({ top: '', right: '', left: '' });

		// Reset z-index on all cards, then raise the active one.
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );

		// If inside a card, use fixed positioning.
		if ( $( btn ).closest( '.ecv2-card, .ecv2-table, .ecv2-spreadsheet' ).length ) {
			var rect = btn.getBoundingClientRect();
			$menu.addClass( 'ecv2-menu-fixed' );
			var menu_left = rect.left;
			var menu_top = rect.bottom + 4;
			$menu.css({ top: menu_top + 'px', left: menu_left + 'px', right: 'auto' });
			$menu.toggleClass( 'ecv2-price-menu-open' );
			if ( $menu.hasClass( 'ecv2-price-menu-open' ) ) {
				$( btn ).closest( '.ecv2-card' ).addClass( 'ecv2-card-menu-active' );
				var menu_rect = $menu[0].getBoundingClientRect();
				if ( menu_rect.right > window.innerWidth - 8 ) {
					$menu.css({ left: 'auto', right: '8px' });
				}
				if ( menu_rect.bottom > window.innerHeight - 8 ) {
					var above_top = rect.top - menu_rect.height - 4;
					if ( above_top > 8 ) {
						$menu.css({ top: above_top + 'px' });
					}
				}
			}
		} else {
			$menu.removeClass( 'ecv2-menu-fixed' );
			$menu.css({ top: '', left: '', right: '' });
			$menu.toggleClass( 'ecv2-price-menu-open' );
		}

		// Update preview on open.
		ecv2_update_price_preview( $cell );

		// Snapshot the current state of every data-field control so Cancel/Escape
		// can revert checkboxes and selects in addition to the text inputs.
		ecv2_snapshot_price_editor_state( $menu );

		// Focus the first input.
		setTimeout( function() {
			$menu.find( '.ecv2-price-menu-input' ).first().focus().select();
		}, 50 );
	};

	function ecv2_snapshot_price_editor_state( $menu ) {
		$menu.find( '[data-field]' ).each( function() {
			var $el = $( this );
			if ( $el.is( ':checkbox' ) ) {
				$el.data( 'snap-checked', $el.is( ':checked' ) );
			} else if ( $el.is( 'select' ) ) {
				// store JSON of values to handle multi-select
				$el.data( 'snap-value', JSON.stringify( $el.val() ) );
			} else {
				// text inputs already have data-original; snapshot it for completeness
				if ( typeof $el.data( 'original' ) === 'undefined' ) {
					$el.data( 'snap-value', $el.val() );
				}
			}
		});
	}

	function ecv2_restore_price_editor_state( $menu ) {
		$menu.find( '[data-field]' ).each( function() {
			var $el = $( this );
			if ( $el.is( ':checkbox' ) ) {
				var was = $el.data( 'snap-checked' );
				if ( typeof was !== 'undefined' ) {
					$el.prop( 'checked', !!was );
				}
			} else if ( $el.is( 'select' ) ) {
				var json = $el.data( 'snap-value' );
				if ( typeof json !== 'undefined' ) {
					try { $el.val( JSON.parse( json ) ); } catch ( e ) {}
				}
			} else if ( typeof $el.data( 'original' ) !== 'undefined' ) {
				$el.val( $el.data( 'original' ) );
			} else {
				var snap = $el.data( 'snap-value' );
				if ( typeof snap !== 'undefined' ) {
					$el.val( snap );
				}
			}
		});

		// Re-evaluate conditional sections to match restored state.
		$menu.find( '[data-field]' ).each( function() {
			var $el = $( this );
			if ( $el.is( ':checkbox' ) || $el.is( 'select' ) ) {
				$el.trigger( 'change' );
			}
		});
	}

	// Live preview as user types.
	$( document ).on( 'input', '.ecv2-price-menu-input', function() {
		var $cell = $( this ).closest( '.ecv2-price-cell' );
		ecv2_update_price_preview( $cell );
	});

	// Enter key saves, Escape closes.
	$( document ).on( 'keydown', '.ecv2-price-menu-input, .ecv2-price-menu-select', function( e ) {
		if ( e.key === 'Enter' && ! $( this ).is( 'select' ) ) {
			e.preventDefault();
			ecv2_save_price_editor( $( this ).closest( '.ecv2-price-menu' ).find( '.ecv2-btn-primary' )[0] );
		}
		if ( e.key === 'Escape' ) {
			// Reset to original values and close.
			var $cell = $( this ).closest( '.ecv2-price-cell' );
			var $menu = $cell.find( '.ecv2-price-menu' );
			ecv2_restore_price_editor_state( $menu );
			$menu.removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
		}
	});

	function ecv2_update_price_preview( $cell ) {
		var product_id = $cell.data( 'product-id' );
		var $preview = $cell.find( '.ecv2-price-menu-preview' );
		var price_val = parseFloat( $cell.find( '.ecv2-price-menu-input[data-field="price"]' ).val() ) || 0;
		var list_val = parseFloat( $cell.find( '.ecv2-price-menu-input[data-field="list_price"]' ).val() ) || 0;

		if ( list_val > 0 && list_val > price_val && price_val > 0 ) {
			var pct = Math.round( ( list_val - price_val ) / list_val * 100 );
			$preview.html(
				'<span class="ecv2-price-menu-preview-list">' + list_val.toFixed( 2 ) + '</span> ' +
				'<span class="ecv2-price-menu-preview-current">' + price_val.toFixed( 2 ) + '</span> ' +
				'<span class="ecv2-price-menu-preview-tag">' + pct + '% off</span>'
			).show();
		} else if ( list_val > 0 && list_val <= price_val ) {
			$preview.html(
				'<span class="ecv2-price-menu-preview-warn">' + ecv2_esc_html( ecv2_lang.list_price_not_higher ) + '</span>'
			).show();
		} else {
			$preview.hide();
		}
	}

	/* ----------------------------------------------------------------------
	 * Expanded price editor helpers (sectioned popover)
	 * ---------------------------------------------------------------------- */
	// Toggle the "More pricing options" disclosure.
	window.ecv2_toggle_price_more = function( btn ) {
		var $btn = $( btn );
		var $extras = $btn.next( '.ecv2-price-menu-extras' );
		var is_open = $extras.is( ':visible' );
		if ( is_open ) {
			$extras.slideUp( 150 );
			$btn.attr( 'aria-expanded', 'false' );
			$btn.find( '.ecv2-price-menu-more-chev' ).removeClass( 'ecv2-rotated' );
		} else {
			$extras.slideDown( 150 );
			$btn.attr( 'aria-expanded', 'true' );
			$btn.find( '.ecv2-price-menu-more-chev' ).addClass( 'ecv2-rotated' );
		}
	};

	// Show/hide conditional sections when their controlling field changes. Works inside
	// either the inline price popover or the Advanced Pricing slideout — both reuse the
	// same .ecv2-price-menu-conditional[data-show-when=...] markup.
	$( document ).on( 'change', '.ecv2-price-menu input[type="checkbox"][data-field], .ecv2-price-menu select[data-field], .ecv2-slideout-advanced-pricing input[type="checkbox"][data-field], .ecv2-slideout-advanced-pricing select[data-field]', function() {
		var $el = $( this );
		var field = $el.attr( 'data-field' );
		if ( ! field ) { return; }

		var raw_value;
		if ( $el.is( ':checkbox' ) ) {
			raw_value = $el.is( ':checked' ) ? '1' : '0';
		} else {
			raw_value = String( $el.val() );
		}

		// Scope conditional lookup to whichever container the field lives in so the popover
		// and slideout don't cross-trigger each other's conditional rows.
		var $scope = $el.closest( '.ecv2-price-menu, .ecv2-slideout-advanced-pricing' );
		$scope.find( '.ecv2-price-menu-conditional[data-show-when="' + field + '"]' ).each( function() {
			var $cond = $( this );
			var not_value = $cond.attr( 'data-show-when-not' );
			var show;
			if ( typeof not_value !== 'undefined' && not_value !== false ) {
				show = ( raw_value !== String( not_value ) );
			} else {
				show = ( raw_value === '1' );
			}
			if ( show ) {
				$cond.slideDown( 150 );
			} else {
				$cond.slideUp( 150 );
			}
		});
	});

	// Cancel: revert all inputs/checkboxes/selects to their state at popup-open time, close.
	window.ecv2_close_price_editor = function( btn ) {
		var $cell = $( btn ).closest( '.ecv2-price-cell' );
		var $menu = $cell.find( '.ecv2-price-menu' );
		ecv2_restore_price_editor_state( $menu );
		$menu.removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
		$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );
	};

	function ecv2_reset_price_editor( $cell ) {
		// Legacy helper kept for back-compat with anything calling it externally.
		ecv2_restore_price_editor_state( $cell.find( '.ecv2-price-menu' ) );
	}

	// Build the closed-cell badge HTML (mirrors the PHP print_price_cell_badges output).
	function ecv2_build_price_flags_html( d ) {
		var flags = [];

		if ( d.login_for_pricing && parseInt( d.login_for_pricing, 10 ) === 1 ) {
			flags.push({
				icon:  'lock',
				label: ( ecv2_lang.flag_login || 'Login' ),
				title: ( ecv2_lang.flag_login_action_title || 'Login required to view price — click to manage' ),
				cls:   'ecv2-price-flag-login',
				// Opens the Advanced Pricing slideout — that's where the login_for_pricing
				// checkbox, role multi-select, and login button label all live.
				action: 'ecv2_open_advanced_from_badge( this ); return false;'
			});
		}

		if ( d.enable_price_label && parseInt( d.enable_price_label, 10 ) > 0 ) {
			var lbl_text = d.custom_price_label && String( d.custom_price_label ).length ? String( d.custom_price_label ) : ( ecv2_lang.flag_label_default || 'Custom label' );
			flags.push({
				icon:  'tag',
				label: lbl_text,
				title: ( ecv2_lang.flag_label_action_title || 'Custom price label is enabled — click to edit' ),
				cls:   'ecv2-price-flag-pricelabel',
				action: 'ecv2_open_advanced_from_badge( this ); return false;'
			});
		}

		if ( d.show_custom_price_range && parseInt( d.show_custom_price_range, 10 ) === 1 ) {
			var low  = parseFloat( d.price_range_low ) || 0;
			var high = parseFloat( d.price_range_high ) || 0;
			flags.push({
				icon:  'leftright',
				label: ( d.price_range_low_formatted || low.toFixed( 2 ) ) + '\u2013' + ( d.price_range_high_formatted || high.toFixed( 2 ) ),
				title: ( ecv2_lang.flag_range_action_title || 'Displayed as a price range on the storefront — click to edit' ),
				cls:   'ecv2-price-flag-range',
				action: 'ecv2_open_advanced_from_badge( this ); return false;'
			});
		}

		var tier_count = parseInt( d.tier_count, 10 ) || 0;
		if ( tier_count > 0 ) {
			flags.push({
				icon:    'chart-bar',
				label:   ( tier_count === 1 ? '1 ' + ( ecv2_lang.tier_singular || 'tier' ) : tier_count + ' ' + ( ecv2_lang.tier_plural || 'tiers' ) ),
				title:   ( ecv2_lang.flag_tier_action_title || 'Volume pricing — click to manage' ),
				cls:     'ecv2-price-flag-tier',
				// 'action' makes the flag render as a <button> shortcut into the volume manager.
				action:  'ecv2_open_volume_from_badge( this ); return false;'
			});
		}

		var role_count = parseInt( d.roleprice_count, 10 ) || 0;
		if ( role_count > 0 ) {
			flags.push({
				icon:    'groups',
				label:   ( role_count === 1 ? '1 ' + ( ecv2_lang.b2b_singular || 'B2B role' ) : role_count + ' ' + ( ecv2_lang.b2b_plural || 'B2B roles' ) ),
				title:   ( ecv2_lang.flag_b2b_action_title || 'B2B role pricing — click to manage' ),
				cls:     'ecv2-price-flag-b2b',
				action:  'ecv2_open_b2b_from_badge( this ); return false;'
			});
		}

		// Allow PRO-side JS to extend the badge list (mirrors the PHP filter).
		if ( typeof window.ecv2_filter_price_cell_badges === 'function' ) {
			try {
				flags = window.ecv2_filter_price_cell_badges( flags, d ) || flags;
			} catch ( err ) { /* swallow */ }
		}

		if ( ! flags.length ) { return ''; }

		var html = '<div class="ecv2-price-flags">';
		for ( var i = 0; i < flags.length; i++ ) {
			var f = flags[ i ];
			if ( f.action ) {
				html += '<button type="button" class="ecv2-price-flag ecv2-price-flag-action ' + ecv2_esc_attr( f.cls || '' ) + '" onclick="' + ecv2_esc_attr( f.action ) + '" title="' + ecv2_esc_attr( f.title || '' ) + '">';
			} else {
				html += '<span class="ecv2-price-flag ' + ecv2_esc_attr( f.cls || '' ) + '" title="' + ecv2_esc_attr( f.title || '' ) + '">';
			}
			html += '<span class="dashicons dashicons-' + ecv2_esc_attr( f.icon || 'marker' ) + '" aria-hidden="true"></span>';
			html += '<span class="ecv2-price-flag-label">' + ecv2_esc_html( f.label || '' ) + '</span>';
			html += f.action ? '</button>' : '</span>';
		}
		html += '</div>';
		return html;
	}

	/**
	 * Refresh the .ecv2-price-flags row inside a price cell after a save. The flags row is
	 * now a SIBLING of .ecv2-price-badge-btn (not nested inside it), so this helper finds
	 * an existing row, inserts/replaces/removes it as needed, and inserts it after the
	 * .ecv2-price-cell-main wrapper to preserve the price-then-flags vertical order.
	 *
	 * @param {jQuery} $cell — the .ecv2-price-cell jQuery object.
	 * @param {Object} d     — response data from ecv2_product_save_prices.
	 */
	function ecv2_refresh_price_flags_row( $cell, d ) {
		var new_html = ecv2_build_price_flags_html( d );
		var $existing = $cell.children( '.ecv2-price-flags' );

		if ( ! new_html ) {
			$existing.remove();
			return;
		}

		if ( $existing.length ) {
			$existing.replaceWith( new_html );
		} else {
			// Insert after the .ecv2-price-cell-main wrapper. Falls back to appending if the
			// wrapper isn't present (e.g. third-party themes overriding the structure).
			var $main = $cell.children( '.ecv2-price-cell-main' );
			if ( $main.length ) {
				$main.after( new_html );
			} else {
				$cell.append( new_html );
			}
		}
	}
	window.ecv2_refresh_price_flags_row = ecv2_refresh_price_flags_row;

	function ecv2_esc_attr( s ) {
		return String( s == null ? '' : s ).replace( /[&"'<>]/g, function( c ) {
			return { '&':'&amp;', '"':'&quot;', "'":'&#039;', '<':'&lt;', '>':'&gt;' }[ c ];
		});
	}
	window.ecv2_esc_attr = ecv2_esc_attr;

	window.ecv2_save_price_editor = function( btn ) {
		var $cell = $( btn ).closest( '.ecv2-price-cell' );
		var product_id = $cell.data( 'product-id' );
		var nonce = $cell.data( 'nonce' );
		var $menu = $cell.find( '.ecv2-price-menu' );

		// Required fields.
		var $price_input = $menu.find( '.ecv2-price-menu-input[data-field="price"]' );
		var $list_input = $menu.find( '.ecv2-price-menu-input[data-field="list_price"]' );
		var price_val = $price_input.val().replace( /[^0-9.\-]/g, '' );
		var list_val = $list_input.val().replace( /[^0-9.\-]/g, '' );

		var post_data = {
			action: 'ecv2_product_save_prices',
			product_id: product_id,
			price: price_val,
			list_price: list_val,
			wp_easycart_nonce: nonce
		};

		// Optional fields — only send values for fields that the user actually has visible/active.
		// (The server only writes columns whose key is present in $_POST — partial-save safe.)
		$menu.find( '.ecv2-price-menu-input[data-field], .ecv2-price-menu-select[data-field], input[type="checkbox"][data-field]' ).each( function() {
			var $el = $( this );
			var field = $el.attr( 'data-field' );
			if ( ! field || field === 'price' || field === 'list_price' ) { return; }

			if ( $el.is( ':checkbox' ) ) {
				post_data[ field ] = $el.is( ':checked' ) ? '1' : '0';
			} else if ( $el.is( 'select[multiple]' ) ) {
				// Multi-selects need bracketed name to come through as an array on the server.
				var values = $el.val() || [];
				post_data[ field ] = values; // jQuery serializes arrays as field[]=v1&field[]=v2.
			} else {
				var raw = $el.val();
				if ( raw == null ) { raw = ''; }
				// Numeric range fields: strip currency formatting before posting.
				if ( field === 'price_range_low' || field === 'price_range_high' ) {
					raw = String( raw ).replace( /[^0-9.\-]/g, '' );
				}
				post_data[ field ] = raw;
			}
		});

		var $save_btn = $( btn );
		$save_btn.prop( 'disabled', true ).text( ecv2_lang.loading );

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: post_data,
			// jQuery default (traditional:false) → field[]=v1&field[]=v2, which PHP receives as an array.
			// Required so multi-selects (e.g. login_for_pricing_user_level) come through with all values.
			success: function( response ) {
				$save_btn.prop( 'disabled', false ).text( ecv2_lang.save_label || 'Save' );
				if ( response && response.success ) {
					var d = response.data;

					ecv2_sync_product_field( product_id, 'prices', {
						price:                d.price,
						list_price:           d.list_price,
						price_formatted:      d.price_formatted,
						list_price_formatted: d.list_price_formatted,
						is_on_sale:           d.is_on_sale,
						discount_pct:         d.discount_pct
					});

					// Refresh the flags row on the local cell. Now lives outside the price button — find or create it.
					ecv2_refresh_price_flags_row( $cell, d );

					// Close the menu.
					$menu.removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
					$( '.ecv2-card' ).removeClass( 'ecv2-card-menu-active' );

					ecv2_toast( ecv2_lang.price_saved, 'success' );

					// Push undo (still scoped to price + list_price; advanced fields aren't undone here
					// because they require their own nonced save path).
					ecv2_push_undo({
						message: ecv2_lang.price_saved + ' \u2014 ' + ecv2_lang.click_undo,
						undo_fn: function() {
							$.ajax({
								url: wpeasycart_admin_ajax_object.ajax_url,
								type: 'POST',
								data: {
									action: 'ecv2_product_save_prices',
									product_id: product_id,
									price: d.old_price,
									list_price: d.old_list_price,
									wp_easycart_nonce: nonce
								},
								success: function( r2 ) {
									if ( r2 && r2.success ) {
										if ( r2.data ) {
											ecv2_sync_product_field( product_id, 'prices', {
												price:                r2.data.price,
												list_price:           r2.data.list_price,
												price_formatted:      r2.data.price_formatted,
												list_price_formatted: r2.data.list_price_formatted,
												is_on_sale:           r2.data.is_on_sale,
												discount_pct:         r2.data.discount_pct
											});
											ecv2_refresh_price_flags_row( $cell, r2.data );
										}
										ecv2_toast( ecv2_lang.undone, 'info' );
									}
								}
							});
						}
					});
				} else {
					var err_msg = ( response && response.data && response.data.message ) ? response.data.message : ecv2_lang.error;
					ecv2_toast( err_msg, 'error' );
				}
			},
			error: function() {
				$save_btn.prop( 'disabled', false ).text( ecv2_lang.save_label || 'Save' );
				ecv2_toast( ecv2_lang.error, 'error' );
			}
		});
	};

	/* Helper: show/hide the indicator pip on the popover's Advanced pricing link based
	 * on the current data-advanced state. Called after any save that could change it. */
	window.ecv2_refresh_advanced_pip = function( $cell ) {
		var advanced;
		try { advanced = JSON.parse( $cell.attr( 'data-advanced' ) || '{}' ); } catch ( e ) { advanced = {}; }

		var any_active = (
			parseInt( advanced.show_custom_price_range, 10 ) === 1 ||
			parseInt( advanced.enable_price_label, 10 ) > 0 ||
			parseInt( advanced.login_for_pricing, 10 ) === 1 ||
			parseInt( advanced.tier_count, 10 ) > 0 ||
			parseInt( advanced.roleprice_count, 10 ) > 0
		);

		var $link = $cell.find( '.ecv2-price-menu-advanced-link' );
		var $pip  = $link.find( '.ecv2-price-menu-advanced-pip' );
		if ( any_active ) {
			if ( ! $pip.length ) {
				// Insert before the chevron so the visual order stays Label → PRO? → Pip → Chev.
				$link.find( '.ecv2-price-menu-advanced-chev' ).before(
					'<span class="ecv2-price-menu-advanced-pip" aria-label="' + ecv2_esc_attr( ecv2_lang.advanced_pip_aria || 'Advanced pricing options are configured for this product' ) + '" title="' + ecv2_esc_attr( ecv2_lang.advanced_pip_title || 'Advanced pricing configured' ) + '"></span>'
				);
			}
		} else {
			$pip.remove();
		}
	};

	/* Helper: refresh the count chip / data-advanced after a manager modal save or delete.
	 * Called by the volume + B2B managers' success handlers. The slideout (if open) has
	 * its chip updated; the cell's data-advanced is bumped so a future open is fresh. */
	window.ecv2_refresh_price_cell_count = function( product_id, field, count ) {
		var $cell = $( '.ecv2-price-cell[data-product-id="' + product_id + '"]' ).first();
		if ( ! $cell.length ) { return; }

		// Bump the cell's data-advanced JSON.
		var advanced;
		try { advanced = JSON.parse( $cell.attr( 'data-advanced' ) || '{}' ); } catch ( e ) { advanced = {}; }
		if ( field === 'tier_count' )      { advanced.tier_count = count; }
		if ( field === 'roleprice_count' ) { advanced.roleprice_count = count; }
		$cell.attr( 'data-advanced', JSON.stringify( advanced ) );

		ecv2_refresh_price_flags_row( $cell, advanced );

		// If the slideout is currently bound to this product, refresh its visible chip.
		if ( typeof window.ecv2_advanced_render_count_chip === 'function' ) {
			window.ecv2_advanced_render_count_chip( field, count, product_id );
		}

		// Pip on the popover's Advanced link reflects whatever state we're now in.
		window.ecv2_refresh_advanced_pip( $cell );
	};

	// Close fixed-position price menus on scroll.
	$( window ).on( 'scroll', function() {
		$( '.ecv2-price-menu.ecv2-menu-fixed' ).removeClass( 'ecv2-price-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
	});

	/* --- Save Basic Stock Quantity (from stock menu) --- */

	window.ecv2_save_stock_qty = function( btn ) {
		var $wrap = $( btn ).closest( '.ecv2-stock-wrap' );
		var product_id = $wrap.data( 'product-id' );
		var $input = $wrap.find( '.ecv2-stock-menu-input' );
		var new_qty = parseInt( $input.val(), 10 );
		var old_qty = parseInt( $input.data( 'original' ), 10 );

		if ( isNaN( new_qty ) || new_qty === old_qty ) {
			$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			return;
		}

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_product_inline_update',
				product_id: product_id,
				field: 'stock_quantity',
				value: new_qty,
				wp_easycart_nonce: ecv2_nonces.inline_update
			},
			success: function( response ) {
				if ( response.success ) {
					ecv2_toast( ecv2_lang.stock_saved, 'success' );
					$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
					// Update input's original value.
					$input.data( 'original', new_qty );
					ecv2_sync_product_field( product_id, 'stock_quantity', {
						tracking_type:  'basic',
						stock_quantity: new_qty,
						option_total:   0
					});
				} else {
					ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
				}
			},
			error: function() { ecv2_toast( ecv2_lang.error, 'error' ); }
		});
	};

	// Allow Enter key to save in stock menu input.
	$( document ).on( 'keydown', '.ecv2-stock-menu-input', function( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			$( this ).closest( '.ecv2-stock-menu-section' ).find( '.ecv2-btn' ).trigger( 'click' );
		}
		// Stop propagation so Escape doesn't also close the menu
		if ( e.key === 'Escape' ) {
			e.stopPropagation();
			$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
		}
	});

	/* --- Confirmation Dialog --- */

	var ecv2_confirm_resolve = null;

	function ecv2_show_confirm( title, message ) {
		return new Promise( function( resolve ) {
			ecv2_confirm_resolve = resolve;
			$( '#ecv2-confirm-title' ).text( title );
			$( '#ecv2-confirm-message' ).text( message );
			$( '#ecv2-confirm-dialog' ).fadeIn( 200 );
		});
	}
	window.ecv2_show_confirm = ecv2_show_confirm;

	window.ecv2_confirm_ok = function() {
		$( '#ecv2-confirm-dialog' ).fadeOut( 200 );
		if ( ecv2_confirm_resolve ) {
			ecv2_confirm_resolve( true );
			ecv2_confirm_resolve = null;
		}
	};

	window.ecv2_confirm_cancel = function() {
		$( '#ecv2-confirm-dialog' ).fadeOut( 200 );
		if ( ecv2_confirm_resolve ) {
			ecv2_confirm_resolve( false );
			ecv2_confirm_resolve = null;
		}
	};

	// Prevent overlay click from double-firing on confirm dialog.
	$( document ).on( 'click', '#ecv2-confirm-dialog', function( e ) {
		if ( $( e.target ).is( '#ecv2-confirm-dialog' ) ) {
			ecv2_confirm_cancel();
			return false;
		}
	});

	/* --- Switch Tracking Type --- */

	window.ecv2_switch_tracking_type = function( el, new_type ) {
		var $wrap = $( el ).closest( '.ecv2-stock-wrap' );
		var product_id = $wrap.data( 'product-id' );
		var nonce = $wrap.data( 'nonce' );
		var current_type = $wrap.data( 'tracking' );

		if ( new_type === current_type ) {
			$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });
			if ( new_type === 'option' && ecv2_lang.variant_tracking_enabled && typeof window.ecv2_open_variant_popup === 'function' ) {
				window.ecv2_open_variant_popup( product_id );
			}
			return;
		}

		// Close the stock menu immediately.
		$( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ).css({ top: '', left: '', right: '' });

		// Get confirmation message.
		var confirm_msg = '';
		switch ( new_type ) {
			case 'unlimited': confirm_msg = ecv2_lang.confirm_unlimited; break;
			case 'basic':     confirm_msg = ecv2_lang.confirm_basic; break;
			case 'option':    confirm_msg = ecv2_lang.confirm_option; break;
		}

		ecv2_show_confirm( ecv2_lang.confirm_tracking_title, confirm_msg ).then( function( confirmed ) {
			if ( ! confirmed ) return;

			$.ajax({
				url: wpeasycart_admin_ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'ecv2_product_change_tracking_type',
					product_id: product_id,
					tracking_type: new_type,
					wp_easycart_nonce: nonce
				},
				success: function( response ) {
					if ( response.success ) {
						ecv2_toast( ecv2_lang.tracking_changed, 'success' );
						ecv2_sync_product_field( product_id, 'stock_quantity', response.data );
					} else {
						ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
					}
				},
				error: function() { ecv2_toast( ecv2_lang.error, 'error' ); }
			});
		});
	};

	/**
	 * Rebuild the stock badge and menu entirely based on server response.
	 * Handles Fix #2 (no page reload) and Fix #3 (option total display).
	 */
	function ecv2_rebuild_stock_badge( $wrap, data ) {
		var type = data.tracking_type;
		var qty = parseInt( data.stock_quantity, 10 ) || 0;
		var option_total = parseInt( data.option_total, 10 ) || 0;
		var product_id = data.product_id;
		var nonce = $wrap.data( 'nonce' );
		var low_threshold = 10;

		// Update data attributes on the wrap.
		$wrap.data( 'tracking', type ).attr( 'data-tracking', type );
		$wrap.data( 'stock-qty', qty ).attr( 'data-stock-qty', qty );
		$wrap.data( 'option-total', option_total ).attr( 'data-option-total', option_total );

		// Rebuild badge button.
		var badge_html = '';
		if ( type === 'option' ) {
			badge_html = '<span class="ecv2-stock-badge ecv2-stock-option"><span class="dashicons dashicons-admin-settings"></span> ' + option_total + ' ' + ecv2_lang.in_stock + '</span>';
		} else if ( type === 'unlimited' ) {
			badge_html = '<span class="ecv2-stock-badge ecv2-stock-unlimited">&infin; ' + ecv2_lang.unlimited_label + '</span>';
		} else if ( qty <= 0 ) {
			badge_html = '<span class="ecv2-stock-badge ecv2-stock-out">' + ecv2_lang.out_of_stock + '</span>';
		} else if ( qty <= low_threshold ) {
			badge_html = '<span class="ecv2-stock-badge ecv2-stock-low">' + qty + ' ' + ecv2_lang.left + '</span>';
		} else {
			badge_html = '<span class="ecv2-stock-badge ecv2-stock-ok">' + qty + ' ' + ecv2_lang.in_stock + '</span>';
		}
		$wrap.find( '.ecv2-stock-badge-btn' ).html( badge_html );

		// Rebuild the dropdown menu.
		var menu_html = '';

		// Basic tracking: inline qty editor.
		if ( type === 'basic' ) {
			menu_html += '<div class="ecv2-stock-menu-section">';
			menu_html += '<label class="ecv2-stock-menu-label">' + ecv2_lang.stock_saved.replace( ecv2_lang.stock_saved, 'Stock Quantity' ) + '</label>';
			menu_html += '<div class="ecv2-stock-menu-inline">';
			menu_html += '<input type="number" class="ecv2-stock-menu-input" value="' + qty + '" step="1" data-original="' + qty + '" />';
			menu_html += '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="ecv2_save_stock_qty( this );">Save</button>';
			menu_html += '</div></div>';
			menu_html += '<div class="ecv2-stock-menu-divider"></div>';
		}

		// Option tracking: manage variants link.
		if ( type === 'option' ) {
			if ( ecv2_lang.variant_tracking_enabled ) {
				menu_html += '<a href="#" class="ecv2-stock-menu-item" onclick="ecv2_open_variant_popup( ' + product_id + ' ); return false;">';
				menu_html += '<span class="dashicons dashicons-list-view"></span> ' + ecv2_esc_html( ecv2_lang.manage_variants );
				menu_html += '</a>';
			} else {
				menu_html += '<a href="#" class="ecv2-stock-menu-item ecv2-stock-menu-item-locked" onclick="jQuery( this ).closest( \'.ecv2-stock-menu\' ).removeClass( \'ecv2-stock-menu-open ecv2-menu-fixed\' ); return wpec_gate.locked_action( ecv2_lang.variant_gate );">';
				menu_html += '<span class="dashicons dashicons-list-view"></span> ' + ecv2_esc_html( ecv2_lang.manage_variants );
				menu_html += ' <span class="dashicons dashicons-lock ecv2-menu-lock-icon"></span>';
				menu_html += '</a>';
			}
			menu_html += '<div class="ecv2-stock-menu-divider"></div>';
		}

		// Tracking type switcher.
		menu_html += '<div class="ecv2-stock-menu-label ecv2-stock-menu-label-section">Change Tracking Type</div>';

		var types = [
			{ key: 'unlimited', name: 'Unlimited',               desc: 'Do not track stock',     pro: false },
			{ key: 'basic',     name: 'Basic Tracking',          desc: 'Track overall quantity', pro: false },
			{ key: 'option',    name: 'Option/Variant Tracking', desc: 'Track per variation',    pro: true  }
		];
		for ( var i = 0; i < types.length; i++ ) {
			var t = types[ i ];
			var is_current = ( t.key === type );
			var is_locked = ( t.pro && ! wpec_gate.is_enabled( ecv2_lang.variant_gate ) );
			var classes = 'ecv2-stock-menu-type';
			if ( is_current ) { classes += ' ecv2-stock-menu-type-active'; }
			if ( is_locked )  { classes += ' ecv2-stock-menu-type-locked'; }

			var onclick;
			if ( is_locked ) {
				onclick = "jQuery( this ).closest( '.ecv2-stock-menu' ).removeClass( 'ecv2-stock-menu-open ecv2-menu-fixed' ); return wpec_gate.locked_action( ecv2_lang.variant_gate );";
			} else {
				onclick = "ecv2_switch_tracking_type( this, '" + t.key + "' ); return false;";
			}
			menu_html += '<a href="#" class="' + classes + '" data-type="' + t.key + '" onclick="' + onclick + '">';
			menu_html += '<span class="ecv2-stock-menu-type-radio">' + ( is_current ? '<span class="ecv2-stock-menu-type-dot"></span>' : '' ) + '</span>';
			menu_html += '<span class="ecv2-stock-menu-type-info">';
			menu_html += '<span class="ecv2-stock-menu-type-name">' + ecv2_esc_html( t.name );
			if ( is_locked ) {
				menu_html += ' <span class="dashicons dashicons-lock ecv2-menu-lock-icon"></span>';
			}
			menu_html += '</span>';
			if ( is_locked && ! is_current ) {
				menu_html += '<span class="ecv2-stock-menu-type-desc">' + ecv2_esc_html( ( ecv2_lang.variant_gate && ecv2_lang.variant_gate.desc ) || 'Available in WP EasyCart PRO' ) + '</span>';
			} else {
				menu_html += '<span class="ecv2-stock-menu-type-desc">' + ecv2_esc_html( t.desc ) + '</span>';
			}
			menu_html += '</span></a>';
		}

		$wrap.find( '.ecv2-stock-menu' ).html( menu_html );
	}

	/* --- Category Editor Popover --- */

	var ecv2_cat_search_timer = null;
	var ecv2_cat_active_cell = null;

	window.ecv2_open_category_editor = function( trigger ) {
		var $cell = $( trigger ).closest( '.ecv2-category-cell' );
		var product_id = $cell.data( 'product-id' );
		var nonce = $cell.data( 'nonce' );
		var categories = $cell.data( 'categories' ) || [];

		// Close any other open popover.
		ecv2_close_category_editor();

		ecv2_cat_active_cell = $cell;

		// Build the popover HTML.
		var html = '<div class="ecv2-cat-popover" data-product-id="' + product_id + '" data-nonce="' + ecv2_esc_html( nonce ) + '">';

		// Search section.
		html += '<div class="ecv2-cat-popover-header">';
		html += '<div class="ecv2-cat-search-wrap">';
		html += '<span class="ecv2-cat-search-icon"><span class="dashicons dashicons-search"></span></span>';
		html += '<input type="text" class="ecv2-cat-search-input" placeholder="' + ecv2_esc_html( ecv2_lang.cat_search_placeholder ) + '" autocomplete="off" />';
		html += '<span class="ecv2-cat-search-spinner" style="display:none;"><span class="dashicons dashicons-update ecv2-spin"></span></span>';
		html += '</div>';
		html += '<div class="ecv2-cat-results"></div>';
		html += '</div>';

		// Divider.
		html += '<div class="ecv2-cat-divider"></div>';

		// Assigned categories section.
		html += '<div class="ecv2-cat-assigned">';
		html += '<div class="ecv2-cat-assigned-label">' + ecv2_esc_html( ecv2_lang.cat_assigned ) + '</div>';
		html += '<div class="ecv2-cat-assigned-list">';
		html += ecv2_render_assigned_categories( categories );
		html += '</div>';
		html += '</div>';

		html += '</div>';

		$cell.append( html );

		// If inside a table (overflow:hidden), use fixed positioning so the popover is not clipped.
		var $popover = $cell.find( '.ecv2-cat-popover' );
		if ( $cell.closest( '.ecv2-table' ).length ) {
			var $display = $cell.find( '.ecv2-category-display' );
			var rect = $display[0].getBoundingClientRect();
			$popover.addClass( 'ecv2-menu-fixed' );
			var pop_top = rect.bottom + 4;
			var pop_left = rect.left;
			$popover.css({ top: pop_top + 'px', left: pop_left + 'px', right: 'auto' });

			// Check if popover overflows viewport right edge.
			var pop_rect = $popover[0].getBoundingClientRect();
			if ( pop_rect.right > window.innerWidth - 8 ) {
				$popover.css({ left: 'auto', right: '8px' });
			}
			// Check if popover overflows viewport bottom edge.
			if ( pop_rect.bottom > window.innerHeight - 8 ) {
				var above_top = rect.top - pop_rect.height - 4;
				if ( above_top > 8 ) {
					$popover.css({ top: above_top + 'px' });
				}
			}
		}

		// Focus the search input.
		setTimeout( function() {
			$cell.find( '.ecv2-cat-search-input' ).focus();
		}, 50 );
	};

	function ecv2_render_assigned_categories( categories ) {
		categories = ecv2_filter_valid_categories( categories );
		if ( categories.length === 0 ) {
			return '<div class="ecv2-cat-empty">' + ecv2_esc_html( ecv2_lang.cat_none_assigned ) + '</div>';
		}
		var html = '';
		for ( var i = 0; i < categories.length; i++ ) {
			html += '<div class="ecv2-cat-assigned-item" data-cat-id="' + parseInt( categories[ i ].id, 10 ) + '">';
			html += '<span>' + ecv2_esc_html( categories[ i ].name ) + '</span>';
			html += '<button type="button" class="ecv2-cat-remove-btn" title="Remove" data-cat-id="' + parseInt( categories[ i ].id, 10 ) + '"><span class="dashicons dashicons-no-alt"></span></button>';
			html += '</div>';
		}
		return html;
	}

	function ecv2_update_category_display( $cell, categories ) {
		categories = ecv2_filter_valid_categories( categories );

		// Update the data attribute.
		$cell.data( 'categories', categories ).attr( 'data-categories', JSON.stringify( categories ) );

		// Rebuild the display tags.
		var $display = $cell.find( '.ecv2-category-display' );
		var tag_html = '';
		if ( categories.length > 0 ) {
			tag_html += '<span class="ecv2-category-tag">' + ecv2_esc_html( categories[0].name ) + '</span>';
			if ( categories.length > 1 ) {
				var all_names = [];
				for ( var i = 0; i < categories.length; i++ ) {
					all_names.push( categories[ i ].name );
				}
				tag_html += '<span class="ecv2-category-more" title="' + ecv2_esc_html( all_names.join( ', ' ) ) + '">+' + ( categories.length - 1 ) + '</span>';
			}
		} else {
			tag_html += '<span class="ecv2-sku-empty">\u2014</span>';
		}
		tag_html += '<span class="ecv2-category-edit-icon"><span class="dashicons dashicons-edit-large"></span></span>';
		$display.html( tag_html );
	}

	function ecv2_filter_valid_categories( categories ) {
		if ( ! categories || ! categories.length ) return [];
		var out = [];
		for ( var i = 0; i < categories.length; i++ ) {
			var c = categories[ i ];
			if ( c && parseInt( c.id, 10 ) > 0 && typeof c.name === 'string' && c.name.length > 0 ) {
				out.push( { id: parseInt( c.id, 10 ), name: c.name } );
			}
		}
		return out;
	}

	window.ecv2_close_category_editor = function() {
		$( '.ecv2-cat-popover' ).remove();
		ecv2_cat_active_cell = null;
	};

	// Search input handler with debounce.
	$( document ).on( 'input', '.ecv2-cat-search-input', function() {
		var $input = $( this );
		var $popover = $input.closest( '.ecv2-cat-popover' );
		var search_val = $input.val().trim();

		clearTimeout( ecv2_cat_search_timer );

		if ( search_val.length < 1 ) {
			$popover.find( '.ecv2-cat-results' ).empty();
			$popover.find( '.ecv2-cat-search-spinner' ).hide();
			return;
		}

		$popover.find( '.ecv2-cat-search-spinner' ).show();

		ecv2_cat_search_timer = setTimeout( function() {
			var product_id = $popover.data( 'product-id' );
			$.ajax({
				url: wpeasycart_admin_ajax_object.ajax_url,
				type: 'POST',
				data: {
					action: 'ecv2_category_search',
					search: search_val,
					product_id: product_id,
					wp_easycart_nonce: ecv2_nonces.category_search
				},
				success: function( response ) {
					$popover.find( '.ecv2-cat-search-spinner' ).hide();
					if ( response.success ) {
						var results = response.data.results;
						var html = '';
						if ( results.length === 0 ) {
							html = '<div class="ecv2-cat-hint">' + ecv2_esc_html( ecv2_lang.cat_no_results ) + '</div>';
						} else {
							for ( var i = 0; i < results.length; i++ ) {
								var r = results[ i ];
								if ( r.assigned ) {
									html += '<div class="ecv2-cat-result-item ecv2-cat-result-item-assigned">';
									html += '<span>' + ecv2_esc_html( r.name ) + '</span>';
									html += '<span class="ecv2-cat-result-check">' + ecv2_esc_html( ecv2_lang.cat_already_assigned ) + '</span>';
									html += '</div>';
								} else {
									html += '<div class="ecv2-cat-result-item" data-cat-id="' + r.id + '" data-cat-name="' + ecv2_esc_html( r.name ) + '">';
									html += '<span>' + ecv2_esc_html( r.name ) + '</span>';
									html += '<span class="ecv2-cat-result-add">+ Add</span>';
									html += '</div>';
								}
							}
						}
						$popover.find( '.ecv2-cat-results' ).html( html );
					}
				},
				error: function() {
					$popover.find( '.ecv2-cat-search-spinner' ).hide();
				}
			});
		}, 300 );
	});

	// Add category from search results.
	$( document ).on( 'click', '.ecv2-cat-result-item:not(.ecv2-cat-result-item-assigned)', function() {
		var $item = $( this );
		var $popover = $item.closest( '.ecv2-cat-popover' );
		var $cell = $popover.closest( '.ecv2-category-cell' );
		var product_id = $popover.data( 'product-id' );
		var nonce = $popover.data( 'nonce' );
		var cat_id = $item.data( 'cat-id' );

		// Immediately mark as assigned in search results.
		$item.addClass( 'ecv2-cat-result-item-assigned' ).css( 'pointer-events', 'none' );
		$item.find( '.ecv2-cat-result-add' ).replaceWith( '<span class="ecv2-cat-result-check"><span class="dashicons dashicons-update ecv2-spin" style="font-size:12px;width:12px;height:12px;"></span></span>' );

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_category_add',
				product_id: product_id,
				category_id: cat_id,
				wp_easycart_nonce: nonce
			},
			success: function( response ) {
				if ( response.success ) {
					var categories = response.data.categories;

					// Update cell data and display.
					ecv2_update_category_display( $cell, categories );

					// Update the assigned list in the popover.
					$popover.find( '.ecv2-cat-assigned-list' ).html( ecv2_render_assigned_categories( categories ) );

					// Mark completed in search results.
					$item.find( '.ecv2-cat-result-check' ).html( ecv2_esc_html( ecv2_lang.cat_already_assigned ) );

					ecv2_toast( ecv2_lang.cat_added, 'success' );
				} else {
					// Revert.
					$item.removeClass( 'ecv2-cat-result-item-assigned' ).css( 'pointer-events', '' );
					$item.find( '.ecv2-cat-result-check' ).replaceWith( '<span class="ecv2-cat-result-add">+ Add</span>' );
					ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
				}
			},
			error: function() {
				$item.removeClass( 'ecv2-cat-result-item-assigned' ).css( 'pointer-events', '' );
				$item.find( '.ecv2-cat-result-check' ).replaceWith( '<span class="ecv2-cat-result-add">+ Add</span>' );
				ecv2_toast( ecv2_lang.error, 'error' );
			}
		});
	});

	// Remove category.
	$( document ).on( 'click', '.ecv2-cat-remove-btn', function( e ) {
		e.stopPropagation();
		var $btn = $( this );
		var $popover = $btn.closest( '.ecv2-cat-popover' );
		var $cell = $popover.closest( '.ecv2-category-cell' );
		var product_id = $popover.data( 'product-id' );
		var nonce = $popover.data( 'nonce' );
		var cat_id = $btn.data( 'cat-id' );
		var $item = $btn.closest( '.ecv2-cat-assigned-item' );


		// Fade the item while saving.
		$item.css( 'opacity', 0.4 );

		$.ajax({
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			data: {
				action: 'ecv2_category_remove',
				product_id: product_id,
				category_id: cat_id,
				wp_easycart_nonce: nonce
			},
			success: function( response ) {
				if ( response.success ) {
					var categories = response.data.categories;

					// Update cell data and display.
					ecv2_update_category_display( $cell, categories );

					// Update the assigned list in the popover.
					$popover.find( '.ecv2-cat-assigned-list' ).html( ecv2_render_assigned_categories( categories ) );

					// If search results are showing, update assigned state.
					$popover.find( '.ecv2-cat-result-item-assigned' ).each( function() {
						var result_id = $( this ).data( 'cat-id' );
						if ( result_id == cat_id ) {
							$( this ).removeClass( 'ecv2-cat-result-item-assigned' ).css( 'pointer-events', '' );
							$( this ).find( '.ecv2-cat-result-check' ).replaceWith( '<span class="ecv2-cat-result-add">+ Add</span>' );
						}
					});

					ecv2_toast( ecv2_lang.cat_removed, 'success' );
				} else {
					$item.css( 'opacity', 1 );
					ecv2_toast( response.data.message || ecv2_lang.error, 'error' );
				}
			},
			error: function() {
				$item.css( 'opacity', 1 );
				ecv2_toast( ecv2_lang.error, 'error' );
			}
		});
	});

	// Close popover on outside click.
	$( document ).on( 'click', function( e ) {
		if ( ecv2_cat_active_cell && ! $( e.target ).closest( '.ecv2-cat-popover, .ecv2-category-display' ).length ) {
			ecv2_close_category_editor();
		}
	});

	// Close popover on Escape key.
	$( document ).on( 'keydown', function( e ) {
		if ( e.key === 'Escape' && ecv2_cat_active_cell ) {
			ecv2_close_category_editor();
		}
	});

	window.ecv2_imgmgr_data               = null;     // server-hydrated image data, null when modal is closed
	window.ecv2_imgmgr_current_set        = 'basic';  // currently selected option/modifier set id
	window.ecv2_imgmgr_url_type           = '';       // active URL panel: '', 'image', 'video', 'youtube', 'vimeo'
	window.ecv2_imgmgr_dirty              = false;    // unsaved changes flag, used by the close-confirm logic
	window.ecv2_imgmgr_current_image_type = 'basic';  // 'basic' | 'variant' | 'modifier' (PRO-extended)

	window.ecv2_imgmgr_change_set = function() {
		ecv2_imgmgr_current_set = $( '#ecv2-imgmgr-set-select' ).val();
		ecv2_imgmgr_close_url_panel();
		ecv2_imgmgr_render_gallery();
	};

	function ecv2_imgmgr_get_current_set() {
		if ( ! ecv2_imgmgr_data ) return null;
		for ( var i = 0; i < ecv2_imgmgr_data.sets.length; i++ ) {
			if ( String( ecv2_imgmgr_data.sets[i].id ) === String( ecv2_imgmgr_current_set ) ) {
				return ecv2_imgmgr_data.sets[i];
			}
		}
		return null;
	}

	function ecv2_imgmgr_render_gallery() {
		var set = ecv2_imgmgr_get_current_set();
		var $gallery = $( '#ecv2-imgmgr-gallery' ).empty();

		if ( ! set || set.images.length === 0 ) {
			$( '#ecv2-imgmgr-empty' ).show();
			$( '#ecv2-imgmgr-count' ).text( '0 ' + ecv2_lang.img_images );
			return;
		}

		$( '#ecv2-imgmgr-empty' ).hide();

		for ( var i = 0; i < set.images.length; i++ ) {
			var img = set.images[i];
			var $item = $( '<div class="ecv2-imgmgr-gallery-item" data-img-index="' + i + '"></div>' );
			var thumb = img.thumb || img.url;
			if ( thumb ) {
				$item.append( '<img src="' + $('<span>').text( thumb ).html() + '" alt="" loading="lazy" onerror="ecv2_image_error(this);" data-ecv2-placeholder-class="ecv2-imgmgr-thumb-missing" />' );
			} else {
				$item.append( '<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:var(--ecv2-g300);"><span class="dashicons dashicons-format-video" style="font-size:32px;width:32px;height:32px;"></span></div>' );
			}

			// Video badge.
			if ( img.type === 'video' || img.type === 'youtube' || img.type === 'vimeo' ) {
				var badge_label = img.type === 'youtube' ? 'YouTube' : ( img.type === 'vimeo' ? 'Vimeo' : 'Video' );
				$item.append( '<span class="ecv2-imgmgr-video-badge"><span class="dashicons dashicons-controls-play"></span> ' + badge_label + '</span>' );
			}

			$item.append( '<button type="button" class="ecv2-imgmgr-remove" data-img-index="' + i + '" title="' + ecv2_lang.img_remove + '"><span class="dashicons dashicons-no-alt"></span></button>' );
			$gallery.append( $item );
		}

		$( '#ecv2-imgmgr-count' ).text( set.images.length + ' ' + ecv2_lang.img_images );

		// Make sortable.
		$gallery.sortable( {
			placeholder: 'ecv2-imgmgr-gallery-item ui-sortable-placeholder',
			tolerance: 'pointer',
			cursor: 'grabbing',
			stop: function() {
				ecv2_imgmgr_sync_order_from_dom();
				ecv2_imgmgr_dirty = true;
			}
		} );
	}
	window.ecv2_imgmgr_render_gallery = ecv2_imgmgr_render_gallery;

	function ecv2_imgmgr_sync_order_from_dom() {
		var set = ecv2_imgmgr_get_current_set();
		if ( ! set ) return;

		var $items = $( '#ecv2-imgmgr-gallery .ecv2-imgmgr-gallery-item' );
		var new_images = [];
		$items.each( function() {
			var idx = parseInt( $( this ).attr( 'data-img-index' ), 10 );
			if ( set.images[ idx ] ) {
				new_images.push( set.images[ idx ] );
			}
		} );
		set.images = new_images;

		// Re-index.
		$items.each( function( i ) {
			$( this ).attr( 'data-img-index', i );
			$( this ).find( '.ecv2-imgmgr-remove' ).attr( 'data-img-index', i );
		} );
	}

	// Remove image.
	$( document ).on( 'click', '.ecv2-imgmgr-remove', function( e ) {
		e.stopPropagation();
		var idx = parseInt( $( this ).attr( 'data-img-index' ), 10 );
		var set = ecv2_imgmgr_get_current_set();
		if ( set ) {
			set.images.splice( idx, 1 );
			ecv2_imgmgr_dirty = true;
			ecv2_imgmgr_render_gallery();
		}
	} );

	// Add from media library.
	window.ecv2_imgmgr_add_media_library = function() {
		if ( ! ecv2_imgmgr_data || ! ecv2_imgmgr_data.is_licensed ) {
			if ( typeof show_pro_required === 'function' ) {
				show_pro_required();
			} else {
				ecv2_toast( ecv2_lang.img_pro_required, 'info' );
			}
			return;
		}

		var frame = wp.media( {
			title: ecv2_lang.img_select_images,
			button: { text: ecv2_lang.img_use_images },
			multiple: true
		} );

		frame.on( 'select', function() {
			var selection = frame.state().get( 'selection' );
			var set = ecv2_imgmgr_get_current_set();
			if ( ! set ) return;

			selection.each( function( attachment ) {
				var url = attachment.attributes.sizes && attachment.attributes.sizes.large
					? attachment.attributes.sizes.large.url
					: attachment.attributes.url;
				set.images.push( {
					id: String( attachment.id ),
					type: 'media',
					url: url,
					thumb: url
				} );
			} );

			ecv2_imgmgr_dirty = true;
			ecv2_imgmgr_render_gallery();
		} );

		frame.open();
	};

	// URL panel toggle.
	window.ecv2_imgmgr_toggle_url_panel = function( type ) {
		if ( ! ecv2_imgmgr_data || ! ecv2_imgmgr_data.is_licensed ) {
			if ( type !== 'image' ) {
				if ( typeof show_pro_required === 'function' ) {
					show_pro_required();
				} else {
					ecv2_toast( ecv2_lang.img_pro_required, 'info' );
				}
				return;
			}
		}

		ecv2_imgmgr_url_type = type;
		var labels = {
			image: ecv2_lang.img_enter_image_url,
			video: ecv2_lang.img_enter_video_url,
			youtube: ecv2_lang.img_enter_youtube_url,
			vimeo: ecv2_lang.img_enter_vimeo_url
		};
		$( '#ecv2-imgmgr-url-label' ).text( labels[ type ] || '' );
		$( '#ecv2-imgmgr-url-input' ).val( '' );
		$( '#ecv2-imgmgr-thumb-input' ).val( '' );

		if ( type === 'image' ) {
			$( '#ecv2-imgmgr-thumb-row' ).hide();
			$( '#ecv2-imgmgr-url-input' ).attr( 'placeholder', 'https://yoursite.com/image.jpg' );
		} else {
			$( '#ecv2-imgmgr-thumb-row' ).show();
			if ( type === 'youtube' ) {
				$( '#ecv2-imgmgr-url-input' ).attr( 'placeholder', 'https://www.youtube.com/embed/AAKH3jJRaDk' );
			} else if ( type === 'vimeo' ) {
				$( '#ecv2-imgmgr-url-input' ).attr( 'placeholder', 'https://player.vimeo.com/video/1568156516' );
			} else {
				$( '#ecv2-imgmgr-url-input' ).attr( 'placeholder', 'https://yoursite.com/video.mp4' );
			}
		}

		$( '#ecv2-imgmgr-url-panel' ).show();
		$( '#ecv2-imgmgr-url-input' ).focus();
	};

	window.ecv2_imgmgr_close_url_panel = function() {
		$( '#ecv2-imgmgr-url-panel' ).hide();
		ecv2_imgmgr_url_type = '';
	};

	// Thumbnail media library for video fields.
	window.ecv2_imgmgr_thumb_media_library = function() {
		var frame = wp.media( {
			title: ecv2_lang.img_select_thumbnail,
			button: { text: ecv2_lang.img_use_image },
			multiple: false
		} );

		frame.on( 'select', function() {
			var attachment = frame.state().get( 'selection' ).first();
			var url = attachment.attributes.sizes && attachment.attributes.sizes.large
				? attachment.attributes.sizes.large.url
				: attachment.attributes.url;
			$( '#ecv2-imgmgr-thumb-input' ).val( url );
		} );

		frame.open();
	};

	// Add URL.
	window.ecv2_imgmgr_add_url = function() {
		var url = $.trim( $( '#ecv2-imgmgr-url-input' ).val() );
		if ( ! url ) return;

		var set = ecv2_imgmgr_get_current_set();
		if ( ! set ) return;

		var thumb = $.trim( $( '#ecv2-imgmgr-thumb-input' ).val() );

		if ( ecv2_imgmgr_url_type === 'image' ) {
			set.images.push( {
				id: 'image:' + url,
				type: 'image_url',
				url: url,
				thumb: url
			} );
		} else if ( ecv2_imgmgr_url_type === 'video' ) {
			set.images.push( {
				id: 'video:' + url + ':::' + thumb,
				type: 'video',
				url: url,
				thumb: thumb
			} );
		} else if ( ecv2_imgmgr_url_type === 'youtube' ) {
			set.images.push( {
				id: 'youtube:' + url + ':::' + thumb,
				type: 'youtube',
				url: url,
				thumb: thumb
			} );
		} else if ( ecv2_imgmgr_url_type === 'vimeo' ) {
			set.images.push( {
				id: 'vimeo:' + url + ':::' + thumb,
				type: 'vimeo',
				url: url,
				thumb: thumb
			} );
		}

		ecv2_imgmgr_dirty = true;
		ecv2_imgmgr_close_url_panel();
		ecv2_imgmgr_render_gallery();
	};

	// Save images.
	window.ecv2_imgmgr_save = function() {
		if ( ! ecv2_imgmgr_data ) return;

		var product_id = $( '#ecv2-imgmgr-product-id' ).val();
		var nonce = $( '#ecv2-imgmgr-nonce' ).val();

		// Build sets payload.
		var sets = [];
		for ( var i = 0; i < ecv2_imgmgr_data.sets.length; i++ ) {
			var s = ecv2_imgmgr_data.sets[i];
			var ids = [];
			for ( var j = 0; j < s.images.length; j++ ) {
				ids.push( s.images[j].id );
			}
			sets.push( { id: String( s.id ), image_ids: ids } );
		}

		// Determine use_optionitem_images and use_advanced_optionset from current type.
		var use_optionitem_images = ( ecv2_imgmgr_current_image_type === 'variant' || ecv2_imgmgr_current_image_type === 'modifier' ) ? 1 : 0;
		var use_advanced_optionset = ( ecv2_imgmgr_current_image_type === 'modifier' ) ? 1 : 0;

		$( '#ecv2-imgmgr-save-btn' ).prop( 'disabled', true ).text( ecv2_lang.processing );

		$.post( ajaxurl, {
			action: 'ecv2_save_product_images',
			product_id: product_id,
			wp_easycart_nonce: nonce,
			sets: JSON.stringify( sets ),
			use_optionitem_images: use_optionitem_images,
			use_advanced_optionset: use_advanced_optionset
		}, function( response ) {
			$( '#ecv2-imgmgr-save-btn' ).prop( 'disabled', false ).text( ecv2_lang.img_save_images );
			if ( response.success ) {
				ecv2_toast( ecv2_lang.img_saved );
				ecv2_imgmgr_dirty = false;

				// Update the product thumbnail in the list.
				var img_url = response.data.image_url;
				var $wraps = $( '.ecv2-image-wrap[data-product-id="' + product_id + '"], .ecv2-card-image[data-product-id="' + product_id + '"]' );
				$wraps.each( function() {
					var $w = $( this );
					if ( img_url ) {
						if ( $w.hasClass( 'ecv2-card-image' ) ) {
							var $click = $w.find( '.ecv2-card-image-click' );
							$click.find( '.ecv2-card-image-placeholder' ).remove();
							var $img = $click.find( 'img' );
							if ( $img.length ) {
								$img.attr( 'src', img_url );
							} else {
								$click.prepend( '<img src="' + img_url + '" alt="" loading="lazy" onerror="ecv2_image_error(this);" data-ecv2-placeholder-class="ecv2-card-image-placeholder" />' );
							}
						} else {
							var $thumb = $w.find( '.ecv2-product-thumb' );
							if ( $thumb.length ) {
								$thumb.attr( 'src', img_url );
							} else {
								$w.find( '.ecv2-product-thumb-placeholder' ).replaceWith( '<img src="' + img_url + '" alt="" class="ecv2-product-thumb" loading="lazy" onerror="ecv2_image_error(this);" />' );
							}
						}
					} else {
						if ( $w.hasClass( 'ecv2-card-image' ) ) {
							var $click = $w.find( '.ecv2-card-image-click' );
							$click.find( 'img' ).remove();
							if ( ! $click.find( '.ecv2-card-image-placeholder' ).length ) {
								$click.prepend( '<div class="ecv2-card-image-placeholder"><span class="dashicons dashicons-format-image"></span></div>' );
							}
						} else {
							$w.find( '.ecv2-product-thumb' ).replaceWith( '<div class="ecv2-product-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>' );
						}
					}
				} );

				ecv2_close_image_manager();

				if ( $( '#ecv2-variant-popup' ).is( ':visible' ) && ecv2_variant_state.product_id > 0 ) {
					ecv2_load_variants();
				}
			} else {
				ecv2_toast( response.data && response.data.message ? response.data.message : ecv2_lang.error, 'error' );
			}
		} );
	};

	// Close on backdrop click.
	$( document ).on( 'click', '#ecv2-image-manager-modal', function( e ) {
		if ( $( e.target ).is( '#ecv2-image-manager-modal' ) ) {
			ecv2_close_image_manager();
		}
	} );

	// Close on ESC.
	$( document ).on( 'keydown', function( e ) {
		if ( e.keyCode === 27 && $( '#ecv2-image-manager-modal' ).is( ':visible' ) ) {
			ecv2_close_image_manager();
		}
	} );

	window.ecv2_variant_locked_action = window.ecv2_variant_locked_action || function( state, url ) {
		var gate = { state: state || 'upsell', url: url || '' };
		if ( window.wpec_gate && typeof window.wpec_gate.locked_action === 'function' ) {
			return window.wpec_gate.locked_action( gate );
		}
		if ( typeof show_pro_required === 'function' ) { show_pro_required(); }
		return false;
	};

})( jQuery );
window.ecv2_open_variant_popup            = window.ecv2_open_variant_popup            || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_image_manager_for_variant = window.ecv2_open_image_manager_for_variant || function(){};
window.ecv2_open_volume_pricing           = window.ecv2_open_volume_pricing           || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_b2b_pricing              = window.ecv2_open_b2b_pricing              || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_advanced_pricing         = window.ecv2_open_advanced_pricing         || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_variant_from_badge       = window.ecv2_open_variant_from_badge       || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_volume_from_badge        = window.ecv2_open_volume_from_badge        || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_b2b_from_badge           = window.ecv2_open_b2b_from_badge           || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_advanced_from_badge      = window.ecv2_open_advanced_from_badge      || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_open_image_manager            = window.ecv2_open_image_manager            || function(){ if (typeof show_pro_required === 'function') show_pro_required(); };
window.ecv2_close_image_manager           = window.ecv2_close_image_manager           || function(){};
window.ecv2_load_variants                 = window.ecv2_load_variants                 || function(){};
