/** Email health / log / order resend. Self-contained ( loads on every EasyCart admin page ). */
( function( $ ) {
	'use strict';
	var V = window.ecv2_email_vars || {};
	var T = $.extend( { error: 'Something went wrong.', refreshed: 'Checks refreshed.', copy: 'Copy', copied: 'Copied', copy_fail: 'Could not copy.', sending: 'Sending…', checking: 'Checking…' }, V.i18n || {} );
	function toast( msg, kind ) {
		var $c = $( '#ecem-toast' ); if ( ! $c.length ) { $c = $( '<div id="ecem-toast" role="status" aria-live="polite"></div>' ).appendTo( 'body' ); }
		var $t = $( '<div class="ecem-toast ecem-toast-' + ( kind || 'info' ) + '"></div>' ).text( msg ); $c.append( $t );
		setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 5000 );
	}
	function post( action, data, cb, fail ) {
		$.post( V.ajax_url, $.extend( { action: action, nonce: V.nonce }, data || {} ), function( r ) {
			if ( ! r || ! r.success ) { toast( ( r && r.data && r.data.message ) || T.error, 'error' ); fail && fail(); return; }
			cb && cb( r.data );
		}, 'json' ).fail( function() { toast( T.error, 'error' ); fail && fail(); } );
	}
	var q_timer = null, log_status = '', log_page = 1, log_xhr_seq = 0;
	/* Server-side paged ( @since 6.0.0 ): page 1 after a filter / search change, the current page after a retry or resend. */
	function reload_log( page ) {
		var $rows = $( '#ecem_log_rows' );
		if ( ! $rows.length ) { return; }
		if ( page ) { log_page = page; }
		var seq = ++log_xhr_seq;
		$rows.attr( 'aria-busy', 'true' ).css( 'opacity', 0.6 );
		post( 'ecv2_email_log', { status: log_status, q: $( '#ecem_log_q' ).val() || '', paged: log_page }, function( d ) {
			if ( seq !== log_xhr_seq ) { return; } // a newer request ( typing, fast paging ) owns the table
			$rows.html( d.html ).removeAttr( 'aria-busy' ).css( 'opacity', '' );
		}, function() { $rows.removeAttr( 'aria-busy' ).css( 'opacity', '' ); } );
	}

	/* Retry queue panel ( 6.0.0 ): refreshed after anything that can change the queue. */
	function reload_queue() {
		var $q = $( '#ecem_queue_body' );
		if ( ! $q.length ) { return; }
		post( 'ecv2_email_queue_panel', {}, function( d ) { if ( d.html ) { $q.html( d.html ); } } );
	}

	/* Disclosures: <button data-ecem-toggle aria-controls="id" aria-expanded> + <div id hidden> */
	function set_open( $btn, open ) {
		var $panel = $( document.getElementById( $btn.attr( 'aria-controls' ) ) );
		$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		$panel.prop( 'hidden', ! open );
	}
	function open_panels() {
		return $( '[data-ecem-toggle][aria-expanded="true"]' ).map( function() { return $( this ).attr( 'aria-controls' ); } ).get();
	}
	function restore_panels( ids ) {
		$.each( ids, function( i, id ) { set_open( $( '[data-ecem-toggle][aria-controls="' + id + '"]' ), true ); } );
	}

	function copy_text( text, done ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( function() { done( true ); }, function() { done( fallback_copy( text ) ); } );
			return;
		}
		done( fallback_copy( text ) );
	}
	function fallback_copy( text ) {
		var $ta = $( '<textarea readonly class="ecem-copy-buffer"></textarea>' ).val( text ).appendTo( 'body' ), ok = false;
		$ta[0].select();
		try { ok = document.execCommand( 'copy' ); } catch ( e ) { ok = false; }
		$ta.remove();
		return ok;
	}

	window.ecem = {
		retry: function( queue_id, btn ) { $( btn ).prop( 'disabled', true ).text( '…' ); post( 'ecv2_email_retry', { queue_id: queue_id }, function( d ) { toast( d.message, d.result === 'sent' ? 'success' : 'info' ); reload_log(); reload_queue(); } ); },
		retry_all: function() { post( 'ecv2_email_retry_all', {}, function( d ) { toast( d.message, 'success' ); reload_log(); reload_queue(); } ); },
		resend_receipt: function( order_id, el ) {
			var $el = $( el ).prop( 'disabled', true ); var old = $el.text(); $el.text( T.sending );
			post( 'ecv2_email_resend_receipt', { order_id: order_id }, function( d ) {
				toast( d.message, d.status === 'sent' ? 'success' : 'info' );
				if ( d.status === 'sent' ) { $el.closest( '.ecem-order-chip' ).replaceWith( '<span class="ecv2-chip ecv2-chip-green">Receipt sent</span>' ); reload_log(); }
				else { $el.prop( 'disabled', false ).text( old ); }
			}, function() { $el.prop( 'disabled', false ).text( old ); } );
		},
		run_checks: function( btn ) {
			var keep = open_panels();
			$( '[data-ecem-run]' ).prop( 'disabled', true );
			var $btn = btn ? $( btn ) : $(), old = $btn.text();
			$btn.text( T.checking );
			$( '.ecem-checks, #ecem_guide_wrap' ).css( 'opacity', 0.5 ).attr( 'aria-busy', 'true' );
			var done = function() { $( '[data-ecem-run]' ).prop( 'disabled', false ); $btn.text( old ); $( '.ecem-checks, #ecem_guide_wrap' ).css( 'opacity', '' ).removeAttr( 'aria-busy' ); };
			post( 'ecv2_email_run_checks', {}, function( d ) {
				$( '.ecem-checks' ).html( d.html );
				if ( typeof d.guide === 'string' ) { $( '#ecem_guide_wrap' ).replaceWith( d.guide ); }
				if ( d.hint ) { $( '#ecem_checks_hint' ).text( d.hint ); }
				restore_panels( keep );
				done();
				toast( T.refreshed, 'success' );
			}, done );
		},
		send_test: function( btn ) {
			var $btn = $( btn ), old = $btn.text(), $out = $btn.siblings( '.ecem-test-result' );
			$btn.prop( 'disabled', true ).text( T.sending );
			$out.removeClass( 'is-ok is-bad' ).text( '' );
			post( 'ecv2_email_send_test', {}, function( d ) {
				var ok = d.status === 'sent';
				$btn.prop( 'disabled', false ).text( old );
				$out.addClass( ok ? 'is-ok' : 'is-bad' ).text( d.message );
				if ( ! ok && d.error ) { $out.attr( 'title', d.error ); }
				if ( ! $out.length ) { toast( d.message, ok ? 'success' : 'error' ); }
				reload_log();
			}, function() { $btn.prop( 'disabled', false ).text( old ); } );
		},
		queue_toggle: function( on ) { post( 'ecv2_email_queue_toggle', { on: on ? 1 : 0 }, function( d ) { toast( d.message, 'success' ); reload_queue(); } ); },
		/* Retry queue ( 6.0.0 ): run it now, simulate a failure, drop a test row. */
		queue_run: function( btn ) {
			var $b = $( btn ).prop( 'disabled', true ), old = $b.text();
			$b.text( T.sending );
			post( 'ecv2_email_queue_run', {}, function( d ) {
				$b.prop( 'disabled', false ).text( old );
				toast( d.message, 'info' );
				if ( d.html ) { $( '#ecem_queue_body' ).html( d.html ); }
				reload_log();
			}, function() { $b.prop( 'disabled', false ).text( old ); } );
		},
		queue_simulate: function( btn ) {
			var $b = $( btn ).prop( 'disabled', true ), old = $b.text();
			$b.text( T.sending );
			post( 'ecv2_email_queue_simulate', {}, function( d ) {
				$b.prop( 'disabled', false ).text( old );
				toast( d.message, 'info' );
				if ( d.html ) { $( '#ecem_queue_body' ).html( d.html ); }
				reload_log();
			}, function() { $b.prop( 'disabled', false ).text( old ); } );
		},
		queue_cancel: function( queue_id, btn ) {
			var $b = $( btn ).prop( 'disabled', true );
			post( 'ecv2_email_queue_cancel', { queue_id: queue_id }, function( d ) {
				toast( d.message, 'success' );
				if ( d.html ) { $( '#ecem_queue_body' ).html( d.html ); }
				reload_log();
			}, function() { $b.prop( 'disabled', false ); } );
		},
		snooze: function( el ) { post( 'ecv2_email_dismiss_banner', {}, function() { $( el ).closest( '.ecem-banner' ).slideUp( 200 ); } ); },
		log_filter: function( el ) { $( '.ecem-log-bar .ecv2-chip' ).removeClass( 'is-on' ); $( el ).addClass( 'is-on' ); log_status = $( el ).data( 'status' ) || ''; reload_log( 1 ); },
		log_search: function() { clearTimeout( q_timer ); q_timer = setTimeout( function() { reload_log( 1 ); }, 300 ); }
	};

	$( document )
		.on( 'click', '[data-ecem-toggle]', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			set_open( $btn, $btn.attr( 'aria-expanded' ) !== 'true' );
		} )
		.on( 'click', '[data-ecem-run]', function( e ) {
			e.preventDefault();
			window.ecem.run_checks( this );
		} )
		.on( 'click', '#ecem_log_rows [data-ecem-page]', function( e ) {
			e.preventDefault();
			var page = parseInt( $( this ).attr( 'data-ecem-page' ), 10 );
			if ( $( this ).prop( 'disabled' ) || ! page ) { return; }
			reload_log( page );
			var $log = $( '#ecem_log' );
			if ( $log.length && $log.offset().top < $( window ).scrollTop() ) { $( 'html, body' ).animate( { scrollTop: $log.offset().top - 60 }, 150 ); }
		} )
		.on( 'click', '[data-ecem-send-test]', function( e ) {
			e.preventDefault();
			window.ecem.send_test( this );
		} )
		.on( 'click', '[data-ecem-copy]', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			copy_text( String( $btn.attr( 'data-ecem-copy' ) ), function( ok ) {
				if ( ! ok ) { toast( T.copy_fail, 'error' ); return; }
				$btn.addClass( 'is-copied' ).text( T.copied );
				setTimeout( function() { $btn.removeClass( 'is-copied' ).text( T.copy ); }, 1800 );
			} );
		} )
		.on( 'click', 'a[href="#ecem_guide"]', function( e ) {
			var $guide = $( '#ecem_guide' );
			if ( ! $guide.length ) { return; }
			e.preventDefault();
			$( 'html, body' ).animate( { scrollTop: $guide.offset().top - 80 }, 200 );
			$guide.find( '.ecem-step.is-current [data-ecem-toggle]' ).first().each( function() { set_open( $( this ), true ); } ).trigger( 'focus' );
		} );
} )( jQuery );
