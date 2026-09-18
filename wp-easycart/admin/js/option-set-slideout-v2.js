/**
 * WP EasyCart — Create Option Set slideout ( V2 ).
 *
 * Entry points:
 *   ecosv2_open( { origin: 'product'|'standalone', type: 'basic-combo', after: 'edit'|'close' } )
 *     after: 'edit' sends the merchant to the full option editor for the new set once it is
 *     created ( default on the Option Sets screens ); 'close' just closes ( product editors,
 *     which pick the new set up from the ecosv2:created event ).
 *   ecosv2_close()
 *
 * Legacy routes that land here:
 *   wp_easycart_admin_open_slideout( 'new_option_box' )      → basic option set
 *   wp_easycart_admin_open_slideout( 'new_adv_option_box' )  → modifier ( PRO )
 *   Option Sets page: any "Add New" link with ec_admin_form_action=add-new-option
 *
 * After a successful save the panel does exactly what the legacy flow did so
 * every existing consumer keeps working: the new set is inserted into
 * #ec_new_product_option1..5 and #option1..5, the PRO callbacks
 * wp_easycart_pro_add_new_basic_option_insert / _advanced_option_insert fire,
 * and jQuery( document ).trigger( 'ecosv2:created', data ) is raised.
 *
 * Server: ec_admin_ajax_ecv2_save_option_set ( one request creates the set and
 * every choice; nonce 'wp-easycart-optionset-quick-edit' ).
 */
