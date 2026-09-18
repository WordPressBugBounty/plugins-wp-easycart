/* WP EasyCart Admin — Settings › Checkout ( V2 ): order status editor.
 * Saves through the handlers in wp_easycart_admin_order_statuses.php:
 *   ec_admin_ajax_add_orderstatus            ( order_status, color_code, is_approved ) → echoes the new id
 *   ec_admin_ajax_save_orderstatus           ( status_id, order_status, color_code )
 *   ec_admin_ajax_save_orderstatus_approved  ( status_id, is_approved )  — custom statuses only
 *   ec_admin_ajax_archieve_orderstatus       ( status_id )               — custom statuses only
 * All take wp_easycart_nonce for the 'wp-easycart-settings-checkout' action. */
( function( $ ) {
	'use strict';

	var V = window.ecst_checkout_vars || { ajax: window.ajaxurl, nonce: '', i18n: {} };
	var T = V.i18n || {};
	var $box = $( '#ecst_orderstatus' );
	if ( ! $box.length ) { return; }

	function toast( msg, kind ) {
		if ( typeof window.ecv2_toast === 'function' ) { return window.ecv2_toast( msg, kind || 'success' ); }
	}
	function confirmBox( title, text ) {
		if ( typeof window.ecv2_show_confirm === 'function' ) { return window.ecv2_show_confirm( title, text ); }
		return Promise.resolve( window.confirm( title + ( text ? '\n\n' + text : '' ) ) );
	}
	function post( action, data ) {
		return $.post( V.ajax, $.extend( { action: action, wp_easycart_nonce: V.nonce }, data ) );
	}
	function setState( $item, kind, text ) {
		var $s = $item.find( '.ecos-state' );
		clearTimeout( $s.data( 'timer' ) );
		$s.removeClass( 'is-saved is-saving is-error' ).text( text || '' );
		if ( kind ) { $s.addClass( 'is-' + kind ); }
		if ( kind === 'saved' ) { $s.data( 'timer', setTimeout( function() { $s.text( '' ).removeClass( 'is-saved' ); }, 1600 ) ); }
	}
	function setPaid( $chip, on ) {
		var $text = $chip.find( '.ecos-paid-text' );
		$chip.toggleClass( 'is-on', on );
		$text.text( on ? $text.data( 'on' ) : $text.data( 'off' ) );
	}
	function refreshCustom() {
		var n = $( '#ecst_orderstatus_rows' ).children( '.ecos-item' ).length;
		$( '#ecst_orderstatus_custom_n' ).text( n );
		$( '#ecst_orderstatus_empty' ).prop( 'hidden', n > 0 );
	}

	/* ---- color swatch follows its picker ( list and add bar ) ---- */
	$box.on( 'input change', '.ecos-color', function() {
		$( this ).closest( '.ecos-swatch' ).css( '--ecos-c', this.value );
	} );

	/* ---- rename / recolor ( debounced per item: the color picker fires continuously ) ---- */
	function saveItem( $item ) {
		clearTimeout( $item.data( 'timer' ) );
		$item.data( 'timer', setTimeout( function() {
			var $name = $item.find( '.ecos-name' ), name = $.trim( $name.val() );
			if ( name === '' ) {
				$name.val( $name.data( 'saved' ) || $name.prop( 'defaultValue' ) );
				toast( T.name_required, 'error' );
				return;
			}
			setState( $item, 'saving', T.saving );
			post( 'ec_admin_ajax_save_orderstatus', { status_id: $item.data( 'id' ), order_status: name, color_code: $item.find( '.ecos-color' ).val() } )
				.done( function() { $name.data( 'saved', name ); setState( $item, 'saved', T.saved ); } )
				.fail( function() { setState( $item, 'error', T.failed ); } );
		}, 350 ) );
	}
	$box.on( 'change', '.ecos-item .ecos-name', function() { saveItem( $( this ).closest( '.ecos-item' ) ); } );
	$box.on( 'keydown', '.ecos-item .ecos-name', function( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); $( this ).trigger( 'blur' ); }
		if ( e.key === 'Escape' ) { $( this ).val( $( this ).data( 'saved' ) || this.defaultValue ).trigger( 'blur' ); }
	} );
	$box.on( 'input change', '.ecos-item .ecos-color', function() { saveItem( $( this ).closest( '.ecos-item' ) ); } );

	/* ---- counts as paid ( custom statuses and the add bar ) ---- */
	$box.on( 'change', '.ecos-paid', function() {
		var $t = $( this ), on = $t.is( ':checked' ), $chip = $t.closest( '.ecos-paid-chip' ), $item = $t.closest( '.ecos-item' );
		setPaid( $chip, on );
		if ( ! $item.length ) { return; }
		setState( $item, 'saving', T.saving );
		post( 'ec_admin_ajax_save_orderstatus_approved', { status_id: $item.data( 'id' ), is_approved: on ? 1 : 0 } )
			.done( function() { setState( $item, 'saved', T.saved ); } )
			.fail( function() { setState( $item, 'error', T.failed ); $t.prop( 'checked', ! on ); setPaid( $chip, ! on ); } );
	} );

	/* ---- delete ( archive ) ---- */
	$box.on( 'click', '.ecos-del', function() {
		var $item = $( this ).closest( '.ecos-item' );
		confirmBox( T.confirm_title, T.confirm_text ).then( function( ok ) {
			if ( ! ok ) { return; }
			$item.addClass( 'is-removing' );
			post( 'ec_admin_ajax_archieve_orderstatus', { status_id: $item.data( 'id' ) } )
				.done( function() { $item.slideUp( 150, function() { $item.remove(); refreshCustom(); } ); toast( T.deleted, 'success' ); } )
				.fail( function() { $item.removeClass( 'is-removing' ); setState( $item, 'error', T.failed ); } );
		} );
	} );

	/* ---- add: clone the add bar's controls into a new item so markup stays in one place ( PHP ) ---- */
	function newItem( id, name, color, paid ) {
		var $add = $( '#ecst_orderstatus_add' );
		var $item = $( '<li class="ecos-item"></li>' ).attr( 'data-id', id );
		var $swatch = $add.find( '.ecos-swatch' ).clone().css( '--ecos-c', color );
		$swatch.find( 'input' ).removeAttr( 'id' ).val( color ).attr( 'aria-label', String( T.color_for || '%s' ).replace( '%s', name ) );
		var $wrap = $add.find( '.ecos-name-wrap' ).clone();
		$wrap.find( 'input' ).removeAttr( 'id placeholder' ).val( name ).attr( 'aria-label', T.status_name || '' ).data( 'saved', name );
		$wrap.find( '.ecos-state' ).text( '' ).removeClass( 'is-saved is-saving is-error' );
		var $chip = $add.find( '.ecos-paid-chip' ).clone();
		$chip.find( 'input' ).removeAttr( 'id' ).prop( 'checked', !! paid );
		setPaid( $chip, !! paid );
		var $del = $( '<button type="button" class="ecos-del"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' )
			.attr( 'title', T.delete ).attr( 'aria-label', String( T.delete_named || '%s' ).replace( '%s', name ) );
		return $item.append( $swatch, $wrap, $chip, $del );
	}
	function addStatus() {
		var $add = $( '#ecst_orderstatus_add' ), $name = $( '#ecst_orderstatus_add_name' ), $paid = $( '#ecst_orderstatus_add_paid' ), $btn = $( '#ecst_orderstatus_add_btn' );
		var name = $.trim( $name.val() ), color = $( '#ecst_orderstatus_add_color' ).val(), paid = $paid.is( ':checked' ) ? 1 : 0;
		if ( name === '' ) { $name.trigger( 'focus' ); toast( T.name_required, 'error' ); return; }
		setState( $add, 'saving', T.saving );
		$btn.prop( 'disabled', true );
		post( 'ec_admin_ajax_add_orderstatus', { order_status: name, color_code: color, is_approved: paid } )
			.done( function( r ) {
				var id = parseInt( r, 10 );
				if ( ! id ) { setState( $add, 'error', T.failed ); return; }
				var $item = newItem( id, name, color, paid ).hide();
				$( '#ecst_orderstatus_rows' ).append( $item );
				$item.fadeIn( 150 );
				refreshCustom();
				$name.val( '' ).trigger( 'focus' );
				$paid.prop( 'checked', false );
				setPaid( $paid.closest( '.ecos-paid-chip' ), false );
				setState( $add, '', '' );
				toast( T.added, 'success' );
			} )
			.fail( function() { setState( $add, 'error', T.failed ); } )
			.always( function() { $btn.prop( 'disabled', false ); } );
	}
	$box.on( 'click', '#ecst_orderstatus_add_btn', addStatus );
	$box.on( 'keydown', '#ecst_orderstatus_add_name', function( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); addStatus(); } } );
} )( jQuery );
