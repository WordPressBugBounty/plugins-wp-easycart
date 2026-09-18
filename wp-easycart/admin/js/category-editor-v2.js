/**
 * WP EasyCart Admin — Category editor ( V2 ).
 *
 * Product membership is immediate ( add via combo / picker, remove via token × ).
 * All other fields save through eccat.save(). Depends on catalog-v2.js.
 */
jQuery( function( $ ) {
	'use strict';

	var $root = $( '#eccat' );
	if ( ! $root.length ) { return; }

	var D = JSON.parse( $( '#eccat_data' ).text() || '{}' );
	var L = D.i18n || {};
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var products = D.products || [];
	var total = D.product_total || 0;
	var dirty = false;
	var original_slug = D.slug || '';

	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function thumb( url, cls ) { return url ? '<span class="ecv2-thumb ' + ( cls || '' ) + '"><img src="' + esc( url ) + '" alt=""></span>' : '<span class="ecv2-thumb ' + ( cls || '' ) + '"><span class="dashicons dashicons-format-image"></span></span>'; }
	function slugify( s ) { return String( s ).toLowerCase().replace( /[^a-z0-9\s\-]/g, '' ).trim().replace( /[\s\-]+/g, '-' ); }

	/* ------------------------------------------------------------------ */
	/* Dirty + header binding                                              */
	/* ------------------------------------------------------------------ */

	function mark_dirty() { dirty = true; $( '#eccat_dirty' ).addClass( 'is-visible' ).removeClass( 'is-saved' ).text( 'Unsaved changes' ); }
	function mark_saved() { dirty = false; $( '#eccat_dirty' ).addClass( 'is-visible is-saved' ).text( L.saved || 'Saved' ); setTimeout( function() { if ( ! dirty ) { $( '#eccat_dirty' ).removeClass( 'is-visible' ); } }, 2500 ); }
	$root.on( 'input change', '[data-track]', mark_dirty );
	$( window ).on( 'beforeunload', function() { if ( dirty ) { return L.leave || 'You have unsaved changes.'; } } );

	$( '#eccat_name' ).on( 'input', function() { $( '#eccat_h_title' ).text( this.value || '…' ); } );
	$( '#eccat_active' ).on( 'change', function() { $( '#eccat_h_status' ).toggleClass( 'is-active', this.checked ).text( this.checked ? 'Active' : 'Hidden' ); } );
	$( '#eccat_excerpt' ).on( 'input', function() { $( '#eccat_excerpt_count' ).text( this.value.length ); } );

	/* Slug: manual only; offer redirect when it changes */
	$( '#eccat_slug' ).on( 'input', function() {
		var v = slugify( this.value );
		if ( v !== this.value ) { this.value = v; }
		var changed = v !== original_slug && original_slug !== '';
		$( '#eccat_redirect_row' ).toggle( changed && !! D.redirects );
		$( '#eccat_slug_desc' ).text( changed ? ( L.slug_changed || '' ) : ( L.slug_same || '' ) ).toggleClass( 'is-warn', changed );
	} );
	window.eccat = window.eccat || {};
	eccat.slug_from_name = function() { $( '#eccat_slug' ).val( slugify( $( '#eccat_name' ).val() ) ).trigger( 'input' ); mark_dirty(); };

	/* ------------------------------------------------------------------ */
	/* Images                                                              */
	/* ------------------------------------------------------------------ */

	function media( title, button, cb ) {
		if ( ! window.wp || ! wp.media ) { ecv2_toast( 'The media library is not available on this page.', 'error' ); return; }
		var frame = wp.media( { title: title, button: { text: button }, multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function() { cb( frame.state().get( 'selection' ).first().toJSON() ); } );
		frame.open();
	}
	eccat.pick_image = function() {
		media( L.pick_image || 'Choose a category image', L.use_image || 'Use this image', function( a ) {
			var url = a.url;
			$( '#eccat_image' ).val( url ); mark_dirty();
			$( '#eccat_img_thumb' ).addClass( 'has' ).html( '<img src="' + esc( ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : url ) + '" alt="">' );
			$( '#eccat_h_thumb' ).html( '<img src="' + esc( ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : url ) + '" alt="">' );
			$( '#eccat_img_meta' ).text( a.filename + ( a.width ? ' · ' + a.width + '×' + a.height : '' ) );
			$( '#eccat_img_remove' ).show(); $( '#eccat_img_slot .ecv2-btn' ).first().text( 'Replace' );
		} );
	};
	eccat.remove_image = function() {
		$( '#eccat_image' ).val( '' ); mark_dirty();
		$( '#eccat_img_thumb' ).removeClass( 'has' ).html( '<span class="dashicons dashicons-format-image"></span>' );
		$( '#eccat_h_thumb' ).html( '<span class="dashicons dashicons-format-image"></span>' );
		$( '#eccat_img_meta' ).text( 'Recommended: square, at least 600×600' );
		$( '#eccat_img_remove' ).hide(); $( '#eccat_img_slot .ecv2-btn' ).first().text( 'Upload' );
	};
	eccat.pick_banner = function() {
		media( L.pick_banner || 'Choose a banner image', L.use_image || 'Use this image', function( a ) {
			$( '#eccat_featured_image' ).val( a.id ); mark_dirty();
			$( '#eccat_banner_thumb' ).addClass( 'has' ).html( '<img src="' + esc( ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url ) + '" alt="">' );
			$( '#eccat_banner_remove' ).show(); $( '#eccat_banner_slot .ecv2-btn' ).first().text( 'Replace' );
		} );
	};
	eccat.remove_banner = function() {
		$( '#eccat_featured_image' ).val( '0' ); mark_dirty();
		$( '#eccat_banner_thumb' ).removeClass( 'has' ).html( '<span class="dashicons dashicons-format-image"></span>' );
		$( '#eccat_banner_remove' ).hide(); $( '#eccat_banner_slot .ecv2-btn' ).first().text( 'Upload' );
	};

	/* ------------------------------------------------------------------ */
	/* Products — tokens + combo ( immediate )                             */
	/* ------------------------------------------------------------------ */

	function render_tokens() {
		var $t = $( '#eccat_tokens' ), $in = $( '#eccat_search' );
		$t.children( '.eccat-token, .eccat-token-more' ).remove();
		var frag = '';
		$.each( products, function( i, p ) {
			frag += '<span class="eccat-token' + ( p.active ? '' : ' is-inactive' ) + ( p.smart ? ' is-smart' : '' ) + '" data-id="' + p.id + '" title="' + esc( p.sku || '' ) + ( p.smart ? ' · added by a rule' : '' ) + '">' + thumb( p.image, 'ecv2-thumb-xs' ) + '<a href="' + esc( p.edit_url ) + '">' + esc( p.title ) + '</a>' + ( p.smart ? '<span class="eccat-token-tag" title="Added by a smart rule">rule</span>' : '' ) + '<button type="button" class="eccat-token-x" data-id="' + p.id + '" title="' + ( p.smart ? 'Exclude from this category\'s rules' : 'Remove from category' ) + '">&times;</button></span>';
		} );
		if ( total > products.length ) { frag += '<span class="eccat-token-more">+' + ( total - products.length ) + ' more</span>'; }
		$in.before( frag );
		$( '#eccat_rail_count, #eccat_h_count' ).text( total + ( $( '#eccat_h_count' ).length ? '' : '' ) );
		$( '#eccat_h_count' ).text( total + ( total === 1 ? ' product' : ' products' ) );
		var mode = window.eccat_smart ? eccat_smart.mode() : 0;
		$( '#eccat_products_hint' ).text( total + ' in this category · ' + ( mode === 1 ? 'filled by rules' : ( mode === 2 ? 'rules + hand-picked · adds apply immediately' : 'changes apply immediately' ) ) );
		$( '#eccat_combo' ).toggleClass( 'is-readonly', mode === 1 );
		$( '#eccat_more' ).toggle( total > products.length );
		var inactive = $.grep( products, function( p ) { return ! p.active; } ).length;
		$( '#eccat_products_stats' ).text( ( total - inactive ) + ' active · ' + inactive + ' inactive' + ( total > products.length ? ' (of the ' + products.length + ' shown)' : '' ) );
	}

	function post( data, ok ) {
		return $.ajax( { url: AJAX_URL, type: 'POST', dataType: 'json', data: data } ).done( function( r ) { if ( r && r.success ) { ok( r.data || {} ); } else { ecv2_toast( ( r && r.data && r.data.message ) || L.error, 'error' ); } } ).fail( function() { ecv2_toast( L.error, 'error' ); } );
	}

	function add_product( p ) {
		post( { action: 'ecv2_category_add_products', wp_easycart_nonce: D.list_nonce, category_id: D.category_id, product_ids: [ p.id ] }, function( d ) {
			if ( d.added ) {
				products.unshift( { id: p.id, title: p.title, sku: p.sku, image: p.image, active: p.active, edit_url: p.edit_url || ( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' + p.id ) } );
				total++; render_tokens();
			}
			var $t = $( '<div class="ecv2-toast ecv2-toast-success"><span class="dashicons dashicons-yes"></span> <span></span> <a href="#">' + esc( L.undo || 'Undo' ) + '</a></div>' );
			$t.find( 'span' ).last().text( ( L.added || 'Added “%s”' ).replace( '%s', p.title ) );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); remove_product( p.id, true ); $t.remove(); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 6000 );
		} );
	}

	function remove_product( id, silent ) {
		var p = $.grep( products, function( x ) { return x.id === id; } )[0];
		post( { action: 'ecv2_category_remove_product', wp_easycart_nonce: D.list_nonce, category_id: D.category_id, product_id: id }, function( d ) {
			products = $.grep( products, function( x ) { return x.id !== id; } ); total = Math.max( 0, total - 1 ); render_tokens();
			if ( d && d.excluded && window.eccat_smart ) { eccat_smart.note_excluded( id ); if ( p ) { ecv2_toast( 'Excluded “' + p.title + '” — rules won\'t add it back.', 'success' ); } return; }
			if ( silent || ! p ) { return; }
			var $t = $( '<div class="ecv2-toast ecv2-toast-success"><span class="dashicons dashicons-yes"></span> <span></span> <a href="#">' + esc( L.undo || 'Undo' ) + '</a></div>' );
			$t.find( 'span' ).last().text( ( L.removed || 'Removed “%s”' ).replace( '%s', p.title ) );
			$t.find( 'a' ).on( 'click', function( e ) { e.preventDefault(); add_product( p ); $t.remove(); } );
			$( '#ecv2-toast-container' ).append( $t ); setTimeout( function() { $t.fadeOut( 300, function() { $( this ).remove(); } ); }, 6000 );
		} );
	}

	$root.on( 'click', '.eccat-token-x', function() { remove_product( parseInt( $( this ).data( 'id' ), 10 ) ); } );

	/* Combo search */
	var timer, last_q = null;
	$( '#eccat_search' ).on( 'input focus', function() {
		var q = $.trim( this.value );
		clearTimeout( timer );
		timer = setTimeout( function() { search( q ); }, 200 );
	} );
	$( document ).on( 'click', function( e ) { if ( ! $( e.target ).closest( '#eccat_combo' ).length ) { $( '#eccat_dd' ).hide(); } } );
	$( '#eccat_search' ).on( 'keydown', function( e ) {
		var $items = $( '#eccat_dd .eccat-dd-item' ), $cur = $items.filter( '.is-hover' );
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) { e.preventDefault(); var i = $items.index( $cur ); i = e.key === 'ArrowDown' ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 ); $items.removeClass( 'is-hover' ).eq( i ).addClass( 'is-hover' ); }
		if ( e.key === 'Enter' ) { e.preventDefault(); ( $cur.length ? $cur : $items.first() ).trigger( 'click' ); }
		if ( e.key === 'Escape' ) { $( '#eccat_dd' ).hide(); }
	} );

	function search( q ) {
		if ( q === last_q && $( '#eccat_dd' ).is( ':visible' ) ) { return; }
		last_q = q;
		$( '#eccat_dd' ).show().html( '<div class="eccat-dd-hd"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
		post( { action: 'ecv2_category_product_search', wp_easycart_nonce: D.list_nonce, category_id: D.category_id, q: q, scope: 'notin' }, function( d ) {
			var html = '<div class="eccat-dd-hd">' + ( d.items.length ? 'MATCHES' + ( d.total > d.items.length ? ' · ' + d.items.length + ' of ' + d.total : '' ) : esc( L.no_matches || 'No matching products.' ) ) + '</div>';
			$.each( d.items, function( i, p ) {
				html += '<a href="#" class="eccat-dd-item" data-i="' + i + '">' + thumb( p.image ) + '<span class="eccat-dd-title">' + esc( p.title ) + '</span><small>' + esc( p.sku || '' ) + ' · ' + ( p.cats ? esc( p.cats ) : 'no category' ) + ( p.active ? '' : ' · inactive' ) + '</small></a>';
			} );
			html += '<div class="eccat-dd-hd">OR</div><a href="#" class="eccat-dd-item eccat-dd-create" id="eccat_dd_browse"><span class="dashicons dashicons-search"></span><span class="eccat-dd-title">Browse all products…</span></a>';
			if ( window.wp_easycart_admin_open_slideout ) { html += '<a href="#" class="eccat-dd-item eccat-dd-create" id="eccat_dd_new"><span class="dashicons dashicons-plus-alt2"></span><span class="eccat-dd-title">' + esc( L.create_product || 'Create a new product in this category' ) + '</span></a>'; }
			$( '#eccat_dd' ).html( html ).data( 'items', d.items );
		} );
	}
	$root.on( 'click', '.eccat-dd-item', function( e ) {
		e.preventDefault();
		if ( this.id === 'eccat_dd_browse' ) { $( '#eccat_dd' ).hide(); ecv2_catalog.pick_products( D.category_id, on_picked ); return; }
		if ( this.id === 'eccat_dd_new' ) { $( '#eccat_dd' ).hide(); try { window.ecv2_new_product_category = D.category_id; wp_easycart_admin_open_slideout( 'new_product_box' ); } catch ( err ) {} return; }
		var items = $( '#eccat_dd' ).data( 'items' ) || [], p = items[ parseInt( $( this ).data( 'i' ), 10 ) ];
		if ( p ) { add_product( p ); }
		$( '#eccat_search' ).val( '' ); $( '#eccat_dd' ).hide(); last_q = null;
	} );

	function on_picked( ids, items ) {
		$.each( ids, function( i, id ) {
			var p = $.grep( items, function( x ) { return x.id === parseInt( id, 10 ); } )[0];
			if ( p && ! $.grep( products, function( x ) { return x.id === p.id; } ).length ) { products.unshift( { id: p.id, title: p.title, sku: p.sku, image: p.image, active: p.active, edit_url: 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' + p.id } ); total++; }
		} );
		render_tokens();
	}

	eccat.load_more = function( btn ) {
		$( btn ).prop( 'disabled', true );
		post( { action: 'ecv2_category_products', nonce: D.nonce, category_id: D.category_id, offset: products.length }, function( d ) {
			$.each( d.items, function( i, p ) { if ( ! $.grep( products, function( x ) { return x.id === p.id; } ).length ) { products.push( p ); } } );
			render_tokens(); $( btn ).prop( 'disabled', false );
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Subcategories — "Add existing category" ( immediate )              */
	/* ------------------------------------------------------------------ */

	var subtree = $.map( D.subtree_ids || [], function( v ) { return parseInt( v, 10 ); } );
	var children = D.children || [];

	function render_children() {
		var html = '';
		$.each( children, function( i, ch ) {
			html += '<div class="ecos-u">' + thumb( ch.image ) + '<div class="ecos-u-main"><a class="ecv2-link-primary" href="' + esc( ch.edit_url ) + '">' + esc( ch.name ) + '</a><span class="ecv2-sub">' + ch.products + ( ch.products === 1 ? ' product' : ' products' ) + '</span></div><span class="ecv2-chip ' + ( ch.active ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '">' + ( ch.active ? 'Active' : 'Hidden' ) + '</span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' + esc( ch.edit_url ) + '">Open</a></div>';
		} );
		$( '#eccat_subs_list' ).html( html );
		$( '#eccat_subs_empty' ).toggle( ! children.length );
		$( '#eccat_rail_subs' ).text( children.length );
		$( '#eccat_subs_hint' ).text( children.length ? ( children.length === 1 ? ( L.sub_one || '1 subcategory' ) : ( L.sub_count || '%d subcategories' ).replace( '%d', children.length ) ) : ( L.sub_none || 'None yet' ) );
	}

	eccat.focus_sub_search = function() { $( '#eccat_sub_search' ).trigger( 'focus' ); };

	var sub_timer, sub_seq = 0;
	$( '#eccat_sub_search' ).on( 'input', function() {
		var q = $.trim( this.value );
		clearTimeout( sub_timer );
		if ( ! q ) { $( '#eccat_sub_dd' ).hide(); return; }
		sub_timer = setTimeout( function() { search_subs( q ); }, 200 );
	} );
	$( document ).on( 'click', function( e ) { if ( ! $( e.target ).closest( '#eccat_sub_combo' ).length ) { $( '#eccat_sub_dd' ).hide(); } } );
	$( '#eccat_sub_search' ).on( 'keydown', function( e ) {
		var $items = $( '#eccat_sub_dd .eccat-dd-item' ), $cur = $items.filter( '.is-hover' );
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) { e.preventDefault(); var i = $items.index( $cur ); i = e.key === 'ArrowDown' ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 ); $items.removeClass( 'is-hover' ).eq( i ).addClass( 'is-hover' ); }
		if ( e.key === 'Enter' ) { e.preventDefault(); ( $cur.length ? $cur : $items.first() ).trigger( 'click' ); }
		if ( e.key === 'Escape' ) { $( '#eccat_sub_dd' ).hide(); }
	} );

	function search_subs( q ) {
		var my = ++sub_seq;
		$( '#eccat_sub_dd' ).show().html( '<div class="eccat-dd-hd"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
		$.post( AJAX_URL, { action: 'ecv2_category_search', search: q, wp_easycart_nonce: D.search_nonce || ( window.ecv2_catalog_vars && ecv2_catalog_vars.nonces && ecv2_catalog_vars.nonces.category_search ) || '' }, function( r ) {
			if ( my !== sub_seq ) { return; }
			var results = ( r && r.success && r.data && r.data.results ) ? r.data.results : [], items = [];
			$.each( results, function( i, c ) {
				var id = parseInt( c.id, 10 );
				if ( $.inArray( id, subtree ) !== -1 ) { return; }                                              /* itself or already inside its subtree */
				if ( $.grep( children, function( x ) { return x.id === id; } ).length ) { return; }              /* already a direct child */
				items.push( { id: id, name: c.name } );
			} );
			var html = '<div class="eccat-dd-hd">' + ( items.length ? 'MATCHES' : esc( L.no_categories || 'No matching categories.' ) ) + '</div>';
			$.each( items, function( i, c ) { html += '<a href="#" class="eccat-dd-item" data-i="' + i + '"><span class="dashicons dashicons-category"></span><span class="eccat-dd-title">' + esc( c.name ) + '</span></a>'; } );
			$( '#eccat_sub_dd' ).html( html ).data( 'items', items );
		}, 'json' ).fail( function() { if ( my === sub_seq ) { $( '#eccat_sub_dd' ).html( '<div class="eccat-dd-hd">' + esc( L.error ) + '</div>' ); } } );
	}

	$root.on( 'click', '#eccat_sub_dd .eccat-dd-item', function( e ) {
		e.preventDefault();
		var items = $( '#eccat_sub_dd' ).data( 'items' ) || [], c = items[ parseInt( $( this ).data( 'i' ), 10 ) ];
		$( '#eccat_sub_search' ).val( '' ); $( '#eccat_sub_dd' ).hide();
		if ( ! c ) { return; }
		post( { action: 'ecv2_category_set_parent', nonce: D.nonce, category_id: D.category_id, child_id: c.id }, function( d ) {
			children = d.children || []; subtree = $.map( d.subtree_ids || subtree, function( v ) { return parseInt( v, 10 ); } );
			render_children();
			if ( d.health ) { var $h = $( '#eccat_health .ecdv2-health-items' ).empty(); $.each( d.health, function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } ); }
			ecv2_toast( d.message || ( L.sub_moved || 'Moved “%s” under this category' ).replace( '%s', c.name ), 'success' );
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Yoast SEO card ( ecv2_catalog_save_yoast, after the main save )    */
	/* ------------------------------------------------------------------ */

	var $yoast = $( '#catv2-yoast' ), yoast_dirty = false;
	if ( $yoast.length ) {
		$yoast.on( 'input change', '[data-track]', function() { yoast_dirty = true; } );
		var yoast_defaults = { title: $( '#ecv2_yoast_preview' ).data( 'default-title' ) || '', desc: $( '#ecv2_yoast_preview' ).data( 'default-desc' ) || '' };
		var yoast_preview = function() {
			var t = $.trim( $( '#ecv2_yoast_title' ).val() ), d = $.trim( $( '#ecv2_yoast_metadesc' ).val() );
			if ( t.indexOf( '%%' ) === -1 ) { $( '#ecv2_yoast_preview_title' ).text( t || yoast_defaults.title ); }
			$( '#ecv2_yoast_preview_desc' ).text( d || yoast_defaults.desc );
			$yoast.find( '.ecdv2-char-count' ).each( function() { var $c = $( this ), n = ( $( '#' + $c.data( 'count-for' ) ).val() || '' ).length, max = parseInt( $c.data( 'count-max' ), 10 ) || 0; $c.text( n ? n + ' / ' + max : '' ).toggleClass( 'is-over', max > 0 && n > max ); } );
		};
		$yoast.on( 'input', '#ecv2_yoast_title, #ecv2_yoast_metadesc', yoast_preview ); yoast_preview();
	}
	function yoast_save() {
		if ( ! $yoast.length || ! yoast_dirty ) { return; }
		$.post( AJAX_URL, {
			action: 'ecv2_catalog_save_yoast', nonce: $yoast.data( 'yoast-nonce' ), entity: $yoast.data( 'yoast-type' ), id: $yoast.data( 'yoast-id' ), level: $yoast.data( 'yoast-level' ) || 0,
			yoast_title: $( '#ecv2_yoast_title' ).val(), yoast_metadesc: $( '#ecv2_yoast_metadesc' ).val(), yoast_focuskw: $( '#ecv2_yoast_focuskw' ).val(), yoast_canonical: $( '#ecv2_yoast_canonical' ).val(), yoast_noindex: $( '#ecv2_yoast_noindex' ).val()
		}, function( r ) {
			if ( r && r.success ) { yoast_dirty = false; ecv2_toast( L.yoast_saved || 'Yoast SEO saved', 'success' ); }
			else { ecv2_toast( ( r && r.data && r.data.message ) || L.error, 'error' ); }
		}, 'json' ).fail( function() { ecv2_toast( L.error, 'error' ); } );
	}

	/* ------------------------------------------------------------------ */
	/* Save                                                                */
	/* ------------------------------------------------------------------ */

	eccat.save = function() {
		var name = $.trim( $( '#eccat_name' ).val() );
		if ( ! name ) { $( '#eccat_name' ).addClass( 'is-invalid' ).trigger( 'focus' ); ecv2_toast( L.name_required || 'Give the category a name.', 'error' ); return; }
		$( '#eccat_name' ).removeClass( 'is-invalid' );
		var data = {
			name: name, parent_id: $( '#eccat_parent' ).val(), description: $( '#eccat_desc' ).val(),
			is_active: $( '#eccat_active' ).is( ':checked' ) ? 1 : 0, featured_category: $( '#eccat_featured' ).is( ':checked' ) ? 1 : 0,
			priority: $( '#eccat_priority' ).val(), image: $( '#eccat_image' ).val(), featured_image: $( '#eccat_featured_image' ).val(),
			slug: $( '#eccat_slug' ).val(), excerpt: $( '#eccat_excerpt' ).val(),
			redirect: $( '#eccat_redirect_row' ).is( ':visible' ) && $( '#eccat_redirect' ).is( ':checked' ) ? 1 : 0
		};
		if ( window.eccat_smart && eccat_smart.enabled() ) { data.smart_mode = eccat_smart.mode(); data.smart_rules = eccat_smart.rules(); }
		var $btn = $( '#eccat_save' ).addClass( 'is-saving' ).prop( 'disabled', true );
		$btn.find( '.ecdv2-save-label' ).text( L.saving || 'Saving…' );
		$.ajax( { url: AJAX_URL, type: 'POST', dataType: 'json', data: { action: 'ecv2_category_save', nonce: D.nonce, category_id: D.category_id, data: JSON.stringify( data ) } } )
			.done( function( r ) {
				if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || L.error, 'error' ); return; }
				var d = r.data;
				original_slug = d.slug; $( '#eccat_slug' ).val( d.slug ).trigger( 'input' );
				if ( d.slug_prefix ) { $( '#eccat_slug_pre' ).text( d.slug_prefix.replace( /^https?:\/\/[^\/]+/, '' ) ); }
				if ( d.permalink ) { $( '#eccat_view' ).attr( 'href', d.permalink ); $( '#eccat_h_url' ).text( d.permalink.replace( /^https?:\/\/[^\/]+/, '' ) ); }
				$( '#eccat_h_path' ).text( d.path && d.path.length ? d.path.join( ' › ' ) + ' › ' + name : 'Top level' );
				var $h = $( '#eccat_health .ecdv2-health-items' ).empty();
				$.each( d.health || [], function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } );
				if ( d.products ) { products = d.products; total = d.product_total || products.length; render_tokens(); }
				if ( window.eccat_smart ) { eccat_smart.after_save( d.smart ); }
				mark_saved();
				yoast_save();
				var smart_msg = d.smart ? ( ( d.smart.added ? ' · ' + d.smart.added + ' added by rules' : '' ) + ( d.smart.removed ? ' · ' + d.smart.removed + ' removed' : '' ) ) : '';
				ecv2_toast( ( L.saved || 'Saved' ) + ( d.redirect_added ? ' · redirect added from the old URL' : '' ) + smart_msg, 'success' );
			} )
			.fail( function() { ecv2_toast( L.error, 'error' ); } )
			.always( function() { $btn.removeClass( 'is-saving' ).prop( 'disabled', false ).find( '.ecdv2-save-label' ).text( 'Save' ); } );
	};

	$( '.ecdv2-rail-link' ).on( 'click', function() { $( '.ecdv2-rail-link' ).removeClass( 'is-active' ); $( this ).addClass( 'is-active' ); } );

	/* ================================================================== */
	/* Smart categories ( PRO ) — rule builder                             */
	/* ================================================================== */
	var S = D.smart || { available: false };
	window.eccat_smart = ( function() {
		var mode = S.mode || 0, rules = ( S.rules && S.rules.rules ) ? S.rules.rules.slice() : [], exclude = ( S.rules && S.rules.exclude ) ? S.rules.exclude.slice() : [], pv_timer = null;
		function enabled() { return !! ( S.available && S.is_pro ); }
		function type_of( f ) { return S.fields[ f ] ? S.fields[ f ].type : 'text'; }
		function first_op( f ) { var ops = S.ops[ type_of( f ) ] || {}; for ( var k in ops ) { return k; } return ''; }
		function default_value( f ) { var t = type_of( f ); if ( t === 'select' ) { var src = S.sources[ S.fields[ f ].source ] || []; return src.length ? src[0].value : ''; } if ( t === 'days' ) { return 14; } if ( t === 'number' ) { return 0; } return ''; }
		function opts_html( map, sel ) { var h = ''; $.each( map, function( k, label ) { h += '<option value="' + esc( k ) + '"' + ( String( k ) === String( sel ) ? ' selected' : '' ) + '>' + esc( label ) + '</option>'; } ); return h; }
		function src_html( f, sel ) { var h = ''; $.each( S.sources[ S.fields[ f ].source ] || [], function( i, o ) { h += '<option value="' + esc( o.value ) + '"' + ( String( o.value ) === String( sel ) ? ' selected' : '' ) + '>' + esc( o.label ) + '</option>'; } ); return h || '<option value="">—</option>'; }
		function value_html( i, r ) {
			var t = type_of( r.f );
			if ( t === 'select' ) { return '<select class="ecv2-select eccat-rule-v" data-i="' + i + '">' + src_html( r.f, r.v ) + '</select>'; }
			if ( t === 'bool' ) { return '<span class="ecos-hint"></span>'; }
			if ( t === 'days' ) { return '<div class="eccat-rule-days"><input type="number" min="1" class="ecv2-input eccat-rule-v" data-i="' + i + '" value="' + esc( r.v ) + '"><span>days</span></div>'; }
			if ( t === 'number' ) { return '<input type="number" step="any" class="ecv2-input eccat-rule-v" data-i="' + i + '" value="' + esc( r.v ) + '">'; }
			return '<input type="text" class="ecv2-input eccat-rule-v" data-i="' + i + '" value="' + esc( r.v ) + '" placeholder="…">';
		}
		function render() {
			var $l = $( '#eccat_rules_list' ).empty();
			$.each( rules, function( i, r ) {
				var fields_html = ''; $.each( S.fields, function( k, f ) { fields_html += '<option value="' + k + '"' + ( k === r.f ? ' selected' : '' ) + '>' + esc( f.label ) + '</option>'; } );
				$l.append( '<div class="eccat-rule" data-i="' + i + '"><select class="ecv2-select eccat-rule-f" data-i="' + i + '">' + fields_html + '</select><select class="ecv2-select eccat-rule-o" data-i="' + i + '">' + opts_html( S.ops[ type_of( r.f ) ] || {}, r.o ) + '</select><div class="eccat-rule-vwrap">' + value_html( i, r ) + '</div><button type="button" class="eccat-rule-x" data-i="' + i + '" title="Remove rule">×</button></div>' );
			} );
			if ( ! rules.length ) { $l.append( '<div class="ecos-hint" style="padding:6px 0">No rules yet — add one to start matching products.</div>' ); }
			$( '#eccat_exclude_hint' ).html( exclude.length ? exclude.length + ' excluded · <a href="#" id="eccat_exclude_clear">clear</a>' : '' );
			$( '#eccat_rules_only_warn' ).toggle( mode === 1 );
			schedule_preview();
		}
		function schedule_preview() { clearTimeout( pv_timer ); pv_timer = setTimeout( preview, 350 ); }
		function preview() {
			if ( ! enabled() || ! mode ) { return; }
			$( '#eccat_preview' ).addClass( 'is-loading' );
			$.post( AJAX_URL, { action: 'ecv2_category_rules_preview', nonce: D.nonce, category_id: D.category_id, rules: JSON.stringify( rules_obj() ) }, function( r ) {
				$( '#eccat_preview' ).removeClass( 'is-loading' );
				if ( ! r || ! r.success ) { $( '#eccat_preview_count' ).text( '—' ); return; }
				$( '#eccat_preview_count' ).text( r.data.empty_rules ? '—' : r.data.count );
				var $t = $( '#eccat_preview_thumbs' ).empty();
				$.each( r.data.sample || [], function( i, p ) { $t.append( $( '<span class="ecv2-thumb" title="' + esc( p.title ) + '">' + ( p.image ? '<img src="' + esc( p.image ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</span>' ) ); } );
				if ( r.data.count > ( r.data.sample || [] ).length ) { $t.append( '<span class="eccat-preview-more">+' + ( r.data.count - r.data.sample.length ) + '</span>' ); }
				if ( mode === 1 ) { var manual = $.grep( products, function( p ) { return ! p.smart; } ).length; $( '#eccat_rules_only_n' ).text( manual ? manual + ' hand-picked product' + ( manual === 1 ? '' : 's' ) + ' will be re-checked against the rules.' : '' ); }
			}, 'json' );
		}
		function rules_obj() { return { mode: $( '#eccat_rules_mode' ).val() || 'all', rules: rules, exclude: exclude }; }
		$root.on( 'change', '.eccat-rule-f', function() { var i = +$( this ).data( 'i' ); rules[ i ] = { f: this.value, o: first_op( this.value ), v: default_value( this.value ) }; mark_dirty(); render(); } );
		$root.on( 'change', '.eccat-rule-o', function() { rules[ +$( this ).data( 'i' ) ].o = this.value; mark_dirty(); schedule_preview(); } );
		$root.on( 'input change', '.eccat-rule-v', function() { rules[ +$( this ).data( 'i' ) ].v = this.value; mark_dirty(); schedule_preview(); } );
		$root.on( 'click', '.eccat-rule-x', function() { rules.splice( +$( this ).data( 'i' ), 1 ); mark_dirty(); render(); } );
		$root.on( 'change', '#eccat_rules_mode', function() { mark_dirty(); schedule_preview(); } );
		$root.on( 'click', '#eccat_exclude_clear', function( e ) { e.preventDefault(); exclude = []; mark_dirty(); render(); } );
		if ( S.available ) { render(); }
		return {
			enabled: enabled,
			mode: function() { return mode; },
			rules: rules_obj,
			set_mode: function( m ) {
				if ( ! S.available ) { return; }
				if ( ! S.is_pro ) { ecv2_toast( L.smart_locked || 'Smart mode for categories is included with Pro and Premium licenses.', 'info' ); if ( S.upgrade_url ) { window.open( S.upgrade_url, '_blank' ); } return; }
				mode = m; $( '#eccat_mode_row .ecv2-seg-btn' ).removeClass( 'is-on' ).filter( '[data-mode="' + m + '"]' ).addClass( 'is-on' );
				$( '#eccat_rules' ).toggle( m > 0 );
				if ( m > 0 && ! rules.length ) { rules.push( { f: 'added', o: 'within', v: 14 } ); }
				mark_dirty(); render(); render_tokens();
			},
			add_rule: function() { rules.push( { f: 'title', o: 'contains', v: '' } ); mark_dirty(); render(); setTimeout( function() { $( '#eccat_rules_list .eccat-rule:last .eccat-rule-v' ).trigger( 'focus' ); }, 50 ); },
			note_excluded: function( id ) { if ( $.inArray( id, exclude ) < 0 ) { exclude.push( id ); } render(); },
			after_save: function( res ) { if ( res ) { schedule_preview(); } }
		};
	} )();
	window.eccat.set_mode = window.eccat_smart.set_mode;
	window.eccat.add_rule = window.eccat_smart.add_rule;

	/* ================================================================== */
	/* SERP preview                                                        */
	/* ================================================================== */
	function serp() {
		var name = $.trim( $( '#eccat_name' ).val() ), ex = $.trim( $( '#eccat_excerpt' ).val() ), slug = $.trim( $( '#eccat_slug' ).val() );
		var site = $( '#eccat_serp_title' ).text().split( ' – ' ).pop();
		$( '#eccat_serp_title' ).text( ( name || 'Category' ) + ' – ' + site );
		$( '#eccat_serp_desc' ).text( ex || 'Google will pull text from the page — add a search excerpt above to control it.' ).toggleClass( 'is-placeholder', ! ex );
		var pre = $( '#eccat_slug_pre' ).text(); $( '#eccat_serp_path' ).text( pre + ( slug || '' ) + ( slug ? '/' : '' ) );
		var n = ex.length, $c = $( '#eccat_serp_count' );
		$c.text( n ? n + ' / 160' + ( n < 70 ? ' · room for about ' + ( 155 - n ) + ' more characters' : ( n > 160 ? ' · likely truncated' : '' ) ) : '' ).toggleClass( 'is-warn', n > 160 );
	}
	$( '#eccat_name, #eccat_excerpt, #eccat_slug' ).on( 'input', serp ); serp();

	render_tokens();
	dirty = false; $( '#eccat_dirty' ).removeClass( 'is-visible' );
} );
