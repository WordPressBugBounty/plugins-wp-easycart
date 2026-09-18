/**
 * WP EasyCart Diagnostics: resumable repair jobs.
 *
 * The repair links on the Store Status page ( a.ecds-job[data-job] ) post one step at a time to
 * the ecv2_status_job AJAX action. Each answer carries the cursor for the next step
 * ( { done, next, phase, processed, total } ); this file loops until done, draws a progress bar
 * and, when the job completes, reloads the page with the tool's existing success flag.
 *
 * The cursor is kept in sessionStorage after every step, so a run that is interrupted
 * ( closed tab, dropped connection, PHP time-out ) offers Resume on the next page load and
 * continues from the last completed batch instead of starting over.
 *
 * @since 6.0.0
 */
/* global jQuery, ecv2_status_vars */
( function( $ ) {
	'use strict';

	var vars = window.ecv2_status_vars || {};
	var i18n = vars.i18n || {};
	var labels = vars.labels || {};
	var STORAGE_KEY = 'ecv2_status_job';
	var state = null;      /* { job, phase, last_id, processed, total, phase_no, phases } */
	var running = false;
	var $box, $label, $step, $bar, $fill, $count, $actions, $body;

	function fmt( template, a, b ) {
		return String( template || '' ).replace( '%1$s', a ).replace( '%2$s', b ).replace( '%s', a );
	}

	function esc_html( s ) {
		return String( s ).replace( /[&<>"']/g, function( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function save() {
		try {
			if ( state ) {
				window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
			} else {
				window.sessionStorage.removeItem( STORAGE_KEY );
			}
		} catch ( e ) { /* private mode or storage disabled: the run still works, it just cannot resume after a reload */ }
	}

	function load() {
		try {
			var raw = window.sessionStorage.getItem( STORAGE_KEY );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) {
			return null;
		}
	}

	function find_elements() {
		$box = $( '#ecds_job_progress' );
		$label = $( '#ecds_job_label' );
		$step = $( '#ecds_job_step' );
		$bar = $( '#ecds_job_bar' );
		$fill = $( '#ecds_job_bar_fill' );
		$count = $( '#ecds_job_count' );
		$actions = $( '#ecds_job_actions' );
		$body = $( '#ecds_body' );
		return $box.length > 0;
	}

	function show() {
		$box.removeClass( 'is-error' ).prop( 'hidden', false );
		$label.text( labels[ state.job ] || state.job );
		$actions.empty();
		render();
	}

	function render() {
		var total = parseInt( state.total, 10 ) || 0;
		var processed = parseInt( state.processed, 10 ) || 0;
		var pct = total > 0 ? Math.min( 100, Math.round( processed / total * 100 ) ) : 0;
		if ( state.phases > 1 && state.phase_no ) {
			$step.text( fmt( i18n.step, state.phase_no, state.phases ) );
		} else {
			$step.text( '' );
		}
		if ( total > 0 ) {
			$bar.removeClass( 'is-indeterminate' ).attr( 'aria-valuenow', pct );
			$fill.css( 'width', pct + '%' );
			$count.text( fmt( i18n.progress, processed, total ) );
		} else if ( ! state.phase ) {
			$bar.addClass( 'is-indeterminate' ).attr( 'aria-valuenow', 0 );
			$fill.css( 'width', '' );
			$count.text( i18n.starting || '' );
		} else {
			$bar.addClass( 'is-indeterminate' ).attr( 'aria-valuenow', 0 );
			$fill.css( 'width', '' );
			$count.text( fmt( i18n.progress_na, processed ) );
		}
	}

	function set_busy( busy ) {
		running = busy;
		$body.toggleClass( 'is-busy', busy );
	}

	function step() {
		set_busy( true );
		$.ajax( {
			url: vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ecv2_status_job',
				nonce: vars.nonce,
				job: state.job,
				phase: state.phase || '',
				last_id: state.last_id || '',
				processed: state.processed || 0,
				total: state.total || 0
			}
		} ).done( function( response ) {
			if ( ! response || ! response.success || ! response.data ) {
				fail( response && response.data && response.data.message ? response.data.message : i18n.error );
				return;
			}
			var d = response.data;
			state.phase = d.phase;
			state.last_id = d.next;
			state.processed = d.processed;
			state.total = d.total;
			state.phase_no = d.phase_no;
			state.phases = d.phases;
			render();
			if ( d.done ) {
				state = null;
				save();
				set_busy( false );
				$fill.css( 'width', '100%' );
				$count.text( i18n.finishing || '' );
				window.location.href = d.redirect;
				return;
			}
			save();
			step();
		} ).fail( function() {
			fail( i18n.network );
		} );
	}

	function fail( message ) {
		set_busy( false );
		save();
		$box.addClass( 'is-error' );
		$bar.removeClass( 'is-indeterminate' );
		$count.text( message || i18n.error || '' );
		offer_resume();
	}

	function offer_resume() {
		$actions.html(
			'<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecds_job_resume">' + esc_html( i18n.resume || 'Resume' ) + '</button>' +
			'<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecds_job_discard">' + esc_html( i18n.discard || 'Discard' ) + '</button>'
		);
	}

	function start( job ) {
		if ( ! labels[ job ] ) {
			return;
		}
		if ( running ) {
			window.alert( i18n.running || '' );
			return;
		}
		if ( 'reset-store-permalinks' === job && i18n.confirm_reset && ! window.confirm( i18n.confirm_reset ) ) {
			return;
		}
		state = { job: job, phase: '', last_id: '', processed: 0, total: 0, phase_no: 0, phases: 0 };
		save();
		show();
		step();
	}

	function resume() {
		if ( ! state || running ) {
			return;
		}
		show();
		step();
	}

	function discard() {
		state = null;
		save();
		$box.prop( 'hidden', true ).removeClass( 'is-error' );
		$actions.empty();
	}

	function strip_autostart_param() {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}
		try {
			var url = new URL( window.location.href );
			if ( url.searchParams.has( 'ecds_job' ) ) {
				url.searchParams[ 'delete' ]( 'ecds_job' );
				window.history.replaceState( null, '', url.toString() );
			}
		} catch ( e ) { /* older browsers without URL(): leaving the parameter in place only re-offers the job on reload */ }
	}

	$( document ).on( 'click', 'a.ecds-job', function( e ) {
		e.preventDefault();
		start( $( this ).data( 'job' ) );
	} );

	$( document ).on( 'click', '#ecds_job_resume', function( e ) {
		e.preventDefault();
		resume();
	} );

	$( document ).on( 'click', '#ecds_job_discard', function( e ) {
		e.preventDefault();
		discard();
	} );

	$( window ).on( 'beforeunload', function( e ) {
		if ( running ) {
			e.preventDefault();
			e.returnValue = i18n.leave || '';
			return i18n.leave || '';
		}
	} );

	$( function() {
		if ( ! find_elements() ) {
			return;
		}
		var saved = load();
		if ( saved && saved.job && labels[ saved.job ] ) {
			state = saved;
			show();
			$box.addClass( 'is-error' );
			$bar.removeClass( 'is-indeterminate' );
			$count.text( i18n.interrupted || '' );
			offer_resume();
			strip_autostart_param();
			return;
		}
		if ( vars.autostart ) {
			strip_autostart_param();
			start( vars.autostart );
		}
	} );
} )( jQuery );
