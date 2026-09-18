/**
 * WP EasyCart Admin — Option Set editor ( V2 ).
 *
 * State lives in `choices` ( array of the models produced by
 * wp_easycart_admin_option_editor_v2::item_to_model ). The table and the
 * storefront preview are re-rendered from state; save() posts the whole set.
 *
 * Depends on catalog-v2.js for ecv2_toast / ecv2_show_confirm / ecv2_catalog.
 */
jQuery( function( $ ) {
	'use strict';

	var $root = $( '#ecos' );
	if ( ! $root.length ) { return; }

	var D = JSON.parse( $( '#ecos_data' ).text() || '{}' );
	var L = D.i18n || {};
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;

	var choices = D.choices || [];
	var removed = [];          /* ids removed since last save ( for impact + server diff ) */
	var type    = D.type;
	var dirty   = false;
	var tmp_seq = 1;
	var open_drawer = null;    /* index with the advanced drawer open */

	var LISTED   = { 'basic-combo': 1, 'basic-swatch': 1, 'combo': 1, 'swatch': 1, 'radio': 1, 'checkbox': 1, 'grid': 1 };
	var SWATCH   = { 'basic-swatch': 1, 'swatch': 1 };
	var BASIC    = { 'basic-combo': 1, 'basic-swatch': 1 };
	var NUMERIC  = { 'number': 1, 'dimensions1': 1, 'dimensions2': 1 };
	var TEXTUAL  = { 'text': 1, 'textarea': 1 };

	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function num( v ) { var f = parseFloat( String( v ).replace( /[^0-9.\-]/g, '' ) ); return isNaN( f ) ? 0 : f; }
	function fmt( v ) { var d = isNaN( parseInt( D.decimals, 10 ) ) ? 2 : parseInt( D.decimals, 10 ); return ( D.currency || '$' ) + Math.abs( v ).toFixed( d ); }
	function is_listed() { return !! LISTED[ type ]; }
	function is_swatch() { return !! SWATCH[ type ]; }
	function is_basic() { return !! BASIC[ type ]; }
	function adv_pricing() { return D.is_pro && ! is_basic(); }

	/* ------------------------------------------------------------------ */
	/* Locked ( modifier set without PRO ): read-only, actions open upsell */
	/* ------------------------------------------------------------------ */

	function is_locked() { return !! D.locked; }
	function upsell() {
		if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'modifiers' } ); }
		else if ( L.locked ) { ecv2_toast( L.locked, 'info' ); }
		return false;
	}
	function apply_lock() {
		if ( ! is_locked() ) { return; }
		$root.find( '.ecdv2-main' ).find( 'input, select, textarea' ).not( '#ecos_locked *' ).prop( 'disabled', true );
		$root.find( '.ecos-row' ).attr( 'draggable', 'false' );
		$root.find( '.ecos-sw-rm, .ecos-act[data-act="dup"], .ecos-act[data-act="remove"]' ).remove();
		$( '#ecos_add, #ecos_paste' ).prop( 'disabled', true );
	}

	/* ------------------------------------------------------------------ */
	/* Dirty state                                                         */
	/* ------------------------------------------------------------------ */

	function mark_dirty() {
		dirty = true;
		$( '#ecos_dirty' ).addClass( 'is-visible' ).removeClass( 'is-saved' ).text( L.unsaved || 'Unsaved changes' );
	}
	function mark_saved() {
		dirty = false;
		$( '#ecos_dirty' ).addClass( 'is-visible is-saved' ).text( L.saved || 'Saved' );
		setTimeout( function() { if ( ! dirty ) { $( '#ecos_dirty' ).removeClass( 'is-visible' ); } }, 2500 );
	}
	$root.on( 'input change', '[data-track]', mark_dirty );
	$( window ).on( 'beforeunload', function() { if ( dirty ) { return L.leave || 'You have unsaved changes.'; } } );

	/* Header + preview follow the fields */
	$( '#ecos_name' ).on( 'input', function() {
		$( '#ecos_h_title' ).text( this.value || '…' );
		$( '#ecos_label' ).attr( 'placeholder', this.value );
		if ( ! $.trim( $( '#ecos_label' ).val() ) ) { $( '#ecos_pv_label' ).text( this.value ); }
	} );
	$( '#ecos_label' ).on( 'input', function() { $( '#ecos_pv_label' ).text( this.value || $( '#ecos_name' ).val() ); } );
	$( '#ecos_required' ).on( 'change', function() {
		$( '#ecos_pv_req' ).toggle( this.checked );
		$( '#ecos_h_req' ).toggleClass( 'is-active', this.checked ).text( this.checked ? 'Required' : 'Optional' );
	} );
	$( '#ecos_meta_url_var' ).on( 'input', update_url_var_desc );

	function update_url_var_desc() {
		var v = $.trim( $( '#ecos_meta_url_var' ).val() ) || $( '#ecos_meta_url_var' ).attr( 'placeholder' );
		var first = choices.length ? choices[0].name : 'value';
		$( '#ecos_url_var_desc' ).text( '?' + v + '=' + encodeURIComponent( String( first ).toLowerCase() ) + ' pre-selects that choice on the product page.' );
	}

	/* ------------------------------------------------------------------ */
	/* Type handling                                                       */
	/* ------------------------------------------------------------------ */

	$( '#ecos_type' ).on( 'change', function() {
		var next = this.value;
		var was_basic = !! BASIC[ D.type ];
		if ( was_basic && ! BASIC[ next ] && D.usage > 0 ) {
			ecv2_show_confirm( 'Change type?', ( L.type_warn || 'Switching to a modifier type removes variant stock tracking on %d products.' ).replace( '%d', D.usage ) ).then( function( ok ) {
				if ( ! ok ) { $( '#ecos_type' ).val( type ); return; }
				apply_type( next );
			} );
			return;
		}
		apply_type( next );
	} );

	function apply_type( next ) {
		type = next;
		var listed = is_listed();
		$( '#ecos_h_type' ).text( ( D.types[ type ] || {} ).label || type );
		/* Required: basic types are locked on */
		var $req = $( '#ecos_required' );
		if ( is_basic() ) { $req.prop( 'checked', true ).prop( 'disabled', true ).closest( '.ecv2-toggle' ).addClass( 'ecv2-toggle-locked' ); $( '#ecos_required_hint' ).text( 'Variation sets are always required.' ); }
		else { $req.prop( 'disabled', false ).closest( '.ecv2-toggle' ).removeClass( 'ecv2-toggle-locked' ); $( '#ecos_required_hint' ).text( 'Turn off for optional add-ons.' ); }
		$req.trigger( 'change' );
		/* Description */
		var $d = $( '#ecos_type_desc' );
		if ( ! listed ) { $d.text( L.type_input || '' ).removeClass( 'is-warn' ); }
		else if ( is_basic() ) { $d.text( D.usage ? ( L.type_safe || '' ).replace( '%d', D.usage ) : '' ).removeClass( 'is-warn' ); }
		else if ( BASIC[ D.type ] && D.usage ) { $d.text( ( L.type_warn || '' ).replace( '%d', D.usage ) ).addClass( 'is-warn' ); }
		else { $d.text( '' ).removeClass( 'is-warn' ); }
		/* Choices card mode */
		$( '#ecos_input_note' ).toggle( ! listed );
		$( '#ecos_tbl, #ecos_add, #ecos_paste, #ecos_count_hint' ).toggle( listed );
		$( '#ecos_stock_note' ).toggle( listed && D.stock_rows > 0 );
		$( '#ecos_choices_hint' ).text( listed ? 'Drag to reorder. Price and weight adjust the product unless you pick Override.' : 'Default value and price rule for the shopper input.' );
		if ( ! listed && ! choices.length ) { choices.push( blank() ); }
		/* Rules visibility */
		$( '[data-rule]' ).each( function() {
			var r = $( this ).data( 'rule' ), show = r === 'all' || ( r === 'swatch' && is_swatch() ) || ( r === 'number' && NUMERIC[ type ] ) || ( r === 'text' && TEXTUAL[ type ] ) || ( r === 'file' && type === 'file' );
			$( this ).toggle( !! show );
		} );
		render();
	}

	/* ------------------------------------------------------------------ */
	/* Choices table                                                       */
	/* ------------------------------------------------------------------ */

	function blank() {
		return { id: 0, tmp: 't' + ( tmp_seq++ ), name: '', sku: '', price_type: 'add', price: 0, weight_type: 'add', weight: 0, icon: '', initially_selected: false, initial_value: '', custom_label_on: false, custom_label: '', allow_download: true, disallow_shipping: false, download_override: '', download_addition: '' };
	}

	function price_types() {
		var t = [ [ 'add', '+ Add' ], [ 'onetime', 'One-time' ], [ 'override', 'Override' ], [ 'multiply', '× Multiply' ] ];
		if ( TEXTUAL[ type ] ) { t.push( [ 'per_char', 'Per character' ] ); }
		return t;
	}

	function adj_html( i, kind ) {
		var c = choices[ i ], k = kind === 'price' ? 'price' : 'weight', kt = k + '_type';
		var val = num( c[ k ] ), types = kind === 'price' ? price_types() : [ [ 'add', '+ Add' ], [ 'onetime', 'One-time' ], [ 'override', 'Override' ], [ 'multiply', '× Multiply' ] ];
		var html = '<span class="ecos-adj">';
		if ( adv_pricing() ) {
			html += '<select data-i="' + i + '" data-k="' + kt + '" class="ecos-adj-type">';
			$.each( types, function( j, t ) { html += '<option value="' + t[0] + '"' + ( c[ kt ] === t[0] ? ' selected' : '' ) + '>' + t[1] + '</option>'; } );
			html += '</select>';
		} else {
			html += '<span class="ecos-adj-fixed">' + ( kind === 'price' ? ( D.currency || '$' ) : '' ) + '</span>';
		}
		html += '<input type="text" inputmode="decimal" data-i="' + i + '" data-k="' + k + '" class="ecos-adj-val" value="' + ( val ? val : '' ) + '" placeholder="0' + ( kind === 'price' ? '.00' : '' ) + '"></span>';
		return html;
	}

	function render() {
		var $tb = $( '#ecos_tbody' ).empty();
		var sw = is_swatch();
		$( '#ecos_tbl' ).toggleClass( 'has-swatch', sw ).toggleClass( 'is-input', ! is_listed() );
		$.each( choices, function( i, c ) {
			var $tr = $( '<tr class="ecos-row" draggable="true" data-i="' + i + '"></tr>' );
			$tr.append( '<td class="ecos-col-drag"><span class="ecv2-drag-handle" title="Drag to reorder"><span class="dashicons dashicons-menu"></span></span></td>' );
			/* Swatch: color ( free ) or image ( PRO ). The button previews whatever is set; clicking opens the picker popover. */
			var sw_cell = '<td class="ecos-col-sw">';
			var cols = swatch_colors( c.icon );
			sw_cell += '<button type="button" class="ecos-sw' + ( c.icon ? ' has' : '' ) + ( cols ? ' is-color' : '' ) + '" data-i="' + i + '" title="' + ( c.icon ? 'Change swatch' : 'Set swatch color or image' ) + '" style="' + swatch_style( c.icon ) + '">' + ( c.icon ? '' : '<span class="dashicons dashicons-plus-alt2"></span>' ) + '</button>';
			if ( c.icon ) { sw_cell += '<button type="button" class="ecos-sw-rm" data-i="' + i + '" title="Remove swatch">&times;</button>'; }
			$tr.append( sw_cell + '</td>' );
			$tr.append( '<td><input type="text" class="ecos-ci ecos-ci-name" data-i="' + i + '" data-k="name" value="' + esc( c.name ) + '" placeholder="' + esc( L.choice_name || 'Choice name' ) + '"></td>' );
			$tr.append( '<td class="ecos-col-sku"><input type="text" class="ecos-ci ecos-ci-sku" data-i="' + i + '" data-k="sku" value="' + esc( c.sku ) + '" placeholder="' + esc( c.name ? '-' + String( c.name ).toUpperCase().replace( /[^A-Z0-9]/g, '' ).slice( 0, 4 ) : '-SKU' ) + '"></td>' );
			$tr.append( '<td class="ecos-col-price">' + adj_html( i, 'price' ) + '</td>' );
			$tr.append( '<td class="ecos-col-weight">' + adj_html( i, 'weight' ) + '</td>' );
			$tr.append( '<td class="ecos-col-def"><span class="ecos-rad' + ( c.initially_selected ? ' is-on' : '' ) + '" data-i="' + i + '" role="radio" aria-checked="' + ( c.initially_selected ? 'true' : 'false' ) + '" title="Initially selected"></span></td>' );
			$tr.append( '<td class="ecos-col-menu"><div class="ecv2-row-menu-wrap"><button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_toggle_row_menu(this);">&#8943;</button><div class="ecv2-row-menu"><a href="#" class="ecv2-row-menu-item ecos-act" data-act="advanced" data-i="' + i + '"><span class="dashicons dashicons-admin-settings"></span> Advanced…</a><a href="#" class="ecv2-row-menu-item ecos-act" data-act="dup" data-i="' + i + '"><span class="dashicons dashicons-admin-page"></span> Duplicate</a><a href="#" class="ecv2-row-menu-item ecv2-row-menu-item-danger ecos-act" data-act="remove" data-i="' + i + '"><span class="dashicons dashicons-trash"></span> Remove choice</a></div></div></td>' );
			$tb.append( $tr );
			if ( open_drawer === i ) { $tb.append( drawer_html( i ) ); }
		} );
		if ( ! choices.length ) {
			$tb.append( '<tr class="ecos-empty-row"><td colspan="8">No choices yet. Add one below or paste a list.</td></tr>' );
		}
		var defaults = $.grep( choices, function( c ) { return c.initially_selected; } ).length;
		$( '#ecos_count_hint' ).text( choices.length + ' ' + ( choices.length === 1 ? 'choice' : 'choices' ) + ' · ' + ( defaults ? defaults + ' default' : 'no default' ) );
		$( '#ecos_rail_count' ).text( choices.length );
		$( '#ecos_h_count' ).text( choices.length + ' ' + ( choices.length === 1 ? 'choice' : 'choices' ) );
		render_preview();
		update_url_var_desc();
		apply_lock();
	}

	function drawer_html( i ) {
		var c = choices[ i ], pro = D.is_pro;
		var html = '<tr class="ecos-drawer"><td colspan="8"><div class="ecdv2-grid">';
		if ( ! is_listed() ) {
			html += '<div class="ecdv2-field"><label class="ecdv2-label">Default value <span class="ecdv2-label-hint">pre-filled for the shopper</span></label><input type="text" class="ecv2-input ecos-ci" data-i="' + i + '" data-k="initial_value" value="' + esc( c.initial_value ) + '"></div>';
		}
		html += '<div class="ecdv2-field"><label class="ecdv2-label">Custom price label <span class="ecdv2-label-hint">replaces +' + fmt( 1 ).replace( /1[.,]?0*$/, 'X.XX' ) + '</span>' + ( pro ? '' : ' <span class="ecv2-chip ecv2-chip-blue">' + esc( ( window.wp_easycart_edition && window.wp_easycart_edition.badge_pro ) || 'Pro/Premium' ) + '</span>' ) + '</label><div class="ecos-inline"><label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecos-ci" data-i="' + i + '" data-k="custom_label_on"' + ( c.custom_label_on ? ' checked' : '' ) + ( pro ? '' : ' disabled' ) + '><span class="ecv2-toggle-slider"></span></label><input type="text" class="ecv2-input ecos-ci" data-i="' + i + '" data-k="custom_label" value="' + esc( c.custom_label ) + '" placeholder="e.g. Free"' + ( pro ? '' : ' disabled' ) + '></div></div>';
		html += '<div class="ecdv2-field"><label class="ecdv2-label">Behavior when chosen</label><label class="ecos-toggle-row"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecos-ci" data-i="' + i + '" data-k="allow_download"' + ( c.allow_download ? ' checked' : '' ) + '><span class="ecv2-toggle-slider"></span></span><span class="ecos-toggle-t">Allow downloads</span></label><label class="ecos-toggle-row"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecos-ci" data-i="' + i + '" data-k="disallow_shipping"' + ( c.disallow_shipping ? ' checked' : '' ) + '><span class="ecv2-toggle-slider"></span></span><span class="ecos-toggle-t">Disallow shipping</span></label></div>';
		html += '<div class="ecdv2-field"><label class="ecdv2-label">Download override' + ( pro ? '' : ' <span class="ecv2-chip ecv2-chip-blue">' + esc( ( window.wp_easycart_edition && window.wp_easycart_edition.badge_pro ) || 'Pro/Premium' ) + '</span>' ) + '</label><input type="text" class="ecv2-input ecos-ci" data-i="' + i + '" data-k="download_override" value="' + esc( c.download_override ) + '" placeholder="File name in products/downloads, or s3:key for Amazon S3"' + ( pro ? '' : ' disabled' ) + '></div>';
		html += '<div class="ecdv2-field"><label class="ecdv2-label">Additional download' + ( pro ? '' : ' <span class="ecv2-chip ecv2-chip-blue">' + esc( ( window.wp_easycart_edition && window.wp_easycart_edition.badge_pro ) || 'Pro/Premium' ) + '</span>' ) + '</label><input type="text" class="ecv2-input ecos-ci" data-i="' + i + '" data-k="download_addition" value="' + esc( c.download_addition ) + '" placeholder="Extra file delivered with this choice (file name or s3:key)"' + ( pro ? '' : ' disabled' ) + '></div>';
		html += '</div><div class="ecos-drawer-foot"><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecos-act" data-act="advanced" data-i="' + i + '">Close</button></div></td></tr>';
		return html;
	}

	/* Field edits */
	$root.on( 'input change', '.ecos-ci, .ecos-adj-val, .ecos-adj-type', function() {
		var i = parseInt( $( this ).data( 'i' ), 10 ), k = $( this ).data( 'k' );
		if ( ! choices[ i ] ) { return; }
		var v = this.type === 'checkbox' ? this.checked : this.value;
		if ( k === 'price' || k === 'weight' ) { v = num( v ); }
		if ( k === 'sku' ) { v = String( v ).replace( /[^A-Za-z0-9\-_]/g, '' ); if ( v !== this.value ) { this.value = v; } }
		choices[ i ][ k ] = v;
		mark_dirty();
		if ( k === 'name' || k === 'price' || k === 'price_type' || k === 'custom_label' || k === 'custom_label_on' ) { render_preview(); }
		if ( k === 'name' ) { $( '.ecos-ci-sku[data-i="' + i + '"]' ).attr( 'placeholder', v ? '-' + String( v ).toUpperCase().replace( /[^A-Z0-9]/g, '' ).slice( 0, 4 ) : '-SKU' ); update_url_var_desc(); }
	} );
	$root.on( 'keydown', '.ecos-ci-name', function( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); var i = parseInt( $( this ).data( 'i' ), 10 ); if ( i === choices.length - 1 ) { add_choice(); } else { $( '.ecos-ci-name[data-i="' + ( i + 1 ) + '"]' ).trigger( 'focus' ); } }
	} );

	/* Default radio */
	$root.on( 'click', '.ecos-rad', function() {
		if ( is_locked() ) { return upsell(); }
		var i = parseInt( $( this ).data( 'i' ), 10 );
		$.each( choices, function( j, c ) { c.initially_selected = ( j === i ) ? ! c.initially_selected : false; } );
		mark_dirty(); render();
	} );

	/* ---------------- Swatch: color or image ---------------- */
	function swatch_colors( icon ) {
		icon = String( icon || '' ).trim();
		if ( ! icon || icon.charAt( 0 ) !== '#' ) { return null; }
		var parts = icon.split( ',' ).map( function( x ) { return x.trim(); } );
		if ( parts.length > 2 ) { return null; }
		for ( var k = 0; k < parts.length; k++ ) { if ( ! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( parts[ k ] ) ) { return null; } }
		return parts;
	}
	function swatch_style( icon ) {
		var cols = swatch_colors( icon );
		if ( cols ) { return cols.length === 2 ? 'background:linear-gradient(135deg,' + cols[0] + ' 50%,' + cols[1] + ' 50%)' : 'background:' + cols[0]; }
		return icon ? 'background-image:url(' + esc( icon ) + ')' : '';
	}
	function pick_image( i ) {
		if ( ! window.wp || ! wp.media ) { ecv2_toast( 'The media library is not available on this page.', 'error' ); return; }
		var frame = wp.media( { title: L.pick_photo || 'Choose a swatch image', button: { text: L.use_photo || 'Use this image' }, multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function() {
			var a = frame.state().get( 'selection' ).first().toJSON();
			choices[ i ].icon = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
			mark_dirty(); render();
		} );
		frame.open();
	}
	function open_swatch_popover( i, $anchor ) {
		$( '.ecos-swpop' ).remove();
		var cols = swatch_colors( choices[ i ].icon ) || [], c1 = cols[0] || '#3b82f6', c2 = cols[1] || '', two = cols.length === 2;
		var $p = $( '<div class="ecos-swpop" role="dialog">' +
			'<div class="ecos-swpop-tabs"><button type="button" class="is-on" data-tab="color">Color</button><button type="button" data-tab="image">Image' + ( D.is_pro ? '' : ' <span class="dashicons dashicons-lock"></span>' ) + '</button></div>' +
			'<div class="ecos-swpop-body" data-tab="color">' +
				'<div class="ecos-swpop-row"><input type="color" class="ecos-swpop-c1" value="' + esc( c1 ) + '"><input type="text" class="ecv2-input ecos-swpop-h1" value="' + esc( c1 ) + '" maxlength="7" spellcheck="false"></div>' +
				'<label class="ecos-swpop-two"><input type="checkbox" class="ecos-swpop-toggle2"' + ( two ? ' checked' : '' ) + '> Two-tone</label>' +
				'<div class="ecos-swpop-row ecos-swpop-row2"' + ( two ? '' : ' style="display:none"' ) + '><input type="color" class="ecos-swpop-c2" value="' + esc( c2 || '#ffffff' ) + '"><input type="text" class="ecv2-input ecos-swpop-h2" value="' + esc( c2 || '#ffffff' ) + '" maxlength="7" spellcheck="false"></div>' +
				'<div class="ecos-swpop-presets"></div>' +
			'</div>' +
			'<div class="ecos-swpop-body" data-tab="image" style="display:none">' +
				( D.is_pro ? '<p class="ecos-hint">Use a photo or pattern instead of a flat color.</p><button type="button" class="ecv2-btn ecv2-btn-sm ecos-swpop-pick">Choose image…</button>' : '<p class="ecos-hint"><span class="ecv2-chip ecv2-chip-blue">' + esc( ( window.wp_easycart_edition && window.wp_easycart_edition.badge_pro ) || 'Pro/Premium' ) + '</span> ' + esc( L.pro_swatch || 'Image swatches are included with Pro and Premium licenses.' ) + ' Color swatches are included with every store.</p>' ) +
			'</div>' +
			'<div class="ecos-swpop-foot"><span class="ecos-swpop-prev"></span><span class="ecos-grow"></span><button type="button" class="ecv2-btn ecv2-btn-sm ecos-swpop-cancel">Cancel</button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary ecos-swpop-ok">Apply</button></div>' +
		'</div>' );
		var presets = [ '#000000', '#ffffff', '#6b7280', '#ef4444', '#f97316', '#eab308', '#22c55e', '#0ea5e9', '#3b82f6', '#8b5cf6', '#ec4899', '#78350f' ];
		$.each( presets, function( k, hex ) { $p.find( '.ecos-swpop-presets' ).append( '<button type="button" class="ecos-swpop-preset" data-hex="' + hex + '" style="background:' + hex + '" title="' + hex + '"></button>' ); } );
		$( 'body' ).append( $p );
		var off = $anchor.offset(); $p.css( { top: off.top + $anchor.outerHeight() + 6, left: Math.max( 8, Math.min( off.left, $( window ).width() - $p.outerWidth() - 8 ) ) } );
		function cur() { var a = $p.find( '.ecos-swpop-h1' ).val().trim(), b = $p.find( '.ecos-swpop-toggle2' ).is( ':checked' ) ? $p.find( '.ecos-swpop-h2' ).val().trim() : ''; return b ? a + ',' + b : a; }
		function refresh() { var v = cur(); $p.find( '.ecos-swpop-prev' ).attr( 'style', swatch_style( v ) ).toggleClass( 'is-bad', ! swatch_colors( v ) ); $p.find( '.ecos-swpop-ok' ).prop( 'disabled', ! swatch_colors( v ) ); }
		$p.on( 'input', '.ecos-swpop-c1', function() { $p.find( '.ecos-swpop-h1' ).val( this.value ); refresh(); } );
		$p.on( 'input', '.ecos-swpop-c2', function() { $p.find( '.ecos-swpop-h2' ).val( this.value ); refresh(); } );
		$p.on( 'input', '.ecos-swpop-h1', function() { if ( /^#[0-9a-f]{6}$/i.test( this.value ) ) { $p.find( '.ecos-swpop-c1' ).val( this.value ); } refresh(); } );
		$p.on( 'input', '.ecos-swpop-h2', function() { if ( /^#[0-9a-f]{6}$/i.test( this.value ) ) { $p.find( '.ecos-swpop-c2' ).val( this.value ); } refresh(); } );
		$p.on( 'change', '.ecos-swpop-toggle2', function() { $p.find( '.ecos-swpop-row2' ).toggle( this.checked ); refresh(); } );
		$p.on( 'click', '.ecos-swpop-preset', function() { var hex = $( this ).data( 'hex' ); $p.find( '.ecos-swpop-c1' ).val( hex ); $p.find( '.ecos-swpop-h1' ).val( hex ); refresh(); } );
		$p.on( 'click', '.ecos-swpop-tabs button', function() { var t = $( this ).data( 'tab' ); $p.find( '.ecos-swpop-tabs button' ).removeClass( 'is-on' ); $( this ).addClass( 'is-on' ); $p.find( '.ecos-swpop-body' ).hide().filter( '[data-tab="' + t + '"]' ).show(); $p.find( '.ecos-swpop-ok' ).toggle( t === 'color' ); } );
		$p.on( 'click', '.ecos-swpop-pick', function() { $p.remove(); pick_image( i ); } );
		$p.on( 'click', '.ecos-swpop-cancel', function() { $p.remove(); } );
		$p.on( 'click', '.ecos-swpop-ok', function() { var v = cur(); if ( ! swatch_colors( v ) ) { return; } choices[ i ].icon = swatch_colors( v ).join( ',' ).toLowerCase(); $p.remove(); mark_dirty(); render(); } );
		$( document ).off( 'mousedown.ecosswpop' ).on( 'mousedown.ecosswpop', function( e ) {
			if ( ! $( e.target ).closest( '.ecos-swpop, .media-modal' ).length ) { $p.remove(); $( document ).off( 'mousedown.ecosswpop' ); }
		} );
		refresh();
	}
	$root.on( 'click', '.ecos-sw', function() { if ( is_locked() ) { return upsell(); } open_swatch_popover( parseInt( $( this ).data( 'i' ), 10 ), $( this ) ); } );
	$root.on( 'click', '.ecos-sw-rm', function() { if ( is_locked() ) { return upsell(); } var i = parseInt( $( this ).data( 'i' ), 10 ); choices[ i ].icon = ''; mark_dirty(); render(); } );

	/* Row actions */
	$root.on( 'click', '.ecos-act', function( e ) {
		e.preventDefault();
		var i = parseInt( $( this ).data( 'i' ), 10 ), act = $( this ).data( 'act' );
		$( '.ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open ecv2-menu-fixed' );
		if ( act === 'advanced' ) { open_drawer = ( open_drawer === i ) ? null : i; render(); return; }
		if ( is_locked() ) { upsell(); return; }
		if ( act === 'dup' ) {
			var c = $.extend( {}, choices[ i ], { id: 0, tmp: 't' + ( tmp_seq++ ), name: choices[ i ].name + ' (copy)', initially_selected: false } );
			choices.splice( i + 1, 0, c ); open_drawer = null; mark_dirty(); render();
			$( '.ecos-ci-name[data-i="' + ( i + 1 ) + '"]' ).trigger( 'focus' ).select(); return;
		}
		if ( act === 'remove' ) { remove_choice( i ); }
	} );

	function remove_choice( i ) {
		var c = choices[ i ];
		function do_remove() {
			if ( c.id ) { removed.push( c.id ); }
			choices.splice( i, 1 ); open_drawer = null; mark_dirty(); render();
			var $t = $( '<div class="ecv2-toast ecv2-toast-success"><span class="dashicons dashicons-yes"></span> <span></span> <a href="#">' + esc( L.undo || 'Undo' ) + '</a></div>' );
			$t.find( 'span' ).last().text( 'Removed “' + c.name + '” — save to apply.' );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); choices.splice( i, 0, c ); if ( c.id ) { removed = $.grep( removed, function( r ) { return r !== c.id; } ); } render(); $t.remove(); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 6000 );
		}
		if ( ! c.id || ! is_basic() ) { do_remove(); return; }
		$.post( AJAX_URL, { action: 'ecv2_option_choice_impact', nonce: D.nonce, ids: [ c.id ] }, function( r ) {
			var d = ( r && r.success ) ? r.data : { stock_rows: 0, products: 0 };
			if ( ! d.stock_rows ) { do_remove(); return; }
			ecv2_show_confirm( L.remove_title || 'Remove choice?', ( L.remove_impact || '' ).replace( '%1$s', c.name ).replace( '%2$d', d.stock_rows ).replace( '%3$d', d.products ) ).then( function( ok ) { if ( ok ) { do_remove(); } } );
		}, 'json' );
	}

	function add_choice() {
		if ( is_locked() ) { upsell(); return; }
		choices.push( blank() ); mark_dirty(); render();
		$( '.ecos-ci-name' ).last().trigger( 'focus' );
	}

	/* Drag reorder */
	var drag_i = null;
	$root.on( 'dragstart', '.ecos-row', function( e ) { if ( is_locked() ) { e.preventDefault(); return; } drag_i = parseInt( $( this ).data( 'i' ), 10 ); $( this ).addClass( 'is-dragging' ); e.originalEvent.dataTransfer.effectAllowed = 'move'; } );
	$root.on( 'dragover', '.ecos-row', function( e ) { e.preventDefault(); $( '.ecos-row' ).removeClass( 'drop-before drop-after' ); var r = this.getBoundingClientRect(); $( this ).addClass( e.originalEvent.clientY - r.top < r.height / 2 ? 'drop-before' : 'drop-after' ); } );
	$root.on( 'dragend', '.ecos-row', function() { $( '.ecos-row' ).removeClass( 'is-dragging drop-before drop-after' ); drag_i = null; } );
	$root.on( 'drop', '.ecos-row', function( e ) {
		e.preventDefault();
		if ( drag_i === null ) { return; }
		var to = parseInt( $( this ).data( 'i' ), 10 ), before = $( this ).hasClass( 'drop-before' );
		var item = choices.splice( drag_i, 1 )[0];
		if ( drag_i < to ) { to--; }
		choices.splice( before ? to : to + 1, 0, item );
		open_drawer = null; drag_i = null; mark_dirty(); render();
	} );

	/* Paste list */
	window.ecos = window.ecos || {};
	ecos.add_choice = add_choice;
	ecos.paste_open = function() { if ( is_locked() ) { upsell(); return; } $( '#ecos_paste_box' ).show(); $( '#ecos_paste_text' ).trigger( 'focus' ); };
	ecos.paste_close = function() { $( '#ecos_paste_box' ).hide(); };
	ecos.paste_apply = function() {
		if ( is_locked() ) { upsell(); return; }
		var lines = $( '#ecos_paste_text' ).val().split( /\r?\n/ ), added = 0;
		$.each( lines, function( i, line ) {
			line = $.trim( line ); if ( ! line ) { return; }
			var parts = line.split( /\s*[,\t]\s*/ );
			var c = blank(); c.name = parts[0];
			if ( parts[1] !== undefined && parts[1] !== '' ) { c.price = num( parts[1] ); }
			if ( parts[2] !== undefined ) { c.sku = String( parts[2] ).replace( /[^A-Za-z0-9\-_]/g, '' ); }
			choices.push( c ); added++;
		} );
		$( '#ecos_paste_text' ).val( '' ); ecos.paste_close();
		if ( added ) { mark_dirty(); render(); ecv2_toast( added + ' choices added — save to apply.', 'success' ); }
	};

	/* ------------------------------------------------------------------ */
	/* Storefront preview                                                  */
	/* ------------------------------------------------------------------ */

	function price_tag( c ) {
		if ( c.custom_label_on && c.custom_label && D.is_pro ) { return c.custom_label; }
		var v = num( c.price ); if ( ! v ) { return ''; }
		switch ( adv_pricing() ? c.price_type : 'add' ) {
			case 'override': return fmt( v );
			case 'multiply': return '×' + v;
			case 'per_char': return '+' + fmt( v ) + '/char';
			case 'onetime': return ( v < 0 ? '-' : '+' ) + fmt( v ) + ' once';
			default: return ( v < 0 ? '-' : '+' ) + fmt( v );
		}
	}

	function render_preview() {
		var $b = $( '#ecos_pv_body' ).empty();
		var def = $.grep( choices, function( c ) { return c.initially_selected; } )[0] || choices[0] || null;
		if ( ! is_listed() ) {
			var ph = TEXTUAL[ type ] ? ( $.trim( $( '#ecos_meta_placeholder' ).val() || '' ) || 'Type here…' ) : ( NUMERIC[ type ] ? '0' : ( type === 'date' ? 'mm/dd/yyyy' : ( type === 'file' ? 'Choose file…' : '' ) ) );
			var v = def ? def.initial_value : '';
			if ( TEXTUAL[ type ] ) { v = text_rules_preview( v ); }
			$b.append( type === 'textarea' ? '<textarea class="ecos-pv-input" rows="3" readonly placeholder="' + esc( ph ) + '">' + esc( v ) + '</textarea>' : '<input class="ecos-pv-input" readonly placeholder="' + esc( ph ) + '" value="' + esc( v ) + '">' );
			if ( def && price_tag( def ) ) { $b.append( '<div class="ecos-pv-sub">' + esc( price_tag( def ) ) + '</div>' ); }
			if ( type === 'file' ) { $b.append( '<div class="ecos-pv-sub">' + esc( ( L.ft_accepted || 'Accepted file types: %s' ).replace( '%s', file_types_label() ) ) + '</div>' ); }
			return;
		}
		if ( ! choices.length ) { $b.append( '<div class="ecos-pv-empty">Add choices to see the preview.</div>' ); return; }
		if ( is_swatch() ) {
			var size = parseInt( $( '#ecos_meta_swatch_size' ).val(), 10 ) || 30;
			var $sw = $( '<div class="ecos-pv-sw"></div>' );
			$.each( choices, function( i, c ) {
				var $s = $( '<span class="ecos-pv-s' + ( def === c ? ' is-on' : '' ) + '" title="' + esc( c.name ) + '"></span>' ).css( { width: size, height: size } );
				if ( c.icon ) { $s.attr( 'style', $s.attr( 'style' ) + ';' + swatch_style( c.icon ) ); } else { $s.addClass( 'is-missing' ).text( String( c.name ).charAt( 0 ) ); }
				$sw.append( $s );
			} );
			$b.append( $sw ).append( '<div class="ecos-pv-sub">Selected: <b>' + esc( def ? def.name : '' ) + '</b>' + ( def && price_tag( def ) ? ' · ' + esc( price_tag( def ) ) : '' ) + '</div>' );
			return;
		}
		if ( type === 'radio' || type === 'checkbox' ) {
			var $l = $( '<div class="ecos-pv-radio' + ( type === 'checkbox' ? ' is-check' : '' ) + '"></div>' );
			$.each( choices, function( i, c ) { $l.append( '<div><span class="ecos-rad' + ( c.initially_selected ? ' is-on' : '' ) + '"></span>' + esc( c.name || '…' ) + ( price_tag( c ) ? ' <span>' + esc( price_tag( c ) ) + '</span>' : '' ) + '</div>' ); } );
			$b.append( $l ); return;
		}
		if ( type === 'grid' ) {
			var $g = $( '<div class="ecos-pv-grid"></div>' );
			$.each( choices, function( i, c ) { $g.append( '<div><span>' + esc( c.name || '…' ) + ( price_tag( c ) ? ' <small>' + esc( price_tag( c ) ) + '</small>' : '' ) + '</span><input value="0" readonly></div>' ); } );
			$b.append( $g ); return;
		}
		$b.append( '<div class="ecos-pv-dd"><span>' + esc( def ? def.name : '' ) + ( def && price_tag( def ) ? ' (' + esc( price_tag( def ) ) + ')' : '' ) + '</span><span>▾</span></div>' );
		var $list = $( '<div class="ecos-pv-list"></div>' );
		$.each( choices, function( i, c ) { $list.append( '<div>' + esc( c.name || '…' ) + '<span>' + esc( price_tag( c ) ) + '</span></div>' ); } );
		$b.append( $list );
	}
	$( '#ecos_meta_swatch_size' ).on( 'change', render_preview );

	/* Input rules preview ( mirrors wp_easycart_text_input_rules::apply(); the storefront uses ec-text-input-rules.js ). */
	function text_rules_preview( v ) {
		v = String( v == null ? '' : v );
		var c = $( '#ecos_meta_text_case' ).val(), a = $( '#ecos_meta_text_allowed' ).val(), max = parseInt( $( '#ecos_meta_max_length' ).val(), 10 );
		if ( c === 'upper' ) { v = v.toUpperCase(); } else if ( c === 'lower' ) { v = v.toLowerCase(); } else if ( c === 'title' ) { v = v.replace( /(^|\s)(\S)/g, function( m, s, ch ) { return s + ch.toUpperCase(); } ); }
		var re = null;
		try {
			if ( a === 'letters' ) { re = new RegExp( '[^\\p{L}\\p{M}]', 'gu' ); } else if ( a === 'letters_space' ) { re = new RegExp( '[^\\p{L}\\p{M} \\r\\n]', 'gu' ); } else if ( a === 'alnum' ) { re = new RegExp( '[^\\p{L}\\p{M}0-9]', 'gu' ); } else if ( a === 'alnum_space' ) { re = new RegExp( '[^\\p{L}\\p{M}0-9 \\r\\n]', 'gu' ); }
		} catch ( e ) {
			var lat = 'A-Za-z' + String.fromCharCode( 0xC0 ) + '-' + String.fromCharCode( 0x24F );
			re = a === 'letters' ? new RegExp( '[^' + lat + ']', 'g' ) : a === 'letters_space' ? new RegExp( '[^' + lat + ' \r\n]', 'g' ) : ( a === 'alnum' ? new RegExp( '[^' + lat + '0-9]', 'g' ) : ( a === 'alnum_space' ? new RegExp( '[^' + lat + '0-9 \r\n]', 'g' ) : null ) );
		}
		if ( a === 'numbers' ) { re = /[^0-9]/g; }
		if ( re ) { v = v.replace( re, '' ); }
		if ( max > 0 && v.length > max ) { v = v.slice( 0, max ); }
		return v;
	}
	$( '#ecos_meta_text_case, #ecos_meta_text_allowed' ).on( 'change', render_preview );
	$( '#ecos_meta_placeholder, #ecos_meta_max_length' ).on( 'input', render_preview );

	/* ------------------------------------------------------------------ */
	/* File upload types ( 6.0.0 ): none ticked = the store default list   */
	/* ------------------------------------------------------------------ */

	function file_types() {
		return $root.find( '.ecos-ft-cb:checked' ).map( function() { return this.value; } ).get();
	}
	function file_types_label() {
		var list = file_types();
		return list.length ? list.join( ', ' ).toUpperCase() : ( D.file_default || '' );
	}
	function update_file_types() {
		var list = file_types(), custom = list.length > 0;
		$( '#ecos_ft_state' ).toggleClass( 'is-default', ! custom )
			.find( '.dashicons' ).attr( 'class', 'dashicons dashicons-' + ( custom ? 'yes' : 'info-outline' ) );
		$( '#ecos_ft_state_text' ).text( ( custom ? ( L.ft_custom || 'Shoppers can upload: %s' ) : ( L.ft_default || 'Default types: %s' ) ).replace( '%s', file_types_label() ) );
		$( '#ecos_ft_clear' ).toggle( custom );
		$root.find( '.ecos-ft-group' ).each( function() {
			var $cbs = $( this ).find( '.ecos-ft-cb' ), all = $cbs.length && $cbs.filter( ':checked' ).length === $cbs.length;
			$( this ).find( '.ecos-ft-all' ).text( all ? ( L.ft_clear_all || 'Clear' ) : ( L.ft_select_all || 'Select all' ) );
		} );		if ( type === 'file' ) { render_preview(); }
	}
	$root.on( 'change', '.ecos-ft-cb', update_file_types );
	$root.on( 'click', '.ecos-ft-all', function() {
		if ( is_locked() ) { return upsell(); }
		var $cbs = $( this ).closest( '.ecos-ft-group' ).find( '.ecos-ft-cb' ), all = $cbs.filter( ':checked' ).length === $cbs.length;
		$cbs.prop( 'checked', ! all );
		mark_dirty(); update_file_types();
	} );
	$( '#ecos_ft_clear' ).on( 'click', function() {
		if ( is_locked() ) { return upsell(); }
		$root.find( '.ecos-ft-cb' ).prop( 'checked', false );
		mark_dirty(); update_file_types();
	} );

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	ecos.save = function() {
		if ( is_locked() ) { upsell(); return; }
		var name = $.trim( $( '#ecos_name' ).val() );
		if ( ! name ) { $( '#ecos_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); ecv2_toast( L.name_required || 'Give the option set a name.', 'error' ); return; }
		$( '#ecos_name' ).removeClass( 'is-invalid' );
		if ( is_listed() && ! $.grep( choices, function( c ) { return $.trim( c.name ) !== ''; } ).length ) { ecv2_toast( L.min_choices || 'Add at least one choice.', 'error' ); return; }
		var set = {
			name: name, label: $.trim( $( '#ecos_label' ).val() ), type: type,
			required: $( '#ecos_required' ).is( ':checked' ) ? 1 : 0, error_text: $( '#ecos_error' ).val(),
			meta: { min: $( '#ecos_meta_min' ).val(), max: $( '#ecos_meta_max' ).val(), step: $( '#ecos_meta_step' ).val(), min_length: $( '#ecos_meta_min_length' ).val(), max_length: $( '#ecos_meta_max_length' ).val(), url_var: $( '#ecos_meta_url_var' ).val(), swatch_size: $( '#ecos_meta_swatch_size' ).val(), text_case: $( '#ecos_meta_text_case' ).val(), text_allowed: $( '#ecos_meta_text_allowed' ).val(), placeholder: $( '#ecos_meta_placeholder' ).val(), file_types: file_types() }
		};
		var $btn = $( '#ecos_save' ).addClass( 'is-saving' ).prop( 'disabled', true );
		$btn.find( '.ecdv2-save-label' ).text( L.saving || 'Saving…' );
		$.ajax( { url: AJAX_URL, type: 'POST', dataType: 'json', data: { action: 'ecv2_option_save', nonce: D.nonce, option_id: D.option_id, set: JSON.stringify( set ), choices: JSON.stringify( choices ), removed: JSON.stringify( removed ) } } )
			.done( function( r ) {
				if ( ! r || ! r.success ) {
					if ( r && r.data && r.data.code === 'pro_required' ) { upsell(); }
					ecv2_toast( ( r && r.data && r.data.message ) || L.error, 'error' ); return;
				}
				choices = r.data.choices; removed = []; D.type = type; D.usage = r.data.usage; D.stock_rows = r.data.stock_rows;
				open_drawer = null; render();
				render_health( r.data.health );
				mark_saved();
				ecv2_toast( ( L.saved || 'Saved' ) + ( r.data.stock_deleted ? ' · ' + r.data.stock_deleted + ' variant stock rows removed' : '' ), 'success' );
			} )
			.fail( function() { ecv2_toast( L.error || 'Something went wrong.', 'error' ); } )
			.always( function() { $btn.removeClass( 'is-saving' ).prop( 'disabled', false ).find( '.ecdv2-save-label' ).text( 'Save' ); } );
	};

	function render_health( items ) {
		var $h = $( '#ecos_health .ecdv2-health-items' ).empty();
		$.each( items || [], function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } );
	}

	ecos.load_more_usage = function( btn ) {
		var $list = $( '#ecos_used_list' ), offset = $list.children().length;
		$( btn ).prop( 'disabled', true );
		$.post( AJAX_URL, { action: 'ecv2_option_usage', nonce: D.nonce, option_id: D.option_id, offset: offset }, function( r ) {
			if ( ! r || ! r.success ) { return; }
			$.each( r.data.items, function( i, u ) {
				var bits = [ u.advanced ? 'Advanced option' : 'Slot ' + u.slot ];
				if ( u.other_sets.length ) { bits.push( 'also uses ' + u.other_sets.join( ', ' ) ); }
				if ( u.variant_rows ) { bits.push( u.variant_rows + ' variants' + ( u.variant_oos ? ', ' + u.variant_oos + ' out of stock' : '' ) ); }
				$list.append( '<div class="ecos-u">' + ( u.image ? '<span class="ecv2-thumb"><img src="' + esc( u.image ) + '" alt=""></span>' : '<span class="ecv2-thumb"><span class="dashicons dashicons-format-image"></span></span>' ) + '<div class="ecos-u-main"><a class="ecv2-link-primary" href="' + esc( u.edit_url ) + '">' + esc( u.title ) + '</a><span class="ecv2-sub">' + esc( bits.join( ' · ' ) ) + '</span></div><span class="ecv2-chip ' + ( u.active ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '">' + ( u.active ? 'Active' : 'Inactive' ) + '</span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' + esc( u.edit_url ) + '">Open</a></div>' );
			} );
			var shown = $list.children().length;
			$( '.ecos-used-foot .ecos-hint' ).text( 'Showing ' + shown + ' of ' + D.usage );
			if ( shown >= D.usage ) { $( btn ).remove(); } else { $( btn ).prop( 'disabled', false ); }
		}, 'json' );
	};

	/* Rail highlighting */
	$( '.ecdv2-rail-link' ).on( 'click', function() { $( '.ecdv2-rail-link' ).removeClass( 'is-active' ); $( this ).addClass( 'is-active' ); } );

	/* Boot */
	apply_type( type );
	update_file_types();
	$( '#ecos_required' ).trigger( 'change' );
	dirty = false; $( '#ecos_dirty' ).removeClass( 'is-visible' );
} );
