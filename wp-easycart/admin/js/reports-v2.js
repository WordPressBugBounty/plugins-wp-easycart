/**
 * EasyCart › Reports ( 6.0.2 ).
 *
 * Draws the tabs from #ecrp_config ( wp_easycart_reports::page_config() ) and the answers of AJAX
 * ecv2_reports_data. A payload has kpis, charts, tables, insights and notes, or locked + teaser for a tab the store's plan
 * does not open. Charts use Chart.js 3.
 *
 * Kept for WP EasyCart PRO and extensions: wpeasycart_admin_update_chart_data() reloads ( PRO's #location_filter calls
 * it ), and every change of dates or filters fires the jQuery event wpeasycart_reports_filters_changed with the older
 * request data ( start_date, end_date, start_date2, end_date2, range, product, country, billing_country, location_id ).
 * window.wpecReports exposes state, reload() and money().
 *
 * @since 6.0.2
 */
( function( $ ) {
	'use strict';

	var node = document.getElementById( 'ecrp_config' );
	if ( ! node || ! $ ) {
		return;
	}
	var cfg = {};
	try {
		cfg = JSON.parse( node.textContent );
	} catch ( e ) {
		return;
	}
	var T = cfg.text || {};
	var today = moment( cfg.today, 'YYYY-MM-DD' );
	var state = {
		tab: 'overview',
		start: cfg.args.start,
		end: cfg.args.end,
		preset: 'last30',
		compare: 'none',
		start2: '',
		end2: '',
		range: cfg.args.range,
		chart: '',
		product: '0',
		country: '0',
		billing: '0',
		location: '0'
	};
	var charts = [];
	var cache = {};
	var request = null;
	var lastFilters = '';
	var $panel = $( '#ecrp_panel' );

	/* ------------------------------------------------------------------ */
	/* Formatting                                                          */
	/* ------------------------------------------------------------------ */

	function esc( s ) {
		return String( null == s ? '' : s ).replace( /[&<>"']/g, function( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function money( amount, compact ) {
		var c = cfg.money || {};
		var n = parseFloat( amount );
		if ( isNaN( n ) ) {
			n = 0;
		}
		var decimals = ( null == c.decimals ) ? 2 : parseInt( c.decimals, 10 );
		var negative = n < -0.0000001;
		var abs = Math.abs( n );
		var suffix = '';
		if ( compact && abs >= 1000 ) {
			if ( abs >= 1000000 ) {
				abs = abs / 1000000;
				suffix = 'M';
			} else {
				abs = abs / 1000;
				suffix = 'k';
			}
			decimals = abs >= 100 ? 0 : 1;
		} else if ( compact ) {
			decimals = ( abs % 1 ) ? decimals : 0;
		}
		var parts = abs.toFixed( decimals ).split( '.' );
		var number = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, null == c.group ? ',' : c.group ) + ( parts[1] ? ( null == c.dec ? '.' : c.dec ) + parts[1] : '' ) + suffix;
		var symbol = ( null == c.symbol ) ? '$' : c.symbol;
		var before = ( null == c.before ) ? true : !! c.before;
		var out = ( c.code && ! compact ) ? c.code + ' ' : '';
		if ( negative && c.negBefore ) {
			out += '-';
		}
		if ( before ) {
			out += symbol;
		}
		if ( negative && ! c.negBefore ) {
			out += '-';
		}
		out += number;
		if ( ! before ) {
			out += symbol;
		}
		return out;
	}

	function number( n, decimals ) {
		var v = parseFloat( n );
		if ( isNaN( v ) ) {
			return '—';
		}
		var c = cfg.money || {};
		var parts = v.toFixed( decimals || 0 ).split( '.' );
		return parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, null == c.group ? ',' : c.group ) + ( parts[1] ? ( null == c.dec ? '.' : c.dec ) + parts[1] : '' );
	}

	function show( value, format ) {
		if ( null === value || undefined === value || '' === value ) {
			return '—';
		}
		switch ( format ) {
			case 'money':
				return money( value );
			case 'pct':
				return number( value, 1 ) + '%';
			case 'int':
				return number( value, 0 );
			case 'dec':
				return number( value, 2 );
			default:
				return String( value );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Dates                                                               */
	/* ------------------------------------------------------------------ */

	var presets = {
		today: function() { return [ today.clone(), today.clone() ]; },
		yesterday: function() { return [ today.clone().subtract( 1, 'day' ), today.clone().subtract( 1, 'day' ) ]; },
		last7: function() { return [ today.clone().subtract( 6, 'days' ), today.clone() ]; },
		last30: function() { return [ today.clone().subtract( 29, 'days' ), today.clone() ]; },
		last90: function() { return [ today.clone().subtract( 89, 'days' ), today.clone() ]; },
		thisMonth: function() { return [ today.clone().startOf( 'month' ), today.clone() ]; },
		lastMonth: function() { var m = today.clone().subtract( 1, 'month' ); return [ m.clone().startOf( 'month' ), m.clone().endOf( 'month' ) ]; },
		thisQuarter: function() { return [ today.clone().startOf( 'quarter' ), today.clone() ]; },
		lastQuarter: function() { var q = today.clone().subtract( 1, 'quarter' ); return [ q.clone().startOf( 'quarter' ), q.clone().endOf( 'quarter' ) ]; },
		thisYear: function() { return [ today.clone().startOf( 'year' ), today.clone() ]; },
		lastYear: function() { var y = today.clone().subtract( 1, 'year' ); return [ y.clone().startOf( 'year' ), y.clone().endOf( 'year' ) ]; },
		last12: function() { return [ today.clone().subtract( 11, 'months' ).startOf( 'month' ), today.clone() ]; }
	};

	function rangeText( start, end ) {
		var a = moment( start, 'YYYY-MM-DD' );
		var b = moment( end, 'YYYY-MM-DD' );
		if ( start === end ) {
			return a.format( 'MMM D, YYYY' );
		}
		return a.format( a.year() === b.year() ? 'MMM D' : 'MMM D, YYYY' ) + ' – ' + b.format( 'MMM D, YYYY' );
	}

	function setCompare() {
		var a = moment( state.start, 'YYYY-MM-DD' );
		var b = moment( state.end, 'YYYY-MM-DD' );
		if ( 'previous' === state.compare ) {
			var days = b.diff( a, 'days' ) + 1;
			state.end2 = a.clone().subtract( 1, 'day' ).format( 'YYYY-MM-DD' );
			state.start2 = a.clone().subtract( days, 'days' ).format( 'YYYY-MM-DD' );
		} else if ( 'year' === state.compare ) {
			state.start2 = a.clone().subtract( 1, 'year' ).format( 'YYYY-MM-DD' );
			state.end2 = b.clone().subtract( 1, 'year' ).format( 'YYYY-MM-DD' );
		} else {
			state.start2 = '';
			state.end2 = '';
		}
	}

	function autoRange() {
		var days = moment( state.end, 'YYYY-MM-DD' ).diff( moment( state.start, 'YYYY-MM-DD' ), 'days' ) + 1;
		return days > 730 ? 'monthly' : ( days > 92 ? 'weekly' : 'daily' );
	}

	function paintRange() {
		$( '#ecrp_range_text' ).text( rangeText( state.start, state.end ) + ( state.start2 ? '  ·  ' + T.vs + ' ' + rangeText( state.start2, state.end2 ) : '' ) );
		$( '#daily_filter' ).val( state.range );
		$( '#ecrp_compare' ).val( state.compare );
	}

	function initPicker() {
		var $range = $( '#wpeasycart_admin_report_range1' );
		if ( ! $.fn.daterangepicker ) {
			return;
		}
		var ranges = {};
		var keys = [ 'today', 'yesterday', 'last7', 'last30', 'last90', 'thisMonth', 'lastMonth', 'thisQuarter', 'lastQuarter', 'thisYear', 'lastYear', 'last12' ];
		var labels = {};
		keys.forEach( function( key ) {
			ranges[ T[ key ] ] = presets[ key ]();
			labels[ T[ key ] ] = key;
		} );
		$range.daterangepicker( {
			startDate: moment( state.start, 'YYYY-MM-DD' ),
			endDate: moment( state.end, 'YYYY-MM-DD' ),
			maxDate: today.clone(),
			ranges: ranges,
			alwaysShowCalendars: false,
			opens: 'right',
			locale: {
				format: 'MMM D, YYYY',
				applyLabel: T.apply,
				cancelLabel: T.cancel,
				customRangeLabel: T.custom,
				firstDay: parseInt( cfg.weekStart, 10 ) || 0
			}
		}, function( start, end, label ) {
			state.start = start.format( 'YYYY-MM-DD' );
			state.end = moment.min( end, today ).format( 'YYYY-MM-DD' );
			state.preset = labels[ label ] || '';
			state.range = autoRange();
			setCompare();
			paintRange();
			load();
		} );
		$range.on( 'keydown', function( e ) {
			if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				$range.trigger( 'click' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Loading                                                             */
	/* ------------------------------------------------------------------ */

	function requestData() {
		state.product = String( $( '#product_filter' ).val() || '0' );
		state.country = String( $( '#country_filter' ).val() || '0' );
		state.billing = String( $( '#billing_country_filter' ).val() || '0' );
		state.location = $( '#location_filter' ).length ? String( $( '#location_filter' ).val() || '0' ) : '0';
		return {
			tab: state.tab,
			start: state.start,
			end: state.end,
			start2: state.start2,
			end2: state.end2,
			range: state.range,
			product: state.product,
			country: state.country,
			billing_country: state.billing,
			location_id: state.location
		};
	}

	function announce( data ) {
		var older = {
			start_date: data.start,
			end_date: data.end,
			start_date2: data.start2 || 0,
			end_date2: data.end2 || 0,
			range: data.range,
			product: data.product,
			country: data.country,
			billing_country: data.billing_country,
			location_id: data.location_id
		};
		var key = JSON.stringify( older );
		if ( key !== lastFilters ) {
			lastFilters = key;
			$( document ).trigger( 'wpeasycart_reports_filters_changed', [ older ] );
		}
	}

	function load() {
		var data = requestData();
		announce( data );
		var key = JSON.stringify( data );
		writeHash();
		if ( cache[ key ] ) {
			render( cache[ key ] );
			return;
		}
		if ( request ) {
			request.abort();
		}
		$panel.addClass( 'is-loading' ).attr( 'aria-busy', 'true' );
		request = $.post( cfg.ajax, $.extend( { action: 'ecv2_reports_data', nonce: cfg.nonce }, data ) ).done( function( response ) {
			if ( response && response.success && response.data ) {
				cache[ key ] = response.data;
				render( response.data );
			} else {
				fail( response && response.data && response.data.message ? response.data.message : T.error );
			}
		} ).fail( function( xhr, status ) {
			if ( 'abort' !== status ) {
				fail( T.error );
			}
		} ).always( function() {
			$panel.removeClass( 'is-loading' ).removeAttr( 'aria-busy' );
		} );
	}

	function fail( message ) {
		destroyCharts();
		$panel.html( '<div class="ecrp3-empty is-error">' + esc( message ) + ' <button type="button" class="ecv2-btn ecv2-btn-sm" data-ecrp-retry>' + esc( T.retry ) + '</button></div>' );
	}

	/* ------------------------------------------------------------------ */
	/* Drawing                                                             */
	/* ------------------------------------------------------------------ */

	function destroyCharts() {
		charts.forEach( function( chart ) {
			try {
				chart.destroy();
			} catch ( e ) {
				/* already gone */
			}
		} );
		charts = [];
	}

	function render( payload ) {
		destroyCharts();
		$( '#ecrp_sources_card' ).attr( 'hidden', true ).appendTo( '#ec_admin_chart' );
		var html = '';
		if ( payload.history ) {
			html += '<div class="ecrp3-note is-info"><span class="dashicons dashicons-update"></span>' + esc( payload.history ) + '</div>';
		}
		if ( 'overview' === payload.tab && cfg.activity && ! cfg.activity.on ) {
			html += '<div class="ecrp3-note"><span class="dashicons dashicons-visibility"></span>' + esc( T.activityOff ) + ' <a href="' + esc( cfg.activity.url ) + '">' + esc( T.activitySwitch ) + '</a></div>';
		}
		/* Bug round 7: Cookie consent is set to a choice nothing on the site can answer, so views, searches and visits never count. */
		if ( cfg.activity && cfg.activity.on && cfg.activity.consent && ! payload.locked && -1 !== [ 'overview', 'products', 'searches', 'sources' ].indexOf( payload.tab ) ) {
			html += '<div class="ecrp3-note is-warn" role="status"><span class="dashicons dashicons-warning"></span><span class="ecrp3-note-text">' + esc( cfg.activity.consent ) + '</span> <a href="' + esc( cfg.activity.consentUrl ) + '">' + esc( T.activityConsent ) + '</a></div>';
		}
		if ( payload.locked ) {
			$panel.html( html + locked( payload ) );
			if ( 'sources' === payload.tab ) {
				placeSourcesCard();
			}
			return;
		}
		if ( payload.insights && payload.insights.length ) {
			html += '<ul class="ecrp3-insights">';
			payload.insights.forEach( function( item ) {
				html += '<li class="is-' + esc( item.tone || 'plain' ) + '">' + esc( item.text ) + '</li>';
			} );
			html += '</ul>';
		}
		if ( payload.kpis && payload.kpis.length ) {
			html += '<div class="ecrp3-kpis">';
			payload.kpis.forEach( function( kpi ) {
				html += kpiHtml( kpi );
			} );
			html += '</div>';
		}
		if ( payload.charts && payload.charts.length ) {
			html += '<div class="ecrp3-charts is-n' + Math.min( 3, payload.charts.length ) + '">';
			payload.charts.forEach( function( chart, i ) {
				html += '<div class="ecrp3-card ecrp3-chart' + ( chart.wide ? ' is-wide' : '' ) + '"><h3>' + esc( chart.title ) + '</h3><div class="ecrp3-canvas"><canvas id="ecrp_chart_' + i + '" role="img" aria-label="' + esc( chart.title ) + '"></canvas></div></div>';
			} );
			html += '</div>';
		}
		( payload.tables || [] ).forEach( function( table, i ) {
			html += tableHtml( table, i );
		} );
		( payload.notes || [] ).forEach( function( note ) {
			html += '<p class="ecrp3-foot">' + esc( note ) + '</p>';
		} );
		if ( ! payload.kpis && ! payload.charts && ! payload.tables ) {
			html += '<div class="ecrp3-empty">' + esc( T.empty ) + '</div>';
		}
		$panel.html( html );
		$panel.data( 'payload', payload );
		( payload.charts || [] ).forEach( function( chart, i ) {
			draw( document.getElementById( 'ecrp_chart_' + i ), chart );
		} );
		if ( 'sources' === payload.tab ) {
			placeSourcesCard();
		}
		$( document ).trigger( 'wpeasycart_reports_rendered', [ payload, state ] );
	}

	function placeSourcesCard() {
		var $card = $( '#ecrp_sources_card' );
		if ( $card.length ) {
			$card.removeAttr( 'hidden' ).appendTo( $panel );
		}
	}

	function kpiHtml( kpi ) {
		var change = '';
		if ( null !== kpi.change && undefined !== kpi.change && '' !== kpi.prev_display ) {
			var up = kpi.change > 0;
			var tone = ( 0 === kpi.change || ! kpi.good ) ? 'flat' : ( ( up === ( 'up' === kpi.good ) ) ? 'good' : 'bad' );
			change = '<span class="ecrp3-change is-' + tone + '"><span class="dashicons dashicons-arrow-' + ( up ? 'up' : ( 0 === kpi.change ? 'right' : 'down' ) ) + '-alt"></span>' + esc( number( Math.abs( kpi.change ), Math.abs( kpi.change ) < 10 ? 1 : 0 ) ) + '%</span>';
		} else if ( '' !== kpi.prev_display && null !== kpi.prev ) {
			change = '<span class="ecrp3-change is-flat">' + esc( T.previous ) + '</span>';
		}
		return '<div class="ecrp3-kpi" data-kpi="' + esc( kpi.key ) + '">' +
			'<div class="ecrp3-kpi-label">' + esc( kpi.label ) + '</div>' +
			'<div class="ecrp3-kpi-value">' + esc( kpi.display ) + '</div>' +
			( change || kpi.prev_display ? '<div class="ecrp3-kpi-compare">' + change + ( kpi.prev_display ? ' <span class="ecrp3-kpi-prev">' + esc( T.vs ) + ' ' + esc( kpi.prev_display ) + '</span>' : '' ) + '</div>' : '' ) +
			( kpi.hint ? '<div class="ecrp3-kpi-hint">' + esc( kpi.hint ) + '</div>' : '' ) +
			'</div>';
	}

	function tableHtml( table, i ) {
		var rows = table.rows || [];
		var html = '<div class="ecrp3-card ecrp3-table-card" data-table="' + i + '"><div class="ecrp3-card-head"><h3>' + esc( table.title ) + '</h3>';
		if ( table.csv && rows.length ) {
			html += '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecrp-csv="' + i + '" title="' + esc( T.downloadCsv ) + '"><span class="dashicons dashicons-download"></span> ' + esc( T.csv ) + '</button>';
		}
		html += '</div>';
		if ( table.note ) {
			html += '<p class="ecrp3-card-note">' + esc( table.note ) + '</p>';
		}
		if ( ! rows.length ) {
			return html + '<div class="ecrp3-empty">' + esc( table.empty || T.empty ) + '</div></div>';
		}
		html += '<div class="ecrp3-table-wrap"><table class="ecrp3-table"><thead><tr>';
		( table.columns || [] ).forEach( function( col, c ) {
			html += '<th scope="col" class="is-' + esc( col.align || 'left' ) + '"><button type="button" data-ecrp-sort="' + c + '">' + esc( col.label ) + '</button></th>';
		} );
		html += '</tr></thead><tbody>' + tableRows( table, rows ) + '</tbody></table></div>';
		if ( rows.length > PAGE_ROWS ) {
			html += '<button type="button" class="ecv2-btn ecv2-btn-sm ecrp3-more" data-ecrp-more>' + esc( ( T.showAll || 'Show all %d' ).replace( '%d', number( rows.length, 0 ) ) ) + '</button>';
		}
		return html + '</div>';
	}

	var PAGE_ROWS = 10;

	function tableRows( table, rows ) {
		var html = '';
		rows.forEach( function( row, r ) {
			html += '<tr' + ( r >= PAGE_ROWS && ! table._all ? ' hidden' : '' ) + '>';
			( table.columns || [] ).forEach( function( col ) {
				var value = row[ col.key ];
				var text = show( value, col.format || 'text' );
				if ( col.link && row[ col.link ] ) {
					text = '<a href="' + esc( row[ col.link ] ) + '">' + esc( text ) + '</a>';
				} else {
					text = esc( text );
				}
				if ( col.bar && table.max && table.max[ col.key ] ) {
					var pct = Math.max( 0, Math.min( 100, parseFloat( value ) / table.max[ col.key ] * 100 ) );
					text = '<span class="ecrp3-bar"><i style="width:' + pct.toFixed( 1 ) + '%"></i></span>' + text;
				}
				html += '<td class="is-' + esc( col.align || 'left' ) + '">' + text + '</td>';
			} );
			html += '</tr>';
		} );
		return html;
	}

	function locked( payload ) {
		var lock = payload.locked || {};
		var teaser = payload.teaser || {};
		var columns = teaser.columns || [];
		var html = '<div class="ecrp3-locked" data-feature="' + esc( lock.feature ) + '"><div class="ecrp3-card ecrp3-teaser">';
		html += '<table class="ecrp3-table"><thead><tr>';
		columns.forEach( function( col, c ) {
			html += '<th class="is-' + ( c ? 'right' : 'left' ) + '">' + esc( col ) + '</th>';
		} );
		html += '</tr></thead><tbody>';
		if ( teaser.row && teaser.row.length ) {
			html += '<tr class="is-real">';
			teaser.row.forEach( function( cell, c ) {
				html += '<td class="is-' + ( c ? 'right' : 'left' ) + '">' + esc( cell ) + '</td>';
			} );
			html += '</tr>';
		}
		for ( var r = 0; r < 4; r++ ) {
			html += '<tr class="is-fake" aria-hidden="true">';
			for ( var c = 0; c < Math.max( 1, columns.length ); c++ ) {
				html += '<td class="is-' + ( c ? 'right' : 'left' ) + '"><span style="width:' + ( c ? 40 + ( ( r * 17 + c * 11 ) % 40 ) : 70 + ( ( r * 23 ) % 50 ) ) + 'px"></span></td>';
			}
			html += '</tr>';
		}
		html += '</tbody></table>';
		html += '<p class="ecrp3-teaser-more">' + esc( teaser.row && teaser.row.length ? ( teaser.more || T.teaserNote ) : T.teaserEmpty ) + '</p>';
		html += '<div class="ecrp3-lock"><span class="dashicons dashicons-lock"></span><strong>' + esc( lock.title ) + '</strong>' + ( lock.text ? '<span>' + esc( lock.text ) + '</span>' : '' );
		if ( lock.url ) {
			html += '<a class="ecv2-btn ecv2-btn-primary" href="' + esc( lock.url ) + '" data-ecrp-lock="' + esc( payload.tab ) + '">' + esc( lock.button ) + '</a>';
		} else {
			html += '<button type="button" class="ecv2-btn ecv2-btn-primary" data-ecrp-upsell="' + esc( lock.feature ) + '" data-ecrp-lock="' + esc( payload.tab ) + '">' + esc( lock.button ) + '</button>';
		}
		html += '</div></div></div>';
		return html;
	}

	function brand() {
		var color = '';
		try {
			color = window.getComputedStyle( document.getElementById( 'ec_admin_chart' ) ).getPropertyValue( '--ec-brand' ).trim();
		} catch ( e ) {
			color = '';
		}
		return color || '#2563eb';
	}

	function alpha( color, a ) {
		var m = /^#([0-9a-f]{6})$/i.exec( color );
		if ( ! m ) {
			return 'rgba(37,99,235,' + a + ')';
		}
		var n = parseInt( m[1], 16 );
		return 'rgba(' + ( ( n >> 16 ) & 255 ) + ',' + ( ( n >> 8 ) & 255 ) + ',' + ( n & 255 ) + ',' + a + ')';
	}

	var palette = [ '#2563eb', '#0d9488', '#d97706', '#7c3aed', '#db2777', '#65a30d', '#0891b2', '#9ca3af' ];

	function draw( canvas, spec ) {
		if ( ! canvas || 'function' !== typeof window.Chart ) {
			return;
		}
		var main = brand();
		var type = spec.type || 'line';
		if ( ( 'line' === type || 'bar' === type ) && state.chart ) {
			type = state.chart;
		}
		var round = 'doughnut' === type;
		var datasets = ( spec.datasets || [] ).map( function( set, i ) {
			var compare = !! set.compare;
			var color = compare ? '#9ca3af' : ( set.color || ( i ? palette[ i % palette.length ] : main ) );
			var out = {
				label: set.label,
				data: set.data,
				titles: set.titles || null,
				borderColor: round ? '#fff' : color,
				backgroundColor: round ? ( set.colors || palette ) : ( 'bar' === type ? ( compare ? alpha( '#9ca3af', 0.5 ) : alpha( /^#/.test( color ) ? color : '#2563eb', 0.85 ) ) : alpha( /^#/.test( color ) ? color : '#2563eb', compare ? 0 : 0.12 ) ),
				borderWidth: round ? 2 : 2,
				pointRadius: ( spec.labels || [] ).length > 40 ? 0 : 2,
				pointHoverRadius: 4,
				tension: 0.25,
				fill: ! compare && 'line' === type && ! set.noFill,
				borderDash: compare && 'line' === type ? [ 5, 4 ] : []
			};
			if ( set.stack ) {
				out.stack = set.stack;
			}
			return out;
		} );
		var options = {
			responsive: true,
			maintainAspectRatio: false,
			animation: { duration: 250 },
			interaction: { mode: round ? 'nearest' : 'index', intersect: round },
			plugins: {
				legend: { display: round || datasets.length > 1, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } },
				tooltip: {
					callbacks: {
						title: function( items ) {
							if ( ! items.length ) {
								return '';
							}
							var set = items[0].dataset;
							return ( set.titles && set.titles[ items[0].dataIndex ] ) ? set.titles[ items[0].dataIndex ] : items[0].label;
						},
						label: function( item ) {
							var v = round ? item.parsed : item.parsed.y;
							var label = round ? item.label : item.dataset.label;
							var shown = spec.money ? money( v ) : ( spec.pct ? number( v, 1 ) + '%' : number( v, spec.decimals || 0 ) );
							if ( ! round && item.dataset.titles && item.dataset.titles[ item.dataIndex ] && item.datasetIndex > 0 ) {
								label = item.dataset.titles[ item.dataIndex ];
							}
							return ' ' + label + ': ' + shown;
						}
					}
				}
			}
		};
		if ( ! round ) {
			options.scales = {
				x: { stacked: !! spec.stacked, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: ( spec.labels || [] ).length > 12, autoSkipPadding: 12 } },
				y: {
					stacked: !! spec.stacked,
					beginAtZero: true,
					grid: { color: 'rgba(17,24,39,.06)' },
					ticks: {
						precision: spec.money || spec.pct ? undefined : 0,
						callback: function( value ) {
							return spec.money ? money( value, true ) : ( spec.pct ? value + '%' : number( value, 0 ) );
						}
					}
				}
			};
		} else {
			options.cutout = '62%';
		}
		charts.push( new window.Chart( canvas, { type: type, data: { labels: spec.labels || [], datasets: datasets }, options: options } ) );
	}

	/* ------------------------------------------------------------------ */
	/* Tables: sort and CSV                                                */
	/* ------------------------------------------------------------------ */

	$panel.on( 'click', '[data-ecrp-sort]', function() {
		var payload = $panel.data( 'payload' );
		var $card = $( this ).closest( '[data-table]' );
		var table = payload && payload.tables ? payload.tables[ parseInt( $card.attr( 'data-table' ), 10 ) ] : null;
		if ( ! table ) {
			return;
		}
		var c = parseInt( $( this ).attr( 'data-ecrp-sort' ), 10 );
		var col = table.columns[ c ];
		var dir = ( table._sort === c && 'desc' === table._dir ) ? 'asc' : 'desc';
		table._sort = c;
		table._dir = dir;
		table.rows.sort( function( a, b ) {
			var x = a[ col.key ];
			var y = b[ col.key ];
			var nx = parseFloat( x );
			var ny = parseFloat( y );
			var cmp = ( ! isNaN( nx ) && ! isNaN( ny ) ) ? nx - ny : String( x || '' ).localeCompare( String( y || '' ) );
			return 'desc' === dir ? -cmp : cmp;
		} );
		$card.find( 'tbody' ).html( tableRows( table, table.rows ) );
		$card.find( 'th' ).removeAttr( 'aria-sort' ).eq( c ).attr( 'aria-sort', 'desc' === dir ? 'descending' : 'ascending' );
	} );

	$panel.on( 'click', '[data-ecrp-more]', function() {
		var payload = $panel.data( 'payload' );
		var $card = $( this ).closest( '[data-table]' );
		var table = payload && payload.tables ? payload.tables[ parseInt( $card.attr( 'data-table' ), 10 ) ] : null;
		if ( table ) {
			table._all = true;
		}
		$card.find( 'tbody tr[hidden]' ).removeAttr( 'hidden' );
		$( this ).remove();
	} );

	$panel.on( 'click', '[data-ecrp-csv]', function() {
		var payload = $panel.data( 'payload' );
		var table = payload && payload.tables ? payload.tables[ parseInt( $( this ).attr( 'data-ecrp-csv' ), 10 ) ] : null;
		if ( ! table ) {
			return;
		}
		var cell = function( v ) {
			var s = String( null == v ? '' : v );
			if ( /^[=+\-@\t\r]/.test( s ) ) {
				s = "'" + s; /* never a formula in a spreadsheet */
			}
			return /[",\n]/.test( s ) ? '"' + s.replace( /"/g, '""' ) + '"' : s;
		};
		var lines = [ table.columns.map( function( col ) { return cell( col.label ); } ).join( ',' ) ];
		table.rows.forEach( function( row ) {
			lines.push( table.columns.map( function( col ) { return cell( row[ col.key ] ); } ).join( ',' ) );
		} );
		var blob = new Blob( [ '﻿' + lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8' } );
		var link = document.createElement( 'a' );
		link.href = URL.createObjectURL( blob );
		link.download = ( table.id || 'report' ) + '-' + state.start + '-' + state.end + '.csv';
		document.body.appendChild( link );
		link.click();
		setTimeout( function() {
			URL.revokeObjectURL( link.href );
			link.remove();
		}, 100 );
	} );

	/* ------------------------------------------------------------------ */
	/* Locks                                                               */
	/* ------------------------------------------------------------------ */

	function upsell( feature ) {
		if ( 'function' === typeof window.ecdv2_upsell ) {
			window.ecdv2_upsell( { context: 'reports', feature: feature } );
		} else if ( cfg.upgrade && cfg.upgrade.trial ) {
			window.location.href = cfg.upgrade.trial;
		}
	}

	$panel.on( 'click', '[data-ecrp-lock]', function() {
		$.post( cfg.ajax, { action: 'ecv2_reports_lock_click', nonce: cfg.nonce, tab: $( this ).attr( 'data-ecrp-lock' ) } );
	} );
	$panel.on( 'click', '[data-ecrp-upsell]', function() {
		upsell( $( this ).attr( 'data-ecrp-upsell' ) );
	} );
	$panel.on( 'click', '[data-ecrp-retry]', function() {
		load();
	} );

	/* ------------------------------------------------------------------ */
	/* Tabs                                                                */
	/* ------------------------------------------------------------------ */

	var $tabs = $( '#ecrp_tabs [role="tab"]' );

	function selectTab( id, focus ) {
		var $tab = $tabs.filter( '[data-tab="' + id + '"]' );
		if ( ! $tab.length ) {
			return;
		}
		state.tab = id;
		$tabs.attr( { 'aria-selected': 'false', tabindex: '-1' } );
		$tab.attr( { 'aria-selected': 'true', tabindex: '0' } );
		$panel.attr( 'aria-labelledby', $tab.attr( 'id' ) );
		$( '#ecrp_offer' ).toggle( 'overview' === id && showOffer() );
		if ( focus ) {
			$tab.trigger( 'focus' );
		}
		if ( $tab[0].scrollIntoView && $tab.closest( '.ecrp3-tabs' )[0].scrollWidth > $tab.closest( '.ecrp3-tabs' )[0].clientWidth ) {
			$tab[0].scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
		load();
	}

	$tabs.on( 'click', function() {
		selectTab( $( this ).attr( 'data-tab' ), false );
	} );
	$( '#ecrp_tabs' ).on( 'keydown', '[role="tab"]', function( e ) {
		var index = $tabs.index( this );
		var next = -1;
		if ( 'ArrowRight' === e.key ) {
			next = ( index + 1 ) % $tabs.length;
		} else if ( 'ArrowLeft' === e.key ) {
			next = ( index - 1 + $tabs.length ) % $tabs.length;
		} else if ( 'Home' === e.key ) {
			next = 0;
		} else if ( 'End' === e.key ) {
			next = $tabs.length - 1;
		}
		if ( next > -1 ) {
			e.preventDefault();
			selectTab( $tabs.eq( next ).attr( 'data-tab' ), true );
		}
	} );

	function writeHash() {
		try {
			var hash = '#tab=' + state.tab;
			if ( window.location.hash !== hash ) {
				window.history.replaceState( null, '', hash );
			}
		} catch ( e ) {
			/* no history API */
		}
	}

	/* ------------------------------------------------------------------ */
	/* Toolbar                                                             */
	/* ------------------------------------------------------------------ */

	$( '#ecrp_compare' ).on( 'change', function() {
		state.compare = $( this ).val();
		setCompare();
		paintRange();
		load();
	} );
	$( '#daily_filter' ).on( 'change', function() {
		state.range = $( this ).val();
		load();
	} );
	$( '#country_filter, #billing_country_filter' ).on( 'change', function() {
		load();
	} );
	$( '.wpeasycart_admin_chart_types' ).on( 'click', '[data-ecrp-chart]', function() {
		state.chart = $( this ).attr( 'data-ecrp-chart' );
		$( '.wpeasycart_admin_chart_types [data-ecrp-chart]' ).removeClass( 'selected' ).attr( 'aria-pressed', 'false' );
		$( this ).addClass( 'selected' ).attr( 'aria-pressed', 'true' );
		var payload = $panel.data( 'payload' );
		if ( payload ) {
			render( payload );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Menus: views and export                                             */
	/* ------------------------------------------------------------------ */

	function closeMenus() {
		$( '.ecrp3-menu-list' ).attr( 'hidden', true );
		$( '.ecrp3-menu > button' ).attr( 'aria-expanded', 'false' );
	}

	function toggleMenu( $button, $list ) {
		var open = $list.is( '[hidden]' );
		closeMenus();
		if ( open ) {
			$list.removeAttr( 'hidden' );
			$button.attr( 'aria-expanded', 'true' );
			$list.find( 'button, a' ).first().trigger( 'focus' );
		}
	}

	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecrp3-menu' ).length ) {
			closeMenus();
		}
	} ).on( 'keydown', function( e ) {
		if ( 'Escape' === e.key ) {
			closeMenus();
		}
	} );

	function viewState() {
		return { tab: state.tab, preset: state.preset, start: state.start, end: state.end, compare: state.compare, range: state.range, country: state.country, billing: state.billing, product: state.product };
	}

	function applyView( saved ) {
		if ( ! saved ) {
			return;
		}
		if ( saved.preset && presets[ saved.preset ] ) {
			var r = presets[ saved.preset ]();
			state.start = r[0].format( 'YYYY-MM-DD' );
			state.end = r[1].format( 'YYYY-MM-DD' );
		} else if ( saved.start && saved.end ) {
			state.start = saved.start;
			state.end = saved.end > cfg.today ? cfg.today : saved.end;
		}
		state.preset = saved.preset || '';
		state.compare = saved.compare || 'none';
		state.range = saved.range || autoRange();
		$( '#country_filter' ).val( saved.country || '0' );
		$( '#billing_country_filter' ).val( saved.billing || '0' );
		setCompare();
		paintRange();
		var picker = $( '#wpeasycart_admin_report_range1' ).data( 'daterangepicker' );
		if ( picker ) {
			picker.setStartDate( moment( state.start, 'YYYY-MM-DD' ) );
			picker.setEndDate( moment( state.end, 'YYYY-MM-DD' ) );
		}
		selectTab( saved.tab || 'overview', false );
	}

	/* A locked tool's chip: Update when only a newer WP EasyCart PRO is missing, else the store's plan ( page_config() locks ). */
	function lockChip( feature ) {
		var lock = ( cfg.locks && cfg.locks[ feature ] ) || {};
		return { text: lock.badge || 'Pro/Premium', update: !! lock.update };
	}

	function paintViews() {
		var $list = $( '#ecrp_views_menu' );
		var html = '';
		if ( null === cfg.views ) {
			var chip = lockChip( 'views' );
			html = '<button type="button" role="menuitem" data-ecrp-views-locked><strong>' + esc( T.views ) + ' <span class="ecrp3-chip' + ( chip.update ? ' is-update' : '' ) + '">' + esc( chip.text ) + '</span></strong><span>' + esc( T.viewSave ) + '</span></button>';
		} else {
			if ( ! cfg.views.length ) {
				html += '<p class="ecrp3-menu-empty">' + esc( T.viewNone ) + '</p>';
			}
			cfg.views.forEach( function( view ) {
				html += '<div class="ecrp3-view-row"><button type="button" role="menuitem" data-ecrp-view="' + esc( view.id ) + '"><strong>' + esc( view.name ) + '</strong></button><button type="button" class="ecrp3-view-x" data-ecrp-view-delete="' + esc( view.id ) + '" aria-label="' + esc( T.viewDelete + ' ' + view.name ) + '">&times;</button></div>';
			} );
			html += '<button type="button" role="menuitem" class="is-add" data-ecrp-view-save><span class="dashicons dashicons-plus-alt2"></span> ' + esc( T.viewSave ) + '</button>';
		}
		$list.html( html );
	}

	$( '#ecrp_views_btn' ).on( 'click', function( e ) {
		e.stopPropagation();
		paintViews();
		toggleMenu( $( this ), $( '#ecrp_views_menu' ) );
	} );
	$( '#ecrp_views_menu' ).on( 'click', '[data-ecrp-views-locked]', function() {
		closeMenus();
		upsell( 'views' );
	} ).on( 'click', '[data-ecrp-view]', function() {
		var id = $( this ).attr( 'data-ecrp-view' );
		closeMenus();
		( cfg.views || [] ).forEach( function( view ) {
			if ( String( view.id ) === id ) {
				applyView( view.state );
			}
		} );
	} ).on( 'click', '[data-ecrp-view-save]', function() {
		var name = window.prompt( T.viewName, '' );
		closeMenus();
		if ( ! name ) {
			return;
		}
		$.post( cfg.ajax, { action: 'ecv2_reports_view_save', nonce: cfg.nonce, name: name, state: JSON.stringify( viewState() ) } ).done( function( response ) {
			if ( response && response.success && response.data && response.data.views ) {
				cfg.views = response.data.views;
			}
		} );
	} ).on( 'click', '[data-ecrp-view-delete]', function( e ) {
		e.stopPropagation();
		var id = $( this ).attr( 'data-ecrp-view-delete' );
		$.post( cfg.ajax, { action: 'ecv2_reports_view_delete', nonce: cfg.nonce, id: id } ).done( function( response ) {
			if ( response && response.success && response.data && response.data.views ) {
				cfg.views = response.data.views;
				paintViews();
			}
		} );
	} );

	$( '#ecrp_export_btn' ).on( 'click', function( e ) {
		e.stopPropagation();
		toggleMenu( $( this ), $( '#ecrp_export_menu' ) );
	} );
	$( '[data-ecrp-research-chip]' ).text( cfg.research ? '' : lockChip( 'export' ).text ).toggleClass( 'is-update', ! cfg.research && lockChip( 'export' ).update ).toggle( ! cfg.research );
	$( '#ecrp_export_menu' ).on( 'click', '[data-ecrp-export]', function() {
		var kind = $( this ).attr( 'data-ecrp-export' );
		closeMenus();
		if ( 'orders' === kind ) {
			if ( 'function' === typeof window.wpeasycart_admin_export_report ) {
				window.wpeasycart_admin_export_report();
			}
		} else if ( ! cfg.research ) {
			upsell( 'export' );
		} else {
			research();
		}
	} );

	function research() {
		var $m = dialog( T.research, '<p>' + esc( T.researchHint ) + '</p><p class="ecrp3-progress" data-progress>' + esc( T.researchBusy ) + '</p><div data-files></div>', '' );
		var step = function( job ) {
			$.post( cfg.ajax, $.extend( { action: cfg.research.action, nonce: cfg.research.nonce || cfg.nonce, job: job || '' }, requestData() ) ).done( function( response ) {
				if ( ! response || ! response.success ) {
					$m.find( '[data-progress]' ).text( response && response.data && response.data.message ? response.data.message : T.error );
					return;
				}
				var d = response.data;
				if ( ! d.done ) {
					$m.find( '[data-progress]' ).text( T.researchBusy + ( d.progress ? ' ' + d.progress : '' ) );
					step( d.job );
					return;
				}
				$m.find( '[data-progress]' ).text( T.researchReady );
				var files = '';
				( d.files || [] ).forEach( function( file ) {
					files += '<a class="ecrp-dl-row" href="' + esc( file.url ) + '"><span class="ecrp-dl-text"><b>' + esc( file.name ) + '</b><span>' + esc( file.desc || '' ) + '</span></span><span class="ecrp-dl-go">' + esc( T.download ) + '</span></a>';
				} );
				$m.find( '[data-files]' ).html( '<div class="ecrp-dl-rows">' + files + '</div>' );
			} ).fail( function() {
				$m.find( '[data-progress]' ).text( T.error );
			} );
		};
		step( '' );
	}

	/* ------------------------------------------------------------------ */
	/* Dialogs: the summary email                                          */
	/* ------------------------------------------------------------------ */

	function dialog( title, body, footer ) {
		$( '#ecrp_dialog' ).remove();
		var $m = $(
			'<div class="ecv2-modal-overlay ecrp-dl ecv2-layer" id="ecrp_dialog" role="dialog" aria-modal="true" aria-labelledby="ecrp_dialog_title">' +
				'<div class="ecv2-modal ecrp-dl-modal">' +
					'<div class="ecv2-modal-header"><h2 id="ecrp_dialog_title">' + esc( title ) + '</h2><button type="button" class="ecv2-modal-close" data-close aria-label="' + esc( T.close ) + '">&times;</button></div>' +
					'<div class="ecv2-modal-body">' + body + '</div>' +
					'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' + footer + '<button type="button" class="ecv2-btn" data-close>' + esc( T.close ) + '</button></div></div>' +
				'</div>' +
			'</div>'
		);
		var opener = document.activeElement;
		var close = function() {
			$( document ).off( 'keydown.ecrpdialog' );
			$m.remove();
			if ( opener && opener.focus ) {
				opener.focus();
			}
		};
		$( 'body' ).append( $m );
		$m.on( 'click', function( e ) {
			if ( $( e.target ).is( $m ) || $( e.target ).is( '[data-close]' ) ) {
				close();
			}
		} );
		$( document ).on( 'keydown.ecrpdialog', function( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
			if ( 'Tab' === e.key ) {
				var $f = $m.find( 'button, a, input, select, textarea' ).filter( ':visible' );
				if ( $f.length ) {
					if ( e.shiftKey && document.activeElement === $f[0] ) {
						e.preventDefault();
						$f.last().trigger( 'focus' );
					} else if ( ! e.shiftKey && document.activeElement === $f[ $f.length - 1 ] ) {
						e.preventDefault();
						$f.first().trigger( 'focus' );
					}
				}
			}
		} );
		setTimeout( function() {
			$m.find( 'input, select, textarea, button' ).first().trigger( 'focus' );
		}, 30 );
		$m.data( 'close', close );
		return $m;
	}

	function showOffer() {
		return cfg.summary && ! cfg.summary.on && '' === cfg.summary.offer;
	}

	/*
	 * A toast, the admin's own ( window.ecv2_toast ) when a script on the page defines it, else the same markup
	 * ( .ecv2-toast in admin-v2.css ) in a container on <body>: .ecv2-wrap is a size container, so a fixed toast inside it
	 * would sit on the report instead of the screen.
	 */
	function toast( message, type ) {
		if ( ! message ) {
			return;
		}
		type = ( 'error' === type ) ? 'error' : 'success';
		if ( 'function' === typeof window.ecv2_toast ) {
			window.ecv2_toast( message, type );
			return;
		}
		var $holder = $( '#ecv2-toast-container' );
		if ( ! $holder.length ) {
			$holder = $( '<div id="ecv2-toast-container" class="ecv2-layer" role="status" aria-live="polite"></div>' ).appendTo( document.body );
		}
		var $toast = $( '<div class="ecv2-toast ecv2-toast-' + type + '"><span class="dashicons dashicons-' + ( 'error' === type ? 'no' : 'yes' ) + '" aria-hidden="true"></span> <span></span></div>' );
		$toast.find( 'span' ).last().text( message );
		$holder.append( $toast );
		setTimeout( function() {
			$toast.fadeOut( 300, function() {
				$( this ).remove();
			} );
		}, 'error' === type ? 7000 : 4000 );
	}

	/*
	 * JSON from a WordPress AJAX answer, also when something printed text before it ( a PHP notice from another plugin, a mail
	 * plugin's debug line ): the answer is the last {"success":… in the body.
	 */
	function answer( text ) {
		if ( text && 'object' === typeof text ) {
			return text;
		}
		text = String( null == text ? '' : text );
		try {
			return JSON.parse( text );
		} catch ( e ) {
			var at = text.lastIndexOf( '{"success":' );
			if ( at > -1 ) {
				try {
					return JSON.parse( text.slice( at ) );
				} catch ( e2 ) {
					return null;
				}
			}
		}
		return null;
	}

	/* AJAX ecv2_reports_summary: resolves with the answer's data, or rejects with the message to show ( fallback: failed ). */
	function summaryRequest( data, failed ) {
		var done = $.Deferred();
		var settle = function( response ) {
			if ( response && response.success ) {
				if ( response.data && response.data.settings ) {
					cfg.summary = response.data.settings;
				}
				done.resolve( response.data || {} );
			} else {
				done.reject( response && response.data && response.data.message ? response.data.message : ( failed || T.summarySaveFail ) );
			}
		};
		$.ajax( {
			url: cfg.ajax,
			type: 'POST',
			dataType: 'text',
			data: $.extend( { action: 'ecv2_reports_summary', nonce: cfg.nonce }, data )
		} ).done( function( text ) {
			settle( answer( text ) );
		} ).fail( function( xhr ) {
			settle( answer( xhr ? xhr.responseText : '' ) );
		} );
		return done.promise();
	}

	function summarySave( data, done ) {
		return summaryRequest( data ).then( function( d ) {
			if ( done ) {
				done( d );
			}
		}, function( message ) {
			if ( done ) {
				done( { error: message } );
			}
		} );
	}

	$( '#ecrp_offer' ).on( 'click', '[data-ecrp-offer]', function() {
		var what = $( this ).attr( 'data-ecrp-offer' );
		summarySave( { do: what }, function() {
			$( '#ecrp_offer' ).attr( 'hidden', true ).hide();
			if ( 'on' === what ) {
				summaryDialog();
			}
		} );
	} );

	function summaryDialog() {
		var s = cfg.summary || {};
		var body = '<label class="ecrp3-check"><input type="checkbox" data-s="on"' + ( s.on ? ' checked' : '' ) + '> ' + esc( T.summaryOn ) + '</label>';
		body += '<label class="ecrp3-row"><span>' + esc( T.summaryHow ) + '</span><select class="ecv2-select" data-s="frequency">';
		$.each( s.frequencies || {}, function( key, label ) {
			body += '<option value="' + esc( key ) + '"' + ( key === s.frequency ? ' selected' : '' ) + '>' + esc( label ) + '</option>';
		} );
		body += '</select></label>';
		if ( ! s.pro ) {
			body += '<p class="ecrp3-hint"><button type="button" class="ecrp3-link" data-ecrp-upsell-digest>' + esc( T.summaryProFreq ) + '</button></p>';
		}
		body += '<label class="ecrp3-row"><span>' + esc( T.summaryTo ) + '</span><textarea class="ecv2-input" rows="2" data-s="recipients" placeholder="' + esc( s.default_to ) + '">' + esc( s.recipients ) + '</textarea></label><p class="ecrp3-hint">' + esc( T.summaryToHint ) + '</p>';
		var choices = s.choices || {};
		if ( Object.keys( choices ).length ) {
			body += '<fieldset class="ecrp3-row"><legend>' + esc( T.summarySections ) + '</legend>';
			$.each( choices, function( key, label ) {
				body += '<label class="ecrp3-check"><input type="checkbox" data-s-section value="' + esc( key ) + '"' + ( ( s.sections || [] ).indexOf( key ) > -1 ? ' checked' : '' ) + '> ' + esc( label ) + '</label>';
			} );
			body += '</fieldset>';
		}
		/* The result of a test or a failed save, in the dialog; the toast on the page is what screen readers hear. */
		body += '<p class="ecrp3-hint ecrp3-summary-message" data-s-message hidden></p>';
		var $m = dialog( T.summaryTitle, body, '<button type="button" class="ecv2-btn" data-s-test>' + esc( T.summaryTest ) + '</button><button type="button" class="ecv2-btn ecv2-btn-primary" data-s-save>' + esc( T.save ) + '</button>' );
		var $save = $m.find( '[data-s-save]' );
		var $test = $m.find( '[data-s-test]' );
		var $message = $m.find( '[data-s-message]' );
		var collect = function() {
			var sections = [];
			$m.find( '[data-s-section]:checked' ).each( function() {
				sections.push( this.value );
			} );
			return { do: 'save', on: $m.find( '[data-s="on"]' ).is( ':checked' ) ? 1 : 0, frequency: $m.find( '[data-s="frequency"]' ).val(), recipients: $m.find( '[data-s="recipients"]' ).val(), sections: sections };
		};
		var say = function( text, tone ) {
			$message.text( text || '' ).toggleClass( 'is-error', 'error' === tone ).toggleClass( 'is-good', 'good' === tone ).prop( 'hidden', ! text );
		};
		/* One request at a time: both buttons wait, the one clicked says what is happening. */
		var busy = function( $button, text ) {
			$m.attr( 'aria-busy', 'true' );
			$save.add( $test ).prop( 'disabled', true );
			$button.addClass( 'is-busy' ).text( text );
		};
		var idle = function( $focus ) {
			$m.removeAttr( 'aria-busy' );
			$save.prop( 'disabled', false ).removeClass( 'is-busy' ).text( T.save );
			$test.prop( 'disabled', false ).removeClass( 'is-busy' ).text( T.summaryTest );
			if ( $focus && $.contains( document, $focus[0] ) ) {
				$focus.trigger( 'focus' ); /* a disabled button dropped the focus */
			}
		};
		$m.on( 'click', '[data-ecrp-upsell-digest]', function() {
			$m.data( 'close' )();
			upsell( 'digest' );
		} );
		$m.on( 'click', '[data-s-save]', function() {
			if ( 'true' === $m.attr( 'aria-busy' ) ) {
				return;
			}
			say( '' );
			busy( $save, T.saving );
			summaryRequest( collect(), T.summarySaveFail ).then( function( d ) {
				/* Saved: close at once; the toast says so on the page. */
				$m.data( 'close' )();
				toast( d.message || T.saved, 'success' );
			}, function( message ) {
				idle( $save );
				say( message, 'error' );
				toast( message, 'error' );
			} );
		} );
		/* A test uses the dialog's choices as they are now, without saving them. */
		$m.on( 'click', '[data-s-test]', function() {
			if ( 'true' === $m.attr( 'aria-busy' ) ) {
				return;
			}
			say( '' );
			busy( $test, T.sending );
			summaryRequest( $.extend( collect(), { do: 'test' } ), T.summaryNoReply ).then( function( d ) {
				idle( $test );
				say( d.message || T.summaryTestSent, 'good' );
				toast( d.message || T.summaryTestSent, 'success' );
			}, function( message ) {
				idle( $test );
				say( message, 'error' );
				toast( message, 'error' );
			} );
		} );
	}

	$( '#ecrp_summary_btn' ).on( 'click', summaryDialog );

	/* ------------------------------------------------------------------ */
	/* Start                                                               */
	/* ------------------------------------------------------------------ */

	window.wpecReports = { state: state, reload: load, money: money, render: render };
	/* Kept name: WP EasyCart PRO's location filter and the product picker call it. */
	window.wpeasycart_admin_update_chart_data = function() {
		load();
	};

	initPicker();
	paintRange();
	if ( showOffer() ) {
		$( '#ecrp_offer' ).removeAttr( 'hidden' ).show();
	}
	var first = ( /[#&]tab=([a-z_]+)/.exec( window.location.hash || '' ) || [] )[1];
	var initialKey = JSON.stringify( requestData() );
	cache[ initialKey ] = cfg.initial;
	if ( first && 'overview' !== first && $tabs.filter( '[data-tab="' + first + '"]' ).length ) {
		selectTab( first, false );
	} else {
		announce( requestData() );
		render( cfg.initial );
	}
}( window.jQuery ) );
