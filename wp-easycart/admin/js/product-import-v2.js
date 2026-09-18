/**
 * WP EasyCart Admin — Products CSV import / export panel ( V2 ).
 *
 * Drives the panel printed by admin/template/products/products/product-import-v2.php:
 *   Import  choose file → upload ( ecv2_product_import_upload, server-side preview ) → confirm → chunked
 *           ecv2_product_import_run ( the existing run_importer() loop: offset + line ) → results
 *   Export  Export all ( nonced link ) / Export selected ( ecv2_product_bulk_export_prepare with the ticked rows )
 *   Help    docs link + sample CSV ( ecv2_product_import_sample )
 *
 * Settings and strings come from `ecv2_product_import` ( wp_localize_script ). Header buttons carry
 * data-ecv2pi-open="import|export|help".
 *
 * @since 6.0.0
 */
( function( $ ) {
	'use strict';

	var cfg = window.ecv2_product_import || {};
	var i18n = cfg.i18n || {};
	var state = { token: '', analysis: null, file: '', running: false, xhr: null };

	function t( key, fallback ) { return i18n[ key ] || fallback || key; }
	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 ), n = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function( m, pos ) { var i = pos ? parseInt( pos, 10 ) - 1 : n++; return typeof args[ i ] === 'undefined' ? '' : args[ i ]; } );
	}
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function num( n ) { n = Number( n ) || 0; return n.toLocaleString ? n.toLocaleString() : String( n ); }
	function toast( msg, type ) { if ( typeof window.ecv2_toast === 'function' ) { window.ecv2_toast( msg, type || 'error' ); } else { window.alert( msg ); } }
	function post( action, data, ok, fail ) {
		return $.post( cfg.ajax_url, $.extend( { action: action, nonce: cfg.nonce }, data || {} ), null, 'json' )
			.done( function( r ) {
				if ( ! r || ! r.success ) { ( fail || function( m ) { toast( m ); } )( ( r && r.data && r.data.message ) || t( 'generic_error' ), r && r.data ); return; }
				if ( ok ) { ok( r.data ); }
			} )
			.fail( function() { ( fail || function( m ) { toast( m ); } )( t( 'generic_error' ) ); } );
	}
	function download( text, filename ) {
		var a = document.createElement( 'a' );
		a.href = URL.createObjectURL( new Blob( [ text ], { type: 'text/csv' } ) );
		a.download = filename;
		document.body.appendChild( a ); a.click(); document.body.removeChild( a );
	}

	/* ---------------------------------------------------------------- modal + tabs */
	var $modal;
	function open( tab ) {
		$modal = $( '#ecv2pi_modal' );
		if ( ! $modal.length ) { return; }
		$modal.show().attr( 'aria-hidden', 'false' );
		$( document ).on( 'keydown.ecv2pi', function( e ) { if ( e.key === 'Escape' ) { close(); } } );
		show_tab( tab || 'import' );
	}
	function close() {
		if ( state.running ) { return; }
		if ( state.xhr ) { try { state.xhr.abort(); } catch ( e ) {} state.xhr = null; }
		$( document ).off( 'keydown.ecv2pi' );
		if ( $modal ) { $modal.hide().attr( 'aria-hidden', 'true' ); }
		discard();
		reset_import();
	}
	function show_tab( tab ) {
		$modal.find( '.ecv2pi-tab' ).each( function() { var on = $( this ).data( 'ecv2piTab' ) === tab; $( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on ? 'true' : 'false' ); } );
		$modal.find( '.ecv2pi-pane' ).each( function() { $( this ).prop( 'hidden', $( this ).data( 'ecv2piPane' ) !== tab ); } );
		if ( tab === 'export' ) { refresh_selected(); }
		set_footer( tab === 'import' ? import_footer() : '<button type="button" class="ecv2-btn" data-ecv2pi-close>' + esc( t( 'close', 'Close' ) ) + '</button>', '' );
	}
	function set_footer( right, left ) { $( '#ecv2pi_foot' ).html( right ); $( '#ecv2pi_foot_left' ).html( left || '' ); }
	function step( name ) {
		$modal.find( '.ecv2pi-step' ).each( function() { $( this ).prop( 'hidden', $( this ).data( 'ecv2piStep' ) !== name ); } );
	}

	/* ---------------------------------------------------------------- import: pick + upload */
	function reset_import() {
		state.token = ''; state.analysis = null; state.file = '';
		$( '#ecv2pi_file' ).val( '' );
		$( '#ecv2pi_preview, #ecv2pi_done' ).empty();
		if ( $modal ) { step( 'pick' ); }
	}
	function import_footer() {
		if ( state.analysis ) { return preview_footer(); }
		return '<button type="button" class="ecv2-btn" data-ecv2pi-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button>';
	}
	function discard() {
		if ( ! state.token ) { return; }
		var token = state.token; state.token = '';
		$.post( cfg.ajax_url, { action: 'ecv2_product_import_discard', nonce: cfg.nonce, token: token } );
	}
	function pick( file ) {
		if ( ! file ) { return; }
		var ext = String( file.name || '' ).split( '.' ).pop().toLowerCase();
		if ( ext !== 'csv' && ext !== 'txt' ) { toast( t( 'not_csv' ) ); return; }
		if ( cfg.max_upload && file.size > cfg.max_upload ) { toast( fmt( t( 'too_large' ), cfg.max_upload_label ) ); return; }
		discard();
		state.file = file.name;
		step( 'busy' );
		set_footer( '<button type="button" class="ecv2-btn" data-ecv2pi-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button>' );
		$( '#ecv2pi_busy_text' ).text( fmt( t( 'uploading' ), 0 ) );
		$( '#ecv2pi_upload_bar_wrap' ).prop( 'hidden', false ); $( '#ecv2pi_upload_bar' ).css( 'width', '0%' );
		var fd = new FormData();
		fd.append( 'action', 'ecv2_product_import_upload' ); fd.append( 'nonce', cfg.nonce ); fd.append( 'file', file );
		state.xhr = $.ajax( {
			url: cfg.ajax_url, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
			xhr: function() {
				var x = $.ajaxSettings.xhr();
				if ( x.upload ) { x.upload.addEventListener( 'progress', function( e ) { if ( e.lengthComputable ) { var p = Math.round( e.loaded / e.total * 100 ); $( '#ecv2pi_upload_bar' ).css( 'width', p + '%' ); $( '#ecv2pi_busy_text' ).text( p >= 100 ? t( 'checking' ) : fmt( t( 'uploading' ), p ) ); } } ); }
				return x;
			}
		} ).done( function( r ) {
			state.xhr = null;
			if ( ! r || ! r.success ) { toast( ( r && r.data && r.data.message ) || t( 'generic_error' ) ); reset_import(); set_footer( import_footer() ); return; }
			state.token = r.data.token; state.analysis = r.data.analysis;
			render_preview();
		} ).fail( function( x ) {
			state.xhr = null;
			if ( x && x.statusText === 'abort' ) { return; }
			toast( x && x.status === 413 ? fmt( t( 'too_large' ), cfg.max_upload_label ) : t( 'generic_error' ) );
			reset_import(); set_footer( import_footer() );
		} );
	}

	/* ---------------------------------------------------------------- import: preview */
	function chip( action ) {
		if ( action === 'insert' ) { return 'ecv2-chip-green'; }
		if ( action === 'update' ) { return 'ecv2-chip-blue'; }
		if ( action === 'problem' ) { return 'ecv2-chip-amber'; }
		return 'ecv2-chip-gray';
	}
	function render_preview() {
		var a = state.analysis, tt = a.totals, h = '';
		h += '<div class="ecv2pi-file"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span><span class="ecv2pi-file-meta"><b title="' + esc( state.file ) + '">' + esc( state.file ) + '</b><span class="ecv2-sub">' + esc( fmt( t( 'rows' ), num( a.file.rows ) ) ) + ' · ' + esc( fmt( t( 'columns_ok' ), a.file.recognised ) + ( a.file.recognised < a.file.columns ? ' / ' + a.file.columns : '' ) ) + '</span></span><a href="#" id="ecv2pi_change">' + esc( t( 'change_file' ) ) + '</a></div>';
		h += '<div class="ecv2pi-totals"><div class="is-insert"><b>' + num( tt.insert ) + '</b><span>' + esc( t( 'new_products' ) ) + '</span></div><div class="is-update"><b>' + num( tt.update ) + '</b><span>' + esc( t( 'updates' ) ) + '</span></div><div class="' + ( tt.problem ? 'is-problem' : '' ) + '"><b>' + num( tt.problem ) + '</b><span>' + esc( t( 'problems' ) ) + '</span></div>' + ( tt.ignored ? '<div class="is-problem"><b>' + num( tt.ignored ) + '</b><span>' + esc( t( 'ignored' ) ) + '</span></div>' : '' ) + '</div>';
		if ( a.truncated ) { h += '<div class="ecv2-sub ecv2pi-sub-note">' + esc( fmt( t( 'scan_truncated' ), num( a.scanned ) ) ) + '</div>'; }
		if ( a.blocking.length ) {
			h += '<div class="ecos-note ecv2pi-note danger"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><div><b>' + esc( t( 'fix_first' ) ) + '</b><ul>';
			$.each( a.blocking, function( i, m ) { h += '<li>' + esc( m ) + '</li>'; } );
			h += '</ul></div></div>';
		}
		if ( a.warnings.length ) {
			h += '<div class="ecos-note ecv2pi-note"><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><b>' + esc( t( 'heads_up' ) ) + '</b><ul>';
			$.each( a.warnings, function( i, m ) { h += '<li>' + esc( m ) + '</li>'; } );
			h += '</ul></div></div>';
		}
		/* column map */
		h += '<details class="ecv2pi-columns"' + ( a.file.recognised < a.file.columns ? ' open' : '' ) + '><summary>' + esc( t( 'columns_title' ) ) + ' <span class="ecv2-sub">' + esc( a.file.recognised + ' / ' + a.file.columns ) + '</span></summary><div class="ecv2pi-colgrid">';
		$.each( a.columns, function( i, c ) {
			var cls = c.kind === 'key' ? 'is-key' : ( c.kind === 'unknown' || c.kind === 'empty' ? 'is-bad' : ( c.kind === 'side' ? 'is-side' : '' ) );
			h += '<span class="ecv2pi-col ' + cls + '" title="' + esc( c.kind === 'unknown' ? t( 'unknown_col' ) : c.name ) + '">' + ( c.name === '' ? '<i>—</i>' : esc( c.name ) ) + ( c.suggest ? '<small>' + esc( fmt( t( 'did_you_mean' ), c.suggest ) ) + '</small>' : '' ) + '</span>';
		} );
		h += '</div></details>';
		/* problems */
		if ( a.problems.length ) {
			h += '<div class="ecv2pi-problems"><b>' + esc( t( 'problem_rows' ) ) + '</b>';
			$.each( a.problems, function( i, p ) { h += '<div><span class="ecv2-chip ecv2-chip-amber">' + num( p.count ) + '</span><span>' + esc( p.reason ) + ' <span class="ecv2-sub">' + esc( t( 'row' ) + ' ' + p.rows.join( ', ' ) + ( p.count > p.rows.length ? ', …' : '' ) ) + '</span></span></div>'; } );
			h += '</div>';
		}
		/* sample */
		if ( a.sample.length ) {
			h += '<div class="ecv2pi-sample-wrap"><table class="ecv2pi-sample"><thead><tr><th class="ecv2pi-col-row">' + esc( t( 'row' ) ) + '</th><th>' + esc( t( 'sku' ) ) + '</th>' + ( a.has_title ? '<th>' + esc( t( 'title' ) ) + '</th>' : '' ) + ( a.has_price ? '<th class="ecv2pi-col-num">' + esc( t( 'price' ) ) + '</th>' : '' ) + '<th class="ecv2pi-col-action">' + esc( t( 'action' ) ) + '</th></tr></thead><tbody>';
			$.each( a.sample, function( i, r ) {
				var label = r.action === 'insert' ? t( 'insert' ) : ( r.action === 'update' ? fmt( t( 'update' ), r.product_id ) : ( r.action === 'problem' ? t( 'skip' ) : '' ) );
				h += '<tr><td class="ecv2pi-col-row">' + esc( r.row ) + '</td><td class="ecv2pi-col-sku">' + esc( r.sku ) + '</td>' + ( a.has_title ? '<td class="ecv2pi-col-title">' + esc( r.title ) + '</td>' : '' ) + ( a.has_price ? '<td class="ecv2pi-col-num">' + esc( r.price ) + '</td>' : '' ) + '<td class="ecv2pi-col-action">' + ( label ? '<span class="ecv2-chip ' + chip( r.action ) + '">' + esc( label ) + '</span>' : '' ) + ( r.reason ? '<span class="ecv2-sub">' + esc( r.reason ) + '</span>' : '' ) + '</td></tr>';
			} );
			h += '</tbody></table></div>';
			if ( a.file.rows > a.sample.length ) { h += '<div class="ecv2-sub ecv2pi-sub-note">' + esc( fmt( t( 'showing_first' ), a.sample.length, num( a.file.rows ) ) ) + '</div>'; }
		}
		$( '#ecv2pi_preview' ).html( h );
		step( 'preview' );
		set_footer( preview_footer() );
	}
	function preview_footer() {
		var a = state.analysis, tt = a.totals, parts = [];
		if ( tt.insert ) { parts.push( fmt( t( 'n_new' ), num( tt.insert ) ) ); }
		if ( tt.update ) { parts.push( tt.update === 1 ? t( 'one_update' ) : fmt( t( 'n_updates' ), num( tt.update ) ) ); }
		var label = a.can_run ? fmt( t( 'import_btn' ), parts.join( ' + ' ) ) : t( 'nothing' );
		return '<button type="button" class="ecv2-btn" data-ecv2pi-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2pi_go"' + ( a.can_run ? '' : ' disabled' ) + '>' + esc( label ) + '</button>';
	}

	/* ---------------------------------------------------------------- import: run */
	function run() {
		if ( ! state.token || ! state.analysis || ! state.analysis.can_run ) { return; }
		var total = state.analysis.file.rows - ( state.analysis.totals.ignored || 0 ), done = { inserted: 0, updated: 0 }, errors = [], seen = {};
		state.running = true;
		$modal.find( '.ecv2pi-tab, .ecv2-modal-close' ).prop( 'disabled', true );
		step( 'run' );
		$( '#ecv2pi_run_bar' ).css( 'width', '0%' ); $( '#ecv2pi_run_text' ).text( fmt( t( 'progress' ), 0, num( total ) ) );
		set_footer( '<span class="ecv2-sub">' + esc( t( 'importing' ) ) + '</span>' );
		function add_errors( text ) {
			$.each( String( text || '' ).split( /\r\n|\r|\n/ ), function( i, line ) { line = $.trim( line ); if ( line && ! seen[ line ] ) { seen[ line ] = true; errors.push( line ); } } );
		}
		function finish( stopped_msg ) {
			state.running = false;
			$modal.find( '.ecv2pi-tab, .ecv2-modal-close' ).prop( 'disabled', false );
			var token = state.token; state.token = '';
			if ( token ) { $.post( cfg.ajax_url, { action: 'ecv2_product_import_discard', nonce: cfg.nonce, token: token } ); }
			var h = '<div class="ecv2pi-totals"><div class="is-insert"><b>' + num( done.inserted ) + '</b><span>' + esc( t( 'added' ) ) + '</span></div><div class="is-update"><b>' + num( done.updated ) + '</b><span>' + esc( t( 'updated' ) ) + '</span></div><div class="' + ( errors.length ? 'is-problem' : '' ) + '"><b>' + num( errors.length ) + '</b><span>' + esc( t( 'errors' ) ) + '</span></div></div>';
			if ( stopped_msg ) { h += '<div class="ecos-note ecv2pi-note danger"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><div>' + esc( stopped_msg ) + '</div></div>'; }
			if ( errors.length ) {
				h += '<div class="ecv2pi-errors"><b>' + esc( t( 'errors_title' ) ) + '</b><ul>';
				$.each( errors, function( i, e ) { h += '<li>' + esc( e ) + '</li>'; } );
				h += '</ul></div>';
			} else if ( ! stopped_msg ) {
				h += '<div class="ecos-note ecv2pi-note info"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div>' + esc( t( 'all_good' ) ) + '</div></div>';
			}
			$( '#ecv2pi_done' ).html( h );
			step( 'done' );
			set_footer( '<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2pi_reload">' + esc( t( 'done_refresh' ) ) + '</button>' );
		}
		( function chunk( offset, line ) {
			$.post( cfg.ajax_url, { action: 'ecv2_product_import_run', nonce: cfg.nonce, token: state.token, offset: offset, line: line }, null, 'json' )
				.done( function( d ) {
					if ( d && d.success === false ) { finish( ( d.data && d.data.message ) || t( 'generic_error' ) ); return; }
					if ( ! d || typeof d.done === 'undefined' ) { finish( fmt( t( 'stopped_early' ), num( line ) ) ); return; }
					done.inserted += Number( d.inserted ) || 0; done.updated += Number( d.updated ) || 0;
					if ( d.errors ) { add_errors( d.errors ); }
					var at = Math.min( Number( d.line ) || 0, total );
					$( '#ecv2pi_run_bar' ).css( 'width', ( total ? Math.round( at / total * 100 ) : 100 ) + '%' ); $( '#ecv2pi_run_text' ).text( fmt( t( 'progress' ), num( at ), num( total ) ) );
					if ( ! d.done ) { chunk( d.next, d.line ); return; }
					$( '#ecv2pi_run_bar' ).css( 'width', '100%' );
					finish( '' );
				} )
				.fail( function() { finish( fmt( t( 'stopped_early' ), num( line ) ) ); } );
		} )( 0, 0 );
	}

	/* ---------------------------------------------------------------- export + help */
	function selected_ids() {
		var ids = [], seen = {};
		$( '.ecv2-row-check:checked' ).each( function() { var v = String( this.value ); if ( v && ! seen[ v ] ) { seen[ v ] = true; ids.push( v ); } } );
		return ids;
	}
	function refresh_selected() {
		var ids = selected_ids(), can = ids.length > 0 && window.ecv2_nonces && window.ecv2_nonces.bulk_export;
		$( '#ecv2pi_export_selected' ).prop( 'disabled', ! can ).toggleClass( 'ecv2-btn-primary', !! can );
		$( '#ecv2pi_export_selected_label' ).text( can ? fmt( t( 'export_selected' ), ids.length ) : t( 'export_selected', 'Export selected' ).replace( /%d\s*/, '' ) );
		$( '#ecv2pi_export_selected_hint' ).text( can ? '' : t( 'none_selected' ) ).prop( 'hidden', !! can );
	}
	function export_selected() {
		var ids = selected_ids();
		if ( ! ids.length || ! window.ecv2_nonces || ! window.ecv2_nonces.bulk_export ) { return; }
		var $btn = $( '#ecv2pi_export_selected' ).prop( 'disabled', true );
		$.post( cfg.ajax_url, { action: 'ecv2_product_bulk_export_prepare', wp_easycart_nonce: window.ecv2_nonces.bulk_export, product_ids: ids }, null, 'json' )
			.done( function( r ) {
				if ( r && r.success && r.data && r.data.download_url ) { window.open( r.data.download_url, '_blank' ); toast( t( 'export_ready' ), 'info' ); }
				else { toast( ( r && r.data && r.data.message ) || t( 'generic_error' ) ); }
			} )
			.fail( function() { toast( t( 'generic_error' ) ); } )
			.always( function() { $btn.prop( 'disabled', false ); } );
	}

	/* ---------------------------------------------------------------- wiring */
	$( function() {
		if ( ! $( '#ecv2pi_modal' ).length ) { return; }
		$( document ).on( 'click', '[data-ecv2pi-open]', function( e ) { e.preventDefault(); open( $( this ).data( 'ecv2piOpen' ) ); } );
		$( document ).on( 'click', '#ecv2pi_modal [data-ecv2pi-close]', function( e ) { e.preventDefault(); close(); } );
		$( document ).on( 'click', '#ecv2pi_modal', function( e ) { if ( $( e.target ).is( '#ecv2pi_modal' ) ) { close(); } } );
		$( document ).on( 'click', '#ecv2pi_modal .ecv2pi-tab', function() { if ( ! state.running ) { show_tab( $( this ).data( 'ecv2piTab' ) ); } } );
		var $drop = $( '#ecv2pi_drop' );
		$drop.on( 'click', function( e ) { if ( ! $( e.target ).is( 'input' ) ) { $( '#ecv2pi_file' ).trigger( 'click' ); } } );
		$drop.on( 'keydown', function( e ) { if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); $( '#ecv2pi_file' ).trigger( 'click' ); } } );
		$drop.on( 'dragover', function( e ) { e.preventDefault(); $drop.addClass( 'is-over' ); } ).on( 'dragleave drop', function() { $drop.removeClass( 'is-over' ); } ).on( 'drop', function( e ) { e.preventDefault(); var f = e.originalEvent.dataTransfer.files[0]; if ( f ) { pick( f ); } } );
		$( '#ecv2pi_file' ).on( 'change', function() { if ( this.files[0] ) { pick( this.files[0] ); } } );
		$( document ).on( 'click', '#ecv2pi_change', function( e ) { e.preventDefault(); discard(); reset_import(); set_footer( import_footer() ); } );
		$( document ).on( 'click', '#ecv2pi_go', function() { run(); } );
		$( document ).on( 'click', '#ecv2pi_reload', function() { window.location.reload(); } );
		$( '#ecv2pi_export_selected' ).on( 'click', export_selected );
		$( document ).on( 'change', '.ecv2-row-check, #ecv2-select-all, #ecv2-ss-select-all', function() { if ( $modal && $modal.is( ':visible' ) ) { refresh_selected(); } } );
		$( '#ecv2pi_sample' ).on( 'click', function() { post( 'ecv2_product_import_sample', {}, function( d ) { download( d.csv, d.filename || t( 'sample_name' ) ); } ); } );
		$( window ).on( 'beforeunload', function() { if ( state.running ) { return true; } } );
	} );

	window.ecv2pi = { open: open, close: close };
} )( jQuery );
