/**
 * WP EasyCart Admin — user role editor: the Role prices card. @since 6.0.0
 *
 * One flow: the search at the top adds products ( ecv2_role_price_search → ecv2_role_price_set ), each row edits its
 * role price inline, the toolbar applies a % off to every listed price or to the checked rows
 * ( ecv2_role_price_percent scope=existing|selected ), "Every active product" is the separate bulk action
 * ( scope=all, confirmed with the product count ), rows are removed one at a time or as a selection
 * ( ecv2_role_price_remove ), and the list is paged / filtered server-side ( ecv2_role_price_list, 50 per page ).
 *
 * Loads after settings-lists-v2.js, which owns the rest of the role editor ( users, delete, undo ) and defines
 * window.ecrole; this file extends it. The card's elements are ecrl_rp_* so the earlier role-price bindings in that
 * file ( #ecrl_search, .ecrl-price-input, #ecrl_price_rows ) find nothing and stay inert.
 */
jQuery( function( $ ) {
	'use strict';
	var $card = $( '#rlv2-prices' ), $lite = $( '#eclite[data-kind="role"]' );
	if ( ! $card.length || ! $lite.length || ! $( '#ecrl_rp_data' ).length ) { return; }

	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var D = $( '#eclite_data' ).length ? JSON.parse( $( '#eclite_data' ).text() ) : {};
	var R = JSON.parse( $( '#ecrl_rp_data' ).text() ), I = R.i18n || {};
	var nonce = D.nonce || ( window.ecv2_catalog_vars && ecv2_catalog_vars.nonces && ecv2_catalog_vars.nonces.rolev2 );
	var S = { items: R.items || [], total: R.total || 0, all_total: R.total || 0, page: 1, pages: Math.max( 1, Math.ceil( ( R.total || 0 ) / ( R.per || 50 ) ) ), per: R.per || 50, q: '', active: R.active_products || 0 };

	/* esc() for HTML text, attr() for attribute values ( .text().html() leaves quotes alone ), fmt() raw for .text()/toasts, fmth() escaped for markup. */
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function attr( s ) { return esc( s ).replace( /"/g, '&quot;' ); }
	function t( key, fallback ) { return I[ key ] || fallback; }
	function sub( s, a, e ) { var i = 0; return String( s ).replace( /%(\d+\$)?[sd]/g, function() { var v = a[ i++ ]; return e ? esc( v ) : String( v == null ? '' : v ); } ); }
	function fmt( s ) { return sub( s, Array.prototype.slice.call( arguments, 1 ), false ); }
	function fmth( s ) { return sub( s, Array.prototype.slice.call( arguments, 1 ), true ); }
	function num( n ) { n = Number( n ) || 0; return n.toLocaleString ? n.toLocaleString() : String( n ); }
	function money( v ) { return ( Number( v ) || 0 ).toFixed( 2 ); }
	function toast( m, type ) { if ( window.ecv2_toast ) { ecv2_toast( m, type ); } }
	function post( action, data, ok, fail ) {
		$.post( AJAX_URL, $.extend( { action: action, nonce: nonce, role_id: D.id }, data || {} ), function( r ) {
			if ( ! r || ! r.success ) { var m = ( r && r.data && r.data.message ) || 'Something went wrong.'; if ( fail ) { fail( m ); } else { toast( m, 'error' ); } return; }
			ok && ok( r.data );
		}, 'json' ).fail( function() { if ( fail ) { fail( 'Something went wrong.' ); } else { toast( 'Something went wrong.', 'error' ); } } );
	}

	/* ---- rendering ---- */
	function pct_html( p ) {
		if ( p.pct === null || p.pct === undefined ) { return '<span class="ecv2-sub">—</span>'; }
		if ( p.pct > 0 ) { return '<span class="ecv2-chip ecv2-chip-red">' + fmth( t( 'higher', '%s higher' ), '+' + p.pct + '%' ) + '</span>'; }
		if ( p.pct === 0 ) { return '<span class="ecv2-chip ecv2-chip-gray">' + esc( t( 'same', 'Same as regular' ) ) + '</span>'; }
		return '<span class="ecv2-chip ecv2-chip-green">' + fmth( t( 'off', '%s off' ), Math.abs( p.pct ) + '%' ) + '</span>';
	}
	function row_html( p ) {
		var inactive = p.active === false && ! p.orphan;
		return '<div class="ecrl-rp-row' + ( p.orphan ? ' is-orphan' : '' ) + ( inactive ? ' is-inactive' : '' ) + '" data-id="' + p.id + '" data-product="' + p.product_id + '">' +
			'<span><input type="checkbox" class="ecrl-rp-check" value="' + p.id + '"></span>' +
			'<span class="ecrl-rp-p"><span class="ecv2-thumb ecv2-thumb-xs">' + ( p.image ? '<img src="' + attr( p.image ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</span><span class="ecrl-rp-p-text"><b>' + esc( p.title ) + '</b><span class="ecv2-sub">' + esc( p.sku ) + ( inactive ? ' · ' + esc( t( 'inactive', 'Inactive' ) ) : '' ) + '</span></span></span>' +
			'<span class="ecrl-rp-reg">' + ( p.orphan ? '<span class="ecv2-sub">—</span>' : money( p.regular ) ) + '</span>' +
			'<span><input type="number" step="0.01" min="0" class="ecv2-input ecrl-rp-input" value="' + money( p.role_price ) + '"' + ( p.orphan ? ' disabled' : '' ) + ' aria-label="' + attr( R.label ) + '"></span>' +
			'<span class="ecrl-rp-pct">' + pct_html( p ) + '</span>' +
			'<span class="ecrl-rp-rm"><a href="#" class="ecrl-rp-remove" title="' + attr( t( 'remove_title', 'Remove this role price' ) ) + '">' + esc( t( 'remove', 'Remove' ) ) + '</a></span>' +
		'</div>';
	}
	function render() {
		var $rows = $( '#ecrl_rp_rows' ).empty(), empty = S.all_total === 0;
		if ( empty && S.q ) { S.q = ''; $( '#ecrl_rp_q' ).val( '' ); }
		$( '#ecrl_rp_empty' ).toggle( empty ); $( '#ecrl_rp_list, #ecrl_rp_toolbar' ).toggle( ! empty );
		if ( ! empty ) {
			if ( ! S.items.length ) { $rows.append( '<div class="ecos-empty ecrl-rp-nomatch">' + fmth( t( 'no_match', 'No role prices match “%s”.' ), S.q ) + '</div>' ); }
			$.each( S.items, function( i, p ) { $rows.append( row_html( p ) ); } );
			var first = S.total ? ( S.page - 1 ) * S.per + 1 : 0, last = Math.min( S.page * S.per, S.total );
			$( '#ecrl_rp_foot' ).text( S.total ? fmt( t( 'showing', 'Showing %1$s–%2$s of %3$s' ), num( first ), num( last ), num( S.total ) ) + ( S.q ? ' ' + fmt( t( 'matching', 'matching “%s”' ), S.q ) : '' ) : '' );
			$( '#ecrl_rp_pager' ).toggle( S.total > S.per || S.pages > 1 || !! S.q );
			$( '#ecrl_rp_prev' ).prop( 'disabled', S.page <= 1 ); $( '#ecrl_rp_next' ).prop( 'disabled', S.page >= S.pages );
		}
		$( '#ecrl_rp_all' ).prop( 'checked', false );
		sel_changed(); counts();
	}
	function counts() {
		var n = S.all_total;
		$( '.ecdv2-rail a[href="#rlv2-prices"] .ecv2-chip' ).text( n );
		$( '.ecrl-rp-h-count' ).text( fmt( t( n === 1 ? 'count_one' : 'count_many', n === 1 ? '%s role price' : '%s role prices' ), num( n ) ) );
	}
	function sel_ids() { return $( '.ecrl-rp-check:checked' ).map( function() { return parseInt( this.value, 10 ); } ).get(); }
	function sel_changed() {
		var ids = sel_ids();
		$( '#ecrl_rp_sel' ).toggle( ids.length > 0 ); $( '#ecrl_rp_sel_count' ).text( fmt( t( 'selected', '%s selected' ), num( ids.length ) ) );
		$( '.ecrl-rp-actions' ).toggle( ids.length === 0 );
		$( '.ecrl-rp-row' ).each( function() { $( this ).toggleClass( 'is-selected', $( this ).find( '.ecrl-rp-check' ).is( ':checked' ) ); } );
	}
	function load( page, q ) {
		if ( page !== undefined ) { S.page = page; } if ( q !== undefined ) { S.q = q; }
		$( '#ecrl_rp_list' ).addClass( 'is-loading' );
		post( 'ecv2_role_price_list', { page: S.page, q: S.q }, function( d ) {
			$( '#ecrl_rp_list' ).removeClass( 'is-loading' );
			S.items = d.items; S.total = d.total; S.all_total = d.all_total; S.page = d.page; S.pages = d.pages; S.per = d.per; S.active = d.active_products;
			render();
		}, function( m ) { $( '#ecrl_rp_list' ).removeClass( 'is-loading' ); toast( m, 'error' ); } );
	}

	/* ---- add a product ( search ) ---- */
	var st;
	$( '#ecrl_rp_add' ).on( 'input', function() {
		clearTimeout( st ); var q = $.trim( this.value );
		if ( q.length < 2 ) { $( '#ecrl_rp_add_results' ).hide(); return; }
		st = setTimeout( function() {
			post( 'ecv2_role_price_search', { q: q }, function( d ) {
				var $res = $( '#ecrl_rp_add_results' ).empty().show();
				$.each( d.items, function( i, p ) {
					$res.append( '<div class="ecrl-result ecrl-rp-result" data-id="' + p.id + '" data-title="' + attr( p.title ) + '"><span class="ecv2-thumb ecv2-thumb-xs">' + ( p.image ? '<img src="' + attr( p.image ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</span><span class="ecrl-result-main"><b>' + esc( p.title ) + '</b><span class="ecv2-sub">' + esc( p.sku ) + ' · ' + esc( t( 'regular_short', 'regular' ) ) + ' ' + esc( p.price_display ) + ( p.existing !== null ? ' · ' + esc( t( 'role_price_short', 'role price' ) ) + ' ' + money( p.existing ) : '' ) + '</span></span><span class="ecrl-result-set ecrl-rp-set"><input type="number" step="0.01" min="0" class="ecv2-input" placeholder="' + money( p.price ) + '" value="' + ( p.existing !== null ? money( p.existing ) : '' ) + '" aria-label="' + attr( R.label ) + '"><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary">' + esc( p.existing !== null ? t( 'update', 'Update' ) : t( 'set', 'Set' ) ) + '</button></span></div>' );
				} );
				if ( ! d.items.length ) { $res.append( '<div class="ecos-hint" style="padding:10px">' + esc( t( 'no_products', 'No matching products.' ) ) + '</div>' ); }
			} );
		}, 250 );
	} );
	$card.on( 'click', '.ecrl-rp-set button', function() {
		var $row = $( this ).closest( '.ecrl-rp-result' ), $in = $row.find( 'input' ), v = $.trim( $in.val() );
		if ( v === '' ) { $in.addClass( 'is-invalid' ).trigger( 'focus' ); return; }
		$( this ).prop( 'disabled', true );
		set_price( $row.data( 'id' ), v, $row.data( 'title' ) );
	} );
	$card.on( 'keydown', '.ecrl-rp-set input', function( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); $( this ).siblings( 'button' ).trigger( 'click' ); } } );
	$( document ).on( 'mousedown', function( e ) { if ( ! $( e.target ).closest( '.ecrl-rp-add' ).length ) { $( '#ecrl_rp_add_results' ).hide(); } } );

	function set_price( product_id, price, title ) {
		post( 'ecv2_role_price_set', { product_id: product_id, price: price }, function( d ) {
			if ( ! d.row ) { load( S.page ); return; }
			var found = false;
			$.each( S.items, function( i, p ) { if ( p.product_id === d.row.product_id ) { S.items[ i ] = d.row; found = true; } } );
			S.all_total = d.total; if ( ! S.q ) { S.total = d.total; }
			if ( found ) {
				var $old = $( '.ecrl-rp-row[data-product="' + d.row.product_id + '"]' ), checked = $old.find( '.ecrl-rp-check' ).is( ':checked' );
				$old.replaceWith( row_html( d.row ) ); if ( checked ) { $( '.ecrl-rp-row[data-product="' + d.row.product_id + '"] .ecrl-rp-check' ).prop( 'checked', true ); }
				sel_changed(); counts();
			} else {
				/* A product added from the search: show it at the top of the current page; paging re-sorts by title. */
				S.items.unshift( d.row ); render();
				$( '.ecrl-rp-row[data-id="' + d.row.id + '"]' ).addClass( 'is-new' );
			}
			$( '#ecrl_rp_add_results' ).hide(); $( '#ecrl_rp_add' ).val( '' );
			toast( d.created ? fmt( t( 'added', 'Added a role price for %s.' ), title || d.row.title ) : t( 'saved', 'Role price saved.' ), 'success' );
		}, function( m ) { $( '.ecrl-rp-set button' ).prop( 'disabled', false ); toast( m, 'error' ); } );
	}

	/* ---- inline edit / remove / selection ---- */
	$card.on( 'change', '.ecrl-rp-input', function() {
		var $row = $( this ).closest( '.ecrl-rp-row' ), v = $.trim( this.value );
		if ( v === '' || Number( v ) < 0 ) { $( this ).addClass( 'is-invalid' ); toast( t( 'enter_price', 'Enter a price of 0 or more.' ), 'error' ); return; }
		$( this ).removeClass( 'is-invalid' ); set_price( $row.data( 'product' ), v );
	} );
	$card.on( 'keydown', '.ecrl-rp-input', function( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); $( this ).trigger( 'blur' ); } } );
	$card.on( 'click', '.ecrl-rp-remove', function( e ) { e.preventDefault(); remove_ids( [ $( this ).closest( '.ecrl-rp-row' ).data( 'id' ) ], false ); } );
	$card.on( 'change', '.ecrl-rp-check', sel_changed );
	$card.on( 'change', '#ecrl_rp_all', function() { $( '.ecrl-rp-check' ).prop( 'checked', this.checked ); sel_changed(); } );

	function remove_ids( ids, bulk ) {
		if ( ! ids.length ) { return; }
		var data = bulk ? { roleprice_ids: ids } : { roleprice_id: ids[0] };
		$.each( ids, function( i, id ) { $( '.ecrl-rp-row[data-id="' + id + '"]' ).addClass( 'is-removing' ); } );
		post( 'ecv2_role_price_remove', data, function( d ) {
			S.items = $.grep( S.items, function( p ) { return $.inArray( p.id, ids ) === -1; } );
			S.all_total = d.total; S.total = S.q ? Math.max( 0, S.total - ( d.removed || ids.length ) ) : d.total;
			if ( ! S.items.length && S.total > 0 ) { load( Math.max( 1, Math.min( S.page, Math.ceil( S.total / S.per ) ) ) ); return; }
			S.pages = Math.max( 1, Math.ceil( S.total / S.per ) ); render();
			if ( bulk ) { toast( d.message, 'success' ); }
		}, function( m ) { $( '.ecrl-rp-row.is-removing' ).removeClass( 'is-removing' ); toast( m, 'error' ); } );
	}
	function remove_selected() {
		var ids = sel_ids(); if ( ! ids.length ) { return; }
		var msg = fmt( t( 'remove_sel_msg', 'Remove %s role prices?' ), num( ids.length ) ), go = function() { remove_ids( ids, true ); };
		if ( window.ecv2_show_confirm ) { ecv2_show_confirm( t( 'remove_sel_title', 'Remove selected role prices?' ), msg ).then( function( ok ) { if ( ok ) { go(); } } ); }
		else if ( window.confirm( msg ) ) { go(); }
	}

	/* ---- % off: listed / selected ( toolbar ) ---- */
	function percent_off( anchor, prefer_selected ) {
		if ( ! window.ecv2_catalog_modal ) { return; }
		var ids = sel_ids(), scope_opts = '';
		if ( ids.length ) { scope_opts += '<option value="selected"' + ( prefer_selected ? ' selected' : '' ) + '>' + fmth( t( 'scope_selected', '%s selected products' ), num( ids.length ) ) + '</option>'; }
		scope_opts += '<option value="existing">' + fmth( t( 'scope_existing', 'All %s products with a role price' ), num( S.all_total ) ) + '</option>';
		ecv2_catalog_modal( 'ecv2-pct', t( 'pct_title', 'Apply a percentage off' ),
			'<div class="ecdv2-grid"><div class="ecdv2-field"><label class="ecdv2-label" for="ecv2_pct_v">' + esc( t( 'pct_label', 'Percent off the regular price' ) ) + '</label><div class="ecrl-rp-pct-in"><input type="number" min="1" max="99" step="0.5" class="ecv2-input" id="ecv2_pct_v" value="20"><span>%</span></div></div>' +
			'<div class="ecdv2-field"><label class="ecdv2-label" for="ecv2_pct_scope">' + esc( t( 'apply_to', 'Apply to' ) ) + '</label><select class="ecv2-select" id="ecv2_pct_scope">' + scope_opts + '</select></div></div>' +
			'<div class="ecos-note info" style="margin-top:10px"><span class="dashicons dashicons-info-outline"></span><div>' + esc( t( 'pct_note', 'Role prices are recalculated from each product’s current regular price. Existing values are overwritten.' ) ) + '</div></div>',
			'<button type="button" class="ecv2-btn" data-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_pct_go">' + esc( t( 'apply', 'Apply' ) ) + '</button>',
			{ size: 'sm', anchor: anchor || null } );
		$( '#ecv2_pct_go' ).on( 'click', function() {
			var $b = $( this ).prop( 'disabled', true ), scope = $( '#ecv2_pct_scope' ).val(), data = { percent: $( '#ecv2_pct_v' ).val(), scope: scope };
			if ( scope === 'selected' ) { data.roleprice_ids = ids; }
			post( 'ecv2_role_price_percent', data, function( d ) { ecv2_catalog_close_modal(); toast( d.message, 'success' ); load( S.page ); }, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
		} );
		$( '#ecv2_pct_v' ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); $( '#ecv2_pct_go' ).trigger( 'click' ); } } );
	}

	/* ---- % off: every active product ( separate bulk action, centered dialog, confirmed with the count ) ---- */
	function percent_all() {
		if ( ! window.ecv2_catalog_modal ) { return; }
		if ( ! S.active ) { toast( t( 'no_active', 'There are no active products to price.' ), 'info' ); return; }
		var n = num( S.active );
		ecv2_catalog_modal( 'ecv2-pct-all', t( 'all_title', 'Apply % off to every active product' ),
			'<div class="ecdv2-field"><label class="ecdv2-label" for="ecv2_pcta_v">' + esc( t( 'pct_label', 'Percent off the regular price' ) ) + '</label><div class="ecrl-rp-pct-in"><input type="number" min="1" max="99" step="0.5" class="ecv2-input" id="ecv2_pcta_v" value="20"><span>%</span></div></div>' +
			'<div class="ecos-note" style="margin-top:12px"><span class="dashicons dashicons-warning"></span><div>' + fmth( t( 'all_note', 'Bulk action: this creates a role price for every one of the %s active products and overwrites any they already have.' ), n ) + '</div></div>' +
			'<label class="ecrl-rp-confirm"><input type="checkbox" id="ecv2_pcta_ok"> <span>' + fmth( t( 'all_check', 'I understand this affects all %s active products' ), n ) + '</span></label>',
			'<button type="button" class="ecv2-btn" data-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_pcta_go" disabled>' + fmth( t( 'all_go', 'Apply to %s products' ), n ) + '</button>',
			{ size: 'sm', anchor: null } );
		$( '#ecv2_pcta_ok' ).on( 'change', function() { $( '#ecv2_pcta_go' ).prop( 'disabled', ! this.checked ); } );
		$( '#ecv2_pcta_go' ).on( 'click', function() {
			if ( ! $( '#ecv2_pcta_ok' ).is( ':checked' ) ) { return; }
			var $b = $( this ).prop( 'disabled', true );
			post( 'ecv2_role_price_percent', { percent: $( '#ecv2_pcta_v' ).val(), scope: 'all' }, function( d ) { ecv2_catalog_close_modal(); toast( d.message, 'success' ); $( '#ecrl_rp_q' ).val( '' ); load( 1, '' ); }, function( m ) { $b.prop( 'disabled', false ); toast( m, 'error' ); } );
		} );
	}

	/* ---- toolbar / pager / empty-state buttons ---- */
	$card.on( 'click', '[data-rp]', function() {
		var what = $( this ).data( 'rp' );
		if ( 'pct' === what ) { percent_off( this, false ); }
		else if ( 'pct-selected' === what ) { percent_off( this, true ); }
		else if ( 'pct-all' === what ) { percent_all(); }
		else if ( 'remove-selected' === what ) { remove_selected(); }
		else if ( 'prev' === what ) { if ( S.page > 1 ) { load( S.page - 1 ); } }
		else if ( 'next' === what ) { if ( S.page < S.pages ) { load( S.page + 1 ); } }
		else if ( 'focus-add' === what ) { $( '#ecrl_rp_add' ).trigger( 'focus' ); }
	} );
	var qt; $( '#ecrl_rp_q' ).on( 'input', function() { clearTimeout( qt ); var q = $.trim( this.value ); qt = setTimeout( function() { if ( q !== S.q ) { load( 1, q ); } }, 250 ); } );

	/* Public surface: keeps the ecrole.* names other code may call, pointed at the new card. */
	window.ecrole = $.extend( window.ecrole || {}, {
		set_price: function( product_id, price ) { set_price( product_id, price ); },
		remove_price: function( id ) { remove_ids( [ id ], false ); },
		percent_off: function( anchor ) { percent_off( anchor || null, false ); },
		percent_all: function() { percent_all(); },
		prices_load: load
	} );

	render();
} );
