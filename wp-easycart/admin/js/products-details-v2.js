/**
 * WP EasyCart Product Editor V2 — front-end controller.
 *
 * Design contract:
 *  - Every save dispatches the EXISTING per-section AJAX endpoints
 *    ( ec_admin_ajax_save_product_details_* ), so all server-side
 *    validation, sanitization, and action/filter hooks keep firing.
 *  - Field IDs are unchanged from the legacy editor, so legacy
 *    products.js helpers ( tier pricing, b2b pricing, manufacturer
 *    creation, option set selects, etc. ) keep working untouched.
 *  - Only sections with changed fields are saved ( dirty tracking ).
 *
 * Requires: jQuery, wpeasycart_admin_ajax_object ( from legacy enqueue ).
 *
 * @since 6.0.0
 */

/* global jQuery, tinymce, wp, wpeasycart_admin_ajax_object */

/* ===================================================================== */
/* Cart Links quick-create card ( SEO & Marketing tab ). Self-contained:  */
/* talks to the same endpoints as Marketing > Cart Links.                 */
/* ===================================================================== */
if ( typeof window.ecdv2_confirm !== 'function' ) {
	window.ecdv2_confirm = function( opts ) {
		opts = opts || {};
		return new Promise( function( resolve ) {
			var $ = jQuery;
			var $overlay = $( '#ecdv2-confirm-overlay' );
			if ( ! $overlay.length ) {
				$overlay = $(
					'<div class="ecv2-modal-overlay ecdv2-confirm-overlay" id="ecdv2-confirm-overlay" style="display:none;">' +
						'<div class="ecv2-modal ecdv2-confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="ecdv2-confirm-title" aria-describedby="ecdv2-confirm-message">' +
							'<div class="ecdv2-confirm-body">' +
								'<span class="dashicons dashicons-warning ecdv2-confirm-icon"></span>' +
								'<div><h3 id="ecdv2-confirm-title"></h3><p id="ecdv2-confirm-message"></p></div>' +
							'</div>' +
							'<div class="ecdv2-confirm-actions">' +
								'<button type="button" class="ecv2-btn ecv2-btn-ghost" data-confirm="0"></button>' +
								'<button type="button" class="ecv2-btn ecv2-btn-primary" data-confirm="1"></button>' +
							'</div>' +
						'</div>' +
					'</div>'
				).appendTo( document.body );
			}
			var settle = function( ok ) {
				$overlay.fadeOut( 120 );
				$( document ).off( 'keydown.ecdv2confirm' );
				resolve( ok );
			};
			$( '#ecdv2-confirm-title' ).text( opts.title || '' ).toggle( !! opts.title );
			$( '#ecdv2-confirm-message' ).text( opts.message || '' );
			/* 6.0.1: opts.details ( one line per consequence, plain text ) and opts.note ( small print under them ). The
			   list is added on first use, so it also works on an overlay the PRO copy of this function built. */
			var $details = $( '#ecdv2-confirm-details' );
			if ( ! $details.length ) {
				$details = $( '<ul id="ecdv2-confirm-details" class="ecdv2-confirm-details"></ul><p id="ecdv2-confirm-note" class="ecdv2-confirm-note"></p>' ).insertAfter( '#ecdv2-confirm-message' ).first();
			}
			$details.empty().toggle( !! ( opts.details && opts.details.length ) );
			$.each( opts.details || [], function( i, line ) { $( '<li>' ).text( line ).appendTo( $details ); } );
			$( '#ecdv2-confirm-note' ).text( opts.note || '' ).toggle( !! opts.note );
			$overlay.find( '.ecdv2-confirm-modal' ).toggleClass( 'has-details', !! ( opts.details && opts.details.length ) );
			$overlay.find( '[data-confirm="1"]' ).text( opts.confirm_label || 'Continue' ).toggleClass( 'ecv2-btn-danger', !! opts.danger );
			$overlay.find( '[data-confirm="0"]' ).text( opts.cancel_label || 'Cancel' );
			$overlay.find( '[data-confirm]' ).off( 'click' ).on( 'click', function() { settle( $( this ).data( 'confirm' ) === 1 ); } );
			$overlay.off( 'click' ).on( 'click', function( e ) { if ( e.target === this ) { settle( false ); } } );
			$( document ).on( 'keydown.ecdv2confirm', function( e ) { if ( e.key === 'Escape' ) { settle( false ); } } );
			$overlay.fadeIn( 120 );
			setTimeout( function() { try { $overlay.find( '[data-confirm="1"]' ).trigger( 'focus' ); } catch ( e ) {} }, 130 );
		});
	};
}

window.ecdv2_cart_links = ( function( $ ) {
	'use strict';

	function card() { return $( '#ecdv2_cart_links_card' ); }
	function toast( msg, type ) {
		if ( typeof window.ecv2_toast === 'function' ) { window.ecv2_toast( msg, type ); }
		else if ( window.ecdv2 && typeof window.ecdv2.toast === 'function' ) { window.ecdv2.toast( msg, type ); }
		else { window.alert( msg ); }
	}

	/* 6.0.4: the form opens under the empty state or the list; the buttons that open it say whether it is open. */
	function toggle_form( open ) {
		var $form = $( '#ecdv2_cl_quick' );
		open = ( true === open || false === open ) ? open : ! $form.is( ':visible' );
		if ( open ) {
			$form.slideDown( 140, function() {
				$( '#ecdv2_cl_quick_label' ).trigger( 'focus' );
			} );
		} else {
			$form.slideUp( 140 );
		}
		$( '#ecdv2_cl_quick_toggle, #ecdv2_cl_add_another' ).attr( 'aria-expanded', open ? 'true' : 'false' );
	}

	/* 6.0.4: no links = the empty state and its button; links = the list and Create another cart link. */
	function paint_state() {
		var any = $( '#ecdv2_cl_existing .ecdv2-cl-existing-row' ).length > 0;
		$( '#ecdv2_cl_existing' ).toggle( any );
		$( '#ecdv2_cl_empty' ).prop( 'hidden', any );
		$( '#ecdv2_cl_add_another' ).prop( 'hidden', ! any );
	}

	function create() {
		var $card = card();
		var item = {
			product_id: parseInt( $card.data( 'product-id' ), 10 ),
			quantity: Math.max( 1, parseInt( $( '#ecdv2_cl_quick_qty' ).val(), 10 ) || 1 )
		};
		$( '.ecdv2-cl-quick-basic' ).each( function() {
			item[ 'optionitem_id_' + $( this ).data( 'slot' ) ] = parseInt( $( this ).val(), 10 ) || 0;
		} );
		/* Modifier inputs, when PRO mounted them ( same markup contract as
		 * the Marketing drawer ). */
		var mods = [];
		$card.find( '.ecv2-clp-mod' ).each( function() {
			var $mod = $( this );
			var val = $mod.find( '.ecv2-clp-mod-input' ).val();
			if ( '' === val || null === val ) { return; }
			var is_select = $mod.find( 'select' ).length > 0;
			mods.push( {
				option_id: parseInt( $mod.data( 'option-id' ), 10 ),
				optionitem_id: is_select ? ( parseInt( val, 10 ) || 0 ) : 0,
				value: is_select ? '' : val
			} );
		} );
		if ( mods.length ) { item.modifiers = JSON.stringify( mods ); }

		var $btn = $( '#ecdv2_cl_quick_create' ).prop( 'disabled', true );
		$.post( ( window.wpeasycart_admin_ajax_object || {} ).ajax_url || window.ajaxurl, {
			action: 'ecv2_cart_link_save',
			wp_easycart_nonce: $card.data( 'nonce' ),
			cart_link_id: 0,
			link_label: $( '#ecdv2_cl_quick_label' ).val(),
			destination: 'cart',
			clear_cart: 0,
			is_active: 1,
			items: [ item ]
		} ).done( function( response ) {
			$btn.prop( 'disabled', false );
			if ( ! response || ! response.success ) {
				toast( ( response && response.data && response.data.message ) || 'Could not create the link.', 'error' );
				return;
			}
			add_row( response.data, $( '#ecdv2_cl_quick_label' ).val() );
			reset_form();
			toggle_form( false );
			toast( 'Cart link created — copy it from the list above.', 'success' );
		} ).fail( function() {
			$btn.prop( 'disabled', false );
			toast( 'Could not create the link.', 'error' );
		} );
	}

	function copy( el ) {
		var text = $( el ).attr( 'data-url' ) || $( el ).data( 'url' ) || '';
		if ( ! text ) { return; }
		var done = function() { toast( 'Link copied to clipboard.', 'success' ); };
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done, done );
		} else {
			var input = document.createElement( 'input' );
			input.value = text;
			document.body.appendChild( input );
			input.select();
			try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
			document.body.removeChild( input );
		}
	}

	function esc_text( s ) {
		return $( '<span>' ).text( s == null ? '' : s ).html();
	}

	/* Live row matching product-cart-links-card.php markup. A QR button is
	 * included when PRO's row-QR helper is present on the page. */
	function add_row( data, label ) {
		var id = parseInt( data.cart_link_id, 10 );
		var html = '';
		html += '<div class="ecdv2-cl-existing-row" data-cart-link-id="' + id + '">';
		html += '<div class="ecdv2-cl-existing-main">';
		html += '<span class="ecdv2-cl-existing-label">' + ( label ? esc_text( label ) : '(untitled link)' ) + '</span>';
		html += '<span class="ecdv2-cl-existing-meta"><code>' + esc_text( data.token ) + '</code> · <span class="ecdv2-cl-uses">0 uses</span></span>';
		html += '</div>';
		html += '<div class="ecdv2-cl-existing-actions">';
		html += '<button type="button" class="ecv2-btn ecv2-btn-sm" data-url="' + esc_text( data.url ) + '" onclick="ecdv2_cart_links.copy( this ); return false;"><span class="dashicons dashicons-clipboard"></span> Copy</button>';
		if ( typeof window.ecv2_clp_card_row_qr === 'function' ) {
			html += '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-clp-qr" onclick="ecv2_clp_card_row_qr( ' + id + ' ); return false;" title="Download QR code"><span class="dashicons dashicons-smartphone"></span> QR</button>';
		}
		html += '<button type="button" class="ecdv2-cl-row-delete" title="Delete link" onclick="ecdv2_cart_links.remove( this, ' + id + ' ); return false;"><span class="dashicons dashicons-trash"></span></button>';
		html += '</div></div>';
		var $new_row = $( html ).hide();
		$( '#ecdv2_cl_existing' ).show().append( $new_row );
		paint_state();
		$new_row.fadeIn( 160 );
	}

	function reset_form() {
		$( '#ecdv2_cl_quick_label' ).val( '' );
		$( '#ecdv2_cl_quick_qty' ).val( 1 );
		$( '.ecdv2-cl-quick-basic' ).each( function() { this.selectedIndex = 0; } );
		card().find( '.ecv2-clp-mod-input' ).each( function() {
			if ( 'SELECT' === this.tagName ) { this.selectedIndex = 0; } else { this.value = ''; }
		} );
	}

	function remove( el, id ) {
		window.ecdv2_confirm( {
			title: 'Delete cart link?',
			message: 'Anywhere this link is already shared or printed, it will stop working.',
			confirm_label: 'Delete',
			danger: true
		} ).then( function( ok ) {
			if ( ! ok ) { return; }
			$.post( ( window.wpeasycart_admin_ajax_object || {} ).ajax_url || window.ajaxurl, {
				action: 'ecv2_cart_link_delete',
				wp_easycart_nonce: card().data( 'nonce' ),
				cart_link_id: id
			} ).done( function( response ) {
				if ( ! response || ! response.success ) {
					toast( 'Could not delete the link.', 'error' );
					return;
				}
				var $row = $( el ).closest( '.ecdv2-cl-existing-row' );
				$row.fadeOut( 140, function() {
					$row.remove();
					paint_state();
				} );
				toast( 'Cart link deleted.', 'success' );
			} ).fail( function() { toast( 'Could not delete the link.', 'error' ); } );
		} );
	}

	return { toggle_form: toggle_form, create: create, copy: copy, remove: remove };
} )( jQuery );

/* Free Media tab: five image slots. Keep each thumbnail, its Replace/Remove
 * labels and the 'has-image' state in sync with the hidden image1..5 inputs
 * ( ecdv2.media_pick / media_clear trigger 'change' on them ). */
( function( $ ) {
	'use strict';
	$( document ).on( 'change', '.ecdv2-media-slot input[type="hidden"]', function() {
		var $slot = $( this ).closest( '.ecdv2-media-slot' ), url = $.trim( this.value );
		if ( window.ecdv2 && typeof ecdv2.mark_dirty === 'function' ) { ecdv2.mark_dirty( 'images' ); }
		var $thumb = $slot.find( '.ecdv2-media-slot-thumb' );
		if ( url !== '' && ! /^https?:\/\//i.test( url ) && window.ecdv2_media_base ) {
			url = window.ecdv2_media_base.replace( '%d', $slot.data( 'slot' ) ) + url;
		}
		$slot.toggleClass( 'has-image', url !== '' );
		$thumb.css( 'background-image', url !== '' ? 'url(' + url + ')' : '' );
		$slot.find( '.ecdv2-media-link' ).first().text( url !== '' ? ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.replace_image ) || 'Replace' : ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.choose_image ) || 'Choose' );
		$slot.find( '.ecdv2-media-link-remove' ).prop( 'hidden', url === '' );
	} );
	$( document ).on( 'keydown', '.ecdv2-media-slot-thumb', function( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); $( this ).trigger( 'click' ); }
	} );

	/* Free Options tab: choice chips + variation count follow the two selects. */
	var I18N = function( k, d ) { return ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n[ k ] ) || d; };
	function ecdv2_opt_render() {
		var $wrap = $( '#ecdv2_opt_slots' );
		if ( ! $wrap.length ) { return; }
		var counts; try { counts = JSON.parse( $wrap.attr( 'data-counts' ) || '{}' ); } catch ( e ) { counts = {}; }
		var total = 1, used = 0, names = [];
		$wrap.find( '.ecdv2-opt-slot:not(.is-locked)' ).each( function() {
			var $s = $( this ), $sel = $s.find( 'select' ), id = parseInt( $sel.val(), 10 ) || 0;
			$s.toggleClass( 'has-value', id > 0 );
			$s.find( '.ecdv2-opt-slot-rm' ).prop( 'hidden', id === 0 );
			if ( id > 0 ) { used++; total *= ( counts[ id ] || 1 ); names.push( $sel.find( 'option:selected' ).text().replace( /\s*\(\d+\)$/, '' ) ); }
		} );
		var $v = $( '#ecdv2_opt_variations' );
		if ( ! used ) { $v.text( I18N( 'opt_no_sets', 'No option sets — this product sells as a single item.' ) ); }
		else if ( used === 1 ) { $v.text( I18N( 'opt_one_set', '%1 · %2 variations' ).replace( '%1', names[ 0 ] ).replace( '%2', total ) ); }
		else { $v.text( I18N( 'opt_two_sets', '%1 × %2 · %3 variations' ).replace( '%1', names[ 0 ] ).replace( '%2', names[ 1 ] ).replace( '%3', total ) ); }
	}
	window.ecdv2_opt_render = ecdv2_opt_render;
	window.ecdv2_opt_clear = function( name ) {
		$( '#' + name ).val( '0' ).trigger( 'change' );
	};
	$( document ).on( 'change', '#ecdv2_opt_slots select', function() {
		var $sel = $( this ), id = parseInt( $sel.val(), 10 ) || 0, $chips = $( '#' + this.id + '_chips' ).empty();
		// Keep the two slots distinct.
		$( '#ecdv2_opt_slots select' ).not( this ).each( function() { if ( id && parseInt( this.value, 10 ) === id ) { $( this ).val( '0' ); $( '#' + this.id + '_chips' ).empty(); } } );
		ecdv2_opt_render();
		if ( ! id ) { return; }
		$chips.append( $( '<span class="ecdv2-opt-chip is-loading">' ).text( I18N( 'loading', 'Loading…' ) ) );
		$.post( ajaxurl, { action: 'ec_admin_ajax_ecv2_option_items', option_id: id }, function( data ) {
			var r; try { r = JSON.parse( data ); } catch ( e ) { r = null; }
			$chips.empty();
			if ( ! r || ! r.items ) { return; }
			$.each( r.items, function( i, it ) {
				var $c = $( '<span class="ecdv2-opt-chip">' ).text( it.name );
				if ( it.price_display ) { $c.append( ' ' ).append( $( '<em>' ).text( '+' + it.price_display ) ); }
				$chips.append( $c );
			} );
		} );
	} );
	// A set created from the "Create a new option set" panel lands in the first empty slot.
	$( document ).on( 'ecosv2:created', function( e, r ) {
		if ( ! r || ! r.option_id ) { return; }
		/* 6.0.5: a modifier ( the panel's Dropdown / Swatches as add-ons too ) never goes in a variation slot. */
		if ( r.option_type && 'basic-combo' !== r.option_type && 'basic-swatch' !== r.option_type ) { return; }
		var $empty = $( '#ecdv2_opt_slots .ecdv2-opt-slot:not(.is-locked) select' ).filter( function() { return ! parseInt( this.value, 10 ); } ).first();
		if ( ! $empty.length ) { return; }
		if ( ! $empty.find( 'option[value="' + r.option_id + '"]' ).length ) {
			$( '#ecdv2_opt_slots select' ).each( function() { $( this ).append( $( '<option>' ).val( r.option_id ).text( r.option_name + ' (' + ( r.item_count || 0 ) + ')' ) ); } );
		}
		var counts; try { counts = JSON.parse( $( '#ecdv2_opt_slots' ).attr( 'data-counts' ) || '{}' ); } catch ( err ) { counts = {}; }
		counts[ r.option_id ] = r.item_count || 0;
		$( '#ecdv2_opt_slots' ).attr( 'data-counts', JSON.stringify( counts ) );
		$empty.val( String( r.option_id ) ).trigger( 'change' );
		if ( window.ecdv2 && typeof ecdv2.mark_dirty === 'function' ) { ecdv2.mark_dirty( 'options' ); }
	} );
	$( ecdv2_opt_render );

	/* A free install saving a product that still carries PRO media or PRO options loses that data.
	 * Ask once per area, in plain terms, before the save runs. */
	$( function() {
		if ( ! window.ecdv2 || typeof ecdv2.save_all !== 'function' ) { return; }
		var orig = ecdv2.save_all, asked = false, asked_opts = false;
		ecdv2.save_all = function() {
			var $o = $( '#ecdv2_options_legacy_notice' );
			if ( $o.length && $( '.ecdv2-card[data-ecdv2-section="options"]' ).hasClass( 'is-dirty' ) && ! asked_opts ) {
				var ex = parseInt( $o.attr( 'data-extra' ), 10 ) || 0, mods = parseInt( $o.attr( 'data-modifiers' ), 10 ) || 0, vars = parseInt( $o.attr( 'data-variants' ), 10 ) || 0, op = [];
				if ( ex > 0 )   { op.push( ( ex === 1 ? I18N( 'one_extra_set', '1 extra option set' ) : I18N( 'n_extra_sets', '%d extra option sets' ) ).replace( '%d', ex ) ); }
				if ( mods > 0 ) { op.push( ( mods === 1 ? I18N( 'one_modifier', '1 modifier' ) : I18N( 'n_modifiers', '%d modifiers' ) ).replace( '%d', mods ) ); }
				if ( vars > 0 ) { op.push( I18N( 'variant_stock', 'stock tracking on %d variations' ).replace( '%d', vars ) ); }
				var omsg = I18N( 'lossy_options_confirm', 'Saving will remove %s from this product and keep only the first two option sets. This cannot be undone without PRO. Save anyway?' ).replace( '%s', op.length > 2 ? op.slice( 0, -1 ).join( ', ' ) + ' and ' + op[ op.length - 1 ] : op.join( ' and ' ) );
				if ( ! window.confirm( omsg ) ) { return false; }
				asked_opts = true;
			}
			var $n = $( '#ecdv2_media_legacy_notice' );
			var images_dirty = $( '.ecdv2-card[data-ecdv2-section="images"]' ).hasClass( 'is-dirty' );
			if ( $n.length && images_dirty && ! asked ) {
				var extra = parseInt( $n.attr( 'data-extra' ), 10 ) || 0, sets = $n.attr( 'data-sets' ) === '1', gallery = parseInt( $n.attr( 'data-gallery' ), 10 ) || 0, parts = [];
				if ( gallery > 0 ) { parts.push( ( ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.n_image_gallery ) || 'the %d-image gallery' ).replace( '%d', gallery ) ); }
				if ( extra > 0 ) { parts.push( extra === 1 ? ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.one_extra_image ) || '1 extra image' : ( ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.n_extra_images ) || '%d extra images' ).replace( '%d', extra ) ); }
				if ( sets ) { parts.push( ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.option_image_sets ) || 'the per-option image sets' ); }
				var msg = ( ( window.wpeasycart_ecdv2_i18n && wpeasycart_ecdv2_i18n.lossy_media_confirm ) || 'Saving will remove %s from this product and keep only the first two images. This cannot be undone without PRO. Save anyway?' ).replace( '%s', parts.length > 2 ? parts.slice( 0, -1 ).join( ', ' ) + ' and ' + parts[ parts.length - 1 ] : parts.join( ' and ' ) );
				if ( ! window.confirm( msg ) ) { return false; }
				asked = true;
			}
			return orig.apply( this, arguments );
		};
	} );
} )( jQuery );

