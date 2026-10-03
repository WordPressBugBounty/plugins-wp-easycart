/**
 * WP EasyCart — boxes and packages ( 6.0.2 ).
 *
 * Settings › Shipping › Boxes: the box list and its editor ( #ecpk_boxes ).
 * Order screen: the Packages block in the Fulfillment card ( #ecpk_order ), its package editor, Add tracking and
 * Mark delivered. The block is redrawn from the server after every change; label extensions call
 * window.ecpk_replace( html ) with the block their own request sent back, and listen for the 'ecpk:updated' event.
 *
 * The package editor ( #ecpk_edit_modal ) works on a copy of the block's .ecpk-data: each package is a card with its
 * box ( or a custom size ), weight ( worked out from the items and box until the merchant types one ) and items with
 * quantity steppers and a Move menu; units that are in no package wait in "Not in a package yet". Save sends the
 * packages to ecv2_order_packages_save ( same request as before ) once every unit is in exactly one package.
 *
 * Data: ecpk_vars ( wp_easycart_admin_packages::script_vars() ).
 */
( function( $ ) {
	'use strict';

	var V = window.ecpk_vars || { i18n: {}, boxes: [], units: { dim: 'in', weight: 'lb' } };
	var T = V.i18n || {};
	V.units = V.units || { dim: 'in', weight: 'lb' };

	function t( key, fallback ) {
		return ( T && T[ key ] ) ? T[ key ] : fallback;
	}

	/* 6.0.2: %s / %d in order, or %1$s / %2$d by position ( the wording comes from PHP's translated strings ). */
	function sp( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;
		return String( str ).replace( /%(?:(\d+)\$)?[sd]/g, function( match, pos ) {
			var value = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ next++ ];
			return ( undefined === value || null === value ) ? '' : String( value );
		} );
	}

	function toast( msg, kind ) {
		/* 6.0.2: the order screen's own toast there ( ecv2_toast is not loaded on it ). */
		if ( document.getElementById( 'ecpk_order' ) && 'function' === typeof window.ecodv2_save_toast ) {
			return window.ecodv2_save_toast( msg, 'error' === kind );
		}
		if ( typeof window.ecv2_toast === 'function' ) {
			if ( ! document.getElementById( 'ecv2-toast-container' ) ) {
				$( '<div id="ecv2-toast-container" role="status" aria-live="polite"></div>' ).appendTo( 'body' );
			}
			return window.ecv2_toast( msg, kind || 'success' );
		}
		var $t = $( '<div class="ecpk-toast" role="status"></div>' ).toggleClass( 'is-error', 'error' === kind ).text( msg ).appendTo( 'body' );
		setTimeout( function() { $t.fadeOut( 200, function() { $t.remove(); } ); }, 2600 );
	}

	function num( value ) {
		var n = parseFloat( String( value == null ? '' : value ).replace( ',', '.' ) );
		return isNaN( n ) || n < 0 ? 0 : n;
	}

	function fmt( n ) {
		return String( Math.round( num( n ) * 100 ) / 100 );
	}

	function fmt3( n ) {
		return String( Math.round( num( n ) * 1000 ) / 1000 );
	}

	function post( data ) {
		return $.ajax( { url: V.ajax_url || window.ajaxurl, type: 'post', dataType: 'json', data: data } );
	}

	function fail( xhr, $error ) {
		var msg = ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) ? xhr.responseJSON.data.message : t( 'error', 'That did not work. Please try again.' );
		if ( $error && $error.length ) {
			$error.text( msg ).prop( 'hidden', false );
		} else {
			toast( msg, 'error' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Modals                                                              */
	/* ------------------------------------------------------------------ */

	/* 6.0.2: dialogs stack ( the Discard changes question opens over the package editor ), so Escape, the backdrop and
	   Close act on the top one only, Tab stays inside it, and focus goes back to what opened it. */
	var stack = [];

	function topModal() {
		return stack.length ? stack[ stack.length - 1 ] : null;
	}

	function focusables( $scope ) {
		return $scope.find( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' ).filter( ':visible' );
	}

	function openModal( $modal, $backdrop, autofocus ) {
		stack.push( { $modal: $modal, $backdrop: $backdrop, back: document.activeElement } );
		$backdrop.prop( 'hidden', false );
		$modal.prop( 'hidden', false ).find( '.ecpk-error' ).prop( 'hidden', true ).text( '' );
		$( 'body' ).addClass( 'ecpk-modal-open' );
		if ( false !== autofocus ) {
			setTimeout( function() { $modal.find( 'input:visible, select:visible' ).first().trigger( 'focus' ); }, 30 );
		}
	}

	function closeModal( $modal ) {
		var entry = null;
		var i;
		for ( i = stack.length - 1; i >= 0; i-- ) {
			if ( stack[ i ].$modal[ 0 ] === $modal[ 0 ] ) {
				entry = stack.splice( i, 1 )[ 0 ];
				break;
			}
		}
		$modal.prop( 'hidden', true );
		if ( ! entry ) {
			return;
		}
		var shared = false;
		$.each( stack, function( j, other ) {
			if ( other.$backdrop[ 0 ] === entry.$backdrop[ 0 ] ) {
				shared = true;
			}
		} );
		if ( ! shared ) {
			entry.$backdrop.prop( 'hidden', true );
		}
		if ( ! stack.length ) {
			$( 'body' ).removeClass( 'ecpk-modal-open' );
		}
		var back = entry.back;
		if ( back && back.focus && document.body.contains( back ) && back !== document.body ) {
			back.focus();
		} else {
			/* The button that opened it was redrawn ( ecpk_replace ): the block's first control instead. */
			var $first = focusables( $( '#ecpk_order' ) ).first();
			if ( $first.length ) {
				$first[ 0 ].focus();
			}
		}
	}

	function closeModals() {
		while ( stack.length ) {
			closeModal( stack[ stack.length - 1 ].$modal );
		}
		$( '.ecpk-modal, .ecpk-modal-backdrop' ).prop( 'hidden', true );
	}

	function requestClose( $modal ) {
		if ( $modal.is( '#ecpk_edit_modal' ) ) {
			editorRequestClose();
			return;
		}
		closeModal( $modal );
	}

	$( document ).on( 'click', '[data-ecpk-close]', function( e ) {
		e.preventDefault();
		var $modal = $( this ).closest( '.ecpk-modal' );
		if ( $modal.length ) {
			requestClose( $modal );
		} else {
			closeModals();
		}
	} );

	$( document ).on( 'click', '.ecpk-modal-backdrop', function( e ) {
		e.preventDefault();
		var top = topModal();
		if ( top ) {
			requestClose( top.$modal );
		}
	} );

	$( document ).on( 'keydown', function( e ) {
		var top = topModal();
		if ( ! top ) {
			return;
		}
		if ( ED.menu && menuKey( e ) ) {
			return;
		}
		if ( 'Escape' === e.key || 'Esc' === e.key ) {
			e.preventDefault();
			requestClose( top.$modal );
			return;
		}
		if ( 'Tab' !== e.key ) {
			return;
		}
		var $f = focusables( top.$modal );
		if ( ! $f.length ) {
			e.preventDefault();
			return;
		}
		var first = $f[ 0 ];
		var last = $f[ $f.length - 1 ];
		var at = $f.index( document.activeElement );
		if ( ! top.$modal[ 0 ].contains( document.activeElement ) ) {
			e.preventDefault();
			( e.shiftKey ? last : first ).focus();
		} else if ( -1 === at ) {
			/* On a heading ( tabindex -1 ): Tab moves on by itself; Shift+Tab would leave the dialog. */
			if ( e.shiftKey ) {
				e.preventDefault();
				last.focus();
			}
		} else if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Settings › Shipping › Boxes                                         */
	/* ------------------------------------------------------------------ */

	var boxes = $.isArray( V.boxes ) ? V.boxes : [];

	function typeLabel( type ) {
		return { box: t( 'type_box', 'Box' ), envelope: t( 'type_envelope', 'Envelope' ), soft: t( 'type_soft', 'Soft pack' ) }[ type ] || type;
	}

	function drawBoxes() {
		var $list = $( '#ecpk_boxes_list' );
		if ( ! $list.length ) {
			return;
		}
		$list.empty();
		if ( ! boxes.length ) {
			$list.append( $( '<p class="ecpk-muted"></p>' ).text( t( 'none', 'No boxes yet.' ) ) );
			return;
		}
		var $table = $( '<table class="ecpk-table"><thead><tr><th></th><th></th><th></th><th></th><th></th></tr></thead><tbody></tbody></table>' );
		var heads = [ t( 'col_box', 'Box' ), t( 'col_size', 'Inside size' ), t( 'col_empty', 'Empty weight' ), t( 'col_max', 'Holds up to' ), '' ];
		$table.find( 'th' ).each( function( i ) { $( this ).text( heads[ i ] ); } );
		$table.find( 'th' ).eq( 2 ).addClass( 'ecpk-col-weight' ).end().eq( 3 ).addClass( 'ecpk-col-weight' ); /* 6.0.2: hidden at phone width */
		$.each( boxes, function( i, box ) {
			var $tr = $( '<tr></tr>' ).toggleClass( 'is-off', ! parseInt( box.is_active, 10 ) );
			/* 6.0.2: the name, then its chips, with the type on a quiet second line ( "Medium box Box" read as one name ). */
			var $name = $( '<td class="ecpk-box-name"></td>' ).append( $( '<b></b>' ).text( box.label ) );
			if ( parseInt( box.is_default, 10 ) ) {
				$name.append( ' ' ).append( $( '<span class="ecv2-chip ecpk-chip-default"></span>' ).text( t( 'default', 'Default' ) ).attr( 'title', t( 'default_hint', 'Used for orders that cannot be packed by size.' ) ) );
			}
			if ( ! parseInt( box.is_active, 10 ) ) {
				$name.append( ' ' ).append( $( '<span class="ecv2-chip ecv2-chip-gray"></span>' ).text( t( 'off', 'Off' ) ) );
			}
			$name.append( $( '<span class="ecpk-box-type"></span>' ).text( typeLabel( box.package_type ) ) );
			var size = num( box.length ) > 0 ? fmt( box.length ) + ' × ' + fmt( box.width ) + ( num( box.height ) > 0 ? ' × ' + fmt( box.height ) : '' ) + ' ' + V.units.dim : '—';
			/* 6.0.2: the name and the size open the editor; Edit, Make default, on / off and Remove sit in a "…" menu. */
			$name.find( 'b' ).first().replaceWith( $( '<button type="button" class="ecpk-box-open" data-ecpk-box-edit></button>' ).attr( 'data-id', box.package_id ).text( box.label ) );
			$tr.append( $name );
			$tr.append( $( '<td></td>' ).append( $( '<button type="button" class="ecpk-box-open is-size" data-ecpk-box-edit></button>' ).attr( 'data-id', box.package_id ).text( size ) ) );
			$tr.append( $( '<td class="ecpk-col-weight"></td>' ).text( num( box.box_weight ) > 0 ? fmt( box.box_weight ) + ' ' + V.units.weight : '—' ) );
			$tr.append( $( '<td class="ecpk-col-weight"></td>' ).text( num( box.max_weight ) > 0 ? fmt( box.max_weight ) + ' ' + V.units.weight : '—' ) );
			var $menu = $( '<div class="ecv2-row-menu ecpk-box-menu" role="menu"></div>' );
			var item = function( label, attr, icon, danger ) {
				return $( '<button type="button" class="ecv2-row-menu-item" role="menuitem"></button>' ).toggleClass( 'ecv2-row-menu-item-danger', !! danger ).attr( attr, '' ).attr( 'data-id', box.package_id ).append( $( '<span class="dashicons" aria-hidden="true"></span>' ).addClass( 'dashicons-' + icon ) ).append( $( '<span></span>' ).text( label ) );
			};
			$menu.append( item( t( 'edit', 'Edit' ), 'data-ecpk-box-edit', 'edit' ) );
			if ( ! parseInt( box.is_default, 10 ) ) {
				$menu.append( item( t( 'make_default', 'Make default' ), 'data-ecpk-box-default', 'star-filled' ) );
			}
			$menu.append( item( parseInt( box.is_active, 10 ) ? t( 'turn_off', 'Stop using for packing' ) : t( 'turn_on', 'Use for packing' ), 'data-ecpk-box-toggle', parseInt( box.is_active, 10 ) ? 'hidden' : 'visibility' ) );
			$menu.append( item( t( 'remove', 'Remove' ), 'data-ecpk-box-delete', 'trash', true ) );
			var $wrap = $( '<div class="ecv2-row-menu-wrap"></div>' )
				.append( $( '<button type="button" class="ecv2-row-menu-trigger" aria-haspopup="true" aria-expanded="false">&hellip;</button>' ).attr( 'aria-label', t( 'actions', 'Box actions' ) + ': ' + box.label ) )
				.append( $menu );
			$tr.append( $( '<td class="ecpk-row-actions"></td>' ).append( $wrap ) );
			$table.find( 'tbody' ).append( $tr );
		} );
		$list.append( $table );
	}

	function fillTemplates( selected ) {
		var $sel = $( '#ecpk_box_template' );
		var templates = V.templates || {};
		var keys = Object.keys( templates );
		$( '#ecpk_box_template_row' ).prop( 'hidden', ! keys.length );
		$sel.find( 'option:not(:first)' ).remove();
		$.each( keys, function( i, key ) {
			$sel.append( $( '<option></option>' ).attr( 'value', key ).text( templates[ key ] ) );
		} );
		$sel.val( selected || '' );
	}

	function openBox( box ) {
		box = box || {};
		$( '#ecpk_box_title' ).text( box.package_id ? t( 'edit_box', 'Edit box' ) : t( 'add_box', 'Add a box' ) );
		$( '#ecpk_box_id' ).val( box.package_id || 0 );
		$( '#ecpk_box_label' ).val( box.label || '' );
		$( '#ecpk_box_type' ).val( box.package_type || 'box' );
		$( '#ecpk_box_length' ).val( box.length ? fmt( box.length ) : '' );
		$( '#ecpk_box_width' ).val( box.width ? fmt( box.width ) : '' );
		$( '#ecpk_box_height' ).val( box.height ? fmt( box.height ) : '' );
		$( '#ecpk_box_weight' ).val( box.box_weight ? box.box_weight : '' );
		$( '#ecpk_box_max' ).val( box.max_weight ? box.max_weight : '' );
		$( '#ecpk_box_default' ).prop( 'checked', !! parseInt( box.is_default || ( boxes.length ? 0 : 1 ), 10 ) );
		/* 6.0.2: the default box stays the default until another box takes over ( the save never clears it ), so its
		   switch shows on and fixed, with a note on how to move it. */
		var isDefault = !! ( box.package_id && parseInt( box.is_default, 10 ) );
		$( '#ecpk_box_default' ).prop( 'disabled', isDefault );
		$( '#ecpk_box_default_note' ).text( isDefault ? t( 'default_locked', 'This is the default box. To move the default, choose Make default in another box’s … menu.' ) : '' ).prop( 'hidden', ! isDefault );
		$( '#ecpk_box_active' ).prop( 'checked', undefined === box.is_active || !! parseInt( box.is_active, 10 ) );
		fillTemplates( box.carrier_template || '' );
		autoName();
		openModal( $( '#ecpk_box_modal' ), $( '#ecpk_box_backdrop' ) );
	}

	/* 6.0.2: an empty name is saved as the carrier box's name, else the type and size ( wp_easycart_packages::auto_label() );
	   the name field shows which as its placeholder. */
	function autoName() {
		var templates = V.templates || {};
		var tpl = $( '#ecpk_box_template' ).val();
		var name = ( tpl && templates[ tpl ] ) ? templates[ tpl ] : typeLabel( $( '#ecpk_box_type' ).val() || 'box' );
		if ( ! ( tpl && templates[ tpl ] ) ) {
			var sides = [];
			$.each( [ '#ecpk_box_length', '#ecpk_box_width', '#ecpk_box_height' ], function( i, sel ) {
				if ( num( $( sel ).val() ) > 0 ) { sides.push( fmt( $( sel ).val() ) ); }
			} );
			if ( sides.length ) {
				name += ' ' + sides.join( ' × ' ) + ' ' + V.units.dim;
			}
		}
		$( '#ecpk_box_label' ).attr( 'placeholder', name ).attr( 'title', t( 'auto_name', 'Leave empty to name it by its size' ) );
	}

	$( document ).on( 'input change', '#ecpk_box_type, #ecpk_box_length, #ecpk_box_width, #ecpk_box_height, #ecpk_box_template', autoName );

	function findBox( id ) {
		var found = null;
		$.each( boxes, function( i, box ) {
			if ( parseInt( box.package_id, 10 ) === parseInt( id, 10 ) ) {
				found = box;
			}
		} );
		return found;
	}

	$( document ).on( 'click', '#ecpk_box_add', function( e ) {
		e.preventDefault();
		openBox( null );
	} );

	/* 6.0.2: the box list's "…" menu. */
	function closeBoxMenus() {
		$( '#ecpk_boxes_list .ecv2-row-menu' ).removeClass( 'ecv2-row-menu-open' );
		$( '#ecpk_boxes_list .ecv2-row-menu-trigger' ).attr( 'aria-expanded', 'false' );
	}

	$( document ).on( 'click', '#ecpk_boxes_list .ecv2-row-menu-trigger', function( e ) {
		e.preventDefault();
		e.stopPropagation();
		var $menu = $( this ).siblings( '.ecv2-row-menu' );
		var open = ! $menu.hasClass( 'ecv2-row-menu-open' );
		closeBoxMenus();
		$menu.toggleClass( 'ecv2-row-menu-open', open );
		$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			$menu.find( '.ecv2-row-menu-item' ).first().trigger( 'focus' );
		}
	} );

	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '#ecpk_boxes_list .ecv2-row-menu-wrap' ).length ) {
			closeBoxMenus();
		}
	} );

	$( document ).on( 'keydown', '#ecpk_boxes_list .ecv2-row-menu', function( e ) {
		if ( 'Escape' === e.key ) {
			var $trigger = $( this ).siblings( '.ecv2-row-menu-trigger' );
			closeBoxMenus();
			$trigger.trigger( 'focus' );
		}
	} );

	/* 6.0.2: a confirm dialog in the page's own style ( it was the browser's box ). */
	function confirmBox( title, text, ok, done, cancel ) {
		/* The V2 settings confirm ( catalog-v2.js, loaded on every settings page ) when it is there. */
		if ( 'function' === typeof window.ecv2_show_confirm ) {
			var asked = window.ecv2_show_confirm( title, text );
			if ( asked && 'function' === typeof asked.then ) {
				asked.then( function( yes ) { if ( yes ) { done(); } } );
				return;
			}
		}
		var $modal = $( '#ecpk_confirm_modal' );
		if ( ! $modal.length ) {
			$( 'body' ).append( '<div class="ecpk-modal-backdrop ecpk-confirm-backdrop" id="ecpk_confirm_backdrop" hidden></div>' );
			$modal = $( '<div class="ecpk-modal ecpk-confirm ecv2-layer" id="ecpk_confirm_modal" role="alertdialog" aria-modal="true" aria-labelledby="ecpk_confirm_title" aria-describedby="ecpk_confirm_text" hidden></div>' )
				.append( $( '<div class="ecpk-modal-head"></div>' ).append( '<h3 id="ecpk_confirm_title"></h3>' ) )
				.append( $( '<div class="ecpk-modal-body"></div>' ).append( '<p id="ecpk_confirm_text"></p>' ) )
				.append( $( '<div class="ecpk-modal-foot"></div>' ).append( '<button type="button" class="ecv2-btn" id="ecpk_confirm_cancel" data-ecpk-close></button>' ).append( '<button type="button" class="ecv2-btn ecv2-btn-danger" id="ecpk_confirm_ok"></button>' ) )
				.appendTo( 'body' );
		}
		$( '#ecpk_confirm_title' ).text( title );
		$( '#ecpk_confirm_text' ).text( text );
		$( '#ecpk_confirm_cancel' ).text( cancel || t( 'cancel', 'Cancel' ) );
		$( '#ecpk_confirm_ok' ).text( ok ).off( 'click' ).on( 'click', function() {
			closeModal( $modal );
			done();
		} );
		openModal( $modal, $( '#ecpk_confirm_backdrop' ), false );
		setTimeout( function() { $( '#ecpk_confirm_ok' ).trigger( 'focus' ); }, 40 );
	}

	/* Save a box as it is, with some fields changed ( Make default, on / off ). */
	function saveBoxChange( id, change ) {
		var box = findBox( id );
		if ( ! box ) {
			return;
		}
		post( { action: 'ecv2_packages_box_save', nonce: V.nonce, box: JSON.stringify( $.extend( {}, box, change ) ) } ).done( function( r ) {
			if ( r && r.success ) {
				boxes = r.data.boxes || [];
				drawBoxes();
				toast( r.data.message );
			} else {
				fail( { responseJSON: r } );
			}
		} ).fail( function( xhr ) { fail( xhr ); } );
	}

	$( document ).on( 'click', '[data-ecpk-box-edit]', function( e ) {
		e.preventDefault();
		closeBoxMenus();
		openBox( findBox( $( this ).attr( 'data-id' ) ) );
	} );

	$( document ).on( 'click', '[data-ecpk-box-default]', function( e ) {
		e.preventDefault();
		closeBoxMenus();
		saveBoxChange( $( this ).attr( 'data-id' ), { is_default: 1 } );
	} );

	$( document ).on( 'click', '[data-ecpk-box-toggle]', function( e ) {
		e.preventDefault();
		closeBoxMenus();
		var box = findBox( $( this ).attr( 'data-id' ) );
		if ( box ) {
			saveBoxChange( box.package_id, { is_active: parseInt( box.is_active, 10 ) ? 0 : 1, is_default: parseInt( box.is_default, 10 ) ? 1 : 0 } );
		}
	} );

	$( document ).on( 'click', '[data-ecpk-box-delete]', function( e ) {
		e.preventDefault();
		closeBoxMenus();
		var id = $( this ).attr( 'data-id' );
		var box = findBox( id );
		confirmBox( t( 'remove_title', 'Remove box' ) + ( box ? ': ' + box.label : '' ), t( 'delete_confirm', 'Remove this box?' ), t( 'remove', 'Remove' ), function() {
			post( { action: 'ecv2_packages_box_delete', nonce: V.nonce, package_id: id } ).done( function( r ) {
				if ( r && r.success ) {
					boxes = r.data.boxes || [];
					drawBoxes();
					toast( r.data.message );
				} else {
					fail( { responseJSON: r } );
				}
			} ).fail( function( xhr ) { fail( xhr ); } );
		} );
	} );

	$( document ).on( 'click', '#ecpk_box_save', function( e ) {
		e.preventDefault();
		var $btn = $( this ).prop( 'disabled', true );
		var box = {
			package_id: $( '#ecpk_box_id' ).val(),
			label: $( '#ecpk_box_label' ).val(),
			package_type: $( '#ecpk_box_type' ).val(),
			length: $( '#ecpk_box_length' ).val(),
			width: $( '#ecpk_box_width' ).val(),
			height: $( '#ecpk_box_height' ).val(),
			box_weight: $( '#ecpk_box_weight' ).val(),
			max_weight: $( '#ecpk_box_max' ).val(),
			carrier_template: $( '#ecpk_box_template' ).val() || '',
			is_default: $( '#ecpk_box_default' ).is( ':checked' ) ? 1 : 0,
			is_active: $( '#ecpk_box_active' ).is( ':checked' ) ? 1 : 0
		};
		post( { action: 'ecv2_packages_box_save', nonce: V.nonce, box: JSON.stringify( box ) } ).done( function( r ) {
			if ( r && r.success ) {
				boxes = r.data.boxes || [];
				drawBoxes();
				closeModal( $( '#ecpk_box_modal' ) );
				toast( r.data.message );
			} else {
				fail( { responseJSON: r }, $( '#ecpk_box_error' ) );
			}
		} ).fail( function( xhr ) { fail( xhr, $( '#ecpk_box_error' ) ); } ).always( function() { $btn.prop( 'disabled', false ); } );
	} );

	/* ------------------------------------------------------------------ */
	/* Order screen: the Packages block                                    */
	/* ------------------------------------------------------------------ */

	function orderId() {
		return $( '#ecpk_order' ).attr( 'data-order-id' ) || '';
	}

	function blockData() {
		var raw = $( '#ecpk_order .ecpk-data' ).first().text();
		try {
			return JSON.parse( raw || '{}' );
		} catch ( err ) {
			return { packages: [], lines: [] };
		}
	}

	/* Replace the block with fresh server HTML ( also for label extensions ). */
	window.ecpk_replace = function( html ) {
		var $block = $( '#ecpk_order' );
		if ( ! $block.length || 'string' !== typeof html ) {
			return;
		}
		$block.html( html );
		$( document ).trigger( 'ecpk:updated', [ orderId() ] );
	};

	/* 6.0.2: a line added, changed or removed on the order screen draws the block again, so the package editor counts
	   the order's lines as they are now ( it read the page-load list before ). */
	var refreshTimer = null;
	$( document ).ajaxSuccess( function( e, xhr, settings ) {
		var sent = ( settings && 'string' === typeof settings.data ) ? settings.data : '';
		if ( ! $( '#ecpk_order' ).length || -1 === sent.indexOf( 'order_detail_line_item' ) ) {
			return;
		}
		clearTimeout( refreshTimer );
		refreshTimer = setTimeout( function() {
			post( { action: 'ecv2_order_packages_refresh', nonce: V.order_nonce, order_id: orderId() } ).done( function( r ) {
				if ( r && r.success ) {
					window.ecpk_replace( r.data.html || '' );
				}
			} );
		}, 150 );
	} );

	/* 6.0.2 bug round 6: a button that saves says so until the answer comes back ( or the page reloads ): disabled, a spinner
	   and "Saving…" ( its data-busy-text ). on false puts its own label back. */
	function busy( $btn, on ) {
		if ( ! $btn || ! $btn.length ) {
			return;
		}
		if ( on ) {
			if ( undefined === $btn.data( 'ecpkLabel' ) ) {
				$btn.data( 'ecpkLabel', $btn.html() );
			}
			$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' ).addClass( 'ecpk-busy' ).empty()
				.append( $( '<span class="ecpk-spin" aria-hidden="true"></span>' ) )
				.append( document.createTextNode( $btn.attr( 'data-busy-text' ) || t( 'ed_saving', 'Saving…' ) ) );
			return;
		}
		if ( undefined !== $btn.data( 'ecpkLabel' ) ) {
			$btn.html( $btn.data( 'ecpkLabel' ) );
			$btn.removeData( 'ecpkLabel' );
		}
		$btn.prop( 'disabled', false ).removeAttr( 'aria-busy' ).removeClass( 'ecpk-busy' );
	}

	function orderAction( data, done, $btn ) {
		data.nonce = V.order_nonce;
		data.order_id = orderId();
		busy( $btn, true );
		return post( data ).done( function( r ) {
			if ( r && r.success ) {
				window.ecpk_replace( r.data.html || '' );
				if ( r.data.message ) {
					toast( r.data.message );
				}
				if ( done ) {
					done( r.data );
				}
			} else {
				busy( $btn, false );
				fail( { responseJSON: r } );
			}
		} ).fail( function( xhr ) {
			busy( $btn, false );
			fail( xhr );
		} );
	}

	/* 6.0.2: both change the order at once and cannot be undone here, so they ask first. */
	$( document ).on( 'click', '#ecpk_order [data-ecpk-repack]', function( e ) {
		e.preventDefault();
		var $btn = $( this );
		confirmBox( t( 'repack_title', 'Pack again?' ), t( 'repack_ask', 'Packages without a label are rebuilt from your box settings. Changes you made to them are lost.' ), t( 'repack', 'Pack again' ), function() {
			orderAction( { action: 'ecv2_order_packages_repack' }, null, $btn );
		} );
	} );

	$( document ).on( 'click', '#ecpk_order [data-ecpk-delivered]', function( e ) {
		e.preventDefault();
		var id = $( this ).attr( 'data-ecpk-delivered' );
		var $btn = $( this );
		confirmBox( t( 'delivered_title', 'Mark delivered?' ), t( 'delivered_ask', 'The package is marked delivered. Once every package has arrived, the order reads as delivered; a partly refunded order keeps its Partial Refund status.' ), t( 'delivered', 'Mark delivered' ), function() {
			orderAction( { action: 'ecv2_order_packages_delivered', shipment_id: id }, function() {
				/* The order may have moved to Delivered: redraw the rest of the page too. */
				window.ecodv2_leaving = true;
				setTimeout( function() { window.location.reload(); }, 700 );
			}, $btn );
		} );
	} );

	/* Add tracking */
	$( document ).on( 'click', '#ecpk_order [data-ecpk-track]', function( e ) {
		e.preventDefault();
		var raw = String( $( this ).attr( 'data-ecpk-track' ) || '0' );
		var sid = parseInt( raw, 10 ) || 0;
		/* 6.0.2 bug round 6: every package of the store's own opens the order screen's Fulfill window on its tracking row,
		   right after the packages changed too ( the window's rows are drawn again first ). A fulfillment partner's package
		   is not in it and keeps this dialog. */
		if ( '1' === String( $( this ).attr( 'data-ecpk-store' ) ) && 0 !== raw.indexOf( 'p:' ) && 'function' === typeof window.ecodv2_fulfill_package && window.ecodv2_fulfill_package( sid, parseInt( $( this ).attr( 'data-ecpk-index' ), 10 ) || 0 ) ) {
			return;
		}
		if ( sid && 'function' === typeof window.ecodv2_fulfill_open && $( '#ecodv2_label_popup .ecodv2-label-pkg[data-shipment-id="' + sid + '"]:not(.is-done)' ).length ) {
			window.ecodv2_fulfill_open( sid );
			return;
		}
		$( '#ecpk_track_shipment' ).val( raw );
		$( '#ecpk_track_number' ).val( '' );
		$( '#ecpk_track_error' ).prop( 'hidden', true ).text( '' );
		openModal( $( '#ecpk_track_modal' ), $( '#ecpk_order_backdrop' ) );
	} );

	$( document ).on( 'click', '#ecpk_track_save', function( e ) {
		e.preventDefault();
		var $btn = $( this );
		if ( $btn.prop( 'disabled' ) ) {
			return;
		}
		var tracking = $.trim( $( '#ecpk_track_number' ).val() );
		if ( '' === tracking ) {
			$( '#ecpk_track_error' ).text( t( 'need_tracking', 'Enter the tracking number.' ) ).prop( 'hidden', false );
			return;
		}
		$( '#ecpk_track_error' ).prop( 'hidden', true ).text( '' );
		busy( $btn, true );
		post( {
			action: 'ecv2_order_packages_track',
			nonce: V.order_nonce,
			order_id: orderId(),
			shipment_id: $( '#ecpk_track_shipment' ).val(),
			carrier: $( '#ecpk_track_carrier' ).val(),
			tracking_number: tracking,
			notify: $( '#ecpk_track_notify' ).is( ':checked' ) ? 1 : 0
		} ).done( function( r ) {
			if ( r && r.success ) {
				toast( r.data.message );
				/* Tracking and status changed on the order: redraw the page. The button keeps saying Saving… until it reloads. */
				window.ecodv2_leaving = true;
				setTimeout( function() { window.location.reload(); }, 700 );
			} else {
				busy( $btn, false );
				fail( { responseJSON: r }, $( '#ecpk_track_error' ) );
			}
		} ).fail( function( xhr ) {
			busy( $btn, false );
			fail( xhr, $( '#ecpk_track_error' ) );
		} );
	} );

	/* ------------------------------------------------------------------ */
	/* Package editor ( 6.0.2 )                                            */
	/* ------------------------------------------------------------------ */

	/* lines: the order's shippable lines ( id, title, sku, detail, qty, weight each ). pkgs: every package in order, a
	   locked one ( label bought ) read-only. auto: the weight follows the items and box until the merchant types one. */
	var ED = { lines: [], pkgs: [], planned: false, initial: '', seq: 0, focus: null, menu: null, saving: false, state: '' };

	function el( tag, cls, text ) {
		var $e = $( document.createElement( tag ) );
		if ( cls ) {
			$e.attr( 'class', cls );
		}
		if ( undefined !== text && null !== text ) {
			$e.text( text );
		}
		return $e;
	}

	function icon( name ) {
		return el( 'span', 'dashicons dashicons-' + name ).attr( 'aria-hidden', 'true' );
	}

	function lineOf( id ) {
		var i;
		for ( i = 0; i < ED.lines.length; i++ ) {
			if ( ED.lines[ i ].id === String( id ) ) {
				return ED.lines[ i ];
			}
		}
		return null;
	}

	function findPkg( key ) {
		var i;
		for ( i = 0; i < ED.pkgs.length; i++ ) {
			if ( ED.pkgs[ i ].uid === key ) {
				return ED.pkgs[ i ];
			}
		}
		return null;
	}

	function qtyIn( pkg, id ) {
		return ( pkg && pkg.items ) ? ( parseInt( pkg.items[ id ], 10 ) || 0 ) : 0;
	}

	function addUnits( pkg, id, n ) {
		var q = qtyIn( pkg, id ) + n;
		if ( q > 0 ) {
			pkg.items[ id ] = q;
		} else {
			delete pkg.items[ id ];
		}
	}

	function packedIn( id, locked ) {
		var n = 0;
		$.each( ED.pkgs, function( i, pkg ) {
			if ( !! pkg.locked === !! locked ) {
				n += qtyIn( pkg, id );
			}
		} );
		return n;
	}

	/* Units of a line still to place ( below 0: placed too many times ). Packages with a label count as placed. */
	function leftFor( line ) {
		return Math.max( 0, line.qty - packedIn( line.id, true ) ) - packedIn( line.id, false );
	}

	function editables() {
		return $.grep( ED.pkgs, function( pkg ) { return ! pkg.locked; } );
	}

	function unitsIn( pkg ) {
		var n = 0;
		$.each( ED.lines, function( i, line ) {
			n += qtyIn( pkg, line.id );
		} );
		return n;
	}

	function itemsText( n ) {
		return sp( 1 === n ? t( 'items_one', '%d item' ) : t( 'items_many', '%d items' ), n );
	}

	function pkgTitle( pkg ) {
		return sp( t( 'ed_package', 'Package %d' ), $.inArray( pkg, ED.pkgs ) + 1 );
	}

	/* "Package 2 ( Medium box )" in the Move menus. */
	function pkgTarget( pkg ) {
		var box = findBox( pkg.package_id );
		return pkgTitle( pkg ) + ( box ? ' ( ' + box.label + ' )' : '' );
	}

	function sizeText( l, w, h ) {
		if ( num( l ) <= 0 || num( w ) <= 0 ) {
			return '';
		}
		return fmt( l ) + ' × ' + fmt( w ) + ( num( h ) > 0 ? ' × ' + fmt( h ) : '' ) + ' ' + V.units.dim;
	}

	function weightText( n ) {
		return fmt3( n ) + ' ' + V.units.weight;
	}

	function contentsOf( pkg ) {
		var w = 0;
		$.each( ED.lines, function( i, line ) {
			w += num( line.weight ) * qtyIn( pkg, line.id );
		} );
		return w;
	}

	function autoWeight( pkg ) {
		var box = findBox( pkg.package_id );
		return Math.round( ( contentsOf( pkg ) + ( box ? num( box.box_weight ) : 0 ) ) * 1000 ) / 1000;
	}

	function syncWeight( pkg ) {
		if ( pkg.auto && ! pkg.locked ) {
			pkg.weight = autoWeight( pkg );
		}
	}

	function activeBoxes() {
		return $.grep( boxes, function( box ) { return !! parseInt( box.is_active, 10 ); } );
	}

	function defaultBox() {
		var active = activeBoxes();
		var found = null;
		$.each( active, function( i, box ) {
			if ( ! found && parseInt( box.is_default, 10 ) ) {
				found = box;
			}
		} );
		return found || active[ 0 ] || null;
	}

	function uid() {
		ED.seq += 1;
		return 'k' + ED.seq;
	}

	function newPackage() {
		var box = defaultBox();
		var pkg = {
			uid: uid(),
			shipment_id: 0,
			package_id: box ? parseInt( box.package_id, 10 ) : 0,
			package_name: box ? box.label : '',
			length: box ? num( box.length ) : 0,
			width: box ? num( box.width ) : 0,
			height: box ? num( box.height ) : 0,
			weight: 0,
			auto: true,
			items: {},
			locked: false,
			label: '',
			tracking: ''
		};
		syncWeight( pkg );
		return pkg;
	}

	/* What Save would change, to tell whether anything did. */
	function snapshot() {
		var out = [];
		$.each( ED.pkgs, function( i, pkg ) {
			if ( pkg.locked ) {
				return;
			}
			var items = [];
			$.each( ED.lines, function( j, line ) {
				var q = qtyIn( pkg, line.id );
				if ( q > 0 ) {
					items.push( line.id + ':' + q );
				}
			} );
			if ( ! items.length ) {
				return; /* An empty package is not saved. */
			}
			out.push( [ pkg.shipment_id, pkg.package_id, fmt3( pkg.length ), fmt3( pkg.width ), fmt3( pkg.height ), fmt3( pkg.weight ), items.join( ',' ) ].join( '|' ) );
		} );
		return out.join( ';' );
	}

	function isDirty() {
		return snapshot() !== ED.initial;
	}

	function problems() {
		var res = { under: [], over: [], lockedOver: [], units: 0, left: 0, extra: 0 };
		$.each( ED.lines, function( i, line ) {
			var left = leftFor( line );
			res.units += line.qty;
			if ( left > 0 ) {
				res.under.push( { line: line, n: left } );
				res.left += left;
			} else if ( left < 0 ) {
				res.over.push( { line: line, n: -left } );
				res.extra -= left;
			}
			if ( packedIn( line.id, true ) > line.qty ) {
				res.lockedOver.push( line );
			}
		} );
		res.ok = ! res.under.length && ! res.over.length;
		return res;
	}

	function loadEditor() {
		var data = blockData();
		ED.seq = 0;
		ED.focus = null;
		ED.saving = false;
		ED.state = '';
		ED.planned = !! data.planned;
		ED.orderId = data.order_id || orderId();
		ED.lines = $.map( data.lines || [], function( line ) {
			return {
				id: String( line.id ),
				title: String( line.title || '' ),
				sku: String( line.sku || '' ),
				detail: String( line.detail || '' ),
				qty: parseInt( line.qty, 10 ) || 0,
				weight: num( line.weight )
			};
		} );
		ED.pkgs = $.map( data.packages || [], function( raw ) {
			var pkg = {
				uid: uid(),
				shipment_id: parseInt( raw.shipment_id, 10 ) || 0,
				package_id: parseInt( raw.package_id, 10 ) || 0,
				package_name: String( raw.package_name || '' ),
				length: num( raw.length ),
				width: num( raw.width ),
				height: num( raw.height ),
				weight: num( raw.weight ),
				auto: false,
				items: {},
				locked: !! raw.locked,
				label: String( raw.label || '' ),
				tracking: String( raw.tracking || '' )
			};
			$.each( raw.items || {}, function( id, q ) {
				q = parseInt( q, 10 ) || 0;
				/* An editable package keeps only this order's shippable lines ( the save drops anything else too ). */
				if ( q > 0 && ( pkg.locked || lineOf( id ) ) ) {
					pkg.items[ String( id ) ] = q;
				}
			} );
			if ( ! pkg.locked ) {
				/* A box removed from the library since: a custom size, as saved. */
				if ( pkg.package_id && ! findBox( pkg.package_id ) ) {
					pkg.package_id = 0;
				}
				/* The saved weight is the worked-out one ( or none ): keep working it out. */
				pkg.auto = pkg.weight <= 0 || Math.abs( pkg.weight - autoWeight( pkg ) ) < 0.0015;
				syncWeight( pkg );
			}
			return pkg;
		} );
		ED.initial = snapshot();
	}

	/* ---------- drawing ---------- */

	function nameEl( line ) {
		var $n = el( 'div', 'ecpk-ed-name' ).append( el( 'span', 'ecpk-ed-title', line.title ) );
		var bits = [];
		if ( line.sku ) {
			bits.push( line.sku );
		}
		if ( line.detail ) {
			bits.push( line.detail );
		}
		if ( num( line.weight ) > 0 ) {
			bits.push( sp( t( 'ed_each', '%s each' ), weightText( line.weight ) ) );
		}
		if ( bits.length ) {
			$n.append( el( 'span', 'ecpk-ed-sub', bits.join( ' · ' ) ) );
		}
		return $n;
	}

	function menuButton( label, act, key, aria ) {
		var $b = el( 'button', 'ecv2-btn ecv2-btn-sm ecpk-menu-btn' ).attr( { type: 'button', 'data-act': act, 'data-key': key, 'aria-haspopup': 'menu', 'aria-expanded': 'false' } );
		if ( aria ) {
			$b.attr( 'aria-label', aria );
		}
		return $b.append( el( 'span', '', label ), icon( 'arrow-down-alt2' ) );
	}

	function introEl() {
		var $wrap = el( 'div', 'ecpk-ed-intro' );
		if ( ED.planned ) {
			$wrap.append( el( 'p', 'ecpk-ed-note is-planned' ).append( icon( 'lightbulb' ), el( 'span', '', t( 'ed_planned', 'These packages were worked out from your box settings and are not saved yet.' ) ) ) );
		}
		var lead = t( 'ed_intro', 'Choose a box for each package and what goes in it. Every item goes in exactly one package.' );
		if ( editables().length < ED.pkgs.length ) {
			lead += ' ' + t( 'ed_locked_intro', 'Packages that already have a label keep their box and items.' );
		}
		return $wrap.append( el( 'p', 'ecpk-ed-lead', lead ) );
	}

	/* Units in no package, units placed twice, and labelled packages holding more than the order has now. */
	function alertsEl( pr ) {
		if ( ! pr.under.length && ! pr.over.length && ! pr.lockedOver.length ) {
			return null;
		}
		var $box = el( 'div', 'ecpk-ed-alerts' );
		if ( pr.under.length ) {
			var $tray = el( 'section', 'ecpk-ed-tray' ).attr( 'aria-labelledby', 'ecpk_ed_tray_h' );
			var $head = el( 'div', 'ecpk-ed-tray-head' ).append( icon( 'warning' ), el( 'h4', '', t( 'ed_tray', 'Not in a package yet' ) ).attr( { id: 'ecpk_ed_tray_h', tabindex: '-1', 'data-key': 'tray-head' } ) );
			if ( pr.under.length > 1 ) {
				$head.append( menuButton( t( 'ed_add_everything', 'Pack all into' ), 'menu-all', 'tray-all' ) );
			}
			var $rows = el( 'ul', 'ecpk-ed-rows' );
			$.each( pr.under, function( i, u ) {
				$rows.append( el( 'li', 'ecpk-ed-row' ).attr( 'data-line', u.line.id ).append(
					nameEl( u.line ),
					el( 'span', 'ecpk-ed-left', sp( t( 'ed_left', '%d left' ), u.n ) ),
					menuButton( t( 'ed_add', 'Add to' ), 'menu-add', 'tray-' + u.line.id, sp( t( 'ed_add_label', 'Add %s to a package' ), u.line.title ) )
				) );
			} );
			$box.append( $tray.append( $head, $rows ) );
		}
		if ( pr.over.length ) {
			var $over = el( 'section', 'ecpk-ed-tray is-over' ).attr( 'aria-labelledby', 'ecpk_ed_over_h' );
			$over.append( el( 'div', 'ecpk-ed-tray-head' ).append( icon( 'dismiss' ), el( 'h4', '', t( 'ed_over_title', 'More than the order has' ) ).attr( { id: 'ecpk_ed_over_h', tabindex: '-1', 'data-key': 'over-head' } ) ) );
			var $orows = el( 'ul', 'ecpk-ed-rows' );
			$.each( pr.over, function( i, o ) {
				$orows.append( el( 'li', 'ecpk-ed-row' ).attr( 'data-line', o.line.id ).append(
					nameEl( o.line ),
					el( 'span', 'ecpk-ed-left', sp( t( 'ed_over_row', '%d too many' ), o.n ) ),
					el( 'button', 'ecv2-btn ecv2-btn-sm' ).attr( { type: 'button', 'data-act': 'trim', 'data-key': 'trim-' + o.line.id } ).text( t( 'ed_trim', 'Take out the extra' ) )
				) );
			} );
			$box.append( $over.append( $orows ) );
		}
		$.each( pr.lockedOver, function( i, line ) {
			$box.append( el( 'p', 'ecpk-ed-note is-info' ).append( icon( 'info-outline' ), el( 'span', '', sp( t( 'ed_locked_over', 'Packages with a label hold more %s than the order has now.' ), line.title ) ) ) );
		} );
		return $box;
	}

	function boxChoices( pkg ) {
		return $.grep( boxes, function( box ) {
			return !! parseInt( box.is_active, 10 ) || parseInt( box.package_id, 10 ) === parseInt( pkg.package_id, 10 );
		} );
	}

	function fillWeightNote( $note, pkg ) {
		$note.empty();
		if ( pkg.auto ) {
			$note.append( el( 'span', '', ( unitsIn( pkg ) > 0 && contentsOf( pkg ) <= 0 ) ? t( 'ed_w_none', 'These items have no weight saved. Enter the packed weight.' ) : t( 'ed_w_auto', 'Worked out from the items and the box.' ) ) );
		} else {
			$note.append( el( 'span', '', t( 'ed_w_manual', 'Entered by you.' ) ) );
			var auto = autoWeight( pkg );
			if ( auto > 0 && Math.abs( auto - num( pkg.weight ) ) > 0.0005 ) {
				$note.append( ' ', el( 'button', 'ecpk-linkbtn' ).attr( { type: 'button', 'data-act': 'weight-auto', 'data-key': 'wa-' + pkg.uid } ).text( sp( t( 'ed_w_reset', 'Use %s' ), weightText( auto ) ) ) );
			}
		}
		var box = findBox( pkg.package_id );
		if ( box && num( box.max_weight ) > 0 && num( pkg.weight ) > num( box.max_weight ) ) {
			$note.append( el( 'span', 'ecpk-ed-warn' ).append( icon( 'warning' ), document.createTextNode( ' ' + sp( t( 'ed_w_over', 'Heavier than this box holds ( %s ).' ), weightText( box.max_weight ) ) ) ) );
		}
	}

	function boxEl( pkg ) {
		var $wrap = el( 'div', 'ecpk-ed-pkg-box' );
		var box = findBox( pkg.package_id );
		var $left = el( 'div', 'ecpk-ed-col' );
		var choices = boxChoices( pkg );
		if ( choices.length ) {
			var selId = 'ecpk_ed_box_' + pkg.uid;
			var $sel = el( 'select', 'ecv2-select ecpk-ed-select' ).attr( { id: selId, 'data-act': 'box', 'data-key': 'box-' + pkg.uid } );
			$.each( choices, function( i, choice ) {
				var id = parseInt( choice.package_id, 10 );
				var label = String( choice.label );
				var size = sizeText( choice.length, choice.width, choice.height );
				if ( size && -1 === label.indexOf( size ) ) {
					label += ' · ' + size;
				}
				if ( ! parseInt( choice.is_active, 10 ) ) {
					label = sp( t( 'ed_not_in_use', '%s ( not in use )' ), label );
				}
				$sel.append( $( '<option></option>' ).attr( 'value', id ).text( label ).prop( 'selected', !! box && id === parseInt( box.package_id, 10 ) ) );
			} );
			$sel.append( $( '<option value="0"></option>' ).text( t( 'custom', 'Custom size' ) ).prop( 'selected', ! box ) );
			$left.append( el( 'label', 'ecpk-ed-label', t( 'ed_box', 'Box' ) ).attr( 'for', selId ), $sel );
		}
		if ( box ) {
			var bits = [ sp( t( 'ed_inside', 'Inside %s' ), sizeText( box.length, box.width, box.height ) || '—' ) ];
			if ( num( box.box_weight ) > 0 ) {
				bits.push( sp( t( 'ed_box_weighs', 'box weighs %s' ), weightText( box.box_weight ) ) );
			}
			if ( num( box.max_weight ) > 0 ) {
				bits.push( sp( t( 'ed_holds', 'holds up to %s' ), weightText( box.max_weight ) ) );
			}
			$left.append( el( 'p', 'ecpk-ed-hint', bits.join( ' · ' ) ) );
		} else {
			var $dims = el( 'fieldset', 'ecpk-ed-dims' ).append( el( 'legend', 'ecpk-ed-label', sp( t( 'ed_size', 'Size ( %s )' ), V.units.dim ) ) );
			var $row = el( 'div', 'ecpk-ed-dims-row' );
			$.each( [ 'length', 'width', 'height' ], function( i, side ) {
				if ( i ) {
					$row.append( el( 'span', 'ecpk-ed-times', '×' ).attr( 'aria-hidden', 'true' ) );
				}
				$row.append( el( 'label', 'ecpk-ed-dim' ).append(
					el( 'span', 'ecpk-ed-dim-label', t( side, side ) ),
					$( '<input type="number" min="0" step="any" inputmode="decimal" class="ecv2-input">' ).attr( { 'data-act': 'dim', 'data-dim': side, 'data-key': side + '-' + pkg.uid } ).val( num( pkg[ side ] ) > 0 ? fmt3( pkg[ side ] ) : '' )
				) );
			} );
			$left.append( $dims.append( $row ) );
		}
		var wid = 'ecpk_ed_w_' + pkg.uid;
		var $right = el( 'div', 'ecpk-ed-col ecpk-ed-col-weight' );
		$right.append( el( 'label', 'ecpk-ed-label', sp( t( 'ed_weight', 'Weight ( %s )' ), V.units.weight ) ).attr( 'for', wid ) );
		$right.append( $( '<input type="number" min="0" step="any" inputmode="decimal" class="ecv2-input ecpk-ed-weight" placeholder="0">' ).attr( { id: wid, 'data-act': 'weight', 'data-key': 'w-' + pkg.uid, 'aria-describedby': 'ecpk_ed_wn_' + pkg.uid } ).val( num( pkg.weight ) > 0 ? fmt3( pkg.weight ) : '' ) );
		var $note = el( 'div', 'ecpk-ed-wnote' ).attr( { id: 'ecpk_ed_wn_' + pkg.uid, 'data-wnote': pkg.uid } );
		fillWeightNote( $note, pkg );
		$right.append( $note );
		return $wrap.append( $left, $right );
	}

	function itemsEl( pkg ) {
		var $sec = el( 'div', 'ecpk-ed-items' );
		var rows = $.grep( ED.lines, function( line ) { return qtyIn( pkg, line.id ) > 0; } );
		if ( ! rows.length ) {
			return $sec.append( el( 'p', 'ecpk-ed-empty', t( 'ed_empty', 'Nothing in this package yet. Move items here, or remove it: empty packages are not saved.' ) ) );
		}
		var n = $.inArray( pkg, ED.pkgs ) + 1;
		var $ul = el( 'ul', 'ecpk-ed-rows' ).attr( 'aria-label', t( 'ed_items', 'Items' ) + ': ' + pkgTitle( pkg ) );
		$.each( rows, function( i, line ) {
			var q = qtyIn( pkg, line.id );
			var left = leftFor( line );
			var max = q + Math.max( 0, left );
			var key = pkg.uid + '-' + line.id;
			var $step = el( 'div', 'ecpk-step' ).attr( { role: 'group', 'aria-label': sp( t( 'ed_qty_group', 'Quantity of %1$s in package %2$d' ), line.title, n ) } ).append(
				el( 'button', 'ecpk-step-btn', '−' ).attr( { type: 'button', 'data-act': 'dec', 'data-key': 'dec-' + key, 'aria-label': sp( t( 'ed_fewer', 'One fewer %s' ), line.title ) } ),
				$( '<input type="number" class="ecpk-step-input" min="0" step="1" inputmode="numeric">' ).attr( { max: max, 'data-act': 'qty', 'data-key': 'qty-' + key, 'aria-label': t( 'ed_qty', 'Quantity' ) } ).val( q ),
				el( 'button', 'ecpk-step-btn', '+' ).attr( { type: 'button', 'data-act': 'inc', 'data-key': 'inc-' + key, 'aria-label': sp( t( 'ed_more', 'One more %s' ), line.title ) } ).prop( 'disabled', q >= max )
			);
			var $qty = el( 'div', 'ecpk-ed-qty' ).append( $step );
			if ( line.qty > 1 ) {
				$qty.append( el( 'span', 'ecpk-ed-of', sp( t( 'ed_of', 'of %d' ), line.qty ) ) );
			}
			$ul.append( el( 'li', 'ecpk-ed-row' ).attr( 'data-line', line.id ).toggleClass( 'is-over', left < 0 ).append(
				nameEl( line ),
				$qty,
				menuButton( t( 'ed_move', 'Move' ), 'menu-move', 'mv-' + key, sp( t( 'ed_move_label', 'Move %s to another package' ), line.title ) )
			) );
		} );
		return $sec.append( $ul );
	}

	function summaryText( pkg ) {
		var bits = [ itemsText( unitsIn( pkg ) ) ];
		if ( num( pkg.weight ) > 0 ) {
			bits.push( weightText( pkg.weight ) );
		}
		return bits.join( ' · ' );
	}

	function removable( pkg ) {
		return editables().length > 1 || 0 === unitsIn( pkg );
	}

	function cardEl( pkg ) {
		var n = $.inArray( pkg, ED.pkgs ) + 1;
		var $card = el( 'section', 'ecpk-ed-pkg' ).toggleClass( 'is-locked', pkg.locked ).attr( { 'data-uid': pkg.uid, 'aria-labelledby': 'ecpk_ed_h_' + pkg.uid } );
		var $head = el( 'div', 'ecpk-ed-pkg-head' );
		$head.append( el( 'span', 'ecpk-ed-pkg-icon' ).append( icon( pkg.locked ? 'lock' : 'archive' ) ) );
		$head.append( el( 'div', 'ecpk-ed-pkg-titles' ).append(
			el( 'h4', '', pkgTitle( pkg ) ).attr( { id: 'ecpk_ed_h_' + pkg.uid, tabindex: '-1', 'data-key': 'head-' + pkg.uid } ),
			el( 'span', 'ecpk-ed-pkg-sum', summaryText( pkg ) ).attr( 'data-sum', pkg.uid )
		) );
		if ( pkg.locked ) {
			if ( pkg.label ) {
				$head.append( el( 'span', 'ecpk-chip ecpk-chip-blue', pkg.label ) );
			}
			$card.append( $head, el( 'p', 'ecpk-ed-locked-note', t( 'ed_locked_note', 'This package has a label, so its box and items stay as they are.' ) + ( pkg.tracking ? ' · ' + pkg.tracking : '' ) ) );
			var $ro = el( 'ul', 'ecpk-ed-readonly' );
			$.each( pkg.items, function( id, q ) {
				var line = lineOf( id );
				$ro.append( el( 'li' ).append( el( 'span', 'ecpk-pkg-qty', q + '×' ), document.createTextNode( ' ' + ( line ? line.title : '#' + id ) ) ) );
			} );
			return $card.append( $ro );
		}
		if ( removable( pkg ) ) {
			$head.append( el( 'button', 'ecpk-icon-btn is-danger' ).attr( { type: 'button', 'data-act': 'remove-pkg', 'data-key': 'rm-' + pkg.uid, 'aria-label': sp( t( 'ed_remove_pkg', 'Remove package %d' ), n ), title: sp( t( 'ed_remove_pkg', 'Remove package %d' ), n ) } ).append( icon( 'trash' ) ) );
		}
		return $card.append( $head, boxEl( pkg ), itemsEl( pkg ) );
	}

	function footer( pr ) {
		pr = pr || problems();
		var dirty = isDirty();
		var state = pr.over.length ? 'over' : ( pr.under.length ? 'under' : 'ok' );
		$( '#ecpk_edit_dirty' ).prop( 'hidden', ! dirty );
		var $status = $( '#ecpk_edit_status' ).removeClass( 'is-ok is-warn is-bad is-muted' ).empty();
		var text;
		if ( 'over' === state ) {
			text = sp( t( 'ed_status_over', 'Packed more than once: %s.' ), itemsText( pr.extra ) );
			$status.addClass( 'is-bad' ).append( icon( 'dismiss' ), el( 'span', '', text ) );
		} else if ( 'under' === state ) {
			text = sp( t( 'ed_status_under', 'Not in a package yet: %s.' ), itemsText( pr.left ) );
			$status.addClass( 'is-warn' ).append( icon( 'warning' ), el( 'span', '', text ) );
		} else {
			text = t( 'ed_status_ok', 'Every item is packed.' );
			if ( ! dirty && ! ED.planned ) {
				$status.addClass( 'is-muted' ).append( icon( 'yes' ), el( 'span', '', text + ' ' + t( 'ed_status_same', 'No changes yet.' ) ) );
			} else {
				$status.addClass( 'is-ok' ).append( icon( 'yes-alt' ), el( 'span', '', text ) );
			}
		}
		/* Say so when packing turns complete or incomplete ( not on every change ). */
		if ( ED.state && ED.state !== state ) {
			announce( text );
		}
		ED.state = state;
		var $save = $( '#ecpk_edit_save' );
		$save.prop( 'disabled', ED.saving || ( ! dirty && ! ED.planned ) ).attr( 'aria-disabled', pr.ok ? 'false' : 'true' ).toggleClass( 'is-blocked', ! pr.ok );
		if ( ! pr.ok ) {
			$save.attr( 'title', t( 'ed_fix_first', 'Put every item in exactly one package to save.' ) );
		} else {
			$save.removeAttr( 'title' );
		}
	}

	function announce( msg ) {
		var $live = $( '#ecpk_edit_live' ).text( '' );
		setTimeout( function() { $live.text( msg ); }, 60 );
	}

	function render() {
		var $root = $( '#ecpk_edit_packages' );
		if ( ! $root.length ) {
			return;
		}
		var active = document.activeElement;
		var key = ( active && $root[ 0 ].contains( active ) ) ? active.getAttribute( 'data-key' ) : null;
		closeMenu( false );
		var pr = problems();
		$root.empty().append( introEl() );
		var $alerts = alertsEl( pr );
		if ( $alerts ) {
			$root.append( $alerts );
		}
		var $list = el( 'div', 'ecpk-ed-list' );
		$.each( ED.pkgs, function( i, pkg ) {
			$list.append( cardEl( pkg ) );
		} );
		$root.append( $list );
		$root.append( el( 'button', 'ecpk-ed-add' ).attr( { type: 'button', 'data-act': 'add-pkg', 'data-key': 'add-pkg' } ).append( icon( 'plus-alt2' ), el( 'span', '', t( 'ed_add_pkg', 'Add package' ) ) ) );
		if ( ! boxes.length ) {
			var $hint = el( 'p', 'ecpk-ed-note is-info' ).append( icon( 'info-outline' ), el( 'span', '', t( 'ed_no_boxes', 'Save the boxes you ship in under Settings › Shipping to choose them here.' ) + ' ' ) );
			if ( V.boxes_url ) {
				$hint.children( 'span' ).last().append( el( 'a', '', t( 'ed_boxes_link', 'Set up boxes' ) ).attr( { href: V.boxes_url, target: '_blank', rel: 'noopener noreferrer' } ) );
			}
			$root.append( $hint );
		}
		footer( pr );
		/* Focus goes where the action asked, else back to the control that had it ( found by its data-key ). */
		var wants = ED.focus ? [].concat( ED.focus ) : ( key ? [ key ] : [] );
		ED.focus = null;
		var i;
		for ( i = 0; i < wants.length; i++ ) {
			var target = wants[ i ] ? $root.find( '[data-key="' + wants[ i ] + '"]' )[ 0 ] : null;
			if ( target && ! target.disabled ) {
				target.focus();
				break;
			}
		}
	}

	/* After typing ( a quantity, the weight ): redraw what depends on it without redrawing the cards, so the field keeps
	   its caret and a click that ends the typing still lands. */
	function refresh() {
		var $root = $( '#ecpk_edit_packages' );
		var pr = problems();
		var $old = $root.children( '.ecpk-ed-alerts' );
		var $alerts = alertsEl( pr );
		if ( $old.length && $alerts ) {
			$old.replaceWith( $alerts );
		} else if ( $old.length ) {
			$old.remove();
		} else if ( $alerts ) {
			$root.children( '.ecpk-ed-intro' ).after( $alerts );
		}
		$.each( editables(), function( i, pkg ) {
			var $card = $root.children( '.ecpk-ed-list' ).children( '[data-uid="' + pkg.uid + '"]' );
			$card.find( '[data-sum]' ).text( summaryText( pkg ) );
			var $w = $card.find( 'input[data-act="weight"]' );
			if ( pkg.auto && $w[ 0 ] !== document.activeElement ) {
				$w.val( num( pkg.weight ) > 0 ? fmt3( pkg.weight ) : '' );
			}
			fillWeightNote( $card.find( '[data-wnote]' ), pkg );
			$card.find( '.ecpk-ed-items .ecpk-ed-row' ).each( function() {
				var line = lineOf( $( this ).attr( 'data-line' ) );
				if ( ! line ) {
					return;
				}
				var q = qtyIn( pkg, line.id );
				var left = leftFor( line );
				var max = q + Math.max( 0, left );
				$( this ).toggleClass( 'is-over', left < 0 );
				$( this ).find( 'input[data-act="qty"]' ).attr( 'max', max );
				$( this ).find( '[data-act="inc"]' ).prop( 'disabled', q >= max );
				$( this ).find( '[data-act="dec"]' ).prop( 'disabled', q <= 0 );
			} );
		} );
		footer( pr );
	}

	/* ---------- changes ---------- */

	function setQty( pkg, id, value, focus ) {
		var line = lineOf( id );
		if ( ! pkg || ! line ) {
			return;
		}
		var q = qtyIn( pkg, id );
		var max = q + Math.max( 0, leftFor( line ) );
		value = Math.max( 0, Math.min( max, parseInt( value, 10 ) || 0 ) );
		addUnits( pkg, id, value - q );
		syncWeight( pkg );
		ED.focus = focus;
		render();
		if ( 0 === value && q > 0 ) {
			announce( sp( t( 'ed_taken_out', '%s is not in a package now.' ), q + ' × ' + line.title ) );
		}
	}

	/* n units of a line from one package ( null: from the ones in no package ) to another ( null: a new package ). */
	function moveUnits( from, id, n, to ) {
		var line = lineOf( id );
		if ( from ) {
			n = Math.min( n, qtyIn( from, id ) );
		}
		if ( ! line || n <= 0 ) {
			return;
		}
		if ( ! to ) {
			to = newPackage();
			ED.pkgs.push( to );
		}
		if ( from ) {
			addUnits( from, id, -n );
			syncWeight( from );
		}
		addUnits( to, id, n );
		syncWeight( to );
		return { line: line, n: n, to: to };
	}

	function moveFromPackage( from, id, n, to ) {
		var done = moveUnits( from, id, n, to );
		if ( ! done ) {
			return;
		}
		/* Part of the line stays: stay on its Move button; all of it went: follow it. */
		ED.focus = qtyIn( from, id ) > 0 ? [ 'mv-' + from.uid + '-' + id ] : [ 'mv-' + done.to.uid + '-' + id, 'head-' + done.to.uid ];
		render();
		announce( sp( t( 'ed_moved', 'Moved %1$s to %2$s.' ), done.n + ' × ' + done.line.title, pkgTitle( done.to ) ) );
	}

	function addFromTray( id, n, to ) {
		var under = $.map( problems().under, function( u ) { return u.line.id; } );
		var at = $.inArray( String( id ), under );
		var done = moveUnits( null, id, n, to );
		if ( ! done ) {
			return;
		}
		ED.focus = [ 'tray-' + id, 'tray-' + ( under[ at + 1 ] || '' ), 'tray-' + ( under[ at - 1 ] || '' ), 'mv-' + done.to.uid + '-' + id, 'head-' + done.to.uid ];
		render();
		announce( sp( t( 'ed_moved', 'Moved %1$s to %2$s.' ), done.n + ' × ' + done.line.title, pkgTitle( done.to ) ) );
	}

	function packAll( to ) {
		if ( ! to ) {
			to = newPackage();
			ED.pkgs.push( to );
		}
		$.each( problems().under, function( i, u ) {
			moveUnits( null, u.line.id, u.n, to );
		} );
		ED.focus = [ 'head-' + to.uid ];
		render();
		announce( sp( t( 'ed_moved', 'Moved %1$s to %2$s.' ), itemsText( unitsIn( to ) ), pkgTitle( to ) ) );
	}

	function takeOut( pkg, id ) {
		var line = lineOf( id );
		var q = qtyIn( pkg, id );
		if ( ! line || ! q ) {
			return;
		}
		addUnits( pkg, id, -q );
		syncWeight( pkg );
		ED.focus = [ 'tray-' + id, 'tray-head', 'head-' + pkg.uid ];
		render();
		announce( sp( t( 'ed_taken_out', '%s is not in a package now.' ), q + ' × ' + line.title ) );
	}

	/* Extra units come out of the last packages that hold them. */
	function trim( id ) {
		var line = lineOf( id );
		if ( ! line ) {
			return;
		}
		var extra = -leftFor( line );
		var touched = null;
		var list = editables();
		var i;
		for ( i = list.length - 1; i >= 0 && extra > 0; i-- ) {
			var take = Math.min( extra, qtyIn( list[ i ], id ) );
			if ( take > 0 ) {
				addUnits( list[ i ], id, -take );
				syncWeight( list[ i ] );
				extra -= take;
				touched = list[ i ];
			}
		}
		var over = $.map( problems().over, function( o ) { return 'trim-' + o.line.id; } );
		ED.focus = over.concat( touched ? [ 'head-' + touched.uid ] : [], [ 'add-pkg' ] );
		render();
	}

	function addPackage() {
		var pkg = newPackage();
		ED.pkgs.push( pkg );
		ED.focus = [ 'box-' + pkg.uid, 'length-' + pkg.uid, 'head-' + pkg.uid ];
		render();
		announce( sp( t( 'ed_added_pkg', '%s added.' ), pkgTitle( pkg ) ) );
	}

	function removePackage( pkg ) {
		var at = $.inArray( pkg, ED.pkgs );
		if ( at < 0 ) {
			return;
		}
		var title = pkgTitle( pkg );
		ED.pkgs.splice( at, 1 );
		var prev = ED.pkgs[ at - 1 ];
		var next = ED.pkgs[ at ];
		ED.focus = [ prev ? 'head-' + prev.uid : '', next ? 'head-' + next.uid : '', 'tray-head', 'add-pkg' ];
		render();
		announce( sp( t( 'ed_removed_pkg', '%s removed.' ), title ) );
	}

	function setBox( pkg, value ) {
		var box = findBox( value );
		if ( box && parseInt( value, 10 ) > 0 ) {
			pkg.package_id = parseInt( box.package_id, 10 );
			pkg.package_name = String( box.label );
			pkg.length = num( box.length );
			pkg.width = num( box.width );
			pkg.height = num( box.height );
		} else {
			/* Custom size: it starts at the box it was, to adjust. */
			if ( pkg.package_id ) {
				pkg.package_name = '';
			}
			pkg.package_id = 0;
		}
		syncWeight( pkg );
		render();
	}

	/* ---------- Move / Add menus ---------- */

	function menuEntries( $btn ) {
		var act = $btn.attr( 'data-act' );
		var entries = [];
		var pkg = findPkg( $btn.closest( '.ecpk-ed-pkg' ).attr( 'data-uid' ) );
		var id = $btn.closest( '.ecpk-ed-row' ).attr( 'data-line' );
		var line = id ? lineOf( id ) : null;
		var targets = editables();
		if ( 'menu-move' === act && pkg && line ) {
			var q = qtyIn( pkg, id );
			var moveTo = function( to, name ) {
				if ( q > 1 ) {
					entries.push( { text: sp( t( 'ed_move_all_to', 'Move all %1$d to %2$s' ), q, name ), run: function() { moveFromPackage( pkg, id, q, to ); } } );
					entries.push( { text: sp( t( 'ed_move_one_to', 'Move 1 to %s' ), name ), run: function() { moveFromPackage( pkg, id, 1, to ); } } );
				} else {
					entries.push( { text: sp( t( 'ed_move_to', 'Move to %s' ), name ), run: function() { moveFromPackage( pkg, id, 1, to ); } } );
				}
			};
			$.each( targets, function( i, to ) {
				if ( to !== pkg ) {
					moveTo( to, pkgTarget( to ) );
				}
			} );
			moveTo( null, t( 'ed_new_pkg', 'a new package' ) );
			entries.push( { sep: true } );
			entries.push( { text: t( 'ed_take_out', 'Take out of this package' ), danger: true, run: function() { takeOut( pkg, id ); } } );
		} else if ( 'menu-add' === act && line ) {
			var left = leftFor( line );
			var addTo = function( to, name ) {
				if ( left > 1 ) {
					entries.push( { text: sp( t( 'ed_add_all_to', 'Add all %1$d to %2$s' ), left, name ), run: function() { addFromTray( id, left, to ); } } );
					entries.push( { text: sp( t( 'ed_add_one_to', 'Add 1 to %s' ), name ), run: function() { addFromTray( id, 1, to ); } } );
				} else {
					entries.push( { text: sp( t( 'ed_add_to', 'Add to %s' ), name ), run: function() { addFromTray( id, 1, to ); } } );
				}
			};
			$.each( targets, function( i, to ) {
				addTo( to, pkgTarget( to ) );
			} );
			addTo( null, t( 'ed_new_pkg', 'a new package' ) );
		} else if ( 'menu-all' === act ) {
			$.each( targets, function( i, to ) {
				entries.push( { text: pkgTarget( to ), run: function() { packAll( to ); } } );
			} );
			entries.push( { text: t( 'ed_new_pkg', 'a new package' ), run: function() { packAll( null ); } } );
		}
		return entries;
	}

	function openMenu( $btn ) {
		closeMenu( false );
		var entries = menuEntries( $btn );
		if ( ! entries.length ) {
			return;
		}
		var $menu = el( 'div', 'ecpk-menu' ).attr( { role: 'menu', 'aria-label': $btn.attr( 'aria-label' ) || $.trim( $btn.text() ) } );
		$.each( entries, function( i, entry ) {
			if ( entry.sep ) {
				$menu.append( el( 'div', 'ecpk-menu-sep' ).attr( 'role', 'separator' ) );
				return;
			}
			$menu.append( el( 'button', 'ecpk-menu-item' + ( entry.danger ? ' is-danger' : '' ), entry.text ).attr( { type: 'button', role: 'menuitem', tabindex: '-1' } ).on( 'click', function( e ) {
				e.preventDefault();
				closeMenu( true ); /* back on its button; the action then moves focus where it belongs */
				entry.run();
			} ) );
		} );
		/* Inside the dialog ( no transform on it ), so a fixed position is the window's and Tab stays in the dialog. */
		$( '#ecpk_edit_modal' ).append( $menu );
		ED.menu = { $menu: $menu, $btn: $btn };
		$btn.attr( 'aria-expanded', 'true' );
		placeMenu();
		quietFocus( $menu.find( '.ecpk-menu-item' ).first()[ 0 ] );
	}

	function placeMenu() {
		if ( ! ED.menu ) {
			return;
		}
		var r = ED.menu.$btn[ 0 ].getBoundingClientRect();
		var $menu = ED.menu.$menu;
		var w = $menu.outerWidth();
		var h = $menu.outerHeight();
		var vw = window.innerWidth || document.documentElement.clientWidth;
		var vh = window.innerHeight || document.documentElement.clientHeight;
		var left = Math.max( 8, Math.min( r.right - w, vw - w - 8 ) );
		var top = r.bottom + 4;
		if ( top + h > vh - 8 && r.top - h - 4 >= 8 ) {
			top = r.top - h - 4;
		}
		$menu.css( { left: left + 'px', top: Math.max( 8, top ) + 'px' } );
	}

	/* The menu is fixed to the window: focusing an item must not scroll the dialog ( that would close the menu ). */
	function quietFocus( node ) {
		if ( ! node ) {
			return;
		}
		try {
			node.focus( { preventScroll: true } );
		} catch ( err ) {
			node.focus();
		}
	}

	function closeMenu( refocus ) {
		var menu = ED.menu;
		if ( ! menu ) {
			return;
		}
		ED.menu = null;
		menu.$menu.remove();
		menu.$btn.attr( 'aria-expanded', 'false' );
		if ( refocus && document.body.contains( menu.$btn[ 0 ] ) ) {
			menu.$btn[ 0 ].focus();
		}
	}

	/* Keys while a menu is open; true when handled. */
	function menuKey( e ) {
		var $items = ED.menu.$menu.find( '.ecpk-menu-item' );
		var at = $items.index( document.activeElement );
		if ( 'Escape' === e.key || 'Esc' === e.key || 'Tab' === e.key ) {
			e.preventDefault();
			closeMenu( true );
			return true;
		}
		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key || 'Home' === e.key || 'End' === e.key ) {
			e.preventDefault();
			var next = 0;
			if ( 'ArrowDown' === e.key ) {
				next = ( at + 1 ) % $items.length;
			} else if ( 'ArrowUp' === e.key ) {
				next = at < 0 ? $items.length - 1 : ( at - 1 + $items.length ) % $items.length;
			} else if ( 'End' === e.key ) {
				next = $items.length - 1;
			}
			quietFocus( $items.eq( next )[ 0 ] );
			return true;
		}
		return false;
	}

	$( document ).on( 'mousedown touchstart', function( e ) {
		if ( ED.menu && ! $( e.target ).closest( '.ecpk-menu' ).length && e.target !== ED.menu.$btn[ 0 ] && ! $.contains( ED.menu.$btn[ 0 ], e.target ) ) {
			closeMenu( false );
		}
	} );

	$( window ).on( 'resize', function() {
		closeMenu( false );
	} );

	/* ---------- editor events ---------- */

	$( document ).on( 'click', '#ecpk_order [data-ecpk-edit]', function( e ) {
		e.preventDefault();
		loadEditor();
		var units = 0;
		$.each( ED.lines, function( i, line ) {
			units += line.qty;
		} );
		$( '#ecpk_edit_sub' ).text( sp( t( 'ed_sub', 'Order %1$s · %2$s to pack' ), '#' + ED.orderId, itemsText( units ) ) );
		$( '#ecpk_edit_save' ).text( t( 'ed_save', 'Save packages' ) );
		render();
		openModal( $( '#ecpk_edit_modal' ), $( '#ecpk_order_backdrop' ), false );
		$( '#ecpk_edit_body' ).scrollTop( 0 );
		setTimeout( function() { $( '#ecpk_edit_title' ).trigger( 'focus' ); }, 30 );
	} );

	$( document ).on( 'click', '#ecpk_edit_packages [data-act]', function( e ) {
		var $btn = $( this );
		var act = $btn.attr( 'data-act' );
		if ( 'box' === act || 'dim' === act || 'weight' === act || 'qty' === act ) {
			return;
		}
		e.preventDefault();
		var pkg = findPkg( $btn.closest( '.ecpk-ed-pkg' ).attr( 'data-uid' ) );
		var id = $btn.closest( '.ecpk-ed-row' ).attr( 'data-line' );
		var key = pkg ? pkg.uid + '-' + id : '';
		if ( 'menu-move' === act || 'menu-add' === act || 'menu-all' === act ) {
			if ( ED.menu && ED.menu.$btn[ 0 ] === this ) {
				closeMenu( true );
			} else {
				openMenu( $btn );
			}
		} else if ( 'dec' === act && pkg ) {
			setQty( pkg, id, qtyIn( pkg, id ) - 1, [ 'dec-' + key, 'mv-' + key, 'head-' + pkg.uid ] );
		} else if ( 'inc' === act && pkg ) {
			setQty( pkg, id, qtyIn( pkg, id ) + 1, [ 'inc-' + key, 'qty-' + key ] );
		} else if ( 'remove-pkg' === act && pkg ) {
			removePackage( pkg );
		} else if ( 'add-pkg' === act ) {
			addPackage();
		} else if ( 'weight-auto' === act && pkg ) {
			pkg.auto = true;
			syncWeight( pkg );
			ED.focus = [ 'w-' + pkg.uid ];
			render();
		} else if ( 'trim' === act ) {
			trim( $btn.closest( '.ecpk-ed-row' ).attr( 'data-line' ) );
		}
	} );

	$( document ).on( 'keydown', '#ecpk_edit_packages .ecpk-menu-btn', function( e ) {
		if ( 'ArrowDown' === e.key && ! ED.menu ) {
			e.preventDefault();
			openMenu( $( this ) );
		}
	} );

	$( document ).on( 'change', '#ecpk_edit_packages select[data-act="box"]', function() {
		var pkg = findPkg( $( this ).closest( '.ecpk-ed-pkg' ).attr( 'data-uid' ) );
		if ( pkg ) {
			setBox( pkg, $( this ).val() );
		}
	} );

	$( document ).on( 'input', '#ecpk_edit_packages input[data-act]', function() {
		var $in = $( this );
		var act = $in.attr( 'data-act' );
		var pkg = findPkg( $in.closest( '.ecpk-ed-pkg' ).attr( 'data-uid' ) );
		if ( ! pkg ) {
			return;
		}
		var raw = $.trim( String( $in.val() ) );
		if ( 'dim' === act ) {
			pkg[ $in.attr( 'data-dim' ) ] = num( raw );
			footer();
		} else if ( 'weight' === act ) {
			pkg.auto = '' === raw;
			pkg.weight = pkg.auto ? autoWeight( pkg ) : num( raw );
			refresh();
		} else if ( 'qty' === act && '' !== raw ) {
			var id = $in.closest( '.ecpk-ed-row' ).attr( 'data-line' );
			var line = lineOf( id );
			if ( line ) {
				var q = qtyIn( pkg, id );
				var value = Math.max( 0, Math.min( q + Math.max( 0, leftFor( line ) ), Math.floor( num( raw ) ) ) );
				addUnits( pkg, id, value - q );
				syncWeight( pkg );
				refresh();
			}
		}
	} );

	/* Leaving a field shows the value that counts ( a quantity kept within the order, the worked-out weight ). */
	$( document ).on( 'change', '#ecpk_edit_packages input[data-act="qty"], #ecpk_edit_packages input[data-act="weight"]', function() {
		var $in = $( this );
		var pkg = findPkg( $in.closest( '.ecpk-ed-pkg' ).attr( 'data-uid' ) );
		if ( ! pkg ) {
			return;
		}
		if ( 'qty' === $in.attr( 'data-act' ) ) {
			$in.val( qtyIn( pkg, $in.closest( '.ecpk-ed-row' ).attr( 'data-line' ) ) );
		} else if ( pkg.auto ) {
			$in.val( num( pkg.weight ) > 0 ? fmt3( pkg.weight ) : '' );
		}
	} );

	$( document ).on( 'keydown', '#ecpk_edit_packages input', function( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( this ).trigger( 'change' );
		}
	} );

	function showEditorError( msg ) {
		var $error = $( '#ecpk_edit_error' ).text( msg ).prop( 'hidden', false );
		$( '#ecpk_edit_body' ).scrollTop( 0 );
		$error[ 0 ].focus();
	}

	$( document ).on( 'click', '#ecpk_edit_save', function( e ) {
		e.preventDefault();
		if ( ED.saving ) {
			return;
		}
		var pr = problems();
		if ( ! pr.ok ) {
			/* Save explains itself: to the list of what still needs a package. */
			var $to = $( '#ecpk_ed_over_h, #ecpk_ed_tray_h' ).first();
			if ( $to.length ) {
				$to[ 0 ].focus();
			}
			announce( t( 'ed_fix_first', 'Put every item in exactly one package to save.' ) );
			return;
		}
		var packages = [];
		$.each( ED.pkgs, function( i, pkg ) {
			var items = {};
			var count = 0;
			$.each( pkg.items, function( id, q ) {
				q = parseInt( q, 10 ) || 0;
				if ( q > 0 && ( pkg.locked || lineOf( id ) ) ) {
					items[ id ] = q;
					count += q;
				}
			} );
			if ( ! pkg.locked && ! count ) {
				return; /* An empty package is not kept. */
			}
			packages.push( {
				shipment_id: pkg.shipment_id,
				package_id: pkg.package_id,
				package_name: pkg.package_name,
				length: pkg.length,
				width: pkg.width,
				height: pkg.height,
				weight: pkg.weight,
				items: items
			} );
		} );
		var $btn = $( this );
		ED.saving = true;
		$btn.prop( 'disabled', true ).addClass( 'ecv2-btn-busy' ).text( t( 'ed_saving', 'Saving…' ) );
		$( '#ecpk_edit_error' ).prop( 'hidden', true ).text( '' );
		post( { action: 'ecv2_order_packages_save', nonce: V.order_nonce, order_id: orderId(), packages: JSON.stringify( packages ) } ).done( function( r ) {
			if ( r && r.success ) {
				ED.initial = snapshot();
				ED.planned = false;
				ED.saving = false;
				window.ecpk_replace( r.data.html || '' );
				closeModal( $( '#ecpk_edit_modal' ) );
				toast( r.data.message );
			} else {
				showEditorError( ( r && r.data && r.data.message ) ? r.data.message : t( 'error', 'That did not work. Please try again.' ) );
			}
		} ).fail( function( xhr ) {
			showEditorError( ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) ? xhr.responseJSON.data.message : t( 'error', 'That did not work. Please try again.' ) );
		} ).always( function() {
			ED.saving = false;
			$btn.removeClass( 'ecv2-btn-busy' ).text( t( 'ed_save', 'Save packages' ) );
			footer();
		} );
	} );

	function editorRequestClose() {
		var $modal = $( '#ecpk_edit_modal' );
		if ( ED.saving ) {
			return;
		}
		closeMenu( false );
		if ( ! isDirty() ) {
			closeModal( $modal );
			return;
		}
		confirmBox( t( 'ed_discard_title', 'Discard changes?' ), t( 'ed_discard_ask', 'Your changes to the packages have not been saved.' ), t( 'ed_discard', 'Discard changes' ), function() {
			closeModal( $modal );
		}, t( 'ed_keep', 'Keep editing' ) );
	}

	$( function() {
		/* 6.0.2: the dialogs live on body. Settings pages print them inside .ecv2-wrap, a CSS size container, which
		   places fixed children against the page instead of the window ( the box editor opened above the view ). */
		$( '.ecpk-modal, .ecpk-modal-backdrop' ).appendTo( 'body' );
		$( '.ecpk-modal' ).addClass( 'ecv2-layer' );
		$( '#ecpk_edit_body' ).on( 'scroll', function() {
			closeMenu( false );
		} );
		drawBoxes();
	} );
}( jQuery ) );
