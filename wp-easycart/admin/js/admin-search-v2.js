/**
 * WP EasyCart — one admin search ( 6.0.5 ).
 *
 * The window behind the header's search box, ⌘K / Ctrl K anywhere and "/" while nothing is being typed. Two sources:
 * - the index ( pages, quick actions, every setting with its value, help articles ), fetched once per version from
 *   ecv2_search_index and kept in localStorage, so every keystroke searches it here with no request;
 * - store records ( orders, products, customers, subscriptions, catalog names and what WP EasyCart PRO adds ), asked from
 *   ecv2_search_records 150 ms after typing pauses, the older request aborted.
 * A page can add its own group ( the order screen's "This order" actions ) through window.wpecSearchPage:
 *   ( window.wpecSearchPage = window.wpecSearchPage || [] ).push( { label: 'This order', items: function() { return [ { label, words, run, key } ]; } } );
 * Public: window.wpecSearch.open( query ), .close(), .isOpen().
 * Settings: wpEasyCartSearch ( wp_easycart_admin_search::config() ).
 *
 * @since 6.0.5
 */
( function( $ ) {
	'use strict';
	var C = window.wpEasyCartSearch;
	if ( ! C || ! $ ) {
		return;
	}
	var T = C.i18n || {};
	var IX_KEY = 'wpec_search_ix_' + C.user;
	var RECENT_KEY = 'wpec_search_recent_' + C.user;
	var GROUP_LIMIT = 5;

	var S = {
		index: null,       /* prepared items */
		loading: null,     /* the index request */
		q: '',
		chip: 'all',
		rows: [],          /* selectable rows in order */
		active: 0,
		records: {},       /* query + scope => answer */
		pending: '',       /* the records request being waited on */
		xhr: null,
		timer: null,
		opener: null,
		built: false
	};

	/* ------------------------------------------------------------------ Helpers */

	function esc( s ) {
		return String( null == s ? '' : s ).replace( /[&<>"']/g, function( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}
	function fold( s ) {
		s = String( null == s ? '' : s ).toLowerCase();
		if ( s.normalize ) {
			s = s.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' );
		}
		return s;
	}
	function words( s ) {
		return fold( s ).split( /[^a-z0-9À-￿#@._\-]+/ ).filter( Boolean );
	}
	function store( key, value ) {
		try {
			if ( undefined === value ) {
				var raw = window.localStorage.getItem( key );
				return raw ? JSON.parse( raw ) : null;
			}
			window.localStorage.setItem( key, JSON.stringify( value ) );
		} catch ( e ) {}
		return null;
	}
	function kind( q ) {
		var bare = q.replace( /^#/, '' );
		if ( /^\d{1,12}$/.test( bare ) ) {
			return 'number';
		}
		if ( q.indexOf( '@' ) > -1 && q.indexOf( ' ' ) === -1 ) {
			return 'email';
		}
		if ( q.indexOf( ' ' ) === -1 && q.length >= 3 && /\d/.test( q ) && /^[A-Za-z0-9._\-\/]+$/.test( q ) ) {
			return 'code';
		}
		return 'text';
	}
	/* One edit apart ( a letter changed, added, dropped or two swapped ): "shiping", "recieve", "stirpe". */
	function near( a, b ) {
		if ( a === b ) {
			return true;
		}
		var la = a.length, lb = b.length;
		if ( Math.abs( la - lb ) > 1 ) {
			return false;
		}
		var i = 0;
		while ( i < la && i < lb && a.charAt( i ) === b.charAt( i ) ) {
			i++;
		}
		if ( la === lb ) {
			if ( a.slice( i + 1 ) === b.slice( i + 1 ) ) {
				return true;
			}
			return a.charAt( i ) === b.charAt( i + 1 ) && a.charAt( i + 1 ) === b.charAt( i ) && a.slice( i + 2 ) === b.slice( i + 2 );
		}
		return la > lb ? a.slice( i + 1 ) === b.slice( i ) : a.slice( i ) === b.slice( i + 1 );
	}
	function nearAny( list, w ) {
		for ( var i = 0; i < list.length; i++ ) {
			var word = list[ i ];
			if ( near( word, w ) || ( word.length > w.length && near( word.slice( 0, w.length ), w ) ) ) {
				return true;
			}
		}
		return false;
	}
	function startsAny( list, w ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( 0 === list[ i ].indexOf( w ) ) {
				return true;
			}
		}
		return false;
	}
	/* Marks the query's words where a word starts ( longest first ). */
	function mark( text, q ) {
		var src = String( null == text ? '' : text ), ws = words( q ).filter( function( w ) { return w.length > 1 || q.length === 1; } );
		if ( ! ws.length ) {
			return esc( src );
		}
		ws.sort( function( a, b ) { return b.length - a.length; } );
		var low = fold( src ), out = '', i = 0, plain = '';
		while ( i < src.length ) {
			var hit = 0;
			if ( 0 === i || /[^a-z0-9]/.test( low.charAt( i - 1 ) ) ) {
				for ( var j = 0; j < ws.length; j++ ) {
					if ( low.substr( i, ws[ j ].length ) === ws[ j ] ) {
						hit = ws[ j ].length;
						break;
					}
				}
			}
			if ( hit ) {
				out += esc( plain ) + '<mark>' + esc( src.substr( i, hit ) ) + '</mark>';
				plain = '';
				i += hit;
			} else {
				plain += src.charAt( i );
				i++;
			}
		}
		return out + esc( plain );
	}
	function icon( name ) {
		var p = {
			page: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
			action: '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
			setting: '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
			doc: '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
			orders: '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
			products: '<path d="m21 8-9-5-9 5v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
			customers: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
			subscriptions: '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/>',
			catalog: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			here: '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
			recent: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
			out: '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>'
		};
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ( p[ name ] || p.page ) + '</svg>';
	}

	/* ------------------------------------------------------------------ The index */

	function prepareOne( it ) {
		it._l = fold( it.l );
		it._s = fold( it.s || '' );
		it._k = fold( it.k || '' );
		it._d = fold( it.d || '' );
		it._v = fold( it.v || '' );
		it._lw = words( it.l );
		it._kw = words( ( it.k || '' ) + ' ' + ( it.s || '' ) );
		if ( 'doc' === it.t ) {
			prepareDoc( it );
		}
		return it;
	}

	/* ------------------------------------------------------------------ Help articles ( 6.0.5 )

	   Help articles are matched on what they are about, not on every word ( Matt: "email setup" found Royal Mail and Checkout
	   Fields, never the email guides ): stems ( emails = email, shipping = ship; the same rule as wp_easycart_docs_index::stem() ),
	   two words read as one ( "set up" = setup ), words that stand for each other in a store ( email: receipt, SMTP, notification
	   … ), the article's title, headings, category, summary and key words ( wp_easycart_docs_index::describe() ) weighed in that
	   order, most of the words found rather than all, a typo only in a long word, "not working" / "fix" / "error" leaning to the
	   fix-it guides and archived articles last. All in the browser: nothing typed is sent anywhere. */

	var DOC_STOP = {}, DOC_FIX = {}, DOC_SAME = {}, DOC_COMMON = {}, DOC_LOOSE = {};
	/* Words every store question can carry: they add to an article that has them but are never missing from one that does not. */
	$.each( ( 'customer client order store shop site website page wordpress easycart plugin add create make change edit update manage' ).split( ' ' ), function( _, w ) {
		DOC_LOOSE[ w ] = 1;
	} );
	$.each( ( 'a an and are as at be by can could do does doing done for from get gets getting got have has how i if in into is it its me my of on or our should so that the their them then there these this those to too up us use using want was we what when where which who why will with would you your' ).split( ' ' ), function( _, w ) {
		DOC_STOP[ w ] = 1;
	} );
	$.each( ( 'fix fixing fixed error errors problem problems issue issues broken troubleshoot troubleshooting fail fails failing failed failure not missing wrong stuck cant cannot dont doesnt isnt wont working work works help' ).split( ' ' ), function( _, w ) {
		DOC_FIX[ w ] = 1;
	} );
	$.each( [
		'email smtp receipt notification sender deliverability inbox spam mailer mandrill sendgrid postmark',
		'setup setting configure configuration install installation connect enable',
		'ship shipment delivery deliver carrier postage label fulfill fulfillment',
		'payment pay gateway card merchant processor',
		'tax vat gst taxjar taxcloud avatax',
		'coupon discount promo promotion offer voucher',
		'product item catalog sku',
		'option variant variation size color colour modifier attribute',
		'inventory stock quantity oversell',
		'subscription recurring membership member plan renewal',
		'refund return cancel cancellation chargeback',
		'customer user account login',
		'design theme layout look style css template appearance customize',
		'cache caching speed slow performance',
		'license licence registration activate activation',
		'import importer csv migrate migration woocommerce transfer',
		'download digital file',
		'analytics ga4 gtag pixel conversion',
		'abandon abandoned recover recovery',
		'wholesale b2b trade',
		'language translate translation multilingual wpml polylang',
		'404 permalink'
	], function( gi, list ) {
		$.each( list.split( ' ' ), function( _, w ) {
			DOC_SAME[ stem( w ) ] = gi;
		} );
	} );

	/* A light stem, applied until it settles ( settings = setting = set ): wp_easycart_docs_index::stem() is the same rule. */
	function stem( w ) {
		var once = stemOnce( w );
		return once === w ? once : stemOnce( once );
	}
	function stemOnce( w ) {
		var n = w.length;
		if ( n > 4 && 'ies' === w.slice( -3 ) ) {
			return w.slice( 0, -3 ) + 'y';
		}
		if ( n > 5 && 'ing' === w.slice( -3 ) ) {
			return undouble( w.slice( 0, -3 ) );
		}
		if ( n > 4 && 'ed' === w.slice( -2 ) ) {
			return undouble( w.slice( 0, -2 ) );
		}
		if ( n > 4 && /(ss|x|ch|sh)es$/.test( w ) ) {
			return w.slice( 0, -2 );
		}
		if ( n > 3 && 's' === w.slice( -1 ) && [ 'ss', 'us', 'is' ].indexOf( w.slice( -2 ) ) === -1 ) {
			return w.slice( 0, -1 );
		}
		return w;
	}
	function undouble( s ) {
		var n = s.length;
		return ( n > 2 && s.charAt( n - 1 ) === s.charAt( n - 2 ) && 'lsz'.indexOf( s.charAt( n - 1 ) ) === -1 ) ? s.slice( 0, -1 ) : s;
	}
	/* A field's stems as a set, with each two words read together too ( "Set Up" also gives setup ). */
	function stemSet( text ) {
		var raw = fold( text ).split( /[^a-z0-9]+/ ).filter( Boolean ), set = {}, list = [];
		for ( var i = 0; i < raw.length; i++ ) {
			var s = stem( raw[ i ] );
			if ( ! set[ s ] ) {
				set[ s ] = 1;
				list.push( s );
			}
			if ( i + 1 < raw.length ) {
				set[ stem( raw[ i ] + raw[ i + 1 ] ) ] = 1;
			}
		}
		return { set: set, list: list };
	}
	function prepareDoc( it ) {
		var keys = {}, list = String( it.dw || '' ).split( ' ' ).filter( Boolean );
		$.each( list, function( _, w ) {
			keys[ w ] = 1;
		} );
		it._df = [
			{ w: 10, f: stemSet( it.l ) },
			{ w: 6, f: stemSet( it.dh || '' ) },
			{ w: 4, f: stemSet( it.s || '' ) },
			{ w: 4, f: stemSet( it.d || '' ) },
			{ w: 3, f: { set: keys, list: list } }
		];
		it._dtn = $.grep( it._df[ 0 ].f.list, function( w ) { return ! DOC_STOP[ w ] && 'how' !== w; } ).length || 1; /* the title's own words */
		it._dfix = /fix common problems|troubleshoot/.test( it._s ) || /^(how to fix|troubleshoot)/.test( it._l );
		it._dhow = /^how to |^set up /.test( it._l );
		it._dold = 'archive' === it._s;
	}
	/* The query's words for help articles: concepts ( mostly found ) and what the merchant wants ( fix / how to ). */
	function docQuery( q ) {
		var raw = fold( q ).replace( /['’]/g, '' ).split( /[^a-z0-9]+/ ).filter( Boolean ), out = { concepts: [], fix: false, how: false };
		for ( var i = 0; i < raw.length; i++ ) {
			var w = raw[ i ], next = raw[ i + 1 ], last = i === raw.length - 1;
			if ( next && DOC_SAME.hasOwnProperty( stem( w + next ) ) ) {
				w = w + next; /* "set up", "e mail", "log in" */
				i++;
				last = i === raw.length - 1;
			}
			if ( DOC_FIX[ w ] ) {
				out.fix = true;
				continue;
			}
			if ( 'how' === w || 'setup' === w || 'guide' === w ) {
				out.how = true;
			}
			if ( DOC_STOP[ w ] || w.length < 2 || ( last && w.length < 3 && ! /s$/.test( q ) && out.concepts.length ) ) {
				continue; /* a word still being typed waits for its third letter */
			}
			var s = stem( w );
			out.concepts.push( { w: w, s: s, g: DOC_SAME.hasOwnProperty( s ) ? DOC_SAME[ s ] : -1, last: last } );
		}
		return out;
	}
	/* How well a help article answers each of the query's words: the best of its fields ( b, 0 = not found ) and whether the word
	   itself is there ( x: not a word standing for it, not the start of one ). */
	function docBest( it, dq, typing ) {
		var out = [];
		$.each( dq.concepts, function( _, c ) {
			var best = 0, exact = false, title = false;
			$.each( it._df, function( fi, field ) {
				var w = field.w, set = field.f.set, got = 0, i;
				if ( set[ c.s ] ) {
					got = w;
					exact = true;
				} else if ( c.g > -1 ) {
					for ( var k in set ) {
						if ( set.hasOwnProperty( k ) && DOC_SAME[ k ] === c.g ) {
							got = w * 0.6;
							break;
						}
					}
				}
				if ( ! got && c.last && typing && c.w.length >= 3 ) {
					for ( i = 0; i < field.f.list.length; i++ ) {
						if ( 0 === field.f.list[ i ].indexOf( c.w ) ) {
							got = w * 0.7;
							break;
						}
					}
				}
				if ( ! got && w >= 6 && c.w.length >= 6 ) {
					for ( i = 0; i < field.f.list.length; i++ ) {
						if ( field.f.list[ i ].length >= 6 && near( field.f.list[ i ], c.s ) ) {
							got = w * 0.4;
							break;
						}
					}
				}
				best = Math.max( best, got );
				title = title || ( 0 === fi && got > 0 );
			} );
			out.push( { b: best, x: exact, t: title } );
		} );
		return out;
	}
	/* The help articles that answer the query, best first: each word counts by how few articles have the word itself ( "receipts"
	   says more than "customers" ), a word most articles use counts less still ( DOC_COMMON, from the index ), a word no article
	   has counts for nothing, and the words found must carry at least half of the query's weight. */
	function docRank( docs, dq, typing ) {
		if ( ! dq.concepts.length ) {
			return [];
		}
		var bests = [], seen = [], weight = [], all = 0, n = docs.length, out = [], loose = [], strict = false;
		$.each( dq.concepts, function( ci, c ) {
			loose[ ci ] = !! DOC_LOOSE[ c.s ];
			strict = strict || ! loose[ ci ];
		} );
		$.each( dq.concepts, function( ci ) {
			seen[ ci ] = 0;
		} );
		$.each( docs, function( di, it ) {
			bests[ di ] = it._df ? docBest( it, dq, typing ) : [];
			$.each( bests[ di ], function( ci, m ) {
				if ( m.x ) {
					seen[ ci ]++;
				}
			} );
		} );
		$.each( dq.concepts, function( ci ) {
			var any = false;
			$.each( bests, function( di, list ) {
				if ( list[ ci ] && list[ ci ].b > 0 ) {
					any = true;
					return false;
				}
			} );
			weight[ ci ] = any ? Math.log( 1 + n / ( 1 + seen[ ci ] ) ) * ( DOC_COMMON[ dq.concepts[ ci ].s ] ? 0.15 : 1 ) : 0;
			if ( ! ( strict && loose[ ci ] ) ) {
				all += weight[ ci ];
			}
		} );
		$.each( docs, function( di, it ) {
			var total = 0, got = 0, held = 0, inTitle = 0;
			$.each( bests[ di ], function( ci, m ) {
				if ( m.t ) {
					inTitle++;
				}
				if ( m.b > 0 ) {
					var counted = ! ( strict && loose[ ci ] );
					total += m.b * weight[ ci ] * ( counted ? 1 : 0.3 );
					if ( counted ) {
						got += weight[ ci ];
						held += weight[ ci ] * m.b / 10; /* a word in the title holds the article to the query more than one in its key words */
					}
				}
			} );
			var cover = all ? got / all : 0, hold = all ? held / all : 0;
			if ( ! total || ( strict && hold < 0.25 ) ) {
				return;
			}
			/* A short title the query fills says more than a long one it touches ( "Products" over "Build a Product Page Template" ). */
			var score = total * ( 0.5 + 0.5 * cover ) * ( 1 + 0.5 * Math.min( 1, inTitle / it._dtn ) );
			if ( dq.fix && it._dfix ) {
				score *= 2.2; /* a problem described: the fix-it guides first */
			} else if ( dq.how && it._dhow ) {
				score *= 1.2;
			}
			if ( it._dold ) {
				score *= 0.5;
			}
			out.push( { s: Math.round( score * 10 ), it: it } );
		} );
		out.sort( function( a, b ) { return b.s - a.s; } );
		return out;
	}
	/* End of help articles. */
	function prepare( items ) {
		$.each( items, function( _, it ) {
			prepareOne( it );
			if ( 'docmeta' === it.t ) {
				DOC_COMMON = {};
				$.each( String( it.dc || '' ).split( ' ' ), function( _, w ) {
					if ( w ) {
						DOC_COMMON[ w ] = 1;
					}
				} );
			}
		} );
		/* WordPress's own command palette ( WordPress 6.9+ ), which Ctrl / ⌘ K opens elsewhere in wp-admin. */
		if ( T.wp_commands && window.wp && window.wp.data && window.wp.data.select && window.wp.data.select( 'core/commands' ) ) {
			items.push( prepareOne( {
				t: 'action',
				l: T.wp_commands,
				s: T.actions,
				k: 'wordpress command palette posts pages plugins',
				run: function() {
					window.wp.data.dispatch( 'core/commands' ).open();
				}
			} ) );
		}
		return items;
	}
	function loadIndex() {
		if ( S.index ) {
			return $.Deferred().resolve().promise();
		}
		if ( S.loading ) {
			return S.loading;
		}
		var cached = store( IX_KEY );
		if ( cached && cached.v === C.version && $.isArray( cached.items ) ) {
			S.index = prepare( cached.items );
			return $.Deferred().resolve().promise();
		}
		S.loading = $.ajax( { url: C.ajax, method: 'GET', dataType: 'json', data: { action: 'ecv2_search_index', nonce: C.nonce } } ).then( function( r ) {
			var items = ( r && r.success && r.data && $.isArray( r.data.items ) ) ? r.data.items : [];
			store( IX_KEY, { v: C.version, items: items } );
			S.index = prepare( items );
		}, function() {
			S.index = [];
		} ).always( function() {
			S.loading = null;
		} );
		return S.loading;
	}

	/* How well an item matches: 0 = not at all. Every word must be found ( a typo of one letter counts in names ). */
	function score( it, q, ws ) {
		var total = 0;
		if ( it._l === q ) {
			total += 1000;
		} else if ( 0 === it._l.indexOf( q ) ) {
			total += 500;
		} else if ( q.length > 2 && it._l.indexOf( q ) > -1 ) {
			total += 220;
		} else if ( q.length >= 4 && it._lw.length && q.indexOf( ' ' ) === -1 && ( near( it._lw[ 0 ], q ) || ( it._lw[ 0 ].length > q.length && near( it._lw[ 0 ].slice( 0, q.length ), q ) ) ) ) {
			total += 180; /* a typo of the name's first word: "shiping" */
		}
		for ( var i = 0; i < ws.length; i++ ) {
			var w = ws[ i ], s = 0;
			if ( startsAny( it._lw, w ) ) {
				s = 120;
			} else if ( w.length > 1 && it._l.indexOf( w ) > -1 ) {
				s = 70;
			} else if ( w.length > 1 && it._k.indexOf( w ) > -1 ) {
				s = 45;
			} else if ( w.length > 1 && it._s.indexOf( w ) > -1 ) {
				s = 30;
			} else if ( w.length > 1 && it._v.indexOf( w ) > -1 ) {
				s = 25;
			} else if ( w.length > 2 && it._d.indexOf( w ) > -1 ) {
				s = 12;
			} else if ( w.length >= 4 && nearAny( it._lw, w ) ) {
				s = 60;
			} else if ( w.length >= 4 && nearAny( it._kw, w ) ) {
				s = 20;
			} else {
				return 0;
			}
			total += s;
		}
		if ( 'action' === it.t ) {
			total += 40;
		} else if ( 'page' === it.t ) {
			total += 30;
		} else if ( 'doc' === it.t ) {
			total -= 15;
		}
		if ( 'settings' === C.context && 'setting' === it.t ) {
			total += 60;
		}
		return total - Math.min( 20, it._l.length / 6 );
	}

	/* The page's own groups ( window.wpecSearchPage ). */
	function pageGroups( q, ws ) {
		var out = [];
		$.each( window.wpecSearchPage || [], function( gi, provider ) {
			var list = [];
			try {
				list = ( 'function' === typeof provider.items ) ? provider.items() : ( provider.items || [] );
			} catch ( e ) {
				list = [];
			}
			var rows = [];
			$.each( list || [], function( _, a ) {
				if ( ! a || ! a.label ) {
					return;
				}
				var hay = fold( a.label + ' ' + ( a.words || '' ) ), ok = true;
				for ( var i = 0; i < ws.length; i++ ) {
					if ( hay.indexOf( ws[ i ] ) === -1 ) {
						ok = false;
						break;
					}
				}
				if ( ok ) {
					rows.push( { type: 'here', title: a.label, key: a.key || '', run: a.run, url: a.url || '' } );
				}
			} );
			if ( rows.length ) {
				out.push( { key: 'page' + gi, label: provider.label || T.this_page, rows: rows, rank: q ? 900 : 950 } );
			}
		} );
		return out;
	}

	function localGroups( q ) {
		var ws = words( q ), fq = fold( q ), groups = { action: [], page: [], setting: [], doc: [] }, dq = docQuery( q ), typing = ! /\s$/.test( q );
		var docs = [];
		$.each( S.index || [], function( _, it ) {
			if ( 'doc' === it.t ) {
				docs.push( it );
				return;
			}
			var s = score( it, fq, ws );
			if ( s > 0 && groups[ it.t ] ) {
				groups[ it.t ].push( { s: s, it: it } );
			}
		} );
		/* Help articles ( 6.0.5 ): the ones close to the best; a question ( fix, how to, two words or more ) moves them up the groups. */
		groups.doc = docRank( docs, dq, typing );
		if ( groups.doc.length ) {
			var best = groups.doc[ 0 ].s;
			groups.doc = $.grep( groups.doc, function( m ) { return m.s >= best * 0.4; } );
			if ( dq.fix || dq.how || dq.concepts.length > 1 ) {
				$.each( groups.doc, function( _, m ) {
					m.s = Math.round( m.s * 1.5 );
				} );
			}
		}
		var defs = [
			{ key: 'actions', type: 'action', label: T.actions },
			{ key: 'pages', type: 'page', label: T.pages },
			{ key: 'settings', type: 'setting', label: T.settings },
			{ key: 'help', type: 'doc', label: T.help }
		];
		var out = [];
		$.each( defs, function( _, d ) {
			var list = groups[ d.type ];
			if ( ! list.length ) {
				return;
			}
			list.sort( function( a, b ) { return b.s - a.s || a.it.l.length - b.it.l.length; } );
			/* With strong matches, rows found only in a description are noise ( "stripe" in a setting's help text ). Help articles
			   have their own floor above. */
			if ( list[ 0 ].s >= 200 && 'doc' !== d.type ) {
				var floor = list[ 0 ].s / 4;
				list = $.grep( list, function( m ) { return m.s >= floor; } );
			}
			out.push( {
				key: d.key,
				label: d.label,
				rank: list[ 0 ].s,
				rows: $.map( list, function( m ) { return itemRow( m.it ); } )
			} );
		} );
		return out;
	}

	function itemRow( it ) {
		return {
			type: it.t,
			title: it.l,
			sub: 'action' === it.t ? '' : ( it.s || '' ),
			desc: it.d || '',
			value: it.v || '',
			chip: it.c || '',
			url: it.u,
			newTab: !! it.n,
			key: it.key || '',
			pg: it.pg || '',
			run: it.run || null
		};
	}

	/* ------------------------------------------------------------------ Records */

	function recordKey( q, scope ) {
		return scope + '|' + q;
	}
	function wantsRecords( q ) {
		if ( ! C.records ) {
			return false;
		}
		var k = kind( q );
		return 'text' !== k || q.length >= 2;
	}
	function askRecords( q, scope ) {
		var key = recordKey( q, scope );
		if ( S.records[ key ] || ! wantsRecords( q ) ) {
			return;
		}
		clearTimeout( S.timer );
		S.pending = key;
		S.timer = setTimeout( function() {
			if ( S.xhr ) {
				S.xhr.abort();
			}
			S.xhr = $.ajax( { url: C.ajax, method: 'POST', dataType: 'json', data: { action: 'ecv2_search_records', nonce: C.nonce, q: q, scope: scope } } );
			S.xhr.done( function( r ) {
				S.records[ key ] = ( r && r.success && r.data ) ? r.data : { groups: [], failed: true };
			} ).fail( function( x, status ) {
				if ( 'abort' !== status ) {
					S.records[ key ] = { groups: [], failed: true };
				}
			} ).always( function() {
				if ( S.pending === key && S.records[ key ] ) {
					S.pending = '';
					render( true );
				}
			} );
		}, 150 );
	}
	function recordGroups( q, scope ) {
		var answer = S.records[ recordKey( q, scope ) ] || null;
		if ( ! answer ) {
			return { groups: [], waiting: wantsRecords( q ), answer: null };
		}
		var k = kind( q ), groups = [];
		$.each( answer.groups || [], function( i, g ) {
			groups.push( {
				key: g.key,
				label: g.label,
				more: g.more || '',
				rank: ( 'text' === k ? 260 : 2000 ) - i,
				record: true,
				rows: $.map( g.items || [], function( r ) {
					return { type: 'record', group: g.key, title: r.title, sub: r.sub || '', url: r.url, badge: r.badge || null, thumb: r.thumb || '', hit: !! r.hit, actions: r.actions || null };
				} )
			} );
		} );
		if ( 'orders' === C.context || 'order' === C.context || 'products' === C.context || 'product' === C.context || 'users' === C.context ) {
			$.each( groups, function( _, g ) {
				g.rank += 200;
			} );
		}
		return { groups: groups, waiting: false, answer: answer };
	}

	/* ------------------------------------------------------------------ Recent */

	function recentList() {
		var list = store( RECENT_KEY );
		return $.isArray( list ) ? list : [];
	}
	function remember( entry ) {
		if ( ! entry || ! entry.url || ! entry.label ) {
			return;
		}
		var bare = bareUrl( entry.url );
		var list = $.grep( recentList(), function( r ) {
			return bareUrl( r.url ) !== bare;
		} );
		list.unshift( { label: String( entry.label ).slice( 0, 120 ), sub: String( entry.sub || '' ).slice( 0, 80 ), url: entry.url, at: Date.now() } );
		store( RECENT_KEY, list.slice( 0, 8 ) );
	}
	function ago( at ) {
		var s = Math.max( 0, ( Date.now() - at ) / 1000 ), unit, n;
		if ( s < 60 ) {
			return T.just_now || '';
		}
		if ( window.Intl && Intl.RelativeTimeFormat ) {
			try {
				var rtf = new Intl.RelativeTimeFormat( document.documentElement.lang || undefined, { numeric: 'auto' } );
				if ( s < 3600 ) {
					return rtf.format( -Math.round( s / 60 ), 'minute' );
				}
				if ( s < 86400 ) {
					return rtf.format( -Math.round( s / 3600 ), 'hour' );
				}
				return rtf.format( -Math.round( s / 86400 ), 'day' );
			} catch ( e ) {}
		}
		n = s < 3600 ? Math.round( s / 60 ) : ( s < 86400 ? Math.round( s / 3600 ) : Math.round( s / 86400 ) );
		unit = s < 3600 ? 'min' : ( s < 86400 ? 'h' : 'd' );
		return ( T.ago || '%s' ).replace( '%s', n + ' ' + unit );
	}

	/* ------------------------------------------------------------------ The window */

	function build() {
		if ( S.built ) {
			return;
		}
		S.built = true;
		var html = '<div class="wpec-search" id="wpec_search" hidden>'
			+ '<div class="wpec-search-backdrop" data-wpec-search-close></div>'
			+ '<div class="wpec-search-panel" role="dialog" aria-modal="true" aria-label="' + esc( T.label ) + '">'
			+ '<div class="wpec-search-top">' + icon( 'search' )
			+ '<input type="text" id="wpec_search_input" class="wpec-search-input" role="combobox" aria-expanded="true" aria-controls="wpec_search_list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="' + esc( T.placeholder ) + '" aria-label="' + esc( T.label ) + '" />'
			+ '<span class="wpec-search-spin" aria-hidden="true" hidden></span>'
			+ '<button type="button" class="wpec-search-x" data-wpec-search-close aria-label="' + esc( T.close ) + '"><kbd>esc</kbd></button>'
			+ '</div>'
			+ '<div class="wpec-search-chips" role="toolbar" aria-label="' + esc( T.label ) + '"></div>'
			+ '<div class="wpec-search-list" id="wpec_search_list" role="listbox" aria-label="' + esc( T.label ) + '"></div>'
			+ '<div class="screen-reader-text wpec-search-status" role="status" aria-live="polite"></div>'
			+ '<div class="wpec-search-foot"><span><kbd>↑</kbd><kbd>↓</kbd> ' + esc( T.move ) + '</span><span><kbd>↵</kbd> ' + esc( T.open ) + '</span><span><kbd>' + esc( C.mod ) + '</kbd><kbd>↵</kbd> ' + esc( T.new_tab ) + '</span><span><kbd>Tab</kbd> ' + esc( T.next_group ) + '</span><span class="wpec-search-foot-docs">' + icon( 'doc' ) + ' ' + esc( T.docs ) + ' ' + esc( T.opens_docs ) + '</span></div>'
			+ '</div></div>';
		$( 'body' ).append( html );
		var $in = $( '#wpec_search_input' );
		$in.on( 'input', function() {
			S.q = String( this.value || '' );
			S.chip = 'all';
			S.active = 0;
			render();
		} );
		$in.on( 'keydown', keys );
		$( '#wpec_search' ).on( 'click', '[data-wpec-search-close]', close );
		$( '#wpec_search' ).on( 'click', '.wpec-search-chip', function() {
			S.chip = String( $( this ).attr( 'data-chip' ) || 'all' );
			S.active = 0;
			render();
			$in.trigger( 'focus' );
		} );
		$( '#wpec_search' ).on( 'mousemove', '.wpec-search-row', function() {
			var i = parseInt( $( this ).attr( 'data-i' ), 10 );
			if ( i !== S.active && ! isNaN( i ) ) {
				S.active = i;
				paintActive( false );
			}
		} );
		$( '#wpec_search' ).on( 'click', '.wpec-search-row', function( e ) {
			if ( $( e.target ).closest( '.wpec-search-act' ).length ) {
				return;
			}
			var i = parseInt( $( this ).attr( 'data-i' ), 10 );
			if ( ! isNaN( i ) ) {
				e.preventDefault();
				go( S.rows[ i ], e.ctrlKey || e.metaKey || e.shiftKey );
			}
		} );
		$( '#wpec_search' ).on( 'click', '.wpec-search-act', function( e ) {
			var href = $( this ).attr( 'href' );
			if ( ! href ) {
				return;
			}
			if ( '1' !== $( this ).attr( 'data-new' ) && ! ( e.ctrlKey || e.metaKey || e.shiftKey ) ) {
				e.preventDefault();
				remember( { label: $( this ).closest( '.wpec-search-row' ).find( '.wpec-search-title' ).text(), sub: '', url: $( this ).closest( '.wpec-search-row' ).attr( 'data-url' ) } );
				window.location.href = href;
			}
		} );
		/* Focus stays inside the window. */
		$( '#wpec_search' ).on( 'keydown', function( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				close();
			} else if ( 'Tab' === e.key && e.target !== $in[ 0 ] ) {
				var $f = $( '#wpec_search' ).find( 'input, button, a[href]' ).filter( ':visible' );
				var first = $f.first()[ 0 ], last = $f.last()[ 0 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );
	}

	function open( q ) {
		build();
		var $w = $( '#wpec_search' );
		if ( $w.prop( 'hidden' ) ) {
			S.opener = document.activeElement;
			$w.prop( 'hidden', false );
			$( 'html' ).addClass( 'wpec-search-open' );
		}
		S.q = 'string' === typeof q ? q : '';
		S.chip = 'all';
		S.active = 0;
		$( '#wpec_search_input' ).val( S.q );
		render();
		setTimeout( function() {
			var input = document.getElementById( 'wpec_search_input' );
			if ( input ) {
				input.focus();
				input.setSelectionRange( input.value.length, input.value.length );
			}
		}, 10 );
		if ( ! S.index ) {
			$( '.wpec-search-spin' ).prop( 'hidden', false );
			loadIndex().always( function() {
				$( '.wpec-search-spin' ).prop( 'hidden', true );
				if ( isOpen() ) {
					render( true );
				}
			} );
		}
		return false;
	}
	function close() {
		var $w = $( '#wpec_search' );
		if ( ! $w.length || $w.prop( 'hidden' ) ) {
			return false;
		}
		$w.prop( 'hidden', true );
		$( 'html' ).removeClass( 'wpec-search-open' );
		clearTimeout( S.timer );
		if ( S.xhr ) {
			S.xhr.abort();
		}
		if ( S.opener && S.opener.focus && document.body.contains( S.opener ) && shown( S.opener ) && ! $( S.opener ).closest( '.ecsh-tb-search' ).length ) {
			try {
				S.opener.focus();
			} catch ( e ) {}
		}
		return false;
	}
	function isOpen() {
		var w = document.getElementById( 'wpec_search' );
		return !! ( w && ! w.hidden );
	}

	/* ------------------------------------------------------------------ Drawing */

	function render( keep ) {
		var q = $.trim( S.q ), html = '', groups = [], rows = [], status = '';
		var $list = $( '#wpec_search_list' );
		if ( ! $list.length ) {
			return;
		}
		if ( ! keep ) {
			S.active = 0;
		}
		if ( '' === q ) {
			groups = emptyGroups();
		} else {
			var local = S.index ? localGroups( q ) : [];
			var page = pageGroups( q, words( q ) );
			var rec = recordGroups( q, '' );
			if ( ! rec.answer && rec.waiting ) {
				askRecords( q, '' );
			}
			groups = page.concat( local, rec.groups );
			groups.sort( function( a, b ) { return b.rank - a.rank; } );
			chips( groups );
			if ( 'all' !== S.chip ) {
				var picked = $.grep( groups, function( g ) { return g.key === S.chip; } );
				/* A record group's chip shows up to 20 of them ( asked with that scope ), the five already here meanwhile. */
				if ( picked.length && picked[ 0 ].record ) {
					var scoped = recordGroups( q, S.chip );
					if ( scoped.answer ) {
						picked = $.grep( scoped.groups, function( g ) { return g.key === S.chip; } );
						$.each( picked, function( _, g ) {
							g.record = true;
						} );
					} else {
						askRecords( q, S.chip );
					}
				}
				groups = picked;
			}
			/* An exact record ( an order number, a customer's email, a SKU ) leads, with what can be done with it. */
			var top = null;
			$.each( rec.groups, function( _, g ) {
				$.each( g.rows, function( _, r ) {
					if ( r.hit && ! top ) {
						top = r;
					}
				} );
			} );
			if ( top && 'all' === S.chip ) {
				html += '<div class="wpec-search-group" role="presentation"><div class="wpec-search-top-hit">' + rowHtml( top, rows.length, q, true ) + '</div></div>';
				rows.push( top );
			}
			$.each( groups, function( _, g ) {
				var shown = 'all' === S.chip ? g.rows.slice( 0, GROUP_LIMIT ) : g.rows.slice( 0, 30 );
				if ( top ) {
					shown = $.grep( shown, function( r ) { return r !== top; } );
				}
				if ( ! shown.length ) {
					return;
				}
				html += '<div class="wpec-search-group" role="group" aria-label="' + esc( g.label ) + '" data-group="' + esc( g.key ) + '">'
					+ '<div class="wpec-search-group-head" aria-hidden="true">' + esc( g.label ) + '</div>';
				$.each( shown, function( _, r ) {
					r.groupKey = g.key;
					html += rowHtml( r, rows.length, q, false );
					rows.push( r );
				} );
				if ( 'all' === S.chip && ( g.rows.length > GROUP_LIMIT || ( g.record && g.more ) ) ) {
					html += '<button type="button" class="wpec-search-more wpec-search-chip" data-chip="' + esc( g.key ) + '">' + esc( T.see_all ) + ' ' + esc( g.label.toLowerCase() ) + ' →</button>';
				} else if ( g.record && g.more && ( 'all' !== S.chip || g.rows.length > GROUP_LIMIT ) ) {
					html += '<a class="wpec-search-more" href="' + esc( g.more ) + '">' + esc( T.see_all ) + ' ' + esc( g.label.toLowerCase() ) + ' →</a>';
				}
				html += '</div>';
			} );
			if ( rec.waiting && ! rec.answer ) {
				html += '<div class="wpec-search-note is-waiting" role="presentation"><span class="wpec-search-dot"></span>' + esc( T.looking ) + '</div>';
			} else if ( rec.answer && rec.answer.failed ) {
				html += '<div class="wpec-search-note" role="presentation">' + esc( T.failed ) + '</div>';
			} else if ( rec.answer && rec.answer.partial ) {
				html += '<div class="wpec-search-note" role="presentation">' + esc( T.partial ) + '</div>';
			}
			if ( ! S.index && S.loading ) {
				html += '<div class="wpec-search-note is-waiting" role="presentation"><span class="wpec-search-dot"></span>' + esc( T.loading ) + '</div>';
			}
			if ( ! rows.length && ! ( rec.waiting && ! rec.answer ) && ( S.index || ! S.loading ) ) {
				html += '<div class="wpec-search-empty">' + esc( ( T.none || '%s' ).replace( '%s', q ) ) + '</div>';
			}
			/* The help site's own search, always last. */
			var docs = { type: 'docsearch', title: ( T.docs_for || '%s' ).replace( '%s', q ), url: C.docs_search + encodeURIComponent( q ), newTab: true };
			if ( 'all' === S.chip || 'help' === S.chip ) {
				html += '<div class="wpec-search-group" role="group" aria-label="' + esc( T.help ) + '">' + rowHtml( docs, rows.length, '', false ) + '</div>';
				rows.push( docs );
			}
			status = ( T.results || '%d' ).replace( '%d', String( Math.max( 0, rows.length - 1 ) ) );
		}
		if ( '' === q ) {
			$( '.wpec-search-chips' ).empty().prop( 'hidden', true );
			$.each( groups, function( _, g ) {
				html += '<div class="wpec-search-group" role="group" aria-label="' + esc( g.label ) + '"><div class="wpec-search-group-head" aria-hidden="true">' + esc( g.label ) + '</div>';
				$.each( g.rows, function( _, r ) {
					html += rowHtml( r, rows.length, '', false );
					rows.push( r );
				} );
				html += '</div>';
			} );
			html += '<div class="wpec-search-tip"><b>' + esc( T.tip_label ) + '</b> ' + esc( T.tip ) + '</div>';
		}
		S.rows = rows;
		if ( S.active >= rows.length ) {
			S.active = Math.max( 0, rows.length - 1 );
		}
		$list.html( html );
		paintActive( ! keep );
		if ( '' !== status ) {
			$( '.wpec-search-status' ).text( status );
		}
	}

	function bareUrl( url ) {
		return String( url || '' ).replace( /[?&]wp_easycart_nonce=[^&#]*/, '' ).replace( /#.*$/, '' ).replace( /^https?:\/\/[^\/]+/, '' );
	}
	function emptyGroups() {
		var out = [], here = bareUrl( window.location.href );
		var recent = $.grep( recentList(), function( r ) { return bareUrl( r.url ) !== here; } );
		if ( recent.length ) {
			out.push( {
				key: 'recent',
				label: T.recent,
				rows: $.map( recent.slice( 0, 5 ), function( r ) {
					return { type: 'recent', title: r.label, sub: r.sub, url: r.url, when: r.at };
				} )
			} );
		}
		$.each( pageGroups( '', [] ), function( _, g ) {
			g.rows = g.rows.slice( 0, 6 );
			out.push( g );
		} );
		var acts = $.grep( S.index || [], function( it ) { return 'action' === it.t; } );
		if ( acts.length ) {
			out.push( { key: 'actions', label: T.actions, rows: $.map( acts.slice( 0, 6 ), itemRow ) } );
		}
		return out;
	}

	function chips( groups ) {
		var $c = $( '.wpec-search-chips' ), html = '';
		if ( groups.length < 2 ) {
			$c.empty().prop( 'hidden', true );
			return;
		}
		html += '<button type="button" class="wpec-search-chip' + ( 'all' === S.chip ? ' is-on' : '' ) + '" data-chip="all" aria-pressed="' + ( 'all' === S.chip ? 'true' : 'false' ) + '">' + esc( T.all ) + '</button>';
		$.each( groups, function( _, g ) {
			var n = g.rows.length;
			html += '<button type="button" class="wpec-search-chip' + ( g.key === S.chip ? ' is-on' : '' ) + '" data-chip="' + esc( g.key ) + '" aria-pressed="' + ( g.key === S.chip ? 'true' : 'false' ) + '">' + esc( g.label ) + ' <span>' + ( g.record && g.more ? n + '+' : n ) + '</span></button>';
		} );
		$c.html( html ).prop( 'hidden', false );
	}

	function rowHtml( r, i, q, big ) {
		var ic = 'record' === r.type ? r.group : r.type, right = '', sub = r.sub || '';
		if ( 'docsearch' === r.type ) {
			ic = 'search';
		}
		if ( r.thumb ) {
			ic = '';
		}
		if ( r.value ) {
			right += '<span class="wpec-search-value" title="' + esc( r.value ) + '">' + esc( r.value ) + '</span>';
		}
		if ( r.chip ) {
			right += '<span class="wpec-search-plan is-' + esc( r.chip ) + '">' + esc( T[ r.chip ] || r.chip ) + '</span>';
		}
		if ( r.badge && r.badge.text ) {
			right += '<span class="wpec-search-badge"' + ( r.badge.color ? ' style="--wpec-badge:' + esc( r.badge.color ) + '"' : '' ) + '>' + esc( r.badge.text ) + '</span>';
		}
		if ( r.key && 'here' === r.type ) {
			right += '<kbd>' + esc( r.key ) + '</kbd>';
		}
		if ( 'recent' === r.type && r.when ) {
			right += '<span class="wpec-search-when">' + esc( ago( r.when ) ) + '</span>';
		}
		if ( r.newTab || 'doc' === r.type ) {
			right += '<span class="wpec-search-out">' + icon( 'out' ) + '</span>';
		}
		if ( 'doc' === r.type && r.desc ) {
			sub = r.desc;
		}
		var acts = '';
		if ( big && r.actions && r.actions.length ) {
			acts = '<div class="wpec-search-acts">';
			$.each( r.actions, function( _, a ) {
				acts += '<a class="wpec-search-act" href="' + esc( a.url ) + '"' + ( a['new'] ? ' target="_blank" rel="noopener" data-new="1"' : '' ) + '>' + esc( a.label ) + '</a>';
			} );
			acts += '</div>';
		}
		return '<div class="wpec-search-row' + ( big ? ' is-big' : '' ) + ' is-' + esc( r.type ) + '" id="wpec_search_opt_' + i + '" role="option" aria-selected="false" data-i="' + i + '" data-url="' + esc( r.url || '' ) + '">'
			+ ( r.thumb ? '<span class="wpec-search-thumb"><img src="' + esc( r.thumb ) + '" alt="" loading="lazy" /></span>' : '<span class="wpec-search-icon">' + icon( ic ) + '</span>' )
			+ '<span class="wpec-search-main"><span class="wpec-search-title">' + mark( r.title, q ) + '</span>' + ( sub ? '<span class="wpec-search-sub">' + mark( sub, 'doc' === r.type ? q : '' ) + '</span>' : '' ) + acts + '</span>'
			+ ( right ? '<span class="wpec-search-right">' + right + '</span>' : '' )
			+ '</div>';
	}

	function paintActive( scroll ) {
		var $rows = $( '#wpec_search_list .wpec-search-row' );
		$rows.removeClass( 'is-active' ).attr( 'aria-selected', 'false' );
		var $row = $rows.filter( '[data-i="' + S.active + '"]' );
		$row.addClass( 'is-active' ).attr( 'aria-selected', 'true' );
		$( '#wpec_search_input' ).attr( 'aria-activedescendant', $row.length ? $row.attr( 'id' ) : null );
		if ( $row.length && false !== scroll && $row[ 0 ].scrollIntoView ) {
			$row[ 0 ].scrollIntoView( { block: 'nearest' } );
		}
	}

	function keys( e ) {
		var n = S.rows.length;
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			S.active = n ? ( S.active + 1 ) % n : 0;
			paintActive();
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			S.active = n ? ( S.active - 1 + n ) % n : 0;
			paintActive();
		} else if ( 'Enter' === e.key ) {
			e.preventDefault();
			if ( S.rows[ S.active ] ) {
				go( S.rows[ S.active ], e.ctrlKey || e.metaKey || e.shiftKey );
			}
		} else if ( 'Tab' === e.key && n > 1 ) {
			/* Tab: the first row of the next group ( Shift+Tab: the previous one ). */
			var $rows = $( '#wpec_search_list .wpec-search-row' ), $cur = $rows.filter( '[data-i="' + S.active + '"]' ), $groups = $( '#wpec_search_list .wpec-search-group' );
			var at = $groups.index( $cur.closest( '.wpec-search-group' ) ), to = e.shiftKey ? at - 1 : at + 1;
			if ( to < 0 ) {
				to = $groups.length - 1;
			} else if ( to >= $groups.length ) {
				to = 0;
			}
			var $first = $groups.eq( to ).find( '.wpec-search-row' ).first();
			if ( $first.length ) {
				e.preventDefault();
				S.active = parseInt( $first.attr( 'data-i' ), 10 ) || 0;
				paintActive();
			}
		} else if ( 'Home' === e.key && ( e.ctrlKey || e.metaKey ) ) {
			e.preventDefault();
			S.active = 0;
			paintActive();
		}
	}

	/* ------------------------------------------------------------------ Going */

	function go( r, newTab ) {
		if ( ! r ) {
			return;
		}
		if ( r.run ) {
			close();
			r.run();
			return;
		}
		if ( ! r.url ) {
			return;
		}
		if ( 'record' === r.type || 'page' === r.type || 'setting' === r.type ) {
			remember( { label: r.title, sub: 'record' === r.type ? ( findGroupLabel( r.group ) || '' ) : r.sub, url: r.url } );
		}
		if ( newTab || r.newTab ) {
			window.open( r.url, '_blank', 'noopener' );
			if ( ! newTab ) {
				close();
			}
			return;
		}
		if ( samePage( r ) ) {
			close();
			return;
		}
		window.location.href = r.url;
	}
	function findGroupLabel( key ) {
		var label = '';
		$( '#wpec_search_list .wpec-search-group[data-group="' + key + '"]' ).each( function() {
			label = $( this ).attr( 'aria-label' ) || '';
		} );
		return label;
	}
	/* A quick action ending #wpec-add-new: the list's own Add button ( on this page now, or after the list loads ). */
	function addNew() {
		var btn = document.querySelector( '[data-ecv2-add-new]' );
		if ( btn ) {
			btn.click();
			return true;
		}
		return false;
	}
	/* A setting or section on the settings page being viewed scrolls to it and lights it up instead of reloading. */
	function samePage( r ) {
		var url = String( r.url );
		if ( /#wpec-add-new$/.test( url ) && bareUrl( url ) === bareUrl( window.location.href ) && addNew() ) {
			return true;
		}
		var $wrap = $( '#ecst' );
		if ( 'setting' === r.type && r.pg && $wrap.length && String( $wrap.attr( 'data-page' ) ) === r.pg ) {
			if ( r.key && window.ecst && window.ecst.jump && window.ecst.jump( r.key ) ) {
				return true;
			}
			var hash = r.url.indexOf( '#' ) > -1 ? r.url.slice( r.url.indexOf( '#' ) + 1 ) : '';
			var el = hash ? document.getElementById( decodeURIComponent( hash ) ) : null;
			if ( el ) {
				if ( window.ecst && window.ecst.scrollTo ) {
					window.ecst.scrollTo( el, 'start' );
				} else {
					el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
				return true;
			}
		}
		var m = String( r.url ).match( /[?&]ecshs=([^&#]+)/ );
		if ( m && 'function' === typeof window.ecshJumpToSection ) {
			var base = String( r.url ).replace( /&ecshs=[^&#]+/, '' ).replace( /^.*admin\.php\?/, '' );
			if ( 0 === window.location.search.replace( /^\?/, '' ).indexOf( base ) && window.ecshJumpToSection( decodeURIComponent( m[ 1 ] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ Ways in */

	window.wpecSearch = { open: open, close: close, isOpen: isOpen };

	function typing( el ) {
		if ( ! el ) {
			return false;
		}
		var tag = el.tagName ? el.tagName.toLowerCase() : '';
		return ( 'input' === tag || 'textarea' === tag || 'select' === tag || !! el.isContentEditable ) && shown( el );
	}
	/* On screen: laid out, inside the window, not hidden ( closed drawers stay in the page, moved off screen or hidden ). */
	function shown( el ) {
		if ( ! el || ! el.getBoundingClientRect ) {
			return false;
		}
		var r = el.getBoundingClientRect(), cs = window.getComputedStyle( el );
		return r.width > 0 && r.height > 0 && r.right > 0 && r.bottom > 0 && r.left < window.innerWidth && r.top < window.innerHeight && 'hidden' !== cs.visibility && '0' !== cs.opacity;
	}
	/* Another window ( a dialog, a drawer, the media library ) is in front. */
	function otherLayer() {
		if ( 'function' === typeof window.ecodv2_layer_open ) {
			return !! window.ecodv2_layer_open(); /* the order screen knows its own */
		}
		var found = false;
		$( '[aria-modal="true"], .media-modal, body.ecv2-qe-open' ).each( function() {
			if ( ! $( this ).closest( '#wpec_search' ).length && ( 'BODY' === this.tagName || shown( this ) ) ) {
				found = true;
			}
		} );
		return found;
	}

	/* Captured on window, before WordPress's own command palette ( Ctrl / ⌘ K on every admin screen since WordPress 6.9 ):
	   on EasyCart screens the key is this search's, and WordPress's commands are one of its actions. */
	window.addEventListener( 'keydown', function( e ) {
		var k = String( e.key || '' ).toLowerCase();
		if ( ( e.metaKey || e.ctrlKey ) && ! e.altKey && ! e.shiftKey && 'k' === k ) {
			if ( isOpen() ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				close();
				return;
			}
			if ( otherLayer() ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			open( '' );
			return;
		}
		if ( '/' === e.key && ! e.metaKey && ! e.ctrlKey && ! e.altKey && ! typing( e.target ) && ! isOpen() && ! otherLayer() ) {
			e.preventDefault();
			open( '' );
		}
	}, true );
	$( document ).on( 'focus click', '.ecsh-tb-search input', function( e ) {
		e.preventDefault();
		$( this ).trigger( 'blur' );
		open( '' );
	} );
	$( document ).on( 'submit', '.ecsh-tb-search', function( e ) {
		e.preventDefault();
		open( String( $( this ).find( 'input' ).val() || '' ) );
	} );
	$( document ).on( 'click', '.ecsh-tb-search-btn, [data-wpec-search-open]', function( e ) {
		e.preventDefault();
		var preset = $( this ).attr( 'data-wpec-search-open' );
		open( preset ? String( preset ) : '' );
	} );

	$( function() {
		if ( C.here ) {
			remember( C.here );
		}
		if ( '#wpec-add-new' === window.location.hash ) {
			try {
				window.history.replaceState( window.history.state, '', window.location.href.replace( /#wpec-add-new$/, '' ) );
			} catch ( e ) {}
			setTimeout( addNew, 60 ); /* after the page's own scripts bind the button */
		}
		$( '[data-wpec-search-kbd]' ).text( C.mod + ' K' );
		/* The index is fetched while the page is idle, so the first ⌘K is instant ( once per version ). */
		var idle = window.requestIdleCallback || function( fn ) { return setTimeout( fn, 1500 ); };
		idle( function() {
			var cached = store( IX_KEY );
			if ( ! cached || cached.v !== C.version ) {
				loadIndex();
			}
		} );
	} );
}( window.jQuery ) );