window.ecdv2 = ( function( $ ) {

	/* Download the product QR (data-URI img, canvas, or remote img) as a PNG. */
	function download_qr( btn ) {
		var $box = $( btn ).closest( '.ecdv2-menu-qr' );
		var filename = $box.attr( 'data-qr-filename' ) || 'product-qr.png';
		var canvas = $box.find( 'canvas' ).get( 0 );
		var img = $box.find( 'img' ).get( 0 );
		var trigger = function( href, revoke ) {
			var a = document.createElement( 'a' );
			a.href = href;
			a.download = filename;
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			if ( revoke ) { setTimeout( function() { URL.revokeObjectURL( href ); }, 4000 ); }
		};
		if ( canvas ) {
			trigger( canvas.toDataURL( 'image/png' ), false );
			return;
		}
		if ( ! img || ! img.src ) { return; }
		if ( img.src.indexOf( 'data:' ) === 0 ) {
			trigger( img.src, false );
			return;
		}
		/* Remote src: fetch as a blob; if blocked, open in a tab as a last resort. */
		fetch( img.src ).then( function( r ) { return r.blob(); } ).then( function( blob ) {
			trigger( URL.createObjectURL( blob ), true );
		}).catch( function() {
			window.open( img.src, '_blank' );
		});
	}

	'use strict';

	/* ------------------------------------------------------------------ */
	/* State                                                                */
	/* ------------------------------------------------------------------ */

	var dirty = {};            /* section key => true                       */
	var saving = false;
	var slug_locked = false;
	var slug_touched = false;  /* user manually edited the slug             */
	var search_index = null;
	var activity_loaded = false;
	var media_frames = {};

	var i18n = ( typeof window.wpeasycart_ecdv2_i18n !== 'undefined' ) ? window.wpeasycart_ecdv2_i18n : {};
	function _t( key, fallback ) {
		return ( i18n && i18n[ key ] ) ? i18n[ key ] : fallback;
	}

	/* Section key => save endpoint suffix. Sections sharing an endpoint
	 * ( the split general_options cards ) are deduped at save time.      */
	var endpoint_map = {
		'basic'                        : 'basic',
		/* 6.0.4 ( editor plan Phase 2 ): these used to save the moment they changed; they wait for Save now. */
		'categories'                   : 'categories',
		'price_rules'                  : 'price_rules',
		'reviews'                      : 'general_options',
		'short_description'            : 'short_description',
		'specifications'               : 'specifications',
		'images'                       : 'images',
		'options'                      : 'options',
		'pricing'                      : 'pricing',
		'tax'                          : 'tax',
		'quantities'                   : 'quantities',
		'shipping'                     : 'shipping',
		'packaging'                    : 'packaging',
		'customs'                      : 'customs',
		'general_options'              : 'general_options',
		'general_options_visibility'   : 'general_options',
		'general_options_marketing'    : 'general_options',
		'menus'                        : 'menus',
		'featured_products'            : 'featured_products',
		'tags'                         : 'tags',
		'subscription'                 : 'subscription',
		'downloads'                    : 'downloads',
		'deconetwork'                  : 'deconetwork',
		'seo'                          : 'seo',
		'yoast_seo'                    : 'yoast_seo',
		'search_ai'                    : 'search_ai',
		'googlemerchant'               : 'google_merchant_pro',
		'order_completed_note'         : 'order_completed_note',
		'order_completed_email_note'   : 'order_completed_email_note',
		'order_completed_details_note' : 'order_completed_details_note'
	};

	/* The editor's own sections; register_section() never replaces them. */
	var builtin_sections = $.extend( {}, endpoint_map );

	/* ------------------------------------------------------------------ */
	/* Value helpers ( mirror legacy ec_admin_get_value semantics )         */
	/* ------------------------------------------------------------------ */

	function el( id ) {
		return document.getElementById( id );
	}

	function v( id ) { /* text / select / hidden */
		var node = el( id );
		return node ? $( node ).val() : '';
	}

	function c( id ) { /* checkbox => 1/0; hidden carrier ( gated PRO field ) => its value */
		var node = el( id );
		if ( ! node ) {
			return 0;
		}
		if ( 'checkbox' === node.type ) {
			return node.checked ? 1 : 0;
		}
		return $( node ).val() ? $( node ).val() : 0;
	}

	function ed( id ) { /* wp_editor content: tinymce in visual mode, the textarea in text ( Code ) mode */
		/* 6.0.4: the mode is read from wp_editor's wrapper ( html-active = Code ). Whether the textarea shows says nothing
		 * while its tab or section is hidden, and text typed in Code mode was then read from the visual editor's old copy. */
		/* Until TinyMCE has started, the textarea holds the text ( an editor not yet started answers '' ). */
		var wrap = document.getElementById( 'wp-' + id + '-wrap' );
		var visual = wrap ? ! $( wrap ).hasClass( 'html-active' ) : ! $( el( id ) ).is( ':visible' );
		if ( visual && typeof tinymce !== 'undefined' && tinymce.editors && tinymce.editors[ id ] && tinymce.editors[ id ].initialized ) {
			return tinymce.editors[ id ].getContent();
		}
		return el( id ) ? $( el( id ) ).val() : '';
	}

	function maybe( data, key, value ) { /* only include when the field exists on the page */
		if ( el( key ) ) {
			data[ key ] = value;
		}
		return data;
	}

	/* ------------------------------------------------------------------ */
	/* Payload builders — one per legacy save endpoint                      */
	/* Field lists verified against products.js + wp_easycart_admin_products.php */
	/* ------------------------------------------------------------------ */

	var payloads = {

		/* 6.0.4: the categories as the chips show them ( sorted, so adding back a removed one is no change ). Nothing without
		 * the chips: the server then refuses rather than empty the product's categories. */
		categories: function() {
			if ( ! el( 'ecdv2_cat_tokens' ) ) {
				return {};
			}
			var ids = [];
			$( '#ecdv2_cat_tokens .ecdv2-cat-token' ).each( function() {
				var id = parseInt( $( this ).attr( 'data-category-id' ), 10 );
				if ( id ) {
					ids.push( id );
				}
			} );
			ids.sort( function( a, b ) { return a - b; } );
			return { categories_sent: 1, category_ids: ids };
		},

		/* 6.0.4: volume pricing and role prices as their rows show them; a list on the page is sent even when empty. */
		price_rules: function() {
			var tiers = [];
			var roles = [];
			$( '#price_tiers_holder > .ec_admin_price_tier_row' ).each( function() {
				var $in = $( this ).find( 'input[type=number]' );
				tiers.push( { quantity: $in.eq( 0 ).val(), price: $in.eq( 1 ).val() } );
			} );
			$( '#role_prices_holder > .ec_admin_role_price_row' ).each( function() {
				roles.push( { role: $( this ).attr( 'data-role' ) || '', price: $( this ).attr( 'data-price' ) || '' } );
			} );
			return {
				price_rules_sent : 1,
				tiers_shown      : el( 'price_tiers_holder' ) ? 1 : 0,
				roles_shown      : el( 'role_prices_holder' ) ? 1 : 0,
				tiers            : tiers,
				roles            : roles
			};
		},

		basic: function() {
			var data = {
				activate_in_store : c( 'activate_in_store' ),
				title             : v( 'title' ),
				post_slug         : v( 'post_slug' ),
				model_number      : v( 'model_number' ),
				manufacturer_id   : v( 'manufacturer_id' ),
				price             : v( 'price' ),
				description       : ed( 'description' )
			};
			/* Previous Price ( list_price ) renders next to Price on the
			 * General tab in v2; only include it when the field is present. */
			maybe( data, 'list_price', v( 'list_price' ) );
			return data;
		},

		quantities: function() {
			return {
				stock_quantity_type   : v( 'stock_quantity_type' ),
				stock_quantity        : v( 'stock_quantity' ),
				min_purchase_quantity : v( 'min_purchase_quantity' ),
				max_purchase_quantity : v( 'max_purchase_quantity' )
			};
		},

		pricing: function() {
			var data = {
				list_price                   : v( 'list_price' ),
				login_for_pricing            : c( 'login_for_pricing' ),
				login_for_pricing_user_level : v( 'login_for_pricing_user_level' ),
				login_for_pricing_label      : v( 'login_for_pricing_label' ),
				show_custom_price_range      : c( 'show_custom_price_range' ),
				price_range_low              : v( 'price_range_low' ),
				price_range_high             : v( 'price_range_high' ),
				enable_price_label           : c( 'enable_price_label' ),
				replace_price_label          : c( 'replace_price_label' ),
				custom_price_label           : v( 'custom_price_label' )
			};
			maybe( data, 'product_cost', v( 'product_cost' ) ); /* 6.0.2: Cost per item */
			maybe( data, 'unit_measure', v( 'unit_measure' ) ); /* 6.0.5: unit price ( admin review M16 ) */
			maybe( data, 'unit_measure_unit', v( 'unit_measure_unit' ) );
			maybe( data, 'unit_base', v( 'unit_base' ) );
			return data;
		},

		tax: function() {
			var data = {
				is_taxable : c( 'is_taxable' )
			};
			maybe( data, 'vat_rate', c( 'vat_rate' ) );
			maybe( data, 'TIC', v( 'TIC' ) );
			maybe( data, 'tax_class', v( 'tax_class' ) ); /* 6.0.5: the VAT / GST rate ( it was printed and never sent ) */
			return data;
		},

		shipping: function() {
			var restriction_node = el( 'shipping_restriction' );
			var restriction = 0;
			if ( restriction_node && 'checkbox' !== restriction_node.type ) {
				restriction = v( 'shipping_restriction' );
			}
			return {
				is_shippable                  : c( 'is_shippable' ),
				exclude_shippable_calculation : c( 'exclude_shippable_calculation' ),
				ship_to_billing               : c( 'ship_to_billing' ),
				allow_backorders              : c( 'allow_backorders' ),
				backorder_fill_date           : v( 'backorder_fill_date' ),
				handling_price                : v( 'handling_price' ),
				handling_price_each           : v( 'handling_price_each' ),
				shipping_restriction          : restriction
			};
		},

		packaging: function() {
			return {
				weight : v( 'weight' ),
				width  : v( 'width' ),
				height : v( 'height' ),
				length : v( 'length' )
			};
		},

		/* 6.0.2: Customs and packing. */
		customs: function() {
			return {
				packs_in            : v( 'packs_in' ),
				hs_code             : v( 'hs_code' ),
				country_of_origin   : v( 'country_of_origin' ),
				customs_description : v( 'customs_description' )
			};
		},

		general_options: function() {
			var data = {
				show_on_startup      : c( 'show_on_startup' ),
				is_special           : c( 'is_special' ),
				use_customer_reviews : c( 'use_customer_reviews' ),
				is_donation          : c( 'is_donation' ),
				is_giftcard          : c( 'is_giftcard' ),
				inquiry_mode         : c( 'inquiry_mode' ),
				inquiry_url          : v( 'inquiry_url' ),
				catalog_mode         : c( 'catalog_mode' ),
				catalog_mode_phrase  : v( 'catalog_mode_phrase' ),
				is_preorder_type     : c( 'is_preorder_type' ),
				is_restaurant_type   : c( 'is_restaurant_type' ),
				role_id              : v( 'role_id' ),
				sort_position        : v( 'sort_position' )
			};
			maybe( data, 'no_withdrawal', c( 'no_withdrawal' ) ); /* 6.0.4: No right of withdrawal ( EU ) */
			maybe( data, 'mailerlite_group_name', v( 'mailerlite_group_name' ) );
			maybe( data, 'activecampaign_group_name', v( 'activecampaign_group_name' ) );
			return data;
		},

		menus: function() {
			return {
				menulevel1_id_1 : v( 'menulevel1_id_1' ),
				menulevel2_id_1 : v( 'menulevel2_id_1' ),
				menulevel3_id_1 : v( 'menulevel3_id_1' ),
				menulevel1_id_2 : v( 'menulevel1_id_2' ),
				menulevel2_id_2 : v( 'menulevel2_id_2' ),
				menulevel3_id_2 : v( 'menulevel3_id_2' ),
				menulevel1_id_3 : v( 'menulevel1_id_3' ),
				menulevel2_id_3 : v( 'menulevel2_id_3' ),
				menulevel3_id_3 : v( 'menulevel3_id_3' )
			};
		},

		featured_products: function() {
			return {
				featured_product_id_1 : v( 'featured_product_id_1' ),
				featured_product_id_2 : v( 'featured_product_id_2' ),
				featured_product_id_3 : v( 'featured_product_id_3' ),
				featured_product_id_4 : v( 'featured_product_id_4' )
			};
		},

		tags: function() {
			/* Server expects renamed keys for the two effect selects. */
			return {
				hover_effect   : v( 'image_hover_type' ),
				image_effect   : v( 'image_effect_type' ),
				tag_type       : v( 'tag_type' ),
				tag_text       : v( 'tag_text' ),
				tag_bg_color   : v( 'tag_bg_color' ),
				tag_text_color : v( 'tag_text_color' )
			};
		},

		subscription: function() {
			return {
				is_subscription_item                    : c( 'is_subscription_item' ),
				subscription_bill_length                : v( 'subscription_bill_length' ),
				subscription_bill_period                : v( 'subscription_bill_period' ),
				subscription_bill_duration              : v( 'subscription_bill_duration' ),
				subscription_shipping_recurring         : c( 'subscription_shipping_recurring' ),
				subscription_recurring_email            : c( 'subscription_recurring_email' ),
				trial_period_days                       : v( 'trial_period_days' ),
				subscription_signup_fee                 : v( 'subscription_signup_fee' ),
				allow_multiple_subscription_purchases   : c( 'allow_multiple_subscription_purchases' ),
				subscription_prorate                    : c( 'subscription_prorate' ),
				subscription_plan_id                    : v( 'subscription_plan_id' ),
				membership_page                         : v( 'membership_page' )
			};
		},

		downloads: function() {
			return {
				is_download                : c( 'is_download' ),
				is_amazon_download         : c( 'is_amazon_download' ),
				amazon_key                 : v( 'amazon_key' ),
				download_file_name         : v( 'download_file_name' ),
				maximum_downloads_allowed  : v( 'maximum_downloads_allowed' ),
				download_timelimit_seconds : v( 'download_timelimit_seconds' )
			};
		},

		deconetwork: function() {
			return {
				is_deconetwork         : c( 'is_deconetwork' ),
				deconetwork_mode       : v( 'deconetwork_mode' ),
				deconetwork_product_id : v( 'deconetwork_product_id' ),
				deconetwork_size_id    : v( 'deconetwork_size_id' ),
				deconetwork_color_id   : v( 'deconetwork_color_id' ),
				deconetwork_design_id  : v( 'deconetwork_design_id' )
			};
		},

		seo: function() {
			/* 6.0.5: only the fields on the page. With Yoast SEO's card in place of this one the page may hold only the older
			 * description ( print_seo_fallback_v2() ), and the keywords, excerpt and featured image must not be saved empty. */
			var data = {};
			maybe( data, 'seo_description', v( 'seo_description' ) );
			maybe( data, 'seo_keywords', v( 'seo_keywords' ) );
			maybe( data, 'post_excerpt', ed( 'post_excerpt' ) );
			maybe( data, 'featured_image', v( 'featured_image' ) );
			return data;
		},

		/* Yoast SEO card ( SEO tab, only printed while Yoast is active ). Saved to
		 * the product post's _yoast_wpseo_* meta by ec_admin_ajax_save_product_details_yoast_seo. */
		yoast_seo: function() {
			return {
				yoast_title     : v( 'ecdv2_yoast_title' ),
				yoast_metadesc  : v( 'ecdv2_yoast_metadesc' ),
				yoast_focuskw   : v( 'ecdv2_yoast_focuskw' ),
				yoast_canonical : v( 'ecdv2_yoast_canonical' ),
				yoast_noindex   : v( 'ecdv2_yoast_noindex' )
			};
		},

		/* 6.0.2: Search & AI card ( SEO tab ). Saved into the product's Google attributes by
		 * ec_admin_ajax_save_product_details_search_ai, keeping every other attribute. */
		search_ai: function() {
			return {
				gtin       : v( 'ecdv2_sai_gtin' ),
				mpn        : v( 'ecdv2_sai_mpn' ),
				condition  : v( 'ecdv2_sai_condition' ),
				no_barcode : c( 'ecdv2_sai_no_barcode' )
			};
		},

		/* Google Merchant ( PRO ) — same field set the legacy panel button
		 * posted; server action is ec_admin_ajax_save_product_details_ +
		 * this endpoint key. Every gm_* element is rendered by the v2
		 * section, so plain v() reads are safe. */
		google_merchant_pro: function() {
			var data = {
				enabled                     : v( 'gm_enabled' ),
				title                       : v( 'gm_title' ),
				google_product_category     : v( 'gm_google_product_category' ),
				product_type                : v( 'gm_product_type' ),
				identifier_exists           : v( 'gm_identifier_exists' ),
				gtin                        : v( 'gm_gtin' ),
				mpn                         : v( 'gm_mpn' ),
				availability                : v( 'gm_availability' ),
				condition                   : v( 'gm_condition' ),
				availability_date           : v( 'gm_availability_date' ),
				expiration_date             : v( 'gm_expiration_date' ),
				gender                      : v( 'gm_gender' ),
				age_group                   : v( 'gm_age_group' ),
				size_type                   : v( 'gm_size_type' ),
				size_system                 : v( 'gm_size_system' ),
				item_group_id               : v( 'gm_item_group_id' ),
				color                       : v( 'gm_color' ),
				material                    : v( 'gm_material' ),
				pattern                     : v( 'gm_pattern' ),
				size                        : v( 'gm_size' ),
				weight_type                 : v( 'gm_weight_type' ),
				shipping_weight             : v( 'gm_shipping_weight' ),
				unit_pricing_base_measure   : v( 'gm_unit_pricing_base_measure' ),
				unit_pricing_measure        : v( 'gm_unit_pricing_measure' ),
				shipping_label              : v( 'gm_shipping_label' ),
				shipping_unit               : v( 'gm_shipping_unit' ),
				shipping_length             : v( 'gm_shipping_length' ),
				shipping_width              : v( 'gm_shipping_width' ),
				shipping_height             : v( 'gm_shipping_height' ),
				min_handling_time           : v( 'gm_min_handling_time' ),
				max_handling_time           : v( 'gm_max_handling_time' ),
				adult                       : v( 'gm_adult' ),
				multipack                   : v( 'gm_multipack' ),
				is_bundle                   : v( 'gm_is_bundle' ),
				certification               : v( 'gm_certification' ),
				certification_code          : v( 'gm_certification_code' ),
				energy_efficiency_class     : v( 'gm_energy_efficiency_class' ),
				min_energy_efficiency_class : v( 'gm_min_energy_efficiency_class' ),
				max_energy_efficiency_class : v( 'gm_max_energy_efficiency_class' )
			};
			/* 6.0.2: barcode, part number and condition live on the Search & AI card; PRO's save
			 * replaces the whole attribute record, so send the card's values, not blanks. */
			if ( el( 'ecdv2_sai_gtin' ) ) {
				var no_barcode = c( 'ecdv2_sai_no_barcode' );
				data.gtin      = no_barcode ? '' : String( v( 'ecdv2_sai_gtin' ) ).replace( /\D/g, '' );
				data.mpn       = no_barcode ? '' : v( 'ecdv2_sai_mpn' );
				data.condition = v( 'ecdv2_sai_condition' );
				data.identifier_exists = no_barcode ? 'no' : ( ( data.gtin || data.mpn ) ? 'yes' : '' );
			}
			return data;
		},

		images: function() {
			return {
				use_optionitem_images : c( 'use_optionitem_images' ),
				image1                : v( 'image1' ),
				image2                : v( 'image2' ),
				image3                : v( 'image3' ),
				image4                : v( 'image4' ),
				image5                : v( 'image5' )
			};
		},

		options: function() {
			return {
				use_advanced_optionset : c( 'use_advanced_optionset' ),
				option1                : v( 'option1' ),
				option2                : v( 'option2' ),
				option3                : v( 'option3' ),
				option4                : v( 'option4' ),
				option5                : v( 'option5' )
			};
		},

		short_description: function() {
			return { short_description : ed( 'short_description' ) };
		},

		specifications: function() {
			return { specifications : ed( 'specifications' ) };
		},

		order_completed_note: function() {
			return { order_completed_note : ed( 'order_completed_note' ) };
		},

		order_completed_email_note: function() {
			return { order_completed_email_note : ed( 'order_completed_email_note' ) };
		},

		order_completed_details_note: function() {
			return { order_completed_details_note : ed( 'order_completed_details_note' ) };
		}
	};

	/* ------------------------------------------------------------------ */
	/* Dirty tracking                                                       */
	/* ------------------------------------------------------------------ */

	/*
	 * 6.0.4 ( editor plan Phase 2 ): a section counts as changed only while what it would save differs from what was saved.
	 * Each save endpoint's payload is kept as it was when the page loaded ( and after each save ); a change that brings it
	 * back ( typed and undone, or an editor tidying its own content as it starts ) leaves the section unchanged.
	 */
	var snapshots = {};

	function payload_json( endpoint ) {
		if ( ! payloads[ endpoint ] ) {
			return null;
		}
		try {
			return JSON.stringify( payloads[ endpoint ]() );
		} catch ( e ) {
			return null;
		}
	}

	function take_snapshot( endpoint ) {
		snapshots[ endpoint ] = payload_json( endpoint );
	}

	function take_snapshots() {
		var endpoint;
		for ( endpoint in payloads ) {
			if ( payloads.hasOwnProperty( endpoint ) ) {
				take_snapshot( endpoint );
			}
		}
	}

	/* A saved value changed by something other than the section's save ( the Active switch ) is the saved value now. */
	function snapshot_set( endpoint, key, value ) {
		if ( ! snapshots[ endpoint ] ) {
			return;
		}
		try {
			var data = JSON.parse( snapshots[ endpoint ] );
			if ( data && data.hasOwnProperty( key ) ) {
				data[ key ] = value;
				snapshots[ endpoint ] = JSON.stringify( data );
			}
		} catch ( e ) {
			snapshots[ endpoint ] = null;
		}
	}

	function section_unchanged( section ) {
		var endpoint = endpoint_map[ section ];
		return !! endpoint && 'string' === typeof snapshots[ endpoint ] && payload_json( endpoint ) === snapshots[ endpoint ];
	}

	/* The page's unsaved marks from dirty: the pill, Discard, each card and its section in the rail. */
	function paint_dirty() {
		var any = has_dirty();
		$( '#ecdv2_dirty_pill' ).toggleClass( 'is-visible', any );
		$( '#ecdv2_discard_btn' ).prop( 'hidden', ! any );
		$( '.ecdv2-card.is-dirty' ).each( function() {
			if ( ! dirty[ $( this ).attr( 'data-ecdv2-section' ) ] ) {
				$( this ).removeClass( 'is-dirty' );
			}
		} );
		$( '.ecdv2-tab[data-ecdv2-tab]' ).each( function() {
			$( this ).toggleClass( 'has-dirty', panel( $( this ).attr( 'data-ecdv2-tab' ) ).find( '.ecdv2-card.is-dirty' ).length > 0 );
		} );
		paint_tabsets();
	}

	function mark_dirty( section ) {
		if ( ! section || saving ) {
			return;
		}
		if ( section_unchanged( section ) ) {
			/* Back to what was saved: every section saving through the same endpoint is unchanged too. */
			var endpoint = endpoint_map[ section ];
			var key;
			for ( key in dirty ) {
				if ( dirty.hasOwnProperty( key ) && endpoint_map[ key ] === endpoint ) {
					delete dirty[ key ];
				}
			}
			paint_dirty();
			return;
		}
		dirty[ section ] = true;
		$( '#ecdv2_dirty_pill' ).addClass( 'is-visible' );
		$( '#ecdv2_discard_btn' ).prop( 'hidden', false );
		var card = $( '.ecdv2-card[data-ecdv2-section="' + section + '"]' );
		card.addClass( 'is-dirty' );
		var panel = card.closest( '.ecdv2-panel' );
		if ( panel.length ) {
			$( '.ecdv2-tab[data-ecdv2-tab="' + panel.attr( 'data-ecdv2-panel' ) + '"]' ).addClass( 'has-dirty' );
		}
		var label = $( '#ecdv2_save_btn .ecdv2-save-label' );
		if ( label.length && ! $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) ) {
			label.text( _t( 'save', 'Save' ) );
		}
		paint_tabsets();
	}

	/**
	 * 6.0.2: let another plugin's card save with the editor's Save button.
	 *
	 *   ecdv2.register_section( 'facebook', function() { return { fb_title: ... }; } );
	 *
	 * Any input, select or textarea inside .ecdv2-card[data-ecdv2-section="<key>"] ( or carrying
	 * data-ecdv2-sec="<key>" ) then marks the card unsaved, and save_all() posts the payload with
	 * product_id and wp_easycart_nonce ( nonce action wp-easycart-product-details ) to the AJAX action
	 * ec_admin_ajax_save_product_details_<endpoint>. The endpoint defaults to the key. The editor's own
	 * sections cannot be replaced.
	 *
	 * @param {string}   key        Section key ( [A-Za-z0-9_] ).
	 * @param {Function} payload_fn Returns the fields to post.
	 * @param {string}   endpoint   Optional save endpoint suffix.
	 * @return {boolean} Registered.
	 */
	function register_section( key, payload_fn, endpoint ) {
		key = String( key || '' ).replace( /[^A-Za-z0-9_]/g, '' );
		endpoint = String( endpoint || key ).replace( /[^A-Za-z0-9_]/g, '' );
		if ( '' === key || '' === endpoint || 'function' !== typeof payload_fn ) {
			return false;
		}
		var endpoint_key;
		if ( builtin_sections.hasOwnProperty( key ) ) {
			return false;
		}
		for ( endpoint_key in builtin_sections ) {
			if ( builtin_sections.hasOwnProperty( endpoint_key ) && builtin_sections[ endpoint_key ] === endpoint ) {
				return false;
			}
		}
		endpoint_map[ key ] = endpoint;
		payloads[ endpoint ] = payload_fn;
		/* 6.0.4: its saved state, once the card that registered it has drawn. */
		setTimeout( function() {
			if ( ! dirty[ key ] ) {
				take_snapshot( endpoint );
			}
		}, 0 );
		return true;
	}

	function clear_dirty() {
		dirty = {};
		$( '#ecdv2_dirty_pill' ).removeClass( 'is-visible' );
		$( '#ecdv2_discard_btn' ).prop( 'hidden', true );
		$( '.ecdv2-card.is-dirty' ).removeClass( 'is-dirty' );
		$( '.ecdv2-tab.has-dirty' ).removeClass( 'has-dirty' );
		$( '.ecdv2-tabset-pane[data-ecdv2-touched]' ).removeAttr( 'data-ecdv2-touched' );
		paint_tabsets();
	}

	function has_dirty() {
		var key;
		for ( key in dirty ) {
			if ( dirty.hasOwnProperty( key ) && dirty[ key ] ) {
				return true;
			}
		}
		return false;
	}

	/* Bind dirty tracking to one TinyMCE editor exactly once. */
	function bind_editor_dirty( editor ) {
		if ( ! editor || editor.__ecdv2_dirty_bound ) {
			return;
		}
		editor.__ecdv2_dirty_bound = true;
		/* 6.0.4: the section's saved state is what the editor holds once it has started ( it can tidy the stored HTML ). */
		var snap = function() {
			var section = $( '[data-ecdv2-wpeditor="' + editor.id + '"]' ).attr( 'data-ecdv2-sec-editor' );
			if ( section && endpoint_map[ section ] && ! dirty[ section ] ) {
				take_snapshot( endpoint_map[ section ] );
			}
		};
		if ( editor.initialized ) {
			snap();
		} else if ( editor.on ) {
			editor.on( 'init', function() {
				setTimeout( snap, 0 );
			} );
		}
		editor.on( 'change keyup input paste cut', function() {
			var wrap = $( '[data-ecdv2-wpeditor="' + editor.id + '"]' );
			if ( wrap.length ) {
				mark_dirty( wrap.attr( 'data-ecdv2-sec-editor' ) );
			}
		} );
	}

	/* Bind every TinyMCE editor that exists right now. wp_editor's inline
	 * init runs from the footer BEFORE our jQuery-ready callback, so editors
	 * are usually already registered when we boot — an AddEditor-only
	 * listener misses all of them, which is why Description ( and every
	 * other rich-text field ) never flipped the Unsaved pill. */
	function bind_existing_editors() {
		if ( typeof tinymce === 'undefined' || ! tinymce.editors ) {
			return;
		}
		var i;
		for ( i = 0; i < tinymce.editors.length; i++ ) {
			bind_editor_dirty( tinymce.editors[ i ] );
		}
	}

	function bind_dirty_tracking() {
		$( document ).on( 'change input', '[data-ecdv2-sec]', function() {
			mark_dirty( $( this ).attr( 'data-ecdv2-sec' ) );
		} );

		/* 6.0.4: typing in a field takes its error message away ( the SKU is checked again when it changes ). */
		$( document ).on( 'input', '.ecdv2-field.has-error input, .ecdv2-field.has-error textarea', function() {
			if ( this.id ) {
				field_ok( this.id );
			}
		} );

		/* Catch-all: any form control inside a save-mapped section card that
		 * lacks the data-ecdv2-sec stamp ( legacy PRO markup, dynamically
		 * injected inputs ) still flips its section dirty. Cards without an
		 * endpoint mapping ( categories, PRO galleries, tier/role managers —
		 * all of which auto-save ) are skipped, as are helper inputs with
		 * their own instant-save buttons. */
		$( document ).on( 'change input', '.ecdv2-card[data-ecdv2-section] input:not([data-ecdv2-sec]), .ecdv2-card[data-ecdv2-section] select:not([data-ecdv2-sec]), .ecdv2-card[data-ecdv2-section] textarea:not([data-ecdv2-sec])', function() {
			var node = $( this );
			if ( node.is( '[type=hidden], [type=button], [type=submit], [type=file], [type=search]' ) ) {
				return;
			}
			if ( node.closest( '[data-ecdv2-wpeditor]' ).length ) {
				return; /* wp_editor textareas are handled below */
			}
			if ( node.closest( '.select2-container' ).length ) {
				return; /* select2 search box — the real select fires its own change */
			}
			if ( 'manufacturer_name' === this.id || 'ec_notify_new_email' === this.id ) {
				return; /* instant-save helpers with their own buttons */
			}
			if ( node.closest( '[data-ecdv2-nodirty]' ).length ) {
				return; /* 6.0.4: a row being typed before Add ( a tier, a role price ) or the category search */
			}
			var section = node.closest( '.ecdv2-card[data-ecdv2-section]' ).attr( 'data-ecdv2-section' );
			if ( endpoint_map[ section ] ) {
				mark_dirty( section );
			}
		} );

		/* TinyMCE: bind editors that already exist, catch late arrivals via
		 * AddEditor, and re-sweep on window load for slow initializers. */
		bind_existing_editors();
		if ( typeof tinymce !== 'undefined' && tinymce.on ) {
			tinymce.on( 'AddEditor', function( evt ) {
				bind_editor_dirty( evt.editor );
			} );
		}
		$( window ).on( 'load', bind_existing_editors );

		/* Text-mode textareas inside wp_editor wrappers. */
		$( document ).on( 'change input', '[data-ecdv2-wpeditor] textarea', function() {
			mark_dirty( $( this ).closest( '[data-ecdv2-wpeditor]' ).attr( 'data-ecdv2-sec-editor' ) );
		} );

		window.addEventListener( 'beforeunload', function( e ) {
			if ( has_dirty() && ! saving ) {
				e.preventDefault();
				e.returnValue = '';
				return '';
			}
		} );

		document.addEventListener( 'keydown', function( e ) {
			if ( ( e.metaKey || e.ctrlKey ) && 's' === e.key.toLowerCase() ) {
				e.preventDefault();
				save_all();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Saving                                                               */
	/* ------------------------------------------------------------------ */

	function base_data( endpoint ) {
		return {
			action            : 'ec_admin_ajax_save_product_details_' + endpoint,
			product_id        : v( 'product_id' ),
			wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
		};
	}

	/* 6.0.4: the message under a field ( its .ecdv2-field-msg, shown while the field has .has-error; nothing set it before, so
	 * the SKU check's messages never showed ). A message the template printed for the code ( a child with
	 * data-ecdv2-error="<code>" ) shows as it is, else the text given. Returns false when the field is not on the page. */
	function field_error( name, code, message ) {
		var node = el( name );
		if ( ! node ) {
			return false;
		}
		var $field = $( node ).closest( '.ecdv2-field' );
		if ( ! $field.length ) {
			return false;
		}
		var $msg = $field.children( '.ecdv2-field-msg' ).first();
		if ( ! $msg.length ) {
			$msg = $( '<span class="ecdv2-field-msg"></span>' ).appendTo( $field );
		}
		if ( ! $msg.attr( 'id' ) ) {
			$msg.attr( 'id', node.id + '_ecdv2_msg' );
		}
		var $printed = code ? $msg.children( '[data-ecdv2-error="' + code + '"]' ) : $();
		$msg.children().hide();
		if ( $printed.length ) {
			$printed.show();
		} else {
			var $text = $msg.children( '.ecdv2-field-msg-text' );
			if ( ! $text.length ) {
				$text = $( '<span class="ecdv2-field-msg-text"></span>' ).appendTo( $msg );
			}
			$text.text( message || '' ).show();
		}
		$field.addClass( 'has-error' );
		$( node ).addClass( 'ecdv2-invalid' ).attr( { 'aria-invalid': 'true', 'aria-describedby': $msg.attr( 'id' ) } );
		return true;
	}

	function field_ok( name ) {
		var node = el( name );
		if ( ! node ) {
			return;
		}
		$( node ).removeClass( 'ecdv2-invalid' ).removeAttr( 'aria-invalid' ).closest( '.ecdv2-field' ).removeClass( 'has-error' );
	}

	/* 6.0.4: a refusal that names a field ( data.field, data.code, data.message; the General save's SKU ) shows on that field,
	 * on its tab. */
	function show_field_refusal( response ) {
		if ( 'string' === typeof response ) {
			try {
				response = JSON.parse( response );
			} catch ( err ) {
				return;
			}
		}
		var data = ( response && response.data && 'object' === typeof response.data ) ? response.data : null;
		if ( ! data || 'string' !== typeof data.field || ! field_error( data.field, data.code || '', data.message || '' ) ) {
			return '';
		}
		/* 6.0.4: the field goes in the list of what was not saved, which takes the merchant there. */
		return data.field;
	}

	function validate_basic() {
		var items = [];
		if ( '' === $.trim( v( 'title' ) ) ) {
			field_error( 'title', 'required', '' ); /* the field's own message ( "Your product must have a title" ) */
			items.push( { text: _t( 'title_required', 'Please enter a product title before saving.' ), field: 'title' } );
		}
		/* 6.0.4: every product needs a SKU ( its page's shortcode names it ); the server refuses an empty one too. */
		if ( el( 'model_number' ) && '' === $.trim( v( 'model_number' ) ) ) {
			field_error( 'model_number', 'required', _t( 'sku_required', 'A SKU is required.' ) );
			items.push( { text: _t( 'sku_required', 'A SKU is required.' ), field: 'model_number' } );
		}
		if ( ! items.length ) {
			return true;
		}
		toast( items[0].text, 'error' );
		errors_show( items );
		errors_go( items[0] );
		return false;
	}

	/* ---------- What kept a Save from finishing ( 6.0.4, editor plan Phase 2 ) ---------- */

	/* @param {Array} items text, and field ( an input id ) or card ( a selector ) to go to. */
	function errors_show( items ) {
		var $box = $( '#ecdv2_errors' );
		if ( ! $box.length || ! items.length ) {
			return false;
		}
		var $list = $( '#ecdv2_errors_list' ).empty();
		$.each( items, function( i, item ) {
			var $li = $( '<li class="ecdv2-errors-item"></li>' );
			$( '<span class="ecdv2-errors-text"></span>' ).text( item.text ).appendTo( $li );
			if ( item.field || item.card ) {
				$( '<button type="button" class="ecdv2-errors-go"></button>' ).text( vt( 'errors_go', 'Show me' ) ).on( 'click', function() {
					errors_go( item );
				} ).appendTo( $li );
			}
			$list.append( $li );
		} );
		$box.prop( 'hidden', false );
		return true;
	}

	function errors_hide() {
		$( '#ecdv2_errors' ).prop( 'hidden', true );
		$( '#ecdv2_errors_list' ).empty();
	}

	function errors_go( item ) {
		var node = item.field ? el( item.field ) : null;
		if ( ! node ) {
			if ( item.card ) {
				go_card( item.card );
			}
			return;
		}
		var key = $( node ).closest( '.ecdv2-panel' ).attr( 'data-ecdv2-panel' );
		if ( key ) {
			go_tab( key, { no_scroll: true } );
		}
		show_pane_of( node );
		var $box = $( '[data-ecdv2-wpeditor="' + item.field + '"]' );
		$box = $box.length ? $box : $( node ).closest( '.ecdv2-field, .ecdv2-toggle-row' );
		$box = $box.length ? $box : $( node );
		spy_hold_until = Date.now() + 900;
		if ( $box.get( 0 ) && $box.get( 0 ).scrollIntoView ) {
			$box.get( 0 ).scrollIntoView( { block: 'center', behavior: reduce_motion ? 'auto' : 'smooth' } );
		}
		flash( $box );
		try { node.focus( { preventScroll: true } ); } catch ( e ) { node.focus(); }
	}

	/* Puts every field back as it was saved: the page again, without the leave-page question. */
	function discard() {
		if ( ! has_dirty() ) {
			return;
		}
		window.ecdv2_confirm( {
			title: vt( 'discard_title', 'Discard your changes?' ),
			message: vt( 'discard_body', 'Everything you changed since the last save goes back to how it was saved. Pictures you uploaded and categories you made stay.' ),
			confirm_label: vt( 'discard', 'Discard' ),
			cancel_label: vt( 'keep_editing', 'Keep editing' )
		} ).then( function( ok ) {
			if ( ! ok ) {
				return;
			}
			clear_dirty();
			saving = true; /* no leave-page question */
			window.location.reload();
		} );
	}

	/* A save the server refused ( wp_send_json_error(): { success: false, data: { message } }, e.g. a product whose options
	 * a service manages, or option sets over the variation limit ): its message ( '' when it sent none ), else null. Other
	 * answers ( a new product's id, plain text, { success: true } ) are not refusals. */
	function refusal_message( response ) {
		if ( 'string' === typeof response && /"success"\s*:\s*false/.test( response ) ) {
			try {
				response = JSON.parse( response );
			} catch ( err ) {
				return null;
			}
		}
		if ( ! response || 'object' !== typeof response || false !== response.success ) {
			return null;
		}
		return ( response.data && 'string' === typeof response.data.message ) ? response.data.message : '';
	}

	function save_endpoint( endpoint, cards ) {
		var d = $.Deferred();
		var data = $.extend( base_data( endpoint ), payloads[ endpoint ]() );
		cards.addClass( 'is-saving' );
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : data,
			success : function( response ) {
				var refused = refusal_message( response );
				if ( null !== refused ) {
					cards.removeClass( 'is-saving' ); /* still unsaved */
					d.reject( endpoint, refused, show_field_refusal( response ) );
					return;
				}
				cards.removeClass( 'is-saving is-dirty' );
				take_snapshot( endpoint ); /* 6.0.4: what is saved now */
				if ( 'basic' === endpoint ) {
					/* 6.0.4: the SKU saved is the product's own now ( the SKU check compares with it ). */
					field_ok( 'model_number' );
					$( el( 'model_number_orig' ) ).val( v( 'model_number' ) );
				}
				/* 6.0.2: a saved Track Quantity change redraws WP EasyCart PRO's variation table ( its stock
				 * column ), as the legacy instant save did. */
				if ( 'quantities' === endpoint && typeof window.wp_easycart_pro_get_updated_options === 'function' ) {
					window.wp_easycart_pro_get_updated_options();
				}
				d.resolve( response );
			},
			error   : function() {
				cards.removeClass( 'is-saving' );
				d.reject( endpoint, '' );
			}
		} );
		return d.promise();
	}

	/**
	 * Save every changed section.
	 *
	 * @param {Object} opts 6.0.4, optional: then = called once everything saved ( Save and next ).
	 */
	function save_all( opts ) {
		opts = ( opts && 'object' === typeof opts && ! opts.target ) ? opts : {};
		if ( saving ) {
			return false;
		}

		var is_new = $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' );

		/* New product: a single basic save creates the row, then we
		 * redirect into edit mode so all sections unlock.              */
		if ( is_new ) {
			if ( ! validate_basic() ) {
				return false;
			}
			saving = true;
			$( '#ecdv2_save_btn' ).addClass( 'is-saving' );
			save_endpoint( 'basic', $( '.ecdv2-card[data-ecdv2-section="basic"]' ) ).done( function( response ) {
				var new_id = parseInt( response, 10 );
				if ( ! new_id ) {
					try {
						new_id = parseInt( JSON.parse( response ), 10 );
					} catch ( err ) {
						new_id = 0;
					}
				}
				if ( new_id ) {
					clear_dirty();
					window.location.href = 'admin.php?page=wp-easycart-products&subpage=products&product_id=' + new_id + '&ec_admin_form_action=edit&ecdv2_created=1';
				} else {
					saving = false;
					$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
					toast( _t( 'create_failed', 'Could not create the product. Check the SKU is unique and try again.' ), 'error' );
				}
			} ).fail( function( endpoint_key, message, field ) {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				toast( message || _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
				if ( field ) {
					errors_show( [ { text: message, field: field } ] );
					errors_go( { field: field } );
				}
			} );
			return false;
		}

		/* 6.0.4 ( editor plan Phase 2 ): a new product's draft is created by its first Save, which always includes Basics
		 * ( a title, and a SKU from the title when none was typed ). */
		var draft = !! ( vcfg && vcfg.draft );
		if ( draft ) {
			if ( el( 'model_number' ) && '' === $.trim( v( 'model_number' ) ) && '' !== $.trim( v( 'title' ) ) ) {
				$( el( 'model_number' ) ).val( slugify( v( 'title' ) ) );
			}
			dirty.basic = true;
		}
		if ( ! has_dirty() ) {
			toast( _t( 'nothing_to_save', 'No changes to save.' ), 'info' );
			return false;
		}
		if ( dirty.basic && ! validate_basic() ) {
			return false;
		}

		/* Resolve dirty sections -> unique endpoints, keep card refs for spinners. */
		var queue = [];
		var seen = {};
		var section, endpoint;
		for ( section in dirty ) {
			if ( ! dirty.hasOwnProperty( section ) || ! dirty[ section ] ) {
				continue;
			}
			endpoint = endpoint_map[ section ];
			if ( ! endpoint || ! payloads[ endpoint ] ) {
				continue; /* sections without a save endpoint ( e.g. categories save instantly ) */
			}
			if ( seen[ endpoint ] ) {
				seen[ endpoint ] = seen[ endpoint ].add( $( '.ecdv2-card[data-ecdv2-section="' + section + '"]' ) );
				continue;
			}
			seen[ endpoint ] = $( '.ecdv2-card[data-ecdv2-section="' + section + '"]' );
			queue.push( endpoint );
		}

		if ( ! queue.length ) {
			clear_dirty();
			return false;
		}
		if ( draft && queue[0] !== 'basic' && -1 !== $.inArray( 'basic', queue ) ) {
			queue.splice( $.inArray( 'basic', queue ), 1 );
			queue.unshift( 'basic' );
		}

		saving = true;
		$( '#ecdv2_save_btn' ).addClass( 'is-saving' );
		var failed = [];
		var refusals = []; /* the server's own reasons for a refused save, shown as they are */
		var problems = []; /* 6.0.4: the list at the top, each with a way to its field or card */

		( function run( i ) {
			if ( i >= queue.length ) {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				if ( failed.length || refusals.length ) {
					var notes = refusals.slice( 0 );
					if ( failed.length ) {
						notes.push( _t( 'partial_save', 'Some sections failed to save:' ) + ' ' + failed.join( ', ' ) );
					}
					toast( notes.join( ' ' ), 'error' );
					/* 6.0.4: the sections that saved are saved; the list says what is left. */
					var key;
					for ( key in dirty ) {
						if ( dirty.hasOwnProperty( key ) && section_unchanged( key ) ) {
							delete dirty[ key ];
						}
					}
					paint_dirty();
					errors_show( problems );
					/* The first field to fix comes into view ( the list stays at the top ). */
					var to_field = $.grep( problems, function( p ) { return !! p.field; } );
					if ( to_field.length ) {
						errors_go( to_field[0] );
					}
				} else if ( draft ) {
					/* The product exists now: open it as one ( its page, View, Duplicate ). */
					clear_dirty();
					saving = true;
					window.location.href = ( vcfg.edit_url || 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' ) + parseInt( v( 'product_id' ), 10 ) + '&ecdv2_created=1';
					return;
				} else {
					errors_hide();
					clear_dirty();
					toast( _t( 'saved', 'Product saved.' ), 'success' );
					update_header_meta();
					if ( 'function' === typeof opts.then ) {
						opts.then();
					}
				}
				refresh_feeds( queue );
				return;
			}
			/* fail before always: the last section's result must be counted before run() reports. */
			save_endpoint( queue[ i ], seen[ queue[ i ] ] )
				.fail( function( endpoint_key, message, field ) {
					var $card = seen[ endpoint_key ] ? seen[ endpoint_key ].first() : $();
					var section_key = $card.attr( 'data-ecdv2-section' ) || '';
					var name = $.trim( $card.find( '.ecdv2-card-title' ).first().text() ) || endpoint_key;
					var go = section_key ? '.ecdv2-card[data-ecdv2-section="' + section_key + '"]' : '';
					if ( message ) {
						if ( -1 === $.inArray( message, refusals ) ) {
							refusals.push( message );
							problems.push( { text: name + ': ' + message, field: field || '', card: go } );
						}
					} else {
						failed.push( endpoint_key );
						problems.push( { text: vt( 'errors_section', '%s was not saved. Try Save again.' ).replace( '%s', name ), card: go } );
					}
				} )
				.done( function() {
					if ( draft && 'basic' === queue[ i ] ) {
						vcfg.draft = false; /* created: the rest saves onto a product */
					}
				} )
				.always( function() {
					/* 6.0.4: a draft whose Basics did not save is not a product yet; nothing else is saved onto it. */
					if ( draft && 'basic' === queue[ i ] && vcfg.draft ) {
						problems.push( { text: vt( 'draft_basics', 'Give the product a title first: it is created when you save.' ), field: 'title' } );
						run( queue.length );
						return;
					}
					run( i + 1 );
				} );
		} )( 0 );

		return false;
	}

	/* Header "Role restricted" pill mirrors the Limit Visibility select
	   (#role_id, Organize > Visibility & Sorting). Runs after successful
	   saves and on select change so the flag never goes stale. */
	function sync_restricted_pill() {
		var $pill = $( '#ecdv2_restricted_pill' );
		if ( ! $pill.length ) {
			return;
		}
		var role = parseInt( $( '#role_id' ).val(), 10 ) || 0;
		$pill.toggle( role > 0 );
	}

	function update_header_meta() {
		sync_restricted_pill();
		$( '#ecdv2_header_title' ).text( v( 'title' ) );
		var sku = $( '#ecdv2_header_sku' );
		if ( sku.length ) {
			sku.text( v( 'model_number' ) );
		}
		var price = $( '#ecdv2_header_price' );
		if ( price.length ) {
			price.text( money( v( 'price' ) ) );
		}
		sync_header_stock();
	}

	/* 6.0.2: the header's stock chip follows the saved Track Quantity choice ( 0 not tracked = hidden,
	 * 1 = the overall count or Out of stock, 2 = Variant stock ). Wording comes from the chip's own data
	 * attributes, printed by the template. */
	function sync_header_stock() {
		var $chip = $( '#ecdv2_header_stock' );
		var type = String( v( 'stock_quantity_type' ) );
		if ( ! $chip.length || ! el( 'stock_quantity_type' ) ) {
			return;
		}
		var $wrap = $( '#ecdv2_header_stock_wrap' );
		if ( '1' !== type && '2' !== type ) {
			$wrap.hide();
			return;
		}
		var qty = parseInt( v( 'stock_quantity' ), 10 ) || 0;
		var text;
		if ( '2' === type ) {
			text = $chip.attr( 'data-text-variant' ) || 'Variant stock';
		} else if ( qty > 0 ) {
			text = String( $chip.attr( 'data-text-in' ) || '%d in stock' ).replace( '%d', qty );
		} else {
			text = $chip.attr( 'data-text-out' ) || 'Out of stock';
		}
		$chip.text( text )
			.toggleClass( 'ecdv2-instock', '2' === type || qty > 0 )
			.toggleClass( 'ecdv2-outstock', '1' === type && qty <= 0 );
		$wrap.show();
	}

	/* Header status toggle. 6.0.4 ( editor plan Phase 2 ): it saves only itself, at once ( it sent the whole General section,
	 * pending title, price and description edits with it ); everything else still waits for Save. */
	function quick_activate( node ) {
		if ( $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) ) {
			return; /* nothing to persist yet */
		}
		var pill = $( '#ecdv2_status_pill' );
		var paint = function( on ) {
			node.checked = on;
			pill.toggleClass( 'is-active', on ).text( on ? _t( 'active', 'Active' ) : _t( 'draft', 'Draft' ) );
			paint_side();
		};
		var on = !! node.checked;
		paint( on );
		if ( vcfg && vcfg.draft ) {
			return; /* 6.0.4: a new product's draft: the switch is saved with its first Save */
		}
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ecv2_product_toggle_status',
				product_id        : v( 'product_id' ),
				status            : on ? 1 : 0,
				wp_easycart_nonce : $( node ).attr( 'data-nonce' ) || ''
			},
			success : function( response ) {
				if ( ! response || ! response.success ) {
					paint( ! on );
					toast( ( response && response.data && response.data.message ) || _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
					return;
				}
				snapshot_set( 'basic', 'activate_in_store', on ? 1 : 0 );
				toast( on ? _t( 'activated', 'Product is now live in your store.' ) : _t( 'deactivated', 'Product hidden from your store.' ), 'success' );
			},
			error   : function() {
				paint( ! on );
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Tabs + routing                                                       */
	/* ------------------------------------------------------------------ */

	/* Legacy deep-link anchors -> v2 tabs ( old emails / docs keep working ). */
	var legacy_anchor_map = {
		'images'             : 'media',
		'quantities'         : 'inventory',
		'pricing'            : 'pricing',
		'options'            : 'options',
		'general-options'    : 'behavior',
		'featured-products'  : 'organize',
		'seo'                : 'seo',
		'menus'              : 'organize',
		'categories'         : 'organize',
		'tags'               : 'design',
		'subscription'       : 'behavior',
		'downloads'          : 'behavior',
		'deconetwork'        : 'behavior',
		'shipping'           : 'shipping',
		'packaging'          : 'shipping',
		'customs'            : 'shipping',
		'tax'                : 'tax'
	};

	/*
	 * 6.0.1: the per-choice image panel is rendered server side when the page loads, so a set attached in the
	 * Options tab left the Media tab still reporting that there is nothing to give images to until the
	 * merchant reloaded by hand ( and removing one left galleries for choices that no longer applied ). It is
	 * refetched when the Media tab is opened, and only when the options have changed since it was drawn.
	 *
	 * The first attempt only watched the free edition's slot pickers ( #ecdv2_opt_slots ), which are never on the
	 * page when the PRO Media panel is: PRO prints its own Options panel. So the signature now reads the PRO option
	 * and modifier rows too, and PRO fires 'ecdv2:options-changed' after every attach, remove, reorder and modifier
	 * change ( products-v2-pro.js ).
	 */
	var media_pro_stale = false;

	function option_slot_signature() {
		var sig = [];
		$( '#ecdv2_opt_slots select' ).each( function() { sig.push( 's' + String( $( this ).val() || 0 ) ); } );
		$( '#wp-easycart-pro-basic-options .wp-easycart-pro-option-table-row-sortable' ).not( '.wp-easycart-pro-option-modifer-row' ).each( function() { sig.push( 'b' + String( $( this ).attr( 'data-option-id' ) || 0 ) ); } );
		$( '.wp-easycart-pro-option-modifer-row' ).each( function() { sig.push( 'm' + String( $( this ).attr( 'data-option-id' ) || 0 ) ); } );
		return sig.join( ',' );
	}

	var media_pro_loading = false;
	var media_pro_again   = false;
	var media_pro_timer   = 0;

	/*
	 * 6.0.1 ( third pass ): the panel is refetched as soon as the options change, in the background, instead of only
	 * when the Media tab is opened, and the request cannot be answered from a cache ( a GET to admin-ajax.php can be
	 * cached by a host or a caching plugin, which matched "only a hard refresh shows it" ). A refetch asked for while
	 * one is running runs again when it finishes. Each way this can stop is written to the console.
	 */
	function refresh_media_pro( force ) {
		var $wrap = $( '#ecdv2_media_pro_wrap' );
		if ( ! $wrap.length ) { return; }
		if ( media_pro_loading ) { media_pro_again = true; return; }
		var sig = option_slot_signature();
		if ( ! force && ! media_pro_stale && sig === String( $wrap.data( 'ecdv2-slots' ) || '' ) ) { return; }
		var product_id = parseInt( $( '#product_id' ).val(), 10 ) || 0;
		if ( ! product_id ) { return; }
		media_pro_loading = true;
		$wrap.addClass( 'is-loading' );
		$.ajax( {
			url: ( window.wpeasycart_admin_ajax_object || {} ).ajax_url || window.ajaxurl,
			type: 'GET',
			cache: false,
			dataType: 'html',
			data: { action: 'ec_admin_ajax_get_optionitem_images_content_pro', product_id: product_id }
		} ).done( function( html ) {
			if ( html && -1 !== String( html ).indexOf( 'wpeasycart_product_images_pro' ) ) {
				$wrap.html( html );
				/* The drag-and-drop uploaders were bound to the old gallery nodes ( products-pro.js ). */
				if ( 'function' === typeof window.wp_easycart_pro_bind_media_dropzones ) {
					window.wp_easycart_pro_bind_media_dropzones( $wrap );
				}
				$wrap.data( 'ecdv2-slots', option_slot_signature() );
				media_pro_stale = false;
				$( document ).trigger( 'ecdv2:media-refreshed' );
			} else if ( window.console ) {
				window.console.warn( 'WP EasyCart: the Media panel could not be redrawn ( unexpected answer from ec_admin_ajax_get_optionitem_images_content_pro ).', String( html ).substr( 0, 200 ) );
			}
		} ).fail( function( xhr ) {
			if ( window.console ) { window.console.warn( 'WP EasyCart: the Media panel could not be redrawn ( HTTP ' + ( xhr ? xhr.status : '?' ) + ' ).' ); }
		} ).always( function() {
			media_pro_loading = false;
			$wrap.removeClass( 'is-loading' );
			if ( media_pro_again ) { media_pro_again = false; refresh_media_pro( true ); }
		} );
	}

	/* Seed the signature the panel was drawn with ( so the first visit does not refetch for nothing ), and follow
	   option changes: refetch right away ( debounced, the save has finished by then ), and again when Media opens if
	   that one did not land. */
	$( function() {
		$( '#ecdv2_media_pro_wrap' ).data( 'ecdv2-slots', option_slot_signature() );
	} );
	$( document ).on( 'ecdv2:options-changed', function() {
		media_pro_stale = true;
		clearTimeout( media_pro_timer );
		media_pro_timer = setTimeout( function() { refresh_media_pro( true ); }, 150 );
	} );
	/* ------------------------------------------------------------------ */
	/* 6.0.4: views. Page = every section on one page, the rail a map of   */
	/* them; Focus = one section at a time. Edit / Insights picks which     */
	/* sections ( Activity, Offers, Reviews are Insights ). A panel carries */
	/* is-active while it is on screen ( PRO's Offers waits for that ).     */
	/* ------------------------------------------------------------------ */

	var vcfg = ( window.wpeasycart_ecdv2_view && 'object' === typeof window.wpeasycart_ecdv2_view ) ? window.wpeasycart_ecdv2_view : null;
	var view = ( vcfg && 'page' === vcfg.view ) ? 'page' : 'focus';
	var mode = 'edit';
	var spy_hold_until = 0;
	var reduce_motion = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	function vt( key, fallback ) {
		return ( vcfg && vcfg.text && vcfg.text[ key ] ) ? vcfg.text[ key ] : fallback;
	}

	function panel( key ) {
		return $( '.ecdv2-panel[data-ecdv2-panel="' + key + '"]' );
	}

	function panel_mode( key ) {
		return 'insights' === panel( key ).attr( 'data-ecdv2-mode' ) ? 'insights' : 'edit';
	}

	/* The sections of one mode, in the rail's order. */
	/* @param {boolean} all 6.0.4: also the sections the product type does not use ( they are left out otherwise ). */
	function mode_keys( which, all ) {
		var keys = [];
		$( '.ecdv2-tab[data-ecdv2-tab]' ).each( function() {
			var key = $( this ).attr( 'data-ecdv2-tab' );
			if ( ( 'insights' === $( this ).attr( 'data-ecdv2-mode' ) ? 'insights' : 'edit' ) === which && panel( key ).length && ( all || ! panel( key ).hasClass( 'is-unused' ) ) ) {
				keys.push( key );
			}
		} );
		return keys;
	}

	function current_key() {
		var key = $( '.ecdv2-tab.is-active' ).attr( 'data-ecdv2-tab' );
		return ( key && panel_mode( key ) === mode ) ? key : ( mode_keys( mode )[0] || 'general' );
	}

	function set_rail_active( key ) {
		$( '.ecdv2-tab' ).each( function() {
			var on = $( this ).attr( 'data-ecdv2-tab' ) === key;
			$( this ).toggleClass( 'is-active', on );
			if ( 'page' === view && vcfg ) {
				this.removeAttribute( 'aria-selected' );
				if ( on ) { this.setAttribute( 'aria-current', 'true' ); } else { this.removeAttribute( 'aria-current' ); }
			} else {
				this.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				this.removeAttribute( 'aria-current' );
			}
		} );
		/* 6.0.4: the rail's list scrolls when the window is short ( both modes' sections are in it ): keep the current one in view. */
		var list = el( 'ecdv2_tabs' );
		var node = $( '.ecdv2-tab.is-active' ).get( 0 );
		if ( list && node && list.scrollHeight > list.clientHeight + 1 && 'auto' === window.getComputedStyle( list ).overflowY ) {
			var lr = list.getBoundingClientRect();
			var nr = node.getBoundingClientRect();
			if ( nr.top < lr.top ) {
				list.scrollTop -= ( lr.top - nr.top ) + 6;
			} else if ( nr.bottom > lr.bottom ) {
				list.scrollTop += ( nr.bottom - lr.bottom ) + 6;
			}
		}
	}

	/* Which panels are on screen: every panel of the mode in the page view, the one section in the focus view. */
	function show_panels( key ) {
		$( '.ecdv2-panel' ).each( function() {
			var $p = $( this );
			var on = ( 'page' === view && vcfg ) ? ( panel_mode( $p.attr( 'data-ecdv2-panel' ) ) === mode ) : ( $p.attr( 'data-ecdv2-panel' ) === key );
			$p.toggleClass( 'is-active', on );
		} );
	}

	/* Where the sticky header ends once it sticks ( its sticky top + its height ): what a section scrolls to sit under. Read
	   from the header's place on screen it would be too low before the page has scrolled far enough for the header to stick. */
	function scroll_offset() {
		var header = $( '#ecdv2_wrap .ecdv2-header' ).get( 0 );
		if ( ! header ) {
			return 12;
		}
		var stuck = parseFloat( window.getComputedStyle( header ).top );
		if ( isNaN( stuck ) || 'sticky' !== window.getComputedStyle( header ).position ) {
			return Math.max( 0, header.getBoundingClientRect().bottom ) + 12;
		}
		return stuck + header.offsetHeight + 12;
	}

	function scroll_to_node( node, instant ) {
		if ( ! node ) {
			return;
		}
		show_pane_of( node );
		var top = node.getBoundingClientRect().top + window.pageYOffset - scroll_offset();
		spy_hold_until = Date.now() + ( instant || reduce_motion ? 80 : 1200 );
		try {
			window.scrollTo( { top: Math.max( 0, top ), behavior: ( instant || reduce_motion ) ? 'auto' : 'smooth' } );
		} catch ( e ) {
			window.scrollTo( 0, Math.max( 0, top ) );
		}
	}

	function flash( $node ) {
		$node.addClass( 'ecdv2-flash' );
		setTimeout( function() {
			$node.removeClass( 'ecdv2-flash' );
		}, 1800 );
	}

	/* ---------- 6.0.4: cards with tabs ( the product page's text, the notes for buyers; tabset_open() ) ---------- */

	/* Shows one pane of a tab group. @param {boolean} focus Moves focus to its tab ( the arrow keys ). */
	function tabset_show( $set, key, focus ) {
		var $tab = $set.find( '.ecdv2-tabset-tab[data-ecdv2-pane="' + key + '"]' );
		if ( ! $tab.length ) {
			return;
		}
		$set.find( '.ecdv2-tabset-tab' ).each( function() {
			var on = this === $tab.get( 0 );
			$( this ).toggleClass( 'is-active', on ).attr( { 'aria-selected': on ? 'true' : 'false', tabindex: on ? '0' : '-1' } );
		} );
		$set.children( '.ecdv2-tabset-pane' ).each( function() {
			var on = $( this ).attr( 'data-ecdv2-pane' ) === key;
			$( this ).toggleClass( 'is-active', on ).prop( 'hidden', ! on );
		} );
		if ( focus ) {
			$tab.get( 0 ).focus();
		}
		$( document ).trigger( 'ecdv2:pane-shown', [ $set.attr( 'data-ecdv2-tabset' ), key ] );
	}

	/* The tab holding a field or card the editor takes the merchant to opens first. */
	function show_pane_of( node ) {
		/* 6.0.4: and a closed disclosure ( the Shipping box's Customs ). */
		$( node ).closest( 'details:not([open])' ).attr( 'open', 'open' );
		var $pane = $( node ).closest( '.ecdv2-tabset-pane' );
		if ( $pane.length && $pane.prop( 'hidden' ) ) {
			tabset_show( $pane.closest( '.ecdv2-tabset' ), $pane.attr( 'data-ecdv2-pane' ), false );
		}
	}

	/* The words in a field ( a text box or a wp_editor ), or null when it is not on the page. */
	function pane_text( id ) {
		if ( ! el( id ) ) {
			return null;
		}
		var html = $( el( id ) ).closest( '[data-ecdv2-wpeditor]' ).length ? ed( id ) : $( el( id ) ).val();
		return $.trim( String( html || '' ).replace( /<[^>]*>/g, ' ' ).replace( /&nbsp;|&#160;/g, ' ' ) );
	}

	/* Each tab says Empty while its field has no words, and carries the unsaved mark of a change made in it ( the
	   description pane shares the basic section with Product details, so the section alone cannot say which ). */
	function paint_tabsets() {
		$( '.ecdv2-tabset-tab' ).each( function() {
			var $tab = $( this );
			var check = $tab.attr( 'data-ecdv2-pane-check' );
			if ( check ) {
				var text = pane_text( check );
				if ( null !== text ) {
					$tab.find( '.ecdv2-tabset-empty' ).prop( 'hidden', '' !== text );
				}
			}
			var $pane = $tab.closest( '.ecdv2-tabset' ).children( '.ecdv2-tabset-pane[data-ecdv2-pane="' + $tab.attr( 'data-ecdv2-pane' ) + '"]' );
			var changed = $pane.find( '.ecdv2-card.is-dirty' ).length > 0;
			if ( ! changed ) {
				$pane.removeAttr( 'data-ecdv2-touched' );
			}
			$tab.toggleClass( 'has-dirty', changed && !! $pane.attr( 'data-ecdv2-touched' ) );
		} );
	}

	function bind_tabsets() {
		if ( ! $( '.ecdv2-tabset' ).length ) {
			return;
		}
		$( document ).on( 'click', '.ecdv2-tabset-tab', function() {
			tabset_show( $( this ).closest( '.ecdv2-tabset' ), $( this ).attr( 'data-ecdv2-pane' ), false );
		} );
		$( document ).on( 'keydown', '.ecdv2-tabset-tab', function( e ) {
			var $tabs = $( this ).closest( '.ecdv2-tabset-tabs' ).children( '.ecdv2-tabset-tab' );
			var at = $tabs.index( this );
			var to = -1;
			if ( 'ArrowRight' === e.key ) {
				to = ( at + 1 ) % $tabs.length;
			} else if ( 'ArrowLeft' === e.key ) {
				to = ( at - 1 + $tabs.length ) % $tabs.length;
			} else if ( 'Home' === e.key ) {
				to = 0;
			} else if ( 'End' === e.key ) {
				to = $tabs.length - 1;
			}
			if ( -1 === to ) {
				return;
			}
			e.preventDefault();
			tabset_show( $( this ).closest( '.ecdv2-tabset' ), $tabs.eq( to ).attr( 'data-ecdv2-pane' ), true );
		} );
		var timer = 0;
		var touch = function( node ) {
			$( node ).closest( '.ecdv2-tabset-pane' ).attr( 'data-ecdv2-touched', '1' );
			clearTimeout( timer );
			timer = setTimeout( paint_tabsets, 120 );
		};
		$( document ).on( 'change input', '.ecdv2-tabset-pane input, .ecdv2-tabset-pane select, .ecdv2-tabset-pane textarea', function() {
			touch( this );
		} );
		var bind_editor = function( editor ) {
			if ( ! editor || editor.__ecdv2_pane_bound || ! editor.on ) {
				return;
			}
			var $wrap = $( '[data-ecdv2-wpeditor="' + editor.id + '"]' );
			if ( ! $wrap.closest( '.ecdv2-tabset-pane' ).length ) {
				return;
			}
			editor.__ecdv2_pane_bound = true;
			editor.on( 'change input paste cut undo redo', function() {
				touch( $wrap );
			} );
			editor.on( 'keyup', function( evt ) {
				if ( evt && evt.key && 1 === evt.key.length || ( evt && ( 'Backspace' === evt.key || 'Delete' === evt.key || 'Enter' === evt.key ) ) ) {
					touch( $wrap );
				}
			} );
		};
		var bind_editors = function() {
			if ( typeof tinymce === 'undefined' || ! tinymce.editors ) {
				return;
			}
			var i;
			for ( i = 0; i < tinymce.editors.length; i++ ) {
				bind_editor( tinymce.editors[ i ] );
			}
		};
		bind_editors();
		if ( typeof tinymce !== 'undefined' && tinymce.on ) {
			tinymce.on( 'AddEditor', function( evt ) {
				bind_editor( evt.editor );
			} );
		}
		$( window ).on( 'load', function() {
			bind_editors();
			paint_tabsets();
		} );
		paint_tabsets();
	}

	/* A new manufacturer: the name box opens under the picker ( and closes again once it is made ). */
	function manufacturer_new( open ) {
		var $box = $( '#ecdv2_manufacturer_new' );
		$box.prop( 'hidden', ! open );
		$( '#ecdv2_manufacturer_new_toggle' ).prop( 'hidden', !! open ).attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			$( el( 'manufacturer_name' ) ).trigger( 'focus' );
		} else {
			$( el( 'manufacturer_name' ) ).val( '' );
			$( '#ecdv2_manufacturer_new_toggle' ).trigger( 'focus' );
		}
	}

	/* Store menus: the next menu location waiting behind Add another menu location. */
	function menu_location_add( btn ) {
		var $wrap = $( btn ).closest( '.ecdv2-menu-locations' );
		var $next = $wrap.children( '.ecdv2-menu-location[hidden]' ).first();
		if ( ! $next.length ) {
			return;
		}
		$next.prop( 'hidden', false );
		flash( $next );
		$next.find( 'select' ).first().trigger( 'focus' );
		if ( ! $wrap.children( '.ecdv2-menu-location[hidden]' ).length ) {
			$( btn ).remove();
		}
	}

	/* 6.0.4: one card of a section ( the price context chips ): its section opens, then the card scrolls into view. */
	function go_card( selector ) {
		var $card = $( selector ).first();
		if ( ! $card.length ) {
			return;
		}
		var key = $card.closest( '.ecdv2-panel' ).attr( 'data-ecdv2-panel' );
		if ( key ) {
			go_tab( key, { no_scroll: true } );
		}
		scroll_to_node( $card.get( 0 ) );
		flash( $card );
	}

	/**
	 * 6.0.4: one field, wherever it is ( a section's card or the summary column ): its section opens ( a field of the summary
	 * column stays where it is ), the field comes into view, flashes and takes the focus. Used by the quick links under the
	 * title and every [data-ecdv2-go][data-ecdv2-field] button.
	 *
	 * @param {string} id  The field's id.
	 * @param {string} key The section to open when the field is not on the page.
	 */
	function go_field( id, key ) {
		var field = el( id );
		if ( ! field ) {
			if ( key ) {
				go_tab( key );
			}
			return;
		}
		var in_side = $( field ).closest( '#ecdv2_side' ).length > 0;
		if ( in_side ) {
			if ( 'edit' !== mode ) {
				set_mode( 'edit', { silent: true } );
			}
		} else {
			key = $( field ).closest( '.ecdv2-panel' ).attr( 'data-ecdv2-panel' ) || key;
			if ( key ) {
				go_tab( key, { no_scroll: true } );
			}
		}
		show_pane_of( field );
		var $box = $( '[data-ecdv2-wpeditor="' + id + '"]' );
		$box = $box.length ? $box : $( field ).closest( '.ecdv2-field, .ecdv2-toggle-row' );
		$box = $box.length ? $box : $( field );
		var node = $box.get( 0 );
		if ( in_side && side_is_drawer() ) {
			/* 6.0.4: the summary drawer opens on the field. */
			side_drawer( true, { no_focus: true } );
			side_scroll_to( node );
		} else {
			var rect = node.getBoundingClientRect();
			if ( ! in_side || rect.top < scroll_offset() || rect.bottom > window.innerHeight ) {
				spy_hold_until = Date.now() + 900;
				node.scrollIntoView( { block: 'center', behavior: reduce_motion ? 'auto' : 'smooth' } );
			}
		}
		flash( $box );
		setTimeout( function() {
			/* A select2 list takes the focus on the box it draws. */
			var target = $( field ).hasClass( 'select2-hidden-accessible' ) ? ( $( field ).next( '.select2' ).find( '.select2-selection' ).get( 0 ) || field ) : field;
			try { target.focus( { preventScroll: true } ); } catch ( e ) { target.focus(); }
		}, 120 );
	}

	/* 6.0.4: a node of the summary drawer into its view ( the drawer scrolls by itself; the page stays put ). */
	function side_scroll_to( node ) {
		var side = el( 'ecdv2_side' );
		if ( ! side || ! node ) {
			return;
		}
		var head = el( 'ecdv2_side_head' );
		var top = 0;
		var n = node;
		while ( n && n !== side ) {
			top += n.offsetTop;
			n = n.offsetParent;
		}
		side.scrollTop = Math.max( 0, top - ( head ? head.offsetHeight : 0 ) - 12 );
	}

	/**
	 * 6.0.5: a card of the summary column into view. A column taller than the window sticks at the window's foot ( fit_side() ), so a
	 * card near its top ( Status, Product type ) sits above the window and scrolling the page never shows it: the column itself moves
	 * until the card is under the header, and goes back to its foot at the next scroll. A column that fits scrolls the page as before.
	 *
	 * @param {Element} node    The card.
	 * @param {boolean} instant No smooth scroll.
	 */
	var side_reveal_back = null;
	function side_reveal( node, instant ) {
		var side = el( 'ecdv2_side' );
		var rect = node.getBoundingClientRect();
		var head = $( '#ecdv2_wrap .ecdv2-header' ).get( 0 );
		var under = head ? Math.round( parseFloat( window.getComputedStyle( head ).top ) || 0 ) + head.offsetHeight + 14 : scroll_offset();
		if ( rect.top >= under && rect.bottom <= window.innerHeight ) {
			return;
		}
		if ( side && $( side ).hasClass( 'is-sticky' ) && side.offsetHeight + under + 16 > window.innerHeight ) {
			var box = side.getBoundingClientRect();
			if ( rect.top < under ) {
				side.style.top = Math.round( under - ( rect.top - box.top ) ) + 'px';
				/* A sticky column stays inside the editor's grid, so near the foot of the page it can move only so far: the page
				 * scrolls up by what is left ( a few hundred pixels, never back to the top ). */
				var short = under - node.getBoundingClientRect().top;
				var wait = 500;
				if ( short > 1 ) {
					spy_hold_until = Date.now() + 1200;
					wait = 1200;
					try {
						window.scrollBy( { top: -Math.round( short ), behavior: ( instant || reduce_motion ) ? 'auto' : 'smooth' } );
					} catch ( e ) {
						window.scrollBy( 0, -Math.round( short ) );
					}
				}
				if ( side_reveal_back ) {
					window.removeEventListener( 'scroll', side_reveal_back );
				}
				var after = Date.now() + wait;
				var settled = window.pageYOffset;
				side_reveal_back = function() {
					/* The page's own scrolling ( this move, a scroll still running ) never counts; the person scrolling on does. */
					if ( Date.now() < after ) {
						settled = window.pageYOffset;
						return;
					}
					if ( Math.abs( window.pageYOffset - settled ) < 80 ) {
						return;
					}
					window.removeEventListener( 'scroll', side_reveal_back );
					side_reveal_back = null;
					fit_side();
				};
				window.addEventListener( 'scroll', side_reveal_back, { passive: true } );
				return;
			}
		}
		scroll_to_node( node, instant );
	}

	/* The summary column's cards ( Categories lives there since 6.0.4 ). */
	function go_side( key, opts ) {
		var $card = 'categories' === key ? $( '.ecdv2-card[data-ecdv2-section="categories"]' ) : ( 'health' === key ? $( '#ecdv2_health' ) : ( 'status' === key ? $( '#ecdv2_side_visibility' ) : ( 'type' === key ? $( '#ecdv2_side_type_card' ) : $( '#ecdv2_side' ) ) ) );
		if ( ! $card.length ) {
			return;
		}
		if ( 'edit' !== mode ) {
			set_mode( 'edit', { silent: true } );
		}
		if ( side_is_drawer() ) {
			/* 6.0.4: the column is a drawer: it opens on that card. */
			side_drawer( true, { no_focus: true } );
			side_scroll_to( $card.get( 0 ) );
		} else {
			side_reveal( $card.get( 0 ), opts && opts.instant );
		}
		flash( $card );
		/* 6.0.5: Change type ( the type section's bar ) lands on the choice itself. */
		if ( 'type' === key && ! ( opts && opts.no_focus ) && el( 'ecdv2_type' ) && ! el( 'ecdv2_type' ).disabled ) {
			setTimeout( function() {
				try { el( 'ecdv2_type' ).focus( { preventScroll: true } ); } catch ( e ) { el( 'ecdv2_type' ).focus(); }
			}, 60 );
		}
		if ( 'status' === key && ! ( opts && opts.no_focus ) && el( 'activate_in_store' ) ) {
			try { el( 'activate_in_store' ).focus( { preventScroll: true } ); } catch ( e ) { el( 'activate_in_store' ).focus(); }
		}
		if ( 'categories' === key && ! ( opts && opts.no_focus ) ) {
			setTimeout( function() {
				var input = el( 'ecdv2_cat_input' );
				if ( input ) {
					try { input.focus( { preventScroll: true } ); } catch ( e ) { input.focus(); }
				}
			}, 60 );
		}
	}

	/**
	 * Open a section. In the page view it scrolls there ( opening it if folded ); in the focus view it is the only one on
	 * screen. Insights sections switch to Insights.
	 *
	 * @param {string} key  A section key ( a rail entry ), or categories / health for the summary column.
	 * @param {Object} opts no_scroll ( the caller scrolls to a field itself ), instant, keep_hash.
	 */
	function go_tab( key, opts ) {
		opts = opts || {};
		if ( $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) && 'general' !== key ) {
			return;
		}
		if ( 'categories' === key || 'health' === key || 'side' === key || 'status' === key || 'type' === key ) {
			go_side( key, opts );
			return;
		}
		/* 6.0.4: a section of the fields ( a link inside the summary drawer, the rail ): the drawer gets out of the way. */
		if ( side_is_open() ) {
			side_drawer( false, { no_focus: true } );
		}
		var tab = $( '.ecdv2-tab[data-ecdv2-tab="' + key + '"]' );
		if ( ! tab.length ) {
			return;
		}
		if ( panel( key ).hasClass( 'is-unused' ) ) {
			show_unused( key );
		}
		if ( vcfg && panel_mode( key ) !== mode ) {
			set_mode( panel_mode( key ), { silent: true, key: key } );
		}
		set_rail_active( key );
		show_panels( key );
		if ( 'page' === view && vcfg ) {
			fold( key, false, { quiet: true } );
			if ( ! opts.no_scroll ) {
				scroll_to_node( panel( key ).get( 0 ), opts.instant );
			}
		} else if ( ! opts.no_scroll ) {
			window.scrollTo( { top: 0 } );
		}
		if ( history.replaceState && ! opts.keep_hash ) {
			history.replaceState( null, '', '#' + key );
		}
		if ( 'activity' === key && ! activity_loaded ) {
			load_activity();
		}
		if ( 'media' === key ) {
			refresh_media_pro();
		}
		load_panel_feeds( key );
		/* mobile pill bar: keep active pill in view */
		if ( tab[ 0 ].scrollIntoView && window.innerWidth <= 782 && $( 'body' ).hasClass( 'ecdv2-rail-static' ) ) {
			tab[ 0 ].scrollIntoView( { block: 'nearest', inline: 'center', behavior: 'smooth' } );
		}
	}

	/* Edit / Insights. */
	var last_edit_key = '';
	function set_mode( which, opts ) {
		opts = opts || {};
		if ( ! vcfg ) {
			return;
		}
		/* 6.0.4: Back to editing returns to the section that was open. */
		if ( 'edit' === mode && 'insights' === which ) {
			last_edit_key = current_key();
		}
		if ( 'edit' === which && ! opts.key && last_edit_key && panel( last_edit_key ).length && ! panel( last_edit_key ).hasClass( 'is-unused' ) ) {
			opts.key = last_edit_key;
		}
		/* 6.0.4: Open Insights in the summary drawer. */
		if ( side_is_open() && ! opts.silent ) {
			side_drawer( false, { no_focus: true } );
		}
		mode = 'insights' === which ? 'insights' : 'edit';
		$( '#ecdv2_wrap' ).attr( 'data-mode', mode ).toggleClass( 'ecdv2-mode-insights', 'insights' === mode ).toggleClass( 'ecdv2-mode-edit', 'edit' === mode );
		/* The rail carries it too: on a phone it is moved out of the editor into <body> ( admin-frame-v2.js ). */
		$( '.ecdv2-rail[data-mode]' ).attr( 'data-mode', mode );
		$( '[data-ecdv2-mode-set]' ).filter( '.ecdv2-seg-btn' ).each( function() {
			this.setAttribute( 'aria-pressed', $( this ).attr( 'data-ecdv2-mode-set' ) === mode ? 'true' : 'false' );
		} );
		var key = opts.key || mode_keys( mode )[0];
		set_rail_active( key );
		show_panels( key );
		if ( 'insights' === mode ) {
			if ( ! activity_loaded ) {
				load_activity();
			}
			$( '.ecdv2-panel.is-active' ).each( function() {
				load_panel_feeds( $( this ).attr( 'data-ecdv2-panel' ) );
			} );
		}
		if ( ! opts.silent ) {
			if ( history.replaceState ) {
				history.replaceState( null, '', '#' + key );
			}
			if ( 'edit' === mode && 'page' === view && key !== mode_keys( 'edit' )[0] ) {
				fold( key, false, { quiet: true } );
				scroll_to_node( panel( key ).get( 0 ), true );
			} else {
				window.scrollTo( { top: 0 } );
			}
		}
		paint_next();
		$( document ).trigger( 'ecdv2:mode-changed', [ mode ] );
	}

	/* Page / Focus, remembered for this person. */
	function set_view( which, opts ) {
		opts = opts || {};
		if ( ! vcfg ) {
			return;
		}
		var key = current_key();
		view = 'focus' === which ? 'focus' : 'page';
		$( '#ecdv2_wrap' ).attr( 'data-view', view ).toggleClass( 'ecdv2-view-page', 'page' === view ).toggleClass( 'ecdv2-view-focus', 'focus' === view );
		$( '[data-ecdv2-view-set]' ).each( function() {
			this.setAttribute( 'aria-pressed', $( this ).attr( 'data-ecdv2-view-set' ) === view ? 'true' : 'false' );
		} );
		$( '.ecdv2-panel' ).attr( 'role', 'page' === view ? 'region' : 'tabpanel' );
		$( '.ecdv2-rail[data-view]' ).attr( 'data-view', view );
		$( '.ecdv2-tab' ).each( function() {
			if ( 'page' === view ) { this.removeAttribute( 'role' ); } else { this.setAttribute( 'role', 'tab' ); }
		} );
		set_rail_active( key );
		show_panels( key );
		if ( 'page' === view ) {
			fold( key, false, { quiet: true } );
			scroll_to_node( panel( key ).get( 0 ), true );
			watch_feeds();
		} else {
			window.scrollTo( { top: 0 } );
			load_panel_feeds( key );
		}
		paint_next();
		paint_summaries();
		if ( ! opts.silent ) {
			save_pref( { view: view } );
			dismiss_tip();
		}
		$( document ).trigger( 'ecdv2:view-changed', [ view ] );
	}

	function save_pref( data ) {
		if ( ! vcfg || ! vcfg.action || ! window.wpeasycart_admin_ajax_object ) {
			return;
		}
		$.post( wpeasycart_admin_ajax_object.ajax_url, $.extend( { action: vcfg.action, nonce: vcfg.nonce }, data ) );
	}

	/* Fold or open a section in the page view ( quiet: not remembered, e.g. opening one to show a field ). */
	function fold( key, folded, opts ) {
		opts = opts || {};
		var $p = panel( key );
		/* 6.0.4: Insights sections fold too ( any section with a fold button ). */
		if ( ! $p.length || ! $p.find( '> .ecdv2-panel-head .ecdv2-panel-toggle' ).length ) {
			return;
		}
		if ( $p.hasClass( 'is-collapsed' ) === !! folded ) {
			return;
		}
		$p.toggleClass( 'is-collapsed', !! folded );
		$p.find( '> .ecdv2-panel-head .ecdv2-panel-toggle' ).attr( 'aria-expanded', folded ? 'false' : 'true' );
		if ( folded ) {
			paint_summary( key );
		} else {
			load_panel_feeds( key );
			if ( 'media' === key ) {
				refresh_media_pro();
			}
		}
		if ( ! opts.quiet ) {
			save_pref( { section: key, collapsed: folded ? '1' : '0' } );
		}
	}

	/* Focus view: a "Next: Media" button at the foot of each section. */
	function paint_next() {
		var keys = mode_keys( mode );
		$( '.ecdv2-panel' ).each( function() {
			var $p = $( this );
			var key = $p.attr( 'data-ecdv2-panel' );
			var at = $.inArray( key, keys );
			var $box = $p.children( '.ecdv2-panel-next' );
			if ( -1 === at ) {
				$box.remove();
				return;
			}
			if ( ! $box.length ) {
				$box = $( '<div class="ecdv2-panel-next"></div>' ).appendTo( $p );
			}
			$box.empty();
			var next = keys[ at + 1 ];
			if ( next ) {
				var label = $.trim( $( '.ecdv2-tab[data-ecdv2-tab="' + next + '"] span' ).eq( 1 ).text() );
				$( '<button type="button" class="ecv2-btn ecdv2-panel-next-btn"></button>' )
					.text( vt( 'next', 'Next: %s' ).replace( '%s', label ) )
					.append( '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>' )
					.attr( 'data-ecdv2-next', next )
					.appendTo( $box );
			} else if ( 'edit' === mode ) {
				$( '<span class="ecdv2-panel-next-last"></span>' ).text( vt( 'last', 'That was the last section. Save when you are ready.' ) ).appendTo( $box );
			}
		} );
	}

	/* Page view: the rail follows the section being read: the one that reaches a third of the way down the window ( where a
	   field shown from the rail, the search or listing health sits ), never above the header. */
	function spy() {
		if ( 'page' !== view || ! vcfg || Date.now() < spy_hold_until ) {
			return;
		}
		var line = Math.max( scroll_offset() + 24, Math.round( window.innerHeight / 3 ) );
		var best = null;
		var best_top = -Infinity;
		var first = null;
		var first_top = Infinity;
		$( '.ecdv2-panel.is-active' ).each( function() {
			var top = this.getBoundingClientRect().top;
			if ( top <= line && top > best_top ) {
				best_top = top;
				best = this;
			}
			if ( top < first_top ) {
				first_top = top;
				first = this;
			}
		} );
		/* At the very bottom the last short sections can never reach the line: take the lowest one in view. */
		if ( window.innerHeight + window.pageYOffset >= document.documentElement.scrollHeight - 4 ) {
			var low = null;
			var low_top = -Infinity;
			$( '.ecdv2-panel.is-active' ).each( function() {
				var top = this.getBoundingClientRect().top;
				if ( top < window.innerHeight - 40 && top > low_top ) {
					low_top = top;
					low = this;
				}
			} );
			best = low || best;
		}
		var node = best || first;
		if ( node ) {
			var key = $( node ).attr( 'data-ecdv2-panel' );
			if ( key !== $( '.ecdv2-tab.is-active' ).attr( 'data-ecdv2-tab' ) ) {
				set_rail_active( key );
			}
		}
	}

	/* Page view: feeds and the PRO media panel load as their section comes into view. */
	var feed_observer = null;
	function watch_feeds() {
		if ( 'function' !== typeof window.IntersectionObserver ) {
			$( '.ecdv2-panel.is-active' ).each( function() {
				load_panel_feeds( $( this ).attr( 'data-ecdv2-panel' ) );
			} );
			return;
		}
		if ( ! feed_observer ) {
			feed_observer = new window.IntersectionObserver( function( entries ) {
				$.each( entries, function( i, entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}
					var $p = $( entry.target );
					if ( $p.hasClass( 'is-active' ) && ! $p.hasClass( 'is-collapsed' ) ) {
						load_panel_feeds( $p.attr( 'data-ecdv2-panel' ) );
						if ( 'media' === $p.attr( 'data-ecdv2-panel' ) ) {
							refresh_media_pro();
						}
					}
				} );
			}, { rootMargin: '200px 0px' } );
		}
		$( '.ecdv2-panel' ).each( function() {
			feed_observer.observe( this );
		} );
	}

	/* ---------- Folded sections say what is set ---------- */

	function count_pictures() {
		var seen = {};
		var n = 0;
		$( '#ecdv2_media_pro_wrap .ec_admin_product_image, #ecdv2_legacy_pro_media .ec_admin_product_image' ).each( function() {
			var id = $( this ).attr( 'data-attachment_id' ) || String( n );
			if ( ! seen[ id ] ) {
				seen[ id ] = true;
				n++;
			}
		} );
		if ( ! n ) {
			$.each( [ 'image1', 'image2', 'image3', 'image4', 'image5' ], function( i, id ) {
				if ( el( id ) && $.trim( v( id ) ) && -1 === $.inArray( $.trim( v( id ) ), [ 'image1', 'image2', 'image3', 'image4', 'image5' ] ) ) {
					n++;
				}
			} );
		}
		return n;
	}

	function counted( one_key, many_key, n, one_fb, many_fb ) {
		return 1 === n ? vt( one_key, one_fb ) : vt( many_key, many_fb ).replace( '%d', n );
	}

	function type_text() {
		var names = [];
		if ( vcfg && vcfg.type_flags ) {
			$.each( vcfg.type_flags, function( flag, label ) {
				if ( el( flag ) && 1 === parseInt( c( flag ), 10 ) ) {
					names.push( label );
				}
			} );
		}
		return names.length ? names.join( ' + ' ) : vt( 'standard', 'Standard product' );
	}

	function option_set_names() {
		var names = [];
		$( '#wp-easycart-pro-basic-options .wp-easycart-pro-option-table-row-sortable' ).not( '.wp-easycart-pro-option-modifer-row' ).each( function() {
			var name = $.trim( $( this ).find( '.wp-easycart-pro-option-table-column-bold' ).first().text() );
			if ( name ) {
				names.push( name );
			}
		} );
		if ( ! names.length ) {
			$( '#ecdv2_opt_slots select' ).each( function() {
				if ( parseInt( $( this ).val(), 10 ) > 0 ) {
					names.push( $.trim( $( this ).find( 'option:selected' ).text() ) );
				}
			} );
		}
		return names;
	}

	var summaries = {
		general: function() {
			return [ $.trim( v( 'title' ) ) || vt( 'no_title', 'No title yet' ), $.trim( v( 'model_number' ) ) || vt( 'no_sku', 'no SKU' ) ].join( ' · ' );
		},
		media: function() {
			var n = count_pictures();
			return n ? counted( 'picture_one', 'pictures', n, '1 picture', '%d pictures' ) : vt( 'no_pictures', 'No pictures yet' );
		},
		pricing: function() {
			var price = parseFloat( v( 'price' ) ) || 0;
			var list = parseFloat( v( 'list_price' ) ) || 0;
			return [ money( price ) ].concat( list > price ? [ vt( 'compare_at', 'compare-at %s' ).replace( '%s', money( list ) ) ] : [] ).join( ' · ' );
		},
		/* 6.0.5: the taxes it is charged in this store, as the summary column's card says them. */
		tax: function() {
			if ( ! tax_cfg() ) {
				return '';
			}
			var t = tax_card();
			return ( t.taxed || ! tax_cfg().any ) ? t.sub : t.title;
		},
		inventory: function() {
			var type = String( v( 'stock_quantity_type' ) );
			return '0' === type ? vt( 'not_tracked', 'Stock not tracked' ) : ( '2' === type ? vt( 'per_variant', 'Stock per variant' ) : vt( 'in_stock', '%d in stock' ).replace( '%d', parseInt( v( 'stock_quantity' ), 10 ) || 0 ) );
		},
		shipping: function() {
			if ( ! ships() ) {
				return vt( 'no_ship', 'Doesn’t ship' );
			}
			var u = ship_units();
			var weight = with_unit( v( 'weight' ), u.weight );
			var size = ship_size();
			return [ 1 === parseInt( c( 'exclude_shippable_calculation' ), 10 ) ? vt( 'ships_free', 'Ships free' ) : vt( 'ships', 'Ships to the customer' ), weight || vt( 'no_weight', 'no weight yet' ) ].concat( size ? [ size ] : [] ).join( ' · ' );
		},
		options: function() {
			var names = option_set_names();
			return names.length ? names.join( ' × ' ) : vt( 'no_options', 'No options: sold as one item' );
		},
		organize: function() {
			var menus = 0;
			var related = 0;
			$.each( [ 1, 2, 3 ], function( i, n ) {
				if ( parseInt( v( 'menulevel1_id_' + n ), 10 ) > 0 ) {
					menus++;
				}
			} );
			$.each( [ 1, 2, 3, 4 ], function( i, n ) {
				var val = v( 'featured_product_id_' + n );
				if ( val && '0' !== String( val ) ) {
					related++;
				}
			} );
			return [ counted( 'menu_one', 'menus', menus, '1 menu', '%d menus' ), counted( 'related_one', 'related', related, '1 related product', '%d related products' ) ].join( ' · ' );
		},
		behavior: function() {
			return type_text();
		},
		/* 6.0.4: the badge and the picture's hover effect. */
		design: function() {
			var badge = '0' !== String( v( 'tag_type' ) || '0' ) && $.trim( v( 'tag_text' ) );
			var parts = [ badge ? vt( 'badge', 'Badge: %s' ).replace( '%s', badge ) : vt( 'no_badge', 'No badge' ) ];
			var hover = el( 'image_hover_type' );
			if ( hover && $( hover ).find( 'option:selected' ).length ) {
				parts.push( $.trim( $( hover ).find( 'option:selected' ).text() ) );
			}
			return parts.join( ' · ' );
		},
		seo: function() {
			var written = seo_written();
			if ( null === written ) {
				return '';
			}
			return written ? vt( 'seo_set', 'Search description written' ) : vt( 'seo_missing', 'No search description yet' );
		},
		notes: function() {
			var ids = [ 'order_completed_note', 'order_completed_email_note', 'order_completed_details_note' ];
			var written = 0;
			$.each( ids, function( i, id ) {
				if ( $.trim( String( ed( id ) || '' ).replace( /<[^>]*>/g, '' ) ) ) {
					written++;
				}
			} );
			return vt( 'notes', '%1$d of %2$d order notes written' ).replace( '%1$d', written ).replace( '%2$d', ids.length );
		}
	};

	/* 6.0.5: a search description is written when Yoast SEO's meta description holds text ( its card shows while Yoast is
	 * active ) or WP EasyCart's own does ( the store gives Yoast its own for a page whose Yoast description is empty ). Before,
	 * only WP EasyCart's counted, so a product described in Yoast read "No search description yet". null = neither field shows. */
	function seo_written() {
		var yoast = el( 'ecdv2_yoast_metadesc' );
		var own = el( 'seo_description' );
		if ( ! yoast && ! own ) {
			return null;
		}
		return ( !! yoast && '' !== $.trim( v( 'ecdv2_yoast_metadesc' ) ) ) || ( !! own && '' !== $.trim( v( 'seo_description' ) ) );
	}

	function paint_summary( key ) {
		var $sum = panel( key ).find( '> .ecdv2-panel-head [data-ecdv2-sum]' );
		if ( ! $sum.length ) {
			return;
		}
		var text = '';
		if ( summaries[ key ] ) {
			try {
				text = summaries[ key ]();
			} catch ( e ) {
				text = '';
			}
		}
		$sum.text( text );
	}

	function paint_summaries() {
		$( '.ecdv2-panel.is-collapsed' ).each( function() {
			paint_summary( $( this ).attr( 'data-ecdv2-panel' ) );
		} );
	}

	/* ---------- The summary column ---------- */

	/* Listing health, re-checked as fields change. Keys this script cannot work out keep their state from the page load. */
	var health_rules = {
		image             : { tab: 'media', test: function() { return count_pictures() > 0 || !! $( '#ecdv2_header_thumb img' ).length; } },
		description       : { tab: 'general', field: 'description', test: function() { return $.trim( String( ed( 'description' ) || '' ).replace( /<[^>]*>/g, '' ) ).length > 20; } },
		short_description : { tab: 'general', field: 'short_description', test: function() { return '' !== $.trim( v( 'short_description' ) ); } },
		price             : { tab: 'pricing', field: 'price', test: function() { return parseFloat( v( 'price' ) ) > 0 || 1 === parseInt( c( 'is_donation' ), 10 ) || 1 === parseInt( c( 'inquiry_mode' ), 10 ); } },
		category          : { tab: 'categories', test: function() { return $( '#ecdv2_cat_tokens .ecdv2-cat-token' ).length > 0; } },
		seo               : { tab: 'seo', get field() { return el( 'ecdv2_yoast_metadesc' ) ? 'ecdv2_yoast_metadesc' : 'seo_description'; }, test: function() { return seo_written(); } },
		weight            : { tab: 'shipping', field: 'weight', test: function() { return 1 !== parseInt( c( 'is_shippable' ), 10 ) || parseFloat( v( 'weight' ) ) > 0; } },
		stock             : { tab: 'inventory', field: 'stock_quantity_type', test: function() { return '1' !== String( v( 'stock_quantity_type' ) ) || ( parseInt( v( 'stock_quantity' ), 10 ) || 0 ) > 0; } },
		active            : { tab: 'general', field: 'activate_in_store', test: function() { return 1 === parseInt( c( 'activate_in_store' ), 10 ); } }
	};

	function paint_health() {
		var $list = $( '#ecdv2_health_list' );
		if ( ! $list.length ) {
			return;
		}
		var done = 0;
		var total = 0;
		var todo_tabs = {};
		$list.children( '.ecdv2-side-health-item' ).each( function() {
			var $item = $( this );
			var rule = health_rules[ $item.attr( 'data-ecdv2-check' ) ];
			var ok = $item.hasClass( 'is-done' );
			if ( rule ) {
				var answer = rule.test();
				if ( null !== answer ) {
					ok = !! answer;
				}
			}
			$item.toggleClass( 'is-done', ok ).toggleClass( 'is-todo', ! ok );
			total++;
			if ( ok ) {
				done++;
			} else {
				todo_tabs[ rule ? rule.tab : $item.attr( 'data-ecdv2-tab' ) ] = true;
			}
		} );
		/* Unfinished first. */
		$list.append( $list.children( '.is-done' ) );
		$( '#ecdv2_health_count' ).text( vt( 'health_count', '%1$d of %2$d' ).replace( '%1$d', done ).replace( '%2$d', total ) );
		/* 6.0.4: the header's Summary button counts what is left ( the drawer hides the list ). */
		$( '#ecdv2_side_toggle_num' ).text( total - done );
		$( '#ecdv2_side_toggle_count' ).prop( 'hidden', total - done < 1 );
		$( '#ecdv2_health_fill' ).css( 'width', ( total ? Math.round( done / total * 100 ) : 0 ) + '%' );
		$( '.ecdv2-tab[data-ecdv2-tab]' ).each( function() {
			$( this ).toggleClass( 'has-todo', !! todo_tabs[ $( this ).attr( 'data-ecdv2-tab' ) ] );
		} );
	}

	function go_check( key, tab ) {
		var rule = health_rules[ key ];
		var target = rule ? rule.tab : tab;
		if ( ! target ) {
			return;
		}
		if ( 'categories' === target ) {
			go_tab( 'categories' );
			return;
		}
		var field = rule && rule.field ? el( rule.field ) : null;
		if ( 'activate_in_store' === ( rule && rule.field ) && field ) {
			field.focus();
			flash( $( field ).closest( '.ecdv2-status-toggle' ) );
			return;
		}
		if ( ! field ) {
			go_tab( target );
			return;
		}
		go_tab( target, { no_scroll: true } );
		show_pane_of( field );
		var $box = $( '[data-ecdv2-wpeditor="' + rule.field + '"]' );
		$box = $box.length ? $box : $( field ).closest( '.ecdv2-field, .ecdv2-toggle-row' );
		$box = $box.length ? $box : $( field );
		if ( $box.get( 0 ) && $box.get( 0 ).scrollIntoView ) {
			spy_hold_until = Date.now() + 900;
			$box.get( 0 ).scrollIntoView( { block: 'center', behavior: reduce_motion ? 'auto' : 'smooth' } );
		}
		flash( $box );
		setTimeout( function() {
			if ( 'description' === rule.field && typeof tinymce !== 'undefined' && tinymce.get( 'description' ) && ! $( field ).is( ':visible' ) ) {
				tinymce.get( 'description' ).focus();
				return;
			}
			try { field.focus( { preventScroll: true } ); } catch ( e ) { field.focus(); }
		}, 120 );
	}

	function paint_side() {
		if ( ! $( '#ecdv2_side' ).length ) {
			return;
		}
		var active = 1 === parseInt( c( 'activate_in_store' ), 10 );
		$( '#ecdv2_side_status_title' ).text( active ? vt( 'active_title', 'Active' ) : vt( 'draft_title', 'Draft' ) );
		$( '#ecdv2_side_status' ).text( active ? vt( 'active', 'Shoppers can find and buy it' ) : vt( 'draft', 'Hidden from your store until you switch it on' ) );
		$( '#ecdv2_side_type' ).text( type_text() );
		paint_ship_state();
		paint_ship_card();
		paint_tax_state();
		paint_tax_card();
		paint_quick_links();
		/* The product card. */
		var price = parseFloat( v( 'price' ) ) || 0;
		var list = parseFloat( v( 'list_price' ) ) || 0;
		$( '#ecdv2_pv_title' ).text( $.trim( v( 'title' ) ) || vt( 'no_title', 'No title yet' ) );
		$( '#ecdv2_pv_price' ).text( money( price ) );
		$( '#ecdv2_pv_was' ).text( money( list ) ).prop( 'hidden', ! ( list > price ) );
		var badge_type = String( v( 'tag_type' ) || '0' );
		var badge_text = $.trim( v( 'tag_text' ) );
		var $badge = $( '#ecdv2_pv_badge' );
		if ( '0' !== badge_type && '' !== badge_type && badge_text ) {
			$badge.text( badge_text ).css( { background: v( 'tag_bg_color' ) || '', color: v( 'tag_text_color' ) || '' } ).prop( 'hidden', false );
		} else {
			$badge.prop( 'hidden', true );
		}
		var out = '1' === String( v( 'stock_quantity_type' ) ) && ( parseInt( v( 'stock_quantity' ), 10 ) || 0 ) <= 0;
		$( '#ecdv2_pv_stock' ).text( vt( 'out_of_stock', 'Out of stock' ) ).prop( 'hidden', ! out );
		paint_health();
	}

	/* ---------- 6.0.4: Shipping ( one box ) and its card in the summary column ---------- */

	function ships() {
		return ! el( 'is_shippable' ) || 1 === parseInt( c( 'is_shippable' ), 10 );
	}

	/* A number as it reads in the store's unit ( 0.400 → 0.4 lb ). */
	function with_unit( number, unit ) {
		var n = parseFloat( number );
		if ( ! ( n > 0 ) ) {
			return '';
		}
		return vt( 'with_unit', '%1$s %2$s' ).replace( '%1$s', String( Math.round( n * 1000 ) / 1000 ) ).replace( '%2$s', unit || '' );
	}

	function ship_units() {
		var $card = $( '#ecdv2_side_shipping' );
		return { weight: $card.attr( 'data-weight-unit' ) || 'lb', dim: $card.attr( 'data-dim-unit' ) || 'in' };
	}

	/* L × W × H, or '' while any side is missing. */
	function ship_size() {
		var u = ship_units();
		var l = parseFloat( v( 'length' ) );
		var w = parseFloat( v( 'width' ) );
		var h = parseFloat( v( 'height' ) );
		if ( ! ( l > 0 && w > 0 && h > 0 ) ) {
			return '';
		}
		var r = function( n ) {
			return String( Math.round( n * 100 ) / 100 );
		};
		return r( l ) + ' × ' + r( w ) + ' × ' + r( h ) + ' ' + u.dim;
	}

	/* The section: everything after the switch folds away behind a note while the product does not ship. */
	function paint_ship_state() {
		var $p = panel( 'shipping' );
		if ( ! $p.length || ! el( 'is_shippable' ) ) {
			return;
		}
		$p.toggleClass( 'is-noship', ! ships() );
		var parts = [];
		var hs = $.trim( v( 'hs_code' ) );
		if ( hs ) {
			parts.push( 'HS ' + hs );
		}
		var origin = el( 'country_of_origin' );
		if ( origin && $( origin ).val() ) {
			parts.push( $.trim( $( origin ).find( 'option:selected' ).text() ) );
		}
		var $sum = $( '#ecdv2_customs_sum' );
		if ( $sum.length ) {
			if ( ! $sum.attr( 'data-base' ) ) {
				$sum.attr( 'data-base', $sum.text() );
			}
			$sum.text( [ $sum.attr( 'data-base' ) ].concat( parts ).join( ' · ' ) );
		}
	}

	/* The summary column's card under Product type. */
	function paint_ship_card() {
		var $card = $( '#ecdv2_side_shipping' );
		if ( ! $card.length ) {
			return;
		}
		var on = ships();
		var u = ship_units();
		var weight = with_unit( v( 'weight' ), u.weight );
		var free = 1 === parseInt( c( 'exclude_shippable_calculation' ), 10 );
		var warn = on && '1' === $card.attr( 'data-needs-weight' ) && ! weight && ! free;
		$card.toggleClass( 'is-noship', ! on ).toggleClass( 'is-warn', warn );
		$( '#ecdv2_side_ship_title' ).text( on ? ( free ? vt( 'ships_free', 'Ships free' ) : vt( 'ships', 'Ships to the customer' ) ) : vt( 'no_ship', 'Doesn’t ship' ) );
		$( '#ecdv2_side_ship_facts' ).prop( 'hidden', ! on );
		$( '#ecdv2_side_ship_warn' ).prop( 'hidden', ! warn );
		$( '#ecdv2_side_ship_weight' ).text( weight || vt( 'not_set', 'Not set' ) );
		$( '#ecdv2_side_ship_size' ).text( ship_size() || vt( 'not_set', 'Not set' ) );
		var packs = el( 'packs_in' );
		var packs_text = '';
		if ( packs ) {
			var pv = String( $( packs ).val() );
			packs_text = '0' === pv || '' === pv ? vt( 'packs_auto', 'Best box, automatic' ) : ( '-1' === pv ? vt( 'packs_own', 'Its own box' ) : $.trim( $( packs ).find( 'option:selected' ).text() ) );
		}
		$( '#ecdv2_side_ship_packs' ).text( packs_text || vt( 'packs_auto', 'Best box, automatic' ) ).closest( 'dd' ).prev( 'dt' ).addBack().toggle( !! packs );
		var charges = [];
		if ( free ) {
			charges.push( vt( 'left_out', 'Left out of your rates' ) );
		}
		var per_order = parseFloat( v( 'handling_price' ) ) || 0;
		var per_item = parseFloat( v( 'handling_price_each' ) ) || 0;
		if ( per_order > 0 ) {
			charges.push( vt( 'handling_order', '%s handling per order' ).replace( '%s', money( per_order ) ) );
		}
		if ( per_item > 0 ) {
			charges.push( vt( 'handling_item', '%s per item' ).replace( '%s', money( per_item ) ) );
		}
		if ( 1 === parseInt( c( 'ship_to_billing' ), 10 ) ) {
			charges.push( vt( 'billing_address', 'Ships to the billing address' ) );
		}
		$( '#ecdv2_side_ship_charges' ).text( charges.length ? charges.join( '; ' ) : vt( 'no_handling', 'Your shipping rates' ) );
		var key = type_key();
		$( '#ecdv2_side_ship_off' ).prop( 'hidden', on ).text( 'download' === key ? vt( 'off_download', '' ) : ( 'service' === key ? vt( 'off_service', '' ) : vt( 'off_other', 'Checkout asks no shipping address and adds no shipping for it.' ) ) || vt( 'off_other', '' ) );
	}

	/* ---------- 6.0.5: the Tax section and its card in the summary column ---------- */

	/* What the store charges ( wp_easycart_admin_details_products_v2::tax_config_v2() ). */
	function tax_cfg() {
		return ( vcfg && vcfg.tax && vcfg.tax.lead ) ? vcfg.tax : null;
	}

	/* A switch of the product: sales ( Charge sales tax ) or vat ( Charge VAT / GST ). */
	function tax_on( kind ) {
		var id = 'vat' === kind ? 'vat_rate' : 'is_taxable';
		return !! el( id ) && 1 === parseInt( c( id ), 10 );
	}

	/* The product's VAT / GST rate class ( '' = the standard rate ), and that class's settings. */
	function tax_class_key() {
		return el( 'tax_class' ) ? String( v( 'tax_class' ) || '' ) : '';
	}

	function tax_class_info() {
		var cfg = tax_cfg();
		var classes = ( cfg && cfg.classes ) ? cfg.classes : {};
		return classes[ tax_class_key() ] || classes[''] || null;
	}

	/* "VAT 20%", "VAT 5% · Reduced rate" ( tax_vat_text() in PHP ). */
	function tax_vat_text() {
		var cfg = tax_cfg();
		var info = tax_class_info();
		var text = ( info && info.pct ) ? vt( 'tax_rate_pct', '%1$s %2$s' ).replace( '%1$s', cfg.vat_name ).replace( '%2$s', info.pct ) : cfg.vat_name;
		return ( '' !== tax_class_key() && info ) ? text + ' · ' + info.label : text;
	}

	/* The summary card's words ( tax_card_text() in PHP ): the taxes the store charges that are on for this product. */
	function tax_card() {
		var cfg = tax_cfg();
		var parts = [];
		$.each( cfg.lead.concat( cfg.other ), function( i, kind ) {
			if ( ! tax_on( kind ) || ( cfg.any && ! cfg[ kind ] ) ) {
				return;
			}
			parts.push( 'vat' === kind ? tax_vat_text() : ( cfg.service || cfg.sales_name || vt( 'tax_sales', 'Sales tax' ) ) ); /* 6.0.5: by the store's country */
		} );
		var taxed = parts.length > 0;
		return {
			taxed: taxed,
			warn: ! cfg.any,
			title: taxed ? vt( 'taxable', 'Taxable' ) : vt( 'not_taxable', 'Not taxable' ),
			sub: ! cfg.any ? vt( 'tax_store_none', 'Your store doesn’t charge tax yet' ) : ( taxed ? parts.join( ' · ' ) : vt( 'tax_no_tax', 'Checkout adds no tax for it' ) )
		};
	}

	/* 6.0.4: the summary column's Tax card, under Shipping. */
	function paint_tax_card() {
		var $card = $( '#ecdv2_side_tax' );
		if ( ! $card.length || ! el( 'is_taxable' ) || ! tax_cfg() ) {
			return;
		}
		var t = tax_card();
		$card.toggleClass( 'is-off', ! t.taxed ).toggleClass( 'is-warn', t.warn );
		$( '#ecdv2_side_tax_title' ).text( t.title );
		$( '#ecdv2_side_tax_sub' ).text( t.sub );
	}

	/* The section: the switches' boxes, the note while nothing is charged, the rate, the folded taxes' words and the code hint. */
	function paint_tax_state() {
		var cfg = tax_cfg();
		if ( ! cfg || ! $( '#ecdv2_tax' ).length ) {
			return;
		}
		var vat = tax_on( 'vat' );
		$( '#ecdv2_tax_switch_sales' ).toggleClass( 'is-on', tax_on( 'sales' ) );
		$( '#ecdv2_tax_switch_vat' ).toggleClass( 'is-on', vat );
		$( '#ecdv2_tax_off' ).prop( 'hidden', tax_card().taxed );
		$( '#ecdv2_tax_rate' ).prop( 'hidden', ! vat );
		$.each( cfg.other, function( i, kind ) {
			var $sum = $( '#ecdv2_tax_other_' + kind + '_sum' );
			if ( $sum.length ) {
				$sum.text( $sum.attr( 'data-ecdv2-tax-why' ) + ' · ' + ( tax_on( kind ) ? vt( 'tax_on_product', 'On for this product' ) : vt( 'tax_off', 'Off' ) ) );
			}
		} );
		var code = $.trim( String( v( 'TIC' ) || '' ) );
		$( '#ecdv2_tax_code_hint' ).prop( 'hidden', '' !== code && '0' !== code && '00000' !== code ); /* 00000: TaxCloud's general goods */
		paint_tax_rate();
	}

	/* What a shopper pays at the chosen rate, on the price as it is typed; a class with no rate of its own says so. */
	function paint_tax_rate() {
		var cfg = tax_cfg();
		var $note = $( '#ecdv2_tax_rate_note' );
		var $warn = $( '#ecdv2_tax_rate_warn' );
		if ( ! cfg || ! $note.length ) {
			return;
		}
		var name = cfg.vat_name || 'VAT';
		var key = tax_class_key();
		var info = tax_class_info();
		var standard = ( cfg.classes && cfg.classes[''] ) ? cfg.classes[''] : null;
		var text = '';
		if ( ! cfg.vat ) {
			text = vt( 'tax_store_novat', 'Your store doesn’t charge %s yet, so nothing is added.' ).replace( '%s', name );
		} else if ( ! info || null === info.rate || undefined === info.rate ) {
			text = cfg.by_country ? vt( 'tax_country_rate', 'Each shopper pays their own country’s %s rate.' ).replace( '%s', name ) : '';
		} else if ( parseFloat( info.rate ) <= 0 ) {
			text = vt( 'tax_zero', 'At the zero rate, no %s is charged on it.' ).replace( '%s', name );
		} else {
			var rate = parseFloat( info.rate );
			var price = parseFloat( v( 'price' ) ) || 0;
			if ( price <= 0 ) {
				text = vt( 'tax_at', '%1$s at %2$s.' ).replace( '%1$s', name ).replace( '%2$s', info.pct );
			} else {
				var amount = cfg.vat_included ? price - price / ( 1 + rate / 100 ) : price * rate / 100;
				text = ( cfg.vat_included ? vt( 'tax_included', 'At %1$s, %2$s of the %3$s price is %4$s.' ) : vt( 'tax_added', 'At %1$s, checkout adds %2$s %4$s to the %3$s price.' ) )
					.replace( '%1$s', info.pct ).replace( '%2$s', money( amount ) ).replace( '%3$s', money( price ) ).replace( '%4$s', name );
			}
			if ( cfg.by_country && '' === key ) {
				text += ' ' + vt( 'tax_by_country', 'Shoppers in other countries pay their own country’s rate.' );
			}
		}
		$note.text( text ).prop( 'hidden', '' === text );
		if ( cfg.vat && '' !== key && 'zero' !== key && info && ! info.set ) {
			var words = ( standard && standard.pct ) ? vt( 'tax_class_unset', '%1$s has no rate set yet, so it charges the standard %2$s.' ).replace( '%1$s', info.label ).replace( '%2$s', standard.pct ) : vt( 'tax_class_unset_plain', '%s has no rate set yet, so it charges the standard rate.' ).replace( '%s', info.label );
			$warn.empty().append( document.createTextNode( words + ' ' ) ).append( $( '<a target="_blank" rel="noopener"></a>' ).attr( 'href', String( $warn.attr( 'data-href' ) || '' ) + encodeURIComponent( 'ec_option_tax_class_' + key + '_rate' ) ).text( vt( 'tax_class_set', 'Set its rate' ) ) ).prop( 'hidden', false );
		} else {
			$warn.prop( 'hidden', true ).empty();
		}
	}

	/* 6.0.4: the quick links under Basics' title and SKU ( the price and the manufacturer, set elsewhere ). */
	function paint_quick_links() {
		if ( ! $( '#ecdv2_quick_links' ).length ) {
			return;
		}
		if ( el( 'price' ) ) {
			$( '#ecdv2_quick_price' ).text( money( parseFloat( v( 'price' ) ) || 0 ) );
		}
		var maker = el( 'manufacturer_id' );
		if ( maker ) {
			var id = String( $( maker ).val() || '' );
			$( '#ecdv2_quick_manufacturer' ).text( '' !== id && '0' !== id ? $.trim( $( maker ).find( 'option:selected' ).text() ) : vt( 'no_manufacturer', 'None' ) );
		}
	}

	/* A new first picture ( the PRO media panel redrawn ) shows on the card. */
	function paint_card_image() {
		var src = $( '#ecdv2_media_pro_wrap .ec_admin_product_image img, #ecdv2_legacy_pro_media .ec_admin_product_image img' ).first().attr( 'src' );
		if ( ! src ) {
			return;
		}
		var $img = $( '#ecdv2_pv_img img' );
		if ( ! $img.length ) {
			$( '#ecdv2_pv_img .dashicons' ).remove();
			$img = $( '<img alt="" />' ).prependTo( '#ecdv2_pv_img' );
		}
		$img.attr( 'src', src );
	}

	/* The column stays in view: under the header while it fits the window, else it scrolls until its foot ( listing health,
	   the card, sales ) reaches the bottom of the window and stays there. */
	function fit_side() {
		var side = el( 'ecdv2_side' );
		var header = $( '#ecdv2_wrap .ecdv2-header' ).get( 0 );
		/* Under the header as it really is ( it wraps to two lines on a narrower screen ). */
		var top = header ? Math.round( parseFloat( window.getComputedStyle( header ).top ) || 0 ) + header.offsetHeight + 14 : 96;
		if ( window.innerWidth > 782 ) {
			$( '#ecdv2_wrap .ecdv2-rail' ).css( 'top', top + 'px' );
		}
		if ( ! side ) {
			return;
		}
		/* 6.0.4: a drawer is placed by side_drawer(), not stuck under the header. */
		if ( side_is_drawer() ) {
			$( side ).css( 'top', '' ).removeClass( 'is-sticky' );
			if ( $( '#ecdv2_wrap' ).hasClass( 'ecdv2-side-open' ) ) {
				place_side_drawer();
			}
			return;
		}
		/* 6.0.5: a card brought into view ( side_reveal() ) keeps the column where it is until the person scrolls on: the column's own
		   changes ( the sales figures arriving ) would otherwise put it back at the window's foot. */
		if ( side_reveal_back ) {
			return;
		}
		var fits = side.offsetHeight + top + 16 <= window.innerHeight;
		$( side ).css( 'top', ( fits ? top : Math.min( top, window.innerHeight - side.offsetHeight - 16 ) ) + 'px' ).addClass( 'is-sticky' );
	}

	/* The editor's width decides its columns ( the WordPress and EasyCart menus can be folded, so the window's width can't ). */
	function fit_columns() {
		var wrap = el( 'ecdv2_wrap' );
		if ( ! wrap || ! $( wrap ).hasClass( 'ecdv2-product' ) ) {
			return;
		}
		var w = wrap.getBoundingClientRect().width;
		$( wrap ).toggleClass( 'ecdv2-w3', w >= 960 ).toggleClass( 'ecdv2-w2', w >= 760 && w < 960 ).toggleClass( 'ecdv2-w1', w < 760 ).toggleClass( 'ecdv2-roomy', w >= 1240 );
		/* 6.0.4: narrower than the three columns, the summary column is a drawer ( it went above or below the fields ). */
		var drawer = w < 960 && !! el( 'ecdv2_side' );
		if ( drawer !== side_is_drawer() ) {
			if ( ! drawer ) {
				side_drawer( false, { no_focus: true } );
			}
			$( wrap ).toggleClass( 'ecdv2-side-drawer', drawer );
		}
		side_roles();
		fit_side();
	}

	/* ---------- 6.0.4: the summary column as a drawer ( the editor narrower than 960px ) ---------- */

	function side_is_drawer() {
		return $( '#ecdv2_wrap' ).hasClass( 'ecdv2-side-drawer' );
	}

	function side_is_open() {
		return $( '#ecdv2_wrap' ).hasClass( 'ecdv2-side-open' );
	}

	/* A drawer is a dialog; beside the fields the column is the page's summary. */
	function side_roles() {
		var side = el( 'ecdv2_side' );
		if ( ! side ) {
			return;
		}
		if ( side_is_drawer() ) {
			side.setAttribute( 'role', 'dialog' );
			side.setAttribute( 'aria-modal', 'true' );
			side.setAttribute( 'aria-labelledby', 'ecdv2_side_title' );
		} else {
			side.removeAttribute( 'role' );
			side.removeAttribute( 'aria-modal' );
			side.removeAttribute( 'aria-labelledby' );
		}
	}

	/* The drawer starts under the editor's sticky header, so Save and the Summary button stay in reach. */
	function place_side_drawer() {
		var wrap = el( 'ecdv2_wrap' );
		var header = $( '#ecdv2_wrap .ecdv2-header' ).get( 0 );
		if ( ! wrap || ! header ) {
			return;
		}
		wrap.style.setProperty( '--ecdv2-drawer-top', Math.max( 0, Math.round( header.getBoundingClientRect().bottom ) + 8 ) + 'px' );
	}

	var side_return_focus = null;
	var side_scroll_back = null;

	/* At once, whatever the page's scroll-behavior ( the drawer measures the header right after ). */
	function side_scroll_now( y ) {
		try {
			window.scrollTo( { top: y, left: 0, behavior: 'instant' } );
		} catch ( e ) {
			window.scrollTo( 0, y );
		}
	}

	/**
	 * Opens or closes the summary drawer ( only while the column is one ).
	 *
	 * @param {boolean} open      true, false, or undefined to toggle.
	 * @param {Object}  opts      no_focus: leave the focus where it is ( a link inside the drawer takes it to a field ).
	 */
	function side_drawer( open, opts ) {
		opts = opts || {};
		if ( true !== open && false !== open ) {
			open = ! side_is_open();
		}
		if ( open && ! side_is_drawer() ) {
			return;
		}
		if ( open === side_is_open() ) {
			if ( open ) {
				place_side_drawer();
			}
			return;
		}
		if ( open ) {
			side_return_focus = document.activeElement;
			/* At the top of the page the header has not stuck yet, so the drawer would start halfway down: the page moves the
			   header to its place first ( and back when the drawer closes ). */
			side_scroll_back = null;
			var header = $( '#ecdv2_wrap .ecdv2-header' ).get( 0 );
			if ( header ) {
				var stuck = parseFloat( window.getComputedStyle( header ).top ) || 0;
				var gap = header.getBoundingClientRect().top - stuck;
				if ( gap > 1 ) {
					side_scroll_back = window.pageYOffset;
					side_scroll_now( window.pageYOffset + gap );
				}
			}
			place_side_drawer();
		}
		$( '#ecdv2_wrap' ).toggleClass( 'ecdv2-side-open', open );
		$( 'html' ).toggleClass( 'ecdv2-side-locked', open );
		if ( ! open && null !== side_scroll_back ) {
			/* Closed by its button, Escape or the backdrop: back where the page was. A link to a section scrolls there itself. */
			if ( ! opts.no_focus ) {
				side_scroll_now( side_scroll_back );
			}
			side_scroll_back = null;
		}
		$( '#ecdv2_side_toggle' ).attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open && ! opts.no_focus ) {
			setTimeout( function() {
				var close = el( 'ecdv2_side_close' );
				if ( close ) {
					try { close.focus( { preventScroll: true } ); } catch ( e ) { close.focus(); }
				}
			}, 60 );
		}
		if ( ! open ) {
			var back = side_return_focus;
			side_return_focus = null;
			if ( ! opts.no_focus && back && document.contains( back ) && ! $( back ).closest( '#ecdv2_side' ).length ) {
				try { back.focus( { preventScroll: true } ); } catch ( e ) { back.focus(); }
			}
		}
		$( document ).trigger( 'ecdv2:side-drawer', [ open ] );
	}

	/* Escape closes it ( unless a list inside is open ); Tab stays inside it. */
	function side_keydown( e ) {
		if ( ! side_is_open() ) {
			return;
		}
		if ( 'Escape' === e.key || 'Esc' === e.key ) {
			if ( $( '.select2-container--open' ).length || e.defaultPrevented ) {
				return;
			}
			e.preventDefault();
			side_drawer( false );
			return;
		}
		if ( 'Tab' !== e.key ) {
			return;
		}
		var $items = $( '#ecdv2_side' ).find( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' ).filter( function() {
			return ! this.disabled && 'hidden' !== this.type && this.offsetParent !== null && ! $( this ).closest( '[hidden], .select2-hidden-accessible' ).length;
		} );
		if ( ! $items.length ) {
			return;
		}
		var first = $items.get( 0 );
		var last = $items.get( $items.length - 1 );
		if ( ! $( document.activeElement ).closest( '#ecdv2_side' ).length ) {
			e.preventDefault();
			first.focus();
		} else if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	/* ---------- Previous / next product ( the list the merchant came from ) ---------- */

	var nav = null;

	function nav_init() {
		if ( ! vcfg || ! vcfg.product_id ) {
			return;
		}
		var data = null;
		try {
			data = JSON.parse( window.sessionStorage.getItem( 'wpec_product_nav' ) || 'null' );
		} catch ( e ) {
			data = null;
		}
		if ( ! data || ! data.ids || ! data.ids.length || ( Date.now() - ( parseInt( data.t, 10 ) || 0 ) ) > 43200000 ) {
			return;
		}
		var ids = $.map( data.ids, function( x ) { return parseInt( x, 10 ) || null; } );
		var at = $.inArray( parseInt( vcfg.product_id, 10 ), ids );
		if ( -1 === at || ids.length < 2 ) {
			return;
		}
		nav = { prev: ids[ at - 1 ] || 0, next: ids[ at + 1 ] || 0, titles: data.titles || {} };
		var set = function( which, id ) {
			var $a = $( '#ecdv2_nav_' + which );
			if ( id ) {
				$a.attr( 'href', vcfg.edit_url + id ).removeClass( 'is-disabled' ).removeAttr( 'aria-disabled' );
				if ( nav.titles[ id ] ) {
					$a.attr( 'title', String( nav.titles[ id ] ) + ( 'next' === which ? ' ( J )' : ' ( K )' ) );
				}
			} else {
				$a.removeAttr( 'href' ).addClass( 'is-disabled' ).attr( 'aria-disabled', 'true' );
			}
		};
		set( 'prev', nav.prev );
		set( 'next', nav.next );
		$( '#ecdv2_nav_pos' ).text( vt( 'nav_position', '%1$d of %2$d' ).replace( '%1$d', at + 1 ).replace( '%2$d', ids.length ) );
		$( '#ecdv2_nav' ).prop( 'hidden', false );
		$( '#ecdv2_menu_save_next' ).prop( 'hidden', ! nav.next );
		/* Back returns to the list as it was ( filters, search, page ). */
		if ( 'string' === typeof data.back && 0 === data.back.indexOf( window.location.origin ) && -1 !== data.back.indexOf( 'page=wp-easycart-products' ) ) {
			$( '#ecdv2_wrap .ecdv2-header-back' ).attr( 'href', data.back );
		}
	}

	function nav_open( id ) {
		if ( ! id ) {
			return;
		}
		saving = true; /* leaving on purpose: no "unsaved changes" prompt for a saved page */
		window.location.href = vcfg.edit_url + id;
	}

	/* Move to the previous / next product; with unsaved changes, offer to save them first. */
	function nav_go( which ) {
		var id = nav ? nav[ which ] : 0;
		if ( ! id ) {
			if ( 'next' === which ) {
				toast( vt( 'no_next', 'This is the last product in the list you came from.' ), 'info' );
			}
			return;
		}
		if ( ! has_dirty() ) {
			nav_open( id );
			return;
		}
		var name = nav.titles[ id ] ? String( nav.titles[ id ] ) : vt( 'next_product', 'the next product' );
		window.ecdv2_confirm( {
			title: vt( 'save_first', 'Save your changes first?' ),
			message: vt( 'save_first_body', 'This product has changes that are not saved yet. Save them and open %s?' ).replace( '%s', name ),
			confirm_label: vt( 'save_and_open', 'Save and open' ),
			cancel_label: vt( 'stay', 'Stay here' )
		} ).then( function( ok ) {
			if ( ok ) {
				save_then( function() { nav_open( id ); } );
			}
		} );
	}

	function save_then( then ) {
		if ( ! has_dirty() ) {
			then();
			return;
		}
		/* Through window.ecdv2.save_all so the free edition's warnings about data only Pro keeps still ask first. */
		window.ecdv2.save_all( { then: then } );
		if ( ! saving && ! has_dirty() ) {
			then();
		}
	}

	function save_next() {
		if ( ! nav || ! nav.next ) {
			toast( vt( 'no_next', 'This is the last product in the list you came from.' ), 'info' );
			return;
		}
		save_then( function() { nav_open( nav.next ); } );
	}

	/* ---------- Wiring ---------- */

	function dismiss_tip() {
		var $tip = $( '#ecdv2_view_tip' );
		if ( $tip.length ) {
			$tip.remove();
			save_pref( { tip: 1 } );
		}
	}

	function typing_target( node ) {
		return !! ( node && ( /^(INPUT|TEXTAREA|SELECT)$/.test( node.tagName ) || node.isContentEditable ) );
	}

	/* ---------- Product type ( 6.0.4, editor plan Phase 2 ) ---------- */

	/* The type choice writes the Type settings switches ( vcfg.types: key => label, flag, ships, help ); the switches stay the
	 * truth, so a product is whatever type its switches say. A section the type does not use leaves the page and waits in the
	 * rail's "Not used for this type" group, unless it holds something ( stock tracking, option sets ) or was opened. */
	var type_custom = false;
	var type_forced = {};
	var rail_nodes = null;

	function type_list() {
		return ( vcfg && vcfg.types && 'object' === typeof vcfg.types ) ? vcfg.types : {};
	}

	function type_key() {
		var on = [];
		$.each( type_list(), function( key, type ) {
			if ( type.flag && el( type.flag ) && 1 === parseInt( c( type.flag ), 10 ) ) {
				on.push( key );
			}
		} );
		if ( on.length > 1 ) {
			return 'custom';
		}
		if ( 1 === on.length ) {
			return on[0];
		}
		return ( el( 'is_shippable' ) && 1 !== parseInt( c( 'is_shippable' ), 10 ) ) ? 'service' : 'physical';
	}

	function set_switch( name, on ) {
		var node = el( name );
		if ( ! node || node.disabled || 'checkbox' !== node.type || !! node.checked === on ) {
			return;
		}
		$( node ).prop( 'checked', on ).trigger( 'change' );
	}

	function set_type( key ) {
		var types = type_list();
		if ( 'custom' === key ) {
			type_custom = true;
			paint_type( true );
			return;
		}
		if ( ! types[ key ] ) {
			return;
		}
		type_custom = false;
		$.each( types, function( k, type ) {
			if ( type.flag ) {
				set_switch( type.flag, k === key );
			}
		} );
		if ( null !== types[ key ].ships && 'undefined' !== typeof types[ key ].ships ) {
			set_switch( 'is_shippable', 1 === parseInt( types[ key ].ships, 10 ) );
		}
		paint_type( true );
		paint_side();
	}

	/* Sections this type has no use for, while they hold nothing. */
	function type_unused( key ) {
		var out = {}; /* 6.0.4: never Shipping: a product that does not ship says so there */
		if ( ( 'download' === key || 'giftcard' === key || 'donation' === key ) && el( 'stock_quantity_type' ) && '0' === String( v( 'stock_quantity_type' ) ) ) {
			out.inventory = true;
		}
		if ( ( 'giftcard' === key || 'donation' === key ) && ! option_set_names().length ) {
			out.options = true;
		}
		return out;
	}

	/* The rail in its printed order, the unused sections last under their own label ( before Insights ). */
	function paint_rail_unused() {
		var $tabs = $( '#ecdv2_tabs' );
		if ( ! $tabs.length ) {
			return;
		}
		if ( ! rail_nodes ) {
			rail_nodes = $tabs.children().toArray();
		}
		var $label = $( '#ecdv2_unused_label' );
		if ( ! $label.length ) {
			$label = $( '<div class="ecdv2-rail-group ecdv2-rail-unused" id="ecdv2_unused_label" data-ecdv2-mode="edit"></div>' ).text( vt( 'type_unused', 'Not used for this type' ) );
		}
		var unused = [];
		var before = null;
		$.each( rail_nodes, function( i, node ) {
			if ( $( node ).hasClass( 'ecdv2-tab' ) && panel( $( node ).attr( 'data-ecdv2-tab' ) ).hasClass( 'is-unused' ) ) {
				unused.push( node );
				$( node ).addClass( 'is-unused' );
				return;
			}
			$( node ).removeClass( 'is-unused' );
			if ( ! before && 'insights' === $( node ).attr( 'data-ecdv2-mode' ) ) {
				before = node;
			}
			$tabs.append( node );
		} );
		$label.detach();
		if ( unused.length ) {
			var $group = $( [ $label.get( 0 ) ].concat( unused ) );
			if ( before ) {
				$group.insertBefore( before );
			} else {
				$tabs.append( $group );
			}
		}
		/* A group whose sections all moved out keeps no label. */
		$tabs.children( '.ecdv2-rail-group' ).not( $label ).each( function() {
			$( this ).toggleClass( 'is-empty', ! $( this ).nextUntil( '.ecdv2-rail-group' ).filter( '.ecdv2-tab' ).length );
		} );
	}

	/* 6.0.5: the type section is named for the type ( Subscription settings, Download settings …; vcfg.type_sections ), and its bar
	 * says what the product is ( #ecdv2_type_bar ). The rail entry keeps one word, so it stays on one line. */
	function paint_type_section( key ) {
		var sections = ( vcfg && vcfg.type_sections && 'object' === typeof vcfg.type_sections ) ? vcfg.type_sections : {};
		var section = sections[ key ] || sections.custom;
		var types = type_list();
		var type = types[ key ];
		if ( section ) {
			$( '.ecdv2-tab[data-ecdv2-tab="behavior"] .ecdv2-tab-label' ).text( section.rail );
			$( '#ecdv2_panel_title_behavior' ).text( section.title );
			$( '#ecdv2_panel_desc_behavior' ).text( section.desc );
			if ( 'focus' === view ) {
				paint_next();
			}
		}
		if ( $( '#ecdv2_type_bar' ).length ) {
			$( '#ecdv2_type_bar_name' ).text( type ? type.label : vt( 'type_mixed', 'Custom ( more than one type )' ) );
			$( '#ecdv2_type_bar_help' ).text( type ? ( type.help || '' ) : vt( 'type_custom', '' ) );
			var icon = ( type && type.icon ) ? type.icon : 'admin-generic';
			$( '#ecdv2_type_bar_icon' ).attr( 'class', 'ecdv2-type-bar-icon dashicons dashicons-' + icon );
		}
	}

	/* @param {boolean} hide Also hide sections that stopped being used ( a type was chosen, or the page loaded ). Other
	 * changes only bring sections back, so a section never disappears under the person working in it. */
	function paint_type( hide ) {
		var $sel = $( '#ecdv2_type' );
		if ( ! $sel.length ) {
			return;
		}
		var types = type_list();
		var key = type_key();
		if ( 'custom' !== key && type_custom ) {
			key = 'custom';
		}
		$( '#ecdv2_wrap' ).attr( 'data-ecdv2-type', key );
		$sel.val( key );
		$( '#ecdv2_type_help' ).text( 'custom' === key ? vt( 'type_custom', '' ) : ( types[ key ] ? types[ key ].help : '' ) );
		/* A service that manages the product ( Square, a print partner, a plan group ) owns its type switches. */
		var locked = false;
		$.each( types, function( k, type ) {
			if ( type.flag && el( type.flag ) && el( type.flag ).disabled ) {
				locked = true;
			}
		} );
		$sel.prop( 'disabled', locked );
		var unused = type_unused( 'custom' === key ? '' : key );
		$.each( mode_keys( 'edit', true ), function( i, k ) {
			var $p = panel( k );
			if ( unused[ k ] && ! type_forced[ k ] ) {
				if ( hide ) {
					$p.addClass( 'is-unused' );
				}
			} else {
				$p.removeClass( 'is-unused' );
			}
		} );
		paint_rail_unused();
		paint_type_section( key );
	}

	/* Opening a section the type does not use shows it for this visit. */
	function show_unused( key ) {
		type_forced[ key ] = true;
		panel( key ).removeClass( 'is-unused' );
		paint_rail_unused();
	}

	function bind_type() {
		if ( ! $( '#ecdv2_type' ).length ) {
			return;
		}
		$( document ).on( 'change', '#ecdv2_type', function() {
			set_type( $( this ).val() );
		} );
		var watched = [ '#is_shippable', '#stock_quantity_type' ];
		$.each( type_list(), function( key, type ) {
			if ( type.flag ) {
				watched.push( '#' + type.flag );
			}
		} );
		$( document ).on( 'change', watched.join( ', ' ), function() {
			paint_type( false );
		} );
		/* A product that mixes types stays Custom until a type is chosen, so its switches never vanish while it is edited. */
		type_custom = 'custom' === type_key();
		paint_type( true );
	}

	function bind_views() {
		if ( ! vcfg || ! $( '#ecdv2_wrap' ).hasClass( 'ecdv2-product' ) ) {
			return;
		}
		$( document ).on( 'click', '[data-ecdv2-view-set]', function() {
			set_view( $( this ).attr( 'data-ecdv2-view-set' ) );
		} );
		$( document ).on( 'click', '[data-ecdv2-mode-set]', function() {
			set_mode( $( this ).attr( 'data-ecdv2-mode-set' ) );
		} );
		$( document ).on( 'click', '.ecdv2-panel-toggle', function( e ) {
			e.stopPropagation();
			var key = $( this ).attr( 'data-ecdv2-fold' );
			fold( key, ! panel( key ).hasClass( 'is-collapsed' ) );
		} );
		/* 6.0.4: the whole heading row folds and opens a section ( a folded one only opened from it, so an open one did not read
		   as foldable ). */
		$( document ).on( 'click', '.ecdv2-view-page .ecdv2-panel > .ecdv2-panel-head', function( e ) {
			if ( $( e.target ).closest( 'a, input, select, textarea, label' ).length ) {
				return;
			}
			var key = $( this ).parent().attr( 'data-ecdv2-panel' );
			if ( ! $( this ).find( '.ecdv2-panel-toggle' ).length ) {
				return;
			}
			fold( key, ! panel( key ).hasClass( 'is-collapsed' ) );
		} );
		/* The Shipping box follows its switch at once ( the summary column repaints a moment later ). */
		$( document ).on( 'change', '#is_shippable', function() {
			paint_ship_state();
			paint_ship_card();
		} );
		$( document ).on( 'click', '[data-ecdv2-next]', function() {
			go_tab( $( this ).attr( 'data-ecdv2-next' ) );
		} );
		/* Open all / Fold all ( the page view's editing sections ), remembered in one request. */
		$( document ).on( 'click', '[data-ecdv2-fold-all]', function() {
			var folded = '1' === $( this ).attr( 'data-ecdv2-fold-all' );
			var keys = mode_keys( mode ); /* 6.0.4: the sections on show ( editing or Insights ) */
			$.each( keys, function( i, key ) {
				fold( key, folded, { quiet: true } );
			} );
			save_pref( { sections: keys, collapsed: folded ? '1' : '0' } );
			set_rail_active( keys[0] );
			spy_hold_until = Date.now() + 400;
			window.scrollTo( { top: 0 } );
		} );
		$( document ).on( 'click', '[data-ecdv2-go]', function() {
			var field = $( this ).attr( 'data-ecdv2-field' );
			var section = $( this ).attr( 'data-ecdv2-go-section' );
			if ( field && el( field ) ) {
				go_field( field, $( this ).attr( 'data-ecdv2-go' ) );
				return;
			}
			/* 6.0.4: one card of a section ( the summary column's Tax card goes to the Tax card in Pricing ). */
			if ( section && $( '.ecdv2-panel .ecdv2-card[data-ecdv2-section="' + section + '"]' ).length ) {
				go_card( '.ecdv2-panel .ecdv2-card[data-ecdv2-section="' + section + '"]' );
				return;
			}
			go_tab( $( this ).attr( 'data-ecdv2-go' ) );
		} );
		/* 6.0.4: the quick links under the title and the Tax card follow their fields. */
		$( document ).on( 'input change', '#price', paint_quick_links );
		$( document ).on( 'change', '#manufacturer_id', paint_quick_links );
		$( document ).on( 'change', '#is_taxable, #vat_rate', paint_tax_card );
		/* 6.0.5: the Tax section follows its switches, the rate, the code and the price at once. */
		$( document ).on( 'change', '#is_taxable, #vat_rate, #tax_class', function() {
			paint_tax_state();
			paint_tax_card();
		} );
		$( document ).on( 'input change', '#TIC, #price', paint_tax_state );
		$( document ).on( 'click', '.ecdv2-side-health-go', function() {
			var $item = $( this ).closest( '.ecdv2-side-health-item' );
			go_check( $item.attr( 'data-ecdv2-check' ), $item.attr( 'data-ecdv2-tab' ) );
		} );
		$( document ).on( 'click', '#ecdv2_view_tip_ok', dismiss_tip );
		$( document ).on( 'click', '#ecdv2_nav_prev, #ecdv2_nav_next', function( e ) {
			e.preventDefault();
			nav_go( 'ecdv2_nav_next' === this.id ? 'next' : 'prev' );
		} );
		document.addEventListener( 'keydown', function( e ) {
			if ( e.metaKey || e.ctrlKey || e.altKey || typing_target( e.target ) || $( '.ecv2-modal-overlay:visible, .ecdv2-menu.is-open' ).length ) {
				return;
			}
			if ( 'j' === e.key || 'k' === e.key ) {
				if ( nav ) {
					e.preventDefault();
					nav_go( 'j' === e.key ? 'next' : 'prev' );
				}
			}
		} );

		/* The summary column and folded sections follow the fields. */
		var side_timer = 0;
		var repaint = function() {
			clearTimeout( side_timer );
			side_timer = setTimeout( function() {
				paint_side();
				paint_summaries();
			}, 200 );
		};
		$( document ).on( 'change input', '#ecdv2_wrap input, #ecdv2_wrap select, #ecdv2_wrap textarea', repaint );
		if ( typeof tinymce !== 'undefined' && tinymce.on ) {
			tinymce.on( 'AddEditor', function( evt ) {
				if ( evt.editor && evt.editor.on ) {
					evt.editor.on( 'change keyup', repaint );
				}
			} );
			$.each( tinymce.editors || [], function( i, editor ) {
				if ( editor && editor.on ) {
					editor.on( 'change keyup', repaint );
				}
			} );
		}
		$( document ).on( 'ecdv2:media-refreshed ecdv2:options-changed', function() {
			paint_card_image();
			repaint();
		} );
		/* Categories save as they change ( their own requests ): watch the chips. */
		if ( 'function' === typeof window.MutationObserver && el( 'ecdv2_cat_tokens' ) ) {
			new window.MutationObserver( repaint ).observe( el( 'ecdv2_cat_tokens' ), { childList: true } );
		}

		var ticking = false;
		$( window ).on( 'scroll.ecdv2view', function() {
			if ( ticking ) {
				return;
			}
			ticking = true;
			( window.requestAnimationFrame || function( fn ) { setTimeout( fn, 16 ); } )( function() {
				ticking = false;
				spy();
			} );
		} );
		if ( 'function' === typeof window.ResizeObserver ) {
			var ro = new window.ResizeObserver( function() {
				fit_columns();
			} );
			ro.observe( el( 'ecdv2_wrap' ) );
			if ( el( 'ecdv2_side' ) ) {
				ro.observe( el( 'ecdv2_side' ) );
			}
		}
		$( window ).on( 'resize.ecdv2view', fit_columns );
		/* 6.0.4: the summary drawer: the header's Summary button, its close button and the backdrop, Escape and Tab. */
		$( document ).on( 'click', '#ecdv2_side_toggle', function() {
			side_drawer();
		} );
		$( document ).on( 'click', '#ecdv2_side_close, #ecdv2_side_backdrop', function() {
			side_drawer( false );
		} );
		$( document ).on( 'keydown.ecdv2side', side_keydown );
		/* The page under the backdrop does not scroll; if it scrolls anyway ( a key, the header ), the drawer follows the header. */
		var backdrop = el( 'ecdv2_side_backdrop' );
		if ( backdrop && backdrop.addEventListener ) {
			backdrop.addEventListener( 'wheel', function( e ) { e.preventDefault(); }, { passive: false } );
			backdrop.addEventListener( 'touchmove', function( e ) { e.preventDefault(); }, { passive: false } );
		}
		$( window ).on( 'scroll.ecdv2side', function() {
			if ( side_is_open() ) {
				place_side_drawer();
			}
		} );
		$( document ).on( 'ecsh:menu-fold', function() {
			setTimeout( fit_columns, 260 );
		} );

		paint_next();
		paint_side();
		paint_summaries();
		fit_columns();
		nav_init();
		if ( 'page' === view ) {
			watch_feeds();
		}
		if ( $( '#ecdv2_view_tip' ).length && 'page' === view ) {
			$( '#ecdv2_view_tip' ).prop( 'hidden', false );
		}
		/* Sales for the summary column, a moment after the page is ready. */
		if ( $( '#ecdv2_side_insights' ).length ) {
			setTimeout( function() {
				activity_request().done( function( data ) {
					paint_kpis( data );
				} );
			}, 700 );
		}
	}

	function paint_kpis( data ) {
		if ( ! data ) {
			return;
		}
		$( '[data-ecdv2-kpi]' ).each( function() {
			var key = $( this ).attr( 'data-ecdv2-kpi' );
			if ( undefined === data[ key ] ) {
				return;
			}
			$( this ).text( 'revenue' === key ? money( data[ key ] ) : String( parseInt( data[ key ], 10 ) || 0 ) );
		} );
	}

	/* Deep links: &section=pricing ( or the Quick add panel's &tab= ), or a #pricing hash; #insights opens Insights. */
	function route_initial() {
		var key = '';
		var match = /[?&](?:section|tab)=([a-z_\-]+)/i.exec( window.location.search );
		if ( match ) {
			key = match[1].toLowerCase();
		}
		if ( ! key ) {
			key = window.location.hash.replace( '#', '' );
		}
		if ( ! key ) {
			return;
		}
		if ( 'insights' === key && vcfg ) {
			set_mode( 'insights', { silent: true } );
			return;
		}
		if ( 'categories' === key && vcfg ) {
			go_tab( 'categories', { instant: true, no_focus: true } );
			return;
		}
		if ( legacy_anchor_map[ key ] ) {
			key = legacy_anchor_map[ key ];
		}
		if ( $( '.ecdv2-tab[data-ecdv2-tab="' + key + '"]' ).length ) {
			go_tab( key, { instant: true } );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings search                                                      */
	/* ------------------------------------------------------------------ */

	/* Merchant-vocabulary aliases: each key expands the typed query with
	 * additional terms to match against ( lets "sku" find Model Number,
	 * "photo" find Media, etc. without renaming anything ). */
	var SEARCH_ALIASES = {
		'sku'       : [ 'model number' ],
		'upc'       : [ 'gtin', 'model number' ],
		'photo'     : [ 'image', 'media' ],
		'picture'   : [ 'image', 'media' ],
		'sale'      : [ 'compare-at price', 'previous price', 'list price', 'volume pricing' ],
		'discount'  : [ 'compare-at price', 'volume pricing', 'offers' ],
		'stock'     : [ 'inventory', 'quantity', 'track stock', 'units in stock' ],
		'variant'   : [ 'option', 'variation' ],
		'seo'       : [ 'search engine', 'meta', 'yoast' ],
		'yoast'     : [ 'yoast seo', 'meta description', 'keyphrase' ],
		'coupon'    : [ 'offers' ],
		'promotion' : [ 'offers' ],
		'history'   : [ 'stock history' ],
		'url'       : [ 'slug', 'permalink' ],
		'tax'       : [ 'vat', 'taxable', 'sales tax', 'tax code' ],
		'gst'       : [ 'vat', 'tax' ],
		'avalara'   : [ 'tax code' ],
		'taxjar'    : [ 'tax code' ],
		'tic'       : [ 'tax code' ],
		'file'      : [ 'download' ],
		'related'   : [ 'featured products' ],
		'cross-sell': [ 'featured products' ]
	};

	function search_entry_text( node ) {
		/* Strip child decorations ( step badges, PRO pills ) so "Option Sets"
		 * indexes as its words, not "1Option Sets". */
		return $.trim( $( node ).clone().children().remove().end().text() ) || $.trim( $( node ).text() );
	}

	function build_search_index() {
		search_index = [];
		$( '.ecdv2-panel' ).each( function() {
			var tab = $( this ).attr( 'data-ecdv2-panel' );
			var tab_label = $( '.ecdv2-tab[data-ecdv2-tab="' + tab + '"] span' ).eq( 1 ).text();

			/* Whole cards/sections: Categories, Featured Products, Cart Links,
			 * Option Sets… none of these carry field labels, which is exactly
			 * why they were previously unfindable. The card hint text rides
			 * along as extra matchable words. */
			$( this ).find( '.ecdv2-card' ).each( function() {
				var $title = $( this ).find( '.ecdv2-card-title' ).first();
				var text = $title.length ? search_entry_text( $title ) : '';
				if ( text.length > 1 ) {
					var hint = $( this ).find( '.ecdv2-card-hint' ).first().text() || '';
					search_index.push( {
						label     : text,
						lower     : text.toLowerCase(),
						extra     : $.trim( hint ).toLowerCase(),
						tab       : tab,
						tab_label : tab_label,
						node      : this,
						is_card   : true
					} );
				}
			} );

			$( this ).find( '.ecdv2-label, .ecdv2-toggle-label, .ecdv2-gate-label, .ecdv2-card-title' ).each( function() {
				if ( $( this ).hasClass( 'ecdv2-card-title' ) ) {
					return; /* indexed above with hint text */
				}
				var text = search_entry_text( this );
				if ( text.length > 1 ) {
					search_index.push( {
						label     : text,
						lower     : text.toLowerCase(),
						extra     : '',
						tab       : tab,
						tab_label : tab_label,
						node      : this,
						is_card   : false
					} );
				}
			} );
		} );

		/* 6.0.4: the summary column's cards ( Categories, listing health ) are findable too. */
		$( '#ecdv2_side .ecdv2-card' ).each( function() {
			var $title = $( this ).find( '.ecdv2-card-title' ).first();
			var text = $title.length ? search_entry_text( $title ) : '';
			if ( text.length > 1 ) {
				search_index.push( {
					label     : text,
					lower     : text.toLowerCase(),
					extra     : $.trim( $( this ).find( '.ecdv2-card-hint' ).first().text() || '' ).toLowerCase(),
					tab       : 'categories' === $( this ).attr( 'data-ecdv2-section' ) ? 'categories' : 'side',
					tab_label : $.trim( $( '#ecdv2_side' ).attr( 'aria-label' ) || '' ),
					node      : this,
					is_card   : true
				} );
			}
		} );
		/* 6.0.4: and the fields there ( Who can see it, Page address, Manufacturer, Show on the store page ). */
		$( '#ecdv2_side' ).find( '.ecdv2-side-label, .ecdv2-side-part .ecdv2-label, .ecdv2-side-part .ecdv2-toggle-label, .ecdv2-side-part .ecdv2-gate-label' ).each( function() {
			var text = search_entry_text( this );
			if ( text.length > 1 ) {
				search_index.push( {
					label     : text,
					lower     : text.toLowerCase(),
					extra     : '',
					tab       : $( this ).hasClass( 'ecdv2-side-label' ) ? 'categories' : 'side',
					tab_label : $.trim( $( '#ecdv2_side' ).attr( 'aria-label' ) || '' ),
					node      : $( this ).closest( '.ecdv2-field, .ecdv2-toggle-row, .ecdv2-gate' ).get( 0 ) || this,
					is_card   : false
				} );
			}
		} );
	}

	/* Lower score = better: 0 label starts with query, 1 a word starts with
	 * it, 2 substring anywhere, 3 alias/hint match only. */
	function search_score( entry, query ) {
		var terms = [ query ].concat( SEARCH_ALIASES[ query ] || [] );
		var best = -1;
		$.each( terms, function( index, term ) {
			var score = -1;
			if ( 0 === entry.lower.indexOf( term ) ) {
				score = 0;
			} else if ( -1 !== entry.lower.indexOf( ' ' + term ) ) {
				score = 1;
			} else if ( -1 !== entry.lower.indexOf( term ) ) {
				score = 2;
			} else if ( entry.extra && -1 !== entry.extra.indexOf( term ) ) {
				score = 3;
			}
			if ( index > 0 && score > -1 ) {
				score = Math.max( score, 3 ); /* alias matches rank below direct ones */
			}
			if ( score > -1 && ( -1 === best || score < best ) ) {
				best = score;
			}
		} );
		return best;
	}

	function bind_search() {
		var input = $( '#ecdv2_search' );
		var results = $( '#ecdv2_search_results' );
		if ( ! input.length ) {
			return;
		}
		input.on( 'input', function() {
			var query = $.trim( input.val() ).toLowerCase();
			results.empty();
			if ( query.length < 2 ) {
				results.removeClass( 'is-open' );
				return;
			}
			if ( null === search_index ) {
				build_search_index();
			}
			var matches = [];
			var i;
			for ( i = 0; i < search_index.length; i++ ) {
				var score = search_score( search_index[ i ], query );
				if ( score > -1 ) {
					matches.push( { entry: search_index[ i ], score: score, order: i } );
				}
			}
			matches.sort( function( a, b ) { return a.score - b.score || a.order - b.order; } );
			matches = $.map( matches.slice( 0, 8 ), function( match ) { return match.entry; } );
			if ( ! matches.length ) {
				results.append( $( '<div class="ecdv2-search-empty"/>' ).text( _t( 'no_results', 'No settings found' ) ) );
			} else {
				$.each( matches, function( idx, match ) {
					var row = $( '<button type="button" class="ecdv2-search-result"/>' );
					row.append( $( '<span class="ecdv2-search-result-label"/>' ).text( match.label ) );
					row.append( $( '<span class="ecdv2-search-result-tab"/>' ).text( match.tab_label ) );
					row.on( 'click', function() {
						if ( 'side' === match.tab || 'categories' === match.tab ) {
							results.removeClass( 'is-open' ).empty();
							input.val( '' );
							if ( 'categories' === match.tab ) {
								go_tab( 'categories', { no_focus: true } );
							} else {
								if ( 'edit' !== mode ) {
									set_mode( 'edit', { silent: true } );
								}
								scroll_to_node( match.node );
								flash( $( match.node ) );
							}
							return;
						}
						go_tab( match.tab, { no_scroll: true } );
						show_pane_of( match.node );
						results.removeClass( 'is-open' ).empty();
						input.val( '' );
						var field = match.is_card
							? $( match.node )
							: $( match.node ).closest( '.ecdv2-field, .ecdv2-toggle-row, .ecdv2-gate' );
						if ( ! field.length ) {
							field = $( match.node );
						}
						/* 6.0.4: a field another choice hides ( e.g. the stock count while stock is not tracked ): show its card. */
						if ( ! field.is( ':visible' ) && field.closest( '.ecdv2-card' ).length ) {
							field = field.closest( '.ecdv2-card' );
						}
						spy_hold_until = Date.now() + 900;
						field[ 0 ].scrollIntoView( { block: 'center', behavior: reduce_motion ? 'auto' : 'smooth' } );
						field.addClass( 'ecdv2-flash' );
						setTimeout( function() {
							field.removeClass( 'ecdv2-flash' );
						}, 1800 );
					} );
					results.append( row );
				} );
			}
			results.addClass( 'is-open' );
		} );
		$( document ).on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '.ecdv2-rail-search, #ecdv2_search_results' ).length ) {
				results.removeClass( 'is-open' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Field dependency engine ( requires / show )                          */
	/* ------------------------------------------------------------------ */

	function controller_value( name ) {
		var node = el( name );
		if ( ! node ) {
			return null;
		}
		if ( 'checkbox' === node.type ) {
			return node.checked ? '1' : '0';
		}
		return String( $( node ).val() );
	}

	function apply_dependencies() {
		$( '[data-ecdv2-requires]' ).each( function() {
			var wrap = $( this );
			var current = controller_value( wrap.attr( 'data-ecdv2-requires' ) );
			if ( null === current ) {
				return;
			}
			var allowed = String( wrap.attr( 'data-ecdv2-requires-value' ) ).split( ',' );
			wrap.toggle( -1 !== $.inArray( current, allowed ) );
		} );
		/* Multi-condition requires: every condition must match ( mirrors the
		 * legacy printer, which hides on any mismatch ). */
		$( '[data-ecdv2-requires-multi]' ).each( function() {
			var wrap = $( this );
			var conditions;
			try { conditions = JSON.parse( wrap.attr( 'data-ecdv2-requires-multi' ) ); } catch ( e ) { conditions = null; }
			if ( ! conditions || ! conditions.length ) {
				return;
			}
			var show = true;
			$.each( conditions, function( i, condition ) {
				var current = controller_value( condition.name );
				if ( null === current ) {
					return; /* controller absent: condition is not evaluable, skip it */
				}
				if ( -1 === $.inArray( current, String( condition.value ).split( ',' ) ) ) {
					show = false;
					return false;
				}
			} );
			wrap.toggle( show );
		} );
		$( '[data-ecdv2-shows]' ).each( function() {
			var target = el( $( this ).attr( 'data-ecdv2-shows' ) );
			if ( target ) {
				$( target ).closest( '.ecdv2-field, .ecdv2-toggle-row' ).toggle( this.checked );
			}
		} );
		update_badge_preview();
	}

	function bind_dependencies() {
		$( document ).on( 'change', '[data-ecdv2-sec]', apply_dependencies );

		/* Live preview of the header "Role restricted" flag while editing;
		   the saved state re-syncs via update_header_meta(). Delegated so it
		   also catches select2's change events on #role_id. */
		$( document ).on( 'change', '#role_id', sync_restricted_pill );
		apply_dependencies();

		/* Legacy adapter: stock_quantity's `requires` points at a
		 * show_stock_quantity element that only existed in the old editor.
		 * Drive its visibility from the Track Quantity select instead
		 * ( 1 = overall tracking shows the stock field ). */
		var quantity_type_sync = function() {
			var sel = el( 'stock_quantity_type' );
			var stock = el( 'stock_quantity' );
			if ( ! sel || ! stock ) {
				return;
			}
			$( stock ).closest( '.ecdv2-field' ).toggle( '1' === String( $( sel ).val() ) );
		};
		$( document ).on( 'change', '#stock_quantity_type', quantity_type_sync );
		quantity_type_sync();

		/* 6.0.2: Track Quantity no longer saves on change ( it saves with Save ), so the free-edition guard of
		 * the legacy handler lives here: variation stock needs WP EasyCart PRO's option panels. Bound on the
		 * select itself so a refused choice goes back before the card is marked unsaved. */
		var type_select = el( 'stock_quantity_type' );
		if ( type_select ) {
			$( type_select ).data( 'ecdv2Prev', String( $( type_select ).val() ) ).on( 'change', function( e ) {
				var chosen = String( $( this ).val() );
				if ( '2' === chosen && ! $( '#wpeasycart_product_options_pro' ).length ) {
					if ( typeof wp_easycart_products_language !== 'undefined' && wp_easycart_products_language['optionitem-tracking-note'] ) {
						window.alert( $( '<div/>' ).html( wp_easycart_products_language['optionitem-tracking-note'] ).text() );
					}
					$( this ).val( $( this ).data( 'ecdv2Prev' ) );
					e.stopPropagation();
					return;
				}
				$( this ).data( 'ecdv2Prev', chosen );
			} );
		}

		/* PRO's Change tracking dialog sets the select and saves through the legacy handler at once: the
		 * header follows it there too. */
		if ( typeof window.ec_admin_product_details_quantity_type_change === 'function' && ! window.ec_admin_product_details_quantity_type_change.ecdv2 ) {
			var legacy_type_change = window.ec_admin_product_details_quantity_type_change;
			window.ec_admin_product_details_quantity_type_change = function() {
				var out = legacy_type_change.apply( this, arguments );
				if ( type_select ) {
					$( type_select ).data( 'ecdv2Prev', String( $( type_select ).val() ) );
				}
				sync_header_stock();
				return out;
			};
			window.ec_admin_product_details_quantity_type_change.ecdv2 = true;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Slug auto-generation                                                 */
	/* ------------------------------------------------------------------ */

	function slugify( text ) {
		return String( text )
			.toLowerCase()
			.replace( / /g, '-' )
			.replace( /[^a-z0-9\-]/g, '' )
			.replace( /\-+/g, '-' )
			.replace( /^\-+|\-+$/g, '' );
	}

	/* 6.0.4: on a new product's draft the SKU follows the title ( as Quick add's does ) until the merchant types one. */
	function bind_draft_sku() {
		if ( ! vcfg || ! vcfg.draft || ! el( 'title' ) || ! el( 'model_number' ) ) {
			return;
		}
		var touched = false;
		$( el( 'model_number' ) ).on( 'input', function() {
			touched = '' !== this.value;
		} );
		$( el( 'title' ) ).on( 'input', function() {
			if ( ! touched ) {
				$( el( 'model_number' ) ).val( slugify( this.value ) );
				field_ok( 'model_number' );
			}
			$( '#ecdv2_header_title' ).text( $.trim( this.value ) || vt( 'new_product', 'New Product' ) );
		} );
	}

	function bind_slug() {
		var title = el( 'title' );
		var slug = el( 'post_slug' );
		if ( ! title || ! slug ) {
			return;
		}
		/* Existing products with a slug start locked; new/blank start auto. */
		if ( '' !== $.trim( $( slug ).val() ) && ! $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) ) {
			slug_locked = true;
			render_slug_lock();
		}
		$( title ).on( 'input', function() {
			if ( ! slug_locked && ! slug_touched ) {
				$( slug ).val( slugify( $( title ).val() ) );
				mark_dirty( 'basic' );
			}
		} );
		$( slug ).on( 'input', function() {
			slug_touched = true;
		} );
	}

	function render_slug_lock() {
		var button = $( '#ecdv2_slug_lock' );
		if ( ! button.length ) {
			return;
		}
		button.toggleClass( 'is-locked', slug_locked )
			.find( '.dashicons' )
			.toggleClass( 'dashicons-lock', slug_locked )
			.toggleClass( 'dashicons-unlock', ! slug_locked );
	}

	function slug_lock_toggle() {
		slug_locked = ! slug_locked;
		if ( ! slug_locked ) {
			slug_touched = false;
			$( el( 'post_slug' ) ).val( slugify( $( el( 'title' ) ).val() ) );
			mark_dirty( 'basic' );
		}
		render_slug_lock();
	}

	/* ------------------------------------------------------------------ */
	/* Badge live preview                                                   */
	/* ------------------------------------------------------------------ */

	function update_badge_preview() {
		var wrap = $( '#ecdv2_badge_preview_wrap' );
		var preview = $( '#ecdv2_badge_preview' );
		if ( ! wrap.length ) {
			return;
		}
		var type = v( 'tag_type' );
		var text = v( 'tag_text' );
		if ( ! type || '0' === String( type ) || '' === $.trim( text ) ) {
			wrap.hide();
			return;
		}
		preview.text( text ).css( {
			background : v( 'tag_bg_color' ) || '#222222',
			color      : v( 'tag_text_color' ) || '#ffffff'
		} );
		wrap.show();
	}

	/* ------------------------------------------------------------------ */
	/* Color swatch <-> text sync                                           */
	/* ------------------------------------------------------------------ */

	function bind_colors() {
		$( document ).on( 'input', '[data-ecdv2-color-for]', function() {
			var target = el( $( this ).attr( 'data-ecdv2-color-for' ) );
			if ( target ) {
				$( target ).val( this.value ).trigger( 'change' );
			}
		} );
		$( document ).on( 'input', '.ecdv2-color-wrap input[type="text"]', function() {
			var swatch = $( this ).siblings( 'input[type="color"]' );
			if ( /^#[0-9a-fA-F]{6}$/.test( this.value ) ) {
				swatch.val( this.value );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* SEO character counters                                               */
	/* ------------------------------------------------------------------ */

	function bind_counters() {
		$( '[data-ecdv2-count-for]' ).each( function() {
			var counter = $( this );
			var target = el( counter.attr( 'data-ecdv2-count-for' ) );
			var max = parseInt( counter.attr( 'data-ecdv2-count-max' ), 10 );
			if ( ! target ) {
				return;
			}
			var update = function() {
				var len = $( target ).val().length;
				counter.text( len + ' / ' + max ).toggleClass( 'is-over', len > max );
			};
			$( target ).on( 'input', update );
			update();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* WP media pickers                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Open the WP media frame for a field.
	 * @param {string}  field_id      Input to receive the value.
	 * @param {boolean} is_attachment Store the attachment id ( and update {field}_preview ) instead of the URL.
	 * @param {string}  media_type    Optional library filter, e.g. 'image'. Omit for any file ( downloads ).
	 */
	function media_pick( field_id, is_attachment, media_type ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		var is_image = ( 'image' === media_type );
		if ( ! media_frames[ field_id ] ) {
			var frame_args = {
				title    : is_image ? _t( 'select_image', 'Select image' ) : _t( 'select_media', 'Select or upload media' ),
				button   : { text: is_image ? _t( 'use_image', 'Use this image' ) : _t( 'use_file', 'Use this file' ) },
				multiple : false
			};
			if ( is_image ) {
				frame_args.library = { type: 'image' };
			}
			media_frames[ field_id ] = wp.media( frame_args );
			media_frames[ field_id ].on( 'select', function() {
				var attachment = media_frames[ field_id ].state().get( 'selection' ).first().toJSON();
				if ( is_attachment ) {
					$( el( field_id ) ).val( attachment.id ).trigger( 'change' );
					var preview = el( field_id + '_preview' );
					if ( preview ) {
						var thumb = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
						$( preview ).css( 'background-image', 'url(' + thumb + ')' );
					}
				} else {
					$( el( field_id ) ).val( attachment.url ).trigger( 'change' );
				}
			} );
		}
		media_frames[ field_id ].open();
	}

	function media_clear( field_id ) {
		$( el( field_id ) ).val( '' ).trigger( 'change' );
		var preview = el( field_id + '_preview' );
		if ( preview ) {
			$( preview ).css( 'background-image', 'none' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Categories ( instant save, token UI )                                */
	/* ------------------------------------------------------------------ */

	/* Categories are searched on the server as you type ( ecv2_category_search,
	 * 20 matches per request ). Tokens carry assignment state; cat_results holds
	 * only the matches for the term currently shown in the menu. */
	var cat_results = [];
	var cat_search_timer = null;
	var cat_search_xhr = null;
	var cat_search_seq = 0;


	function category_token( category_id, label ) {
		$( '#ecdv2_cat_empty' ).remove();
		if ( $( '.ecdv2-cat-token[data-category-id="' + category_id + '"]' ).length ) {
			return;
		}
		var token = $( '<span class="ecdv2-cat-token"/>' ).attr( 'data-category-id', category_id ).text( label );
		$( '<button type="button" aria-label="Remove">&times;</button>' )
			.on( 'click', function() { category_remove( category_id ); } )
			.appendTo( token );
		$( '#ecdv2_cat_tokens' ).append( token );
	}

	/* 6.0.4 ( editor plan Phase 2 ): the chips are what Save gives the product; adding or removing one changes nothing yet. */
	function category_add( category_id, label ) {
		category_id = parseInt( category_id, 10 );
		if ( ! category_id ) {
			return false;
		}
		category_token( category_id, label );
		cat_combo_reset();
		mark_dirty( 'categories' );
		return false;
	}

	function category_create( name ) {
		name = $.trim( name );
		if ( '' === name ) {
			return false;
		}
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_product_details_create_category',
				product_id        : v( 'product_id' ),
				category_name     : name,
				assign            : 0, /* 6.0.4: the category is made now; the product gets it with Save */
				wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
			},
			success : function( response ) {
				if ( ! response || ! response.success ) {
					toast( ( response && response.data && response.data.message ) || _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
					return;
				}
				category_token( parseInt( response.data.category_id, 10 ), response.data.category_name );
				cat_combo_reset();
				mark_dirty( 'categories' );
				if ( ! response.data.existed ) {
					toast( vt( 'category_created', 'Category created. Save to add this product to it.' ), 'success' );
				}
			},
			error   : function() {
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			}
		} );
		return false;
	}

	/* ------------------------- combobox ------------------------------- */

	function cat_combo_reset() {
		$( '#ecdv2_cat_input' ).val( '' ).trigger( 'focus' );
		cat_results = [];
		cat_menu_render( '' );
	}

	/* Ask the server for matches ( debounced; a newer term cancels the older request ). */
	function cat_fetch( term ) {
		var $combo = $( '#ecdv2_cat_combo' );
		var seq = ++cat_search_seq;
		clearTimeout( cat_search_timer );
		cat_search_timer = setTimeout( function() {
			if ( cat_search_xhr && cat_search_xhr.abort ) { cat_search_xhr.abort(); }
			cat_search_xhr = $.ajax( {
				url  : wpeasycart_admin_ajax_object.ajax_url,
				type : 'post',
				data : {
					action            : 'ecv2_category_search',
					search            : term,
					product_id        : $combo.data( 'product-id' ) || v( 'product_id' ),
					wp_easycart_nonce : $combo.data( 'nonce' )
				},
				success : function( response ) {
					if ( seq !== cat_search_seq ) { return; } /* stale */
					cat_results = ( response && response.success && response.data && response.data.results ) ? response.data.results : [];
					cat_menu_paint( term );
				},
				error : function( xhr, status ) {
					if ( 'abort' === status || seq !== cat_search_seq ) { return; }
					cat_results = [];
					cat_menu_paint( term );
				}
			} );
		}, 250 );
	}

	function cat_menu_render( term ) {
		var $menu = $( '#ecdv2_cat_menu' );
		if ( ! $menu.length ) {
			return;
		}
		term = $.trim( term );
		if ( '' === term ) {
			clearTimeout( cat_search_timer );
			cat_search_seq++;
			cat_results = [];
			$menu.html( '<div class="ecdv2-cat-menu-none">' + _t( 'type_to_search', 'Type to search categories, or a new name to create one.' ) + '</div>' ).show();
			return;
		}
		$menu.html( '<div class="ecdv2-cat-menu-none">' + _t( 'searching', 'Searching…' ) + '</div>' ).show();
		cat_fetch( term );
	}

	/* Paint the menu from cat_results ( assigned matches are listed but not pickable ). */
	function cat_menu_paint( term ) {
		var $menu = $( '#ecdv2_cat_menu' );
		if ( ! $menu.length ) {
			return;
		}
		var lower = term.toLowerCase();
		var exact = cat_results.some( function( entry ) { return String( entry.name ).toLowerCase() === lower; } );
		var assigned_exact = false;
		$( '.ecdv2-cat-token' ).each( function() {
			var label = $.trim( $( this ).clone().children().remove().end().text() );
			if ( label.toLowerCase() === lower ) { assigned_exact = true; }
		} );

		var html = '', first_pickable = true;
		cat_results.forEach( function( entry ) {
			/* 6.0.4: the chips, saved or not, decide ( entry.assigned is the saved state ). */
			var is_assigned = $( '.ecdv2-cat-token[data-category-id="' + entry.id + '"]' ).length > 0;
			if ( is_assigned ) {
				html += '<div class="ecdv2-cat-menu-none">' + $( '<i>' ).text( entry.name ).html() + ' · ' + _t( 'category_assigned', 'Already assigned.' ) + '</div>';
				return;
			}
			html += '<button type="button" class="ecdv2-cat-menu-item' + ( first_pickable ? ' is-active' : '' ) + '" data-id="' + entry.id + '">' + $( '<i>' ).text( entry.name ).html() + '</button>';
			first_pickable = false;
		} );
		if ( '' !== term && ! exact && ! assigned_exact ) {
			html += '<button type="button" class="ecdv2-cat-menu-item ecdv2-cat-menu-create' + ( first_pickable ? ' is-active' : '' ) + '" data-create="1"><span class="dashicons dashicons-plus-alt2"></span>' + _t( 'create_category', 'Create' ) + ' “' + $( '<i>' ).text( term ).html() + '”</button>';
		}
		if ( '' === html ) {
			html = '<div class="ecdv2-cat-menu-none">' + ( assigned_exact ? _t( 'category_assigned', 'Already assigned.' ) : _t( 'no_matches', 'No matching categories.' ) ) + '</div>';
		}
		$menu.html( html ).show();
	}

	function cat_menu_pick( $item ) {
		if ( ! $item || ! $item.length ) {
			return;
		}
		if ( $item.data( 'create' ) ) {
			category_create( $( '#ecdv2_cat_input' ).val() );
		} else {
			category_add( $item.data( 'id' ), $.trim( $item.text() ) );
		}
	}

	function bind_cat_combo() {
		if ( ! $( '#ecdv2_cat_combo' ).length ) {
			return;
		}
		$( document ).on( 'focus input', '#ecdv2_cat_input', function() {
			cat_menu_render( $( this ).val() );
		} );
		$( document ).on( 'keydown', '#ecdv2_cat_input', function( e ) {
			var $items = $( '#ecdv2_cat_menu .ecdv2-cat-menu-item' );
			var $active = $items.filter( '.is-active' );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
				e.preventDefault();
				var index = $items.index( $active );
				index = 'ArrowDown' === e.key ? Math.min( index + 1, $items.length - 1 ) : Math.max( index - 1, 0 );
				$items.removeClass( 'is-active' ).eq( index ).addClass( 'is-active' );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				cat_menu_pick( $active.length ? $active : $items.first() );
			} else if ( 'Escape' === e.key ) {
				$( '#ecdv2_cat_menu' ).hide();
			}
		} );
		$( document ).on( 'click', '.ecdv2-cat-menu-item', function() {
			cat_menu_pick( $( this ) );
		} );
		$( document ).on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '#ecdv2_cat_combo' ).length ) {
				$( '#ecdv2_cat_menu' ).hide();
			}
		} );
	}
	$( bind_cat_combo );

	function category_remove( category_id ) {
		$( '.ecdv2-cat-token[data-category-id="' + category_id + '"]' ).remove();
		if ( ! $( '#ecdv2_cat_tokens .ecdv2-cat-token' ).length ) {
			$( '#ecdv2_cat_tokens' ).append( $( '<span class="ecdv2-cat-empty" id="ecdv2_cat_empty"></span>' ).text( _t( 'no_categories', 'No categories assigned yet.' ) ) );
		}
		mark_dirty( 'categories' );
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Activity tab                                                         */
	/* ------------------------------------------------------------------ */

	var default_status_labels = {
		'5'  : 'Received',
		'10' : 'Approved',
		'15' : 'Shipped',
		'20' : 'On Hold',
		'25' : 'Refunded',
		'30' : 'Canceled'
	};

	function esc_html( raw ) {
		return $( '<span/>' ).text( null === raw || undefined === raw ? '' : String( raw ) ).html();
	}

	/* 6.0.5 ( admin review M16 ): what shoppers see beside the price ( "$12.00 / kg" ) as the price and the unit price fields
	 * change, the way wp_easycart_unit_price works it out; bases of another kind than the unit are greyed out. */
	function paint_unit_preview() {
		var node = el( 'ecdv2_unit_preview' );
		if ( ! node ) {
			return;
		}
		var units, bases;
		try {
			units = JSON.parse( node.getAttribute( 'data-units' ) || '{}' );
			bases = JSON.parse( node.getAttribute( 'data-bases' ) || '{}' );
		} catch ( err ) {
			return;
		}
		var unit = units[ v( 'unit_measure_unit' ) ];
		var base_select = el( 'unit_base' );
		if ( base_select ) {
			$( base_select ).find( 'option' ).each( function() {
				var b = bases[ this.value ];
				this.disabled = !! ( unit && b && units[ b[1] ] && units[ b[1] ][0] !== unit[0] );
			} );
			if ( base_select.selectedIndex > -1 && base_select.options[ base_select.selectedIndex ].disabled ) {
				$( base_select ).val( '' ).trigger( 'change' );
				return;
			}
		}
		var measure = parseFloat( String( v( 'unit_measure' ) ).replace( ',', '.' ) );
		var price = parseFloat( v( 'price' ) );
		var key = v( 'unit_base' );
		if ( unit && ( ! bases[ key ] || units[ bases[ key ][1] ][0] !== unit[0] ) ) {
			key = unit[2];
		}
		var base = bases[ key ];
		if ( ! unit || ! base || ! ( measure > 0 ) || ! ( price > 0 ) ) {
			node.hidden = true;
			node.textContent = '';
			return;
		}
		var factor = ( base[0] * units[ base[1] ][1] ) / ( measure * unit[1] );
		var words = String( node.getAttribute( 'data-words' ) || '[price] / [unit]' ).split( '[unit]' ).join( base[2] ).split( '[price]' ).join( money( price * factor ) );
		node.textContent = ( node.getAttribute( 'data-label' ) || 'Shoppers see' ) + ': ' + words;
		node.hidden = false;
	}

	function bind_unit_preview() {
		if ( ! el( 'ecdv2_unit_preview' ) ) {
			return;
		}
		$( document ).on( 'input change', '#unit_measure, #unit_measure_unit, #unit_base, #price', paint_unit_preview );
		paint_unit_preview();
	}

	function money( amount ) {
		var conf = window.wpeasycart_ecdv2_currency || {};
		if ( 'string' === typeof conf ) {
			conf = { symbol: conf };
		}
		var symbol = ( undefined !== conf.symbol ) ? conf.symbol : '$';
		var decimals = ( undefined !== conf.decimals ) ? parseInt( conf.decimals, 10 ) : 2;
		var dec_sep = ( undefined !== conf.dec && '' !== conf.dec ) ? conf.dec : '.';
		var thou_sep = ( undefined !== conf.thou ) ? conf.thou : ',';
		var left = ( undefined !== conf.left ) ? !! conf.left : true;

		var n = parseFloat( amount || 0 );
		var negative = n < 0;
		n = Math.abs( n );
		if ( isNaN( decimals ) || decimals < 0 ) {
			decimals = 2;
		}
		var fixed = n.toFixed( decimals );
		var dot = fixed.indexOf( '.' );
		var int_part = ( -1 === dot ) ? fixed : fixed.slice( 0, dot );
		var frac_part = ( -1 === dot ) ? '' : fixed.slice( dot + 1 );
		int_part = int_part.replace( /\B(?=(\d{3})+(?!\d))/g, thou_sep );
		var number = int_part + ( frac_part ? dec_sep + frac_part : '' );
		var sp = conf.space ? '\u00a0' : ''; /* 6.0.5: 93,00 € */
		return ( negative ? '-' : '' ) + ( left ? symbol + sp + number : number + sp + symbol );
	}

	/* 6.0.4: one request feeds both the Activity section and the summary column's sales. */
	var activity_promise = null;
	function activity_request() {
		if ( activity_promise ) {
			return activity_promise;
		}
		var d = $.Deferred();
		activity_promise = d.promise();
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_ecdv2_activity',
				product_id        : v( 'product_id' ),
				wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
			},
			success : function( response ) {
				if ( ! response || ! response.success ) {
					d.reject( false );
					return;
				}
				d.resolve( response.data );
			},
			error   : function() {
				activity_promise = null; /* a network failure can be tried again */
				d.reject( true );
			}
		} );
		return activity_promise;
	}

	function load_activity() {
		activity_loaded = true;
		var holder = $( '#ecdv2_activity_content' );
		activity_request().done( function( data ) {
			render_activity( holder, data );
		} ).fail( function( retry ) {
			if ( retry ) {
				activity_loaded = false;
			}
			holder.html( '<div class="ecdv2-activity-empty">' + esc_html( _t( 'activity_failed', 'Could not load activity.' ) ) + '</div>' );
		} );
	}

	function render_activity( holder, data ) {
		var status_labels = $.extend( {}, default_status_labels, data.status_labels || {} );
		var html = '';

		/* Metric tiles */
		html += '<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' + esc_html( _t( 'performance', 'Performance' ) ) + '</h3><span class="ecdv2-card-hint">' + esc_html( _t( 'all_time', 'All time' ) ) + '</span></div><div class="ecdv2-card-body"><div class="ecdv2-metrics">';
		html += metric( data.units, _t( 'units_sold', 'Units sold' ) );
		html += metric( money( data.revenue ), _t( 'revenue', 'Revenue' ) );
		html += metric( data.order_count, _t( 'orders', 'Orders' ) );
		html += metric( data.views, _t( 'views', 'Product views' ) );
		html += metric( data.subscribers, _t( 'waitlist', 'Stock waitlist' ) );
		html += '</div></div></div>';

		/* Recent orders */
		html += '<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' + esc_html( _t( 'recent_orders', 'Recent Orders' ) ) + '</h3></div><div class="ecdv2-card-body ecdv2-activity-body">';
		if ( data.recent_orders && data.recent_orders.length ) {
			$.each( data.recent_orders, function( idx, order ) {
				var name = $.trim( ( order.billing_first_name || '' ) + ' ' + ( order.billing_last_name || '' ) ) || order.user_email;
				var status = status_labels[ String( order.orderstatus_id ) ] || ( '#' + order.orderstatus_id );
				html += '<div class="ecdv2-activity-row">';
				html += '<a href="admin.php?page=wp-easycart-orders&subpage=orders&order_id=' + parseInt( order.order_id, 10 ) + '&ec_admin_form_action=edit" class="ecdv2-activity-row-main"><span class="ecdv2-activity-id">#' + parseInt( order.order_id, 10 ) + '</span><span class="ecdv2-activity-name">' + esc_html( name ) + '</span></a>';
				html += '<span class="ecdv2-activity-row-meta">';
				html += '<span class="ecdv2-activity-amount">' + esc_html( order.quantity ) + ' &times; ' + esc_html( money( order.unit_price ) ) + '</span>';
				html += '<span class="ecdv2-activity-date">' + esc_html( ( order.order_date || '' ).substring( 0, 10 ) ) + '</span>';
				html += '<span class="ecdv2-activity-status">' + esc_html( status ) + '</span>';
				html += '</span></div>';
			} );
		} else {
			html += '<div class="ecdv2-activity-empty">' + esc_html( _t( 'no_orders', 'No orders for this product yet.' ) ) + '</div>';
		}
		html += '</div></div>';

		/* Top customers */
		html += '<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' + esc_html( _t( 'top_customers', 'Top Customers' ) ) + '</h3></div><div class="ecdv2-card-body ecdv2-activity-body">';

		if ( data.top_customers && data.top_customers.length ) {
			$.each( data.top_customers, function( idx, customer ) {
				var name = $.trim( ( customer.billing_first_name || '' ) + ' ' + ( customer.billing_last_name || '' ) ) || customer.user_email;
				var initials = $.trim( ( ( customer.billing_first_name || ' ' ).charAt( 0 ) + ( customer.billing_last_name || ' ' ).charAt( 0 ) ) ).toUpperCase() || '?';
				html += '<div class="ecdv2-activity-row">';
				var inner = '<span class="ecdv2-activity-avatar">' + esc_html( initials ) + '</span><span class="ecdv2-activity-name">' + esc_html( name ) + '</span>';
				if ( parseInt( customer.user_id, 10 ) > 0 ) {
					html += '<a href="user-edit.php?user_id=' + parseInt( customer.user_id, 10 ) + '" class="ecdv2-activity-row-main">' + inner + '</a>';
				} else {
					html += '<span class="ecdv2-activity-row-main">' + inner + '</span>';
				}
				html += '<span class="ecdv2-activity-row-meta"><span class="ecdv2-activity-amount">' + esc_html( customer.units ) + ' ' + esc_html( _t( 'units', 'units' ) ) + '</span><span class="ecdv2-activity-date">' + esc_html( customer.orders ) + ' ' + esc_html( _t( 'orders_lc', 'orders' ) ) + '</span></span>';
				html += '</div>';
			} );
		} else {
			html += '<div class="ecdv2-activity-empty">' + esc_html( _t( 'no_customers', 'No customers yet.' ) ) + '</div>';
		}
		html += '</div></div>';

		/* Reviews are not part of Activity: they have their own Reviews tab. */
		holder.html( html );
	}

	function metric( value, label, raw_html ) {
		return '<div class="ecdv2-metric"><span class="ecdv2-metric-value">' + ( raw_html ? String( value ) : esc_html( value ) ) + '</span><span class="ecdv2-metric-label">' + esc_html( label ) + '</span></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Lazy feeds ( PRO panels: stock history, offers )                     */
	/* ------------------------------------------------------------------ */

	/*
	 * Markup contract, printed by PRO inside a panel:
	 *   <div class="ecdv2-feed" data-ecdv2-feed="AJAX_ACTION" data-ecdv2-feed-nonce="NONCE"
 *        data-ecdv2-feed-error="Translated load error"                ( optional )
	 *        data-ecdv2-feed-refresh="quantities">            ( optional: save endpoints that make it stale )
	 *     <div class="ecdv2-feed-body">…loading…</div>
	 *     <button type="button" class="ecdv2-feed-more" hidden>Load more</button>
	 *   </div>
	 * The action receives product_id, page and nonce, and answers
	 * { success: true, data: { html, has_more } }. Page 1 replaces the body; later
	 * pages append into the last [data-ecdv2-feed-rows] element ( or the body ).
	 * The server escapes everything in html. A feed loads the first time its tab opens.
	 */
	function feed_load( feed, more ) {
		var $feed = $( feed );
		if ( ! $feed.length || $feed.data( 'ecdv2FeedBusy' ) ) {
			return;
		}
		var page = more ? ( parseInt( $feed.data( 'ecdv2FeedPage' ), 10 ) || 1 ) + 1 : 1;
		var $body = $feed.find( '.ecdv2-feed-body' ).first();
		var $more = $feed.find( '.ecdv2-feed-more' ).first();
		var fail = function( response ) {
			var message = ( response && response.data && response.data.message ) ? response.data.message : ( $feed.attr( 'data-ecdv2-feed-error' ) || _t( 'feed_failed', 'Could not load this section. Please try again.' ) );
			if ( more ) {
				toast( message, 'error' );
			} else {
				$feed.attr( 'data-ecdv2-feed-loaded', '0' );
				$body.html( '<div class="ecdv2-activity-empty">' + esc_html( message ) + '</div>' );
			}
		};
		$feed.data( 'ecdv2FeedBusy', true ).attr( 'data-ecdv2-feed-loaded', '1' ).addClass( 'is-loading' );
		$more.prop( 'disabled', true );
		$.ajax( {
			url      : wpeasycart_admin_ajax_object.ajax_url,
			type     : 'post',
			dataType : 'json',
			data     : {
				action     : $feed.attr( 'data-ecdv2-feed' ),
				product_id : v( 'product_id' ),
				page       : page,
				nonce      : $feed.attr( 'data-ecdv2-feed-nonce' )
			}
		} ).done( function( response ) {
			if ( ! response || ! response.success || ! response.data ) {
				fail( response );
				return;
			}
			var html = String( response.data.html || '' );
			if ( more ) {
				var $rows = $body.find( '[data-ecdv2-feed-rows]' ).last();
				( $rows.length ? $rows : $body ).append( html );
			} else {
				$body.html( html );
			}
			$feed.data( 'ecdv2FeedPage', page );
			$more.prop( 'hidden', ! response.data.has_more );
		} ).fail( function() {
			fail( null );
		} ).always( function() {
			$feed.data( 'ecdv2FeedBusy', false ).removeClass( 'is-loading' );
			$more.prop( 'disabled', false );
		} );
	}

	function load_panel_feeds( key ) {
		$( '.ecdv2-panel[data-ecdv2-panel="' + key + '"] .ecdv2-feed' ).not( '[data-ecdv2-feed-loaded="1"]' ).each( function() {
			feed_load( this, false );
		} );
	}

	/* After a save, feeds that depend on a saved endpoint ( e.g. stock history on
	 * 'quantities' ) go stale: reload now when visible, otherwise on next open. */
	function refresh_feeds( endpoints ) {
		$( '.ecdv2-feed[data-ecdv2-feed-refresh]' ).each( function() {
			var $feed = $( this );
			var watch = String( $feed.attr( 'data-ecdv2-feed-refresh' ) ).split( /[\s,]+/ );
			var hit = false;
			$.each( endpoints || [], function( i, endpoint ) {
				if ( -1 !== $.inArray( endpoint, watch ) ) {
					hit = true;
				}
			} );
			if ( ! hit || '1' !== $feed.attr( 'data-ecdv2-feed-loaded' ) ) {
				return;
			}
			if ( $feed.closest( '.ecdv2-panel' ).hasClass( 'is-active' ) ) {
				feed_load( this, false );
			} else {
				$feed.attr( 'data-ecdv2-feed-loaded', '0' );
			}
		} );
	}

	function feed_reload( node ) {
		feed_load( $( node ).closest( '.ecdv2-feed' ), false );
	}

	function bind_feeds() {
		$( document ).on( 'click', '.ecdv2-feed-more', function( e ) {
			e.preventDefault();
			feed_load( $( this ).closest( '.ecdv2-feed' ), true );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Search & AI card: preview the saved product data ( 6.0.2 )           */
	/* ------------------------------------------------------------------ */

	function gtin_problem( raw ) {
		var digits = String( raw || '' ).replace( /\D/g, '' );
		if ( '' === digits ) {
			return '';
		}
		if ( [ 8, 12, 13, 14 ].indexOf( digits.length ) < 0 ) {
			return _t( 'gtin_length', 'A barcode has 8, 12, 13 or 14 digits. For a 10-digit ISBN, use the 13-digit one that starts with 978.' );
		}
		var sum = 0, body = digits.slice( 0, -1 ), i;
		for ( i = 0; i < body.length; i++ ) {
			sum += parseInt( body.charAt( body.length - 1 - i ), 10 ) * ( 0 === i % 2 ? 3 : 1 );
		}
		if ( ( 10 - ( sum % 10 ) ) % 10 !== parseInt( digits.slice( -1 ), 10 ) ) {
			return _t( 'gtin_check', 'That barcode\'s last digit doesn\'t match the others. Check it for a typo.' );
		}
		return '';
	}

	function bind_search_ai_preview() {
		$( document ).on( 'input change', '#ecdv2_sai_gtin', function() {
			var problem = gtin_problem( $( this ).val() );
			$( '#ecdv2_sai_gtin_error' ).text( problem ).prop( 'hidden', '' === problem );
		} );
		$( document ).on( 'click', '#ecdv2_sai_preview_btn', function() {
			var $btn  = $( this );
			var $card = $btn.closest( '.ecdv2-sai' );
			var $pre  = $( '#ecdv2_sai_preview' );
			if ( ! $card.length || ! $pre.length ) {
				return;
			}
			if ( ! $pre.prop( 'hidden' ) ) {
				$pre.prop( 'hidden', true );
				return;
			}
			$btn.prop( 'disabled', true );
			$.post( ( window.ajaxurl || wpeasycart_admin_ajax_object.ajax_url ), {
				action     : 'ecv2_search_ai_preview',
				nonce      : $card.attr( 'data-nonce' ),
				product_id : $card.attr( 'data-product-id' )
			} ).done( function( response ) {
				var text = ( response && response.data && ( response.data.text || response.data.message ) ) ? ( response.data.text || response.data.message ) : '';
				$pre.text( text ).prop( 'hidden', '' === text );
			} ).always( function() {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Yoast SEO card: live search-result preview                           */
	/* ------------------------------------------------------------------ */

	function bind_yoast_preview() {
		var $preview = $( '#ecdv2_yoast_preview' );
		if ( ! $preview.length ) {
			return;
		}
		var default_title = $preview.attr( 'data-default-title' ) || '';
		var default_desc = $preview.attr( 'data-default-desc' ) || '';
		var title_touched = false;
		function update() {
			var title = $.trim( v( 'ecdv2_yoast_title' ) );
			var desc = $.trim( v( 'ecdv2_yoast_metadesc' ) );
			/* Stored titles may hold Yoast %%variables%% the browser cannot resolve: keep the
			 * server-rendered preview until the merchant edits the field. */
			if ( title_touched ) {
				$( '#ecdv2_yoast_preview_title' ).text( '' !== title ? title : default_title );
			}
			$( '#ecdv2_yoast_preview_desc' ).text( '' !== desc ? desc : default_desc );
		}
		$( document ).on( 'input change', '#ecdv2_yoast_title', function() {
			title_touched = true;
			update();
		} );
		$( document ).on( 'input change', '#ecdv2_yoast_metadesc', update );
	}

	/* 6.0.5: the older description beside Yoast SEO's card ( print_seo_fallback_v2() ). Clear empties it; Save writes it. */
	function bind_seo_fallback() {
		var $card = $( '#ecdv2_seo_fallback' );
		if ( ! $card.length ) {
			return;
		}
		var $clear = $card.find( '[data-ecdv2-seo-clear]' );
		var paint = function() {
			$clear.prop( 'disabled', '' === $.trim( v( 'seo_description' ) ) );
		};
		$clear.on( 'click', function( e ) {
			e.preventDefault();
			$( '#seo_description' ).val( '' ).trigger( 'input' ).trigger( 'focus' );
		} );
		$( document ).on( 'input change', '#seo_description', paint );
		paint();
	}

	function review_toggle( node, review_id, approved ) {
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_ecdv2_review_status',
				review_id         : review_id,
				approved          : approved,
				wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
			},
			success : function( response ) {
				if ( response && response.success ) {
					$( node )
						.toggleClass( 'is-approved', !! approved )
						.text( approved ? _t( 'unapprove', 'Unapprove' ) : _t( 'approve', 'Approve' ) )
						.attr( 'onclick', 'ecdv2.review_toggle( this, ' + review_id + ', ' + ( approved ? 0 : 1 ) + ' );' );
					toast( approved ? _t( 'review_approved', 'Review approved.' ) : _t( 'review_unapproved', 'Review unapproved.' ), 'success' );
				}
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Header overflow menu                                                 */
	/* ------------------------------------------------------------------ */

	function menu_toggle() {
		$( '#ecdv2_header_menu' ).toggleClass( 'is-open' );
		place_menu();
	}

	/* 6.0.4: the ⋯ menu opens toward the side with room: under the button to the left when the header's actions are at its right,
	   to the right when they wrapped to the left ( it opened off the screen, or under the EasyCart menu ). */
	function place_menu() {
		var $menu = $( '#ecdv2_header_menu' );
		if ( ! $menu.hasClass( 'is-open' ) ) {
			return;
		}
		$menu.removeClass( 'is-left' ).css( 'max-width', '' );
		var wrap = el( 'ecdv2_wrap' );
		var bounds = wrap ? wrap.getBoundingClientRect() : { left: 0, right: window.innerWidth };
		var min = Math.max( 8, bounds.left + 4 );
		var max = Math.min( window.innerWidth - 8, bounds.right - 4 );
		var rect = $menu.get( 0 ).getBoundingClientRect();
		if ( rect.left < min ) {
			$menu.addClass( 'is-left' );
			rect = $menu.get( 0 ).getBoundingClientRect();
		}
		if ( rect.right > max ) {
			$menu.css( 'max-width', Math.max( 180, Math.floor( max - rect.left ) ) + 'px' );
		}
	}

	function menu_close() {
		$( '#ecdv2_header_menu' ).removeClass( 'is-open' );
	}

	function bind_menu() {
		$( document ).on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '.ecdv2-menu-wrap' ).length ) {
				menu_close();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* PRO media add-menu UX                                                */
	/* The legacy gallery menus ( .ec_admin_product_image_menu ) are        */
	/* shown/hidden by the PRO plugin's inline handlers, which don't close  */
	/* siblings, don't toggle, and don't close on an outside click. We add  */
	/* that behavior here without touching the PRO JS: the menus are just   */
	/* display:block/none elements we can manage.                           */
	/* ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ */
	/* Advanced pricing: live row summaries, add-form reset, B2B dedupe     */
	/* ------------------------------------------------------------------ */

	function tier_summary_text( $row ) {
		var qty = parseInt( $row.find( 'input' ).eq( 0 ).val(), 10 );
		var price = $row.find( 'input' ).eq( 1 ).val();
		if ( ! qty || '' === price || isNaN( parseFloat( price ) ) ) {
			return '';
		}
		return _t( 'tier_buy', 'Buy' ) + ' ' + qty + '+ ' + _t( 'tier_at', 'at' ) + ' ' + money( price ) + ' ' + _t( 'tier_each', 'each' );
	}

	function enhance_tier_row( row ) {
		var $row = $( row );
		if ( ! $row.find( '.ecdv2-tier-summary' ).length ) {
			$row.children( 'span' ).eq( 1 ).after( '<div class="ecdv2-tier-summary"></div>' );
		}
		$row.find( '.ecdv2-tier-summary' ).text( tier_summary_text( $row ) );
	}

	function watch_pricing_holders() {
		var holder = document.getElementById( 'price_tiers_holder' );
		if ( holder && ! holder.ecdv2_observed ) {
			holder.ecdv2_observed = true;
			$( holder ).find( '.ec_admin_price_tier_row' ).each( function() {
				enhance_tier_row( this );
			} );
			new MutationObserver( function( mutations ) {
				var added = false;
				mutations.forEach( function( m ) {
					[].forEach.call( m.addedNodes, function( n ) {
						if ( 1 === n.nodeType && n.classList && n.classList.contains( 'ec_admin_price_tier_row' ) ) {
							enhance_tier_row( n );
							added = true;
						}
					} );
				} );
				if ( added ) {
					$( '#ec_admin_new_price_tier_quantity, #ec_admin_new_price_tier_price' ).val( '' );
				}
			} ).observe( holder, { childList: true } );
		}

		/* Live-update a tier summary as its inputs change. */
		$( document ).on( 'input', '.ec_admin_price_tier_row input[type=number]', function() {
			enhance_tier_row( $( this ).closest( '.ec_admin_price_tier_row' )[0] );
		} );
	}

	function role_attr( raw ) {
		return String( raw == null ? '' : raw ).replace( /"/g, '&quot;' );
	}

	function role_row_html( id, role, price ) {
		return '<div class="ec_admin_role_price_row" id="ec_admin_product_details_role_price_row_' + id + '" data-role="' + role_attr( role ) + '" data-price="' + role_attr( price ) + '">' +
			'<span class="ecdv2-role-label">' + esc_html( role ) + '</span>' +
			'<span class="ecdv2-role-price">' + esc_html( money( price ) ) + '</span>' +
			'<span class="ecdv2-role-actions"><a href="" class="ecdv2-row-del" onclick="return ec_admin_product_details_delete_role_price( \'' + id + '\' );" title="' + role_attr( _t( 'delete', 'Delete' ) ) + '">' + ECDV2_X_SVG + '</a></span>' +
			'</div>';
	}

	/* Inline "x" delete icon, matching the PHP x_icon(). */
	var ECDV2_X_SVG = '<svg class="ecdv2-x" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" focusable="false"><path d="M5 5l10 10M15 5L5 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

	/* The add-tier endpoint appends rows with the legacy dashicons trash +
	 * save glyphs. Normalize any such row to the modern shape: a single
	 * inline-SVG "x" delete, no redundant save ( inputs already auto-save ). */
	function modernize_tier_rows() {
		$( '#price_tiers_holder > .ec_admin_price_tier_row' ).each( function() {
			var $row = $( this );
			if ( $row.find( '.ecdv2-row-del' ).length ) {
				return;
			}
			var id = ( $row.attr( 'id' ) || '' ).replace( 'ec_admin_product_details_price_tier_row_', '' );
			if ( ! id ) {
				return;
			}
			$row.children( 'span' ).last().attr( 'class', 'ecdv2-row-actions' ).html(
				'<a href="" class="ecdv2-row-del" onclick="return ec_admin_product_details_delete_price_tier( \'' + id + '\' );" title="' + role_attr( _t( 'delete', 'Delete' ) ) + '">' + ECDV2_X_SVG + '</a>'
			);
		} );
	}

	function bind_tier_rows() {
		modernize_tier_rows();
		var holder = document.getElementById( 'price_tiers_holder' );
		if ( holder && window.MutationObserver ) {
			new MutationObserver( modernize_tier_rows ).observe( holder, { childList: true } );
		}
	}

	function add_role_price() {
		var role = $( '#add_new_role_price_role' ).val();
		var price = $( '#ec_admin_new_role_price' ).val();
		if ( ! role ) {
			toast( _t( 'role_pick', 'Choose a user role first.' ), 'error' );
			return false;
		}
		if ( '' === price || isNaN( parseFloat( price ) ) || parseFloat( price ) < 0 ) {
			toast( _t( 'role_price_invalid', 'Enter a valid price.' ), 'error' );
			return false;
		}
		var dup = false;
		$( '#role_prices_holder .ec_admin_role_price_row' ).each( function() {
			if ( ( $( this ).attr( 'data-role' ) || '' ).toLowerCase() === String( role ).toLowerCase() ) {
				dup = true;
			}
		} );
		if ( dup ) {
			toast( _t( 'role_dup', 'That role already has a price — edit or remove it first.' ), 'error' );
			return false;
		}
		/* 6.0.4 ( editor plan Phase 2 ): a row on the page; Save sends the list ( the server checks the role again ). */
		$( '#ec_admin_no_role_prices' ).remove();
		$( '#role_prices_holder' ).append( role_row_html( 'new' + ( ++price_rule_seq ), role, String( parseFloat( price ) ) ) );
		$( '#ec_admin_new_role_price' ).val( '' );
		mark_dirty( 'price_rules' );
		return false;
	}

	var price_rule_seq = 0;

	/* 6.0.4: volume pricing rows, added, changed and removed on the page until Save. These replace products.js's instant
	 * versions of the same names, which the rows' inline handlers call. */
	function tier_add() {
		var qty = parseInt( v( 'ec_admin_new_price_tier_quantity' ), 10 );
		var price = v( 'ec_admin_new_price_tier_price' );
		if ( ! qty || qty < 1 ) {
			toast( vt( 'tier_qty', 'Enter how many a shopper buys, 1 or more.' ), 'error' );
			return false;
		}
		if ( '' === price || isNaN( parseFloat( price ) ) || parseFloat( price ) < 0 ) {
			toast( _t( 'role_price_invalid', 'Enter a valid price.' ), 'error' );
			return false;
		}
		var taken = false;
		$( '#price_tiers_holder > .ec_admin_price_tier_row' ).each( function() {
			if ( parseInt( $( this ).find( 'input[type=number]' ).eq( 0 ).val(), 10 ) === qty ) {
				taken = true;
			}
		} );
		if ( taken ) {
			toast( vt( 'tier_dup', 'There is already a price break for that quantity.' ), 'error' );
			return false;
		}
		var id = 'new' + ( ++price_rule_seq );
		var rid = 'ec_admin_product_details_price_tier_row_' + id;
		var change = 'ec_admin_product_details_edit_price_tier( \'' + id + '\' );';
		var $row = $( '<div class="ec_admin_price_tier_row"></div>' ).attr( 'id', rid );
		$( '<span></span>' ).append( $( '<input type="number" min="1" step="1" />' ).attr( { id: rid + '_quantity', onchange: change } ).val( qty ) ).appendTo( $row );
		$( '<span></span>' ).append( $( '<input type="number" min="0" step=".001" />' ).attr( { id: rid + '_price', onchange: change } ).val( parseFloat( price ).toFixed( 2 ) ) ).appendTo( $row );
		$( '<span class="ecdv2-row-actions"></span>' ).html( '<a href="" class="ecdv2-row-del" onclick="return ec_admin_product_details_delete_price_tier( \'' + id + '\' );" title="' + role_attr( _t( 'delete', 'Delete' ) ) + '">' + ECDV2_X_SVG + '</a>' ).appendTo( $row );
		$( '#ec_admin_no_price_tiers' ).remove();
		/* In quantity order, as the saved list reads. */
		var $after = null;
		$( '#price_tiers_holder > .ec_admin_price_tier_row' ).each( function() {
			if ( parseInt( $( this ).find( 'input[type=number]' ).eq( 0 ).val(), 10 ) < qty ) {
				$after = $( this );
			}
		} );
		if ( $after ) {
			$row.insertAfter( $after );
		} else {
			$( '#price_tiers_holder' ).prepend( $row );
		}
		$( '#ec_admin_new_price_tier_quantity, #ec_admin_new_price_tier_price' ).val( '' );
		mark_dirty( 'price_rules' );
		return false;
	}

	function tier_edit() {
		mark_dirty( 'price_rules' );
		return false;
	}

	function tier_remove( id ) {
		$( document.getElementById( 'ec_admin_product_details_price_tier_row_' + id ) ).remove();
		if ( ! $( '#price_tiers_holder > .ec_admin_price_tier_row' ).length ) {
			$( '#price_tiers_holder' ).append( $( '<div id="ec_admin_no_price_tiers" class="ecdv2-advblock-empty"></div>' ).text( vt( 'no_tiers', 'No volume pricing tiers yet. Add your first price break above.' ) ) );
		}
		mark_dirty( 'price_rules' );
		return false;
	}

	function role_price_remove( id ) {
		$( document.getElementById( 'ec_admin_product_details_role_price_row_' + id ) ).remove();
		if ( ! $( '#role_prices_holder > .ec_admin_role_price_row' ).length ) {
			$( '#role_prices_holder' ).append( $( '<div id="ec_admin_no_role_prices" class="ecdv2-advblock-empty"></div>' ).text( vt( 'no_role_prices', 'No B2B role pricing yet. Add a role price above.' ) ) );
		}
		mark_dirty( 'price_rules' );
		return false;
	}

	function pro_menus_hide_all() {
		$( '.ec_admin_product_image_menu' ).each( function() {
			$( this ).hide();
		} );
	}

	/* Expose the store currency symbol to CSS so the sentence rows can print
	 * "for $" / "pays $" with the correct symbol. */
	function set_currency_var() {
		var conf = window.wpeasycart_ecdv2_currency || {};
		var sym = ( 'string' === typeof conf ) ? conf : ( ( undefined !== conf.symbol ) ? conf.symbol : '$' );
		$( '.ecdv2-wrap' ).each( function() {
			this.style.setProperty( '--ecdv2-cur', '"' + String( sym ).replace( /"/g, '' ) + '"' );
		} );
	}

	/* Keep the B2B role <select> in sync with what's already priced: disable
	 * roles that already have a price ( so the same role can't be added twice )
	 * and advance the selection to the first still-available role. Runs at
	 * boot and whenever rows are added or removed. */
	function refresh_role_select() {
		var $sel = $( '#add_new_role_price_role' );
		if ( ! $sel.length ) {
			return;
		}
		var used = {};
		$( '#role_prices_holder .ec_admin_role_price_row' ).each( function() {
			used[ ( $( this ).attr( 'data-role' ) || '' ).toLowerCase() ] = true;
		} );
		var first_avail = null;
		$sel.find( 'option' ).each( function() {
			var val = ( $( this ).val() || '' ).toLowerCase();
			var is_used = !! used[ val ];
			$( this ).prop( 'disabled', is_used );
			if ( ! is_used && null === first_avail ) {
				first_avail = $( this ).val();
			}
		} );
		if ( used[ ( $sel.val() || '' ).toLowerCase() ] && null !== first_avail ) {
			$sel.val( first_avail );
		}
	}

	function bind_role_select() {
		refresh_role_select();
		var holder = document.getElementById( 'role_prices_holder' );
		if ( holder && window.MutationObserver ) {
			new MutationObserver( refresh_role_select ).observe( holder, { childList: true } );
		}
	}

	/* Inject a "Back to options" control into each sub-menu ( the URL / video /
	 * youtube / vimeo input panels ) so there's an easy path back to the
	 * source list, not just the close button. Idempotent. */
	function pro_menus_ensure_back() {
		$( '.ec_admin_product_image_menu' ).each( function() {
			var $menu = $( this );
			var $group = $menu.find( '.ec_admin_product_image_input_group' );
			if ( ! $group.length || $menu.find( '.ecdv2-pro-menu-back' ).length ) {
				return;
			}
			var $back = $( '<button type="button" class="ecdv2-pro-menu-back"><span class="dashicons dashicons-arrow-left-alt2"></span>' + esc_html( _t( 'all_options', 'All options' ) ) + '</button>' );
			$back.on( 'click', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				var container = $menu.closest( '.ec_admin_product_image_container' )[0];
				$menu.hide();
				if ( container ) {
					$( container ).find( '.ec_admin_product_image_menu' ).first().show();
				}
			} );
			$group.prepend( $back );
		} );
	}

	function is_wp_media_target( node ) {
		return node.closest && ( node.closest( '.media-modal' ) || node.closest( '.media-frame' ) || node.closest( '.media-router' ) || node.closest( '.ui-dialog' ) );
	}

	function bind_pro_media_menus() {
		pro_menus_ensure_back();

		/* Subtle saved-flash on the auto-saving tier inputs ( the edit endpoint
		 * fires from each input's onchange; this just confirms it visually ). */
		$( document ).on( 'change', '.ec_admin_price_tier_row input[type=number]', function() {
			var $row = $( this ).closest( '.ec_admin_price_tier_row' );
			$row.addClass( 'ecdv2-tier-saved' );
			setTimeout( function() {
				$row.removeClass( 'ecdv2-tier-saved' );
			}, 1200 );
		} );

		/* Capture phase: runs BEFORE the inline onclick that opens the menu.
		 * The whole add tile is now the click target ( v2 PRO template ), with
		 * the "+" icon still handled for the legacy compat path. Close every
		 * menu in that tile first ( fixes the stacked-overlay bug ), and if
		 * the main menu was already open, toggle it closed by stopping the
		 * inline handler from reopening it. Clicks INSIDE an open menu are
		 * left alone — the menu items manage their own open/close. */
		document.addEventListener( 'click', function( e ) {
			if ( ! e.target.closest ) {
				return;
			}
			if ( e.target.closest( '.ec_admin_product_image_menu' ) ) {
				return;
			}
			var add = e.target.closest( '.ec_admin_product_image_add_tile' ) || e.target.closest( '.ec_admin_product_details_media_add' );
			if ( ! add ) {
				return;
			}
			pro_menus_ensure_back();
			var scope = ( add.classList && add.classList.contains( 'ec_admin_product_image_add_tile' ) ) ? add : add.closest( '.ec_admin_product_image_container' );
			if ( ! scope ) {
				return;
			}
			var $menus = $( scope ).find( '.ec_admin_product_image_menu' );
			var main_open = $menus.first().is( ':visible' );
			$menus.each( function() {
				$( this ).hide();
			} );
			if ( main_open ) {
				e.stopPropagation();
				e.preventDefault();
			}
		}, true );

		/* Whole-tile open ( template-version proof ): the add tile opens its
		 * root menu no matter which PHP markup is deployed — older templates
		 * put the inline onclick on the "+" icon only, leaving the rest of
		 * the box dead. Runs in the bubble phase: the capture-phase toggle
		 * above has already handled the toggle-closed case ( it stops
		 * propagation when the main menu was open ), and on newer markup the
		 * tile's own inline handler has already opened the menu, which makes
		 * this a no-op via the visibility check. */
		$( document ).on( 'click', '.ec_admin_product_image_add_tile', function( e ) {
			if ( $( e.target ).closest( '.ec_admin_product_image_menu' ).length ) {
				return; /* clicks inside an open menu manage themselves */
			}
			var $menus = $( this ).find( '.ec_admin_product_image_menu' );
			if ( ! $menus.length || $menus.filter( ':visible' ).length ) {
				return;
			}
			pro_menus_ensure_back();
			$menus.first().show();
		} );

		/* Bubble phase: a click that isn't inside a menu, on a "+", or in the
		 * WP media modal closes any open menu. */
		document.addEventListener( 'click', function( e ) {
			if ( ! $( '.ec_admin_product_image_menu:visible' ).length ) {
				return;
			}
			var t = e.target;
			if ( t.closest && ( t.closest( '.ec_admin_product_image_menu' ) || t.closest( '.ec_admin_product_image_add_tile' ) || t.closest( '.ec_admin_product_details_media_add' ) || is_wp_media_target( t ) ) ) {
				return;
			}
			pro_menus_hide_all();
		}, false );

		document.addEventListener( 'keydown', function( e ) {
			if ( 'Escape' === e.key && $( '.ec_admin_product_image_menu:visible' ).length ) {
				pro_menus_hide_all();
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Sale-pricing live preview ( Price + Previous Price, General tab )    */
	/* ------------------------------------------------------------------ */

	/* Shows exactly what shoppers will see when a Previous Price is set:
	 *   Shoppers see:  ~$39.95~  $34.96  [12% off]
	 * and warns when the previous price would not display ( <= price ). */
	function bind_sale_preview() {
		var price_el = el( 'price' );
		var list_el  = el( 'list_price' );
		if ( ! price_el || ! list_el ) {
			return;
		}
		var field = $( list_el ).closest( '.ecdv2-field' );
		if ( ! field.length ) {
			return;
		}
		var preview = $( '<span class="ecdv2-sale-preview" id="ecdv2_sale_preview" aria-live="polite"></span>' );
		field.append( preview );

		function update() {
			var price = parseFloat( $( price_el ).val() ) || 0;
			var list  = parseFloat( $( list_el ).val() ) || 0;
			if ( list > 0 && price > 0 && list > price ) {
				/* No savings-percentage tag here: whether a "% off" badge
				 * appears on the front-end depends on theme/display settings,
				 * so the preview only promises what always renders — the
				 * crossed-out previous price beside the current price. */
				preview.html(
					'<span class="ecdv2-sale-preview-lead">' + esc_html( _t( 'shoppers_see', 'Shoppers see:' ) ) + '</span> ' +
					'<span class="ecdv2-sale-preview-was">' + esc_html( money( list ) ) + '</span> ' +
					'<span class="ecdv2-sale-preview-now">' + esc_html( money( price ) ) + '</span>'
				).addClass( 'is-visible' ).removeClass( 'is-warning' );
			} else if ( list > 0 && list <= price ) {
				preview.html(
					'<span class="ecdv2-sale-preview-warn">' + esc_html( _t( 'previous_price_low', 'Previous Price only displays when it is higher than the current Price.' ) ) + '</span>'
				).addClass( 'is-visible is-warning' );
			} else {
				preview.removeClass( 'is-visible is-warning' ).empty();
			}
		}

		$( document ).on( 'input change', '#price, #list_price', update );
		update();
	}

	/* The price field, wherever it is ( Pricing's Price card since 6.0.4, a new product's Basics ). Kept for callers of
	 * ecdv2.edit_base_price(). */
	function edit_base_price() {
		var input = $( '#price' );
		if ( ! input.length ) {
			return;
		}
		go_tab( input.closest( '.ecdv2-panel' ).attr( 'data-ecdv2-panel' ) || 'pricing', { no_scroll: true } );
		var field = input.closest( '.ecdv2-field' );
		if ( ! field.length ) {
			field = input;
		}
		field[ 0 ].scrollIntoView( { block: 'center', behavior: 'smooth' } );
		field.addClass( 'ecdv2-flash' );
		setTimeout( function() {
			field.removeClass( 'ecdv2-flash' );
		}, 1800 );
		/* Focus without a second jump; the smooth scroll above positions it. */
		try {
			input[ 0 ].focus( { preventScroll: true } );
		} catch ( e ) {
			input[ 0 ].focus();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Featured products — progressive add / remove                         */
	/* ------------------------------------------------------------------ */

	/*
	 * Four fixed "Featured Product N" selects read as a form, not a list.
	 * This turns them into logical add/remove: chosen products render as
	 * removable rows, and a single "Add" picker ( the first empty select,
	 * unhidden and relabeled — its select2 keeps working, no cloning of a
	 * potentially huge product list ) appears while slots remain. The four
	 * real selects stay the source of truth: the payload, dirty tracking,
	 * and the save endpoint are untouched. Removing compacts selections up
	 * so slots 1..n stay contiguous.
	 */
	function bind_featured_products() {
		var ids = [ 'featured_product_id_1', 'featured_product_id_2', 'featured_product_id_3', 'featured_product_id_4' ];
		var selects = $.map( ids, function( id ) {
			var node = el( id );
			return node ? node : null;
		} );
		if ( selects.length !== 4 ) {
			return; /* fields filtered out or renamed — leave the classic UI */
		}
		var card = $( selects[ 0 ] ).closest( '.ecdv2-card[data-ecdv2-section="featured_products"]' );
		if ( ! card.length ) {
			return;
		}
		var grid = $( selects[ 0 ] ).closest( '.ecdv2-grid' );
		var list = $( '<div class="ecdv2-featured-list ecdv2-field-full" id="ecdv2_featured_list"></div>' );
		grid.prepend( list );
		var rebuilding = false;

		/* Drag to reorder ( slots are positional, so order = display order on
		 * the storefront ). jQuery UI sortable ships on this page for the
		 * options tables; degrade to a static list if it's ever absent. */
		if ( $.fn.sortable ) {
			list.sortable( {
				items: '.ecdv2-featured-row',
				handle: '.ecdv2-featured-drag',
				axis: 'y',
				placeholder: 'ecdv2-featured-placeholder',
				forcePlaceholderSize: true,
				update: function() {
					var items = [];
					list.find( '.ecdv2-featured-row' ).each( function() {
						items.push( $( this ).data( 'ecdv2-item' ) );
					} );
					compact( items );
					rebuild();
					toast( _t( 'featured_reordered', 'Featured products reordered.' ), 'success' );
				}
			} );
		}

		function is_empty( val ) {
			return '' === String( val == null ? '' : val ) || '0' === String( val );
		}

		/* The "None Selected" option's value varies ( '0' vs '' depending on
		 * the field's default_value ) — clearing to a value the select
		 * doesn't contain leaves the select2 box BLANK with no placeholder
		 * text. Always clear to the select's own first-option value. */
		function empty_value( sel ) {
			return sel.options.length ? sel.options[ 0 ].value : '';
		}

		function chosen() {
			var out = [];
			$.each( selects, function( i, sel ) {
				if ( ! is_empty( sel.value ) ) {
					var opt = $( sel ).find( 'option:selected' );
					out.push( { value: sel.value, label: opt.text(), inactive: !! opt.attr( 'data-ec-inactive' ) } );
				}
			} );
			return out;
		}

		/* Re-assign the chosen set to slots 1..n and clear the rest, firing
		 * change only where a value actually moved. */
		function compact( items ) {
			rebuilding = true;
			$.each( selects, function( i, sel ) {
				var next = ( i < items.length ) ? String( items[ i ].value ) : empty_value( sel );
				if ( String( sel.value ) !== next && ! ( is_empty( sel.value ) && is_empty( next ) ) ) {
					$( sel ).val( next ).trigger( 'change' );
				}
			} );
			rebuilding = false;
		}

		function rebuild() {
			var items = chosen();
			list.empty();
			$.each( items, function( i, item ) {
				var row = $( '<div class="ecdv2-featured-row"></div>' ).data( 'ecdv2-item', item );
				if ( $.fn.sortable ) {
					row.append( '<span class="ecdv2-featured-drag" title="' + _t( 'drag_to_reorder', 'Drag to reorder' ) + '"><span class="dashicons dashicons-menu"></span></span>' );
				}
				row.append( $( '<span class="ecdv2-featured-num"></span>' ).text( i + 1 ) );
				row.append( $( '<span class="ecdv2-featured-title"></span>' ).text( item.label ) );
				if ( item.inactive ) {
					row.append( $( '<span class="ecdv2-featured-inactive"></span>' )
						.text( _t( 'inactive', 'Inactive' ) )
						.attr( 'title', _t( 'featured_inactive_hint', 'This product is deactivated and will not display on the storefront until activated.' ) ) );
				}
				row.append(
					$( '<button type="button" class="ecdv2-featured-remove"></button>' )
						.attr( 'title', _t( 'remove', 'Remove' ) )
						.html( '&times;' )
						.on( 'click', function() {
							var next = chosen();
							next.splice( i, 1 );
							compact( next );
							rebuild();
							return false;
						} )
				);
				list.append( row );
			} );


			/* Show exactly one picker: the first EMPTY select ( legacy data can
			 * hold gaps like slots 1 + 3; never expose an occupied slot, and
			 * never force-compact at boot — that would flag unsaved changes
			 * the user didn't make. Gaps self-heal on the first remove ). */
			var picker_shown = false;
			$.each( selects, function( i, sel ) {
				var wrap = $( sel ).closest( '.ecdv2-field' );
				var open_slot = ( ! picker_shown && is_empty( sel.value ) && items.length < selects.length );
				wrap.toggle( open_slot );
				if ( open_slot ) {
					picker_shown = true;
					wrap.find( '.ecdv2-label' ).text(
						items.length
							? _t( 'featured_add_another', 'Add another featured product ({1} of 4 used)' ).replace( '{1}', items.length )
							: _t( 'featured_add_first', 'Add a featured product' )
					);
				}
			} );

			if ( items.length >= selects.length ) {
				list.append( $( '<div class="ecdv2-featured-full"></div>' ).text( _t( 'featured_full', 'All 4 featured slots are in use. Remove one to swap in another product.' ) ) );
			}
		}

		/* Any change on the four ( user pick in the visible picker, or an
		 * external write ) re-renders; programmatic compaction is guarded. */
		$.each( selects, function( i, sel ) {
			$( sel ).on( 'change', function() {
				if ( ! rebuilding ) {
					rebuild();
				}
			} );
		} );

		rebuild();
	}

	/* ------------------------------------------------------------------ */
	/* Pricing-context chips ( General tab ) — live updates                 */
	/* ------------------------------------------------------------------ */

	/*
	 * The "Also affects pricing" row is server-rendered for first paint, then
	 * kept in sync client-side: chips appear the moment Login for Pricing is
	 * toggled on, and disappear when the last volume tier is deleted, without
	 * a reload. State sources, all read live:
	 *   - volume tiers   : row count in #price_tiers_holder
	 *   - B2B roles      : row count in #role_prices_holder
	 *   - label/range/
	 *     login toggles  : the Pricing-tab controls themselves
	 *   - variants       : server-seeded counts on the container, refined by
	 *                      the PRO variants card's own show/hide state and,
	 *                      when it appears fresh, its paging total
	 */
	function bind_price_context() {
		var wrap = $( '#ecdv2_price_context' );
		if ( ! wrap.length ) {
			return;
		}
		var chips_holder = $( '#ecdv2_price_context_chips' );
		var refresh_timer = null;

		function to_int( raw ) {
			return parseInt( raw, 10 ) || 0;
		}

		function variant_state() {
			var total  = to_int( wrap.attr( 'data-variant-total' ) );
			var priced = to_int( wrap.attr( 'data-variant-priced' ) );
			var card = document.getElementById( 'wpeasycart_product_variants' );
			if ( card ) {
				/* Only the card's OWN inline display matters — jQuery's
				 * :visible would report hidden whenever the Options tab
				 * panel itself is inactive. */
				if ( 'none' === card.style.display ) {
					return { total: 0, priced: 0 };
				}
				/* Card appeared after load ( option set just added ): pull the
				 * overall count from the paging summary text. */
				if ( ! total ) {
					var match = /of\s+([\d.,\s]+)/.exec( $( card ).find( '.wp-easycart-pro-option-table-paging-count' ).first().text() );
					if ( match ) {
						total = to_int( match[ 1 ].replace( /[^\d]/g, '' ) );
					}
				}
				/* Priced floor from the currently rendered price inputs —
				 * never shrinks the seed, only reflects fresh entries. */
				var live_priced = $( card ).find( 'input[id^="wpec_variant_price_"]' ).filter( function() {
					return '' !== $.trim( this.value );
				} ).length;
				priced = Math.max( priced, live_priced );
			}
			return { total: total, priced: priced };
		}

		function chip( label, tab ) {
			return $( '<button/>', { type: 'button', 'class': 'ecdv2-price-context-chip' } )
				.text( label )
				.append( '<span class="dashicons dashicons-arrow-right-alt2"></span>' )
				.on( 'click', function() {
					go_tab( tab );
				} );
		}

		function refresh() {
			var chips = [];

			var vs = variant_state();
			if ( vs.total > 0 ) {
				chips.push( chip(
					vs.priced > 0
						? _t( 'ctx_variant_priced', 'Variant pricing ({1} of {2} priced)' ).replace( '{1}', vs.priced ).replace( '{2}', vs.total )
						: _t( 'ctx_variants', 'Variant pricing & stock ({1} variants)' ).replace( '{1}', vs.total ),
					'options'
				) );
			}

			var tiers = $( '#price_tiers_holder .ec_admin_price_tier_row' ).length;
			if ( tiers > 0 ) {
				chips.push( chip(
					1 === tiers ? _t( 'ctx_tier_one', 'Volume pricing (1 tier)' ) : _t( 'ctx_tier_many', 'Volume pricing ({1} tiers)' ).replace( '{1}', tiers ),
					'pricing'
				) );
			}

			var roles = $( '#role_prices_holder .ec_admin_role_price_row' ).length;
			if ( roles > 0 ) {
				chips.push( chip(
					1 === roles ? _t( 'ctx_role_one', 'B2B pricing (1 role)' ) : _t( 'ctx_role_many', 'B2B pricing ({1} roles)' ).replace( '{1}', roles ),
					'pricing'
				) );
			}

			if ( to_int( v( 'enable_price_label' ) ) > 0 ) {
				chips.push( chip( _t( 'ctx_price_label', 'Custom price label' ), 'pricing' ) );
			}
			if ( 1 === to_int( c( 'show_custom_price_range' ) ) ) {
				chips.push( chip( _t( 'ctx_price_range', 'Price range display' ), 'pricing' ) );
			}
			if ( 1 === to_int( c( 'login_for_pricing' ) ) ) {
				chips.push( chip( _t( 'ctx_login_pricing', 'Login for pricing' ), 'pricing' ) );
			}

			chips_holder.empty();
			if ( ! chips.length ) {
				wrap.hide();
				return;
			}
			$.each( chips, function( i, node ) {
				chips_holder.append( node );
			} );
			wrap.show();
		}

		function refresh_soon() {
			clearTimeout( refresh_timer );
			refresh_timer = setTimeout( refresh, 150 );
		}

		/* Pricing-tab toggles ( fire immediately for a responsive feel ). */
		$( document ).on( 'change', '#enable_price_label, #show_custom_price_range, #login_for_pricing', refresh_soon );

		/* Tier + B2B managers: any row added or removed refreshes the row. */
		$.each( [ 'price_tiers_holder', 'role_prices_holder' ], function( i, holder_id ) {
			var holder = document.getElementById( holder_id );
			if ( holder ) {
				new MutationObserver( refresh_soon ).observe( holder, { childList: true } );
			}
		} );

		/* PRO variants card: option sets added/removed toggle its inline
		 * display and re-render its rows; variant price entries fire change. */
		var variants_card = document.getElementById( 'wpeasycart_product_variants' );
		if ( variants_card ) {
			new MutationObserver( refresh_soon ).observe( variants_card, { attributes: true, attributeFilter: [ 'style' ], childList: true, subtree: true } );
		}
		$( document ).on( 'change', 'input[id^="wpec_variant_price_"]', refresh_soon );
	}

	/* ------------------------------------------------------------------ */
	/* Variation-stock quick link ( Inventory tab )                         */
	/* ------------------------------------------------------------------ */

	/* When Track Quantity is set to "Track Variation Quantity" ( value 2 ),
	 * per-variant stock is edited in the Variations table on the Options
	 * tab — surface that with an inline note + one-click jump so nobody has
	 * to hunt the left menu for where quantities actually live. */
	function bind_variant_stock_note() {
		var note = $( '#ecdv2_variant_stock_note' );
		if ( ! note.length ) {
			return;
		}
		function refresh() {
			note.toggle( '2' === String( v( 'stock_quantity_type' ) ) );
		}
		$( document ).on( 'change', '#stock_quantity_type', refresh );
		refresh();
	}

	/* ------------------------------------------------------------------ */
	/* Manufacturer quick-create ( General tab )                            */
	/* ------------------------------------------------------------------ */

	/* Replaces the legacy handler from products.js on the v2 editor. The
	 * legacy version set the select's value without triggering change, so
	 * the select2-rendered box never visually updated, and it JSON.parsed
	 * the raw response with no error handling ( a failed nonce returns "0"
	 * and died silently ). This one validates, surfaces errors as toasts,
	 * appends + selects the new manufacturer, and fires change so select2
	 * refreshes and the General section is marked dirty for save. */
	function create_manufacturer() {
		var name = $.trim( v( 'manufacturer_name' ) );
		if ( '' === name ) {
			toast( _t( 'manufacturer_name_required', 'Enter a manufacturer name first.' ), 'error' );
			$( el( 'manufacturer_name' ) ).focus();
			return false;
		}
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_product_details_insert_manufacturer',
				wp_easycart_nonce : v( 'manufacturer_new_nonce' ),
				manufacturer_name : name
			},
			success : function( raw ) {
				var data = null;
				try {
					data = ( 'string' === typeof raw ) ? JSON.parse( raw ) : raw;
				} catch ( err ) {
					data = null;
				}
				if ( ! data || ! data.manufacturer_id ) {
					toast( _t( 'manufacturer_create_failed', 'Could not create the manufacturer. Please reload and try again.' ), 'error' );
					return;
				}
				var $select = $( el( 'manufacturer_id' ) );
				if ( ! $select.find( 'option[value="' + data.manufacturer_id + '"]' ).length ) {
					$select.append( $( '<option/>', { value: data.manufacturer_id, text: data.name } ) );
				}
				/* trigger('change') refreshes the select2 display AND marks
				 * the General section dirty so the assignment gets saved. */
				$select.val( String( data.manufacturer_id ) ).trigger( 'change' );
				$( el( 'manufacturer_name' ) ).val( '' );
				if ( $( '#ecdv2_manufacturer_new' ).length ) {
					manufacturer_new( false );
				}
				toast( _t( 'manufacturer_created', 'Manufacturer created and selected. Save to assign it to this product.' ), 'success' );
			},
			error   : function() {
				toast( _t( 'manufacturer_create_failed', 'Could not create the manufacturer. Please reload and try again.' ), 'error' );
			}
		} );
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* PRO gate rows                                                        */
	/* ------------------------------------------------------------------ */

	function gate_toggle( head ) {
		$( head ).parent().toggleClass( 'is-open' );
	}

	/* ------------------------------------------------------------------ */
	/* Toasts                                                               */
	/* ------------------------------------------------------------------ */

	function toast( message, type ) {
		var node = $( '<div class="ecdv2-toast is-' + ( type || 'info' ) + '"/>' ).text( message );
		$( '#ecdv2_toasts' ).append( node );
		setTimeout( function() {
			node.addClass( 'is-visible' );
		}, 10 );
		setTimeout( function() {
			node.removeClass( 'is-visible' );
			setTimeout( function() {
				node.remove();
			}, 350 );
		}, 3200 );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

	$( function() {
		bind_dirty_tracking();
		bind_tabsets();
		bind_dependencies();
		bind_search();
		bind_slug();
		bind_colors();
		bind_counters();
		bind_menu();
		bind_pro_media_menus();
		set_currency_var();
		bind_role_select();
		bind_tier_rows();
		watch_pricing_holders();
		bind_unit_preview();
		bind_sale_preview();
		bind_variant_stock_note();
		bind_price_context();
		bind_featured_products();
		bind_feeds();
		bind_yoast_preview();
		bind_seo_fallback();
		bind_search_ai_preview();

		/* Override the legacy handler from products.js ( ready runs after
		 * every script has parsed, so this assignment always wins ). */
		window.ec_admin_product_details_add_new_manufacturer = create_manufacturer;
		/* 6.0.4 ( editor plan Phase 2 ): volume pricing and role prices wait for Save. */
		if ( $( '.ecdv2-card[data-ecdv2-section="price_rules"]' ).length ) {
			window.ec_admin_product_details_add_price_tier = tier_add;
			window.ec_admin_product_details_edit_price_tier = tier_edit;
			window.ec_admin_product_details_delete_price_tier = tier_remove;
			window.ec_admin_product_details_delete_role_price = role_price_remove;
		}

		$( document ).on( 'change input', '#tag_text, #tag_type, #tag_bg_color, #tag_text_color', update_badge_preview );
		update_badge_preview();

		if ( $.fn.select2 ) {
			$( '.ecdv2-select2' ).each( function() {
				if ( ! $( this ).hasClass( 'select2-hidden-accessible' ) ) {
					$( this ).select2( { width: '100%' } );
				}
			} );
			/* Search-as-you-type pickers ( featured products ): the select only holds its
			 * current value; results come from the server 25 at a time. */
			$( '.ecdv2-select2-ajax[data-ecdv2-ajax="products"]' ).each( function() {
				var $sel = $( this );
				if ( $sel.hasClass( 'select2-hidden-accessible' ) ) { return; }
				var exclude = parseInt( $sel.attr( 'data-ecdv2-exclude' ), 10 ) || 0;
				$sel.select2( {
					width: '100%',
					placeholder: $sel.find( 'option' ).first().text() || '',
					minimumInputLength: 0,
					ajax: {
						url: wpeasycart_admin_ajax_object.ajax_url,
						type: 'POST',
						dataType: 'json',
						delay: 250,
						cache: true,
						data: function( params ) {
							return { action: 'ec_admin_ajax_ecv2_product_search', q: params.term || '', page: params.page || 1, exclude: exclude };
						},
						processResults: function( data, params ) {
							params.page = params.page || 1;
							return { results: ( data && data.results ) ? data.results : [], pagination: { more: !! ( data && data.more ) } };
						}
					},
					templateResult: function( item ) {
						if ( ! item.id || item.loading ) { return item.text; }
						var $r = $( '<span class="ecdv2-product-result"></span>' ).text( item.title || item.text );
						if ( item.sku ) { $r.append( $( '<code></code>' ).text( item.sku ) ); }
						if ( item.inactive ) { $r.append( $( '<em></em>' ).text( _t( 'inactive', 'inactive' ) ) ); }
						return $r;
					},
					templateSelection: function( item ) { return item.title || item.text; },
					language: {
						searching: function() { return _t( 'searching', 'Searching…' ); },
						noResults: function() { return _t( 'no_products', 'No products match' ); },
						loadingMore: function() { return _t( 'loading_more', 'Loading more…' ); }
					}
				} );
				/* Carry the inactive flag onto the <option> select2 creates, so the featured
				 * list can badge it exactly as it does for server-rendered options. */
				$sel.on( 'select2:select', function( e ) {
					var d = e.params && e.params.data ? e.params.data : {};
					var $opt = $sel.find( 'option[value="' + d.id + '"]' );
					if ( d.inactive ) { $opt.attr( 'data-ec-inactive', '1' ); } else { $opt.removeAttr( 'data-ec-inactive' ); }
					if ( d.title ) { $opt.text( d.title ); }
				} );
			} );
			/* Manufacturer + option-set pickers: the select holds only its current value;
			 * matches come from ecv2_manufacturer_search ( 40/page ) and
			 * ecv2_option_set_search ( 30/page ). Nonce rides on the element. */
			var ajax_pickers = {
				manufacturers : { action: 'ecv2_manufacturer_search', none: _t( 'no_manufacturers', 'No manufacturers match' ) },
				option_sets   : { action: 'ecv2_option_set_search', none: _t( 'no_option_sets', 'No option sets match' ) }
			};
			$( '.ecdv2-select2-ajax[data-ecdv2-ajax="manufacturers"], .ecdv2-select2-ajax[data-ecdv2-ajax="option_sets"]' ).each( function() {
				var $sel = $( this ), kind = $sel.attr( 'data-ecdv2-ajax' ), cfg = ajax_pickers[ kind ];
				if ( ! cfg || $sel.hasClass( 'select2-hidden-accessible' ) ) { return; }
				$sel.select2( {
					width: '100%',
					placeholder: $sel.find( 'option' ).first().text() || '',
					minimumInputLength: 0,
					ajax: {
						url: wpeasycart_admin_ajax_object.ajax_url,
						type: 'POST',
						dataType: 'json',
						delay: 250,
						cache: true,
						data: function( params ) {
							/* product_id: a product's own option sets ( 6.0.2 ) are listed in its own editor only. */
							return { action: cfg.action, q: params.term || '', page: params.page || 1, product_id: v( 'product_id' ), wp_easycart_nonce: $sel.attr( 'data-ecdv2-nonce' ) };
						},
						processResults: function( data, params ) {
							params.page = params.page || 1;
							var d = ( data && data.data ) ? data.data : data;
							return { results: ( d && d.results ) ? d.results : [], pagination: { more: !! ( d && d.more ) } };
						}
					},
					language: {
						searching: function() { return _t( 'searching', 'Searching…' ); },
						noResults: function() { return cfg.none; },
						loadingMore: function() { return _t( 'loading_more', 'Loading more…' ); }
					}
				} );
				if ( 'option_sets' === kind ) {
					/* Remember the choice count so the variation total stays right. */
					$sel.on( 'select2:select', function( e ) {
						var d = e.params && e.params.data ? e.params.data : {};
						if ( ! d.id ) { return; }
						var $wrap = $( '#ecdv2_opt_slots' ), counts;
						try { counts = JSON.parse( $wrap.attr( 'data-counts' ) || '{}' ); } catch ( err ) { counts = {}; }
						counts[ d.id ] = parseInt( d.item_count, 10 ) || 0;
						$wrap.attr( 'data-counts', JSON.stringify( counts ) );
						/* select2 fires change ( and the first render ) before this event. */
						if ( typeof window.ecdv2_opt_render === 'function' ) { window.ecdv2_opt_render(); }
						media_pro_stale = true;
					} );
				}
			} );
		}

		/* 6.0.4: Page / Focus, Edit / Insights, the summary column, previous / next. The settings are printed by the template,
		   after this file ( which loads in the head ), so they are read here. */
		vcfg = ( window.wpeasycart_ecdv2_view && 'object' === typeof window.wpeasycart_ecdv2_view ) ? window.wpeasycart_ecdv2_view : null;
		view = ( vcfg && 'page' === vcfg.view ) ? 'page' : 'focus';
		if ( vcfg && $( '#ecdv2_wrap' ).hasClass( 'ecdv2-product' ) ) {
			set_rail_active( current_key() );
			bind_views();
			bind_type();
			bind_draft_sku();
		}

		route_initial();

		/* 6.0.4: what each section holds as saved, now that every card has drawn ( editors that start later take theirs
		 * then, bind_editor_dirty ). Anything marked unsaved while the page was still drawing is checked against it. */
		take_snapshots();
		var key;
		for ( key in dirty ) {
			if ( dirty.hasOwnProperty( key ) && section_unchanged( key ) ) {
				delete dirty[ key ];
			}
		}
		paint_dirty();

		if ( -1 !== window.location.search.indexOf( 'ecdv2_created=1' ) ) {
			toast( vt( 'created', 'Product created.' ), 'success' );
			if ( window.history && history.replaceState ) {
				history.replaceState( null, '', window.location.href.replace( /&ecdv2_created=1/, '' ) );
			}
		}
	} );

	/* Public API ( used by inline onclick handlers in PHP templates ). */
	return {
		download_qr: download_qr,
		go_tab           : go_tab,
		edit_base_price  : edit_base_price,
		go_card          : go_card,
		save_all         : save_all,
		quick_activate   : quick_activate,
		mark_dirty       : mark_dirty,
		register_section : register_section,
		gate_toggle      : gate_toggle,
		menu_toggle      : menu_toggle,
		menu_close       : menu_close,
		media_pick       : media_pick,
		media_clear      : media_clear,
		category_add     : category_add,
		category_remove  : category_remove,
		slug_lock_toggle : slug_lock_toggle,
		review_toggle    : review_toggle,
		load_activity    : load_activity,
		feed_reload      : feed_reload,
		add_role_price   : add_role_price,
		field_error      : field_error,
		field_ok         : field_ok,
		toast            : toast,
		/* 6.0.4 */
		discard          : discard,
		errors_hide      : errors_hide,
		set_view         : set_view,
		set_mode         : set_mode,
		fold             : fold,
		save_next        : save_next,
		manufacturer_new : manufacturer_new,
		menu_location_add: menu_location_add,
		show_pane_of     : show_pane_of,
		side_drawer      : side_drawer
	};

} )( window.jQuery );
/* ---- Reviews card: the allow toggle mirrors the hidden carrier in the general card ( 6.0.4: and saves with the product ) ---- */
/* ---- 6.0.2: fields a managed product's service owns ( wp_easycart_product_lock ). A partly locked card lists them in
 * data-ecdv2-locked-ids and they are shown read-only; a card that is locked as a whole carries a cover. The server keeps
 * their stored value whatever is posted. ---- */
( function( $ ) {
	function lock_editor( id ) {
		if ( typeof tinymce === 'undefined' || ! tinymce.get ) { return; }
		var ed = tinymce.get( id );
		if ( ! ed ) { return; }
		var set = function() {
			try {
				if ( ed.mode && 'function' === typeof ed.mode.set ) { ed.mode.set( 'readonly' ); } else if ( 'function' === typeof ed.setMode ) { ed.setMode( 'readonly' ); }
			} catch ( err ) {}
		};
		if ( ed.initialized ) { set(); } else if ( 'function' === typeof ed.on ) { ed.on( 'init', set ); }
	}
	function lock_cards() {
		$( '.ecdv2-card[data-ecdv2-locked-ids]' ).each( function() {
			$.each( String( $( this ).attr( 'data-ecdv2-locked-ids' ) || '' ).split( ',' ), function( i, id ) {
				if ( ! id ) { return; }
				var node = document.getElementById( id );
				if ( node ) {
					$( node ).prop( 'disabled', true ).attr( 'aria-disabled', 'true' ).closest( '.ecdv2-field' ).addClass( 'is-managed-locked' );
				}
				$( '[data-ecdv2-wpeditor="' + id + '"]' ).addClass( 'is-managed-locked' ).find( 'textarea, button, input' ).prop( 'disabled', true );
				lock_editor( id );
			} );
		} );
	}
	$( lock_cards );
	$( window ).on( 'load', lock_cards );
	if ( typeof tinymce !== 'undefined' && tinymce.on ) {
		tinymce.on( 'AddEditor', function() { setTimeout( lock_cards, 0 ); } );
	}
} )( jQuery );

function ecdv2_reviews_toggle( cb ) {
	var $card = jQuery( '#ecdv2-reviews-card' ), on = cb.checked ? 1 : 0;
	/* The product list's toast is only there when products-v2.js is loaded; fall back to the editor's own. */
	var say = function( message, type ) {
		if ( window.ecv2_toast ) { window.ecv2_toast( message, type ); }
		else if ( window.ecdv2 && 'function' === typeof window.ecdv2.toast ) { window.ecdv2.toast( message, type ); }
	};
	jQuery( '#use_customer_reviews' ).val( on ); /* general-options save posts this too */
	$card.toggleClass( 'is-off', ! on ); jQuery( '#ecdv2_reviews_off' ).toggle( ! on );
	if ( cb.getAttribute( 'data-new' ) ) { return; } /* unsaved product: the value rides along with the first save */
	/* 6.0.4 ( editor plan Phase 2 ): the card is a save section ( reviews ), so the switch waits for Save like the rest. */
	if ( $card.attr( 'data-ecdv2-section' ) && window.ecdv2 && 'function' === typeof window.ecdv2.mark_dirty ) { return; }
	jQuery.post( wpeasycart_admin_ajax_object.ajax_url, { action: 'ecdv2_reviews_toggle', product_id: $card.data( 'product-id' ), on: on, nonce: $card.data( 'nonce' ) }, function( r ) {
		if ( r && r.success ) { say( r.data.message, 'success' ); }
		else { cb.checked = ! on; jQuery( '#use_customer_reviews' ).val( on ? 0 : 1 ); $card.toggleClass( 'is-off', !! on ); jQuery( '#ecdv2_reviews_off' ).toggle( !! on ); say( ( r && r.data && r.data.message ) || 'Something went wrong.', 'error' ); }
	}, 'json' ).fail( function() {
		cb.checked = ! on; jQuery( '#use_customer_reviews' ).val( on ? 0 : 1 ); $card.toggleClass( 'is-off', !! on ); jQuery( '#ecdv2_reviews_off' ).toggle( !! on );
		say( 'Something went wrong.', 'error' );
	} );
}

/* 6.0.5: the lowest price of the last 30 days ( wp_easycart_admin_price_history ). The card records price history, not a product
 * field, so it saves on its own and never marks the page unsaved ( data-ecdv2-nodirty ). */
( function( $ ) {
	$( document ).on( 'click', '#ecdv2_prior_price_card .ecdv2-prior-toggle', function() {
		var form = $( '#ecdv2_prior_form' ), open = form.prop( 'hidden' );
		form.prop( 'hidden', ! open );
		$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			$( '#ecdv2_prior_price_value' ).trigger( 'focus' );
		}
	} );
	$( document ).on( 'click', '#ecdv2_prior_price_save', function() {
		var card = $( '#ecdv2_prior_price_card' ), btn = $( this ), msg = card.find( '.ecdv2-prior-msg' );
		var failed = function( text ) {
			btn.prop( 'disabled', false ).removeAttr( 'aria-busy' );
			msg.addClass( 'is-error' ).text( text || card.attr( 'data-failed' ) || '' );
		};
		btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );
		msg.removeClass( 'is-error' ).text( card.attr( 'data-saving' ) || '' );
		$.post( window.ajaxurl, {
			action: 'ecv2_product_prior_price',
			nonce: card.attr( 'data-nonce' ),
			product_id: card.attr( 'data-product-id' ),
			price: $( '#ecdv2_prior_price_value' ).val(),
			start: $( '#ecdv2_prior_price_start' ).val()
		} ).done( function( r ) {
			if ( r && r.success ) {
				$( '#ecdv2_prior_price_body' ).html( r.data.html );
			} else {
				failed( r && r.data && r.data.message ? r.data.message : '' );
			}
		} ).fail( function() {
			failed( '' );
		} );
	} );
} )( jQuery );
