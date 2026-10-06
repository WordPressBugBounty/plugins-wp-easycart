/**
 * Products › Import ( 6.0.3 ): source cards, choices, a trial of ten products, progress, the report.
 *
 * Data: window.wpecImport ( wp_easycart_admin_import::script_data() ). AJAX: ecv2_import_detect, _start, _step, _control,
 * _results, _undo, _redirects ( nonce in "nonce" ). The page drives a running import a few seconds at a time; WP-Cron finishes
 * it when the page is closed.
 *
 * @since 6.0.3
 */
( function () {
	'use strict';

	var D = window.wpecImport || {};
	var T = D.text || {};
	var app = document.getElementById( 'ecimp2-app' );
	if ( ! app ) {
		return;
	}

	var state = {
		view: 'home',
		detect: {},
		source: null,
		choices: null,
		job: D.job || null,
		runs: D.runs || [],
		stepping: false,
		notice: '',
		error: '',
		results: { run: 0, filter: 'notes', rows: [], more: false, counts: null },
		busy: false
	};

	/* ------------------------------------------------------------------ helpers */

	function esc( value ) {
		return String( null === value || undefined === value ? '' : value ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function fmt( n ) {
		n = parseInt( n, 10 ) || 0;
		try {
			return n.toLocaleString();
		} catch ( e ) {
			return String( n );
		}
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', D.nonce || '' );
		Object.keys( data || {} ).forEach( function ( key ) {
			var value = data[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) {
					body.append( key + '[]', v );
				} );
			} else if ( value && 'object' === typeof value ) {
				Object.keys( value ).forEach( function ( k ) {
					body.append( key + '[' + k + ']', value[ k ] );
				} );
			} else {
				body.append( key, null === value || undefined === value ? '' : value );
			}
		} );
		return fetch( D.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.text().then( function ( text ) {
				/* The last {"success": in the body: a notice printed before the JSON cannot hide the answer. */
				var at = text.lastIndexOf( '{"success":' );
				var parsed = null;
				try {
					parsed = JSON.parse( at >= 0 ? text.slice( at ) : text );
				} catch ( e ) {
					parsed = null;
				}
				if ( ! parsed ) {
					throw new Error( T.error || 'Error' );
				}
				if ( ! parsed.success ) {
					throw new Error( parsed.data && parsed.data.message ? parsed.data.message : ( T.error || 'Error' ) );
				}
				return parsed.data;
			} );
		} );
	}

	function sourceOf( id ) {
		var found = null;
		( D.sources || [] ).forEach( function ( s ) {
			if ( s.id === id ) {
				found = s;
			}
		} );
		return found;
	}

	function logo( id ) {
		if ( 'woocommerce' === id ) {
			return '<span class="ecimp2-logo is-woo" aria-hidden="true">Woo</span>';
		}
		if ( 'square' === id ) {
			return '<span class="ecimp2-logo is-square" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2.4"/><rect x="8.5" y="8.5" width="7" height="7" rx="1" fill="currentColor"/></svg></span>';
		}
		if ( 'csv' === id ) {
			return '<span class="ecimp2-logo is-csv" aria-hidden="true">CSV</span>';
		}
		return '<span class="ecimp2-logo is-other" aria-hidden="true">…</span>';
	}

	function chip( status ) {
		var label = ( T.status && T.status[ status ] ) || status;
		return '<span class="ecimp2-chip is-' + esc( status ) + '">' + esc( label ) + '</span>';
	}

	function resultChip( status, label ) {
		return '<span class="ecimp2-chip is-' + esc( status ) + '">' + esc( label ) + '</span>';
	}

	function stepsBar( active, trial ) {
		var labels = T.steps || [];
		var html = '<ol class="ecimp2-steps" aria-label="' + esc( T.running || '' ) + '">';
		labels.forEach( function ( label, index ) {
			var cls = index < active ? 'is-done' : ( index === active ? 'is-on' : '' );
			if ( 1 === index && ! trial && active > 1 ) {
				cls = 'is-skip';
			}
			html += '<li class="' + cls + '"' + ( index === active ? ' aria-current="step"' : '' ) + '><span class="ecimp2-step-n">' + ( index < active && 'is-skip' !== cls ? '✓' : ( index + 1 ) ) + '</span>' + esc( label ) + '</li>';
		} );
		return html + '</ol>';
	}

	function countsOf( job, keys ) {
		var out = { imported: 0, updated: 0, skipped: 0, failed: 0, notes: 0 };
		var counts = ( job && job.counts ) || {};
		Object.keys( counts ).forEach( function ( entity ) {
			if ( keys && keys.indexOf( entity ) < 0 ) {
				return;
			}
			Object.keys( out ).forEach( function ( k ) {
				out[ k ] += parseInt( counts[ entity ][ k ], 10 ) || 0;
			} );
		} );
		return out;
	}

	function notice() {
		var html = '';
		if ( state.notice ) {
			html += '<div class="ecimp2-notice is-ok" role="status">' + esc( state.notice ) + '</div>';
		}
		if ( state.error ) {
			html += '<div class="ecimp2-notice is-bad" role="alert">' + esc( state.error ) + '</div>';
		}
		return html;
	}

	function isOpen( job ) {
		return job && ( 'running' === job.status || 'paused' === job.status );
	}

	/* ------------------------------------------------------------------ views */

	function render() {
		var html = notice();
		if ( 'setup' === state.view ) {
			html += viewSetup();
		} else if ( 'run' === state.view ) {
			html += viewRun();
		} else if ( 'trial' === state.view ) {
			html += viewTrial();
		} else if ( 'report' === state.view ) {
			html += viewReport();
		} else {
			html += viewHome();
		}
		app.innerHTML = html;
	}

	function viewHome() {
		var html = '<h2 class="ecimp2-h">' + esc( T.where ) + '</h2><div class="ecimp2-sources">';
		( D.sources || [] ).forEach( function ( source ) {
			var d = state.detect[ source.id ];
			var status = d ? esc( d.status ) : '<span class="ecimp2-muted">' + esc( T.looking ) + '</span>';
			var counts = '';
			var action = '';
			if ( d && d.counts ) {
				var keys = 'woocommerce' === source.id ? [ [ 'products', T.products ], [ 'variations', '' ], [ 'categories', T.categories ], [ 'reviews', T.reviews ] ] : [ [ 'items', T.products ], [ 'categories', T.categories ] ];
				keys.forEach( function ( k ) {
					if ( d.counts[ k[0] ] && k[1] ) {
						counts += '<span><b>' + fmt( d.counts[ k[0] ] ) + '</b> ' + esc( k[1].toLowerCase() ) + '</span>';
					}
				} );
			}
			if ( ! d ) {
				action = '<button type="button" class="ecv2-btn" disabled>' + esc( T.start ) + '</button>';
			} else if ( d.available ) {
				action = '<button type="button" class="ecv2-btn ecv2-btn-primary" data-act="setup" data-source="' + esc( source.id ) + '">' + esc( T.start ) + '</button>';
			} else if ( d.action && d.action.url ) {
				action = '<a class="ecv2-btn ecv2-btn-primary" href="' + esc( d.action.url ) + '">' + esc( d.action.label ) + '</a>';
			} else {
				action = '<button type="button" class="ecv2-btn" disabled>' + esc( T.nothing ) + '</button>';
			}
			html += '<div class="ecimp2-card' + ( d && d.available ? ' is-ready' : '' ) + '">' +
				'<div class="ecimp2-card-top">' + logo( source.id ) + '<div class="ecimp2-card-name"><b>' + esc( source.label ) + '</b><small>' + esc( source.desc ) + '</small></div></div>' +
				'<p class="ecimp2-card-status">' + status + '</p>' +
				( counts ? '<p class="ecimp2-counts">' + counts + '</p>' : '' ) +
				'<div class="ecimp2-card-foot">' + action + ( d ? '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" data-act="recheck" data-source="' + esc( source.id ) + '">' + esc( T.again ) + '</button>' : '' ) + '</div>' +
				'</div>';
		} );
		html += '<div class="ecimp2-card">' +
			'<div class="ecimp2-card-top">' + logo( 'csv' ) + '<div class="ecimp2-card-name"><b>' + esc( T.csvTitle ) + '</b><small>' + esc( T.csvDesc ) + '</small></div></div>' +
			'<div class="ecimp2-card-foot"><a class="ecv2-btn" href="' + esc( D.urls.csv ) + '">' + esc( T.csvButton ) + '</a></div></div>';
		html += '<div class="ecimp2-card is-muted">' +
			'<div class="ecimp2-card-top">' + logo( 'other' ) + '<div class="ecimp2-card-name"><b>' + esc( T.otherTitle ) + '</b><small>' + esc( T.otherDesc ) + '</small></div></div>' +
			'<div class="ecimp2-card-foot"><span class="ecimp2-chip is-coming">' + esc( T.comingNext ) + '</span></div></div>';
		html += '</div>';
		html += viewHistory();
		return html;
	}

	function viewHistory() {
		var runs = ( state.runs || [] ).filter( function ( run ) {
			return ! run.trial || 'undone' !== run.status;
		} );
		if ( ! runs.length ) {
			return '';
		}
		var html = '<h2 class="ecimp2-h">' + esc( T.history ) + '</h2><div class="ecimp2-panel"><ul class="ecimp2-runs">';
		runs.forEach( function ( run ) {
			var c = countsOf( run, [ 'product' ] );
			html += '<li><div class="ecimp2-run-main"><b>' + esc( run.label ) + '</b>' + ( run.trial ? ' <span class="ecimp2-chip is-trial">' + esc( T.trial ) + '</span>' : '' ) + ' ' + chip( run.status ) +
				'<small>' + esc( run.when ) + ' · ' + esc( T.imported ) + ' ' + fmt( c.imported ) + ' · ' + esc( T.updated ) + ' ' + fmt( c.updated ) + ' · ' + esc( T.skipped ) + ' ' + fmt( c.skipped ) + ( c.failed ? ' · ' + esc( T.failed ) + ' ' + fmt( c.failed ) : '' ) + '</small></div>' +
				'<div class="ecimp2-run-actions">' +
				( 'undone' !== run.status ? '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" data-act="open-report" data-run="' + run.id + '">' + esc( T.report ) + '</button>' : '' ) +
				( ( 'done' === run.status || 'cancelled' === run.status ) ? '<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" data-act="undo" data-run="' + run.id + '">' + esc( T.remove ) + '</button>' : '' ) +
				'</div></li>';
		} );
		return html + '</ul></div>';
	}

	function viewSetup() {
		var d = state.detect[ state.source ];
		var source = sourceOf( state.source ) || { label: state.source };
		var html = stepsBar( 0, true );
		html += '<div class="ecimp2-panel ecimp2-head"><div class="ecimp2-card-top">' + logo( state.source ) + '<div class="ecimp2-card-name"><b>' + esc( source.label ) + '</b><small>' + esc( d ? d.status : '' ) + '</small></div></div>' +
			'<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" data-act="home">' + esc( T.back ) + '</button></div>';
		if ( ! d ) {
			return html + '<p class="ecimp2-loading">' + esc( T.looking ) + '</p>';
		}
		if ( d.checks && d.checks.length ) {
			html += '<h2 class="ecimp2-h">' + esc( T.checks ) + '</h2><div class="ecimp2-panel"><ul class="ecimp2-checks">';
			d.checks.forEach( function ( check ) {
				var mark = { ok: '✓', warn: '!', bad: '×', info: 'i' }[ check.level ] || 'i';
				html += '<li class="is-' + esc( check.level ) + '"><span class="ecimp2-ic" aria-hidden="true">' + mark + '</span><span><b>' + esc( check.title ) + '</b>' + ( check.text ? '<small>' + esc( check.text ) + '</small>' : '' ) + '</span></li>';
			} );
			html += '</ul></div>';
		}
		var choices = state.choices || {};
		html += '<h2 class="ecimp2-h">' + esc( T.choose ) + '</h2><div class="ecimp2-entities">';
		( d.entities || [] ).forEach( function ( entity ) {
			var checked = choices.entities ? choices.entities.indexOf( entity.key ) >= 0 : !! entity['default'];
			var id = 'ecimp2-ent-' + entity.key;
			html += '<label class="ecimp2-entity' + ( entity.available ? '' : ' is-off' ) + '" for="' + id + '">' +
				'<input type="checkbox" id="' + id + '" data-entity="' + esc( entity.key ) + '"' + ( entity.available && checked ? ' checked' : '' ) + ( entity.available ? '' : ' disabled' ) + ' />' +
				'<span><b>' + esc( entity.label ) + ( entity.count ? ' <i>' + fmt( entity.count ) + '</i>' : '' ) + '</b><small>' + esc( entity.desc ) + '</small>' +
				( entity.note ? '<span class="ecimp2-chip is-coming">' + esc( entity.note ) + '</span>' : '' ) + '</span></label>';
		} );
		html += '</div>';
		if ( d.fields && d.fields.length ) {
			html += '<h2 class="ecimp2-h">' + esc( T.options ) + '</h2><div class="ecimp2-panel ecimp2-fields">';
			d.fields.forEach( function ( field ) {
				var value = choices.fields && undefined !== choices.fields[ field.key ] ? choices.fields[ field.key ] : field['default'];
				var id = 'ecimp2-f-' + field.key;
				html += '<div class="ecimp2-field">';
				if ( 'toggle' === field.type ) {
					html += '<label class="ecimp2-toggle" for="' + id + '"><input type="checkbox" id="' + id + '" data-field="' + esc( field.key ) + '"' + ( '1' === String( value ) ? ' checked' : '' ) + ' /><span><b>' + esc( field.label ) + '</b><small>' + esc( field.desc ) + '</small></span></label>';
				} else {
					html += '<label for="' + id + '"><b>' + esc( field.label ) + '</b>' + ( field.desc ? '<small>' + esc( field.desc ) + '</small>' : '' ) + '</label><select id="' + id + '" data-field="' + esc( field.key ) + '">';
					Object.keys( field.options || {} ).forEach( function ( key ) {
						html += '<option value="' + esc( key ) + '"' + ( String( value ) === key ? ' selected' : '' ) + '>' + esc( field.options[ key ] ) + '</option>';
					} );
					html += '</select>';
				}
				html += '</div>';
			} );
			html += '</div>';
		}
		html += '<div class="ecimp2-actions"><button type="button" class="ecv2-btn ecv2-btn-primary" data-act="try"' + ( state.busy ? ' disabled' : '' ) + '>' + esc( T['try'] ) + '</button>' +
			'<button type="button" class="ecv2-btn" data-act="all"' + ( state.busy ? ' disabled' : '' ) + '>' + esc( T.all ) + '</button></div>' +
			'<p class="ecimp2-hint">' + esc( T.tryHint ) + '</p>';
		return html;
	}

	function viewRun() {
		var job = state.job;
		if ( ! job ) {
			return '';
		}
		var html = stepsBar( job.trial ? 1 : 2, job.trial );
		var c = countsOf( job );
		html += '<div class="ecimp2-panel"><div class="ecimp2-run-head"><div><h2 class="ecimp2-h is-flat">' + esc( job.trial ? T.trialRunning : T.running ) + ' · ' + esc( job.label ) + '</h2>' +
			'<p class="ecimp2-muted">' + ( 'paused' === job.status ? esc( T.paused ) : esc( T.closeOk ) ) + '</p></div><div class="ecimp2-run-controls">' +
			( 'running' === job.status ? '<button type="button" class="ecv2-btn ecv2-btn-sm" data-act="pause">' + esc( T.pause ) + '</button>' : '' ) +
			( 'paused' === job.status ? '<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" data-act="resume">' + esc( T.resume ) + '</button>' : '' ) +
			'<button type="button" class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" data-act="stop">' + esc( T.stop ) + '</button></div></div>';
		if ( job.error ) {
			html += '<div class="ecimp2-notice is-bad" role="alert">' + esc( job.error ) + '</div>';
		}
		html += '<div class="ecimp2-bars">';
		( job.phases || [] ).forEach( function ( phase ) {
			var total = Math.max( phase.total, phase.done );
			var pct = 'done' === phase.state ? 100 : ( total > 0 ? Math.min( 99, Math.round( 100 * phase.done / total ) ) : ( 'running' === phase.state ? 5 : 0 ) );
			html += '<div class="ecimp2-bar-row is-' + esc( phase.state ) + '"><b>' + esc( phase.label ) + '</b>' +
				'<div class="ecimp2-bar" role="progressbar" aria-label="' + esc( phase.label ) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '"><i style="width:' + pct + '%"></i></div>' +
				'<small>' + ( 'waiting' === phase.state ? esc( T.waiting ) : fmt( phase.done ) + ( total ? ' / ' + fmt( total ) : '' ) ) + '</small></div>';
		} );
		html += '</div><p class="ecimp2-tally">' + esc( T.imported ) + ' <b>' + fmt( c.imported ) + '</b> · ' + esc( T.updated ) + ' <b>' + fmt( c.updated ) + '</b> · ' + esc( T.skipped ) + ' <b>' + fmt( c.skipped ) + '</b>' +
			( c.failed ? ' · ' + esc( T.failed ) + ' <b>' + fmt( c.failed ) + '</b>' : '' ) + '</p>' +
			'<p class="ecimp2-hint">' + esc( T.quiet ) + '</p></div>';
		return html;
	}

	function resultsTable( rows ) {
		if ( ! rows.length ) {
			return '<p class="ecimp2-muted ecimp2-pad">' + esc( T.noNotes ) + '</p>';
		}
		var html = '<div class="ecimp2-scroll"><table class="ecimp2-table"><thead><tr><th>' + esc( T.item ) + '</th><th>' + esc( T.kind ) + '</th><th>' + esc( T.result ) + '</th><th>' + esc( T.note ) + '</th></tr></thead><tbody>';
		rows.forEach( function ( row ) {
			html += '<tr><td>' + ( row.link ? '<a href="' + esc( row.link ) + '" target="_blank" rel="noopener">' + esc( row.label || '—' ) + '</a>' : esc( row.label || '—' ) ) + '</td>' +
				'<td>' + esc( row.kind ) + '</td><td>' + resultChip( row.status, row.result ) + '</td><td>' + esc( row.note ) + '</td></tr>';
		} );
		return html + '</tbody></table></div>';
	}

	function viewTrial() {
		var job = state.job;
		var html = stepsBar( 1, true );
		var trialRows = state.results.rows.filter( function ( row ) {
			return 'product' === row.entity;
		} );
		html += '<div class="ecimp2-panel"><div class="ecimp2-pad"><h2 class="ecimp2-h is-flat">' + esc( T.trialDone ) + '</h2><p class="ecimp2-muted">' + esc( trialRows.length ? T.trialHint : T.trialNothing ) + '</p></div>' +
			( trialRows.length ? resultsTable( trialRows ) : '' ) + '</div>';
		html += '<div class="ecimp2-actions"><button type="button" class="ecv2-btn ecv2-btn-primary" data-act="all-after-trial"' + ( state.busy ? ' disabled' : '' ) + '>' + esc( T.looksGood ) + '</button>' +
			'<button type="button" class="ecv2-btn" data-act="undo-trial" data-run="' + ( job ? job.id : 0 ) + '"' + ( state.busy ? ' disabled' : '' ) + '>' + esc( T.removeTrial ) + '</button></div>';
		return html;
	}

	function viewReport() {
		var run = state.results.run;
		var job = state.reportJob || state.job;
		var products = countsOf( job, [ 'product' ] );
		var categories = countsOf( job, [ 'category', 'tag' ] );
		var reviews = countsOf( job, [ 'review' ] );
		var all = countsOf( job );
		var html = stepsBar( 3, job && job.trial );
		html += '<div class="ecimp2-panel ecimp2-head"><div><h2 class="ecimp2-h is-flat">' + esc( T.reportTitle ) + '</h2><p class="ecimp2-muted">' + esc( ( T.reportFrom || '%s' ).replace( '%s', job ? job.label : '' ) ) + ( job && job.when ? ' · ' + esc( job.when ) : '' ) + '</p></div>' +
			'<a class="ecv2-btn ecv2-btn-sm" href="' + esc( D.urls.report + '&run=' + run + '&nonce=' + encodeURIComponent( D.nonce ) ) + '">' + esc( T.download ) + '</a></div>';
		html += '<div class="ecimp2-kpis">' +
			'<div class="ecimp2-kpi"><b>' + fmt( products.imported ) + '</b><span>' + esc( T.products ) + ' · ' + esc( T.imported ).toLowerCase() + '</span></div>' +
			'<div class="ecimp2-kpi"><b>' + fmt( products.updated ) + '</b><span>' + esc( T.products ) + ' · ' + esc( T.updated ).toLowerCase() + '</span></div>' +
			'<div class="ecimp2-kpi"><b>' + fmt( products.skipped ) + '</b><span>' + esc( T.products ) + ' · ' + esc( T.skipped ).toLowerCase() + '</span></div>' +
			'<div class="ecimp2-kpi"><b>' + fmt( categories.imported ) + '</b><span>' + esc( T.categories ) + '</span></div>' +
			( reviews.imported ? '<div class="ecimp2-kpi"><b>' + fmt( reviews.imported ) + '</b><span>' + esc( T.reviews ) + '</span></div>' : '' ) +
			'<div class="ecimp2-kpi' + ( all.failed ? ' is-bad' : '' ) + '"><b>' + fmt( all.notes + all.failed ) + '</b><span>' + esc( T.notes ) + '</span></div></div>';
		html += '<div class="ecimp2-panel"><div class="ecimp2-filter" role="group">' +
			'<button type="button" class="ecimp2-pill" data-act="filter" data-filter="notes" aria-pressed="' + ( 'notes' === state.results.filter ) + '">' + esc( T.notesTitle ) + '</button>' +
			'<button type="button" class="ecimp2-pill" data-act="filter" data-filter="all" aria-pressed="' + ( 'all' === state.results.filter ) + '">' + esc( T.everything ) + '</button></div>' +
			resultsTable( state.results.rows ) +
			( state.results.more ? '<div class="ecimp2-pad"><button type="button" class="ecv2-btn ecv2-btn-sm" data-act="more">' + esc( T.more ) + '</button></div>' : '' ) + '</div>';
		var checklist = job && 'square' === job.source ? T.checkSquare : T.checkWoo;
		html += '<div class="ecimp2-grid2"><div class="ecimp2-panel"><h3 class="ecimp2-h3">' + esc( T.checklist ) + '</h3><ul class="ecimp2-list">';
		( checklist || [] ).forEach( function ( item ) {
			html += '<li>' + esc( item ) + '</li>';
		} );
		html += '</ul></div>';
		if ( job && 'woocommerce' === job.source ) {
			html += '<div class="ecimp2-panel"><label class="ecimp2-toggle" for="ecimp2-redirects"><input type="checkbox" id="ecimp2-redirects" data-act="redirects"' + ( D.redirects ? ' checked' : '' ) + ' /><span><b>' + esc( T.redirects ) + '</b><small>' + esc( T.redirectsHint ) + '</small></span></label>' +
				'<p><a class="ecv2-btn ecv2-btn-sm" href="' + esc( D.urls.redirects + '&nonce=' + encodeURIComponent( D.nonce ) ) + '">' + esc( T.redirectsCsv ) + '</a></p></div>';
		}
		html += '</div><div class="ecimp2-actions">' +
			'<button type="button" class="ecv2-btn ecv2-btn-primary" data-act="dismiss">' + esc( T.done ) + '</button>' +
			( job ? '<button type="button" class="ecv2-btn" data-act="again" data-source="' + esc( job.source ) + '">' + esc( T.importAgain ) + '</button>' : '' ) +
			( run ? '<button type="button" class="ecv2-btn ecv2-btn-ghost ecimp2-danger" data-act="undo" data-run="' + run + '"' + ( state.busy ? ' disabled' : '' ) + '>' + esc( state.busy ? T.undoing : T.undo ) + '</button>' : '' ) + '</div>';
		return html;
	}

	/* ------------------------------------------------------------------ actions */

	function detect( id, fresh ) {
		return post( 'ecv2_import_detect', { source: id, fresh: fresh ? 1 : 0 } ).then( function ( data ) {
			state.detect[ id ] = data;
			render();
			return data;
		} ).catch( function ( e ) {
			state.detect[ id ] = { status: e.message, available: false, counts: null, entities: [], fields: [], checks: [] };
			render();
		} );
	}

	function readChoices() {
		var choices = { entities: [], fields: {} };
		app.querySelectorAll( '[data-entity]' ).forEach( function ( box ) {
			if ( box.checked && ! box.disabled ) {
				choices.entities.push( box.getAttribute( 'data-entity' ) );
			}
		} );
		app.querySelectorAll( '[data-field]' ).forEach( function ( input ) {
			choices.fields[ input.getAttribute( 'data-field' ) ] = 'checkbox' === input.type ? ( input.checked ? '1' : '0' ) : input.value;
		} );
		return choices;
	}

	function start( trial, choices ) {
		state.busy = true;
		state.error = '';
		render();
		post( 'ecv2_import_start', { source: state.source, trial: trial ? 1 : 0, entities: choices.entities, fields: choices.fields } ).then( function ( data ) {
			state.busy = false;
			state.job = data.job;
			state.view = 'run';
			render();
			afterStep();
		} ).catch( function ( e ) {
			state.busy = false;
			state.error = e.message;
			render();
		} );
	}

	function stepLoop() {
		if ( state.stepping || ! state.job || 'running' !== state.job.status ) {
			return;
		}
		state.stepping = true;
		post( 'ecv2_import_step', {} ).then( function ( data ) {
			state.stepping = false;
			state.job = data.job;
			if ( data.runs ) {
				state.runs = data.runs;
			}
			afterStep( data.job && data.job.busy ? 2000 : 250 );
		} ).catch( function ( e ) {
			state.stepping = false;
			state.error = e.message;
			render();
			window.setTimeout( stepLoop, 5000 );
		} );
	}

	function afterStep( wait ) {
		var job = state.job;
		if ( ! job ) {
			state.view = 'home';
			render();
			return;
		}
		if ( 'running' === job.status ) {
			state.view = 'run';
			render();
			window.setTimeout( stepLoop, wait || 250 );
			return;
		}
		if ( 'paused' === job.status ) {
			state.view = 'run';
			render();
			return;
		}
		if ( 'done' === job.status ) {
			state.error = '';
			if ( job.trial ) {
				loadResults( job.id, 'all', false ).then( function () {
					state.view = 'trial';
					render();
				} );
			} else {
				openReport( job.id, job );
			}
			return;
		}
		state.view = 'home';
		render();
	}

	function loadResults( run, filter, append ) {
		var offset = append ? state.results.rows.length : 0;
		return post( 'ecv2_import_results', { run: run, filter: filter, offset: offset } ).then( function ( data ) {
			state.results.run = run;
			state.results.filter = filter;
			state.results.rows = append ? state.results.rows.concat( data.rows ) : data.rows;
			state.results.more = data.more;
			state.results.counts = data.counts;
			if ( data.run ) {
				state.reportJob = {
					id: data.run.id,
					label: data.run.label,
					source: data.run.source,
					trial: data.run.trial,
					counts: data.run.counts,
					when: ''
				};
				( state.runs || [] ).forEach( function ( r ) {
					if ( r.id === data.run.id ) {
						state.reportJob.when = r.when;
					}
				} );
			}
		} ).catch( function ( e ) {
			state.error = e.message;
		} );
	}

	function openReport( run ) {
		state.view = 'report';
		state.reportJob = null;
		loadResults( run, 'notes', false ).then( render );
	}

	function undo( run, then ) {
		state.busy = true;
		render();
		var loop = function () {
			post( 'ecv2_import_undo', { run: run } ).then( function ( data ) {
				state.runs = data.runs || state.runs;
				state.job = data.job;
				if ( ! data.done ) {
					loop();
					return;
				}
				state.busy = false;
				state.notice = T.undone;
				then();
			} ).catch( function ( e ) {
				state.busy = false;
				state.error = e.message;
				render();
			} );
		};
		loop();
	}

	function control( action ) {
		return post( 'ecv2_import_control', { 'do': action } ).then( function ( data ) {
			state.job = data.job;
			state.runs = data.runs || state.runs;
			return data;
		} ).catch( function ( e ) {
			state.error = e.message;
			render();
		} );
	}

	app.addEventListener( 'click', function ( event ) {
		var el = event.target.closest( '[data-act]' );
		if ( ! el || el.disabled || 'redirects' === el.getAttribute( 'data-act' ) ) {
			return;
		}
		var act = el.getAttribute( 'data-act' );
		state.notice = '';
		if ( 'setup' === act || 'again' === act ) {
			state.source = el.getAttribute( 'data-source' );
			state.choices = null;
			state.view = 'setup';
			state.error = '';
			if ( 'again' === act ) {
				control( 'dismiss' );
			}
			render();
			if ( ! state.detect[ state.source ] || 'again' === act ) {
				detect( state.source, 'again' === act );
			}
		} else if ( 'recheck' === act ) {
			delete state.detect[ el.getAttribute( 'data-source' ) ];
			render();
			detect( el.getAttribute( 'data-source' ), true );
		} else if ( 'home' === act ) {
			state.view = 'home';
			state.error = '';
			render();
		} else if ( 'try' === act || 'all' === act ) {
			state.choices = readChoices();
			start( 'try' === act, state.choices );
		} else if ( 'all-after-trial' === act ) {
			var settings = ( state.job && state.job.settings ) || {};
			state.source = state.job ? state.job.source : state.source;
			start( false, state.choices || { entities: settings.entities || [], fields: settings.fields || {} } );
		} else if ( 'undo-trial' === act ) {
			var trialJob = state.job;
			if ( trialJob ) {
				state.source = trialJob.source;
				state.choices = state.choices || { entities: ( trialJob.settings || {} ).entities || [], fields: ( trialJob.settings || {} ).fields || {} };
			}
			undo( parseInt( el.getAttribute( 'data-run' ), 10 ), function () {
				state.view = 'setup';
				render();
				if ( ! state.detect[ state.source ] ) {
					detect( state.source, false );
				}
			} );
		} else if ( 'pause' === act || 'resume' === act ) {
			control( act ).then( function () {
				afterStep();
			} );
		} else if ( 'stop' === act ) {
			if ( window.confirm( T.stopAsk ) ) {
				control( 'cancel' ).then( function () {
					if ( state.job ) {
						openReport( state.job.id );
					} else {
						state.view = 'home';
						render();
					}
				} );
			}
		} else if ( 'filter' === act ) {
			loadResults( state.results.run, el.getAttribute( 'data-filter' ), false ).then( render );
		} else if ( 'more' === act ) {
			loadResults( state.results.run, state.results.filter, true ).then( render );
		} else if ( 'open-report' === act ) {
			openReport( parseInt( el.getAttribute( 'data-run' ), 10 ) );
		} else if ( 'undo' === act ) {
			if ( window.confirm( T.undoAsk ) ) {
				undo( parseInt( el.getAttribute( 'data-run' ), 10 ), function () {
					state.view = 'home';
					render();
				} );
			}
		} else if ( 'dismiss' === act ) {
			control( 'dismiss' ).then( function () {
				state.view = 'home';
				render();
			} );
		}
	} );

	app.addEventListener( 'change', function ( event ) {
		var el = event.target;
		if ( el && 'redirects' === el.getAttribute( 'data-act' ) ) {
			post( 'ecv2_import_redirects', { on: el.checked ? 1 : 0 } ).then( function ( data ) {
				D.redirects = !! data.on;
			} ).catch( function ( e ) {
				el.checked = ! el.checked;
				state.error = e.message;
				render();
			} );
		}
	} );

	/* ------------------------------------------------------------------ start */

	if ( ! D.ready ) {
		app.innerHTML = '';
		return;
	}
	if ( D.connected && 'square' === D.open ) {
		state.notice = T.connectedOk;
	}
	if ( D.failed ) {
		state.error = T.connectFailed;
	}
	if ( isOpen( state.job ) ) {
		afterStep();
	} else if ( state.job && 'done' === state.job.status ) {
		afterStep();
	} else if ( D.open && sourceOf( D.open ) ) {
		state.source = D.open;
		state.view = 'setup';
		render();
		detect( D.open, !! D.connected );
	} else {
		render();
	}
	( D.sources || [] ).forEach( function ( source ) {
		if ( ! state.detect[ source.id ] && source.id !== state.source ) {
			detect( source.id, false );
		}
	} );
}() );
