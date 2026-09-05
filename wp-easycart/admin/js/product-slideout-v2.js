/**
 * WP EasyCart — unified product slideout ( Create + Quick Edit ).
 *
 * Public entry points ( also wired to the legacy names so existing callers keep working ):
 *   ecpsv2_open_create()                 <- wp_easycart_admin_open_slideout( 'new_product_box' )
 *   ecpsv2_open_edit( product_id )       <- wp_easycart_open_product_quick_edit( id )
 *   ecpsv2_close()
 *
 * Server:
 *   ec_admin_ajax_save_new_quick_product   create
 *   ec_admin_ajax_get_product_quick_edit   load ( + ecv2 summary payload )
 *   ec_admin_ajax_product_quick_update     save
 *   ec_admin_ajax_ecv2_product_row         re-render one list row after save
 *
 * Everything shown is derived from the current field values, never from stale copy:
 * the header title, SKU hint, discount hint, stock/shipping sub-fields, option-set
 * counter and the dirty state all recompute on every input.
 */
( function( $ ) {
	'use strict';

	var V = window.ecpsv2_vars || {};
	var L = V.lang || {};
	var AJAX = ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) || window.ajaxurl;

	var state = {
		mode: 'create',          // 'create' | 'edit'
		product_id: 0,
		snapshot: null,          // serialized fields at load ( dirty check + discard )
		sku_touched: false,
		ship_touched: false,
		saving: false,
		manager_open: false
	};

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function $box()  { return $( '#ecpsv2_box' ); }
	function $f( id ) { return $( '#' + id ); }
	function val( id ) { return $.trim( String( $f( id ).val() == null ? '' : $f( id ).val() ) ); }
	function num( id ) { var n = parseFloat( val( id ) ); return isNaN( n ) ? null : n; }
	function checked( id ) { return $f( id ).is( ':checked' ); }
	function esc( s ) { return $( '<span>' ).text( s == null ? '' : s ).html(); }
	function fmt( n ) {
		var d = parseInt( V.decimals, 10 ); if ( isNaN( d ) ) { d = 2; }
		return ( V.currency || '$' ) + Number( n ).toFixed( d );
	}
	function toast( msg, type ) {
		if ( typeof window.ecv2_toast === 'function' ) { window.ecv2_toast( msg, type || 'success' ); return; }
		var $t = $( '<div class="ecpsv2-toast ecpsv2-toast-' + esc( type || 'success' ) + '">' ).text( msg ).appendTo( 'body' );
		setTimeout( function() { $t.fadeOut( 200, function() { $t.remove(); } ); }, 2600 );
	}
	function loader( on ) { $f( 'ecpsv2_loader' ).toggleClass( 'is-on', !! on ); }
	function slugify( s ) {
		return String( s || '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 60 );
	}
	function full_editor_url( product_id, tab ) {
		var u = 'admin.php?page=wp-easycart-products&subpage=products&product_id=' + parseInt( product_id, 10 ) + '&ec_admin_form_action=edit';
		if ( tab ) { u += '&tab=' + encodeURIComponent( tab ); }
		return u;
	}
	var DIGITAL_TYPES = { '1': 1, '2': 1, '3': 1, '4': 1, '5': 1, '6': 1, '7': 1, '9': 1, '13': 1 };
	function opt_max() { var n = parseInt( $box().attr( 'data-max-opts' ), 10 ); return ( n > 0 ) ? n : 5; }
	function opt_max_label() {
		return V.is_pro ? ( L.opt_max || 'Maximum of 5 — use modifiers for more' )
		                : ( L.opt_max_free || 'Free edition allows %d — PRO allows 5' ).replace( '%d', opt_max() );
	}

	/* ------------------------------------------------------------------ */
	/* Derived display — every hint recomputes from the fields             */
	/* ------------------------------------------------------------------ */

	function refresh_header() {
		if ( state.mode !== 'edit' ) { return; }
		var t = val( 'ecpsv2_title_input' );
		$f( 'ecpsv2_title_live' ).text( t !== '' ? t : ( L.untitled || 'Untitled product' ) );
	}

	function refresh_sku_hint() {
		var $h = $f( 'ecpsv2_sku_hint' );
		if ( state.mode === 'edit' ) { $h.text( L.sku_edit_hint || 'Changing the SKU changes the product URL.' ); return; }
		if ( val( 'ecpsv2_sku' ) === '' ) { $h.text( L.sku_blank || 'Leave blank to generate from the title.' ); }
		else if ( state.sku_touched ) { $h.text( L.sku_custom || 'Custom SKU.' ); }
		else { $h.text( L.sku_generated || 'Generated from the title — edit to override.' ); }
	}

	function refresh_discount() {
		var p = num( 'ecpsv2_price' ), l = num( 'ecpsv2_list_price' ), $h = $f( 'ecpsv2_disc_hint' );
		$h.removeClass( 'ecpsv2-ok ecpsv2-err' );
		if ( p !== null && l !== null && l > 0 ) {
			if ( l > p ) {
				var pct = Math.round( ( 1 - p / l ) * 100 );
				$h.addClass( 'ecpsv2-ok' ).text( ( L.disc_shows || 'Storefront shows %1 struck through · %2% off' ).replace( '%1', fmt( l ) ).replace( '%2', pct ) );
			} else {
				$h.addClass( 'ecpsv2-err' ).text( L.disc_not_higher || 'List price should be higher than the price.' );
			}
		} else {
			$h.text( L.disc_default || 'Shows a strike-through price and "% off" badge.' );
		}
	}

	function refresh_stock() {
		var v = val( 'ecpsv2_stock' );
		$f( 'ecpsv2_qty_f' ).css( 'visibility', v === '1' ? 'visible' : 'hidden' );
		$f( 'ecpsv2_variant_hint' ).prop( 'hidden', v !== '2' );
	}

	function refresh_shipping() {
		$f( 'ecpsv2_dims' ).prop( 'hidden', ! checked( 'ecpsv2_ship' ) );
	}

	function refresh_type_hint() {
		if ( state.mode !== 'create' ) { return; }
		var t = val( 'ecpsv2_type' ), $h = $f( 'ecpsv2_type_hint' );
		if ( ! V.is_pro ) { $h.text( L.type_pro_hint || 'Downloads, subscriptions, gift cards and more are PRO types.' ); return; }
		if ( t === '5' || t === '6' ) { $h.text( L.type_stripe || 'Subscriptions and memberships bill through Stripe, Authorize.net or PayPal.' ); }
		else if ( DIGITAL_TYPES[ t ] ) { $h.text( L.type_digital || 'This type is delivered without shipping.' ); }
		else { $h.text( '' ); }
	}

	function refresh_opt_count() {
		if ( state.mode !== 'create' ) { return; }
		var mode = val( 'ecpsv2_optmode' );
		$f( 'ecpsv2_optrows' ).prop( 'hidden', mode !== '1' );
		$f( 'ecpsv2_modifier_note' ).prop( 'hidden', mode !== '2' );
		if ( mode !== '1' ) { $f( 'ecpsv2_opt_count' ).text( '' ); return; }
		var visible = $( '#ecpsv2_opt_list .ecpsv2-opt-row' ).length, left = opt_max() - visible;
		$f( 'ecpsv2_opt_count' ).text( left <= 0
			? opt_max_label()
			: ( left === 1 ? ( L.opt_one_left || '1 more option set available' ) : ( L.opt_left || '%d more option sets available' ).replace( '%d', left ) ) );
	}

	function refresh_dirty() {
		if ( state.mode !== 'edit' ) { return; }
		var dirty = ( state.snapshot !== null && serialize() !== state.snapshot );
		$f( 'ecpsv2_discard' ).prop( 'disabled', ! dirty );
		$box().toggleClass( 'is-dirty', dirty );
	}

	function refresh_all() {
		refresh_header(); refresh_sku_hint(); refresh_discount(); refresh_stock();
		refresh_shipping(); refresh_type_hint(); refresh_opt_count(); refresh_dirty();
	}

	/* ------------------------------------------------------------------ */
	/* Field <-> data                                                      */
	/* ------------------------------------------------------------------ */

	function serialize() {
		return JSON.stringify( collect() );
	}

	function collect() {
		var stock = val( 'ecpsv2_stock' );
		return {
			activate_in_store: checked( 'ecpsv2_status' ) ? 1 : 0,
			show_on_startup:   checked( 'ecpsv2_featured' ) ? 1 : 0,
			title:             val( 'ecpsv2_title_input' ),
			model_number:      val( 'ecpsv2_sku' ),
			manufacturer_id:   val( 'ecpsv2_manu_id' ) || '0',
			manufacturer_name: val( 'ecpsv2_manu_id' ) === '0' ? val( 'ecpsv2_manu_input' ) : '',
			price:             val( 'ecpsv2_price' ),
			list_price:        val( 'ecpsv2_list_price' ),
			image1:            val( 'ecpsv2_image' ),
			sort_position:     val( 'ecpsv2_sort' ) || '0',
			stock_option:      stock,
			stock_quantity:    stock === '1' ? ( val( 'ecpsv2_qty' ) || '0' ) : '0',
			is_shippable:      checked( 'ecpsv2_ship' ) ? 1 : 0,
			weight:            val( 'ecpsv2_weight' ),
			length:            val( 'ecpsv2_length' ),
			width:             val( 'ecpsv2_width' ),
			height:            val( 'ecpsv2_height' ),
			is_taxable:        val( 'ecpsv2_tax' ),
			product_type:      state.mode === 'create' ? val( 'ecpsv2_type' ) : '',
			option_type:       state.mode === 'create' ? val( 'ecpsv2_optmode' ) : '',
			options:           state.mode === 'create' ? opt_ids() : []
		};
	}

	function reset_fields() {
		$f( 'ecpsv2_status' ).prop( 'checked', true );
		$f( 'ecpsv2_featured' ).prop( 'checked', true );
		$f( 'ecpsv2_title_input' ).val( '' );
		$f( 'ecpsv2_sku' ).val( '' );
		$f( 'ecpsv2_type' ).val( '0' );
		manu_set( 0, '' );
		$f( 'ecpsv2_sort' ).val( '0' );
		$f( 'ecpsv2_price' ).val( '' );
		$f( 'ecpsv2_list_price' ).val( '' );
		ecpsv2_set_image( '' );
		$f( 'ecpsv2_stock' ).val( '0' );
		$f( 'ecpsv2_qty' ).val( '' );
		$f( 'ecpsv2_ship' ).prop( 'checked', false );
		$f( 'ecpsv2_tax' ).val( V.default_tax || '0' );
		$( '#ecpsv2_weight, #ecpsv2_length, #ecpsv2_width, #ecpsv2_height' ).val( '' );
		$f( 'ecpsv2_optmode' ).val( '0' );
		$f( 'ecpsv2_opt_list' ).empty();
		opt_refresh();
		$( '.ecpsv2-err' ).prop( 'hidden', true );
		$( '.ecv2-input.is-invalid' ).removeClass( 'is-invalid' );
		state.sku_touched = false;
		state.ship_touched = false;
		state.snapshot = null;
	}

	function populate( p ) {
		var e = p.ecv2 || {};
		$f( 'ecpsv2_product_id' ).val( p.product_id );
		$f( 'ecpsv2_eyebrow_id' ).text( '#' + p.product_id );
		$f( 'ecpsv2_full_editor_link' ).attr( 'href', full_editor_url( p.product_id ) );
		$( '.ecpsv2-full-editor' ).each( function() { $( this ).attr( 'href', full_editor_url( p.product_id, $( this ).data( 'tab' ) ) ); } );

		$f( 'ecpsv2_status' ).prop( 'checked', parseInt( p.activate_in_store, 10 ) === 1 );
		$f( 'ecpsv2_featured' ).prop( 'checked', parseInt( p.show_on_startup, 10 ) === 1 );
		$f( 'ecpsv2_title_input' ).val( p.title || '' );
		$f( 'ecpsv2_sku' ).val( p.model_number || '' );
		$f( 'ecpsv2_type_label' ).text( e.type_label || '' );
		manu_set( parseInt( p.manufacturer_id, 10 ) || 0, e.manufacturer_name || '' );
		$f( 'ecpsv2_sort' ).val( p.sort_position || '0' );
		$f( 'ecpsv2_price' ).val( p.price );
		$f( 'ecpsv2_list_price' ).val( parseFloat( p.list_price ) > 0 ? p.list_price : '' );
		ecpsv2_set_image( p.image1 || '' );

		var stock = parseInt( p.use_optionitem_quantity_tracking, 10 ) === 1 ? '2' : ( parseInt( p.show_stock_quantity, 10 ) === 1 ? '1' : '0' );
		$f( 'ecpsv2_stock' ).val( stock );
		$f( 'ecpsv2_qty' ).val( p.stock_quantity || '0' );
		$f( 'ecpsv2_ship' ).prop( 'checked', parseInt( p.is_shippable, 10 ) === 1 );
		$f( 'ecpsv2_weight' ).val( p.weight ); $f( 'ecpsv2_length' ).val( p.length );
		$f( 'ecpsv2_width' ).val( p.width );   $f( 'ecpsv2_height' ).val( p.height );
		var tax = ( parseInt( p.is_taxable, 10 ) === 1 ? 1 : 0 ) + ( parseInt( p.vat_rate, 10 ) === 1 ? 2 : 0 );
		$f( 'ecpsv2_tax' ).val( String( tax ) );

		// Option-set summary ( read-only in quick edit ).
		var $sum = $f( 'ecpsv2_opt_summary' ).empty();
		if ( e.option_sets && e.option_sets.length ) {
			$.each( e.option_sets, function( i, name ) { $sum.append( $( '<span class="ecpsv2-chip ecpsv2-chip-on">' ).text( name ) ); } );
		}
		if ( parseInt( e.modifier_count, 10 ) > 0 ) {
			$sum.append( $( '<span class="ecpsv2-chip ecpsv2-chip-on">' ).text( ( L.modifiers_n || '%d modifiers' ).replace( '%d', e.modifier_count ) ) );
		}
		if ( ! $sum.children().length ) {
			$sum.append( $( '<span class="ecpsv2-hint">' ).text( L.no_options || 'No option sets or modifiers.' ) );
		}

		apply_pro_summary( e );

		state.snapshot = serialize();
		refresh_all();
	}

	/* PRO controls: chips + nonces for the manager modals. */
	function apply_pro_summary( e ) {
		var $cell = $f( 'ecpsv2_price_cell' );
		if ( $cell.length ) {
			var a = e.advanced || {};
			$cell.attr( {
				'data-product-id': state.product_id,
				'data-nonce': e.nonces ? e.nonces.price : '',
				'data-volume-nonce': e.nonces ? e.nonces.volume : '',
				'data-b2b-nonce': e.nonces ? e.nonces.b2b : '',
				'data-price': val( 'ecpsv2_price' ),
				'data-list-price': val( 'ecpsv2_list_price' ),
				'data-advanced': JSON.stringify( a )
			} );
			chip( 'ecpsv2_chip_volume', parseInt( a.tier_count, 10 ) || 0, L.chip_volume_n || '%d volume tiers', L.chip_volume || '+ Volume tiers' );
			chip( 'ecpsv2_chip_b2b', parseInt( a.roleprice_count, 10 ) || 0, L.chip_b2b_n || '%d B2B roles', L.chip_b2b || '+ B2B pricing' );
			var adv_on = parseInt( a.show_custom_price_range, 10 ) === 1 || parseInt( a.enable_price_label, 10 ) > 0 || parseInt( a.login_for_pricing, 10 ) === 1;
			$f( 'ecpsv2_chip_advanced' ).toggleClass( 'ecpsv2-chip-on', adv_on ).text( adv_on ? ( L.chip_advanced_on || 'Price label / range set' ) : ( L.chip_advanced || '+ Price label, range, login-to-view' ) );
		}
		$f( 'ecpsv2_gallery_btn' ).data( 'nonce', e.nonces ? e.nonces.image : '' );
		var n = parseInt( e.image_count, 10 ) || 0;
		$f( 'ecpsv2_gallery_hint' ).text( n > 0 ? ( L.images_n || '%d images in gallery' ).replace( '%d', n ) : ( L.images_none || 'Gallery is empty.' ) );
	}
	function chip( id, count, on_tpl, off_label ) {
		$f( id ).toggleClass( 'ecpsv2-chip-on', count > 0 ).text( count > 0 ? on_tpl.replace( '%d', count ) : off_label );
	}
	function refetch_pro_summary() {
		if ( state.mode !== 'edit' || ! state.product_id ) { return; }
		$.post( AJAX, { action: 'ec_admin_ajax_get_product_quick_edit', product_id: state.product_id }, function( data ) {
			var p; try { p = JSON.parse( data ); } catch ( err ) { return; }
			if ( p && p.ecv2 ) { apply_pro_summary( p.ecv2 ); }
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Open / close                                                        */
	/* ------------------------------------------------------------------ */

	function set_mode( mode ) {
		state.mode = mode;
		$box().attr( 'data-mode', mode );
	}

	function show() {
		var box = $box();
		// Belt and braces against the page reflowing beside the panel: guarantee the
		// node is a <body> child and pinned to the viewport regardless of the cascade.
		if ( ! box.parent().is( 'body' ) ) { box.appendTo( document.body ); }
		box.css( { position: 'fixed', top: 0, right: 0, bottom: 0, left: 0, width: 'auto', height: 'auto', margin: 0, 'z-index': 99999, 'justify-content': 'flex-end' } );
		box.stop( true, true ).css( 'display', 'flex' ).hide().fadeIn( 120 );
		$( 'body' ).addClass( 'ecpsv2-open' );
		setTimeout( function() { $f( 'ecpsv2_title_input' ).trigger( 'focus' ); }, 180 );
	}

	window.ecpsv2_open_create = function() {
		set_mode( 'create' );
		state.product_id = 0;
		reset_fields();
		refresh_all();
		loader( false );
		show();
		return false;
	};

	window.ecpsv2_open_edit = function( product_id ) {
		set_mode( 'edit' );
		state.product_id = parseInt( product_id, 10 );
		reset_fields();
		$f( 'ecpsv2_title_live' ).text( L.loading || 'Loading…' );
		$f( 'ecpsv2_eyebrow_id' ).text( '#' + state.product_id );
		loader( true );
		show();
		$.post( AJAX, { action: 'ec_admin_ajax_get_product_quick_edit', product_id: state.product_id }, function( data ) {
			var p; try { p = JSON.parse( data ); } catch ( err ) { p = null; }
			loader( false );
			if ( ! p || ! p.product_id ) { toast( L.error || 'Could not load this product.', 'error' ); ecpsv2_close( true ); return; }
			populate( p );
		} ).fail( function() { loader( false ); toast( L.error || 'Could not load this product.', 'error' ); ecpsv2_close( true ); } );
		return false;
	};

	window.ecpsv2_close = function( force ) {
		if ( ! force && state.mode === 'edit' && state.snapshot !== null && serialize() !== state.snapshot ) {
			if ( ! window.confirm( L.confirm_discard || 'You have unsaved changes. Close without saving?' ) ) { return false; }
		}
		$box().stop( true, true ).fadeOut( 120 );
		$( 'body' ).removeClass( 'ecpsv2-open' );
		return false;
	};

	window.ecpsv2_discard = function() {
		if ( state.mode !== 'edit' || state.snapshot === null ) { return; }
		var p = JSON.parse( state.snapshot );
		$f( 'ecpsv2_status' ).prop( 'checked', p.activate_in_store === 1 );
		$f( 'ecpsv2_featured' ).prop( 'checked', p.show_on_startup === 1 );
		$f( 'ecpsv2_title_input' ).val( p.title );  $f( 'ecpsv2_sku' ).val( p.model_number );
		manu_set( parseInt( p.manufacturer_id, 10 ) || 0, p.manufacturer_name || manu_name_for( p.manufacturer_id ) ); $f( 'ecpsv2_sort' ).val( p.sort_position );
		$f( 'ecpsv2_price' ).val( p.price ); $f( 'ecpsv2_list_price' ).val( p.list_price );
		ecpsv2_set_image( p.image1 );
		$f( 'ecpsv2_stock' ).val( p.stock_option ); $f( 'ecpsv2_qty' ).val( p.stock_quantity );
		$f( 'ecpsv2_ship' ).prop( 'checked', p.is_shippable === 1 );
		$f( 'ecpsv2_weight' ).val( p.weight ); $f( 'ecpsv2_length' ).val( p.length ); $f( 'ecpsv2_width' ).val( p.width ); $f( 'ecpsv2_height' ).val( p.height );
		$f( 'ecpsv2_tax' ).val( p.is_taxable );
		$( '.ecpsv2-err' ).prop( 'hidden', true ); $( '.is-invalid' ).removeClass( 'is-invalid' );
		refresh_all();
	};

	/* Nested legacy slideouts ( new manufacturer / new option set ) must layer above us. */
	window.ecpsv2_open_nested = function( id ) {
		$( '#' + id ).css( 'z-index', 100001 );
		if ( typeof window.ecpsv2_legacy_open_slideout === 'function' ) { window.ecpsv2_legacy_open_slideout( id ); }
	};

	/* ------------------------------------------------------------------ */
	/* Image                                                               */
	/* ------------------------------------------------------------------ */

	window.ecpsv2_set_image = function( url ) {
		url = url || '';
		$f( 'ecpsv2_image' ).val( url );
		$f( 'ecpsv2_thumb_img' ).attr( 'src', url ).prop( 'hidden', url === '' );
		$f( 'ecpsv2_thumb_ph' ).prop( 'hidden', url !== '' );
		$f( 'ecpsv2_thumb' ).toggleClass( 'has-image', url !== '' );
		$f( 'ecpsv2_img_remove' ).prop( 'hidden', url === '' );
		$f( 'ecpsv2_img_name' ).text( url === '' ? ( L.no_image || 'No main image yet' ) : decodeURIComponent( url.split( '/' ).pop().split( '?' )[ 0 ] ) );
		refresh_dirty();
	};

	window.ecpsv2_pick_image = function() {
		if ( ! window.wp || ! wp.media ) { return; }
		var frame = wp.media( {
			title: L.select_image || 'Select image',
			button: { text: L.use_image || 'Use this image' },
			multiple: false,
			library: { type: 'image' }
		} );
		frame.on( 'select', function() {
			var a = frame.state().get( 'selection' ).first().toJSON();
			ecpsv2_set_image( a.url );
		} );
		frame.open();
	};

	/* ------------------------------------------------------------------ */
	/* Manufacturer combobox: pick an existing brand or type a new one     */
	/* ------------------------------------------------------------------ */

	function manu_list() {
		var raw = $f( 'ecpsv2_manu_combo' ).attr( 'data-list' ), list;
		try { list = JSON.parse( raw || '[]' ); } catch ( e ) { list = []; }
		return list;
	}
	function manu_name_for( id ) {
		id = parseInt( id, 10 ) || 0;
		var hit = $.grep( manu_list(), function( m ) { return m.id === id; } );
		return hit.length ? hit[ 0 ].name : '';
	}
	function manu_set( id, name ) {
		id = parseInt( id, 10 ) || 0;
		if ( id && ! name ) { name = manu_name_for( id ); }
		$f( 'ecpsv2_manu_id' ).val( id );
		$f( 'ecpsv2_manu_input' ).val( name || '' );
		manu_hint();
		manu_close();
	}
	function manu_hint() {
		var id = parseInt( val( 'ecpsv2_manu_id' ), 10 ) || 0, txt = val( 'ecpsv2_manu_input' ), $h = $f( 'ecpsv2_manu_hint' );
		$f( 'ecpsv2_manu_clear' ).prop( 'hidden', txt === '' );
		if ( txt === '' ) { $h.text( '' ); return; }
		if ( id ) { $h.text( L.manu_existing || 'Existing brand.' ); return; }
		var exact = $.grep( manu_list(), function( m ) { return m.name.toLowerCase() === txt.toLowerCase(); } );
		if ( exact.length ) { $f( 'ecpsv2_manu_id' ).val( exact[ 0 ].id ); $h.text( L.manu_existing || 'Existing brand.' ); return; }
		$h.text( ( L.manu_new || '“%s” will be created as a new brand when you save.' ).replace( '%s', txt ) );
	}
	function manu_open() {
		var txt = val( 'ecpsv2_manu_input' ).toLowerCase(), $ul = $f( 'ecpsv2_manu_list' ).empty();
		var matches = $.grep( manu_list(), function( m ) { return txt === '' || m.name.toLowerCase().indexOf( txt ) !== -1; } ).slice( 0, 8 );
		$.each( matches, function( i, m ) {
			$( '<li role="option">' ).attr( 'data-id', m.id ).text( m.name ).appendTo( $ul );
		} );
		var exact = $.grep( manu_list(), function( m ) { return m.name.toLowerCase() === txt; } ).length;
		if ( txt !== '' && ! exact ) {
			$( '<li role="option" class="ecpsv2-combo-create">' ).attr( 'data-id', 0 ).text( ( L.manu_create || 'Create “%s”' ).replace( '%s', val( 'ecpsv2_manu_input' ) ) ).appendTo( $ul );
		}
		var open = $ul.children().length > 0;
		$ul.prop( 'hidden', ! open );
		$f( 'ecpsv2_manu_input' ).attr( 'aria-expanded', open ? 'true' : 'false' );
	}
	function manu_close() {
		$f( 'ecpsv2_manu_list' ).prop( 'hidden', true );
		$f( 'ecpsv2_manu_input' ).attr( 'aria-expanded', 'false' );
	}
	/* After a save creates a brand, remember it so the next open recognises it. */
	function manu_learn( id, name ) {
		id = parseInt( id, 10 ) || 0;
		if ( ! id || ! name || manu_name_for( id ) ) { return; }
		var list = manu_list(); list.push( { id: id, name: name } );
		list.sort( function( a, b ) { return a.name.localeCompare( b.name ); } );
		$f( 'ecpsv2_manu_combo' ).attr( 'data-list', JSON.stringify( list ) );
		manu_set( id, name );
	}

	/* ------------------------------------------------------------------ */
	/* Option sets ( create ): pick one at a time, drag to reorder, remove */
	/* ------------------------------------------------------------------ */

	function opt_source() {
		var out = [];
		$( '#ec_new_product_option1 option' ).each( function() {
			var v = parseInt( this.value, 10 );
			if ( v > 0 ) { out.push( { id: v, name: $( this ).text() } ); }
		} );
		return out;
	}
	function opt_ids() {
		var ids = $( '#ecpsv2_opt_list .ecpsv2-opt-row' ).map( function() { return String( $( this ).data( 'id' ) ); } ).get();
		while ( ids.length < 5 ) { ids.push( '0' ); }
		return ids.slice( 0, 5 );
	}
	function opt_add( id, name ) {
		id = parseInt( id, 10 );
		if ( ! id || $( '#ecpsv2_opt_list .ecpsv2-opt-row[data-id="' + id + '"]' ).length ) { return; }
		if ( $( '#ecpsv2_opt_list .ecpsv2-opt-row' ).length >= opt_max() ) {
			if ( ! V.is_pro && typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'variants' } ); }
			return;
		}
		var $row = $( '<div class="ecpsv2-opt-row">' ).attr( 'data-id', id );
		$row.append( '<span class="ecpsv2-opt-drag" title="' + esc( L.drag || 'Drag to reorder' ) + '" aria-hidden="true"><svg viewBox="0 0 10 16" width="10" height="16"><circle cx="3" cy="3" r="1.3"/><circle cx="7" cy="3" r="1.3"/><circle cx="3" cy="8" r="1.3"/><circle cx="7" cy="8" r="1.3"/><circle cx="3" cy="13" r="1.3"/><circle cx="7" cy="13" r="1.3"/></svg></span>' );
		$row.append( '<span class="ecpsv2-opt-n"></span>' );
		$row.append( $( '<span class="ecpsv2-opt-name">' ).text( name ) );
		$row.append( '<button type="button" class="ecpsv2-opt-rm" aria-label="' + esc( L.remove || 'Remove' ) + '">×</button>' );
		$f( 'ecpsv2_opt_list' ).append( $row );
		opt_refresh();
		refresh_dirty();
	}
	function opt_refresh() {
		var chosen = {};
		$( '#ecpsv2_opt_list .ecpsv2-opt-row' ).each( function( i ) {
			chosen[ $( this ).data( 'id' ) ] = 1;
			$( this ).find( '.ecpsv2-opt-n' ).text( i + 1 );
		} );
		var n = $( '#ecpsv2_opt_list .ecpsv2-opt-row' ).length;
		$f( 'ecpsv2_opt_empty' ).prop( 'hidden', n > 0 );
		// Picker lists only the sets not yet chosen; disabled once five are in.
		var $p = $f( 'ecpsv2_opt_picker' ), first = $p.find( 'option' ).first();
		$p.empty().append( first );
		$.each( opt_source(), function( i, o ) {
			if ( ! chosen[ o.id ] ) { $( '<option>' ).val( o.id ).text( o.name ).appendTo( $p ); }
		} );
		var at_max = n >= opt_max();
		$p.val( '0' ).prop( 'disabled', at_max || $p.find( 'option' ).length <= 1 );
		first.text( at_max ? opt_max_label() : ( $p.find( 'option' ).length <= 1 ? ( L.opt_none_left || 'All option sets added' ) : ( L.opt_pick || '+ Add an option set…' ) ) );
		$f( 'ecpsv2_opt_more' ).prop( 'hidden', ! ( at_max && ! V.is_pro ) );
		refresh_opt_count();
	}
	function opt_init_sortable() {
		var $l = $f( 'ecpsv2_opt_list' );
		if ( ! $l.length || ! $.fn.sortable ) { return; }
		$l.sortable( {
			handle: '.ecpsv2-opt-drag',
			axis: 'y',
			containment: 'parent',
			tolerance: 'pointer',
			placeholder: 'ecpsv2-opt-row ecpsv2-opt-placeholder',
			update: function() { opt_refresh(); refresh_dirty(); }
		} );
	}
	/* The new-option-set slideout inserts its option into #ec_new_product_option1; adopt it as a row. */
	function opt_watch_source() {
		var node = document.getElementById( 'ec_new_product_option1' );
		if ( ! node || ! window.MutationObserver ) { return; }
		var known = {};
		$.each( opt_source(), function( i, o ) { known[ o.id ] = 1; } );
		new MutationObserver( function() {
			$.each( opt_source(), function( i, o ) {
				if ( ! known[ o.id ] ) { known[ o.id ] = 1; if ( val( 'ecpsv2_optmode' ) === '1' ) { opt_add( o.id, o.name ); } }
			} );
			opt_refresh();
		} ).observe( node, { childList: true } );
	}

	/* ------------------------------------------------------------------ */
	/* PRO managers                                                        */
	/* ------------------------------------------------------------------ */

	window.ecpsv2_open_manager = function( which, el ) {
		if ( ! state.product_id ) { return false; }
		state.manager_open = true;
		if ( which === 'volume'   && typeof window.ecv2_open_volume_from_badge === 'function' ) { window.ecv2_open_volume_from_badge( el ); }
		if ( which === 'b2b'      && typeof window.ecv2_open_b2b_from_badge === 'function' )    { window.ecv2_open_b2b_from_badge( el ); }
		if ( which === 'advanced' && typeof window.ecv2_open_advanced_pricing === 'function' )  { window.ecv2_open_advanced_pricing( el ); }
		if ( which === 'images'   && typeof window.ecv2_open_image_manager === 'function' )     { window.ecv2_open_image_manager( state.product_id, $( el ).data( 'nonce' ) ); }
		if ( which === 'variants' && typeof window.ecv2_open_variant_popup === 'function' )     { window.ecv2_open_variant_popup( state.product_id ); }
		return false;
	};

	/* When a manager modal closes, pull a fresh summary so the chips are never stale. */
	function watch_managers() {
		var ids = [ 'ecv2-variant-popup', 'ecv2-image-manager-modal', 'ecv2-volume-pricing-modal', 'ecv2-b2b-pricing-modal', 'ecv2-advanced-pricing-slideout' ];
		if ( ! window.MutationObserver ) { return; }
		var obs = new MutationObserver( function() {
			if ( ! state.manager_open ) { return; }
			var any_open = false;
			$.each( ids, function( i, id ) { if ( $( '#' + id ).is( ':visible' ) ) { any_open = true; } } );
			if ( ! any_open ) { state.manager_open = false; refetch_pro_summary(); }
		} );
		$.each( ids, function( i, id ) {
			var n = document.getElementById( id );
			if ( n ) { obs.observe( n, { attributes: true, attributeFilter: [ 'style', 'class' ] } ); }
		} );
	}

	/* Volume/B2B managers call this on save; mirror the count into our chips too. */
	function hook_count_refresh() {
		var orig = window.ecv2_refresh_price_cell_count;
		window.ecv2_refresh_price_cell_count = function( product_id, field, count ) {
			if ( typeof orig === 'function' ) { orig.apply( this, arguments ); }
			if ( parseInt( product_id, 10 ) !== state.product_id ) { return; }
			if ( field === 'tier_count' )      { chip( 'ecpsv2_chip_volume', parseInt( count, 10 ) || 0, L.chip_volume_n || '%d volume tiers', L.chip_volume || '+ Volume tiers' ); }
			if ( field === 'roleprice_count' ) { chip( 'ecpsv2_chip_b2b', parseInt( count, 10 ) || 0, L.chip_b2b_n || '%d B2B roles', L.chip_b2b || '+ B2B pricing' ); }
		};
	}

	/* ------------------------------------------------------------------ */
	/* Validate + save                                                     */
	/* ------------------------------------------------------------------ */

	function mark( id, err_id, bad, msg ) {
		$f( id ).toggleClass( 'is-invalid', bad );
		var $e = $f( err_id ).prop( 'hidden', ! bad );
		if ( bad && msg ) { $e.text( msg ); }
	}

	function validate() {
		var ok = true, d = collect();
		if ( d.title === '' ) { mark( 'ecpsv2_title_input', 'ecpsv2_title_err', true ); ok = false; } else { mark( 'ecpsv2_title_input', 'ecpsv2_title_err', false ); }
		if ( state.mode === 'create' && d.model_number === '' ) { d.model_number = slugify( d.title ); $f( 'ecpsv2_sku' ).val( d.model_number ); }
		if ( d.model_number === '' ) { mark( 'ecpsv2_sku', 'ecpsv2_sku_err', true, L.sku_required || 'A SKU is required.' ); ok = false; } else { mark( 'ecpsv2_sku', 'ecpsv2_sku_err', false ); }
		var p = parseFloat( d.price );
		if ( d.price === '' || isNaN( p ) || p < 0 ) { mark( 'ecpsv2_price', 'ecpsv2_price_err', true ); ok = false; } else { mark( 'ecpsv2_price', 'ecpsv2_price_err', false ); }
		if ( ! ok ) { $( '.is-invalid' ).first().trigger( 'focus' ); }
		return ok ? d : null;
	}

	window.ecpsv2_save = function( next ) {
		if ( state.saving ) { return false; }
		var d = validate();
		if ( ! d ) { return false; }
		state.saving = true; loader( true );
		var nonce = val( 'wp_easycart_product_quick_edit_nonce' );

		if ( state.mode === 'create' ) {
			var payload = {
				action: 'ec_admin_ajax_save_new_quick_product',
				wp_easycart_nonce: nonce,
				ec_new_product_status: d.activate_in_store,
				ec_new_product_featured: d.show_on_startup,
				ec_new_product_type: d.product_type,
				ec_new_product_title: d.title,
				ec_new_product_sku: d.model_number,
				ec_new_product_manufacturer: d.manufacturer_id,
				ec_new_product_manufacturer_name: d.manufacturer_name,
				ec_new_product_price: d.price,
				ec_new_product_list_price: d.list_price,
				ec_new_product_image: d.image1,
				ec_new_product_option_type: d.option_type,
				option1: d.options[ 0 ], option2: d.options[ 1 ], option3: d.options[ 2 ], option4: d.options[ 3 ], option5: d.options[ 4 ],
				ec_new_product_is_shippable: d.is_shippable,
				ec_new_product_weight: d.weight, ec_new_product_length: d.length, ec_new_product_width: d.width, ec_new_product_height: d.height,
				ec_new_product_is_taxable: d.is_taxable,
				ec_new_product_stock_option: d.stock_option,
				ec_new_product_stock_quantity: d.stock_quantity
			};
			$.post( AJAX, payload, function( data ) {
				var r; try { r = JSON.parse( data ); } catch ( err ) { r = { error: 'parse' }; }
				state.saving = false; loader( false );
				if ( r.error ) {
					if ( r.error === 'model-number-error' ) { mark( 'ecpsv2_sku', 'ecpsv2_sku_err', true, L.sku_duplicate || 'That SKU is already in use — choose another.' ); $f( 'ecpsv2_sku' ).trigger( 'focus' ); }
					else { toast( L.error || 'An error occurred. Please try again.', 'error' ); }
					return;
				}
				if ( r.manufacturer_id && d.manufacturer_name ) { manu_learn( r.manufacturer_id, d.manufacturer_name ); }
				if ( next === 'edit' ) { window.location.href = full_editor_url( r.product_id ); return; }
				if ( next === 'another' ) {
					toast( ( L.created_named || '“%s” created.' ).replace( '%s', d.title ) );
					reset_fields(); refresh_all(); $f( 'ecpsv2_title_input' ).trigger( 'focus' );
					return;
				}
				ecpsv2_close( true );
				window.location.reload();
			} ).fail( function() { state.saving = false; loader( false ); toast( L.network || 'Network error. Please try again.', 'error' ); } );
			return false;
		}

		// Edit.
		var upd = {
			action: 'ec_admin_ajax_product_quick_update',
			wp_easycart_nonce: nonce,
			product_id: state.product_id,
			activate_in_store: d.activate_in_store, show_on_startup: d.show_on_startup,
			title: d.title, model_number: d.model_number, manufacturer_id: d.manufacturer_id, manufacturer_name: d.manufacturer_name,
			price: d.price, list_price: d.list_price, image1: d.image1, sort_position: d.sort_position,
			stock_option: d.stock_option, stock_quantity: d.stock_quantity,
			is_shippable: d.is_shippable, weight: d.weight, length: d.length, width: d.width, height: d.height,
			is_taxable: d.is_taxable
		};
		$.post( AJAX, upd, function( data ) {
			var r; try { r = JSON.parse( data ); } catch ( err ) { r = null; }
			if ( ! r || ! r.product_id ) { state.saving = false; loader( false ); toast( L.error || 'An error occurred. Please try again.', 'error' ); return; }
			if ( r.error === 'model-number-error' ) { state.saving = false; loader( false ); mark( 'ecpsv2_sku', 'ecpsv2_sku_err', true, L.sku_duplicate || 'That SKU is already in use — choose another.' ); return; }
			if ( r.manufacturer_id && d.manufacturer_name ) { manu_learn( r.manufacturer_id, d.manufacturer_name ); }
			if ( next === 'edit' ) { window.location.href = full_editor_url( r.product_id ); return; }
			refresh_row( r.product_id, function( replaced ) {
				state.saving = false; loader( false );
				state.snapshot = serialize(); refresh_dirty();
				toast( L.saved || 'Product saved.' );
				ecpsv2_close( true );
				if ( ! replaced ) { window.location.reload(); }
			} );
		} ).fail( function() { state.saving = false; loader( false ); toast( L.network || 'Network error. Please try again.', 'error' ); } );
		return false;
	};

	/* Re-render the list row server-side so every badge/toggle matches what was saved. */
	function refresh_row( product_id, done ) {
		var $row = $( 'tr.ecv2-row[data-id="' + product_id + '"]' );
		if ( ! $row.length ) { done( false ); return; }
		$.post( AJAX, { action: 'ec_admin_ajax_ecv2_product_row', product_id: product_id, wp_easycart_nonce: val( 'wp_easycart_product_quick_edit_nonce' ) }, function( html ) {
			html = $.trim( html || '' );
			if ( html.indexOf( '<tr' ) === 0 ) {
				var $new = $( html );
				$row.replaceWith( $new );
				$new.addClass( 'ecpsv2-row-flash' );
				setTimeout( function() { $new.removeClass( 'ecpsv2-row-flash' ); }, 1200 );
				done( true );
			} else { done( false ); }
		} ).fail( function() { done( false ); } );
	}

	/* ------------------------------------------------------------------ */
	/* Wiring                                                              */
	/* ------------------------------------------------------------------ */

	$( function() {
		if ( ! $box().length ) { return; }

		// The panel must be a direct child of <body>; if the inline mover ran before jQuery
		// was ready ( or the shell re-parented it ), it ends up in the page flow and squeezes
		// the content beside it. Move it now and pin it.
		if ( ! $box().parent().is( 'body' ) ) { $box().appendTo( document.body ); }

		// Legacy entry points route here.
		window.wp_easycart_open_product_quick_edit = function( id ) { return ecpsv2_open_edit( id ); };
		if ( typeof window.wp_easycart_admin_open_slideout === 'function' && ! window.ecpsv2_legacy_open_slideout ) {
			window.ecpsv2_legacy_open_slideout = window.wp_easycart_admin_open_slideout;
			window.wp_easycart_admin_open_slideout = function( id ) {
				if ( id === 'new_product_box' ) { return ecpsv2_open_create(); }
				return window.ecpsv2_legacy_open_slideout( id );
			};
		}

		var $b = $box();

		$b.on( 'input', '#ecpsv2_title_input', function() {
			if ( state.mode === 'create' && ! state.sku_touched ) { $f( 'ecpsv2_sku' ).val( slugify( this.value ) ); }
			mark( 'ecpsv2_title_input', 'ecpsv2_title_err', false );
			refresh_header(); refresh_sku_hint(); refresh_dirty();
		} );
		$b.on( 'input', '#ecpsv2_sku', function() {
			state.sku_touched = ( this.value !== '' );
			this.value = this.value.replace( /[^A-Za-z0-9\-]/g, '-' );
			mark( 'ecpsv2_sku', 'ecpsv2_sku_err', false );
			refresh_sku_hint(); refresh_dirty();
		} );
		$b.on( 'input', '#ecpsv2_price, #ecpsv2_list_price', function() {
			mark( 'ecpsv2_price', 'ecpsv2_price_err', false );
			refresh_discount(); refresh_dirty();
			$f( 'ecpsv2_price_cell' ).attr( { 'data-price': val( 'ecpsv2_price' ), 'data-list-price': val( 'ecpsv2_list_price' ) } );
		} );
		$b.on( 'change', '#ecpsv2_stock', function() {
			var $o = $( this ).find( 'option:selected' );
			if ( $o.data( 'pro' ) && ! V.variants_pro ) {
				$( this ).val( state.snapshot ? JSON.parse( state.snapshot ).stock_option : '0' );
				if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products', feature: 'variants' } ); }
			}
			refresh_stock(); refresh_dirty();
		} );
		$b.on( 'change', '#ecpsv2_ship', function() { state.ship_touched = true; refresh_shipping(); refresh_dirty(); } );
		$b.on( 'change', '#ecpsv2_type', function() {
			var $o = $( this ).find( 'option:selected' );
			if ( $o.data( 'pro' ) && ! V.is_pro ) {
				$( this ).val( '0' );
				if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'products' } ); }
			}
			if ( ! state.ship_touched && DIGITAL_TYPES[ val( 'ecpsv2_type' ) ] ) { $f( 'ecpsv2_ship' ).prop( 'checked', false ); }
			refresh_type_hint(); refresh_shipping();
		} );
		$b.on( 'change', '#ecpsv2_optmode', function() { opt_refresh(); } );
		$b.on( 'change', '#ecpsv2_opt_picker', function() {
			var id = this.value, name = $( this ).find( 'option:selected' ).text();
			if ( id !== '0' ) { opt_add( id, name ); }
		} );
		$b.on( 'click', '#ecpsv2_opt_list .ecpsv2-opt-rm', function() { $( this ).closest( '.ecpsv2-opt-row' ).remove(); opt_refresh(); refresh_dirty(); } );

		// Manufacturer combobox.
		$b.on( 'focus input', '#ecpsv2_manu_input', function() { $f( 'ecpsv2_manu_id' ).val( '0' ); manu_hint(); manu_open(); refresh_dirty(); } );
		$b.on( 'mousedown', '#ecpsv2_manu_list li', function( e ) {
			e.preventDefault();
			var id = parseInt( $( this ).data( 'id' ), 10 ) || 0;
			manu_set( id, id ? $( this ).text() : val( 'ecpsv2_manu_input' ) );
			refresh_dirty();
		} );
		$b.on( 'keydown', '#ecpsv2_manu_input', function( e ) {
			var $ul = $f( 'ecpsv2_manu_list' ), $act = $ul.find( '.is-active' );
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( $ul.is( '[hidden]' ) ) { manu_open(); return; }
				var $items = $ul.children(), i = $items.index( $act );
				i = e.key === 'ArrowDown' ? Math.min( i + 1, $items.length - 1 ) : Math.max( i - 1, 0 );
				$items.removeClass( 'is-active' ).eq( i ).addClass( 'is-active' );
			} else if ( e.key === 'Enter' ) {
				if ( $act.length ) { e.preventDefault(); $act.trigger( 'mousedown' ); }
				else if ( ! $ul.is( '[hidden]' ) ) { e.preventDefault(); manu_close(); }
			} else if ( e.key === 'Escape' && ! $ul.is( '[hidden]' ) ) {
				e.stopPropagation(); manu_close();
			}
		} );
		$b.on( 'blur', '#ecpsv2_manu_input', function() { setTimeout( manu_close, 120 ); } );
		$b.on( 'click', '#ecpsv2_manu_clear', function() { manu_set( 0, '' ); refresh_dirty(); $f( 'ecpsv2_manu_input' ).trigger( 'focus' ); } );

		opt_init_sortable();
		opt_watch_source();
		opt_refresh();
		$b.on( 'change input', 'input, select', function() { refresh_dirty(); } );

		$b.on( 'click', '#ecpsv2_manage_variants', function( e ) { e.preventDefault(); ecpsv2_open_manager( 'variants', this ); } );

		// Overlay click + Escape close ( Escape yields to an open manager modal ).
		$b.on( 'mousedown', function( e ) { if ( e.target === this ) { ecpsv2_close(); } } );
		$( document ).on( 'keydown', function( e ) {
			if ( e.key !== 'Escape' || ! $b.is( ':visible' ) || state.manager_open ) { return; }
			if ( $( '#ec_admin_upsell_popup' ).is( ':visible' ) || $( '#ecosv2_box' ).is( ':visible' ) ) { return; }
			ecpsv2_close();
		} );
		// Ctrl/Cmd+Enter saves.
		$b.on( 'keydown', function( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 'Enter' ) { e.preventDefault(); ecpsv2_save( 'close' ); }
		} );

		hook_count_refresh();
		watch_managers();
	} );

} )( jQuery );