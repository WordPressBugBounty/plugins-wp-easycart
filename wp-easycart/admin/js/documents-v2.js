/**
 * Settings › Documents: the profile editors ( admin/inc/wp_easycart_admin_documents.php ).
 *
 * One .ecdoc per document. Its profiles arrive as JSON in .ecdoc-data; switches save as they change
 * ( ecv2_documents_save ) and the preview frame re-renders the document for a real order
 * ( ecv2_documents_preview ) with the switches as they are on screen. Profile housekeeping ( PRO ) goes
 * through ecv2_documents_profile. Edit wording and Logo & footer ( a profile's own logo and footer image, PRO ) open
 * drawers. Localized: ecv2_documents { ajax_url, nonce, pro, preview_order, lang, store, email_url }.
 *
 * @since 6.0.1
 */
( function( $ ) {
	'use strict';

	var CFG  = window.ecv2_documents || {};
	var LANG = CFG.lang || {};

	function t( key, fallback ) {
		return LANG[ key ] || fallback;
	}

	function post( data ) {
		return $.post( CFG.ajax_url || window.ajaxurl, $.extend( { nonce: CFG.nonce }, data ), null, 'json' );
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	/* ------------------------------------------------------------------ */
	/* Small dialog ( confirm, or confirm with a name field )              */
	/* ------------------------------------------------------------------ */

	function dialog( opts ) {
		return new Promise( function( resolve ) {
			$( '#ecdoc_dialog' ).remove();
			var field = opts.input ? '<label class="ecdoc-dialog-field" for="ecdoc_dialog_input"><span>' + esc( t( 'name', 'Name' ) ) + '</span><input type="text" class="ecv2-input" id="ecdoc_dialog_input" maxlength="60" value="' + esc( opts.value || '' ) + '"></label>' : '';
			var $d = $(
				'<div class="ecv2-modal-overlay ecdoc-dialog" id="ecdoc_dialog" style="display:flex;">' +
					'<div class="ecv2-modal" role="dialog" aria-modal="true" aria-labelledby="ecdoc_dialog_title">' +
						'<div class="ecv2-modal-header"><h2 id="ecdoc_dialog_title">' + esc( opts.title ) + '</h2></div>' +
						'<div class="ecv2-modal-body">' + ( opts.body ? '<p>' + esc( opts.body ) + '</p>' : '' ) + field + '</div>' +
						'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' +
							'<button type="button" class="ecv2-btn" data-r="0">' + esc( t( 'cancel', 'Cancel' ) ) + '</button>' +
							'<button type="button" class="ecv2-btn ' + ( opts.danger ? 'ecv2-btn-danger' : 'ecv2-btn-primary' ) + '" data-r="1">' + esc( opts.ok ) + '</button>' +
						'</div></div>' +
					'</div>' +
				'</div>'
			).appendTo( 'body' );
			var done = function( ok ) {
				var value = $( '#ecdoc_dialog_input' ).val();
				$d.remove();
				$( document ).off( 'keydown.ecdocdialog' );
				resolve( ok ? ( opts.input ? $.trim( value || '' ) : true ) : false );
			};
			$d.on( 'click', '[data-r]', function() { done( '1' === $( this ).attr( 'data-r' ) ); } );
			$d.on( 'click', function( e ) { if ( e.target === this ) { done( false ); } } );
			$( document ).on( 'keydown.ecdocdialog', function( e ) {
				if ( 'Escape' === e.key ) { done( false ); }
				if ( 'Enter' === e.key && opts.input ) { e.preventDefault(); done( true ); }
			} );
			setTimeout( function() { ( opts.input ? $( '#ecdoc_dialog_input' ) : $d.find( '[data-r="1"]' ) ).trigger( 'focus' ); }, 30 );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* One editor                                                          */
	/* ------------------------------------------------------------------ */

	function Editor( el ) {
		var self   = this;
		this.$el   = $( el );
		this.type  = this.$el.attr( 'data-type' );
		this.data  = {};
		try { this.data = JSON.parse( this.$el.find( '.ecdoc-data' ).text() ) || {}; } catch ( e ) { this.data = {}; }
		this.data.profiles = this.data.profiles || {};
		this.current = this.data['default'] || 'standard';
		this.saveTimer = null;
		this.previewTimer = null;
		this.previewXhr = null;
		this.previewLoaded = false;
		this.$frame = this.$el.find( '.ecdoc-frame' );
		this.$msg   = this.$el.find( '.ecdoc-frame-msg' );
		this.$status = this.$el.find( '.ecdoc-status' );
		/* 6.0.1: a PRO document without PRO ( the Invoice PDF ) is shown read only, with its preview. */
		this.locked = '1' === this.$el.attr( 'data-locked' );

		this.$el.on( 'click', '.ecdoc-tab[data-profile]', function() { self.select( $( this ).attr( 'data-profile' ) ); } );
		this.$el.on( 'click', '.ecdoc-tab[data-add]', function() { self.create(); } );
		this.$el.on( 'change', '.ecdoc-sw input[data-key]', function() { if ( ! self.locked ) { self.changed(); } } );
		/* 6.0.1: a profile's choices ( the Invoice PDF's heading ) save at once, then the preview follows. */
		this.$el.on( 'change', 'input[data-option-key]', function() { if ( ! self.locked ) { self.optionChanged( $( this ).attr( 'data-option-key' ), $( this ).val() ); } } );
		/* 6.0.1: a switch's link to the setting it prints ( Store address ): save a switch changed a moment ago first. */
		this.$el.on( 'click', '[data-edit-link]', function( e ) { e.stopPropagation(); self.flush(); } );
		this.$el.on( 'click', '.ecdoc-actions [data-op]', function() { self.op( $( this ).attr( 'data-op' ) ); } );
		this.$el.on( 'change', '.ecdoc-order-id', function() { self.preview( true ); } );
		this.$el.on( 'click', '[data-wording]', function() { wording( self.type, $( this ).attr( 'data-title' ) || '' ); } );
		this.$el.on( 'click', '[data-branding]', function() { self.flush(); branding( self, $( this ).attr( 'data-title' ) || '', $( this ) ); } );
		this.$frame.on( 'load', function() { self.fit(); } );
		/* 6.0.1: every visit starts on the newest order; a number typed last time ( restored by the browser ) is not kept. */
		this.$el.find( '.ecdoc-order-id' ).val( parseInt( CFG.preview_order, 10 ) > 0 ? parseInt( CFG.preview_order, 10 ) : '' );

		this.show();
		this.watch();
	}

	Editor.prototype.profile = function() {
		return this.data.profiles[ this.current ] || null;
	};

	/* Put the current profile's switches on screen. */
	Editor.prototype.show = function() {
		var p = this.profile();
		if ( ! p || ! p.fields ) { return; }
		this.$el.find( '.ecdoc-sw input[data-key]' ).each( function() {
			var key = $( this ).attr( 'data-key' );
			$( this ).prop( 'checked', !! p.fields[ key ] );
		} );
		var options = p.options || {};
		this.$el.find( 'input[data-option-key]' ).each( function() {
			$( this ).prop( 'checked', options[ $( this ).attr( 'data-option-key' ) ] === $( this ).val() );
		} );
		this.$el.find( '.ecdoc-desc' ).text( p.desc || '' ).prop( 'hidden', ! p.desc );
		this.$el.find( '.ecdoc-tab[data-profile]' ).each( function() {
			var on = $( this ).attr( 'data-profile' ) === p.id;
			$( this ).toggleClass( 'is-active', on ).attr( 'aria-selected', on ? 'true' : 'false' );
		} );
		var isDefault = ( p.id === this.data['default'] );
		this.$el.find( '[data-op="default"]' ).prop( 'hidden', isDefault );
		this.$el.find( '[data-op="rename"], [data-op="delete"]' ).prop( 'hidden', !! p.builtin );
		this.$el.find( '[data-op="reset"]' ).prop( 'hidden', ! p.builtin );
		this.children();
		this.brandMark();
	};

	/* A dot on Logo & footer while the profile on screen has its own logo or footer image. */
	Editor.prototype.brandMark = function() {
		var p = this.profile(), on = !! ( CFG.pro && p && customBrand( p.branding ) );
		this.$el.find( '.ecdoc-brand-btn' ).toggleClass( 'is-custom', on ).find( '.ecdoc-brand-dot' ).prop( 'hidden', ! on ).attr( 'title', on ? t( 'brand_custom', '' ) : null );
	};

	/* A switch that depends on another ( totals under "Show prices" ) only shows while that one is on. */
	Editor.prototype.children = function() {
		var $el = this.$el;
		$el.find( '.ecdoc-sw[data-parent]' ).each( function() {
			var parent = $el.find( '.ecdoc-sw input[data-key="' + $( this ).attr( 'data-parent' ) + '"]' );
			$( this ).prop( 'hidden', parent.length && ! parent.prop( 'checked' ) );
		} );
	};

	Editor.prototype.fields = function() {
		var out = {};
		this.$el.find( '.ecdoc-sw input[data-key]' ).each( function() {
			out[ $( this ).attr( 'data-key' ) ] = $( this ).prop( 'checked' ) ? 1 : 0;
		} );
		return out;
	};

	Editor.prototype.status = function( text, tone ) {
		this.$status.text( text || '' ).attr( 'data-tone', tone || '' );
	};

	Editor.prototype.select = function( id ) {
		if ( ! this.data.profiles[ id ] || this.data.profiles[ id ].locked ) { return; }
		this.flush();
		this.current = id;
		this.show();
		this.status( '' );
		this.preview( true );
	};

	Editor.prototype.changed = function() {
		var self = this, p = this.profile();
		if ( ! p ) { return; }
		p.fields = this.fields();
		this.children();
		this.status( t( 'saving', 'Saving…' ) );
		clearTimeout( this.saveTimer );
		this.saveTimer = setTimeout( function() { self.save(); }, 350 );
		this.preview( false );
	};

	Editor.prototype.optionChanged = function( key, value ) {
		var self = this, p = this.profile();
		if ( ! p ) { return; }
		p.options = $.extend( {}, p.options || {} );
		p.options[ key ] = value;
		clearTimeout( this.saveTimer );
		this.status( t( 'saving', 'Saving…' ) );
		this.save( true );
	};

	/* Save now if a change is waiting ( before switching profiles ). */
	Editor.prototype.flush = function() {
		if ( this.saveTimer ) {
			clearTimeout( this.saveTimer );
			this.save();
		}
	};

	Editor.prototype.save = function( thenPreview ) {
		var self = this, p = this.profile();
		this.saveTimer = null;
		if ( ! p ) { return; }
		var id = p.id, data = { action: 'ecv2_documents_save', type: this.type, profile: id, fields: JSON.stringify( p.fields ) };
		if ( p.options ) { data.options = JSON.stringify( p.options ); }
		post( data ).done( function( r ) {
			if ( r && r.success ) {
				if ( ! self.data.profiles[ id ] ) { return; } /* deleted while this save was on its way */
				self.data.profiles[ id ].fields = r.data.fields;
				if ( r.data.options ) { self.data.profiles[ id ].options = r.data.options; }
				if ( id === self.current ) {
					self.status( t( 'saved', 'Saved' ), 'ok' );
					if ( thenPreview ) { self.preview( true ); }
				}
				return;
			}
			self.status( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ), 'error' );
		} ).fail( function() {
			self.status( t( 'error', 'Could not save.' ), 'error' );
		} );
	};

	/* ---------- Preview ---------- */

	/* Load the first preview when the editor scrolls into view. */
	Editor.prototype.watch = function() {
		var self = this;
		if ( ! ( 'IntersectionObserver' in window ) ) {
			this.preview( true );
			return;
		}
		var io = new IntersectionObserver( function( entries ) {
			entries.forEach( function( entry ) {
				if ( entry.isIntersecting && ! self.previewLoaded ) {
					self.preview( true );
					io.disconnect();
				}
			} );
		}, { rootMargin: '200px' } );
		io.observe( this.$el[0] );
	};

	Editor.prototype.preview = function( now ) {
		var self = this;
		clearTimeout( this.previewTimer );
		this.previewTimer = setTimeout( function() { self.load(); }, now ? 0 : 450 );
	};

	Editor.prototype.load = function() {
		var self = this, p = this.profile();
		var order = parseInt( this.$el.find( '.ecdoc-order-id' ).val(), 10 ) || parseInt( CFG.preview_order, 10 ) || 0;
		this.previewLoaded = true;
		if ( ! order ) {
			this.message( t( 'no_order', 'The preview appears once the store has an order.' ) );
			return;
		}
		if ( this.previewXhr ) { this.previewXhr.abort(); }
		this.$el.find( '.ecdoc-frame-wrap' ).addClass( 'is-loading' );
		this.previewXhr = post( { action: 'ecv2_documents_preview', type: this.type, profile: p ? p.id : '', fields: JSON.stringify( this.fields() ), order_id: order } ).done( function( r ) {
			if ( r && r.success && r.data && r.data.html ) {
				self.$msg.prop( 'hidden', true );
				self.$frame.prop( 'hidden', false );
				self.$frame[0].srcdoc = r.data.html;
				return;
			}
			self.message( ( r && r.data && r.data.message ) || t( 'preview_failed', 'The preview could not be loaded for that order.' ) );
		} ).fail( function( xhr, status ) {
			if ( 'abort' !== status ) { self.message( t( 'preview_failed', 'The preview could not be loaded for that order.' ) ); }
		} ).always( function() {
			self.$el.find( '.ecdoc-frame-wrap' ).removeClass( 'is-loading' );
		} );
	};

	Editor.prototype.message = function( text ) {
		this.$frame.prop( 'hidden', true );
		this.$msg.text( text ).prop( 'hidden', false );
	};

	/* Grow the frame to its document so the page scrolls, not the frame. */
	Editor.prototype.fit = function() {
		try {
			var doc = this.$frame[0].contentDocument;
			if ( doc && doc.documentElement ) {
				this.$frame.css( 'height', Math.min( 2400, Math.max( 320, doc.documentElement.scrollHeight ) ) + 'px' );
			}
		} catch ( e ) {}
	};

	/* ---------- Profiles ( PRO ) ---------- */

	Editor.prototype.op = function( op ) {
		var self = this, p = this.profile();
		if ( ! p ) { return; }
		this.flush(); /* a switch changed a moment ago is saved before the profile is reset, renamed or deleted */
		var run = function( extra ) {
			return post( $.extend( { action: 'ecv2_documents_profile', type: self.type, profile: p.id, op: op }, extra || {} ) ).done( function( r ) {
				if ( ! r || ! r.success ) {
					self.status( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ), 'error' );
					return;
				}
				self.applied( op, r.data );
			} ).fail( function() { self.status( t( 'error', 'Could not save.' ), 'error' ); } );
		};
		if ( 'reset' === op ) {
			dialog( { title: t( 'reset_title', 'Reset this profile?' ), body: t( 'reset_body', '' ), ok: t( 'reset', 'Reset' ) } ).then( function( ok ) { if ( ok ) { run(); } } );
		} else if ( 'delete' === op ) {
			dialog( { title: t( 'delete_title', 'Delete this profile?' ), body: t( 'delete_body', '' ), ok: t( 'delete', 'Delete' ), danger: true } ).then( function( ok ) { if ( ok ) { run(); } } );
		} else if ( 'rename' === op ) {
			dialog( { title: t( 'rename_title', 'Rename profile' ), ok: t( 'rename', 'Rename' ), input: true, value: p.name } ).then( function( name ) { if ( name ) { run( { name: name } ); } } );
		} else if ( 'default' === op ) {
			run();
		}
	};

	Editor.prototype.create = function() {
		var self = this, p = this.profile();
		dialog( { title: t( 'new_title', 'New profile' ), body: t( 'new_body', '' ), ok: t( 'create', 'Create' ), input: true } ).then( function( name ) {
			if ( ! name ) { return; }
			post( { action: 'ecv2_documents_profile', type: self.type, profile: p ? p.id : 'standard', op: 'create', name: name } ).done( function( r ) {
				if ( ! r || ! r.success ) {
					self.status( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ), 'error' );
					return;
				}
				self.data.profiles[ r.data.id ] = { id: r.data.id, name: r.data.name, desc: '', builtin: false, locked: false, fields: r.data.fields, branding: r.data.branding, options: r.data.options };
				$( '<button type="button" class="ecdoc-tab" role="tab" aria-selected="false"></button>' ).attr( 'data-profile', r.data.id ).append( $( '<span class="ecdoc-tab-name"></span>' ).text( r.data.name ) ).insertBefore( self.$el.find( '.ecdoc-tab[data-add]' ) );
				self.select( r.data.id );
				self.announce();
			} );
		} );
	};

	Editor.prototype.applied = function( op, d ) {
		var p = this.profile();
		if ( 'delete' === op ) {
			delete this.data.profiles[ p.id ];
			this.$el.find( '.ecdoc-tab[data-profile="' + p.id + '"]' ).remove();
			this.data['default'] = d['default'];
			this.marks();
			this.select( d['default'] );
			this.announce();
			return;
		}
		if ( 'rename' === op ) {
			p.name = d.name;
			this.$el.find( '.ecdoc-tab[data-profile="' + p.id + '"] .ecdoc-tab-name' ).text( d.name );
			this.announce();
		}
		if ( 'reset' === op && d.fields ) {
			p.fields = d.fields;
			p.branding = d.branding;
			p.options = d.options;
			this.show();
			this.preview( true );
		}
		if ( 'default' === op ) {
			this.data['default'] = d['default'];
			this.marks();
			this.show();
			this.announce();
			this.status( t( 'made_default', 'Now the default.' ), 'ok' );
			return;
		}
		this.status( t( 'saved', 'Saved' ), 'ok' );
	};

	/* Move the "Default" chip to the default profile's tab. */
	Editor.prototype.marks = function() {
		var def = this.data['default'];
		this.$el.find( '.ecdoc-tab .ecdoc-default' ).remove();
		this.$el.find( '.ecdoc-tab[data-profile="' + def + '"]' ).append( ' <span class="ecdoc-default">' + esc( t( 'default', 'Default' ) ) + '</span>' );
	};

	/* 6.0.1: tell the rest of the page ( the PRO attachments grid ) that this document's profiles or default changed. */
	Editor.prototype.announce = function() {
		var list = [];
		$.each( this.data.profiles, function( id, p ) {
			if ( p && ! p.locked ) { list.push( { id: id, name: p.name } ); }
		} );
		$( document ).trigger( 'ecdoc:profiles', [ this.type, { 'default': this.data['default'], profiles: list } ] );
	};

	/* ------------------------------------------------------------------ */
	/* Wording drawer ( the language editor's phrases for one document )   */
	/* ------------------------------------------------------------------ */

	function wording( type, title ) {
		if ( $( '.ecdoc-drawer' ).length ) { return; }
		var state = { type: type, language: '', rows: [], initial: '' };
		var $back = $( '<div class="ecdoc-drawer-backdrop"></div>' );
		var $d = $(
			'<aside class="ecdoc-drawer" id="ecdoc_wording" role="dialog" aria-modal="true" aria-labelledby="ecdoc_wording_title">' +
				'<header class="ecdoc-drawer-head"><h2 id="ecdoc_wording_title"></h2><button type="button" class="ecdoc-drawer-x" data-close aria-label="' + esc( t( 'close', 'Close' ) ) + '">&times;</button></header>' +
				'<div class="ecdoc-drawer-body"><p class="ecdoc-drawer-msg">' + esc( t( 'loading_words', 'Loading…' ) ) + '</p></div>' +
				'<footer class="ecdoc-drawer-foot"><a class="ecdoc-drawer-link" target="_blank" rel="noopener" hidden>' + esc( t( 'open_editor', 'Open the language editor' ) ) + '</a><span class="ecdoc-grow"></span>' +
					'<span class="ecdoc-status" role="status" aria-live="polite"></span>' +
					'<button type="button" class="ecv2-btn" data-close>' + esc( t( 'cancel', 'Cancel' ) ) + '</button>' +
					'<button type="button" class="ecv2-btn ecv2-btn-primary" data-save disabled>' + esc( t( 'save_wording', 'Save wording' ) ) + '</button>' +
				'</footer>' +
			'</aside>'
		);
		$d.find( 'h2' ).text( t( 'wording_title', 'Wording' ) + ( title ? ' · ' + title : '' ) );
		$( 'body' ).append( $back, $d ).addClass( 'ecdoc-drawer-open' );
		setTimeout( function() { $back.addClass( 'is-open' ); $d.addClass( 'is-open' ); }, 10 );

		var values = function() {
			var out = {};
			$d.find( '[data-word]' ).each( function() { out[ $( this ).attr( 'data-word' ) ] = String( $( this ).val() ); } );
			return out;
		};
		var dirty = function() { return JSON.stringify( values() ) !== state.initial; };
		var sync = function() { $d.find( '[data-save]' ).prop( 'disabled', ! dirty() ); };
		var status = function( text, tone ) { $d.find( '.ecdoc-status' ).text( text || '' ).attr( 'data-tone', tone || '' ); };

		var draw = function( payload ) {
			state.language = payload.language;
			state.rows = payload.rows || [];
			var html = '<p class="ecdoc-drawer-intro">' + esc( t( 'wording_intro', '' ) ) + '</p>';
			if ( payload.languages && payload.languages.length > 1 ) {
				html += '<label class="ecdoc-word-lang">' + esc( t( 'language', 'Language' ) ) + ' <select class="ecv2-select" data-language>';
				payload.languages.forEach( function( l ) {
					html += '<option value="' + esc( l.file ) + '"' + ( l.file === payload.language ? ' selected' : '' ) + '>' + esc( l.label ) + '</option>';
				} );
				html += '</select></label>';
			}
			if ( ! state.rows.length ) {
				html += '<p class="ecdoc-drawer-msg">' + esc( t( 'wording_none', 'This language has none of these phrases.' ) ) + '</p>';
			}
			state.rows.forEach( function( row, i ) {
				var id = 'ecdoc_word_' + i;
				var field = row.long ?
					'<textarea class="ecv2-input" rows="3" id="' + id + '" data-word="' + esc( row.id ) + '"></textarea>' :
					'<input type="text" class="ecv2-input" id="' + id + '" data-word="' + esc( row.id ) + '">';
				html += '<div class="ecdoc-word' + ( row.changed ? ' is-changed' : '' ) + '">' +
					'<label for="' + id + '">' + esc( row.label ) + '</label>' + field +
					'<div class="ecdoc-word-meta">' +
						( row.shared && row.shared.length ? '<span class="ecdoc-word-shared">' + esc( t( 'shared', 'Also on %s' ).replace( '%s', row.shared.join( ', ' ) ) ) + '</span>' : '' ) +
						( null !== row['default'] ? '<button type="button" class="ecdoc-link" data-reset="' + i + '"' + ( row.changed ? '' : ' hidden' ) + '>' + esc( t( 'reset_phrase', 'Use the original' ) ) + '</button>' : '' ) +
					'</div>' +
				'</div>';
			} );
			$d.find( '.ecdoc-drawer-body' ).html( html );
			/* Values go in as properties, never as markup. */
			state.rows.forEach( function( row, i ) { $d.find( '#ecdoc_word_' + i ).val( row.value ); } );
			if ( payload.editor ) { $d.find( '.ecdoc-drawer-link' ).attr( 'href', payload.editor ).prop( 'hidden', false ); }
			state.initial = JSON.stringify( values() );
			sync();
		};

		var load = function( language ) {
			$d.find( '.ecdoc-drawer-body' ).html( '<p class="ecdoc-drawer-msg">' + esc( t( 'loading_words', 'Loading…' ) ) + '</p>' );
			post( { action: 'ecv2_documents_wording', type: type, language: language || '' } ).done( function( r ) {
				if ( r && r.success ) { draw( r.data ); return; }
				$d.find( '.ecdoc-drawer-body' ).html( '<p class="ecdoc-drawer-msg">' + esc( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ) ) + '</p>' );
			} ).fail( function() {
				$d.find( '.ecdoc-drawer-body' ).html( '<p class="ecdoc-drawer-msg">' + esc( t( 'error', 'Could not save.' ) ) + '</p>' );
			} );
		};

		var close = function( force ) {
			if ( ! force && dirty() ) {
				dialog( { title: t( 'discard_title', 'Discard your changes?' ), ok: t( 'discard', 'Discard' ), danger: true } ).then( function( ok ) { if ( ok ) { close( true ); } } );
				return;
			}
			$( document ).off( 'keydown.ecdocwording' );
			$d.removeClass( 'is-open' );
			$back.removeClass( 'is-open' );
			$( 'body' ).removeClass( 'ecdoc-drawer-open' );
			setTimeout( function() { $d.remove(); $back.remove(); }, 200 );
		};

		$d.on( 'input change', '[data-word]', function() {
			var i = parseInt( String( $( this ).attr( 'id' ) ).replace( 'ecdoc_word_', '' ), 10 ), row = state.rows[ i ];
			if ( row && null !== row['default'] ) { $d.find( '[data-reset="' + i + '"]' ).prop( 'hidden', String( $( this ).val() ) === String( row['default'] ) ); }
			status( '' );
			sync();
		} );
		$d.on( 'click', '[data-reset]', function() {
			var i = parseInt( $( this ).attr( 'data-reset' ), 10 ), row = state.rows[ i ];
			if ( row ) { $d.find( '#ecdoc_word_' + i ).val( row['default'] ).trigger( 'change' ).trigger( 'focus' ); }
		} );
		$d.on( 'change', '[data-language]', function() {
			var next = $( this ).val();
			if ( dirty() ) {
				dialog( { title: t( 'discard_title', 'Discard your changes?' ), ok: t( 'discard', 'Discard' ), danger: true } ).then( function( ok ) {
					if ( ok ) { load( next ); } else { $d.find( '[data-language]' ).val( state.language ); }
				} );
				return;
			}
			load( next );
		} );
		$d.on( 'click', '[data-save]', function() {
			var $b = $( this ).prop( 'disabled', true );
			status( t( 'saving', 'Saving…' ) );
			post( { action: 'ecv2_documents_wording_save', type: type, language: state.language, values: JSON.stringify( values() ) } ).done( function( r ) {
				if ( r && r.success ) {
					draw( r.data );
					status( r.data.message || t( 'saved', 'Saved' ), 'ok' );
					/* Every document can share a phrase: redraw each preview. */
					$( '.ecdoc' ).each( function() { if ( this.ecdoc ) { this.ecdoc.preview( true ); } } );
					return;
				}
				$b.prop( 'disabled', false );
				status( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ), 'error' );
			} ).fail( function() {
				$b.prop( 'disabled', false );
				status( t( 'error', 'Could not save.' ), 'error' );
			} );
		} );
		$d.on( 'click', '[data-close]', function() { close(); } );
		$back.on( 'click', function() { close(); } );
		$( document ).on( 'keydown.ecdocwording', function( e ) {
			if ( 'Escape' === e.key && ! $( '#ecdoc_dialog' ).length ) { close(); }
		} );
		load( '' );
		setTimeout( function() { $d.find( '[data-close]' ).first().trigger( 'focus' ); }, 30 );
	}

	/* ------------------------------------------------------------------ */
	/* A profile's own logo and footer image ( the Logo & footer drawer; PRO )   */
	/* ------------------------------------------------------------------ */

	var STORE = CFG.store || {};

	/* Does a profile use anything but the store's images? ( wp_easycart_documents::is_custom_branding() ) */
	function customBrand( b ) {
		if ( ! b ) { return false; }
		return ( 'custom' === b.logo_source && '' !== ( b.logo_url || '' ) ) || 'custom' === b.logo_size || ( b.logo_align && 'store' !== b.logo_align ) ||
			( 'custom' === b.footer_source && '' !== ( b.footer_url || '' ) ) || 'custom' === b.footer_size;
	}

	/* "40% wide, up to 80px tall" */
	function sizeText( w, h ) {
		h = parseInt( h, 10 ) || 0;
		return h > 0 ? t( 'brand_sz_h', '%1$s wide, up to %2$s tall' ).replace( '%1$s', w + '%' ).replace( '%2$s', h + 'px' ) : t( 'brand_sz', '%s wide, any height' ).replace( '%s', w + '%' );
	}

	function alignText( a ) {
		return { left: t( 'brand_left', 'Left' ), center: t( 'brand_center', 'Center' ), right: t( 'brand_right', 'Right' ) }[ a ] || t( 'brand_center', 'Center' );
	}

	function branding( editor, title, $opener ) {
		var p = editor.profile();
		if ( ! p || $( '.ecdoc-drawer' ).length ) { return; }
		var locked = ! CFG.pro;
		var b = $.extend( {}, p.branding || {} );
		var isDefault = ( p.id === editor.data['default'] );

		var radio = function( name, value, on ) {
			return '<input type="radio" name="' + name + '" value="' + value + '"' + ( on ? ' checked' : '' ) + '>';
		};
		var part = function( key, label, storeLabel, storeW, storeH ) {
			var own = 'custom' === b[ key + '_source' ], ownSize = 'custom' === b[ key + '_size' ];
			var html = '<fieldset class="ecdoc-bd-part" data-part="' + key + '"' + ( locked ? ' disabled' : '' ) + '>' +
				'<legend>' + esc( label ) + '</legend>' +
				'<p class="ecdoc-bd-off" data-off="' + key + '" hidden><span></span> <button type="button" class="ecdoc-link" data-show="' + ( 'logo' === key ? 'logo' : 'footer_image' ) + '">' + esc( t( 'brand_show', 'Show it' ) ) + '</button></p>' +
				'<div class="ecdoc-bd-group"><div class="ecdoc-bd-label">' + esc( t( 'brand_image', 'Image' ) ) + '</div>' +
					'<label class="ecdoc-bd-choice">' + radio( 'ecbd_' + key + '_source', 'store', ! own ) +
						'<span class="ecdoc-bd-thumb" data-thumb="store-' + key + '"><img alt="" hidden></span>' +
						'<span class="ecdoc-bd-text"><b>' + esc( storeLabel ) + '</b><small data-store-name="' + key + '"></small></span></label>' +
					'<label class="ecdoc-bd-choice">' + radio( 'ecbd_' + key + '_source', 'custom', own ) +
						'<span class="ecdoc-bd-thumb" data-thumb="own-' + key + '"><img alt="" hidden></span>' +
						'<span class="ecdoc-bd-text"><b>' + esc( t( 'brand_own', 'A different image for this profile' ) ) + '</b></span></label>' +
					'<div class="ecdoc-bd-pick" data-when="' + key + '_source">' +
						'<input type="url" class="ecv2-input" data-k="' + key + '_url" placeholder="https://" aria-label="' + esc( label ) + '">' +
						'<button type="button" class="ecv2-btn ecv2-btn-sm" data-media="' + key + '">' + esc( t( 'brand_choose', 'Choose image' ) ) + '</button>' +
					'</div>' +
				'</div>' +
				'<div class="ecdoc-bd-group"><div class="ecdoc-bd-label">' + esc( t( 'brand_size', 'Size' ) ) + '</div>' +
					'<label class="ecdoc-bd-choice is-plain">' + radio( 'ecbd_' + key + '_size', 'store', ! ownSize ) +
						'<span class="ecdoc-bd-text"><b>' + esc( t( 'brand_st_size', 'The store size' ) ) + '</b><small>' + esc( sizeText( storeW, storeH ) ) + '</small></span></label>' +
					'<label class="ecdoc-bd-choice is-plain">' + radio( 'ecbd_' + key + '_size', 'custom', ownSize ) +
						'<span class="ecdoc-bd-text"><b>' + esc( t( 'brand_own_sz', 'Its own size' ) ) + '</b></span></label>' +
					'<div class="ecdoc-bd-size" data-when="' + key + '_size">' +
						'<label>' + esc( t( 'brand_width', 'Width' ) ) + '<span class="ecdoc-bd-num"><input type="number" class="ecv2-input" data-k="' + key + '_width" min="5" max="100" step="1"> %</span><small>' + esc( t( 'brand_w_hint', 'Share of the page' ) ) + '</small></label>' +
						'<label>' + esc( t( 'brand_height', 'Height limit' ) ) + '<span class="ecdoc-bd-num"><input type="number" class="ecv2-input" data-k="' + key + '_height" min="0" max="600" step="1"> px</span><small>' + esc( t( 'brand_h_hint', '0 = no limit' ) ) + '</small></label>' +
					'</div>' +
				'</div>';
			if ( 'logo' === key ) {
				var align = b.logo_align || 'store';
				html += '<div class="ecdoc-bd-group"><div class="ecdoc-bd-label">' + esc( t( 'brand_position', 'Position' ) ) + '</div><div class="ecdoc-bd-pills" role="radiogroup" aria-label="' + esc( t( 'brand_position', 'Position' ) ) + '">';
				[ [ 'store', t( 'brand_st_pos', 'Store ( %s )' ).replace( '%s', alignText( STORE.logo_align ) ) ], [ 'left', t( 'brand_left', 'Left' ) ], [ 'center', t( 'brand_center', 'Center' ) ], [ 'right', t( 'brand_right', 'Right' ) ] ].forEach( function( o ) {
					html += '<label>' + radio( 'ecbd_logo_align', o[0], align === o[0] ) + '<span>' + esc( o[1] ) + '</span></label>';
				} );
				html += '</div></div>';
			}
			return html + '</fieldset>';
		};

		var $back = $( '<div class="ecdoc-drawer-backdrop"></div>' );
		var $d = $(
			'<aside class="ecdoc-drawer ecdoc-bd" id="ecdoc_branding" role="dialog" aria-modal="true" aria-labelledby="ecdoc_branding_title">' +
				'<header class="ecdoc-drawer-head"><h2 id="ecdoc_branding_title">' + esc( t( 'brand_title', 'Logo and footer image' ) ) + '</h2><button type="button" class="ecdoc-drawer-x" data-close aria-label="' + esc( t( 'close', 'Close' ) ) + '">&times;</button></header>' +
				'<div class="ecdoc-bd-store">' +
					'<p>' + esc( t( 'brand_def_hint', 'The store’s logo and footer image, and their sizes, are set on Settings › Email › Sender.' ) ) + '</p>' +
					'<a class="ecv2-btn ecv2-btn-sm" data-defaults href="#"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>' + esc( t( 'brand_defaults', 'Manage store defaults' ) ) + '</a>' +
				'</div>' +
				'<div class="ecdoc-drawer-body">' +
					'<div class="ecdoc-bd-scope">' +
						'<div class="ecdoc-bd-editing"><span class="ecdoc-bd-eyebrow">' + esc( t( 'brand_editing', 'Editing' ) ) + '</span>' +
							'<span class="ecdoc-bd-crumb"><b data-doc></b><span aria-hidden="true">›</span><b data-prof></b>' + ( isDefault ? '<span class="ecdoc-default">' + esc( t( 'default', 'Default' ) ) + '</span>' : '' ) + '</span></div>' +
						'<p>' + esc( t( 'brand_scope', 'Only this profile changes. Other profiles, and every other email, keep the store’s logo and footer image.' ) ) + ' ' + esc( isDefault ? t( 'brand_is_def', 'It is the default profile, so it goes out unless another profile is picked when you send.' ) : t( 'brand_not_def', 'It goes out when you pick it while sending from an order, or once you make it the default.' ) ) + '</p>' +
					'</div>' +
					( locked ? '<div class="ecdoc-bd-lock"><span class="dashicons ' + ( CFG.update_url ? 'dashicons-update' : 'dashicons-lock' ) + '" aria-hidden="true"></span><p>' + esc( t( 'brand_locked', 'A logo and footer image for each profile is included with Pro and Premium licenses.' ) ) + '</p><button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" data-unlock>' + esc( t( 'brand_unlock', 'Unlock a logo for each profile' ) ) + '</button></div>' : '' ) +
					part( 'logo', t( 'brand_logo', 'Logo' ), t( 'brand_st_logo', 'The store logo' ), STORE.logo_width, STORE.logo_height ) +
					part( 'footer', t( 'brand_footer', 'Footer image' ), t( 'brand_st_foot', 'The store footer image' ), STORE.footer_width, STORE.footer_height ) +
					( 'receipt' === editor.type ? '<p class="ecdoc-bd-note">' + esc( t( 'brand_pdf', 'This changes the receipt email. The PDF attached to it is the Invoice PDF, which has its own profiles and logo.' ) ) + '</p>' : '' ) +
				'</div>' +
				'<footer class="ecdoc-drawer-foot"><span class="ecdoc-grow"></span>' +
					'<span class="ecdoc-status" role="status" aria-live="polite"></span>' +
					'<button type="button" class="ecv2-btn" data-close>' + esc( t( locked ? 'close' : 'cancel', locked ? 'Close' : 'Cancel' ) ) + '</button>' +
					( locked ? '' : '<button type="button" class="ecv2-btn ecv2-btn-primary" data-save disabled>' + esc( t( 'brand_save', 'Save for this profile' ) ) + '</button>' ) +
				'</footer>' +
			'</aside>'
		);

		/* Names, URLs and numbers go in as text and properties, never as markup. */
		$d.find( '[data-doc]' ).text( title || '' );
		$d.find( '[data-prof]' ).text( p.name || '' );
		$d.find( '[data-defaults]' ).attr( 'href', CFG.email_url || '#' );
		[ 'logo', 'footer' ].forEach( function( key ) {
			var url = String( STORE[ key + '_url' ] || '' );
			$d.find( '[data-thumb="store-' + key + '"] img' ).attr( 'src', url ).prop( 'hidden', '' === url );
			var file = url.split( '?' )[0].split( '/' ).pop() || url;
			try { file = decodeURIComponent( file ); } catch ( e ) {}
			$d.find( '[data-store-name="' + key + '"]' ).text( '' === url ? t( 'brand_none', 'None set yet' ) : file );
			$d.find( '[data-k="' + key + '_url"]' ).val( b[ key + '_url' ] || '' );
			/* An own size starts from the store's, so switching to it changes nothing until a number does. */
			var ownSize = 'custom' === b[ key + '_size' ];
			$d.find( '[data-k="' + key + '_width"]' ).val( ownSize ? b[ key + '_width' ] : STORE[ key + '_width' ] );
			$d.find( '[data-k="' + key + '_height"]' ).val( ownSize ? b[ key + '_height' ] : STORE[ key + '_height' ] );
		} );

		$( 'body' ).append( $back, $d ).addClass( 'ecdoc-drawer-open' );
		setTimeout( function() { $back.addClass( 'is-open' ); $d.addClass( 'is-open' ); }, 10 );

		var values = function() {
			var out = {};
			[ 'logo', 'footer' ].forEach( function( key ) {
				out[ key + '_source' ] = $d.find( '[name="ecbd_' + key + '_source"]:checked' ).val() || 'store';
				out[ key + '_url' ]    = $.trim( $d.find( '[data-k="' + key + '_url"]' ).val() || '' );
				out[ key + '_size' ]   = $d.find( '[name="ecbd_' + key + '_size"]:checked' ).val() || 'store';
				out[ key + '_width' ]  = String( $d.find( '[data-k="' + key + '_width"]' ).val() || '' );
				out[ key + '_height' ] = String( $d.find( '[data-k="' + key + '_height"]' ).val() || '' );
			} );
			out.logo_align = $d.find( '[name="ecbd_logo_align"]:checked' ).val() || 'store';
			return out;
		};
		var initial = JSON.stringify( values() );
		var dirty = function() { return ! locked && JSON.stringify( values() ) !== initial; };
		var status = function( text, tone ) { $d.find( '.ecdoc-status' ).text( text || '' ).attr( 'data-tone', tone || '' ); };
		/* Errors show in the footer, beside Save, where they are always in view. */
		var err = function( text ) { if ( text ) { status( text, 'error' ); } };

		/* Which parts this profile switches off ( the Logo / Footer image switches beside the preview ). */
		var offs = function() {
			[ [ 'logo', 'logo', t( 'brand_off_logo', 'This profile hides the logo.' ) ], [ 'footer', 'footer_image', t( 'brand_off_foot', 'This profile hides the footer image.' ) ] ].forEach( function( o ) {
				var $sw = editor.$el.find( '.ecdoc-sw input[data-key="' + o[1] + '"]' );
				var off = $sw.length && ! $sw.prop( 'checked' );
				$d.find( '[data-off="' + o[0] + '"]' ).prop( 'hidden', ! off ).find( 'span' ).first().text( o[2] );
				$d.find( '.ecdoc-bd-part[data-part="' + o[0] + '"]' ).toggleClass( 'is-off', !! off );
			} );
		};
		var sync = function() {
			[ 'logo', 'footer' ].forEach( function( key ) {
				var own = 'custom' === $d.find( '[name="ecbd_' + key + '_source"]:checked' ).val();
				var url = $.trim( $d.find( '[data-k="' + key + '_url"]' ).val() || '' );
				$d.find( '[data-when="' + key + '_source"]' ).prop( 'hidden', ! own );
				$d.find( '[data-when="' + key + '_size"]' ).prop( 'hidden', 'custom' !== $d.find( '[name="ecbd_' + key + '_size"]:checked' ).val() );
				$d.find( '[data-thumb="own-' + key + '"] img' ).attr( 'src', url ).prop( 'hidden', '' === url );
			} );
			$d.find( '[data-save]' ).prop( 'disabled', ! dirty() );
		};
		offs();
		sync();

		var close = function( force ) {
			if ( ! force && dirty() ) {
				dialog( { title: t( 'discard_title', 'Discard your changes?' ), ok: t( 'discard', 'Discard' ), danger: true } ).then( function( ok ) { if ( ok ) { close( true ); } } );
				return;
			}
			$( document ).off( 'keydown.ecdocbrand' );
			$d.removeClass( 'is-open' );
			$back.removeClass( 'is-open' );
			$( 'body' ).removeClass( 'ecdoc-drawer-open' );
			setTimeout( function() { $d.remove(); $back.remove(); if ( $opener && $opener.length ) { $opener.trigger( 'focus' ); } }, 200 );
		};

		$d.on( 'input change', 'input', function() { status( '' ); sync(); } );
		$d.on( 'click', '[data-show]', function() {
			editor.$el.find( '.ecdoc-sw input[data-key="' + $( this ).attr( 'data-show' ) + '"]' ).prop( 'checked', true ).trigger( 'change' );
			offs();
		} );
		$d.on( 'click', '[data-media]', function() {
			var key = $( this ).attr( 'data-media' );
			if ( ! window.wp || ! wp.media ) { $d.find( '[data-k="' + key + '_url"]' ).trigger( 'focus' ); return; }
			var frame = wp.media( { title: ( 'logo' === key ? t( 'brand_logo', 'Logo' ) : t( 'brand_footer', 'Footer image' ) ) + ' · ' + ( p.name || '' ), button: { text: t( 'brand_choose', 'Choose image' ) }, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function() {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$d.find( '[data-k="' + key + '_url"]' ).val( a.url || '' ).trigger( 'change' );
			} );
			frame.open();
		} );
		$d.on( 'click', '[data-defaults]', function( e ) {
			if ( dirty() ) {
				e.preventDefault();
				var href = $( this ).attr( 'href' );
				dialog( { title: t( 'discard_title', 'Discard your changes?' ), ok: t( 'discard', 'Discard' ), danger: true } ).then( function( ok ) { if ( ok ) { window.location.href = href; } } );
			}
		} );
		$d.on( 'click', '[data-unlock]', function() {
			if ( CFG.update_url ) { window.location.href = CFG.update_url; return; } /* 6.0.1: only a newer WP EasyCart PRO is missing */
			if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'documents', feature: 'branding' } ); }
		} );
		$d.on( 'click', '[data-save]', function() {
			var $b = $( this ), v = values();
			if ( 'custom' === v.logo_source && '' === v.logo_url ) { err( t( 'brand_need_url', 'Choose an image, or use the store’s.' ) ); $d.find( '[data-k="logo_url"]' ).trigger( 'focus' ); return; }
			if ( 'custom' === v.footer_source && '' === v.footer_url ) { err( t( 'brand_need_url', 'Choose an image, or use the store’s.' ) ); $d.find( '[data-k="footer_url"]' ).trigger( 'focus' ); return; }
			$b.prop( 'disabled', true );
			status( t( 'saving', 'Saving…' ) );
			post( { action: 'ecv2_documents_branding_save', type: editor.type, profile: p.id, values: JSON.stringify( v ) } ).done( function( r ) {
				if ( r && r.success ) {
					if ( editor.data.profiles[ p.id ] ) { editor.data.profiles[ p.id ].branding = r.data.branding; }
					editor.brandMark();
					editor.status( t( 'saved', 'Saved' ), 'ok' );
					editor.preview( true );
					close( true );
					return;
				}
				$b.prop( 'disabled', false );
				err( ( r && r.data && r.data.message ) || t( 'error', 'Could not save.' ) );
			} ).fail( function() {
				$b.prop( 'disabled', false );
				err( t( 'error', 'Could not save.' ) );
			} );
		} );
		$d.on( 'click', '[data-close]', function() { close(); } );
		$back.on( 'click', function() { close(); } );
		$( document ).on( 'keydown.ecdocbrand', function( e ) {
			if ( 'Escape' === e.key && ! $( '#ecdoc_dialog' ).length && ! $( '.media-modal:visible' ).length && ! $( '#ec_admin_upsell_popup:visible' ).length ) { close(); }
		} );
		setTimeout( function() { $d.find( '[data-close]' ).first().trigger( 'focus' ); }, 30 );
	}

	$( function() {
		$( '.ecdoc' ).each( function() { this.ecdoc = new Editor( this ); } );
	} );
} )( jQuery );
