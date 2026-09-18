/**
 * WP EasyCart Admin — lite editors ( menus, manufacturers, reviews ).
 *
 * Everything is wired by data attributes so the three editors share one script:
 *   [data-field]   collected into the save payload ( checkbox → 1/0 )
 *   [data-track]   marks the page dirty on change
 *   [data-bind="title"]  mirrors into the header title
 *   #eclite_slug / #eclite_redirect_row   manual slug with redirect offer
 *   [data-media]   image slots ( mode url|id ) via wp.media
 * Depends on catalog-v2.js ( ecv2_toast, ecv2_catalog ).
 */
jQuery( function( $ ) {
	'use strict';

	var $root = $( '#eclite' );
	if ( ! $root.length ) { return; }

	var D = JSON.parse( $( '#eclite_data' ).text() || '{}' );
	var AJAX_URL = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;
	var dirty = false, original_slug = D.slug || '';

	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function slugify( s ) { return String( s ).toLowerCase().replace( /[^a-z0-9\s\-]/g, '' ).trim().replace( /[\s\-]+/g, '-' ); }
	function mark_dirty() { dirty = true; $( '#eclite_dirty' ).addClass( 'is-visible' ).removeClass( 'is-saved' ).text( 'Unsaved changes' ); }
	function mark_saved() { dirty = false; $( '#eclite_dirty' ).addClass( 'is-visible is-saved' ).text( 'Saved' ); setTimeout( function() { if ( ! dirty ) { $( '#eclite_dirty' ).removeClass( 'is-visible' ); } }, 2500 ); }

	$root.on( 'input change', '[data-track]', mark_dirty );
	/* The leave-page warning follows the visible "Unsaved changes" badge as well as the private flag: editors that
	 * replace eclite.save ( store schedule, user roles, gift cards, subscription plans, fees ) hide the badge on success
	 * but cannot reach `dirty`, which left a false "unsaved changes" prompt after a successful save. */
	$( window ).on( 'beforeunload', function() {
		var $badge = $( '#eclite_dirty' );
		if ( dirty && $badge.length && ! $badge.hasClass( 'is-visible' ) ) { dirty = false; }
		if ( dirty || ( $badge.hasClass( 'is-visible' ) && ! $badge.hasClass( 'is-saved' ) ) ) { return 'You have unsaved changes.'; }
	} );
	/* Public hook for editors that replace eclite.save. */
	window.eclite_mark_saved = function() { mark_saved(); };

	/* ------------------------------------------------------------------ */
	/* Products card ( menus: add / remove; manufacturers: read-only )     */
	/* ------------------------------------------------------------------ */

	/* One product row; the × only renders for editors that expose a remove action ( menus ). Mirrors wp_easycart_admin_menu_editor_v2::product_row_html(). */
	function row_html( u ) {
		return '<div class="ecos-u" data-product-id="' + u.id + '"><span class="ecv2-thumb">' + ( u.image ? '<img src="' + esc( u.image ) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) + '</span><div class="ecos-u-main"><a class="ecv2-link-primary" href="' + esc( u.edit_url ) + '">' + esc( u.title ) + '</a><span class="ecv2-sub">' + esc( u.sku ) + '</span></div><span class="ecv2-chip ' + ( u.active ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) + '">' + ( u.active ? 'Active' : 'Inactive' ) + '</span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' + esc( u.edit_url ) + '">Open</a>' + ( D.remove_action ? '<button type="button" class="eclite-product-x" data-id="' + u.id + '" title="Remove from this menu" aria-label="Remove from this menu">&times;</button>' : '' ) + '</div>';
	}

	/* Redraw the list from a handler answer { items, total, health } ( ecv2_menu_add_product / ecv2_menu_remove_product ). */
	function refresh_products( d ) {
		var $list = $( '#eclite_products' ).empty();
		$.each( d.items || [], function( i, u ) { $list.append( row_html( u ) ); } );
		D.product_total = d.total || 0;
		var shown = $list.children().length;
		$( '#eclite_products_empty' ).toggle( ! shown );
		$( '#eclite_products_footwrap' ).toggle( shown > 0 );
		$( '#eclite_products_foot' ).text( 'Showing ' + shown + ' of ' + D.product_total );
		$( '#eclite_more' ).toggle( shown < D.product_total ).prop( 'disabled', false );
		$( '#eclite_products_hint' ).text( D.product_total + ( D.product_total === 1 ? ' product has' : ' products have' ) + ' this menu in one of ' + ( D.product_total === 1 ? 'its' : 'their' ) + ' three menu paths' );
		$( '#eclite_h_count' ).text( D.product_total + ( D.product_total === 1 ? ' product' : ' products' ) );
		$( '.ecdv2-rail-link[href="#mv2-products"] .ecv2-chip' ).text( D.product_total );
		if ( d.health ) { var $h = $( '#eclite_health .ecdv2-health-items' ).empty(); $.each( d.health, function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } ); }
	}

	function product_change( action, product_id ) {
		$.post( AJAX_URL, { action: action, nonce: D.nonce, id: D.id, level: D.level || 0, product_id: product_id }, function( r ) {
			if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); return; }
			refresh_products( r.data ); ecv2_toast( r.data.message || 'Saved', 'success' );
		}, 'json' ).fail( function() { ecv2_toast( 'Something went wrong.', 'error' ); } );
	}
	$root.on( 'click', '.eclite-product-x', function() { if ( D.remove_action ) { product_change( D.remove_action, parseInt( $( this ).data( 'id' ), 10 ) ); } } );

	/* Typeahead over ec_admin_ajax_ecv2_product_search ( raw JSON: { results:[{id,title,sku,inactive}], more } ). */
	var ps_timer, ps_seq = 0;
	$( '#eclite_product_search' ).on( 'input', function() {
		var q = $.trim( this.value );
		clearTimeout( ps_timer );
		if ( ! q ) { $( '#eclite_product_dd' ).hide(); return; }
		ps_timer = setTimeout( function() { search_products( q ); }, 200 );
	} );
	$( document ).on( 'click', function( e ) { if ( ! $( e.target ).closest( '#eclite_product_combo' ).length ) { $( '#eclite_product_dd' ).hide(); } } );
	$( '#eclite_product_search' ).on( 'keydown', function( e ) {
		var $items = $( '#eclite_product_dd .eccat-dd-item' ), $cur = $items.filter( '.is-hover' );
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) { e.preventDefault(); var i = $items.index( $cur ); i = e.key === 'ArrowDown' ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 ); $items.removeClass( 'is-hover' ).eq( i ).addClass( 'is-hover' ); }
		if ( e.key === 'Enter' ) { e.preventDefault(); ( $cur.length ? $cur : $items.first() ).trigger( 'click' ); }
		if ( e.key === 'Escape' ) { $( '#eclite_product_dd' ).hide(); }
	} );
	function search_products( q ) {
		var my = ++ps_seq;
		$( '#eclite_product_dd' ).show().html( '<div class="eccat-dd-hd"><span class="dashicons dashicons-update ecv2-spin"></span></div>' );
		$.post( AJAX_URL, { action: D.search_action || 'ec_admin_ajax_ecv2_product_search', q: q, page: 1 }, function( r ) {
			if ( my !== ps_seq ) { return; }
			var items = ( r && r.results ) ? r.results : [], listed = {};
			$( '#eclite_products .ecos-u' ).each( function() { listed[ $( this ).data( 'product-id' ) ] = true; } );
			var html = '<div class="eccat-dd-hd">' + ( items.length ? 'MATCHES' + ( r.more ? ' · first ' + items.length : '' ) : 'No matching products.' ) + '</div>';
			$.each( items, function( i, p ) {
				html += '<a href="#" class="eccat-dd-item' + ( listed[ p.id ] ? ' is-listed' : '' ) + '" data-i="' + i + '"><span class="dashicons dashicons-cart"></span><span class="eccat-dd-title">' + esc( p.title ) + '</span><small>' + esc( p.sku || '' ) + ( p.inactive ? ' · inactive' : '' ) + ( listed[ p.id ] ? ' · already in this menu' : '' ) + '</small></a>';
			} );
			$( '#eclite_product_dd' ).html( html ).data( 'items', items );
		}, 'json' ).fail( function() { if ( my === ps_seq ) { $( '#eclite_product_dd' ).html( '<div class="eccat-dd-hd">Something went wrong.</div>' ); } } );
	}
	$root.on( 'click', '#eclite_product_dd .eccat-dd-item', function( e ) {
		e.preventDefault();
		var items = $( '#eclite_product_dd' ).data( 'items' ) || [], p = items[ parseInt( $( this ).data( 'i' ), 10 ) ];
		$( '#eclite_product_search' ).val( '' ); $( '#eclite_product_dd' ).hide();
		if ( p && D.add_action ) { product_change( D.add_action, parseInt( p.id, 10 ) ); }
	} );

	/* ------------------------------------------------------------------ */
	/* Yoast SEO card ( ecv2_catalog_save_yoast, after the main save )    */
	/* ------------------------------------------------------------------ */

	var $yoast = $( '.ecv2-yoast-card' ).first(), yoast_dirty = false;
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
			if ( r && r.success ) { yoast_dirty = false; ecv2_toast( 'Yoast SEO saved', 'success' ); }
			else { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); }
		}, 'json' ).fail( function() { ecv2_toast( 'Something went wrong.', 'error' ); } );
	}
	$root.on( 'input', '[data-bind="title"]', function() { $( '#eclite_h_title' ).text( this.value || '…' ); } );
	$( '.ecdv2-rail-link' ).on( 'click', function() { $( '.ecdv2-rail-link' ).removeClass( 'is-active' ); $( this ).addClass( 'is-active' ); } );

	/* Slug */
	$( '#eclite_slug' ).on( 'input', function() {
		var v = slugify( this.value ); if ( v !== this.value ) { this.value = v; }
		var changed = original_slug !== '' && v !== original_slug;
		$( '#eclite_redirect_row' ).toggle( changed && !! D.redirects );
		$( '#eclite_slug_desc' ).text( changed ? 'The URL will change. Old links will 301-redirect to the new address if the option below stays on.' : 'Slugs are not updated automatically when you rename.' ).toggleClass( 'is-warn', changed );
	} );

	window.eclite = {
		slug_from_name: function() { $( '#eclite_slug' ).val( slugify( $( '#eclite_name' ).val() ) ).trigger( 'input' ); mark_dirty(); },

		pick: function( id ) {
			if ( ! window.wp || ! wp.media ) { ecv2_toast( 'The media library is not available on this page.', 'error' ); return; }
			var $slot = $( '#' + id + '_slot' ), mode = $slot.data( 'media' ) || 'url';
			var frame = wp.media( { title: 'Choose an image', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function() {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$( '#' + id ).val( mode === 'id' ? a.id : a.url ).trigger( 'change' );
				var src = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
				$( '#' + id + '_thumb' ).addClass( 'has' ).html( '<img src="' + esc( src ) + '" alt="">' );
				$( '#' + id + '_remove' ).show(); $slot.find( '.ecv2-btn' ).first().text( 'Replace' );
				if ( id === 'eclite_banner' ) { $( '#eclite_h_thumb' ).html( '<img src="' + esc( src ) + '" alt="">' ); }
			} );
			frame.open();
		},
		clear_image: function( id ) {
			$( '#' + id ).val( '' ).trigger( 'change' );
			$( '#' + id + '_thumb' ).removeClass( 'has' ).html( '<span class="dashicons dashicons-format-image"></span>' );
			$( '#' + id + '_remove' ).hide(); $( '#' + id + '_slot .ecv2-btn' ).first().text( 'Upload' );
			if ( id === 'eclite_banner' ) { $( '#eclite_h_thumb' ).html( '<span class="dashicons dashicons-format-image"></span>' ); }
		},

		/* Reviews */
		set_rating: function( n ) {
			$( '#eclite_rating' ).val( n ).trigger( 'change' );
			$( '.ecv2-star-btn' ).each( function() { $( this ).toggleClass( 'is-on', parseInt( $( this ).data( 'value' ), 10 ) <= n ); } );
			$( '#eclite_stars_label' ).text( n + ' of 5' );
		},
		/* Reviews: public reply ( PRO ) */
		reply_post: function() {
			var text = $.trim( $( '#ecrv_reply' ).val() );
			if ( ! text ) { $( '#ecrv_reply' ).addClass( 'is-invalid' ).trigger( 'focus' ); return; }
			var $b = $( '#ecrv_reply_post' ).prop( 'disabled', true );
			$.post( AJAX_URL, { action: D.reply_action, nonce: D.nonce, review_id: D.id, reply: text, email: $( '#ecrv_reply_email' ).is( ':checked' ) ? 1 : 0 }, function( r ) {
				$b.prop( 'disabled', false );
				if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); return; }
				$( '#ecrv_reply_status' ).text( 'replied ' + r.data.reply_date ); $b.text( 'Update reply' ); $( '#ecrv_reply' ).removeClass( 'is-invalid' );
				ecv2_toast( r.data.message, 'success' );
			}, 'json' );
		},
		reply_clear: function() {
			$.post( AJAX_URL, { action: D.reply_action, nonce: D.nonce, review_id: D.id, reply: '', email: 0 }, function( r ) {
				if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); return; }
				$( '#ecrv_reply' ).val( '' ); $( '#ecrv_reply_status' ).text( 'no reply yet' ); $( '#ecrv_reply_post' ).text( 'Post reply' );
				ecv2_toast( r.data.message, 'success' );
			}, 'json' );
		},

		quick_approve: function() {
			var $cb = $( '#eclite_approved' ); $cb.prop( 'checked', ! $cb.is( ':checked' ) ).trigger( 'change' );
			eclite.save();
		},

		focus_product_search: function() { $( '#eclite_product_search' ).trigger( 'focus' ); },

		load_more: function( btn ) {
			var $list = $( '#eclite_products' ), offset = $list.children().length;
			$( btn ).prop( 'disabled', true );
			$.post( AJAX_URL, { action: D.more_action, nonce: D.nonce, id: D.id, level: D.level || 0, offset: offset }, function( r ) {
				if ( ! r || ! r.success ) { $( btn ).prop( 'disabled', false ); return; }
				$.each( r.data.items, function( i, u ) { $list.append( row_html( u ) ); } );
				var shown = $list.children().length;
				$( '#eclite_products_foot' ).text( 'Showing ' + shown + ' of ' + D.product_total );
				/* menus keep the button ( hidden ) so an add / remove refresh can show it again; other editors drop it */
				if ( shown >= D.product_total ) { if ( D.remove_action ) { $( btn ).hide(); } else { $( btn ).remove(); } } else { $( btn ).prop( 'disabled', false ); }
			}, 'json' );
		},

		save: function() {
			var $name = $( '#eclite_name' );
			if ( $name.length && ! $.trim( $name.val() ) ) { $name.addClass( 'is-invalid' ).trigger( 'focus' ); ecv2_toast( 'A name is required.', 'error' ); return; }
			$name.removeClass( 'is-invalid' );
			var data = {};
			$root.find( '[data-field]' ).each( function() { var k = $( this ).data( 'field' ); data[ k ] = this.type === 'checkbox' ? ( this.checked ? 1 : 0 ) : this.value; } );
			data.redirect = $( '#eclite_redirect_row' ).is( ':visible' ) && $( '#eclite_redirect' ).is( ':checked' ) ? 1 : 0;
			var $btn = $( '#eclite_save' ).addClass( 'is-saving' ).prop( 'disabled', true ); $btn.find( '.ecdv2-save-label' ).text( 'Saving…' );
			$.ajax( { url: AJAX_URL, type: 'POST', dataType: 'json', data: { action: D.save_action, nonce: D.nonce, id: D.id, level: D.level || 0, data: JSON.stringify( data ) } } )
				.done( function( r ) {
					if ( ! r || ! r.success ) { ecv2_toast( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); return; }
					var d = r.data;
					if ( d.redirect ) { /* the record moved ( e.g. a menu changed level and got a new id ): follow it */
						$( '#eclite_dirty' ).removeClass( 'is-visible' ); ecv2_toast( d.message || 'Saved', 'success' ); setTimeout( function() { window.location.href = d.redirect; }, 500 ); return;
					}
					if ( d.slug !== undefined ) { original_slug = d.slug; $( '#eclite_slug' ).val( d.slug ).trigger( 'input' ); }
					if ( d.slug_prefix ) { $( '#eclite_slug_pre' ).text( d.slug_prefix ); }
					if ( d.permalink ) { $( '#eclite_view' ).attr( 'href', d.permalink ); $( '#eclite_h_url' ).text( d.permalink.replace( /^https?:\/\/[^\/]+/, '' ) ); }
					if ( d.path ) { $( '#eclite_h_path' ).text( d.path.length ? d.path.join( ' › ' ) + ' › ' + $( '#eclite_name' ).val() : 'Top level' ); }
					if ( d.approved !== undefined ) { $( '#eclite_h_status' ).toggleClass( 'is-active', !! d.approved ).text( d.approved ? 'Approved' : 'Pending' ); $( '#eclite_quick_approve' ).text( d.approved ? 'Deny' : 'Approve' ).toggleClass( 'ecv2-btn-primary-outline', ! d.approved ); }
					var $h = $( '#eclite_health .ecdv2-health-items' ).empty();
					$.each( d.health || [], function( i, h ) { $h.append( '<div class="ecdv2-health-item ' + ( h.ok ? 'is-ok' : 'is-warn' ) + '"><span class="dashicons dashicons-' + ( h.ok ? 'yes' : 'warning' ) + '"></span><span class="ecdv2-health-label">' + esc( h.label ) + '</span></div>' ); } );
					mark_saved();
					yoast_save();
					ecv2_toast( 'Saved' + ( d.redirect_added ? ' · redirect added from the old URL' : '' ), 'success' );
				} )
				.fail( function() { ecv2_toast( 'Something went wrong.', 'error' ); } )
				.always( function() { $btn.removeClass( 'is-saving' ).prop( 'disabled', false ).find( '.ecdv2-save-label' ).text( 'Save' ); } );
		}
	};

	dirty = false; $( '#eclite_dirty' ).removeClass( 'is-visible' );
} );
