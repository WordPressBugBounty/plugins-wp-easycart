/** Settings lists V2: price points ( ecpp ), per page ( ecperpage ), log viewer ( eclog ). Depends on catalog-v2.js for ecv2_toast. */
jQuery( function( $ ) {
	'use strict';
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function ea( s ) { return esc( s ).replace( /"/g, '&quot;' ); }
	/* 6.0.5 ( admin review M17 ): every word goes through T() / TN(): the store's translation from wp_easycart_admin_js_text
	   ( window.ecv2_t / ecv2_tn, plural forms by the admin language ), English without it. Placeholders %s, %d, %1$s. */
	var T = window.ecv2_t || function( text ) { var a = arguments, i = 1; return String( text ).replace( /%(?:(\d+)\$)?([sd%])/g, function( m, n, k ) { return '%' === k ? '%' : String( n ? a[ +n ] : a[ i++ ] ); } ); };
	var TN = window.ecv2_tn || function( one, many, n ) { return T.apply( null, [ 1 === Number( n ) ? one : many ].concat( Array.prototype.slice.call( arguments, 2 ) ) ); };
	/* fail( message ) handles a { success:false } reply; the optional xhr_fail() handles a transport error ( timeout, 500, bad JSON ). */
	function post( action, nonce, data, ok, fail, xhr_fail ) {
		$.post( AJAX_URL, $.extend( { action: action, nonce: nonce }, data || {} ), function( r ) {
			if ( ! r || ! r.success ) { var m = ( r && r.data && r.data.message ) || T( 'Something went wrong.' ); if ( fail ) { fail( m ); } else { ecv2_toast( m, 'error' ); } return; }
			ok && ok( r.data );
		}, 'json' ).fail( function() { if ( xhr_fail ) { xhr_fail(); } else { ecv2_toast( T( 'Something went wrong.' ), 'error' ); } } );
	}

	/* ================================================================== */
	/* Price points                                                        */
	/* ================================================================== */
	var $pp = $( '#ecpp' );
	if ( $pp.length ) {
		var D = JSON.parse( $( '#ecpp_data' ).text() ), rows = D.rows, analysis = D.analysis, nonce = $pp.data( 'nonce' ), dirty = false, t = null, drag = null;
		var TYPES = { under: T( 'Under' ), between: T( 'Between' ), over: T( 'Over' ) };
		function render() {
			var $r = $( '#ecpp_rows' ).empty();
			$.each( rows, function( i, r ) {
				var a = analysis.rows[ i ] || { count: '…', label: '', warnings: [] };
				var $row = $( '<div class="ecpp-row' + ( a.count === 0 ? ' is-empty' : '' ) + ( r.id && r.id === $pp.data( 'highlight' ) ? ' is-highlight' : '' ) + '" draggable="true" data-i="' + i + '"></div>' );
				$row.append( '<span class="ecpp-grip dashicons dashicons-menu" title="' + ea( T( 'Drag to reorder' ) ) + '"></span>' );
				var sel = '<select class="ecv2-select ecpp-type" data-i="' + i + '">'; $.each( TYPES, function( k, l ) { sel += '<option value="' + k + '"' + ( k === r.type ? ' selected' : '' ) + '>' + l + '</option>'; } ); sel += '</select>';
				$row.append( sel );
				var range = '<span class="ecpp-range">';
				if ( r.type !== 'under' ) { range += '<input type="number" step="0.01" min="0" class="ecv2-input ecpp-low" data-i="' + i + '" value="' + esc( r.low ) + '">'; }
				if ( r.type === 'between' ) { range += '<span class="ecpp-dash">–</span>'; }
				if ( r.type !== 'over' ) { range += '<input type="number" step="0.01" min="0" class="ecv2-input ecpp-high" data-i="' + i + '" value="' + esc( r.high ) + '">'; }
				range += '</span>';
				$row.append( range );
				$row.append( '<span class="ecpp-label">' + esc( a.label ) + '</span>' );
				$row.append( '<span class="ecpp-count">' + ( a.count === 0 ? '<span class="ecv2-chip ecv2-chip-amber">0</span>' : esc( a.count ) ) + '<span class="ecpp-count-l">' + esc( TN( 'product', 'products', a.count ) ) + '</span></span>' ); /* the label only shows in the stacked ( narrow ) layout, where the column header is hidden */
				$row.append( '<button type="button" class="ecpp-x" title="' + ea( T( 'Remove bucket' ) ) + '" onclick="ecpp.remove( ' + i + ' );">&times;</button>' );
				$r.append( $row );
				$.each( a.warnings || [], function( k, w ) { $r.append( '<div class="ecpp-warn ecpp-warn-row">' + esc( w ) + '</div>' ); } );
				$.each( analysis.gaps || [], function( k, g ) { if ( g.after === i ) { $r.append( '<div class="ecpp-warn ecpp-warn-' + g.kind + '">' + esc( g.message ) + ( g.kind === 'gap' ? ' <a href="#" onclick="ecpp.close_gap( ' + i + ' ); return false;">' + esc( T( 'Extend the next bucket to start at %s', fmt( g.from ) ) ) + '</a>' : '' ) + '</div>' ); } } );
			} );
			if ( ! rows.length ) { $r.append( '<div class="ecos-hint" style="padding:14px 12px">' + esc( T( 'No buckets yet. Add one, or let us suggest ranges from your prices.' ) ) + '</div>' ); }
			$( '#ecpp_count' ).text( TN( '%d bucket', '%d buckets', rows.length ) );
			$( '#ecpp_notes' ).text( ( analysis.notes || [] ).join( ' ' ) );
			var cards = [
				{ l: T( 'Buckets' ), v: rows.length, c: 'default' },
				{ l: T( 'Empty buckets' ), v: analysis.empty, c: analysis.empty ? 'amber' : 'gray' },
				{ l: T( 'Gaps / overlaps' ), v: ( analysis.gaps || [] ).length, c: ( analysis.gaps || [] ).length ? 'amber' : 'gray' },
				{ l: T( 'Products not in any bucket' ), v: T( '%1$d of %2$d', analysis.uncovered, analysis.total ), c: analysis.uncovered ? 'red' : 'green' }
			];
			var $c = $( '#ecpp_cards' ).empty();
			$.each( cards, function( k, cd ) { $c.append( '<div class="ecv2-stat-card ecv2-stat-' + cd.c + ' is-static"><span class="ecv2-stat-value">' + esc( cd.v ) + '</span><span class="ecv2-stat-label">' + esc( cd.l ) + '</span></div>' ); } );
			$( '#ecpp_save' ).toggleClass( 'is-dirty', dirty );
		}
		function fmt( v ) { return ( D.currency && D.currency.symbol ? D.currency.symbol.replace( /[\d.,\s]/g, '' ) : '' ) + Number( v ).toFixed( 2 ); }
		function preview() { clearTimeout( t ); t = setTimeout( function() { post( 'ecv2_pricepoint_preview', nonce, { rows: JSON.stringify( rows ) }, function( d ) { analysis = d.analysis; render(); } ); }, 350 ); }
		$pp.on( 'change', '.ecpp-type', function() { var i = +$( this ).data( 'i' ); rows[ i ].type = this.value; if ( rows[ i ].type === 'under' ) { rows[ i ].low = 0; } if ( rows[ i ].type === 'over' ) { rows[ i ].high = 0; } dirty = true; render(); preview(); } );
		$pp.on( 'input', '.ecpp-low', function() { rows[ +$( this ).data( 'i' ) ].low = parseFloat( this.value ) || 0; dirty = true; preview(); } );
		$pp.on( 'input', '.ecpp-high', function() { rows[ +$( this ).data( 'i' ) ].high = parseFloat( this.value ) || 0; dirty = true; preview(); } );
		$pp.on( 'dragstart', '.ecpp-row', function( e ) { drag = +$( this ).data( 'i' ); e.originalEvent.dataTransfer.effectAllowed = 'move'; $( this ).addClass( 'is-dragging' ); } );
		$pp.on( 'dragover', '.ecpp-row', function( e ) { e.preventDefault(); $( '.ecpp-row' ).removeClass( 'drop-before drop-after' ); var r = this.getBoundingClientRect(); $( this ).addClass( e.originalEvent.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after' ); } );
		$pp.on( 'drop', '.ecpp-row', function( e ) { e.preventDefault(); var to = +$( this ).data( 'i' ), before = $( this ).hasClass( 'drop-before' ); if ( drag === null || drag === to ) { return; } var item = rows.splice( drag, 1 )[0]; if ( drag < to ) { to--; } rows.splice( before ? to : to + 1, 0, item ); drag = null; dirty = true; render(); preview(); } );
		$pp.on( 'dragend', function() { $( '.ecpp-row' ).removeClass( 'is-dragging drop-before drop-after' ); } );
		$( window ).on( 'beforeunload', function() { if ( dirty ) { return T( 'You have unsaved changes.' ); } } );
		window.ecpp = {
			add: function() { var last = rows.length ? rows[ rows.length - 1 ] : null; var start = last ? ( last.type === 'over' ? last.low : last.high ) : 0; rows.push( { id: 0, type: 'between', low: start, high: start + ( start || 50 ) } ); dirty = true; render(); preview(); },
			remove: function( i ) { rows.splice( i, 1 ); dirty = true; render(); preview(); },
			close_gap: function( i ) { if ( rows[ i + 1 ] ) { var hi = rows[ i ].type === 'under' ? rows[ i ].high : rows[ i ].high; rows[ i + 1 ].low = hi; dirty = true; render(); preview(); } },
			suggest: function() { ecv2_show_confirm( T( 'Replace your buckets with suggested ranges?' ), T( 'We look at your active product prices and propose an Under, two Between and an Over bucket that split them evenly. Nothing is saved until you click Save.' ) ).then( function( ok ) { if ( ! ok ) { return; } post( 'ecv2_pricepoint_suggest', nonce, {}, function( d ) { rows = d.rows; analysis = d.analysis; dirty = true; render(); } ); } ); },
			save: function() {
				for ( var i = 0; i < rows.length; i++ ) { if ( rows[ i ].type === 'between' && rows[ i ].high < rows[ i ].low ) { ecv2_toast( T( 'Bucket %d has its high amount below its low amount.', i + 1 ), 'error' ); return; } }
				$( '#ecpp_save' ).prop( 'disabled', true );
				post( 'ecv2_pricepoint_save', nonce, { rows: JSON.stringify( rows ) }, function( d ) { rows = d.rows; analysis = d.analysis; dirty = false; $( '#ecpp_save' ).prop( 'disabled', false ); render(); ecv2_toast( d.message, 'success' ); }, function( m ) { $( '#ecpp_save' ).prop( 'disabled', false ); ecv2_toast( m, 'error' ); } );
			}
		};
		render();
	}

	/* ================================================================== */
	/* Per page                                                            */
	/* ================================================================== */
	var $perp = $( '#ecperpage' );
	if ( $perp.length ) {
		var pnonce = $perp.data( 'nonce' );
		function render_chips( values ) {
			var $c = $( '#ecperpage_chips' ), $add = $c.find( '.ecperpage-add' ).detach(); $c.empty();
			$.each( values, function( i, v ) { $c.append( '<span class="ecperpage-chip' + ( v.is_default ? ' is-default' : '' ) + '" data-id="' + v.id + '" data-value="' + v.value + '"><button type="button" class="ecperpage-star" title="' + ea( T( 'Make this the default' ) ) + '" aria-pressed="' + ( v.is_default ? 'true' : 'false' ) + '" onclick="ecperpage.set_default( ' + v.value + ', this );">&#9733;</button><b>' + v.value + '</b>' + ( v.is_default ? '<span class="ecv2-chip ecv2-chip-green">' + esc( T( 'default' ) ) + '</span>' : '' ) + '<button type="button" class="ecperpage-x" title="' + ea( T( 'Remove' ) ) + '" onclick="ecperpage.remove( ' + v.id + ', this );">&times;</button></span>' ); } );
			$c.append( $add ); $( '#ecperpage_count' ).text( TN( '%d value', '%d values', values.length ) );
		}
		window.ecperpage = {
			set_default: function( value, btn ) { if ( $( btn ).closest( '.ecperpage-chip' ).hasClass( 'is-default' ) ) { return; } post( 'ecv2_perpage_default', pnonce, { value: value }, function( d ) { render_chips( d.values ); ecv2_toast( d.message, 'success' ); } ); },
			add: function() { var v = parseInt( $( '#ecperpage_new' ).val(), 10 ); if ( ! v ) { $( '#ecperpage_new' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; } post( 'ecv2_perpage_add', pnonce, { value: v }, function( d ) { $( '#ecperpage_new' ).val( '' ).removeClass( 'is-invalid' ); render_chips( d.values ); ecv2_toast( T( 'Added %s.', v ), 'success' ); }, function( m ) { $( '#ecperpage_new' ).addClass( 'is-invalid' ); ecv2_toast( m, 'error' ); } ); },
			remove: function( id, btn ) { $( btn ).closest( '.ecperpage-chip' ).addClass( 'is-removing' ); post( 'ecv2_perpage_remove', pnonce, { id: id }, function( d ) { render_chips( d.values ); }, function( m ) { $( btn ).closest( '.ecperpage-chip' ).removeClass( 'is-removing' ); ecv2_toast( m, 'error' ); } ); },
			option: function( key, value, el ) { post( 'ecv2_perpage_option', pnonce, { key: key, value: value }, function( d ) { ecv2_toast( d.message, 'success' ); if ( key === 'ec_option_enable_product_paging' && value ) { $( el ).closest( '.ecos-note' ).slideUp( 200 ); $perp.find( 'input[onchange*="enable_product_paging\'"]' ).prop( 'checked', true ); } } ); }
		};
	}

	/* ================================================================== */
	/* Log viewer                                                          */
	/* ================================================================== */
	if ( $( '.eclog-bar' ).length ) {
		var lnonce = $( '.eclog-bar' ).data( 'nonce' );
		function load_payload( $tr, cb ) {
			var $pre = $tr.find( '.eclog-payload' );
			if ( $pre.data( 'loaded' ) === 1 ) { cb && cb( $pre.text() ); return; }
			post( 'ecv2_log_payload', lnonce, { id: $tr.data( 'id' ) }, function( d ) { $pre.text( d.payload ).data( 'loaded', 1 ); cb && cb( d.payload ); } );
		}
		window.eclog = {
			toggle: function( a ) { var $tr = $( a ).closest( 'tr' ), $pre = $tr.find( '.eclog-payload' ); if ( $pre.is( ':visible' ) ) { $pre.hide(); $( a ).text( T( 'expand' ) ); return false; } $( a ).text( T( 'collapse' ) ); load_payload( $tr, function() { $pre.show(); } ); return false; },
			copy: function( link ) { var $tr = $( link ).closest( 'tr' ); load_payload( $tr, function( txt ) { if ( navigator.clipboard ) { navigator.clipboard.writeText( txt ).then( function() { ecv2_toast( T( 'Payload copied.' ), 'success' ); } ); } else { ecv2_toast( T( 'Clipboard unavailable — expand the row and copy manually.' ), 'info' ); } } ); return false; },
			order: function( link ) { var oid = $( link ).closest( 'tr' ).data( 'order-id' ); if ( ! oid ) { ecv2_toast( T( 'This entry is not linked to an order.' ), 'info' ); return false; } window.location.href = 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' + oid; return false; },
			retention: function( days ) { post( 'ecv2_log_retention', lnonce, { days: days }, function( d ) { ecv2_toast( d.message, 'success' ); } ); },
			clear: function() { var days = window.prompt( T( 'Remove entries older than how many days?' ), '30' ); if ( ! days ) { return; } post( 'ecv2_log_clear', lnonce, { days: parseInt( days, 10 ) || 30 }, function( d ) { ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 800 ); } ); }
		};
		$( '.eclog-row.is-open .eclog-expand' ).each( function() { eclog.toggle( this ); } );
	}

	/* ================================================================== */
	/* User roles ( list + editor )                                        */
	/* ================================================================== */
	( function() {
		var $wrap = $( '.ecv2-wrap[data-table-id="ec_admin_user_role_list_v2"], #eclite[data-kind="role"]' );
		if ( ! $wrap.length ) { return; }
		var D = $( '#eclite_data' ).length ? JSON.parse( $( '#eclite_data' ).text() ) : {};
		var nonce = D.nonce || ( window.ecv2_catalog_vars && ecv2_catalog_vars.nonces && ecv2_catalog_vars.nonces.rolev2 ) || $( '.ecv2-wrap' ).data( 'role-nonce' );
		var prices = $( '#ecrl_prices_data' ).length ? JSON.parse( $( '#ecrl_prices_data' ).text() ) : [], money = function( v ) { return Number( v ).toFixed( 2 ); };
		function undo_toast( msg, undo ) {
			var $t = $( '<div class="ecv2-toast ecv2-toast-success ecv2-toast-undo"><span class="dashicons dashicons-yes"></span> <span class="ecv2-toast-msg"></span> <a href="#">' + esc( T( 'Undo' ) ) + '</a></div>' ); $t.find( '.ecv2-toast-msg' ).text( msg );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); post( 'ecv2_role_restore', nonce, { undo: undo }, function( d ) { ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } ); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 12000 );
		}
		function render_prices() {
			var $r = $( '#ecrl_price_rows' ).empty();
			$.each( prices, function( i, p ) {
				var pct = p.pct === null ? '' : ( p.pct > 0 ? '<span class="ecv2-chip ecv2-chip-red">+' + p.pct + '%</span> <span class="ecv2-sub" style="display:inline">' + esc( T( 'higher than regular' ) ) + '</span>' : '<span class="ecv2-chip ecv2-chip-green">' + p.pct + '%</span>' );
				$r.append( '<div class="ecrl-price-row' + ( p.orphan ? ' is-orphan' : '' ) + '" data-id="' + p.id + '"><span class="ecrl-p"><span class="ecv2-thumb ecv2-thumb-xs">' + ( p.image ? '<img src="' + esc( p.image ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</span><span><b>' + esc( p.title ) + '</b><span class="ecv2-sub">' + esc( p.sku ) + '</span></span></span><span>' + money( p.regular ) + '</span><span><input type="number" step="0.01" min="0" class="ecv2-input ecrl-price-input" data-product="' + p.product_id + '" value="' + money( p.role_price ) + '"></span><span>' + pct + '</span><span><a href="#" onclick="ecrole.remove_price( ' + p.id + ' ); return false;">' + esc( T( 'remove' ) ) + '</a></span></div>' );
			} );
			if ( ! prices.length ) { $r.append( '<div class="ecos-empty">' + esc( T( 'No role prices yet — search a product above.' ) ) + '</div>' ); }
			$( '#ecrl_price_foot' ).text( prices.length ? TN( '%d role price', '%d role prices', prices.length ) : '' );
		}
		var st;
		$( '#ecrl_search' ).on( 'input', function() { clearTimeout( st ); var q = this.value; if ( q.length < 2 ) { $( '#ecrl_results' ).hide(); return; } st = setTimeout( function() {
			post( 'ecv2_role_price_search', nonce, { role_id: D.id, q: q }, function( d ) {
				var $res = $( '#ecrl_results' ).empty().show();
				$.each( d.items, function( i, p ) { $res.append( '<div class="ecrl-result" data-id="' + p.id + '" data-price="' + p.price + '"><span class="ecv2-thumb ecv2-thumb-xs">' + ( p.image ? '<img src="' + esc( p.image ) + '" alt="">' : '' ) + '</span><span class="ecrl-result-main"><b>' + esc( p.title ) + '</b><span class="ecv2-sub">' + esc( p.sku ) + ' · ' + esc( T( 'regular %s', p.price_display ) ) + ( p.existing !== null ? ' · ' + esc( T( 'role price %s', money( p.existing ) ) ) : '' ) + '</span></span><span class="ecrl-result-set"><input type="number" step="0.01" min="0" class="ecv2-input" placeholder="' + money( p.price ) + '" value="' + ( p.existing !== null ? money( p.existing ) : '' ) + '"><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary">' + esc( T( 'Set' ) ) + '</button></span></div>' ); } );
				if ( ! d.items.length ) { $res.append( '<div class="ecos-hint" style="padding:10px">' + esc( T( 'No matching products.' ) ) + '</div>' ); }
			} ); }, 250 ); } );
		$wrap.on( 'click', '.ecrl-result-set button', function() { var $row = $( this ).closest( '.ecrl-result' ), v = $row.find( 'input' ).val(); if ( v === '' ) { $row.find( 'input' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; } ecrole.set_price( $row.data( 'id' ), v ); } );
		$wrap.on( 'keydown', '.ecrl-result-set input', function( e ) { if ( e.key === 'Enter' ) { $( this ).siblings( 'button' ).trigger( 'click' ); } } );
		$wrap.on( 'change', '.ecrl-price-input', function() { ecrole.set_price( $( this ).data( 'product' ), this.value ); } );
		$wrap.on( 'change', '#eclite_access', function() { $( '#rlv2_panels' ).toggle( this.checked ); } );
		$( document ).on( 'mousedown', function( e ) { if ( ! $( e.target ).closest( '.ecrl-add' ).length ) { $( '#ecrl_results' ).hide(); } } );
		$( document ).on( 'change', '.ecv2-role-access', function() { var $cb = $( this ), id = $cb.data( 'id' ), on = $cb.is( ':checked' ) ? 1 : 0; post( 'ecv2_role_toggle_access', nonce, { role_id: id, on: on }, function() { ecv2_toast( on ? T( 'Remote admin access on.' ) : T( 'Remote admin access off.' ), 'success' ); }, function( m ) { $cb.prop( 'checked', ! on ); ecv2_toast( m, 'error' ); } ); } );
		if ( window.eclite ) {
			var base_save = eclite.save;
			eclite.save = function() {
				var $root = $( '#eclite' ); var data = {};
				$root.find( '[data-field]' ).each( function() { data[ $( this ).data( 'field' ) ] = this.type === 'checkbox' ? ( this.checked ? 1 : 0 ) : this.value; } );
				data.panels = []; $( '.ecrl-panel-cb:checked' ).each( function() { data.panels.push( this.value ); } );
				var $btn = $( '#eclite_save' ).prop( 'disabled', true );
				post( D.save_action, nonce, { id: D.id, data: JSON.stringify( data ) }, function( d ) {
					$btn.prop( 'disabled', false ); $( '#eclite_dirty' ).removeClass( 'is-visible' ); $( '#eclite_h_title' ).text( d.label );
					var $h = $( '#eclite_health .ecdv2-health-items' ).empty(); $.each( d.health || [], function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } );
					ecv2_toast( d.message, 'success' );
				}, function( m ) { $btn.prop( 'disabled', false ); ecv2_toast( m, 'error' ); } );
			};
		}
		window.ecrole = {
			new_role: function( anchor ) {
				var $m = ecv2_catalog_modal( 'ecv2-newrole', esc( T( 'New role' ) ), '<div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Role name' ) ) + '</label><input type="text" class="ecv2-input" id="ecv2_nr_name" placeholder="' + ea( T( 'e.g. Wholesale' ) ) + '"><span class="ecdv2-field-desc">' + esc( T( 'Assign it to customers from their account page; set role prices in the editor.' ) ) + '</span></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_nr_go">' + esc( T( 'Create & edit' ) ) + '</button>', { size: 'sm', anchor: anchor || null } );
				setTimeout( function() { $( '#ecv2_nr_name' ).trigger( 'focus' ); }, 100 );
				var go = function() { var name = $.trim( $( '#ecv2_nr_name' ).val() ); if ( ! name ) { $( '#ecv2_nr_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; } post( 'ecv2_role_create', nonce, { label: name }, function( d ) { window.location.href = d.edit_url; } ); };
				$( '#ecv2_nr_go' ).on( 'click', go ); $( '#ecv2_nr_name' ).on( 'keydown', function( e ) { if ( e.key === 'Enter' ) { go(); } } );
			},
			view_users: function( link ) { var $tr = $( link ).closest( 'tr' ); window.location.href = 'admin.php?page=wp-easycart-users&subpage=accounts&filter_0=' + encodeURIComponent( $tr.data( 'label' ) ); return false; },
			set_price: function( product_id, price ) { post( 'ecv2_role_price_set', nonce, { role_id: D.id, product_id: product_id, price: price }, function( d ) { var found = false; $.each( prices, function( i, p ) { if ( p.product_id === d.row.product_id ) { prices[ i ] = d.row; found = true; } } ); if ( ! found ) { prices.unshift( d.row ); } render_prices(); $( '#ecrl_results' ).hide(); $( '#ecrl_search' ).val( '' ); ecv2_toast( T( 'Role price saved.' ), 'success' ); } ); },
			remove_price: function( id ) { post( 'ecv2_role_price_remove', nonce, { roleprice_id: id }, function() { prices = $.grep( prices, function( p ) { return p.id !== id; } ); render_prices(); } ); },
			percent_off: function() {
				var $m = ecv2_catalog_modal( 'ecv2-pct', esc( T( 'Apply a discount to role prices' ) ), '<div class="ecdv2-grid"><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Percent off regular price' ) ) + '</label><input type="number" min="1" max="99" class="ecv2-input" id="ecv2_pct_v" value="20"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Apply to' ) ) + '</label><select class="ecv2-select" id="ecv2_pct_scope"><option value="existing">' + esc( T( 'Products that already have a role price (%d)', prices.length ) ) + '</option><option value="all">' + esc( T( 'Every active product (creates role prices)' ) ) + '</option></select></div></div><div class="ecos-note info" style="margin-top:10px"><span class="dashicons dashicons-info-outline"></span><div>' + esc( T( 'Role prices are recalculated from each product\'s current regular price. Existing values are overwritten.' ) ) + '</div></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_pct_go">' + esc( T( 'Apply' ) ) + '</button>' );
				$( '#ecv2_pct_go' ).on( 'click', function() { $( this ).prop( 'disabled', true ); post( 'ecv2_role_price_percent', nonce, { role_id: D.id, percent: $( '#ecv2_pct_v' ).val(), scope: $( '#ecv2_pct_scope' ).val() }, function( d ) { ecv2_catalog_close_modal(); prices = d.rows; render_prices(); ecv2_toast( d.message, 'success' ); }, function( m ) { $( '#ecv2_pct_go' ).prop( 'disabled', false ); ecv2_toast( m, 'error' ); } ); } );
			},
			/* ---- users in this role: server-side search + paging ---- */
			users_state: { page: 1, q: '', pages: 1, total: 0 },
			users_load: function( page, q ) {
				var st = ecrole.users_state; if ( page !== undefined ) { st.page = page; } if ( q !== undefined ) { st.q = q; }
				$( '#ecrl_users' ).addClass( 'is-loading' );
				post( 'ecv2_role_users', nonce, { role_id: D.id, page: st.page, q: st.q }, function( d ) {
					st.page = d.page; st.pages = d.pages; st.total = d.total; var $l = $( '#ecrl_users' ).removeClass( 'is-loading' ).empty();
					if ( ! d.items.length ) { $l.append( '<div class="ecos-empty">' + ( st.q ? esc( T( 'No users in this role match “%s”.', st.q ) ) : esc( T( 'No users have this role yet — search above to add some.' ) ) ) + '</div>' ); }
					$.each( d.items, function( i, u ) { $l.append( '<div class="ecos-u" data-id="' + u.id + '"><input type="checkbox" class="ecrl-u-check" value="' + u.id + '"><div class="ecos-u-main"><a class="ecv2-link-primary" href="' + esc( u.edit_url ) + '">' + esc( u.name || u.email ) + '</a><span class="ecv2-sub">' + esc( u.email ) + ( u.orders ? ' · ' + esc( TN( '%d order', '%d orders', u.orders ) ) : '' ) + ( u.spend ? ' · ' + esc( u.spend ) : '' ) + '</span></div><a href="#" class="ecv2-sub ecrl-u-rm" onclick="return ecrole.remove_user( ' + u.id + ' );">' + esc( T( 'remove' ) ) + '</a></div>' ); } );
					if ( ! st.q ) { $( '#ecrl_users_count' ).text( TN( '%d user', '%d users', d.total ) ); $( '.ecdv2-rail a[href="#rlv2-users"] .ecv2-chip' ).text( d.total ); }
					$( '#ecrl_users_pager' ).toggle( d.total > d.per ); $( '#ecrl_users_foot' ).text( d.total ? ( st.q ? T( 'Showing %1$d–%2$d of %3$d matching', ( d.page - 1 ) * d.per + 1, Math.min( d.page * d.per, d.total ), d.total ) : T( 'Showing %1$d–%2$d of %3$d', ( d.page - 1 ) * d.per + 1, Math.min( d.page * d.per, d.total ), d.total ) ) : '' );
					$( '#ecrl_prev' ).prop( 'disabled', d.page <= 1 ); $( '#ecrl_next' ).prop( 'disabled', d.page >= d.pages );
					ecrole.sel_changed();
				} );
			},
			users_page: function( delta ) { var st = ecrole.users_state; ecrole.users_load( Math.max( 1, Math.min( st.pages, st.page + delta ) ) ); },
			sel_changed: function() {
				var ids = $( '.ecrl-u-check:checked' ).map( function() { return this.value; } ).get(); $( '#ecrl_users_sel' ).toggle( ids.length > 0 ); $( '#ecrl_sel_count' ).text( T( '%d selected →', ids.length ) );
				var $to = $( '#ecrl_sel_to' ); if ( ! $to.children().length ) { $.each( D.roles || [], function( i, r ) { if ( r.id !== D.id ) { $to.append( '<option value="' + r.id + '">' + esc( r.label ) + '</option>' ); } } ); }
			},
			move_selected: function() {
				var ids = $( '.ecrl-u-check:checked' ).map( function() { return this.value; } ).get(), to = $( '#ecrl_sel_to' ).val(), label = $( '#ecrl_sel_to option:selected' ).text();
				if ( ! ids.length || ! to ) { return; }
				post( 'ecv2_role_move_users', nonce, { role_id: D.id, to_role_id: to, user_ids: ids }, function( d ) { ecv2_toast( d.message, 'success' ); ecrole.users_load(); } );
			},
			remove_user: function( id ) {
				var others = ( D.roles || [] ).filter( function( r ) { return r.id !== D.id; } ), def = others.filter( function( r ) { return r.label === 'shopper'; } )[0] || others[0];
				if ( ! def ) { ecv2_toast( T( 'No other role to move this user to.' ), 'error' ); return false; }
				var opts = ''; $.each( others, function( i, r ) { opts += '<option value="' + r.id + '"' + ( r.id === def.id ? ' selected' : '' ) + '>' + esc( r.label ) + '</option>'; } );
				ecv2_catalog_modal( 'ecv2-rlrm', esc( T( 'Remove from this role' ) ), '<div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Move the user to' ) ) + '</label><select class="ecv2-select" id="ecv2_rlrm_to">' + opts + '</select><span class="ecdv2-field-desc">' + esc( T( 'Every account has a role; role prices and access follow it.' ) ) + '</span></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_rlrm_go">' + esc( T( 'Move' ) ) + '</button>', 'ecv2-modal-sm' );
				$( '#ecv2_rlrm_go' ).on( 'click', function() { post( 'ecv2_role_move_users', nonce, { role_id: D.id, to_role_id: $( '#ecv2_rlrm_to' ).val(), user_ids: [ id ] }, function( d ) { ecv2_catalog_close_modal(); ecv2_toast( d.message, 'success' ); ecrole.users_load(); } ); } );
				return false;
			},
			/* ---- add users: search accounts not in this role ---- */
			assign_search: function( q ) {
				post( 'ecv2_role_user_search', nonce, { role_id: D.id, q: q }, function( d ) {
					var $r = $( '#ecrl_assign_results' ).empty().show();
					if ( ! d.items.length ) { $r.append( '<div class="ecos-hint" style="padding:10px">' + esc( q.length < 2 ? T( 'Type at least two characters.' ) : T( 'No other accounts match.' ) ) + '</div>' ); return; }
					var attr = function( s ) { return esc( s ).replace( /"/g, '&quot;' ); };
					$.each( d.items, function( i, u ) { $r.append( '<div class="ecrl-result ecrl-result-user"><span class="ecrl-result-main"><b title="' + attr( u.name || u.email ) + '">' + esc( u.name || u.email ) + '</b>' + ( u.name ? '<span class="ecv2-sub" title="' + attr( u.email ) + '">' + esc( u.email ) + '</span>' : '' ) + '</span>' + ( u.role ? '<span class="ecv2-chip ecv2-chip-gray ecrl-result-role" title="' + attr( T( 'Currently %s', u.role ) ) + '">' + esc( u.role ) + '</span>' : '<span></span>' ) + '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecrole.assign( [' + u.id + '] );">' + esc( T( 'Add' ) ) + '</button></div>' ); } );
					if ( d.items.length > 1 ) { $r.append( '<div class="ecrl-result ecrl-result-user ecrl-result-all"><span class="ecrl-result-main"><span class="ecv2-sub">' + esc( T( 'All %d shown', d.items.length ) ) + '</span></span><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecrole.assign( [' + d.items.map( function( u ) { return u.id; } ).join( ',' ) + '] );">' + esc( T( 'Add all shown' ) ) + '</button></div>' ); }
				} );
			},
			assign: function( ids ) {
				var go = function() { post( 'ecv2_role_assign', nonce, { role_id: D.id, user_ids: ids }, function( d ) { ecv2_toast( d.message, 'success' ); $( '#ecrl_assign_results' ).hide(); $( '#ecrl_assign_q' ).val( '' ); ecrole.users_load( 1, '' ); $( '#ecrl_users_q' ).val( '' ); } ); };
				if ( D.grants_admin ) { ecv2_show_confirm( T( 'Grant admin access?' ), TN( 'This role can manage the store from the EasyCart apps. Add %d user to it?', 'This role can manage the store from the EasyCart apps. Add %d users to it?', ids.length ) ).then( function( ok ) { if ( ok ) { go(); } } ); } else { go(); }
			},
			move_all: function() {
				post( 'ecv2_role_delete_impact', nonce, { role_id: D.id }, function( d ) {
					var opts = ''; $.each( d.targets, function( i, t ) { opts += '<option value="' + t.value + '"' + ( t.value == d.default_target ? ' selected' : '' ) + '>' + esc( t.label ) + '</option>'; } );
					ecv2_catalog_modal( 'ecv2-mv', esc( T( 'Move all users to another role' ) ), '<div class="ecdv2-field"><label class="ecdv2-label">' + esc( TN( 'Move %d user to', 'Move %d users to', d.users ) ) + '</label><select class="ecv2-select" id="ecv2_mv_to">' + opts + '</select></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_mv_go">' + esc( T( 'Move users' ) ) + '</button>', 'ecv2-modal-sm' );
					$( '#ecv2_mv_go' ).on( 'click', function() { post( 'ecv2_role_move_users', nonce, { role_id: D.id, to_role_id: $( '#ecv2_mv_to' ).val() }, function( r ) { ecv2_catalog_close_modal(); ecv2_toast( r.message, 'success' ); setTimeout( function() { window.location.reload(); }, 700 ); } ); } );
				} );
			},
			delete_role: function( id, redirect_to ) {
				post( 'ecv2_role_delete_impact', nonce, { role_id: id }, function( d ) {
					var opts = ''; $.each( d.targets, function( i, t ) { opts += '<option value="' + t.value + '"' + ( t.value == d.default_target ? ' selected' : '' ) + '>' + esc( t.label ) + '</option>'; } );
					var body = '<div class="ecsd-impacts"><div class="ecsd-impact ' + ( d.users ? 'is-warn' : 'is-info' ) + '">' + ( d.users ? TN( '<b>%d</b> user has this role — choose where they go:', '<b>%d</b> users have this role — choose where they go:', d.users ) : T( '<b>0</b> users have this role.' ) ) + '</div>' + ( d.users ? '<select class="ecv2-select" id="ecv2_del_to">' + opts + '</select>' : '' ) + '<div class="ecsd-impact ' + ( d.prices ? 'is-warn' : 'is-info' ) + '" style="margin-top:10px">' + TN( '<b>%d</b> role price will be removed.', '<b>%d</b> role prices will be removed.', d.prices ) + '</div></div><div class="ecos-note info" style="margin-top:12px"><span class="dashicons dashicons-undo"></span><div>' + esc( T( 'Undo is offered for 15 minutes.' ) ) + '</div></div>';
					ecv2_catalog_modal( 'ecv2-delrole', esc( T( 'Delete role “%s”?', d.name ) ), body, '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Keep role' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-danger" id="ecv2_del_go">' + esc( T( 'Delete role' ) ) + '</button>' );
					$( '#ecv2_del_go' ).on( 'click', function() { $( this ).prop( 'disabled', true ); post( 'ecv2_role_delete', nonce, { role_id: id, to_role_id: $( '#ecv2_del_to' ).val() || 2 }, function( r ) { ecv2_catalog_close_modal(); if ( redirect_to ) { try { sessionStorage.setItem( 'ecv2_catalog_flash', JSON.stringify( { message: r.message, role_undo: r.undo } ) ); } catch ( e ) {} window.location.href = redirect_to; return; } $( 'tr[data-id="' + id + '"]' ).fadeOut( 200, function() { $( this ).remove(); } ); if ( window.ecv2_rows_removed ) { ecv2_rows_removed( 1 ); } undo_toast( r.message, r.undo ); } ); } );
				} );
			},
			delete_row: function( link ) { var $tr = $( link ).closest( 'tr' ); if ( $tr.data( 'master' ) == 1 ) { ecv2_toast( T( 'Built-in roles cannot be deleted.' ), 'info' ); return false; } ecrole.delete_row_id( $tr.data( 'id' ) ); return false; },
			delete_row_id: function( id ) { ecrole.delete_role( id, null ); },
			bulk_delete: function( ids ) { if ( ids.length !== 1 ) { ecv2_toast( T( 'Delete roles one at a time — each needs a destination for its users.' ), 'info' ); return; } ecrole.delete_role( ids[0], null ); }
		};
		if ( $( '#eclite[data-kind="role"]' ).length ) {
			ecrole.users_load( 1, '' );
			var ut; $( '#ecrl_users_q' ).on( 'input', function() { clearTimeout( ut ); var q = this.value; ut = setTimeout( function() { ecrole.users_load( 1, $.trim( q ) ); }, 250 ); } );
			var at; $( '#ecrl_assign_q' ).on( 'input', function() { clearTimeout( at ); var q = $.trim( this.value ); if ( q.length < 2 ) { $( '#ecrl_assign_results' ).hide(); return; } at = setTimeout( function() { ecrole.assign_search( q ); }, 250 ); } );
			$( document ).on( 'mousedown', function( e ) { if ( ! $( e.target ).closest( '.ecrl-assign' ).length ) { $( '#ecrl_assign_results' ).hide(); } } );
			$( document ).on( 'change', '.ecrl-u-check', ecrole.sel_changed );
		}
		render_prices();
		try { var flash = JSON.parse( sessionStorage.getItem( 'ecv2_catalog_flash' ) || 'null' ); if ( flash && flash.role_undo ) { sessionStorage.removeItem( 'ecv2_catalog_flash' ); setTimeout( function() { undo_toast( flash.message, flash.role_undo ); }, 300 ); } } catch ( e ) {}
	} )();

	/* ================================================================== */
	/* Subscribers                                                         */
	/* ================================================================== */
	( function() {
		var $bar = $( '.ecsub-bar' ); if ( ! $bar.length ) { return; }
		var nonce = $bar.data( 'nonce' );
		function parse_csv( text ) {
			var rows = [], row = [], cur = '', q = false;
			for ( var i = 0; i < text.length; i++ ) { var c = text[ i ]; if ( q ) { if ( c === '"' ) { if ( text[ i + 1 ] === '"' ) { cur += '"'; i++; } else { q = false; } } else { cur += c; } } else if ( c === '"' ) { q = true; } else if ( c === ',' || c === ';' || c === '\t' ) { row.push( cur ); cur = ''; } else if ( c === '\n' || c === '\r' ) { if ( cur !== '' || row.length ) { row.push( cur ); rows.push( row ); } row = []; cur = ''; if ( c === '\r' && text[ i + 1 ] === '\n' ) { i++; } } else { cur += c; } }
			if ( cur !== '' || row.length ) { row.push( cur ); rows.push( row ); }
			return rows;
		}
		var parsed = null;
		window.ecsub = {
			add: function( existing, anchor ) {
				var e = existing || {};
				ecv2_catalog_modal( 'ecv2-newsub', esc( e.id ? T( 'Edit subscriber' ) : T( 'Add subscriber' ) ), '<div class="ecdv2-grid"><div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label">' + esc( T( 'Email' ) ) + '</label><input type="email" class="ecv2-input" id="ecv2_ns_email" value="' + esc( e.email || '' ) + '"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'First name' ) ) + '</label><input type="text" class="ecv2-input" id="ecv2_ns_first" value="' + esc( e.first || '' ) + '"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Last name' ) ) + '</label><input type="text" class="ecv2-input" id="ecv2_ns_last" value="' + esc( e.last || '' ) + '"></div></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_ns_go">' + esc( e.id ? T( 'Save' ) : T( 'Add subscriber' ) ) + '</button>', { size: 'sm', anchor: anchor || null } );
				setTimeout( function() { $( '#ecv2_ns_email' ).trigger( 'focus' ); }, 100 );
				$( '#ecv2_ns_go' ).on( 'click', function() {
					var email = $.trim( $( '#ecv2_ns_email' ).val() ), first = $( '#ecv2_ns_first' ).val(), last = $( '#ecv2_ns_last' ).val();
					if ( e.id ) {
						var todo = [ [ 'email', email ], [ 'first_name', first ], [ 'last_name', last ] ], i = 0;
						( function next() { if ( i >= todo.length ) { ecv2_catalog_close_modal(); ecv2_toast( T( 'Saved.' ), 'success' ); setTimeout( function() { window.location.reload(); }, 500 ); return; } var f = todo[ i++ ]; post( 'ecv2_subscriber_inline_update', nonce, { subscriber_id: e.id, field: f[0], value: f[1] }, next ); } )();
						return;
					}
					post( 'ecv2_subscriber_add', nonce, { email: email, first: first, last: last }, function() { ecv2_catalog_close_modal(); ecv2_toast( T( 'Added %s.', email ), 'success' ); setTimeout( function() { window.location.reload(); }, 500 ); }, function( m ) { $( '#ecv2_ns_email' ).addClass( 'is-invalid' ); ecv2_toast( m, 'error' ); } );
				} );
			},
			edit: function( link ) { var $tr = $( link ).closest( 'tr' ); ecsub.add( { id: $tr.data( 'id' ), email: $tr.data( 'email' ), first: $tr.data( 'first' ), last: $tr.data( 'last' ) } ); return false; },
			account: function( link ) { var id = $( link ).closest( 'tr' ).data( 'customer-id' ); if ( ! id ) { ecv2_toast( T( 'No customer account matches this email.' ), 'info' ); return false; } window.location.href = 'admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=edit&user_id=' + id; return false; },
			delete_row: function( link ) { var $tr = $( link ).closest( 'tr' ); ecsub.bulk_delete( [ $tr.data( 'id' ) ] ); return false; },
			/* 6.0.1: ask first ( in the V2 dialog ), then reload so the counts and paging are right. The reload lands on
			   ?undo=<key>, where the list prints the shared Undo bar ( wp_easycart_admin_undo::maybe_print_bar() ). It used
			   to come back as a toast through the ecv2_catalog_flash hand-off, but catalog-v2.js read that first and sent
			   the Undo to the category trash, so it always failed; a toast also faded before it could be reached. */
			bulk_delete: function( ids ) {
				var n = ids.length;
				var q = 1 === n ? T( 'Delete this subscriber?' ) : TN( 'Delete %d subscriber?', 'Delete %d subscribers?', n );
				var ask = window.ecv2_show_confirm ? ecv2_show_confirm( q, T( 'They stop receiving the newsletter. You can undo this for 15 minutes.' ) ) : Promise.resolve( window.confirm( q ) );
				ask.then( function( go ) {
					if ( ! go ) { return; }
					post( 'ecv2_subscriber_bulk', nonce, { ids: ids, op: 'delete' }, function( d ) {
						if ( d.undo && window.ecv2_undo_landing ) { window.location.href = ecv2_undo_landing( 'undo', d.undo ); return; }
						ecv2_toast( d.message, 'success' );
						setTimeout( function() { window.location.reload(); }, 600 );
					} );
				} );
			},
			export_selected: function( ids ) { window.location.href = $bar.data( 'export' ) + '&ids=' + ids.join( ',' ); },
			import_open: function() {
				parsed = null;
				ecv2_catalog_modal( 'ecv2-import', esc( T( 'Import subscribers' ) ), '<div class="ecsub-drop" id="ecsub_drop"><input type="file" id="ecsub_file" accept=".csv,text/csv,text/plain" style="display:none"><p>' + T( '<b>Choose a CSV file</b> or paste addresses below.' ) + '</p><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="document.getElementById(\'ecsub_file\').click();">' + esc( T( 'Choose file' ) ) + '</button></div><textarea class="ecv2-input" id="ecsub_paste" rows="5" placeholder="' + ea( T( 'email, first name, last name — one per line. A header row is fine.' ) ) + '"></textarea><div class="ecsub-map" id="ecsub_map" style="display:none"></div><div id="ecsub_preview" class="ecos-hint" style="margin-top:8px"></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecsub_import_go" disabled>' + esc( T( 'Import' ) ) + '</button>' );
				function analyze( text ) {
					var rows = parse_csv( text ); if ( ! rows.length ) { return; }
					var head = rows[0].map( function( h ) { return String( h ).toLowerCase(); } ), hasHeader = head.some( function( h ) { return /mail|first|last|name/.test( h ); } );
					var cols = rows[0].length, map = { email: -1, first: -1, last: -1 };
					if ( hasHeader ) { head.forEach( function( h, i ) { if ( /mail/.test( h ) ) { map.email = i; } else if ( /first|given/.test( h ) ) { map.first = i; } else if ( /last|sur|family/.test( h ) ) { map.last = i; } } ); }
					if ( map.email < 0 ) { rows[0].forEach( function( v, i ) { if ( map.email < 0 && /@/.test( String( v ) ) ) { map.email = i; } } ); if ( map.email < 0 ) { map.email = 0; } if ( cols > 1 && map.first < 0 ) { map.first = map.email === 0 ? 1 : 0; } if ( cols > 2 && map.last < 0 ) { map.last = 2; } }
					var $m = $( '#ecsub_map' ).empty().show(); var opts = function( sel ) { var h = '<option value="-1">' + esc( T( 'Skip' ) ) + '</option>'; for ( var i = 0; i < cols; i++ ) { h += '<option value="' + i + '"' + ( i === sel ? ' selected' : '' ) + '>' + esc( T( 'Column %d', i + 1 ) ) + ( hasHeader ? ' — ' + esc( rows[0][ i ] ) : '' ) + '</option>'; } return h; };
					$m.append( '<label>' + esc( T( 'Email' ) ) + ' <select class="ecv2-select" data-k="email">' + opts( map.email ) + '</select></label><label>' + esc( T( 'First name' ) ) + ' <select class="ecv2-select" data-k="first">' + opts( map.first ) + '</select></label><label>' + esc( T( 'Last name' ) ) + ' <select class="ecv2-select" data-k="last">' + opts( map.last ) + '</select></label>' );
					parsed = { rows: rows, hasHeader: hasHeader, map: map };
					preview();
				}
				function build() { if ( ! parsed ) { return []; } var out = []; var m = parsed.map; $( '#ecsub_map select' ).each( function() { m[ $( this ).data( 'k' ) ] = parseInt( this.value, 10 ); } ); parsed.rows.forEach( function( r, i ) { if ( i === 0 && parsed.hasHeader ) { return; } out.push( { email: m.email >= 0 ? r[ m.email ] : '', first: m.first >= 0 ? r[ m.first ] : '', last: m.last >= 0 ? r[ m.last ] : '' } ); } ); return out; }
				function preview() { var rows = build(); if ( ! rows.length ) { return; } post( 'ecv2_subscriber_import_preview', nonce, { rows: JSON.stringify( rows ) }, function( d ) { $( '#ecsub_preview' ).html( T( '<b>Preview:</b> %1$d new · %2$d already subscribed (skipped) · %3$d invalid', d.new, d.existing, d.invalid ) + ( d.invalid_sample.length ? ' ' + esc( T( '(e.g. %s)', d.invalid_sample.join( ', ' ) ) ) : '' ) ); $( '#ecsub_import_go' ).prop( 'disabled', ! d.new ).text( TN( 'Import %d subscriber', 'Import %d subscribers', d.new ) ); } ); }
				$( '#ecsub_file' ).on( 'change', function() { var f = this.files[0]; if ( ! f ) { return; } var r = new FileReader(); r.onload = function() { $( '#ecsub_paste' ).val( '' ); analyze( String( r.result ) ); }; r.readAsText( f ); } );
				var pt; $( '#ecsub_paste' ).on( 'input', function() { clearTimeout( pt ); var v = this.value; pt = setTimeout( function() { analyze( v ); }, 400 ); } );
				$( document ).on( 'change', '#ecsub_map select', preview );
				$( '#ecsub_import_go' ).on( 'click', function() { $( this ).prop( 'disabled', true ); post( 'ecv2_subscriber_import', nonce, { rows: JSON.stringify( build() ) }, function( d ) { ecv2_catalog_close_modal(); ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 900 ); } ); } );
			}
		};
	} )();


	/* ================================================================== */
	/* Countries & regions                                                 */
	/* ================================================================== */
	( function() {
		var $bar = $( '#eccnt_ctx, .eccnt-bar' ).first(); if ( ! $bar.length ) { return; }
		var nonce = $bar.data( 'nonce' ), cur = null, dirty = false;
		function undo_toast( msg, undo ) {
			var $t = $( '<div class="ecv2-toast ecv2-toast-success ecv2-toast-undo"><span class="dashicons dashicons-yes"></span> <span class="ecv2-toast-msg"></span> <a href="#">' + esc( T( 'Undo' ) ) + '</a></div>' ); $t.find( '.ecv2-toast-msg' ).text( msg );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); post( 'ecv2_country_restore', nonce, { undo: undo }, function( d ) { ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } ); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 12000 );
		}
		$( document ).on( 'change', '.eccnt-ship', function() { var $cb = $( this ), on = $cb.is( ':checked' ) ? 1 : 0; post( 'ecv2_country_toggle_ship', nonce, { kind: $cb.data( 'kind' ), id: $cb.data( 'id' ), on: on }, function() { $cb.closest( 'tr' ).toggleClass( 'is-off', ! on ); }, function( m ) { $cb.prop( 'checked', ! on ); ecv2_toast( m, 'error' ); } ); } );

		/* ---- drawer ---- */
		function drawer_html( c, tab ) {
			var isNew = ! c.id;
			var h = '<div class="ecdrawer-backdrop"></div><aside class="ecdrawer" role="dialog" aria-modal="true"><div class="ecdrawer-h"><h3>' + ( isNew ? esc( T( 'Add country' ) ) : esc( c.name ) ) + '</h3>' + ( ! isNew ? '<span class="ecv2-chip ' + ( c.ship ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '" id="eccnt_d_ship">' + esc( c.ship ? T( 'Ship-to on' ) : T( 'Ship-to off' ) ) + '</span>' : '' ) + '<button type="button" class="ecdrawer-x" onclick="eccountry.close();">&times;</button></div>';
			h += '<div class="ecdrawer-tabs">' + [ [ 'details', esc( T( 'Details' ) ) ], [ 'regions', esc( T( 'Regions' ) ) + ( isNew ? '' : ' · ' + c.regions.length ) ], [ 'tax', esc( T( 'Tax' ) ) ], [ 'usage', esc( T( 'Where it’s used' ) ) ] ].map( function( t ) { return '<a href="#" data-tab="' + t[0] + '" class="' + ( t[0] === tab ? 'is-on' : '' ) + ( isNew && t[0] !== 'details' ? ' is-disabled' : '' ) + '" onclick="return eccountry.tab( \'' + t[0] + '\' );">' + t[1] + '</a>'; } ).join( '' ) + '</div><div class="ecdrawer-b">';
			h += '<div class="ecdrawer-pane" data-pane="details"><div class="ecdv2-grid"><div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label">' + esc( T( 'Name' ) ) + '</label><input type="text" class="ecv2-input" id="eccnt_f_name" value="' + esc( c.name ) + '" data-track></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'ISO 2' ) ) + '</label><input type="text" class="ecv2-input ecv2-mono" id="eccnt_f_iso2" maxlength="2" value="' + esc( c.iso2 ) + '" data-track></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'ISO 3' ) ) + '</label><input type="text" class="ecv2-input ecv2-mono" id="eccnt_f_iso3" maxlength="3" value="' + esc( c.iso3 ) + '" data-track></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Sort order' ) ) + '</label><input type="number" class="ecv2-input" id="eccnt_f_sort" value="' + c.sort + '" data-track></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Ship to' ) ) + '</label><label class="ecos-toggle-row" style="padding:4px 0"><span class="ecv2-toggle"><input type="checkbox" id="eccnt_f_ship"' + ( c.ship ? ' checked' : '' ) + ' data-track><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t">' + esc( T( 'Customers can ship here' ) ) + '</span></span></label></div></div>' + ( ! isNew && c.usage.zones.length === 0 && ! c.ship ? '<div class="ecos-note info" style="margin-top:12px"><span class="dashicons dashicons-info-outline"></span><div>' + esc( T( 'Ship-to is off: this country is hidden from checkout address menus.' ) ) + '</div></div>' : '' ) + '</div>';
			/* 6.0.5: whether shoppers must choose one of the regions ( wp_easycart_regions ); '' = WP EasyCart decides. */
			var rq = c.region || { ready: false }, rqAuto = esc( rq.default_required ? T( 'Automatic: required' ) : T( 'Automatic: optional' ) );
			h += '<div class="ecdrawer-pane" data-pane="regions">' + ( rq.ready ? '<div class="ecdv2-field ecdv2-field-full eccnt-region-req"><label class="ecdv2-label" for="eccnt_f_region_req">' + esc( T( 'Region at checkout' ) ) + '</label><select class="ecv2-select" id="eccnt_f_region_req" data-track><option value=""' + ( '' === rq.value ? ' selected' : '' ) + '>' + rqAuto + '</option><option value="1"' + ( '1' === rq.value ? ' selected' : '' ) + '>' + esc( T( 'Required' ) ) + '</option><option value="0"' + ( '0' === rq.value ? ' selected' : '' ) + '>' + esc( T( 'Optional' ) ) + '</option></select><span class="ecdv2-field-desc">' + esc( T( 'Whether shoppers must pick one of these regions in their address. UK, German and New Zealand addresses use none, so there it is optional unless you choose Required.' ) ) + '</span></div>' : '' ) + '<div class="eccnt-regions" id="eccnt_regions"></div><div class="eccnt-region-form" id="eccnt_region_form"><div class="eccnt-rf-title" id="eccnt_rf_title">' + esc( T( 'Add region' ) ) + '</div><div class="ecdv2-grid"><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Name' ) ) + '</label><input type="text" class="ecv2-input" id="eccnt_r_name"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Code' ) ) + '</label><input type="text" class="ecv2-input ecv2-mono" id="eccnt_r_code" maxlength="10"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Group' ) ) + ' <span class="ecdv2-label-hint">' + esc( T( 'optional' ) ) + '</span></label><input type="text" class="ecv2-input" id="eccnt_r_group" placeholder="' + ea( T( 'e.g. Territory' ) ) + '"></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Sort order' ) ) + '</label><input type="number" class="ecv2-input" id="eccnt_r_sort" value="0"></div><div class="ecdv2-field ecdv2-field-full"><label class="ecos-toggle-row" style="padding:0"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" id="eccnt_r_ship" checked><span class="ecv2-toggle-slider"></span></span><span>' + esc( T( 'Customers can ship here' ) ) + '</span></label></div></div><div class="eccnt-rf-acts"><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="eccountry.region_reset();">' + esc( T( 'Clear' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" id="eccnt_r_save" onclick="eccountry.region_save();">' + esc( T( 'Add region' ) ) + '</button></div><input type="hidden" id="eccnt_r_id" value="0"></div></div>';
			h += '<div class="ecdrawer-pane" data-pane="tax"><div class="ecdv2-grid"><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'VAT rate %%' ) ) + '</label><input type="number" step="0.01" min="0" max="100" class="ecv2-input" id="eccnt_f_vat" value="' + ( c.vat || '' ) + '" data-track><span class="ecdv2-field-desc">' + esc( T( 'Used by the VAT tax mode. Leave blank for no VAT.' ) ) + '</span></div><div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'Stripe tax rate' ) ) + '</label><input type="text" class="ecv2-input ecv2-mono" value="' + esc( c.stripe ) + '" disabled><span class="ecdv2-field-desc">' + esc( c.stripe ? T( 'Created by Stripe tax sync.' ) : T( 'Set automatically when Stripe tax sync runs.' ) ) + '</span></div><div class="ecdv2-field ecdv2-field-full"><label class="ecos-toggle-row" style="padding:0"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" id="eccnt_f_b2b"' + ( c.vat_b2b ? ' checked' : '' ) + ' data-track><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t">' + esc( T( 'Apply to VAT-registered businesses' ) ) + '</span><small>' + esc( T( 'Businesses with a VAT number pay this rate too. Off, a business abroad pays the business rate ( Settings › Taxes › VAT ).' ) ) + '</small></span></label></div></div></div>';
			var u = c.usage || { regions: 0, zones: [], tax_rules: 0, orders: 0 };
			h += '<div class="ecdrawer-pane" data-pane="usage"><div class="eccnt-usage"><div><b>' + u.regions + '</b><span>' + esc( T( 'regions' ) ) + '</span></div><div><b>' + u.zones.length + '</b><span>' + esc( T( 'shipping zones' ) ) + ( u.zones.length ? ': ' + esc( u.zones.join( ', ' ) ) : '' ) + '</span></div><div><b>' + u.tax_rules + '</b><span>' + esc( T( 'tax rules' ) ) + '</span></div><div><b>' + u.orders + '</b><span>' + esc( T( 'past orders' ) ) + '</span></div></div><div class="ecos-note info" style="margin-top:12px"><span class="dashicons dashicons-info-outline"></span><div>' + esc( T( 'Deleting a country removes its regions and its shipping-zone memberships. Tax rules and past orders are never touched — they keep the ISO code. If you just want to stop shipping here, turn Ship-to off instead.' ) ) + '</div></div></div>';
			h += '</div><div class="ecdrawer-f">' + ( isNew ? '' : '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-danger-ghost" onclick="eccountry.delete_ids( [' + c.id + '] );">' + esc( T( 'Delete…' ) ) + '</button>' ) + '<span class="ecos-grow"></span><button type="button" class="ecv2-btn" onclick="eccountry.close();">' + esc( T( 'Close' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="eccnt_save" onclick="eccountry.save();">' + esc( isNew ? T( 'Create country' ) : T( 'Save' ) ) + '</button></div></aside>';
			return h;
		}
		function render_regions() {
			var $r = $( '#eccnt_regions' ).empty();
			if ( ! cur.regions.length ) { $r.append( '<div class="ecos-empty">' + esc( T( 'No regions. Add states, provinces or territories below if this country needs them at checkout.' ) ) + '</div>' ); return; }
			$r.append( '<div class="eccnt-rrow eccnt-rhead"><span>' + esc( T( 'Region' ) ) + '</span><span>' + esc( T( 'Code' ) ) + '</span><span>' + esc( T( 'Ship to' ) ) + '</span><span></span></div>' );
			$.each( cur.regions, function( i, s ) { $r.append( '<div class="eccnt-rrow" data-id="' + s.id + '"><span><b>' + esc( s.name ) + '</b>' + ( s.group ? '<span class="ecv2-sub">' + esc( s.group ) + '</span>' : '' ) + '</span><span class="ecv2-mono">' + esc( s.code ) + '</span><span><label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="eccnt-ship" data-kind="region" data-id="' + s.id + '"' + ( s.ship ? ' checked' : '' ) + '><span class="ecv2-toggle-slider"></span></label></span><span class="eccnt-racts"><a href="#" onclick="return eccountry.region_edit( ' + s.id + ' );">' + esc( T( 'edit' ) ) + '</a> · <a href="#" class="is-danger" onclick="return eccountry.region_delete( ' + s.id + ' );">' + esc( T( 'delete' ) ) + '</a></span></div>' ); } );
		}
		$( document ).on( 'keydown', '#eccnt_regions .eccnt-rrow.is-confirming', function( e ) { if ( 'Escape' === e.key ) { e.preventDefault(); e.stopImmediatePropagation(); eccountry.region_delete_cancel( $( this ).data( 'id' ) ); } } );
		function open_with( c, tab, region_id ) {
			cur = c; dirty = false; $( '.ecdrawer, .ecdrawer-backdrop' ).remove();
			$( 'body' ).append( drawer_html( c, tab || 'details' ) ).addClass( 'ecdrawer-open' );
			if ( c.id ) { render_regions(); }
			eccountry.tab( tab || 'details', true );
			if ( region_id ) { eccountry.region_edit( region_id ); }
			$( '.ecdrawer' ).on( 'input change', '[data-track]', function() { dirty = true; $( '#eccnt_save' ).addClass( 'is-dirty' ); } );
			$( '.ecdrawer-backdrop' ).on( 'click', eccountry.close );
			setTimeout( function() { $( '#eccnt_f_name' ).trigger( 'focus' ); }, 50 );
		}
		/* Swap ( or add ) a country row + its region sub-rows using server-rendered markup, keeping the
		   expanded state and bulk checkbox. A new country goes to the top of the current page. */
		function replace_row( id, html, is_new ) {
			var $old = $( 'tr.eccnt-row[data-id="' + id + '"]' ), $kids = $( 'tr.eccnt-region[data-country="' + id + '"]' ), open = $kids.length && $kids.first().is( ':visible' ), checked = $old.find( '.ecv2-row-check' ).is( ':checked' );
			var $new = $( $.parseHTML( html ) ).filter( 'tr' );
			if ( $old.length ) { $kids.remove(); $old.replaceWith( $new ); }
			else { var $tb = $( '#ec_admin_country_list_v2 tbody' ); $tb.find( '.ecv2-empty-state' ).closest( 'tr' ).remove(); $tb.prepend( $new ); }
			var $row = $( 'tr.eccnt-row[data-id="' + id + '"]' );
			if ( checked ) { $row.find( '.ecv2-row-check' ).prop( 'checked', true ); }
			if ( open && $( 'tr.eccnt-region[data-country="' + id + '"]' ).length ) { eccountry.toggle_tree( $row.find( '.eccnt-tw' )[0] ); }
			if ( is_new ) { $row.addClass( 'is-highlight' ); bump_count( 1 ); }
			return $row;
		}
		function bump_count( delta ) { var $c = $( '.ecv2-record-count' ).first(), m = /^\s*(\d+)/.exec( $c.text() ); if ( ! m ) { return; } var n = parseInt( m[1], 10 ) + delta; $c.text( TN( '%d Country', '%d Countries', n ) ); }
		/* 6.0.1: the stat tiles above the list ( "With regions" and friends ) follow a region or ship-to change. */
		function update_stats( stats ) {
			if ( ! stats ) { return; }
			$.each( stats, function( key, value ) {
				var $card = $( '.ecv2-stat-card[data-stat-key="' + key + '"]' );
				if ( ! $card.length ) { return; }
				$card.find( '.ecv2-stat-value' ).text( value );
				$card.toggleClass( 'ecv2-stat-zero', 0 === parseInt( value, 10 ) );
			} );
		}
		function after_region_change( d ) {
			cur.regions = d.country.regions; cur.usage = d.country.usage; render_regions(); eccountry.region_reset();
			$( '.ecdrawer-tabs a[data-tab="regions"]' ).text( T( 'Regions' ) + ' · ' + cur.regions.length );
			if ( d.row ) { replace_row( cur.id, d.row, false ); }
			update_stats( d.stats );
		}
		window.eccountry = {
			open: function( id, tab, region_id ) { if ( ! id ) { open_with( { id: 0, name: '', iso2: '', iso3: '', sort: 0, ship: true, vat: 0, vat_b2b: false, stripe: '', regions: [], usage: { regions: 0, zones: [], tax_rules: 0, orders: 0 } }, 'details' ); return false; } post( 'ecv2_country_get', nonce, { id: id }, function( c ) { open_with( c, tab, region_id ); } ); return false; },
			open_row: function( link, tab ) { return eccountry.open( $( link ).closest( 'tr' ).data( 'id' ), tab ); },
			close: function() { if ( dirty && ! window.confirm( T( 'Discard unsaved changes?' ) ) ) { return; } $( '.ecdrawer, .ecdrawer-backdrop' ).remove(); $( 'body' ).removeClass( 'ecdrawer-open' ); cur = null; },
			tab: function( name, silent ) { if ( $( '.ecdrawer-tabs a[data-tab="' + name + '"]' ).hasClass( 'is-disabled' ) ) { return false; } $( '.ecdrawer-tabs a' ).removeClass( 'is-on' ).filter( '[data-tab="' + name + '"]' ).addClass( 'is-on' ); $( '.ecdrawer-pane' ).hide().filter( '[data-pane="' + name + '"]' ).show(); return false; },
			save: function() {
				var data = { name: $( '#eccnt_f_name' ).val(), iso2: $( '#eccnt_f_iso2' ).val(), iso3: $( '#eccnt_f_iso3' ).val(), sort: $( '#eccnt_f_sort' ).val(), ship: $( '#eccnt_f_ship' ).is( ':checked' ) ? 1 : 0, vat: $( '#eccnt_f_vat' ).val(), vat_b2b: $( '#eccnt_f_b2b' ).is( ':checked' ) ? 1 : 0 };
				if ( $( '#eccnt_f_region_req' ).length ) { data.region_required = String( $( '#eccnt_f_region_req' ).val() ); }
				$( '#eccnt_save' ).prop( 'disabled', true ); $( '.ecdrawer .is-invalid' ).removeClass( 'is-invalid' );
				post( 'ecv2_country_save', nonce, { id: cur.id, data: JSON.stringify( data ) }, function( d ) {
					dirty = false; var wasNew = ! cur.id; cur = d.country;
					if ( d.row ) { replace_row( cur.id, d.row, wasNew ); }
					else { var $tr = $( 'tr.eccnt-row[data-id="' + cur.id + '"]' ); $tr.find( '.ecv2-title-link' ).text( cur.name ); $tr.find( '.eccnt-ship' ).prop( 'checked', cur.ship ); $tr.toggleClass( 'is-off', ! cur.ship ); }
					eccountry.close();
					ecv2_toast( d.message, 'success' );
				}, function( m ) { $( '#eccnt_save' ).prop( 'disabled', false ); ecv2_toast( m, 'error' ); } );
			},
			region_reset: function() { $( '#eccnt_r_id' ).val( 0 ); $( '#eccnt_r_name, #eccnt_r_code, #eccnt_r_group' ).val( '' ); $( '#eccnt_r_sort' ).val( 0 ); $( '#eccnt_r_ship' ).prop( 'checked', true ); $( '#eccnt_rf_title' ).text( T( 'Add region' ) ); $( '#eccnt_r_save' ).text( T( 'Add region' ) ); },
			region_edit: function( id ) { var s = $.grep( cur.regions, function( r ) { return r.id === id; } )[0]; if ( ! s ) { return false; } eccountry.tab( 'regions' ); $( '#eccnt_r_id' ).val( s.id ); $( '#eccnt_r_name' ).val( s.name ); $( '#eccnt_r_code' ).val( s.code ); $( '#eccnt_r_group' ).val( s.group ); $( '#eccnt_r_sort' ).val( s.sort ); $( '#eccnt_r_ship' ).prop( 'checked', s.ship ); $( '#eccnt_rf_title' ).text( T( 'Edit %s', s.name ) ); $( '#eccnt_r_save' ).text( T( 'Save region' ) ); $( '#eccnt_r_name' ).trigger( 'focus' ); return false; },
			region_save: function() { var data = { name: $( '#eccnt_r_name' ).val(), code: $( '#eccnt_r_code' ).val(), group: $( '#eccnt_r_group' ).val(), sort: $( '#eccnt_r_sort' ).val(), ship: $( '#eccnt_r_ship' ).is( ':checked' ) ? 1 : 0 }, was_edit = parseInt( $( '#eccnt_r_id' ).val(), 10 ) > 0; $( '#eccnt_r_save' ).prop( 'disabled', true ); post( 'ecv2_region_save', nonce, { country_id: cur.id, id: $( '#eccnt_r_id' ).val(), data: JSON.stringify( data ) }, function( d ) { $( '#eccnt_r_save' ).prop( 'disabled', false ); after_region_change( d ); ecv2_toast( was_edit ? T( 'Region saved.' ) : T( 'Region added.' ), 'success' ); }, function( m ) { $( '#eccnt_r_save' ).prop( 'disabled', false ); ecv2_toast( m, 'error' ); } ); },
			/* Two-step delete inside the row ( a dialog would open behind the drawer ): "delete" asks, the row's Delete button does it, Cancel or Escape backs out. */
			region_delete: function( id ) {
				var $row = $( '#eccnt_regions .eccnt-rrow[data-id="' + id + '"]' );
				if ( ! $row.length ) { return false; }
				$( '#eccnt_regions .eccnt-rrow.is-confirming' ).not( $row ).each( function() { eccountry.region_delete_cancel( $( this ).data( 'id' ) ); } );
				if ( $row.hasClass( 'is-confirming' ) ) { return false; }
				$row.addClass( 'is-confirming' ).find( '.eccnt-racts' ).hide().after(
					'<span class="eccnt-rconfirm" role="group" aria-label="' + ea( T( 'Confirm delete' ) ) + '"><span class="eccnt-rconfirm-q">' + esc( T( 'Delete?' ) ) + '</span>' +
					'<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-danger eccnt-rconfirm-go" onclick="return eccountry.region_delete_go( ' + id + ', this );">' + esc( T( 'Delete' ) ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="return eccountry.region_delete_cancel( ' + id + ' );">' + esc( T( 'Cancel' ) ) + '</button></span>' +
					'<span class="eccnt-rconfirm-note">' + esc( T( 'Removed from checkout menus and from any shipping zone that lists it. Past orders keep the code.' ) ) + '</span>'
				);
				$row.find( '.eccnt-rconfirm-go' ).trigger( 'focus' );
				return false;
			},
			region_delete_cancel: function( id ) {
				var $row = $( '#eccnt_regions .eccnt-rrow[data-id="' + id + '"]' );
				$row.removeClass( 'is-confirming' ).find( '.eccnt-rconfirm, .eccnt-rconfirm-note' ).remove();
				$row.find( '.eccnt-racts' ).show().find( '.is-danger' ).trigger( 'focus' );
				return false;
			},
			region_delete_go: function( id, btn ) {
				$( btn ).prop( 'disabled', true ).siblings( 'button' ).prop( 'disabled', true );
				post( 'ecv2_region_delete', nonce, { id: id }, function( d ) { after_region_change( d ); ecv2_toast( T( 'Region deleted.' ), 'success' ); }, function( m ) { $( btn ).prop( 'disabled', false ).siblings( 'button' ).prop( 'disabled', false ); ecv2_toast( m, 'error' ); } );
				return false;
			},
			/* 6.0.1: delete a region straight from the expanded list row, with the same two-step confirm as the drawer. */
			/* 6.0.1: the row menu asks in the shared V2 dialog. The old in-row Delete? / Cancel strip was
			   written for a text link in a wide column; inside the narrow Actions cell it had nowhere to go. */
			region_row_delete: function( id, el ) {
				var $row = $( el ).closest( 'tr' );
				if ( window.ecv2_close_row_menus ) { ecv2_close_row_menus(); }
				var ask = window.ecv2_show_confirm
					? ecv2_show_confirm( T( 'Delete this region?' ), T( 'It stops being offered at checkout. Shipping rates and tax rates that name it are not changed.' ) )
					: Promise.resolve( window.confirm( T( 'Delete this region?' ) ) );
				ask.then( function( go ) {
					if ( go ) { eccountry.region_row_go( id, $row ); }
				} );
				return false;
			},
			region_row_go: function( id, btn ) {
				var $row = ( btn && btn.jquery ) ? btn : $( btn ).closest( 'tr' ), country_id = $row.data( 'country' );
				post( 'ecv2_region_delete', nonce, { id: id }, function( d ) {
					$row.remove();
					if ( d.row ) { replace_row( country_id, d.row, false ); }
					update_stats( d.stats );
					/* the country row is re-rendered closed: keep its regions showing if any are left */
					var $left = $( 'tr.eccnt-region[data-country="' + country_id + '"]' ).not( '.eccnt-region-add' );
					if ( ! $left.length ) { $( 'tr.eccnt-region[data-country="' + country_id + '"]' ).remove(); }
					ecv2_toast( T( 'Region deleted.' ), 'success' );
				}, function( m ) {
					ecv2_toast( m, 'error' );
				} );
				return false;
			},
			toggle_tree: function( el ) { var $tr = $( el ).closest( 'tr' ), id = $tr.data( 'id' ), $kids = $( 'tr.eccnt-region[data-country="' + id + '"]' ), open = $kids.first().is( ':visible' ); $kids.toggle( ! open ); $tr.find( '.eccnt-tw' ).text( open ? '▸' : '▾' ).attr( 'aria-expanded', open ? 'false' : 'true' ); return false; },
			bulk: function( op, ids ) {
				if ( op === 'vat' ) { ecv2_catalog_modal( 'ecv2-vat', esc( TN( 'Set VAT rate for %d country', 'Set VAT rate for %d countries', ids.length ) ), '<div class="ecdv2-field"><label class="ecdv2-label">' + esc( T( 'VAT rate %%' ) ) + '</label><input type="number" step="0.01" min="0" max="100" class="ecv2-input" id="ecv2_vat_rate" value="20"><span class="ecdv2-field-desc">' + esc( T( 'Enter 0 to clear.' ) ) + '</span></div>', '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_vat_go">' + esc( T( 'Set rate' ) ) + '</button>', 'ecv2-modal-sm' ); $( '#ecv2_vat_go' ).on( 'click', function() { post( 'ecv2_country_bulk', nonce, { ids: ids, op: 'vat', rate: $( '#ecv2_vat_rate' ).val() }, function( d ) { ecv2_catalog_close_modal(); ecv2_toast( d.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } ); } ); return; }
				if ( op === 'enable' || op === 'disable' ) { var regions = 0; ecv2_show_confirm( op === 'enable' ? TN( 'Enable ship-to for %d country?', 'Enable ship-to for %d countries?', ids.length ) : TN( 'Disable ship-to for %d country?', 'Disable ship-to for %d countries?', ids.length ), op === 'enable' ? T( 'Their regions are left as they are.' ) : T( 'Their regions are also disabled.' ) ).then( function( ok ) { if ( ! ok ) { return; } post( 'ecv2_country_bulk', nonce, { ids: ids, op: op, regions_too: op === 'disable' ? 1 : 0 }, function( d ) { ecv2_toast( d.message, 'success' ); $.each( ids, function( i, id ) { var $tr = $( 'tr.eccnt-row[data-id="' + id + '"]' ); $tr.find( '.eccnt-ship' ).prop( 'checked', op === 'enable' ); $tr.toggleClass( 'is-off', op !== 'enable' ); } ); } ); } ); return; }
				if ( op === 'delete' ) { eccountry.delete_ids( ids ); }
			},
			delete_row: function( link ) { eccountry.delete_ids( [ $( link ).closest( 'tr' ).data( 'id' ) ] ); return false; },
			delete_ids: function( ids ) {
				post( 'ecv2_country_delete_impact', nonce, { ids: ids }, function( d ) {
					var t = d.totals, one = d.items.length === 1, title = esc( one ? T( 'Delete %s?', d.items[0].name ) : TN( 'Delete %d country?', 'Delete %d countries?', d.items.length ) );
					var body = '';
					if ( t.ship_on ) { body += '<div class="ecos-note" style="margin:0 0 12px"><span class="dashicons dashicons-warning"></span><div><b>' + esc( one ? T( 'This country is enabled for shipping.' ) : TN( '%d of these is enabled for shipping.', '%d of these are enabled for shipping.', t.ship_on ) ) + '</b> ' + T( 'If you only want to stop shipping there, %1$sdisable ship-to instead%2$s — it keeps regions, zones and tax rules.', '<a href="#" id="ecv2_del_disable">', '</a>' ) + '</div></div>'; }
					body += '<div class="ecsd-impacts"><div class="ecsd-impact ' + ( t.regions ? 'is-warn' : 'is-info' ) + '">' + TN( '<b>%d</b> region will be deleted.', '<b>%d</b> regions will be deleted.', t.regions ) + '</div><div class="ecsd-impact ' + ( t.zones.length ? 'is-warn' : 'is-info' ) + '">' + ( t.zones.length ? TN( '<b>%1$d</b> shipping zone loses this location: %2$s', '<b>%1$d</b> shipping zones lose this location: %2$s', t.zones.length, esc( t.zones.join( ', ' ) ) ) : T( '<b>0</b> shipping zones lose this location.' ) ) + '</div><div class="ecsd-impact is-info">' + T( '<b>%1$d</b> tax rules and <b>%2$d</b> past orders keep the ISO code and are not changed.', t.tax_rules, t.orders ) + '</div></div><div class="ecos-note info" style="margin-top:12px"><span class="dashicons dashicons-undo"></span><div>' + esc( T( 'Undo is offered for 15 minutes and restores regions and zone memberships too.' ) ) + '</div></div>';
					ecv2_catalog_modal( 'ecv2-delcnt', title, body, '<button type="button" class="ecv2-btn" data-close>' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-danger" id="ecv2_delcnt_go">' + esc( T( 'Delete' ) ) + '</button>' );
					$( '#ecv2_del_disable' ).on( 'click', function( e ) { e.preventDefault(); post( 'ecv2_country_bulk', nonce, { ids: ids, op: 'disable', regions_too: 1 }, function( r ) { ecv2_catalog_close_modal(); ecv2_toast( r.message, 'success' ); setTimeout( function() { window.location.reload(); }, 600 ); } ); } );
					$( '#ecv2_delcnt_go' ).on( 'click', function() { $( this ).prop( 'disabled', true ); post( 'ecv2_country_delete', nonce, { ids: ids }, function( r ) { ecv2_catalog_close_modal(); $( '.ecdrawer, .ecdrawer-backdrop' ).remove(); $( 'body' ).removeClass( 'ecdrawer-open' ); $.each( ids, function( i, id ) { $( 'tr.eccnt-row[data-id="' + id + '"], tr.eccnt-region[data-country="' + id + '"]' ).fadeOut( 200, function() { $( this ).remove(); } ); } ); undo_toast( r.message, r.undo ); } ); } );
				} );
			},
			/* 6.0.5: Update UK regions ( wp_easycart_admin_country_table::uk_regions_notice() ): the words come with the button. */
			uk_regions: function( btn ) {
				var $btn = $( btn );
				if ( $btn.hasClass( 'is-busy' ) ) { return false; }
				var ask = window.ecv2_show_confirm ? ecv2_show_confirm( $btn.attr( 'data-title' ), $btn.attr( 'data-body' ) ) : Promise.resolve( window.confirm( $btn.attr( 'data-title' ) ) );
				ask.then( function( ok ) {
					if ( ! ok ) { return; }
					busy( $btn, true, $btn.attr( 'data-busy' ) || '' );
					post( 'ecv2_country_uk_regions', nonce, {}, function( d ) {
						try { sessionStorage.setItem( 'eccnt_flash', JSON.stringify( { message: d.message, type: 'success' } ) ); } catch ( e ) {}
						window.location.reload();
					}, function( m ) { busy( $btn, false ); ecv2_toast( m, 'error' ); }, function() { busy( $btn, false ); ecv2_toast( T( 'Something went wrong.' ), 'error' ); } );
				} );
				return false;
			},
			/* Restore defaults: preview what is missing, confirm, then run with a working state that blocks closing
			   the dialog and double submits until the server answers. The result toast survives the reload. */
			restore_defaults: function( btn ) {
				var $btn = $( btn || '#eccnt_restore_btn' );
				if ( restoring || $btn.hasClass( 'is-busy' ) ) { return false; }
				busy( $btn, true, T( 'Checking…' ) );
				post( 'ecv2_country_restore_preview', nonce, {}, function( p ) {
					busy( $btn, false );
					var set = T( 'The default set has %1$d countries, with %2$d regions across %3$d of them.', p.default_countries, p.default_regions, p.default_region_countries );
					if ( ! p.countries && ! p.regions ) { ecv2_toast( T( 'Nothing to restore: every default country and region is already in your list.' ), 'info' ); return; }
					var body = '<div class="ecsd-impacts eccnt-restore-impacts"><div class="ecsd-impact' + ( p.countries ? ' is-add' : '' ) + '">' + TN( '<b>%d</b> country will be added.', '<b>%d</b> countries will be added.', p.countries ) + '</div><div class="ecsd-impact' + ( p.regions ? ' is-add' : '' ) + '">' + TN( '<b>%d</b> region will be added.', '<b>%d</b> regions will be added.', p.regions ) + '</div></div>' +
						'<div class="ecos-note ecos-note-brand eccnt-restore-note"><span class="dashicons dashicons-info-outline"></span><div>' + esc( T( 'New entries start with ship-to off, so checkout does not change until you turn them on. Nothing you already have is changed or removed.' ) ) + ' ' + esc( set ) + '</div></div>' +
						'<div class="eccnt-restore-progress" id="eccnt_restore_progress" hidden><span class="eccnt-spinner" aria-hidden="true"></span><span id="eccnt_restore_status" role="status" aria-live="polite">' + esc( T( 'Adding countries and regions… This can take a little while on some hosts. Keep this page open.' ) ) + '</span></div>';
					var $m = ecv2_catalog_modal( 'ecv2-cntrestore', esc( T( 'Restore default countries & regions?' ) ), body, '<button type="button" class="ecv2-btn" data-close id="eccnt_restore_cancel">' + esc( T( 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="eccnt_restore_go">' + esc( T( 'Restore defaults' ) ) + '</button>', { size: 'sm' } );
					$( '#eccnt_restore_go' ).on( 'click', function() {
						if ( restoring ) { return; }
						restoring = true;
						/* lock the dialog: no Esc, no backdrop click, no Cancel / close while the request runs */
						$m.off( 'click' ).addClass( 'is-working' ); $( document ).off( 'keydown.ecv2modal' ); $m.find( '[data-close]' ).prop( 'disabled', true ).removeAttr( 'data-close' );
						var esc_block = function( e ) { if ( 'Escape' === e.key ) { e.stopPropagation(); e.preventDefault(); } }; /* catalog-v2.js also closes any modal on Escape from a document handler; a window capture listener runs first */
						window.addEventListener( 'keydown', esc_block, true );
						busy( $( this ), true, T( 'Restoring…' ) ); busy( $btn, true, T( 'Restoring…' ) );
						$m.find( '.eccnt-restore-impacts, .eccnt-restore-note' ).hide(); $( '#eccnt_restore_progress' ).prop( 'hidden', false );
						$( window ).on( 'beforeunload.eccntrestore', function() { return T( 'Countries and regions are still being restored.' ); } );
						var done = function() { restoring = false; window.removeEventListener( 'keydown', esc_block, true ); $( window ).off( 'beforeunload.eccntrestore' ); };
						post( 'ecv2_country_restore_defaults', nonce, {}, function( d ) {
							done();
							$( '#eccnt_restore_progress' ).addClass( 'is-done' ).find( '.eccnt-spinner' ).remove(); $( '#eccnt_restore_status' ).text( d.message + ' ' + T( 'Reloading…' ) );
							try { sessionStorage.setItem( 'eccnt_flash', JSON.stringify( { message: d.message, type: d.failed ? 'error' : 'success' } ) ); } catch ( e ) {}
							setTimeout( function() { window.location.reload(); }, 1200 );
						}, function( m ) {
							done(); ecv2_catalog_close_modal(); busy( $btn, false ); ecv2_toast( m, 'error' );
						}, function() {
							done(); ecv2_catalog_close_modal(); busy( $btn, false ); ecv2_toast( T( 'The restore did not finish (the request timed out or was interrupted). Anything already added is kept; run Restore defaults again to add the rest.' ), 'error' );
						} );
					} );
				}, function( m ) { busy( $btn, false ); ecv2_toast( m, 'error' ); }, function() { busy( $btn, false ); ecv2_toast( T( 'Something went wrong.' ), 'error' ); } );
				return false;
			}
		};
		var restoring = false;
		function busy( $b, on, label ) {
			if ( ! $b.length ) { return; }
			if ( on ) {
				if ( ! $b.data( 'idle-html' ) ) { $b.data( 'idle-html', $b.html() ); }
				$b.addClass( 'is-busy' ).prop( 'disabled', true ).attr( 'aria-busy', 'true' ).html( '<span class="eccnt-spinner" aria-hidden="true"></span> ' + esc( label ) );
			} else if ( $b.data( 'idle-html' ) ) {
				$b.removeClass( 'is-busy' ).prop( 'disabled', false ).removeAttr( 'aria-busy' ).html( $b.data( 'idle-html' ) ).removeData( 'idle-html' );
			}
		}
		try { var cflash = JSON.parse( sessionStorage.getItem( 'eccnt_flash' ) || 'null' ); if ( cflash && cflash.message ) { sessionStorage.removeItem( 'eccnt_flash' ); setTimeout( function() { ecv2_toast( cflash.message, cflash.type || 'success' ); }, 300 ); } } catch ( e ) {}
		var q = new URLSearchParams( window.location.search );
		if ( q.get( 'open_country' ) ) { eccountry.open( parseInt( q.get( 'open_country' ), 10 ), q.get( 'open_tab' ) || 'details', parseInt( q.get( 'open_region' ) || '0', 10 ) ); }
		else if ( q.get( 'ec_admin_form_action' ) === 'add-new' ) { eccountry.open( 0 ); }
	} )();

} );