( function( $ ) {
	'use strict';

	var V = window.ecosv2_vars || {};
	var L = V.lang || {};
	var AJAX = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;

	var VARIATION = { 'basic-combo': 1, 'basic-swatch': 1 };            // stock-creating, free
	var LISTED    = { 'radio': 1, 'checkbox': 1, 'grid': 1 };            // PRO types that still take a list of choices
	var NUMERIC   = { 'number': 1, 'dimensions1': 1, 'dimensions2': 1 };
	var TEXTUAL   = { 'text': 1, 'textarea': 1 };

	var SWATCH    = { 'basic-swatch': 1, 'swatch': 1 };                  // types whose choices carry a color / image

	var state = { origin: 'standalone', type: 'basic-combo', after: 'close', label_touched: false, saving: false, hints: {} };

	function $box() { return $( '#ecosv2_box' ); }
	function $f( id ) { return $( '#' + id ); }
	function val( id ) { return $.trim( String( $f( id ).val() == null ? '' : $f( id ).val() ) ); }
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function fmt( n ) { var d = parseInt( V.decimals, 10 ); if ( isNaN( d ) ) { d = 2; } return ( V.currency || '$' ) + Number( n ).toFixed( d ); }
	function toast( msg, type ) {
		if ( typeof window.ecv2_toast === 'function' ) { window.ecv2_toast( msg, type || 'success' ); return; }
		var $t = $( '<div class="ecosv2-toast">' ).toggleClass( 'is-error', type === 'error' ).text( msg ).appendTo( 'body' );
		setTimeout( function() { $t.fadeOut( 200, function() { $t.remove(); } ); }, 2600 );
	}
	function loader( on ) { $f( 'ecosv2_loader' ).toggleClass( 'is-on', !! on ); }
	function takes_list( t ) { return !! ( VARIATION[ t ] || LISTED[ t ] ); }
	function has_pro() { return $box().attr( 'data-pro' ) === '1'; }
	function is_swatch_type( t ) { return !! SWATCH[ t ]; }

	/* Swatch value: "#rrggbb" or "#rrggbb,#rrggbb" ( color, every store ) or an image URL ( PRO ).
	 * Same format and rules as the full option editor ( option-editor-v2.js / sanitize_icon() ). */
	function swatch_colors( icon ) {
		icon = $.trim( String( icon || '' ) );
		if ( ! icon || icon.charAt( 0 ) !== '#' ) { return null; }
		var parts = $.map( icon.split( ',' ), function( x ) { return $.trim( x ); } );
		if ( parts.length > 2 ) { return null; }
		for ( var k = 0; k < parts.length; k++ ) { if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( parts[ k ] ) ) { return null; } }
		return parts;
	}
	function swatch_css( icon ) {
		var cols = swatch_colors( icon );
		if ( cols ) { return { 'background-image': cols.length === 2 ? 'linear-gradient(135deg,' + cols[0] + ' 50%,' + cols[1] + ' 50%)' : 'none', 'background-color': cols[0] }; }
		return icon ? { 'background-image': 'url("' + String( icon ).replace( /["\\]/g, '' ) + '")', 'background-color': '' } : { 'background-image': '', 'background-color': '' };
	}
	function set_swatch( btn, icon ) {
		var cols = swatch_colors( icon );
		$( btn ).attr( 'data-icon', icon || '' )
			.toggleClass( 'has-color', !! cols )
			.toggleClass( 'has-image', !! icon && ! cols )
			.css( swatch_css( icon ) );
		render();
	}

	/* ------------------------------------------------------------------ */
	/* Type                                                                */
	/* ------------------------------------------------------------------ */

	window.ecosv2_set_type = function( el ) {
		var t = $( el ).attr( 'data-type' );
		if ( ! t ) { return; }
		if ( ! VARIATION[ t ] && ! has_pro() ) {
			if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'modifiers' } ); }
			return;
		}
		state.type = t;
		$box().attr( 'data-type', t );
		$( '.ecosv2-tile' ).removeClass( 'is-on' );
		$( el ).addClass( 'is-on' );
		$f( 'ecosv2_type_hint' ).text( state.hints[ t ] || '' );
		render();
	};

	/* ------------------------------------------------------------------ */
	/* Naming                                                              */
	/* ------------------------------------------------------------------ */

	function derive_label() {
		var n = val( 'ecosv2_name' );
		if ( state.label_touched ) { return; }
		$f( 'ecosv2_label' ).val( n === '' ? '' : ( L.label_tpl || 'Choose a %s' ).replace( '%s', n.toLowerCase() ) );
	}

	/* ------------------------------------------------------------------ */
	/* Choices                                                             */
	/* ------------------------------------------------------------------ */

	window.ecosv2_add_row = function( name, price, focus ) {
		var $r = $( '<div class="ecosv2-irow">' );
		$r.append( '<span class="ecosv2-drag" title="' + esc( L.drag || 'Drag to reorder' ) + '" aria-hidden="true"><svg viewBox="0 0 10 16"><circle cx="3" cy="3" r="1.3"/><circle cx="7" cy="3" r="1.3"/><circle cx="3" cy="8" r="1.3"/><circle cx="7" cy="8" r="1.3"/><circle cx="3" cy="13" r="1.3"/><circle cx="7" cy="13" r="1.3"/></svg></span>' );
		$r.append( '<button type="button" class="ecosv2-swatch ecosv2-col-swatch" data-swatch-only data-icon="" title="' + esc( L.swatch_title || 'Set a color or image' ) + '" aria-label="' + esc( L.swatch_title || 'Set a color or image' ) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg></button>' );
		$r.append( $( '<input type="text" class="ecv2-input ecosv2-nm">' ).attr( 'placeholder', L.choice_name || 'Choice name' ).val( name || '' ) );
		$r.append( $( '<input type="text" class="ecv2-input ecosv2-sku">' ).attr( 'placeholder', '-sm' ) );
		$r.append( '<div class="ecosv2-prefix"><span>' + esc( V.currency || '$' ) + '</span><input type="number" step=".01" class="ecv2-input ecosv2-pr" placeholder="0.00" value="' + esc( price || '' ) + '"></div>' );
		$r.append( '<div class="ecosv2-prefix"><span>' + esc( V.weight_unit || 'lb' ) + '</span><input type="number" step=".01" class="ecv2-input ecosv2-wt" placeholder="0.0"></div>' );
		$r.append( '<button type="button" class="ecosv2-rm" aria-label="' + esc( L.remove || 'Remove' ) + '">×</button>' );
		$f( 'ecosv2_rows' ).append( $r );
		if ( focus !== false && ! name ) { $r.find( '.ecosv2-nm' ).trigger( 'focus' ); }
		render();
		return $r;
	};

	window.ecosv2_toggle_paste = function() {
		var $p = $f( 'ecosv2_paste' );
		$p.prop( 'hidden', ! $p.is( '[hidden]' ) );
		if ( ! $p.is( '[hidden]' ) ) { $f( 'ecosv2_pastebox' ).trigger( 'focus' ); }
	};

	window.ecosv2_do_paste = function() {
		var lines = val( 'ecosv2_pastebox' ).split( /\r?\n/ );
		// Drop the empty seed row if the user pasted straight in.
		$( '#ecosv2_rows .ecosv2-irow' ).each( function() { if ( $.trim( $( this ).find( '.ecosv2-nm' ).val() ) === '' ) { $( this ).remove(); } } );
		$.each( lines, function( i, l ) {
			l = $.trim( l ); if ( l === '' ) { return; }
			var m = l.match( /^(.*?)\s*\+\s*([\d.]+)\s*$/ );
			ecosv2_add_row( m ? $.trim( m[ 1 ] ) : l, m ? m[ 2 ] : '', false );
		} );
		$f( 'ecosv2_pastebox' ).val( '' );
		$f( 'ecosv2_paste' ).prop( 'hidden', true );
		render();
	};

	function rows() {
		return $( '#ecosv2_rows .ecosv2-irow' ).map( function() {
			var $r = $( this );
			return {
				name:   $.trim( $r.find( '.ecosv2-nm' ).val() ),
				sku:    $.trim( $r.find( '.ecosv2-sku' ).val() ),
				price:  $.trim( $r.find( '.ecosv2-pr' ).val() ),
				weight: $.trim( $r.find( '.ecosv2-wt' ).val() ),
				icon:   is_swatch_type( state.type ) ? ( $r.find( '.ecosv2-swatch' ).attr( 'data-icon' ) || '' ) : ''
			};
		} ).get();
	}

	function pick_image( btn ) {
		if ( ! window.wp || ! wp.media ) { toast( L.no_media || 'The media library is not available on this page.', 'error' ); return; }
		var frame = wp.media( { title: L.pick_photo || 'Choose a photo', button: { text: L.use_photo || 'Use this photo' }, multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function() {
			var a = frame.state().get( 'selection' ).first().toJSON();
			set_swatch( btn, ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url );
		} );
		frame.open();
	}

	function close_swatch_popover() {
		$( '.ecosv2-swpop' ).remove();
		$( document ).off( 'mousedown.ecosv2swpop' );
	}

	/* Color ( every store ) or image ( PRO ) picker, anchored to the row's swatch button. */
	function open_swatch_popover( btn ) {
		close_swatch_popover();
		var $btn = $( btn ), icon = $btn.attr( 'data-icon' ) || '', cols = swatch_colors( icon ) || [];
		var c1 = cols[0] || '#3b82f6', c2 = cols[1] || '#ffffff', two = cols.length === 2, pro = has_pro();
		var lock = '<svg class="ecosv2-lock-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>';
		var $p = $( '<div class="ecosv2-swpop" role="dialog">' +
			'<div class="ecosv2-swpop-tabs"><button type="button" class="is-on" data-tab="color">' + esc( L.color || 'Color' ) + '</button><button type="button" data-tab="image">' + esc( L.image || 'Image' ) + ( pro ? '' : ' ' + lock ) + '</button></div>' +
			'<div class="ecosv2-swpop-body" data-tab="color">' +
				'<div class="ecosv2-swpop-row"><input type="color" class="ecosv2-swpop-c1"><input type="text" class="ecv2-input ecosv2-swpop-h1" maxlength="7" spellcheck="false"></div>' +
				'<label class="ecosv2-swpop-two"><input type="checkbox" class="ecosv2-swpop-toggle2"> ' + esc( L.two_tone || 'Two-tone' ) + '</label>' +
				'<div class="ecosv2-swpop-row ecosv2-swpop-row2"><input type="color" class="ecosv2-swpop-c2"><input type="text" class="ecv2-input ecosv2-swpop-h2" maxlength="7" spellcheck="false"></div>' +
				'<div class="ecosv2-swpop-presets"></div>' +
			'</div>' +
			'<div class="ecosv2-swpop-body" data-tab="image" hidden>' +
				( pro
					? '<p>' + esc( L.image_hint || 'Use a photo or pattern instead of a flat color.' ) + '</p><button type="button" class="ecv2-btn ecv2-btn-sm ecosv2-swpop-pick">' + esc( L.choose_image || 'Choose image…' ) + '</button>'
					: '<p><span class="ecosv2-pill">' + esc( ( window.wp_easycart_edition && window.wp_easycart_edition.badge_pro ) || 'Pro/Premium' ) + '</span> ' + esc( L.image_pro || 'Photo swatches are included with Pro and Premium licenses. Color swatches are included with every store.' ) + '</p><button type="button" class="ecv2-btn ecv2-btn-sm ecosv2-swpop-upsell">' + esc( L.unlock || 'Unlock' ) + '</button>' ) +
			'</div>' +
			'<div class="ecosv2-swpop-foot"><span class="ecosv2-swpop-prev"></span><span class="ecosv2-spacer"></span>' +
				( icon ? '<button type="button" class="ecosv2-link ecosv2-swpop-clear">' + esc( L.remove_swatch || 'Remove' ) + '</button>' : '' ) +
				'<button type="button" class="ecv2-btn ecv2-btn-sm ecosv2-swpop-cancel">' + esc( L.cancel || 'Cancel' ) + '</button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary ecosv2-swpop-ok">' + esc( L.apply || 'Apply' ) + '</button></div>' +
		'</div>' );
		$p.find( '.ecosv2-swpop-c1, .ecosv2-swpop-h1' ).val( c1 );
		$p.find( '.ecosv2-swpop-c2, .ecosv2-swpop-h2' ).val( c2 );
		$p.find( '.ecosv2-swpop-toggle2' ).prop( 'checked', two );
		$p.find( '.ecosv2-swpop-row2' ).toggle( two );
		$.each( [ '#000000', '#ffffff', '#6b7280', '#ef4444', '#f97316', '#eab308', '#22c55e', '#0ea5e9', '#3b82f6', '#8b5cf6', '#ec4899', '#78350f' ], function( k, hex ) {
			$p.find( '.ecosv2-swpop-presets' ).append( $( '<button type="button" class="ecosv2-swpop-preset">' ).attr( { 'data-hex': hex, title: hex } ).css( 'background', hex ) );
		} );
		$( 'body' ).append( $p );
		var off = $btn.offset();
		$p.css( { top: off.top + $btn.outerHeight() + 6, left: Math.max( 8, Math.min( off.left, $( window ).width() - $p.outerWidth() - 8 ) ) } );

		function cur() {
			var a = $.trim( $p.find( '.ecosv2-swpop-h1' ).val() ), b = $p.find( '.ecosv2-swpop-toggle2' ).is( ':checked' ) ? $.trim( $p.find( '.ecosv2-swpop-h2' ).val() ) : '';
			return b ? a + ',' + b : a;
		}
		function refresh() {
			var v = cur(), ok = !! swatch_colors( v );
			$p.find( '.ecosv2-swpop-prev' ).css( ok ? swatch_css( v ) : { 'background-image': '', 'background-color': '' } ).toggleClass( 'is-bad', ! ok );
			$p.find( '.ecosv2-swpop-ok' ).prop( 'disabled', ! ok );
		}
		$p.on( 'input', '.ecosv2-swpop-c1', function() { $p.find( '.ecosv2-swpop-h1' ).val( this.value ); refresh(); } );
		$p.on( 'input', '.ecosv2-swpop-c2', function() { $p.find( '.ecosv2-swpop-h2' ).val( this.value ); refresh(); } );
		$p.on( 'input', '.ecosv2-swpop-h1', function() { if ( /^#[0-9a-f]{6}$/i.test( this.value ) ) { $p.find( '.ecosv2-swpop-c1' ).val( this.value ); } refresh(); } );
		$p.on( 'input', '.ecosv2-swpop-h2', function() { if ( /^#[0-9a-f]{6}$/i.test( this.value ) ) { $p.find( '.ecosv2-swpop-c2' ).val( this.value ); } refresh(); } );
		$p.on( 'change', '.ecosv2-swpop-toggle2', function() { $p.find( '.ecosv2-swpop-row2' ).toggle( this.checked ); refresh(); } );
		$p.on( 'click', '.ecosv2-swpop-preset', function() { var hex = $( this ).attr( 'data-hex' ); $p.find( '.ecosv2-swpop-c1, .ecosv2-swpop-h1' ).val( hex ); refresh(); } );
		$p.on( 'click', '.ecosv2-swpop-tabs button', function() {
			var t = $( this ).attr( 'data-tab' );
			$p.find( '.ecosv2-swpop-tabs button' ).removeClass( 'is-on' ); $( this ).addClass( 'is-on' );
			$p.find( '.ecosv2-swpop-body' ).prop( 'hidden', true ).filter( '[data-tab="' + t + '"]' ).prop( 'hidden', false );
			$p.find( '.ecosv2-swpop-ok' ).toggle( t === 'color' );
		} );
		$p.on( 'click', '.ecosv2-swpop-pick', function() { close_swatch_popover(); pick_image( btn ); } );
		$p.on( 'click', '.ecosv2-swpop-upsell', function() { close_swatch_popover(); if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'images' } ); } } );
		$p.on( 'click', '.ecosv2-swpop-clear', function() { close_swatch_popover(); set_swatch( btn, '' ); } );
		$p.on( 'click', '.ecosv2-swpop-cancel', close_swatch_popover );
		$p.on( 'click', '.ecosv2-swpop-ok', function() {
			var c = swatch_colors( cur() );
			if ( ! c ) { return; }
			close_swatch_popover();
			set_swatch( btn, c.join( ',' ).toLowerCase() );
		} );
		$( document ).on( 'mousedown.ecosv2swpop', function( e ) {
			if ( ! $( e.target ).closest( '.ecosv2-swpop, .media-modal' ).length ) { close_swatch_popover(); }
		} );
		refresh();
		$p.find( '.ecosv2-swpop-h1' ).trigger( 'focus' );
	}

	/* ------------------------------------------------------------------ */
	/* Derived display                                                     */
	/* ------------------------------------------------------------------ */

	function render() {
		var t = state.type, pro = has_pro(), list = takes_list( t );
		$f( 'ecosv2_choices' ).prop( 'hidden', ! list );
		$f( 'ecosv2_modifier' ).prop( 'hidden', list || ! pro );
		$f( 'ecosv2_meta_num' ).prop( 'hidden', ! NUMERIC[ t ] );
		$f( 'ecosv2_meta_text' ).prop( 'hidden', ! TEXTUAL[ t ] );
		$f( 'ecosv2_error_text' ).prop( 'hidden', ! $f( 'ecosv2_required' ).is( ':checked' ) );

		var name = val( 'ecosv2_name' );
		$f( 'ecosv2_title' ).text( name !== '' ? ( L.title_named || 'Create “%s”' ).replace( '%s', name ) : ( L.title || 'Create an option set' ) );
		$f( 'ecosv2_label_hint' ).text( state.label_touched ? ( L.label_custom || 'Custom label.' ) : ( L.label_generated || 'Generated from the name — edit to override.' ) );

		var all = rows(), named = $.grep( all, function( r ) { return r.name !== ''; } );
		var need_two = list && all.length > 0 && named.length < 2;
		$f( 'ecosv2_count' )
			.text( ( named.length === 1 ? ( L.one_choice || '1 choice' ) : ( L.n_choices || '%d choices' ).replace( '%d', named.length ) ) + ( need_two ? ' — ' + ( L.need_two || 'add at least 2' ) : '' ) )
			.toggleClass( 'ecosv2-err', need_two );

		// Preview.
		var label = val( 'ecosv2_label' ) || $f( 'ecosv2_label' ).attr( 'placeholder' );
		$f( 'ecosv2_preview_label' ).text( label );
		$f( 'ecosv2_preview_meta' ).text( t === 'basic-combo' ? ( L.dropdown || 'Dropdown' ) : t === 'basic-swatch' ? ( L.swatches || 'Swatches' ) : '' );
		var $b = $f( 'ecosv2_preview_body' ).empty();
		if ( ! named.length ) {
			$b.append( $( '<span class="ecosv2-preview-empty">' ).text( L.preview_empty || 'Add a choice to see it here.' ) );
		} else if ( t === 'basic-combo' || t === 'radio' || t === 'checkbox' || t === 'grid' ) {
			var first = named[ 0 ];
			$b.append( $( '<div class="ecosv2-preview-dd">' ).append( $( '<span>' ).text( first.name + ( parseFloat( first.price ) ? ' (+' + fmt( first.price ) + ')' : '' ) ) ).append( '<span>▾</span>' ) );
			$b.append( $( '<div class="ecosv2-hint ecosv2-preview-list">' ).text( $.map( named, function( r ) { return r.name + ( parseFloat( r.price ) ? ' +' + fmt( r.price ) : '' ); } ).join( ' · ' ) ) );
		} else {
			var $chips = $( '<div class="ecosv2-preview-chips">' );
			$.each( named, function( i, r ) {
				var $c = $( '<span class="ecosv2-preview-chip">' ).toggleClass( 'is-sel', i === 0 );
				if ( r.icon ) { $c.append( $( '<span class="ecosv2-preview-sw">' ).css( swatch_css( r.icon ) ) ); }
				$c.append( document.createTextNode( r.name + ( parseFloat( r.price ) ? ' +' + fmt( r.price ) : '' ) ) );
				$chips.append( $c );
			} );
			$b.append( $chips );
		}

		var ok = name !== '' && ( ! list || named.length >= 2 );
		$( '.ecosv2-cta' ).prop( 'disabled', ! ok );
	}

	/* ------------------------------------------------------------------ */
	/* Open / close                                                        */
	/* ------------------------------------------------------------------ */

	function reset() {
		state.label_touched = false;
		$f( 'ecosv2_name' ).val( '' ); $f( 'ecosv2_label' ).val( '' );
		$f( 'ecosv2_rows' ).empty();
		$f( 'ecosv2_pastebox' ).val( '' ); $f( 'ecosv2_paste' ).prop( 'hidden', true );
		$( '#ecosv2_input_price, #ecosv2_meta_min, #ecosv2_meta_max, #ecosv2_meta_step, #ecosv2_meta_min_length, #ecosv2_meta_max_length, #ecosv2_error_text' ).val( '' );
		$( '#ecosv2_required, #ecosv2_preselect' ).prop( 'checked', false );
		$( '.ecosv2-err' ).prop( 'hidden', true ); $( '.is-invalid' ).removeClass( 'is-invalid' );
	}

	function product_context() {
		var $p = $( '#ecpsv2_box' );
		if ( ! $p.length || ! $p.is( ':visible' ) ) { return null; }
		return {
			title: $.trim( $( '#ecpsv2_title_input' ).val() ),
			slot:  $( '#ecpsv2_opt_list .ecpsv2-opt-row' ).length + 1
		};
	}

	window.ecosv2_open = function( opts ) {
		opts = opts || {};
		var ctx = product_context();
		state.origin = opts.origin || ( ctx ? 'product' : 'standalone' );
		/* After "Create": open the full editor on the Option Sets screens; elsewhere ( product editors ) just close. */
		state.after = opts.after || ( ( state.origin === 'standalone' && V.edit_after_create ) ? 'edit' : 'close' );
		$box().attr( 'data-origin', state.origin );

		reset();
		// Default type: what the caller asked for, else Dropdown ( a modifier request on free falls back too ).
		var t = opts.type || 'basic-combo';
		var $tile = $( '.ecosv2-tile[data-type="' + t + '"]' ).not( '.is-locked' );
		if ( ! $tile.length ) { $tile = $( '.ecosv2-tile[data-type="basic-combo"]' ); }
		state.type = $tile.attr( 'data-type' );
		$( '.ecosv2-tile' ).removeClass( 'is-on' ); $tile.addClass( 'is-on' );
		$box().attr( 'data-type', state.type );
		$f( 'ecosv2_type_hint' ).text( state.hints[ state.type ] || '' );

		if ( ctx ) {
			var who = ctx.title !== '' ? ctx.title : ( L.this_product || 'this product' );
			$f( 'ecosv2_sub_product' ).html( ( L.sub_product || 'It will be added to <strong>%1</strong> as slot %2. Reusable on any other product too.' ).replace( '%1', esc( who ) ).replace( '%2', ctx.slot ) );
			$( '#ecpsv2_box' ).addClass( 'is-receded' );
		}

		// Two empty rows to start: the minimum a variation needs.
		if ( takes_list( state.type ) ) { ecosv2_add_row( '', '', false ); ecosv2_add_row( '', '', false ); }
		render();
		loader( false );

		var box = $box();
		box.appendTo( document.body ); /* always last in <body>, so it stacks above the product panel at the same z-index */
		box.stop( true, true ).css( 'display', 'flex' ).hide().fadeIn( 120 );
		$( 'body' ).addClass( 'ecosv2-open' );
		setTimeout( function() { $f( 'ecosv2_name' ).trigger( 'focus' ); }, 180 );
		return false;
	};

	window.ecosv2_close = function() {
		close_swatch_popover();
		$box().stop( true, true ).fadeOut( 120 );
		$( 'body' ).removeClass( 'ecosv2-open' );
		$( '#ecpsv2_box' ).removeClass( 'is-receded' );
		return false;
	};

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	function mark( id, err_id, bad ) { $f( id ).toggleClass( 'is-invalid', bad ); if ( err_id ) { $f( err_id ).prop( 'hidden', ! bad ); } }

	window.ecosv2_save = function( next ) {
		if ( state.saving ) { return false; }
		var name = val( 'ecosv2_name' ), list = takes_list( state.type );
		var items = $.grep( rows(), function( r ) { return r.name !== ''; } );
		var ok = true;
		if ( name === '' ) { mark( 'ecosv2_name', 'ecosv2_name_err', true ); ok = false; } else { mark( 'ecosv2_name', 'ecosv2_name_err', false ); }
		if ( list && items.length < 2 ) { ok = false; $f( 'ecosv2_count' ).addClass( 'ecosv2-err' ); $( '#ecosv2_rows .ecosv2-nm' ).filter( function() { return $.trim( this.value ) === ''; } ).first().trigger( 'focus' ); }
		if ( ! ok ) { return false; }

		state.saving = true; loader( true );
		var payload = {
			action:            'ec_admin_ajax_ecv2_save_option_set',
			wp_easycart_nonce: val( 'wp_easycart_optionset_quick_edit_nonce' ),
			option_type:       state.type,
			option_name:       name,
			option_label:      val( 'ecosv2_label' ) || $f( 'ecosv2_label' ).attr( 'placeholder' ),
			items:             JSON.stringify( list ? items : [] ),
			preselect_first:   $f( 'ecosv2_preselect' ).is( ':checked' ) ? 1 : 0,
			input_price:       val( 'ecosv2_input_price' ),
			option_required:   $f( 'ecosv2_required' ).is( ':checked' ) ? 1 : 0,
			option_error_text: val( 'ecosv2_error_text' ),
			meta_min:          val( 'ecosv2_meta_min' ),
			meta_max:          val( 'ecosv2_meta_max' ),
			meta_step:         val( 'ecosv2_meta_step' ),
			meta_min_length:   val( 'ecosv2_meta_min_length' ),
			meta_max_length:   val( 'ecosv2_meta_max_length' )
		};
		$.post( AJAX, payload, function( data ) {
			var r; try { r = JSON.parse( data ); } catch ( e ) { r = null; }
			state.saving = false; loader( false );
			if ( ! r || ! r.option_id ) { toast( ( r && r.error ) ? r.error : ( L.error || 'An error occurred. Please try again.' ), 'error' ); return; }
			announce( r );
			toast( ( L.created || '“%s” created.' ).replace( '%s', r.option_name ) );
			if ( state.origin === 'standalone' ) {
				if ( next === 'another' ) { reset(); if ( takes_list( state.type ) ) { ecosv2_add_row( '', '', false ); ecosv2_add_row( '', '', false ); } render(); $f( 'ecosv2_name' ).trigger( 'focus' ); return; }
				if ( state.after === 'edit' && r.edit_url ) {
					/* Keep the panel busy while the editor loads, so nothing can be double-submitted. */
					state.saving = true; loader( true );
					window.location.href = r.edit_url;
					return;
				}
				ecosv2_close();
				if ( V.reload_after_create ) { window.location.reload(); }
				return;
			}
			ecosv2_close();
		} ).fail( function() { state.saving = false; loader( false ); toast( L.network || 'Network error. Please try again.', 'error' ); } );
		return false;
	};

	/* Mirror what the legacy slideouts did on success so every consumer keeps working. */
	function announce( r ) {
		var basic = !! VARIATION[ r.option_type ];
		if ( basic ) {
			for ( var i = 1; i <= 5; i++ ) {
				$( '#ec_new_product_option' + i + ' option:first, #option' + i + ' option:first' ).after( $( '<option />', { value: r.option_id, text: r.option_name } ) );
			}
			if ( typeof window.wp_easycart_pro_add_new_basic_option_insert === 'function' ) { window.wp_easycart_pro_add_new_basic_option_insert( r.option_id ); }
		} else if ( typeof window.wp_easycart_pro_add_new_advanced_option_insert === 'function' ) {
			window.wp_easycart_pro_add_new_advanced_option_insert( r.option_id );
		}
		$( document ).trigger( 'ecosv2:created', [ r ] );
	}

	/* ------------------------------------------------------------------ */
	/* Wiring                                                              */
	/* ------------------------------------------------------------------ */

	$( function() {
		var $b = $box();
		if ( ! $b.length ) { return; }
		try { state.hints = JSON.parse( $b.attr( 'data-type-hints' ) || '{}' ); } catch ( e ) { state.hints = {}; }

		// Route the legacy slideout ids here ( chains with any wrapper already installed ).
		var prev = window.wp_easycart_admin_open_slideout;
		window.wp_easycart_admin_open_slideout = function( id ) {
			if ( id === 'new_option_box' )     { return ecosv2_open( { type: 'basic-combo' } ); }
			if ( id === 'new_adv_option_box' ) { return ecosv2_open( { type: has_pro() ? 'text' : 'basic-combo' } ); }
			if ( id === 'new_optionitem_box' || id === 'new_adv_optionitem_box' ) { return false; } // items are created inline now
			return typeof prev === 'function' ? prev.apply( this, arguments ) : undefined;
		};
		// Option Sets page: the "Add New" link opens the panel instead of the form page.
		$( document ).on( 'click', 'a[href*="ec_admin_form_action=add-new-option"]', function( e ) {
			e.preventDefault(); ecosv2_open( { origin: 'standalone' } );
		} );

		$b.on( 'input', '#ecosv2_name', function() { mark( 'ecosv2_name', 'ecosv2_name_err', false ); derive_label(); render(); } );
		$b.on( 'input', '#ecosv2_label', function() { state.label_touched = ( this.value !== '' ); render(); } );
		$b.on( 'input change', '#ecosv2_rows input, #ecosv2_required', function() { render(); } );
		$b.on( 'click', '#ecosv2_rows .ecosv2-rm', function() { $( this ).closest( '.ecosv2-irow' ).remove(); render(); } );
		$b.on( 'click', '#ecosv2_rows .ecosv2-swatch', function() { open_swatch_popover( this ); } );
		$b.find( '.ecosv2-body' ).on( 'scroll', close_swatch_popover );
		$b.on( 'keydown', '#ecosv2_rows .ecosv2-nm', function( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				var $row = $( this ).closest( '.ecosv2-irow' );
				if ( $row.is( ':last-child' ) ) { ecosv2_add_row(); } else { $row.next().find( '.ecosv2-nm' ).trigger( 'focus' ); }
			}
		} );
		if ( $.fn.sortable ) {
			$f( 'ecosv2_rows' ).sortable( { handle: '.ecosv2-drag', axis: 'y', containment: 'parent', tolerance: 'pointer', placeholder: 'ecosv2-irow ecosv2-irow-placeholder', update: render } );
		}
		$b.on( 'mousedown', function( e ) { if ( e.target === this ) { ecosv2_close(); } } );
		$( document ).on( 'keydown', function( e ) {
			if ( e.key === 'Escape' && $( '.ecosv2-swpop' ).length ) { e.stopImmediatePropagation(); close_swatch_popover(); return; }
			if ( e.key === 'Escape' && $b.is( ':visible' ) && ! $( '#ec_admin_upsell_popup' ).is( ':visible' ) ) { e.stopImmediatePropagation(); ecosv2_close(); }
		} );
		$b.on( 'keydown', function( e ) { if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) { e.preventDefault(); ecosv2_save( 'close' ); } } );
	} );

} )( jQuery );