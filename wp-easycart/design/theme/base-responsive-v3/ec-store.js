// JavaScript Document
function wp_easycart_init_location_buttons() {
	if ( jQuery( '.wpeasycart-location-popup' ).length ) {
		jQuery( '.wpeasycart-location-popup' ).appendTo( 'body' );
		if ( 0 == Number( wpeasycart_ajax_object.location_id ) ) {
			jQuery( '.wpeasycart-location-popup' ).fadeIn();
			wpeasycart_trigger_location_geo();
		}
		jQuery( '.wpeasycart-location-popup-find-btn' ).on( 'click', function() {
			jQuery( '.wpeasycart-location-list-loader' ).show();
			jQuery( '.wpeasycart-location-list' ).html( '' );
			var data = {
				action: 'ec_ajax_location_search',
				search: jQuery( '#wpeasycart_location_input' ).val(),
				nonce: jQuery( '#wpeasycart_location_nonce' ).val()
			};
			jQuery.ajax( {
				url: wpeasycart_ajax_object.ajax_url,
				type: 'post',
				data: data,
				cache: false,
				success: function( response ){
					jQuery( '.wpeasycart-location-list-loader' ).hide();
					if ( response.data.locations ) {
						wpeasycart_load_locations( response.data.locations );
					}
				}
			} );
		} );
		jQuery( '.wpeasycart-location-popup-use-location-btn' ).on( 'click', function() {
			wpeasycart_trigger_location_geo();
		} );
		jQuery( document ).on( 'click', '.wpeasycart-location-popup-select-store-btn', function() {
			jQuery( '.wpeasycart-location-list-loader' ).show();
			jQuery( '.wpeasycart-location-list' ).html( '' );
			var data = {
				action: 'ec_ajax_location_set_selected',
				location_id: jQuery( this ).attr( 'data-location-id' ),
				nonce: jQuery( '#wpeasycart_location_nonce' ).val()
			};
			jQuery.ajax( {
				url: wpeasycart_ajax_object.ajax_url,
				type: 'post',
				data: data,
				cache: false,
				success: function( response ){
					location.reload();
				}
			} );
		} );
		jQuery( '.wpeasycart-location-popup-modal-close-btn' ).on( 'click', function() {
			jQuery( '.wpeasycart-location-popup' ).fadeOut();
		} );
		jQuery( '.ec_product_select_location' ).on( 'click', function() {
			jQuery( '.wpeasycart-location-popup' ).fadeIn();
			if ( jQuery( this ).attr( 'data-product-id' ) ) {
				wpeasycart_trigger_location_geo( Number( jQuery( this ).attr( 'data-product-id' ) ) );
			} else if ( jQuery( this ).attr( 'data-type' ) && 'cart' == jQuery( this ).attr( 'data-type' ) ) {
				wpeasycart_trigger_location_geo( false, 'cart' );
			} else {
				wpeasycart_trigger_location_geo();
			}
		} );
	}
}
/* 6.0.2: the elements matching selector inside container, and container itself when it matches. */
function wpeasycart_init_find( container, selector ) {
	var scope = jQuery( container || document );
	return scope.find( selector ).add( scope.filter( selector ) );
}
/* 6.0.4: on a phone the account menu is a row of links that scrolls sideways ( ec-store.css .ec_account_nav_v2 ): the page shown is
   brought into view in it. */
function wpeasycart_account_nav_scroll( container ) {
	wpeasycart_init_find( container, '.ec_account_nav_v2 .ec_account_nav_items' ).each( function() {
		if ( this.scrollWidth <= this.clientWidth + 1 ) {
			return;
		}
		var current = jQuery( this ).children( '.is-current' ).get( 0 );
		if ( ! current || ! current.getBoundingClientRect ) {
			return;
		}
		/* Only when the current chip is not already in view, and then just far enough ( 16px to spare ). */
		var box = this.getBoundingClientRect(), chip = current.getBoundingClientRect();
		if ( chip.left < box.left ) {
			this.scrollLeft = Math.max( 0, this.scrollLeft - ( box.left - chip.left ) - 16 );
		} else if ( chip.right > box.right ) {
			this.scrollLeft = this.scrollLeft + ( chip.right - box.right ) + 16;
		}
	} );
}
/* 6.0.5: a product page has one h1, the title in the details column; its phone copy ( .ec_details_mobile_title_area ) is a div with
   role heading. Themes and store CSS style h1 tags ( weight, line height, margins ), so the copy takes the look an h1 gets in its
   place: an h1 with its classes is put just before it, read and taken out in the same task ( never drawn ). The template calls this
   right after the copy, so it applies before the first paint; without it the copy keeps ec-store.css's heading defaults. Again on
   resize, for themes whose h1 rules change with the width. */
var wpeasycart_title_look_props = [ 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'word-spacing', 'text-transform', 'text-align', 'margin-top', 'margin-bottom', 'padding-top', 'padding-bottom' ];
var wpeasycart_title_look_bound = false;
var wpeasycart_title_look_timer = 0;
function wpeasycart_title_look( container ) {
	var titles = wpeasycart_init_find( container, '.ec_details_mobile_title_area > div.ec_details_title' );
	if ( ! titles.length || ! window.getComputedStyle ) {
		return;
	}
	titles.each( function() {
		var probe = document.createElement( 'h1' );
		probe.className = this.className;
		probe.setAttribute( 'aria-hidden', 'true' );
		probe.textContent = 'x';
		this.parentNode.insertBefore( probe, this );
		var look = window.getComputedStyle( probe );
		for ( var i = 0; i < wpeasycart_title_look_props.length; i++ ) {
			this.style.setProperty( wpeasycart_title_look_props[ i ], look.getPropertyValue( wpeasycart_title_look_props[ i ] ) );
		}
		/* A line height without a unit ( 1.2 ) is laid out a fraction of a pixel away from the same height in px: on show, take the
		   height of one line as drawn. */
		if ( 'normal' !== look.getPropertyValue( 'line-height' ) && probe.getClientRects().length ) {
			var line = probe.getBoundingClientRect().height - parseFloat( look.paddingTop ) - parseFloat( look.paddingBottom ) - parseFloat( look.borderTopWidth ) - parseFloat( look.borderBottomWidth );
			if ( line > 0 ) {
				this.style.setProperty( 'line-height', line + 'px' );
			}
		}
		this.parentNode.removeChild( probe );
	} );
	if ( ! wpeasycart_title_look_bound ) {
		wpeasycart_title_look_bound = true;
		jQuery( window ).on( 'resize', function() {
			clearTimeout( wpeasycart_title_look_timer );
			wpeasycart_title_look_timer = setTimeout( function() {
				wpeasycart_title_look( document );
			}, 150 );
		} );
	}
}
/* 6.0.2: the same, minus the elements already set up for key; each one returned is marked, so nothing is bound twice. */
function wpeasycart_init_elements( container, selector, key ) {
	return wpeasycart_init_find( container, selector ).filter( function() {
		var done = this.wpeasycartInit || ( this.wpeasycartInit = {} );
		if ( done[ key ] ) {
			return false;
		}
		done[ key ] = true;
		return true;
	} );
}
/* 6.0.2: popups and quick views are moved to the end of the page, as before. A copy moved there by an earlier draw whose
 * widget has left the page ( the Elementor editor redrew it ) is removed first, so ids stay unique. */
function wpeasycart_init_to_body( container, selector, key ) {
	jQuery( document.body ).children( selector ).each( function() {
		var owner = jQuery.data( this, 'wpeasycartOwner' );
		if ( owner && ! jQuery.contains( document, owner ) ) {
			jQuery( this ).remove();
		}
	} );
	return wpeasycart_init_elements( container, selector, key ).each( function() {
		if ( this.parentNode && this.parentNode !== document.body ) {
			jQuery.data( this, 'wpeasycartOwner', this.parentNode );
		}
		jQuery( this ).appendTo( document.body );
	} );
}
/* 6.0.2: above 0 while wpeasycart_init() works out the first prices ( no wpeasycart_product_state for them ). */
var wpeasycart_state_quiet = 0;
/**
 * 6.0.2: sets up the store's product parts inside container ( an element, a jQuery set, or document ): galleries, thumbnails,
 * the magnifier and lightbox, quantity buttons, option swatches, selects and advanced options, the background add to cart,
 * review stars and paging, product tabs, sliders, quick views and popups, account order paging. Each element is set up once
 * ( a flag per element and part ), so calling it again for the same container, or for the whole page, is harmless. Runs for
 * the page when it is ready, for every WP EasyCart widget Elementor draws ( editor, popups, Loop Grid ), and after
 * EasyCart's own AJAX redraws. window.wpeasycart_init.
 *
 * @param {Element|jQuery|Document} container Where to look ( default: the page ).
 */
function wpeasycart_init( container ) {
	var ctx = container || document;
	/* 6.0.2: a tab area an extension draws ( WP EasyCart Tabs 3, wpeasycart_product_details_tabs_area ) is set up with the rest. */
	if ( 'function' === typeof window.wpecTabsInit ) {
		jQuery( ctx ).each( function() {
			window.wpecTabsInit( this );
		} );
	}
	/* Quick views move to the end of the page first, as before 6.0.2, so a looping Owl slider never clones them. Those moved
	 * out of a container ( a widget Elementor drew ) are still set up with it. */
	var wpec_quickviews = wpeasycart_init_to_body( ctx, '.ec_product_quickview_container', 'quickview' );
	if ( wpec_quickviews.length && ctx !== document ) {
		ctx = jQuery( ctx ).add( wpec_quickviews );
	}
	if ( 'function' === typeof window.wpeasycart_option_defaults ) {
		window.wpeasycart_option_defaults( ctx ); /* 6.0.2: default option picks ( ec-option-defaults.js ), read before the swatches below are set up */
	}
	wpeasycart_account_nav_scroll( ctx );
	wpeasycart_title_look( ctx );
	if ( wpeasycart_init_find( ctx, '.ec_add_to_cart_form, input[type="number"].ec_quantity' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_add_to_cart_form, input[type="number"].ec_quantity', 'keypress' ).on( 'keypress', function( e ) {
			if ( ! jQuery( e.target ).is( 'textarea' ) && e.which == 13 ) {
				return false;
			}
		} );
	}
	wpeasycart_init_elements( ctx, '.ec_flipbook_left', 'flipbookLeft' ).click( 
		function( event ){
			var current_image = jQuery( event.target ).parent( ).find( 'img.ec_flipbook_image' ).attr( 'src' );
			var image_list_string = jQuery( event.target ).parent( ).data( 'image-list' );
			var image_list = image_list_string.split( ',' ).filter( item => item !== '' );
			var prev = image_list[image_list.length - 1]; 
			for( var i=0; i<image_list.length; i++ ){ 
				if( image_list[i] == current_image ){ 
					break; 
				}else{ 
					prev = image_list[i]; 
				} 
			}
			jQuery( event.target ).parent( ).find( 'img.ec_flipbook_image' ).attr( 'src', prev );
		}
	);
	wpeasycart_init_elements( ctx, '.ec_flipbook_right', 'flipbookRight' ).click( 
		function( event ){
			var current_image = jQuery( event.target ).parent( ).find( 'img.ec_flipbook_image' ).attr( 'src' );
			var image_list_string = jQuery( event.target ).parent( ).data( 'image-list' );
			var image_list = image_list_string.split( ',' ).filter( item => item !== '' );
			var prev = image_list[0]; 
			for( var i=image_list.length-1; i>-1; i-- ){ 
				if( image_list[i] == current_image ){ 
					break; 
				}else{ 
					prev = image_list[i]; 
				} 
			}
			jQuery( event.target ).parent( ).find( 'img.ec_flipbook_image' ).attr( 'src', prev );
		}
	);
    wpeasycart_init_elements( ctx, '.ec_product_shortcode .owl-carousel', 'owl' ).each( function( ){
        jQuery( this ).on({
            'initialized.owl.carousel': function( ){
                jQuery( this ).find( '.wp-easycart-carousel-item' ).show( );
                jQuery( this ).parent( ).find( '.wpec-product-slider-loader' ).hide( );
            }

        }).owlCarousel( JSON.parse( jQuery( this ).attr( 'data-owl-options' ) ) );
    } );
	if( wpeasycart_isTouchDevice( ) ){
		wpeasycart_init_find( ctx, '.ec_product_quickview' ).hide( );
	}
	if( wpeasycart_init_find( ctx, '.ec_is_datepicker' ).length ){
        wpeasycart_init_elements( ctx, '.ec_is_datepicker', 'datepicker' ).datepicker( );
    }
    if( wpeasycart_init_find( ctx, '.ec_details_customer_review_list' ).length ){
        wpeasycart_init_elements( ctx, '.ec_details_customer_review_list', 'reviewPaging' ).each( function( ){
            var reviews = jQuery( this ).find( 'li' );
            var product_id = jQuery( this ).attr( 'data-product-id' );
            var reviews_per_page = jQuery( document.getElementById( 'ec_details_reviews_per_page_' + product_id ) ).val( );
            if( reviews.length > reviews_per_page ){
                var paging = '<div class="ec_details_customer_review_paging" id="ec_details_customer_review_paging_' + product_id + '">';
                for( var i=0; i<Math.ceil( reviews.length / reviews_per_page ); i++ ){
                    paging += '<button onclick="ec_customer_review_paging( ' + (i+1) + ', ' + product_id + ' );">' + ( i + 1 ) + '</button>';
                }
                paging += '</div>';
                jQuery( this ).after( paging );
                ec_customer_review_paging(1, product_id);
            }
        } );
	}
	/* 6.0.2: a background add sends the form as the browser would ( only the checked boxes and radios, textareas, files ).
	 * Only an add to cart goes in the background: a subscription, an inquiry, and an add the store refused or did not
	 * answer are posted the usual way, so the shopper gets the store's own page or message. */
	wpeasycart_init_elements( ctx, '.ec_add_to_cart_form_ajax', 'backgroundAdd' ).submit( function( e ) {
		var form = this;
		var form_action = jQuery( form ).find( 'input[name="ec_cart_form_action"]' ).last( ).val( );
		if ( 'add_to_cart_v3' != form_action || 'undefined' == typeof window.FormData ) {
			return true;
		}
		e.preventDefault( );
		if ( jQuery( form ).data( 'wpeasycartAdding' ) ) {
			return false;
		}
		jQuery( form ).data( 'wpeasycartAdding', true );
		var input_submit = jQuery( form ).find( 'input[type="submit"]' );
		jQuery( input_submit ).addClass( 'loading' ).parent( ).append( '<span class="dashicons dashicons-update-alt"></span>' );
		var form_data = new FormData( form );
		form_data.append( 'action', 'ec_ajax_add_to_cart_complete' );
		form_data.append( 'noredirect', '1' );
		var stop_loading = function( ) {
			jQuery( input_submit ).removeClass( 'loading' ).parent( ).find( '.dashicons-update-alt' ).remove( );
			jQuery( form ).data( 'wpeasycartAdding', false );
		};
		/* Posted again only when the add cannot have run ( nothing added, the request refused ): never a second add. */
		var post_normally = function( ) {
			stop_loading( );
			HTMLFormElement.prototype.submit.call( form );
		};
		/* The add may have gone in but its answer was unreadable: show the cart ( a plain GET, nothing is added again ). */
		var show_cart = function( ) {
			stop_loading( );
			var cart_url = jQuery( form ).attr( 'action' );
			if ( cart_url ) {
				window.location.href = cart_url;
			}
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: form_data,
			dataType: 'text',
			processData: false,
			contentType: false,
			cache: false,
			timeout: 90000,
			success: function( result ){
				var body = String( ( null == result ) ? '' : result ).replace( /^[﻿\s]+|\s+$/g, '' );
				if ( '' == body || '0' == body || '-1' == body ) {
					post_normally( ); /* refused before the add ( nonce, unknown action ) */
					return;
				}
				var json_data = null;
				try {
					json_data = JSON.parse( body );
				} catch ( err ) {
					/* Stray output ahead of the answer ( a BOM, a notice ): read the JSON that follows it. */
					try {
						json_data = ( body.indexOf( '[' ) > 0 ) ? JSON.parse( body.substring( body.indexOf( '[' ) ) ) : null;
					} catch ( err2 ) {
						json_data = null;
					}
				}
				if ( json_data && json_data[0] && false === json_data[0].added ) {
					post_normally( );
					return;
				}
				if ( ! json_data || ! json_data[0] ) {
					show_cart( );
					return;
				}
				stop_loading( );
				try {
					wpeasycart_update_cart_widgets( json_data );
				} catch ( err3 ) {}
				jQuery( input_submit ).parent( ).append( '<span class="dashicons dashicons-saved"></span>' );
				jQuery( input_submit ).addClass( 'added' );
				setTimeout(function() {
					jQuery( input_submit ).removeClass( 'added' ).parent().find( '.dashicons-saved' ).remove();
				}, 2000 );
				if ( json_data[0].item_added ) {
					try {
						jQuery( document ).trigger( 'wpeasycart_item_added', [ json_data[0].item_added ] ); /* 6.0.2 */
					} catch ( err4 ) {}
				}
				if ( wpeasycart_cart_widgets_present( ) ) {
					wpeasycart_refresh_cart_widgets( ); /* 6.0.2: the whole cart ( with images ) for the Cart Icon and wpeasycart_cart_changed */
				}
			},
			error: function( xhr, text_status ){
				if ( 'timeout' == text_status || ! xhr || ! xhr.status ) {
					stop_loading( ); /* no answer: the add may still go in, so nothing is sent again */
				} else if ( xhr.status >= 400 && xhr.status < 500 ) {
					post_normally( ); /* refused before the add */
				} else {
					show_cart( );
				}
			}
		} );
		return false;
	} );
	if( wpeasycart_init_find( ctx, '.ec_details_thumbnail' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_details_thumbnail', 'thumbnail' ).on( 'click', function() {
			var product_id = jQuery( this ).attr( 'data-product-id' );
			var rand_id = jQuery( this ).attr( 'data-rand-id' );
			var src = '';
			if ( jQuery( this ).find( 'img' ).attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).parent().attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).parent().attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).parent().parent().attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).parent().parent().attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).attr( 'data-src' ) ) {
				src = jQuery( this ).find( 'img' ).attr( 'data-src' );
			} else {
				src = jQuery( this ).find( 'img' ).attr( 'src' );
			}
			jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id + ' > .ec_details_thumbnail' ).removeClass( 'ec_active' );
			jQuery( this ).addClass( 'ec_active' );
			if( jQuery( this ).hasClass( 'ec_details_thumbnail_video' ) ) {
				var video_src = jQuery( this ).find( 'a' ).attr( 'href' );
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).hide();
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				if( jQuery( this ).hasClass( 'videoType' ) ) {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><video controls muted autoplay loop><source src="' + video_src + '" /></video></div>' );
				} else {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><iframe src="' + video_src + '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' );
				}
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src );
				jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).addClass( 'inactive' );
				jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'background', 'url( "' + src + '" ) no-repeat' );
			} else {
				if ( jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				if ( jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				if ( jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).show();
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src );
				jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).removeClass( 'inactive' );
				jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'background', 'url( "' + src + '" ) no-repeat' ).attr( 'data-bg', 'url( "' + src + '" ) no-repeat' );
			}
			return false;
		} );
	}
	if( wpeasycart_init_find( ctx, '.ec_details_large_popup_thumbnail' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_details_large_popup_thumbnail', 'popupThumbnail' ).on( 'click', function() {
			var product_id = jQuery( this ).attr( 'data-product-id' );
			var rand_id = jQuery( this ).attr( 'data-rand-id' );
			var src = '';
			if ( jQuery( this ).find( 'img' ).attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).parent().attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).parent().attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).parent().parent().attr( 'data-large-src' ) ) {
				src = jQuery( this ).find( 'img' ).parent().parent().attr( 'data-large-src' );
			} else if ( jQuery( this ).find( 'img' ).attr( 'data-src' ) ) {
				src = jQuery( this ).find( 'img' ).attr( 'data-src' );
			} else {
				src = jQuery( this ).find( 'img' ).attr( 'src' );
			}
			jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id + ' > .ec_details_large_popup_thumbnail' ).removeClass( 'ec_active' );
			jQuery( this ).addClass( 'ec_active' );
			if ( jQuery( this ).hasClass( 'ec_details_thumbnail_video' ) ) {
				var video_src = jQuery( this ).find( 'a' ).attr( 'href' );
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).hide();
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).hide();
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				
				if( jQuery( this ).hasClass( 'videoType' ) ) {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><video controls muted autoplay loop><source src="' + video_src + '" /></video></div>' );
					jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><video controls muted autoplay loop><source src="' + video_src + '" /></video></div>' );
				
				} else {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><iframe src="' + video_src + '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' );
					jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).append( '<div class="wp-easycart-video-box"><iframe src="' + video_src + '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>' );
				}
				jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).addClass( 'inactive' );
				jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'background', 'url( "' + src + '" ) no-repeat' );
			} else {
				if ( jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				if ( jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				if ( jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).length ) {
					jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).find( 'picture > source[type="image/webp"]' ).remove();
				}
				jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id + ' > .ec_details_thumbnail' ).removeClass( 'ec_active' );
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).show();
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( 'img' ).attr( 'data-src', src ).attr( 'src', src ).show();
				jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				jQuery( '.ec_details_large_popup_main_' + product_id + '_' + rand_id ).find( '.wp-easycart-video-box' ).remove();
				jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'background', 'url( "' + src + '" ) no-repeat' );
			}
			return false;
		});
	}
	if ( wpeasycart_init_find( ctx, '.ec_details_main_image.mag_enabled' ).length ) {
		var img = new Image( );
		var img_width = 400;
		var img_height = 400;
		wpeasycart_init_elements( ctx, '.ec_details_main_image', 'magMove' ).mousemove( function( e ){
			var parentOffset = jQuery( this ).parent( ).offset( ); 
			var mouse_x = e.pageX - parentOffset.left;
			var mouse_y = e.pageY - parentOffset.top;
			var div_width = jQuery( this ).width( );
			var div_height = jQuery( this ).height( );
			var x_val = '-' + ( ( img_width - div_width ) * ( mouse_x / div_width ) ) + 'px';
			var y_val = '-' + ( ( img_height - div_height ) * ( mouse_y / div_height ) ) + 'px';
			var product_id = jQuery( this ).attr( 'data-product-id' );
			var rand_id = jQuery( this ).attr( 'data-rand-id' );
			jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'background-position', x_val + ' ' + y_val );	
		} );
		wpeasycart_init_elements( ctx, '.ec_details_main_image', 'magHover' ).hover(
			function() {
				var product_id = jQuery( this ).attr( 'data-product-id' );
				var rand_id = jQuery( this ).attr( 'data-rand-id' );
				img = new Image( );
				img.onload = function( ) {
					img_width = this.width;
					img_height = this.height;
					jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).css( 'width', jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).width( ) + 'px' ).css( 'height', jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).height( ) + 'px' );
					jQuery( '.ec_details_magbox_image_' + product_id + '_' + rand_id ).css( 'width', jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).width( ) + 'px' ).css( 'height', jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).height( ) + 'px' );
				}
				img.src = jQuery( this ).find( 'img' ).attr( 'src' );
				jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).fadeIn( 'fast' ); 
			}, 
			function() {
				var product_id = jQuery( this ).attr( 'data-product-id' );
				var rand_id = jQuery( this ).attr( 'data-rand-id' );
				jQuery( '.ec_details_magbox_' + product_id + '_' + rand_id ).fadeOut( 'fast' ); 
			} 
		);
	}
	if ( wpeasycart_init_find( ctx, '.ec_minus' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_minus', 'minus' ).on( 'click', function() {
			var product_id = jQuery( this ).parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().attr( 'data-rand-id' );
			var min_quantity = jQuery( this ).parent().attr( 'data-min-purchase-quantity' );
			var use_advanced_optionset = jQuery( this ).parent().attr( 'data-use-advanced-optionset' );
			ec_minus_quantity( product_id + '_' + rand_id, min_quantity );
			if ( '1' == use_advanced_optionset ) {
				ec_details_advanced_adjust_price( product_id, rand_id );
			} else {
				ec_details_base_adjust_price( product_id, rand_id );
			}
		} );
	}
	if ( wpeasycart_init_find( ctx, '.ec_plus' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_plus', 'plus' ).on( 'click', function() {
			var product_id = jQuery( this ).parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().attr( 'data-rand-id' );
			var show_stock_quantity = jQuery( this ).parent().attr( 'data-show-stock-quantity' );
			/* 6.0.2: the product's maximum purchase quantity ( this passed the minimum ); ec_plus_quantity() still applies the stock limit. */
			var max_purchase = Number( jQuery( document.getElementById( 'max_quantity_' + product_id + '_' + rand_id ) ).val( ) );
			var max_quantity = ( max_purchase > 0 ) ? max_purchase : 10000000;
			var use_advanced_optionset = jQuery( this ).parent().attr( 'data-use-advanced-optionset' );
			ec_plus_quantity( product_id + '_' + rand_id, show_stock_quantity, max_quantity );
			if ( max_purchase > 0 && Number( jQuery( document.getElementById( 'ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > max_purchase ) {
				jQuery( document.getElementById( 'ec_quantity_' + product_id + '_' + rand_id ) ).val( max_purchase );
			}
			if ( '1' == use_advanced_optionset ) {
				ec_details_advanced_adjust_price( product_id, rand_id );
			} else {
				ec_details_base_adjust_price( product_id, rand_id );
			}
		} );
	}
	if ( wpeasycart_init_find( ctx, '.ec_quantity' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_quantity', 'quantity' ).on( 'change', function() {
			var product_id = jQuery( this ).parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().attr( 'data-rand-id' );
			var use_advanced_optionset = jQuery( this ).parent().attr( 'data-use-advanced-optionset' );
			if ( '1' == use_advanced_optionset ) {
				ec_details_advanced_adjust_price( product_id, rand_id );
			} else {
				ec_details_base_adjust_price( product_id, rand_id );
			}
		} );
	}
	if ( wpeasycart_init_find( ctx, '.ec_donation_amount' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_donation_amount', 'donation' ).on( 'change', function() {
			var product_id = jQuery( this ).attr( 'data-product-id' );
			var rand_id = jQuery( this ).attr( 'data-rand-id' );
			var use_advanced_optionset = jQuery( this ).attr( 'data-use-advanced-optionset' );
			if ( '1' == use_advanced_optionset ) {
				ec_details_advanced_adjust_price( product_id, rand_id );
			} else {
				ec_details_base_adjust_price( product_id, rand_id );
			}
		} );
	}
	wpeasycart_init_elements( ctx, '.ec_details_checkbox_row > input, .ec_details_radio_row > input', 'advCheckRadio' ).click( function( ){
		var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_checkbox_row_ele input, .ec_details_radio_row_ele input', 'advCheckRadioEle' ).click( function( ){
		var product_id = jQuery( this ).parent().parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_combo > .ec_details_option_data > select', 'advCombo' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var product_option_id = jQuery( this ).parent().parent().attr( 'data-product-option-id' );
		var is_required = jQuery( this ).parent().parent().attr( 'data-option-required' );
		var val = jQuery( this ).val();
		if ( val == '0' && is_required == '1' ) {
			jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + product_option_id + '_' + product_id + '_' + rand_id ) ).show( );
		} else {
			jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + product_option_id + '_' + product_id + '_' + rand_id ) ).hide( );
		}
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_date > .ec_details_option_data > input', 'advDate' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_file > .ec_details_option_data > input', 'advFile' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_text > .ec_details_option_data > input', 'advText' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_number > .ec_details_option_data > input', 'advNumber' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_textarea > .ec_details_option_data > textarea', 'advTextarea' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_grid_row > input', 'advGrid' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_advanced, .ec_details_swatches_ele > li.ec_advanced', 'advSwatch' ).click( function( ){
		var optionitem_id = jQuery( this ).attr( 'data-optionitem-id' );
		var product_option_id = jQuery( this ).attr( 'data-product-option-id' );
		var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
		jQuery( document.getElementById( 'ec_option_adv_' + product_option_id + "_" + product_id + '_' + rand_id ) ).val( optionitem_id );
		jQuery( '.ec_details_swatches > li.ec_option_adv_' + product_option_id + "_" + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
		jQuery( '.ec_details_swatches_ele > li.ec_option_adv_' + product_option_id + "_" + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
		jQuery( this ).addClass( 'ec_selected' );
		jQuery( document.getElementById( 'ec_details_option_row_error_' + product_option_id + "_" + product_id + '_' + rand_id ) ).hide( );
		if ( jQuery( this ).find( 'img' ).length ) {
			if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
				jQuery( this ).parent( ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
			} else {
				jQuery( this ).parent( ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
			}
		} else {
			jQuery( this ).parent( ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
		}
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_dimensions1 > .ec_details_option_data > input', 'advDim1' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		wpeasycart_details_size_check( jQuery( this ).parent().parent(), product_id, rand_id, false ); /* 6.0.5: a box that is not a positive number says so at once */
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_dimensions2 > .ec_details_option_data > input', 'advDim2' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		wpeasycart_details_size_check( jQuery( this ).parent().parent(), product_id, rand_id, false ); /* 6.0.5: a box that is not a positive number says so at once */
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_option_row.ec_option_type_dimensions2 > .ec_details_option_data > select', 'advDim2Select' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		wpeasycart_details_size_check( jQuery( this ).parent().parent(), product_id, rand_id, false ); /* 6.0.5: a box that is not a positive number says so at once */
		ec_details_advanced_conditional_logic( product_id, rand_id );
		ec_details_advanced_adjust_price( product_id, rand_id );
	} );
	if ( wpeasycart_init_find( ctx, '.ec_details_swatches > li.ec_option1, .ec_details_swatches_ele > li.ec_option1' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option1, .ec_details_swatches_ele > li.ec_option1', 'swatchInit' ).each( function() {
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_1 = jQuery( this ).attr( 'data-optionitem-id' );
			ec_option1_init_swatches( product_id, rand_id, jQuery( this ), optionitem_id_1 );
		} );
	}
	if ( wpeasycart_init_find( ctx, '.ec_details_combo.ec_option1' ).length ) {
		wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option1', 'comboInit' ).each( function() {
			var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
			ec_option1_init_combo( product_id, rand_id );
		} );
	}
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option1, .ec_details_swatches_ele > li.ec_option1', 'option1' ).click( function( ){
		if( jQuery( this ).hasClass( 'ec_active' ) ){
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_1 = jQuery( this ).attr( 'data-optionitem-id' );
			var quantity = jQuery( this ).attr( 'data-optionitem-quantity' );
			var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
			if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
				use_optionitem_quantity_tracking = '0';
			}
			if ( '1' == use_optionitem_quantity_tracking ) {
				ec_option1_selected( product_id, rand_id, optionitem_id_1, quantity );
			} else {
				ec_option1_updated( product_id, rand_id, optionitem_id_1 );
			}
			ec_option1_image_change( product_id, rand_id, optionitem_id_1, quantity );
			jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( '.ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( this ).addClass( 'ec_selected' );
			jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
			jQuery( document.getElementById( 'ec_option1_' + product_id + '_' + rand_id ) ).val( optionitem_id_1 );
			if ( jQuery( this ).find( 'img' ).length ) {
				if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
				} else {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
				}
			} else {
				jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
			}
			ec_details_base_adjust_price( product_id, rand_id );
		}
	} );
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option2, .ec_details_swatches_ele > li.ec_option2', 'option2' ).click( function( ){
		if( jQuery( this ).hasClass( 'ec_active' ) ){
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_1 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_2 = jQuery( this ).attr( 'data-optionitem-id' );
			var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
			if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
				use_optionitem_quantity_tracking = '0';
			}
			if ( '1' == use_optionitem_quantity_tracking ) {
				var quantity = jQuery( this ).attr( 'data-optionitem-quantity' );
				option2_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, quantity );
			} else {
				ec_option2_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2 );
			}
			jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( '.ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( this ).addClass( 'ec_selected' );
			jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
			jQuery( document.getElementById( 'ec_option2_' + product_id + '_' + rand_id ) ).val( optionitem_id_2 );
			if ( jQuery( this ).find( 'img' ).length ) {
				if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
				} else {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
				}
			} else {
				jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
			}
			ec_details_base_adjust_price( product_id, rand_id );
		}
	} );
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option3, .ec_details_swatches_ele > li.ec_option3', 'option3' ).click( function( ){
		if( jQuery( this ).hasClass( 'ec_active' ) ){
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_1 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_2 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_2 = jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_2 = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_3 = jQuery( this ).attr( 'data-optionitem-id' );
			var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
			if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
				use_optionitem_quantity_tracking = '0';
			}
			if ( '1' == use_optionitem_quantity_tracking ) {
				var quantity = jQuery( this ).attr( 'data-optionitem-quantity' );
				option3_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, quantity );
			} else {
				ec_option3_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3 );
			}
			jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( '.ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( this ).addClass( 'ec_selected' );
			jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
			jQuery( document.getElementById( 'ec_option3_' + product_id + '_' + rand_id ) ).val( optionitem_id_3 );
			if ( jQuery( this ).find( 'img' ).length ) {
				if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
				} else {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
				}
			} else {
				jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
			}
			ec_details_base_adjust_price( product_id, rand_id );
		}
	} );
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option4, .ec_details_swatches_ele > li.ec_option4', 'option4' ).click( function( ){
		if( jQuery( this ).hasClass( 'ec_active' ) ){
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_1 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_2 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_2 = jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_2 = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_3 = 0;
			if( jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
				optionitem_id_3 = jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
			}else{
				optionitem_id_3 = jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( );
			}
			var optionitem_id_4 = jQuery( this ).attr( 'data-optionitem-id' );
			var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
			if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
				use_optionitem_quantity_tracking = '0';
			}
			if ( '1' == use_optionitem_quantity_tracking ) {
				var quantity = jQuery( this ).attr( 'data-optionitem-quantity' );
				option4_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, quantity );
			} else {
				ec_option4_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4 );
			}
			jQuery( '.ec_details_swatches > li.ec_option4_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( '.ec_details_swatches_ele > li.ec_option4_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( this ).addClass( 'ec_selected' );
			jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
			jQuery( document.getElementById( 'ec_option4_' + product_id + '_' + rand_id ) ).val( optionitem_id_4 );
			if ( jQuery( this ).find( 'img' ).length ) {
				if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
				} else {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
				}
			} else {
				jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
			}
			ec_details_base_adjust_price( product_id, rand_id );
		}
	} );
	wpeasycart_init_elements( ctx, '.ec_details_swatches > li.ec_option5, .ec_details_swatches_ele > li.ec_option5', 'option5' ).click( function( ){
		if( jQuery( this ).hasClass( 'ec_active' ) ){
			var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
			var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
			var optionitem_id_5 = jQuery( this ).attr( 'data-optionitem-id' );
			jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			jQuery( '.ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' );
			var quantity = jQuery( this ).attr( 'data-optionitem-quantity' );
			jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
			if ( 'inf' != quantity ) {
				jQuery( '.ec_details_stock_total' ).show();
			} else {
				jQuery( '.ec_details_stock_total' ).hide();
			}
			jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).attr( 'max', quantity );
			if( Number( jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > Number( quantity ) ){
				jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( quantity );
			}
			jQuery( this ).addClass( 'ec_selected' );
			jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
			jQuery( document.getElementById( 'ec_option5_' + product_id + '_' + rand_id ) ).val( optionitem_id_5 );
			if ( jQuery( this ).find( 'img' ).length ) {
				if ( '' == jQuery( this ).find( 'img' ).attr( 'title' ) && '' != jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) ) {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'pac_da_title' ) );
				} else {
					jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).find( 'img' ).attr( 'title' ) );
				}
			} else {
				jQuery( this ).parent( ).parent( ).find( '.ec_details_option_label_selected' ).html( jQuery( this ).attr( 'title' ) );
			}
			ec_details_base_adjust_price( product_id, rand_id );
		}
	} );
	wpeasycart_init_elements( ctx, '.ec_details_radio_row.ec_optionitem_images > input', 'optionImageRadio' ).click( function( ){
		var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( this ).val( );
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, 1 );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_radio_row.ec_optionitem_images > label > input', 'optionImageRadioEle' ).click( function( ){
		var product_id = jQuery( this ).attr( 'data-product-id' );
		var rand_id = jQuery( this ).attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( this ).val( );
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, 1 );
	} );
	wpeasycart_init_elements( ctx, '.ec_option_type_combo .ec_optionitem_images', 'optionImageComboClick' ).click( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( this ).val( );
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, 1 );
	} );
	wpeasycart_init_elements( ctx, '.ec_option_type_combo .ec_optionitem_images', 'optionImageComboChange' ).on( 'change', function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( this ).val( );
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, 1 );
	} );
	wpeasycart_init_elements( ctx, '.ec_option_type_swatch li.ec_optionitem_images', 'optionImageSwatch' ).click( function( ){
		var product_id = jQuery( this ).parent().parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().parent().attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( this ).attr( 'data-optionitem-id' );
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, 1 );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option1', 'combo1' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
		var quantity = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id + ' option:selected' ).attr( 'data-optionitem-quantity' );
		var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
		if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
			use_optionitem_quantity_tracking = '0';
		}
		if ( '1' == use_optionitem_quantity_tracking ) {
			if( optionitem_id_1 != '0' ){
				ec_option1_selected( product_id, rand_id, optionitem_id_1, quantity );
			}else{
				jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
				jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
				jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
				jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
				jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
				if ( 'inf' != quantity ) {
					jQuery( '.ec_details_stock_total' ).show();
				} else {
					jQuery( '.ec_details_stock_total' ).hide();
				}
			}
		} else {
			ec_option1_updated( product_id, rand_id, optionitem_id_1 );
		}
		ec_option1_image_change( product_id, rand_id, optionitem_id_1, quantity );
		jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
		ec_details_base_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option2', 'combo2' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
		if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
			use_optionitem_quantity_tracking = '0';
		}
		var optionitem_id_1 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_2 = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( );
		if ( '1' == use_optionitem_quantity_tracking ) {
			var quantity = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id + ' option:selected' ).attr( 'data-optionitem-quantity' );
			if( optionitem_id_2 != '0' ){
				option2_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, quantity );
			}else{
				jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
				if ( 'inf' != quantity ) {
					jQuery( '.ec_details_stock_total' ).show();
				} else {
					jQuery( '.ec_details_stock_total' ).hide();
				}
			}
		} else {
			ec_option2_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2 );
		}
		jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
		ec_details_base_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option3', 'combo3' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
		if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
			use_optionitem_quantity_tracking = '0';
		}
		var optionitem_id_1 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_2 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_2 = jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_2 = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_3 = jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( );
		if ( '1' == use_optionitem_quantity_tracking ) {
			var quantity = jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id + ' option:selected' ).attr( 'data-optionitem-quantity' );
			if( optionitem_id_3 != '0' ){
				option3_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, quantity );
			}else{
				jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
				if ( 'inf' != quantity ) {
					jQuery( '.ec_details_stock_total' ).show();
				} else {
					jQuery( '.ec_details_stock_total' ).hide();
				}
			}
		} else {
			ec_option3_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3 );
		}
		jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
		ec_details_base_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option4', 'combo4' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
		if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
			use_optionitem_quantity_tracking = '0';
		}
		var optionitem_id_1 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_1 = jQuery( '.ec_details_swatches > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_1 = jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_2 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_2 = jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_2 = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_3 = 0;
		if( jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected' ).length ){
			optionitem_id_3 = jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected, .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' );
		}else{
			optionitem_id_3 = jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( );
		}
		var optionitem_id_4 = jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).val( );
		if ( '1' == use_optionitem_quantity_tracking ) {
			var quantity = jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id + ' option:selected' ).attr( 'data-optionitem-quantity' );
			if( optionitem_id_4 != '0' ){
				option4_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, quantity );
			}else{
				jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
				if ( 'inf' != quantity ) {
					jQuery( '.ec_details_stock_total' ).show();
				} else {
					jQuery( '.ec_details_stock_total' ).hide();
				}
			}
		} else {
			ec_option4_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4 );
		}
		jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
		ec_details_base_adjust_price( product_id, rand_id );
	} );
	wpeasycart_init_elements( ctx, '.ec_details_combo.ec_option5', 'combo5' ).change( function( ){
		var product_id = jQuery( this ).parent().parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().parent().attr( 'data-rand-id' );
		var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
		if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
			use_optionitem_quantity_tracking = '0';
		}
		if ( '1' == use_optionitem_quantity_tracking ) {
			var quantity = jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id + ' option:selected' ).attr( 'data-optionitem-quantity' );
			jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
			if ( 'inf' != quantity ) {
				jQuery( '.ec_details_stock_total' ).show();
			} else {
				jQuery( '.ec_details_stock_total' ).hide();
			}
		}
		jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
		ec_details_base_adjust_price( product_id, rand_id );
	} );
	wpeasycart_tabs_init( wpeasycart_init_find( ctx, '.ec_details_tabs' ) );
	wpeasycart_init_elements( ctx, '.ec_details_review_input', 'reviewStar' ).click( function() {
		var product_id = jQuery( this ).parent().attr( 'data-product-id' );
		var rand_id = jQuery( this ).parent().attr( 'data-rand-id' );
		var score = jQuery( this ).attr( 'data-review-score' );
		if ( jQuery( '.ec_details_review_input_' + product_id + '_' + rand_id ).hasClass( 'ec_product_details_star_off_ele' ) || jQuery( '.ec_details_review_input_' + product_id + '_' + rand_id ).hasClass( 'ec_product_details_star_on_ele' ) ) {
			jQuery( '.ec_details_review_input_' + product_id + '_' + rand_id ).removeClass( 'ec_product_details_star_on_ele' ).addClass( 'ec_product_details_star_off_ele' );
		} else {
			jQuery( '.ec_details_review_input_' + product_id + '_' + rand_id ).removeClass( 'ec_product_details_star_on' ).addClass( 'ec_product_details_star_off' );
		}
		for( var i=0; i<score; i++ ){
			if ( jQuery( document.getElementById( 'ec_details_review_star' + (i+1) + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_product_details_star_off_ele' ) || jQuery( document.getElementById( 'ec_details_review_star' + (i+1) + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_product_details_star_on_ele' ) ) {
				jQuery( document.getElementById( 'ec_details_review_star' + (i+1) + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_product_details_star_off_ele' ).addClass( 'ec_product_details_star_on_ele' );
			} else {
				jQuery( document.getElementById( 'ec_details_review_star' + (i+1) + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_product_details_star_off' ).addClass( 'ec_product_details_star_on' );
			}
		}
	} );
	if( wpeasycart_init_find( ctx, '.ec_product_details_page' ).length ) {
		wpeasycart_state_quiet++; /* 6.0.2: the first price pass announces no product state */
		try {
			wpeasycart_init_elements( ctx, '.ec_product_details_page', 'detailsPage' ).each( function() {
				var product_id = jQuery( this ).attr( 'data-product-id' );
				var rand_id = jQuery( this ).attr( 'data-rand-id' );
				ec_details_base_adjust_price( product_id, rand_id );
				ec_details_advanced_adjust_price( product_id, rand_id );
				ec_details_advanced_conditional_logic( product_id, rand_id );
			} );
		} finally {
			wpeasycart_state_quiet--;
		}
		wpeasycart_init_find( ctx, '.ec_product_openclose' ).hide( );
	}
	wpeasycart_init_to_body( ctx, '.ec_details_inquiry_popup', 'inquiryPopup' );
	wpeasycart_init_to_body( ctx, '.ec_details_large_popup', 'largePopup' );
	wpeasycart_init_elements( ctx, '.wp-easycart-orders-pagination', 'ordersPaging' ).each( function() {
        const pagination_container = jQuery( this );
		const order_wrapper = pagination_container.parent();
		if ( order_wrapper.length === 0 ) {
			return;
		}
		const all_order_rows = order_wrapper.find( '.wp-easycart-order-item' );
		const all_page_links = pagination_container.find( '.wp-easycart-orders-pagination-page-num' );
		const page_numbers_container = pagination_container.find( '.wp-easycart-orders-page-numbers' );
		const per_page = parseInt( pagination_container.find('.wp-easycart-orders-pagination-per-page').val(), 10 );
		const total_pages = parseInt( pagination_container.find( '.wp-easycart-orders-pagination-total-pages').val(), 10 );
		let current_page = 1;
		const page_next_to = 2;
		function wp_easycart_pagination_show_page( page ) {
			const start_i = ( page - 1 ) * per_page;
			const end_i = start_i + per_page;
			all_order_rows.hide();
			all_order_rows.slice( start_i, end_i ).css( 'display', 'flex' );
		}
		function wp_easycart_pagination_update_display( page ) {
			all_page_links.removeClass( 'active' );
			all_page_links.filter( '[data-page="' + page + '"]' ).addClass( 'active' );
			pagination_container.find( '.wp-easycart-orders-pagination-first-page, .wp-easycart-orders-pagination-prev-page' ).toggleClass( 'disabled', page === 1 );
			pagination_container.find( '.wp-easycart-orders-pagination-next-page, .wp-easycart-orders-pagination-last-page' ).toggleClass( 'disabled', page === total_pages );
			all_page_links.hide();
			page_numbers_container.find( '.wp-easycart-orders-pagination-ellipsis' ).remove();
			const ellipsis = jQuery( '<span class="wp-easycart-orders-pagination-ellipsis">...</span>' );
			let pagesToShow = [];
			pagesToShow.push( all_page_links.eq(0) );
			let start = Math.max( 1, page - page_next_to );
			let end = Math.min( total_pages - 2, page + page_next_to );
			if ( page - page_next_to <= 1 ) {
				end = Math.min(total_pages - 2, 1 + (page_next_to * 2));
			}
			if (page + page_next_to >= total_pages - 2) {
				start = Math.max(1, total_pages - 2 - (page_next_to * 2));
			}
			if ( start > 1 ) {
				pagesToShow.push( ellipsis.clone() );
			}
			for ( let i = start; i <= end; i++ ) {
				pagesToShow.push( all_page_links.eq(i) );
			}
			if ( end < total_pages - 2 ) {
				pagesToShow.push( ellipsis.clone() );
			}
			if ( total_pages > 1 ) {
				pagesToShow.push( all_page_links.eq( total_pages - 1 ) );
			}
			jQuery.each( pagesToShow, function() {
				if ( jQuery( this ).is( 'a' ) ) {
					jQuery( this ).show();
				} else {
					page_numbers_container.append( this );
				}
			} );
			if ( total_pages > 1 ) {
				all_page_links.eq( total_pages - 1 ).show();
			}
		}
		pagination_container.on( 'click', 'a[data-page]', function(e) {
			e.preventDefault();
			const target = jQuery( this );
			if ( target.hasClass( 'disabled' ) || target.hasClass( 'active' ) ) {
				return;
			}
			const page_control = target.data( 'page' );
			let new_page = current_page;
			switch ( page_control ) {
				case 'first': new_page = 1; break;
				case 'last': new_page = total_pages; break;
				case 'prev': new_page = Math.max( 1, current_page - 1 ); break;
				case 'next': new_page = Math.min( total_pages, current_page + 1 ); break;
				default: new_page = parseInt( page_control, 10 );
			}
			if ( new_page !== current_page ) {
				current_page = new_page;
				wp_easycart_pagination_show_page( current_page );
				wp_easycart_pagination_update_display( current_page );
			}
		} );
		if ( all_order_rows.length > 0 && total_pages > 0 ) {
			wp_easycart_pagination_show_page( 1 );
			wp_easycart_pagination_update_display( 1 );
		} else {
			pagination_container.hide();
		}
	} );
}
jQuery( document ).ready( function( ){
	wp_easycart_init_location_buttons();
	if ( jQuery( '#wpeasycart_cart_holder' ).length ) {
		wpeasycart_load_cart( jQuery( '#wpeasycart_cart_holder' ).data( 'cart-page' ), jQuery( '#wpeasycart_cart_holder' ).data( 'success-code' ), jQuery( '#wpeasycart_cart_holder' ).data( 'error-code' ), jQuery( '#wpeasycart_cart_holder' ).data( 'language' ), jQuery( '#wpeasycart_cart_holder' ).data( 'nonce' ) )
	}
	if ( jQuery( '#wpeasycart_account_holder' ).length ) {
		wpeasycart_load_account( jQuery( '#wpeasycart_account_holder' ).data( 'account-page' ), jQuery( '#wpeasycart_account_holder' ).data( 'page-id' ), jQuery( '#wpeasycart_account_holder' ).data( 'success-code' ), jQuery( '#wpeasycart_account_holder' ).data( 'error-code' ), jQuery( '#wpeasycart_account_holder' ).data( 'language' ), jQuery( '#wpeasycart_account_holder' ).data( 'nonce' ), jQuery( '#wpeasycart_account_holder' ).data( 'form-only' ) )
	}
	/* 6.0.2: galleries, options, swatches, sliders, quick view, forms ( see wpeasycart_init() ). An error in one product's
	 * setup no longer stops the page's other wiring below ( header cart, country lists, tabs, checkout ). */
	try {
		wpeasycart_init( document );
	} catch ( wpec_init_error ) {
		if ( window.console && window.console.error ) {
			window.console.error( wpec_init_error );
		}
	}
	wpeasycart_cart_billing_country_update( );
	wpeasycart_cart_shipping_country_update( );
	wpeasycart_account_billing_country_update( );
	wpeasycart_account_shipping_country_update( );
	jQuery( document.getElementById( 'ec_cart_billing_country' ) ).change( function( ){ wpeasycart_cart_billing_country_update( ); } );
	jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).change( function( ){ wpeasycart_cart_shipping_country_update( ); } );
	jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).change( function( ){ wpeasycart_account_billing_country_update( ); } );
	jQuery( document.getElementById( 'ec_account_shipping_information_country' ) ).change( function( ){ wpeasycart_account_shipping_country_update( ); } );
	if( jQuery( '.ec_menu_mini_cart' ).length ){
		jQuery( document.getElementById( 'ec_card_number' ) ).keydown( function( ){
			ec_show_cc_type( ec_get_card_type( jQuery( document.getElementById( 'ec_card_number' ) ).val( ) ) )
		} );
		// Load cart menu, updates over possible cached value
		var data = {
			action: 'ec_ajax_get_dynamic_cart_menu',
			language: wpeasycart_ajax_object.current_language,
			nonce: jQuery( '.ec_menu_mini_cart' ).attr( 'data-nonce' )
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				jQuery( '.ec_menu_mini_cart' ).html( data );
			}
		} );
	}
	/* 6.0.2: the Cart Icon and the Elementor cart widgets ask for the visitor's cart once the page is ready, without a nonce,
	 * so a page served from a cache shows the real count ( the Cart Icon's nonce'd count request failed a day later, showing
	 * 0 ). The menu cart and the sidebar Cart widget keep their own requests and updates. */
	if ( wpeasycart_cart_widgets_present( ) ) {
		wpeasycart_refresh_cart_widgets_soon( );
	}
    if( jQuery( document.getElementById( 'ec_card_number' ) ).length ){
	   jQuery( document.getElementById( 'ec_card_number' ) ).payment( 'formatCardNumber' );
	}
    if( jQuery( document.getElementById( 'ec_cc_expiration' ) ).length ){
	   jQuery( document.getElementById( 'ec_cc_expiration' ) ).payment( 'formatCardExpiry' );
    }
    if( jQuery( document.getElementById( 'ec_security_code' ) ).length ){
	   jQuery( document.getElementById( 'ec_security_code' ) ).payment( 'formatCardCVC' );
    }
    
	
	if( jQuery( '.ec_product_sidebar_filter_item' ).length ){
		jQuery( '.ec_product_sidebar_filter_item' ).on( 'click', function( ){
			if( jQuery( this ).hasClass( 'selected' ) ){
				jQuery( this ).removeClass( 'selected' );
			}else{
				jQuery( this ).addClass( 'selected' );
			}
			var filter_ids = [];
			jQuery( '.ec_product_sidebar_filter_item.selected' ).each( function( ){
				filter_ids.push( jQuery( this ).attr( 'data-optionitemid' ) );
			} );
			var category_ids = [];
			jQuery( '.ec_product_sidebar_filter_item.selected' ).each( function( ){
				filter_ids.push( jQuery( this ).attr( 'data-categoryid' ) );
			} );
			jQuery( this ).parent( ).parent( ).parent( ).parent( ).find( '.ec_product_page_content' ).html( '</div><style>@keyframes rotation{0%{transform:rotate(0deg);}100%{ transform:rotate(359deg); } }</style><div class="wpec-product-slider-loader" style="font-size:14px; text-align:center; -webkit-box-sizing:border-box; -moz-box-sizing:border-box; -ms-box-sizing:border-box; box-sizing:border-box; width:350px; top:200px; left:50%; position:absolute; margin-left:-165px; margin-top:-80px; cursor:pointer; text-align:center; z-index:99;"><div><div style="height:30px; width:30px; display:inline-block; box-sizing:content-box; opacity:1; filter:alpha(opacity=100); -webkit-animation:rotation .7s infinite linear; -moz-animation:rotation .7s infinite linear;-o-animation: rotation .7s infinite linear; animation:rotation .7s infinite linear; border-left:8px solid rgba(0, 0, 0, .2); border-right:8px solid rgba(0, 0, 0, .2); border-bottom:8px solid rgba(0, 0, 0, .2); border-top:8px solid #fff; border-radius:100%;"></div></div></div>' );
			var data = {
				action: 'ec_ajax_update_product_page_content',
				filter_ids: filter_ids,
				category_ids: category_ids,
				nonce: jQuery( '.ec_product_sidebar_filter_item' ).attr( 'data-nonce' )
			};
		} );
	}
	if( jQuery( '.ec_product_page_with_sidebar.ec_product_page_sidebar_left, .ec_product_page_with_sidebar.ec_product_page_sidebar_right' ).length ){
		jQuery( '.ec_product_page_sidebar_left > .ec_product_page_sidebar, .ec_product_page_sidebar_right > .ec_product_page_sidebar' ).clone().appendTo( document.body ).addClass( 'ec_product_page_sidebar_mobile_only' ).addClass( 'ec_product_page_sidebar_slide-left' ).find( '.ec_product_sidebar_filter_item' ).removeClass( 'ec_product_sidebar_filter_item' ).addClass( 'ec_product_sidebar_filter_item_mobile' );
		jQuery( '.ec_product_sidebar_filter_item_mobile' ).on( 'click', function() {
			if ( jQuery( this ).hasClass( 'selected' ) ) {
				jQuery( this ).removeClass( 'selected' );
			} else {
				jQuery( this ).addClass( 'selected' );
			}
			jQuery( '.ec_product_page_content' ).html( '<div style="min-height:400px; width:100%; float:left;"><style>@keyframes rotation{0%{transform:rotate(0deg);}100%{ transform:rotate(359deg); } }</style><div class="wpec-product-slider-loader" style="font-size:14px; text-align:center; -webkit-box-sizing:border-box; -moz-box-sizing:border-box; -ms-box-sizing:border-box; box-sizing:border-box; width:350px; top:200px; left:50%; position:absolute; margin-left:-165px; margin-top:-80px; cursor:pointer; text-align:center; z-index:99;"><div><div style="height:30px; width:30px; display:inline-block; box-sizing:content-box; opacity:1; filter:alpha(opacity=100); -webkit-animation:rotation .7s infinite linear; -moz-animation:rotation .7s infinite linear;-o-animation: rotation .7s infinite linear; animation:rotation .7s infinite linear; border-left:8px solid rgba(0, 0, 0, .2); border-right:8px solid rgba(0, 0, 0, .2); border-bottom:8px solid rgba(0, 0, 0, .2); border-top:8px solid #fff; border-radius:100%;"></div></div></div></div>' );
			jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left', '-500px' );
			return true;
		} );
		jQuery( '.ec_product_page_sidebar_slide_bg' ).appendTo( document.body );
		jQuery( '.ec_product_page_filters_toggle' ).on( 'click', function( ){
			if( jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left' ) == '0px' ){
				jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left', '-500px' ).fadeOut();
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			}else{
				jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left', '0px' ).fadeIn( 'fast' );
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeIn( 'fast' );
			}
		} );
		jQuery( '.ec_product_page_sidebar_slide_bg, .ec_product_sidebar_close, .ec_product_sidebar_close_mobile_only' ).on( 'click', function( ){
			jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left', '-500px' );
			return false;
		} );

	} else if( jQuery( '.ec_product_page_with_sidebar.ec_product_page_sidebar_slide-left, .ec_product_page_sidebar_slide-right > .ec_product_page_sidebar' ).length ){
		jQuery( '.ec_product_page_sidebar_slide-left > .ec_product_page_sidebar, .ec_product_page_sidebar_slide-right > .ec_product_page_sidebar' ).appendTo( document.body ).show( );
		jQuery( '.ec_product_page_sidebar_slide_bg' ).appendTo( document.body );
		jQuery( '.ec_product_page_sidebar_slide-left .ec_product_page_filters_toggle' ).on( 'click', function( ){
			if( jQuery( '.ec_product_page_sidebar' ).css( 'left' ) == '0px' ){
				jQuery( '.ec_product_page_sidebar' ).css( 'left', '-500px' );
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			}else{
				jQuery( '.ec_product_page_sidebar' ).css( 'left', '0px' );
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeIn( 'fast' );
			}
		} );
		jQuery( '.ec_product_page_sidebar_slide-right .ec_product_page_filters_toggle' ).on( 'click', function( ){
			if( jQuery( '.ec_product_page_sidebar' ).css( 'right' ) == '0px' ){
				jQuery( '.ec_product_page_sidebar' ).css( 'right', '-500px' );
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			}else{
				jQuery( '.ec_product_page_sidebar' ).css( 'right', '0px' );
				jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeIn( 'fast' );
			}
		} );
		jQuery( '.ec_product_page_sidebar_slide_bg, .ec_product_sidebar_close, .ec_product_sidebar_close_mobile_only' ).on( 'click', function() {
			jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			jQuery( '.ec_product_page_sidebar_slide-left' ).css( 'left', '-500px' );
			jQuery( '.ec_product_page_sidebar_slide-right' ).css( 'right', '-500px' );
			return false;
		} );
		jQuery( '.ec_product_page_sidebar.ec_product_page_sidebar_slide-left .ec_product_sidebar_filter_item, .ec_product_page_sidebar.ec_product_page_sidebar_slide-right .ec_product_sidebar_filter_item, .ec_product_page_sidebar.ec_product_page_sidebar_slide-left .ec_product_sidebar_link_item, .ec_product_page_sidebar.ec_product_page_sidebar_slide-right .ec_product_sidebar_link_item' ).on( 'click', function( ){
			jQuery( '.ec_product_page_content' ).html( '<div style="min-height:400px; width:100%; float:left;"><style>@keyframes rotation{0%{transform:rotate(0deg);}100%{ transform:rotate(359deg); } }</style><div class="wpec-product-slider-loader" style="font-size:14px; text-align:center; -webkit-box-sizing:border-box; -moz-box-sizing:border-box; -ms-box-sizing:border-box; box-sizing:border-box; width:350px; top:200px; left:50%; position:absolute; margin-left:-165px; margin-top:-80px; cursor:pointer; text-align:center; z-index:99;"><div><div style="height:30px; width:30px; display:inline-block; box-sizing:content-box; opacity:1; filter:alpha(opacity=100); -webkit-animation:rotation .7s infinite linear; -moz-animation:rotation .7s infinite linear;-o-animation: rotation .7s infinite linear; animation:rotation .7s infinite linear; border-left:8px solid rgba(0, 0, 0, .2); border-right:8px solid rgba(0, 0, 0, .2); border-bottom:8px solid rgba(0, 0, 0, .2); border-top:8px solid #fff; border-radius:100%;"></div></div></div></div>' );
			jQuery( '.ec_product_page_sidebar_slide_bg' ).fadeOut( 'fast' );
			jQuery( '.ec_product_page_sidebar_mobile_only' ).css( 'left', '-500px' );
		} );
	}
	if ( jQuery( '.ec_product_sidebar_group_title' ).length ) {
		jQuery( '.ec_product_sidebar_group_title' ).on( 'click', function() {
			jQuery( this ).parent( ).find( '.ec_product_sidebar_group_link_list, .ec_product_sidebar_group_filter_list' ).toggle( );
		} );
	}
	
	/* Product tabs ( 6.0.2 ): delegated, so tab lists drawn after the page loads ( Elementor's editor ) work too, with the
	   keyboard ( arrow keys, Home, End, Enter, Space ) and the ARIA tabs pattern. See wpeasycart_tabs_init(). */
	jQuery( document ).on( 'click', '.ec_details_tabs .ec_details_tab', function( ){
		wpeasycart_tabs_init( jQuery( this ).parent( ) );
		wpeasycart_tabs_select( this, true );
	} );
	jQuery( document ).on( 'keydown', '.ec_details_tabs .ec_details_tab', function( e ){
		var li = jQuery( this ), list = li.parent( ), tabs, index, next = null, key = e.key || e.keyCode;
		wpeasycart_tabs_init( list );
		tabs = list.children( '.ec_details_tab' ).filter( ':visible' );
		index = tabs.index( li );
		if ( 'ArrowRight' === key || 'Right' === key || 39 === key || 'ArrowDown' === key || 'Down' === key || 40 === key ) {
			next = tabs.eq( ( index + 1 ) % tabs.length );
		} else if ( 'ArrowLeft' === key || 'Left' === key || 37 === key || 'ArrowUp' === key || 'Up' === key || 38 === key ) {
			next = tabs.eq( ( index - 1 + tabs.length ) % tabs.length );
		} else if ( 'Home' === key || 36 === key ) {
			next = tabs.first( );
		} else if ( 'End' === key || 35 === key ) {
			next = tabs.last( );
		} else if ( 'Enter' === key || ' ' === key || 'Spacebar' === key || 13 === key || 32 === key ) {
			e.preventDefault( );
			wpeasycart_tabs_select( li, true );
			return;
		}
		if ( next && next.length ) {
			e.preventDefault( );
			wpeasycart_tabs_select( next, false );
			next.trigger( 'focus' );
		}
	} );
	/* 6.0.2: #reviews ( the Product Rating widget's link ) opens the product's Customer Reviews tab. See wpeasycart_reviews_from_hash(). */
	wpeasycart_reviews_from_hash( );
	jQuery( window ).on( 'hashchange', function( ){
		wpeasycart_reviews_from_hash( );
	} );
	if ( jQuery( document.getElementById('ec_cart_login_loader' ) ).length ) {
		jQuery( document.getElementById( 'ec_cart_login_loader' ) ).appendTo( document.body );
	}
	if ( jQuery( document.getElementById('ec_cart_create_account_loader' ) ).length ) {
		jQuery( document.getElementById( 'ec_cart_create_account_loader' ) ).appendTo( document.body );
	}
	if ( jQuery( document.getElementById('ec_cart_address_loader' ) ).length ) {
		jQuery( document.getElementById( 'ec_cart_address_loader' ) ).appendTo( document.body );
	}
	if ( jQuery( document.getElementById('ec_cart_subscription_shipping_methods_loader' ) ).length ) {
		jQuery( document.getElementById( 'ec_cart_subscription_shipping_methods_loader' ) ).appendTo( document.body );
	}
	var shippingAddressTimer;
	var billingAddressTimer;
	var contactEmailTimer;
	jQuery( document ).on( 'change keyup', 'input.ec_cart_auto_validate_v2', function() {
		if ( jQuery( this ).hasClass( 'ec_cart_shipping_input_text' ) ) {
			if ( ec_validate_address_block( 'ec_cart_shipping' ) ) {
				if ( jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).length ) {
					clearTimeout( shippingAddressTimer );
					shippingAddressTimer = setTimeout( function() {
						wp_easycart_goto_shipping_v2();
						if ( jQuery( document.getElementById( 'ec_shipping_order_error' ) ).length ) {
							jQuery( document.getElementById( 'ec_shipping_order_error' ) ).hide();
						}
					}, 800 );
				}
			}
		} else if ( jQuery( this ).hasClass( 'ec_cart_contact_information_input_text' ) ) {
			if ( ec_validate_email_block( 'ec_contact' ) ) {
				clearTimeout( contactEmailTimer );
				contactEmailTimer = setTimeout( function() {
					jQuery( document.getElementById( 'ec_email_order2_error' ) ).hide();
					wp_easycart_update_contact_email_v2();
				}, 800 );
			}
			if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length && ec_validate_create_account( 'ec_contact' ) ) {
				jQuery( document.getElementById( 'ec_create_account_order_error' ) ).hide();
			}
		} else {
			if ( ec_validate_address_block( 'ec_cart_billing' ) ) {
				clearTimeout( billingAddressTimer );
				billingAddressTimer = setTimeout( function() {
					var shipping_selector = ( ! jQuery( '#billing_address_type_different' ).length || ( jQuery( '#billing_address_type_different' ).length && jQuery( '#billing_address_type_different' ).is( ':checked' ) ) ) ? '1' : '0';
					ec_update_billing_address_display( shipping_selector, jQuery( document.getElementById( 'wp_easycart_update_billing_nonce' ) ).val() );
					jQuery( document.getElementById( 'ec_billing_order_error' ) ).hide();
				}, 800 );
			}
		}
	} );
	jQuery( document ).on( 'change', 'select.ec_cart_auto_validate_v2', function() {
		if ( jQuery( this ).hasClass( 'ec_cart_shipping_input_text' ) ) {
			if ( ec_validate_address_block( 'ec_cart_shipping' ) ) {
				if ( jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).length ) {
					clearTimeout( shippingAddressTimer );
					shippingAddressTimer = setTimeout( function() {
						wp_easycart_goto_shipping_v2();
						if ( jQuery( document.getElementById( 'ec_shipping_order_error' ) ).length ) {
							jQuery( document.getElementById( 'ec_shipping_order_error' ) ).hide();
						}
					}, 800 );
				}
			}
		} else {
			if ( ec_validate_address_block( 'ec_cart_billing' ) ) {
				clearTimeout( billingAddressTimer );
				billingAddressTimer = setTimeout( function() {
					var shipping_selector = ( ! jQuery( '#billing_address_type_different' ).length || ( jQuery( '#billing_address_type_different' ).length && jQuery( '#billing_address_type_different' ).is( ':checked' ) ) ) ? '1' : '0';
					ec_update_billing_address_display( shipping_selector, jQuery( document.getElementById( 'wp_easycart_update_billing_nonce' ) ).val() );
					jQuery( document.getElementById( 'ec_billing_order_error' ) ).hide();
				}, 800 );
			}
		}
	} );
	jQuery( document ).on( 'change', '#preorder_pickup_date', function() {
		ec_cart_save_pickup_date_time();
	} );
	jQuery( document ).on( 'change', '#preorder_pickup_time', function() {
		ec_cart_save_pickup_date_time();
	} );
	jQuery( document ).on( 'click', '#restaurant_pickup_time_asap', function() {
		ec_cart_save_pickup_date_time();
	} );
	jQuery( document ).on( 'click', '#restaurant_pickup_time_schedule', function() {
		ec_cart_save_pickup_date_time();
	} );
	jQuery( document ).on( 'change', '#restaurant_pickup_time', function() {
		ec_cart_save_pickup_date_time();
	} );
} );
/* 6.0.2: every WP EasyCart widget Elementor draws ( the editor's preview, popups, Loop Grid "load more" ) is set up with
 * wpeasycart_init() too; its cart badges ask for the cart. On a normal page load the widgets are already set up, so this
 * finds nothing left to bind. */
function wpeasycart_elementor_hooks( ) {
	if ( window.wpeasycart_elementor_hooked || ! window.elementorFrontend || ! window.elementorFrontend.hooks || ! window.elementorFrontend.hooks.addAction ) {
		return;
	}
	window.wpeasycart_elementor_hooked = true;
	window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function( $scope ) {
		var type = ( $scope && $scope.attr ) ? String( $scope.attr( 'data-widget_type' ) || '' ) : '';
		if ( 0 !== type.indexOf( 'wp_easycart_' ) ) {
			return;
		}
		try {
			wpeasycart_init( $scope );
		} catch ( err ) {
			if ( window.console && window.console.error ) {
				window.console.error( err );
			}
		}
		if ( wpeasycart_cart_widgets_present( $scope ) ) {
			wpeasycart_refresh_cart_widgets_soon( );
		}
	} );
}
jQuery( window ).on( 'elementor/frontend/init', wpeasycart_elementor_hooks );
wpeasycart_elementor_hooks( );
function ec_cart_restaurant_start_timer() {
	var restaurant_timer_minutes = parseInt( jQuery( '.ec_cart_restaurant_timer_min' ).html() );
	var restaurant_timer_seconds = parseInt( jQuery( '.ec_cart_restaurant_timer_sec' ).html() );
	var restaurant_total_seconds = restaurant_timer_minutes * 60 + restaurant_timer_seconds;
	var restaurant_timer = setInterval( function() {
		restaurant_total_seconds--;
		var minutesLeft = Math.floor( restaurant_total_seconds / 60 );
		var secondsLeft = restaurant_total_seconds % 60;
		jQuery( '.ec_cart_restaurant_timer_min' ).html( minutesLeft.toString().padStart( 2, '0' ) );
		jQuery( '.ec_cart_restaurant_timer_sec' ).html( secondsLeft.toString().padStart(2, '0') );
		if ( restaurant_total_seconds <= 0 ) {
			clearInterval( restaurant_timer );
			window.location.reload();
		}
	}, 1000 );
}
function ec_cart_save_pickup_date_time() {
	var pickup_date = '';
	var pickup_date_time = '';
	var pickup_asap = 0;
	var pickup_time = '';
	if ( jQuery( '#preorder_pickup_date' ).length ) {
		pickup_date = jQuery( '#preorder_pickup_date' ).datepicker( 'getDate' );
		pickup_date_time = jQuery( '#preorder_pickup_time' ).val();
		if ( pickup_date && '' != pickup_date_time ) {
			jQuery( '#ec_preorder_pickup_error' ).hide();
		}
	}
	if ( jQuery( '#restaurant_pickup_time_asap' ).length && jQuery( '#restaurant_pickup_time_asap' ).is( ':checked' ) ) {
		pickup_asap = 1;
	}
	if ( jQuery( '#restaurant_pickup_time' ).length ) {
		pickup_time = jQuery( '#restaurant_pickup_time' ).val();
	}
	var data = {
		action: 'ec_ajax_save_pickup_info',
		pickup_date: pickup_date,
		pickup_date_time: pickup_date_time,
		pickup_asap: pickup_asap,
		pickup_time: pickup_time,
		nonce: jQuery( '#ec_cart_form_nonce' ).val()
	};
	jQuery.ajax( { 
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}
var wpeasycart_login_recaptcha;
var wpeasycart_register_recaptcha;
var wpeasycart_product_stock_recaptcha;
var wpeasycart_inquiry_recaptcha;
var wpeasycart_recaptcha_onload = function ( ){
	if( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ){
		var wpeasycart_login_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_login_recaptcha_callback
		});
	}
	if( jQuery( document.getElementById( 'ec_account_login_widget_recaptcha' ) ).length ){
		var wpeasycart_login_widget_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_widget_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_login_widget_recaptcha_callback
		});
	}
	if( jQuery( document.getElementById( 'ec_account_register_recaptcha' ) ).length ){
		var wpeasycart_register_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_register_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_register_recaptcha_callback
		});
	}
	if( jQuery( document.getElementById( 'ec_product_details_recaptcha' ) ).length ){
		var wpeasycart_register_recaptcha = grecaptcha.render( document.getElementById( 'ec_product_details_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_product_details_recaptcha_callback
		});
	}
	if( jQuery( document.getElementById( 'ec_product_details_inquiry_recaptcha' ) ).length ){
		var wpeasycart_inquiry_recaptcha = grecaptcha.render( document.getElementById( 'ec_product_details_inquiry_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_inquiry_recaptcha_callback
		});
	}
}
function ec_customer_review_paging( page, product_id ){
    var review_ele = jQuery( '.ec_details_customer_review_list[data-product-id="' + product_id + '"]' );
	var reviews = review_ele.find( 'li' );
	var reviews_per_page = jQuery( document.getElementById( 'ec_details_reviews_per_page_' + product_id ) ).val( );
	
	if( reviews.length > reviews_per_page ){
		var i=0;
		reviews.each( function( ){
			if( i < (reviews_per_page * page ) && i >= (reviews_per_page * (page-1) ) ){
				jQuery( this ).show( );
			}else{
				jQuery( this ).hide( );
			}
			i++;
		} );
		i=0;
		review_ele.find( '#ec_details_customer_review_paging_' + product_id + ' > button' ).removeClass( 'selected' ).each( function( ){
			if( ( i+1 ) == page ){
				jQuery( this ).addClass( 'selected' );
			}
			i++;
		} );
	}
}
function wpeasycart_login_recaptcha_callback( response ){
	jQuery( document.getElementById( 'ec_grecaptcha_response_login' ) ).val( response );
	if( response.length ){
		jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', 'none' );
	}else{
		jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', '1px solid red' );
	}
}
function wpeasycart_login_widget_recaptcha_callback( response ){
	jQuery( document.getElementById( 'ec_grecaptcha_response_login_widget' ) ).val( response );
	if( response.length ){
		jQuery( '#ec_account_login_widget_recaptcha > div' ).css( 'border', 'none' );
	}else{
		jQuery( '#ec_account_login_widget_recaptcha > div' ).css( 'border', '1px solid red' );
	}
}
function wpeasycart_register_recaptcha_callback( response ){
	jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val( response );
	if( response.length ){
		jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', 'none' );
	}else{
		jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', '1px solid red' );
	}
}
function wpeasycart_product_details_recaptcha_callback( response ){
	jQuery( document.getElementById( 'ec_grecaptcha_response_product_details' ) ).val( response );
	if( response.length ){
		jQuery( '#ec_product_details_recaptcha > div' ).css( 'border', 'none' );
	}else{
		jQuery( '#ec_product_details_recaptcha > div' ).css( 'border', '1px solid red' );
	}
}
function wpeasycart_inquiry_recaptcha_callback( response ){
	jQuery( document.getElementById( 'ec_grecaptcha_response_inquiry' ) ).val( response );
	if( response.length ){
		jQuery( '#ec_product_details_inquiry_recaptcha > div' ).css( 'border', 'none' );
	}else{
		jQuery( '#ec_product_details_inquiry_recaptcha > div' ).css( 'border', '1px solid red' );
	}
}
/* 6.0.5: countries whose region ( state, county, province ) may be left empty ( Settings › Countries & Regions; the UK, Germany
 * and New Zealand unless the store says otherwise: wpeasycart_ajax_object.address.optional, wp_easycart_regions ). */
function wpeasycart_region_optional( country ) {
	var list = ( 'undefined' !== typeof wpeasycart_ajax_object && wpeasycart_ajax_object.address && wpeasycart_ajax_object.address.optional ) ? wpeasycart_ajax_object.address.optional : [ 'GB', 'DE', 'NZ' ];
	return -1 !== jQuery.inArray( String( country || '' ).toUpperCase(), list );
}
/* 6.0.5: the region and postcode labels say what the country calls them ( County and Postcode in the UK, Province in Canada …:
 * wpeasycart_ajax_object.address.labels ); any other country gets the store's own words back, kept from the page as printed. */
function wpeasycart_address_labels( prefix, country ) {
	var cfg = ( 'undefined' !== typeof wpeasycart_ajax_object && wpeasycart_ajax_object.address && wpeasycart_ajax_object.address.labels ) ? wpeasycart_ajax_object.address.labels : null;
	if ( ! cfg || jQuery.isArray( cfg ) ) {
		return;
	}
	var words = cfg[ String( country || '' ).toUpperCase() ] || null;
	jQuery.each( [ [ 'state', 'region' ], [ 'zip', 'postal' ] ], function( i, pair ) {
		var label = jQuery( 'label[for="' + prefix + '_' + pair[0] + '"]' ).first(), node = null;
		if ( ! label.length ) {
			return;
		}
		label.contents().each( function() {
			if ( 3 === this.nodeType && jQuery.trim( this.nodeValue ).length ) {
				node = this;
				return false;
			}
		} );
		if ( ! node ) {
			return;
		}
		if ( undefined === label.data( 'wpecLabel' ) ) {
			label.data( 'wpecLabel', node.nodeValue );
		}
		var original = String( label.data( 'wpecLabel' ) );
		var word = ( words && words[ pair[1] ] ) ? words[ pair[1] ] + ( /\*\s*$/.test( original ) ? '*' : '' ) : original;
		if ( node.nodeValue !== word ) {
			node.nodeValue = word;
		}
	} );
}
/* 6.0.5: countries without postcodes ( wpeasycart_ajax_object.address.nozip, wp_easycart_address::no_postcode() ). */
function wpeasycart_postcode_none( country ) {
	var list = ( 'undefined' !== typeof wpeasycart_ajax_object && wpeasycart_ajax_object.address && wpeasycart_ajax_object.address.nozip ) ? wpeasycart_ajax_object.address.nozip : [];
	return -1 !== jQuery.inArray( String( country || '' ).toUpperCase(), list );
}
/* 6.0.5: the town, region and postcode fields in the country's order ( wp_easycart_address::form_layout(): csz the town, then the
 * region and postcode; zc the postcode before the town; cz the town and postcode; stack each under the other; cs no postcode ). The
 * first time, the three fields' own blocks ( a half or a row, found from their labels ) move into one grid, .wpec-address-place, which
 * is also an .ec_cart_input_row so every field keeps its look; ids, names and handlers stay. A form whose three fields do not sit in
 * blocks of their own is left as printed. A country without postcodes hides the postcode and keeps what was typed for a later one. */
function wpeasycart_address_place( prefix, country ) {
	var cfg = ( 'undefined' !== typeof wpeasycart_ajax_object && wpeasycart_ajax_object.address ) ? wpeasycart_ajax_object.address : {};
	var layouts = ( cfg.layouts && ! jQuery.isArray( cfg.layouts ) ) ? cfg.layouts : {};
	var code = String( country || '' ).toUpperCase();
	var grid = jQuery( '.wpec-address-place' ).filter( function() { return jQuery( this ).attr( 'data-prefix' ) === prefix; } );
	if ( ! grid.length ) {
		var cells = {}, found = true;
		jQuery.each( [ 'city', 'state', 'zip' ], function( i, part ) {
			var label = jQuery( 'label[for="' + prefix + '_' + part + '"]' ).first();
			var cell = label.closest( '.ec_cart_input_left_half, .ec_cart_input_right_half, .ec_cart_input_row' );
			if ( ! label.length || ! cell.length ) {
				found = false;
				return false;
			}
			cells[ part ] = cell;
		} );
		if ( ! found ) {
			return;
		}
		var list = [ cells.city[0], cells.state[0], cells.zip[0] ];
		for ( var a = 0; a < list.length; a++ ) {
			for ( var b = 0; b < list.length; b++ ) {
				if ( a !== b && ( list[ a ] === list[ b ] || jQuery.contains( list[ a ], list[ b ] ) ) ) {
					return;
				}
			}
		}
		grid = jQuery( '<div class="ec_cart_input_row wpec-address-place"></div>' ).attr( 'data-prefix', prefix );
		grid.insertBefore( cells.city );
		jQuery.each( [ 'city', 'state', 'zip' ], function( i, part ) {
			var cell = cells[ part ], parent = cell.parent();
			cell.addClass( 'wpec-address-cell wpec-address-' + part ).appendTo( grid );
			if ( ! parent.is( grid ) && parent.is( '.ec_cart_input_row' ) && ! parent.children().length && ! jQuery.trim( parent.text() ).length ) {
				parent.remove();
			}
		} );
	}
	grid.attr( 'data-layout', layouts[ code ] || 'csz' );
	var none = wpeasycart_postcode_none( code ), zip = jQuery( document.getElementById( prefix + '_zip' ) );
	grid.toggleClass( 'wpec-address-nozip', none );
	if ( none ) {
		if ( zip.length && '' !== zip.val() ) {
			zip.data( 'wpecZipKept', zip.val() ).val( '' );
		}
		if ( 'function' === typeof ec_hide_error ) {
			ec_hide_error( prefix + '_zip' );
		}
	} else if ( zip.length && '' === zip.val() && zip.data( 'wpecZipKept' ) ) {
		zip.val( zip.data( 'wpecZipKept' ) );
	}
}
/* 6.0.5: every address form on the page follows its country ( after a part of the checkout or the account page is drawn again ). */
function wpeasycart_address_fields() {
	wpeasycart_cart_billing_country_update( );
	wpeasycart_cart_shipping_country_update( );
	wpeasycart_account_billing_country_update( );
	wpeasycart_account_shipping_country_update( );
}
function wpeasycart_cart_billing_country_update( ){
	if( document.getElementById( 'ec_cart_billing_country' ) ){
		var selected_country = jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val( );
		wpeasycart_address_labels( 'ec_cart_billing', selected_country );
		wpeasycart_address_place( 'ec_cart_billing', selected_country );
		if( ec_is_state_required( selected_country, 'ec_cart_billing' ) )
			jQuery( document.getElementById( 'ec_billing_state_required' ) ).show( );
		else
			jQuery( document.getElementById( 'ec_billing_state_required' ) ).hide( );
		
		if( document.getElementById( 'ec_cart_billing_state_' + selected_country ) ){
			jQuery( '.ec_billing_state_dropdown, #ec_cart_billing_state' ).hide( );
			jQuery( document.getElementById( 'ec_cart_billing_state_' + selected_country ) ).show( );
		}else{
			jQuery( '.ec_billing_state_dropdown' ).hide( );
			jQuery( document.getElementById( 'ec_cart_billing_state' ) ).show( );
		}
		if ( 'function' === typeof wpeasycart_subscription_short_place ) {
			wpeasycart_subscription_short_place(); /* 6.0.5: the short subscription checkout */
		}
	}
}
function wpeasycart_cart_shipping_country_update( ){
	if( document.getElementById( 'ec_cart_shipping_country' ) ){
		var selected_country = jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).val( );
		wpeasycart_address_labels( 'ec_cart_shipping', selected_country );
		wpeasycart_address_place( 'ec_cart_shipping', selected_country );
		if( ec_is_state_required( selected_country, 'ec_cart_shipping' ) )
			jQuery( document.getElementById( 'ec_shipping_state_required' ) ).show( );
		else
			jQuery( document.getElementById( 'ec_shipping_state_required' ) ).hide( );
		
		if( document.getElementById( 'ec_cart_shipping_state_' + selected_country ) ){
			jQuery( '.ec_shipping_state_dropdown, #ec_cart_shipping_state' ).hide( );
			jQuery( document.getElementById( 'ec_cart_shipping_state_' + selected_country ) ).show( );
		}else{
			jQuery( '.ec_shipping_state_dropdown' ).hide( );
			jQuery( document.getElementById( 'ec_cart_shipping_state' ) ).show( );
		}
	}
}
function wpeasycart_account_billing_country_update( ){
	if( document.getElementById( 'ec_account_billing_information_country' ) ){
		var selected_country = jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).val( );
		wpeasycart_address_labels( 'ec_account_billing_information', selected_country );
		wpeasycart_address_place( 'ec_account_billing_information', selected_country );
		if( ec_is_state_required( selected_country, 'ec_account_billing_information' ) )
			jQuery( document.getElementById( 'ec_billing_state_required' ) ).show( );
		else
			jQuery( document.getElementById( 'ec_billing_state_required' ) ).hide( );
		
		if( document.getElementById( 'ec_account_billing_information_state_' + selected_country ) ){
			jQuery( '.ec_billing_state_dropdown, #ec_account_billing_information_state' ).hide( );
			jQuery( document.getElementById( 'ec_account_billing_information_state_' + selected_country ) ).show( );
		}else{
			jQuery( '.ec_billing_state_dropdown' ).hide( );
			jQuery( document.getElementById( 'ec_account_billing_information_state' ) ).show( );
		}
	}
}
function wpeasycart_account_shipping_country_update( ){
	if( document.getElementById( 'ec_account_shipping_information_country' ) ){
		var selected_country = jQuery( document.getElementById( 'ec_account_shipping_information_country' ) ).val( );
		wpeasycart_address_labels( 'ec_account_shipping_information', selected_country );
		wpeasycart_address_place( 'ec_account_shipping_information', selected_country );
		if( ec_is_state_required( selected_country, 'ec_account_shipping_information' ) )
			jQuery( document.getElementById( 'ec_shipping_state_required' ) ).show( );
		else
			jQuery( document.getElementById( 'ec_shipping_state_required' ) ).hide( );
		
		if( document.getElementById( 'ec_account_shipping_information_state_' + selected_country ) ){
			jQuery( '.ec_shipping_state_dropdown, #ec_account_shipping_information_state' ).hide( );
			jQuery( document.getElementById( 'ec_account_shipping_information_state_' + selected_country ) ).show( );
		}else{
			jQuery( '.ec_shipping_state_dropdown' ).hide( );
			jQuery( document.getElementById( 'ec_account_shipping_information_state' ) ).show( );
		}
	}
}
function wpeasycart_isTouchDevice() {
      return 'ontouchstart' in window || !!(navigator.msMaxTouchPoints);
}
function ec_product_show_quick_view_link( modelnum ){
	jQuery( document.getElementById( 'ec_product_quickview_container_' + modelnum ) ).fadeIn(100);	
}
function ec_product_hide_quick_view_link( modelnum ){
	jQuery( document.getElementById( 'ec_product_quickview_container_' + modelnum ) ).fadeOut(100);	
}
function change_product_sort( menu_id, menu_name, submenu_id, submenu_name, subsubmenu_id, subsubmenu_name, manufacturer_id, pricepoint_id, currentpage_selected, perpage, URL, divider, filter_option = '' ){
	var url_string = URL + divider + "filternum=" + document.getElementById('sortfield').value;
	if( subsubmenu_id != 0 ){
		url_string = url_string + "&subsubmenuid=" + subsubmenu_id;
		if( subsubmenu_name != 0 )
			url_string = url_string + "&subsubmenu=" + subsubmenu_name;
	}else if( submenu_id != 0 ){
		url_string = url_string + "&submenuid=" + submenu_id;
		if( submenu_name != 0 )
			url_string = url_string + "&submenu=" + submenu_name;	
	}else if( menu_id != 0 ){
		url_string = url_string + "&menuid=" + menu_id;
		if( menu_name != 0 )
			url_string = url_string + "&menu=" + menu_name;
	}
	if( manufacturer_id > 0 )
		url_string = url_string + "&manufacturer=" + manufacturer_id;
	if( pricepoint_id > 0 )
		url_string = url_string + "&pricepoint=" + pricepoint_id;
	if( currentpage_selected )
		url_string = url_string + "&pagenum=" + currentpage_selected;
	if( perpage )
		url_string = url_string + "&perpage=" + perpage; 
    if( filter_option )
        url_string = url_string + "&filter_option=" + filter_option;
	window.location = url_string;
}
function ec_add_to_cart_redirect( product_id, model_number, quantity, use_quantity_tracking, min_quantity, max_quantity, nonce, quantity_ele = false ) {
	return true;
}
function ec_add_to_cart( product_id, model_number, quantity, use_quantity_tracking, min_quantity, max_quantity, nonce, quantity_ele = false ) {
	if( !use_quantity_tracking || ( !isNaN( quantity ) && quantity > 0 && quantity >= min_quantity && quantity <= max_quantity ) ){
		if ( quantity_ele && jQuery( '#' + quantity_ele ).length ) {
			if ( Number( jQuery( '#' + quantity_ele ).val() ) >= min_quantity && Number( jQuery( '#' + quantity_ele ).val() ) <= max_quantity ) {
				quantity = Number( jQuery( '#' + quantity_ele ).val() );
			}
		}

		ec_product_hide_quick_view_link( model_number );
		jQuery( document.getElementById( 'ec_addtocart_quantity_exceeded_error_' + model_number ) ).hide( );
		jQuery( document.getElementById( 'ec_addtocart_quantity_minimum_error_' + model_number ) ).hide( );
		
		jQuery( document.getElementById( "ec_product_loader_" + model_number ) ).show( );
		var data = {
			action: 'ec_ajax_add_to_cart',
			product_id: product_id,
			model_number: model_number,
			quantity: quantity,
			nonce: nonce
		};
		/* 6.0.2: the Meta event ID the button's AddToCart used, so the server event shares it */
		if ( window.wpeasycart_meta_add_eid ) {
			data.ec_meta_eid = window.wpeasycart_meta_add_eid;
			window.wpeasycart_meta_add_eid = '';
		}

		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			/* 6.0.5 ( issue #473 ): no answer ( no connection, a server error ): the store's add to cart link instead of a spinner. */
			error: function( ){
				wpeasycart_add_to_cart_fallback( model_number, quantity );
			},
			success: function( data ){
				var json_data = null;
				try {
					json_data = JSON.parse( data );
				} catch ( e ) {
					json_data = null;
				}
				/* 6.0.5 ( issue #473 ): an answer that can't be read ( an older store's empty answer to a cached page's expired
				   nonce ) goes through the store's add to cart link too. */
				if ( ! json_data || ! json_data[0] ) {
					wpeasycart_add_to_cart_fallback( model_number, quantity );
					return;
				}
				/* 6.0.2: the store refused the add ( catalog or inquiry mode, login for pricing, customer role, a donation ): its page says why.
				   6.0.5: also a cached page's expired nonce ( reason nonce ), where the store's add to cart link adds it instead. */
				if ( json_data[0] && false === json_data[0].added && json_data[0].redirect ) {
					window.location.href = json_data[0].redirect;
					return;
				}
				if ( json_data[0] && json_data[0].item_added ) {
					jQuery( document ).trigger( 'wpeasycart_item_added', [ json_data[0].item_added ] );
				}
				jQuery( document.getElementById( "ec_product_loader_" + model_number ) ).hide( );
				jQuery( document.getElementById( "ec_product_added_" + model_number ) ).show( ).delay( 2500 ).fadeOut( 'slow' );

				if ( jQuery( document.getElementById( "ec_added_to_cart_" + product_id ) ).length ) {
					jQuery( document.getElementById( "ec_add_to_cart_" + product_id ) ).css( 'display', 'none' );
					jQuery( document.getElementById( "ec_added_to_cart_" + product_id ) ).css( 'display', 'inline-block' );
				}

				if ( jQuery( document.getElementById( "ec_added_to_cart_type6_" + product_id  ) ).length ) {
					jQuery( document.getElementById( "ec_add_to_cart_" + product_id ) ).css( 'display', 'none' );
					jQuery( document.getElementById( "ec_added_to_cart_type6_" + product_id ) ).css( 'display', 'inline-block' );
				}

				jQuery( '.ec_product_added_to_cart' ).fadeIn( 'slow' );
				jQuery( ".ec_cart_items_total" ).html( json_data[0].total_items );
				jQuery( ".ec_cart_price_total" ).html( json_data[0].total_price );

				if( json_data[0].total_items == 1 ){
					jQuery( ".ec_menu_cart_singular_text" ).show( );
					jQuery( ".ec_menu_cart_plural_text" ).hide( );
				}else{
					jQuery( ".ec_menu_cart_singular_text" ).hide( );
					jQuery( ".ec_menu_cart_plural_text" ).show( );
				}

				if( json_data[0].total_items == 0 ){
					jQuery( ".ec_cart_price_total" ).hide( );
				}else{
					jQuery( ".ec_cart_price_total" ).show( );
				}

				wpeasycart_minicart_rows( json_data ); /* 6.0.2: rows built as text */
				if ( jQuery( '.wp-easycart-widget-cart-quantity' ).length ) {
					jQuery( '.wp-easycart-widget-cart-quantity' ).html( json_data[0].total_items );
				}
				if ( wpeasycart_cart_widgets_present( ) ) {
					wpeasycart_refresh_cart_widgets( ); /* 6.0.2: the whole cart ( with images ) for the Cart Icon and wpeasycart_cart_changed */
				}
			}
		} );
	} else {
		if ( ! isNaN( quantity ) && ( quantity < min_quantity || quantity < 1 ) ) {
			jQuery( document.getElementById( 'ec_addtocart_quantity_minimum_error_' + model_number ) ).show( );
			jQuery( document.getElementById( 'ec_addtocart_quantity_exceeded_error_' + model_number ) ).hide( );
		} else {
			jQuery( document.getElementById( 'ec_addtocart_quantity_exceeded_error_' + model_number ) ).show( );
			jQuery( document.getElementById( 'ec_addtocart_quantity_minimum_error_' + model_number ) ).hide( );
		}
	}

}

/* 6.0.5 ( issue #473 ): a background add whose answer can't be read ( no connection, a server error, an older store's empty answer
 * to a cached page's expired nonce ) stops its spinner and goes through the store's own add to cart link ( ?ec_action=addtocart,
 * which needs no nonce ) with the quantity chosen. Without that link ( a store copy of the page's script settings ) the button
 * only stops spinning. */
function wpeasycart_add_to_cart_fallback( model_number, quantity ) {
	jQuery( document.getElementById( 'ec_product_loader_' + model_number ) ).hide( );
	ec_product_show_quick_view_link( model_number );
	var url = ( window.wpeasycart_ajax_object && wpeasycart_ajax_object.add_to_cart_url ) ? String( wpeasycart_ajax_object.add_to_cart_url ) : '';
	if ( '' === url || ! model_number ) {
		return;
	}
	url += ( -1 === url.indexOf( '?' ) ? '?' : '&' ) + 'model_number=' + encodeURIComponent( model_number );
	if ( Number( quantity ) > 1 ) {
		url += '&quantity=' + Math.floor( Number( quantity ) );
	}
	window.location.href = url;
}

/* 6.0.2: the cart count, total, mini cart rows and the Cart Icon badge after a background add. The add responses carry
 * one row per cart line ( title, price, quantity, link, cartitem_id ) and the totals on the first row. */
function wpeasycart_update_cart_widgets( json_data ) {
	if ( ! json_data || ! json_data[0] ) {
		return;
	}
	jQuery( '.ec_cart_items_total' ).html( json_data[0].total_items );
	jQuery( '.ec_cart_price_total' ).html( json_data[0].total_price );
	if ( json_data[0].total_items == 1 ) {
		jQuery( '.ec_menu_cart_singular_text' ).show( );
		jQuery( '.ec_menu_cart_plural_text' ).hide( );
	} else {
		jQuery( '.ec_menu_cart_singular_text' ).hide( );
		jQuery( '.ec_menu_cart_plural_text' ).show( );
	}
	if ( json_data[0].total_items == 0 ) {
		jQuery( '.ec_cart_price_total' ).hide( );
	} else {
		jQuery( '.ec_cart_price_total' ).show( );
	}
	wpeasycart_minicart_rows( json_data );
	if ( jQuery( '.wp-easycart-widget-cart-quantity' ).length ) {
		jQuery( '.wp-easycart-widget-cart-quantity' ).html( json_data[0].total_items );
	}
}

/* 6.0.2: whether container ( default: the page ) shows a cart display the cart state keeps current: the Cart Icon's count,
 * an element marked data-wpec-cart-widget ( the Elementor cart widgets ), or on the page a wpeasycart_cart_changed listener.
 * The menu cart and the sidebar Cart widget are not counted: they keep their own requests and updates. */
function wpeasycart_cart_widgets_present( container ) {
	if ( wpeasycart_init_find( container || document, '.wp-easycart-widget-cart-quantity, [data-wpec-cart-widget]' ).length ) {
		return true;
	}
	if ( container && container !== document ) {
		return false;
	}
	try {
		var events = jQuery._data( document, 'events' );
		return !! ( events && events.wpeasycart_cart_changed && events.wpeasycart_cart_changed.length );
	} catch ( err ) {
		return false;
	}
}

var wpeasycart_cart_request = null;
var wpeasycart_cart_again = false;
var wpeasycart_cart_soon = null;
var wpeasycart_cart_answered = 0;
/**
 * 6.0.2: asks the server for the visitor's cart ( ec_ajax_cart_state: no nonce, never cached ), updates the Cart Icon's
 * count, then fires jQuery( document ).trigger( 'wpeasycart_cart_changed', [ cart ] ), cart =
 * { count, subtotal_display, items: [ { title, quantity, price_display, link, image, cartitem_id, product_id } ] } ( plain
 * text: insert with .text() ). Returns the request ( jqXHR ), or null before the store script is set up.
 */
function wpeasycart_refresh_cart_widgets( ) {
	if ( wpeasycart_cart_request ) {
		wpeasycart_cart_again = true;
		return wpeasycart_cart_request;
	}
	if ( 'undefined' == typeof wpeasycart_ajax_object || ! wpeasycart_ajax_object.ajax_url ) {
		return null;
	}
	wpeasycart_cart_request = jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		cache: false,
		data: {
			action: 'ec_ajax_cart_state',
			language: wpeasycart_ajax_object.current_language
		}
	} ).done( function( cart ) {
		if ( cart && 'object' == typeof cart && 'undefined' != typeof cart.count ) {
			wpeasycart_apply_cart_state( cart );
		}
	} ).always( function( ) {
		wpeasycart_cart_request = null;
		wpeasycart_cart_answered = new Date( ).getTime( );
		if ( wpeasycart_cart_again ) {
			wpeasycart_cart_again = false;
			wpeasycart_refresh_cart_widgets( );
		}
	} );
	return wpeasycart_cart_request;
}

/* 6.0.2: the automatic refresh ( page ready, a widget drawn, a cart update ): one request for the calls close together. */
function wpeasycart_refresh_cart_widgets_soon( force ) {
	if ( wpeasycart_cart_soon ) {
		return;
	}
	if ( ! force && ( wpeasycart_cart_request || new Date( ).getTime( ) - wpeasycart_cart_answered < 1500 ) ) {
		return;
	}
	wpeasycart_cart_soon = setTimeout( function( ) {
		wpeasycart_cart_soon = null;
		wpeasycart_refresh_cart_widgets( );
	}, 60 );
}

/* 6.0.2: shows a cart state in the Cart Icon's count ( the request it replaced ), then announces it. The menu cart, the
 * header totals and the sidebar Cart widget keep their own update paths. */
function wpeasycart_apply_cart_state( cart ) {
	jQuery( '.wp-easycart-widget-cart-quantity' ).text( Number( cart.count ) || 0 );
	try {
		jQuery( document ).trigger( 'wpeasycart_cart_changed', [ cart ] );
	} catch ( err ) {}
}

/* 6.0.2: a value from an add response as plain text ( its entities decoded, never read as markup ). */
function wpeasycart_minicart_text( value ) {
	var decoder = document.createElement( 'textarea' );
	decoder.innerHTML = ( null == value ) ? '' : String( value );
	return decoder.value;
}

/* 6.0.2: the mini cart widget's rows from an add response, built as elements and text: a product title, price or link
 * is never pasted into the page as markup. Rows without a title ( an empty cart's totals row ) are skipped. */
function wpeasycart_minicart_rows( json_data ) {
	var holder = jQuery( '.ec_cart_widget_minicart_product_padding' );
	if ( ! holder.length || ! json_data ) {
		return;
	}
	holder.empty( );
	for ( var i = 0; i < json_data.length; i++ ) {
		if ( ! json_data[i] || 'undefined' == typeof json_data[i].title ) {
			continue;
		}
		var link = String( ( json_data[i].link ) ? json_data[i].link : '' );
		if ( ! /^(https?:)?\/\//i.test( link ) && '/' != link.charAt( 0 ) ) {
			link = '#';
		}
		var row = jQuery( '<div class="ec_cart_widget_minicart_product_title"></div>' ).attr( 'id', 'ec_cart_widget_row_' + json_data[i].cartitem_id );
		row.append( jQuery( '<a></a>' ).attr( 'href', link ).text( wpeasycart_minicart_text( json_data[i].title ) ) );
		row.append( document.createTextNode( ' x ' ) );
		row.append( jQuery( '<span></span>' ).text( wpeasycart_minicart_text( json_data[i].quantity ) ) );
		row.append( document.createTextNode( ' @ ' ) );
		row.append( jQuery( '<span></span>' ).text( wpeasycart_minicart_text( json_data[i].price ) ) );
		holder.append( row );
	}
}

/* 6.0.2: the add to cart form of one product instance ( several can share a page: an Elementor template, a product list ). */
function wpeasycart_details_form( product_id, rand_id ) {
	var form = document.getElementById( 'ec_add_to_cart_form_' + product_id + '_' + rand_id );
	if ( ! form ) {
		var probe = document.getElementById( 'ec_quantity_' + product_id + '_' + rand_id ) || document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id );
		if ( probe ) {
			form = jQuery( probe ).closest( 'form' ).get( 0 );
		}
	}
	return ( form ) ? form : null;
}

/* 6.0.2: where one instance's advanced options live, so two forms on a page never read each other's choices. An older
 * custom template without the markers falls back to the whole page, as before. */
function wpeasycart_details_scope( product_id, rand_id ) {
	var scope = jQuery( '.ec_details_options_advanced[data-product-id="' + product_id + '"][data-rand-id="' + rand_id + '"]' );
	if ( scope.length ) {
		return scope;
	}
	var form = wpeasycart_details_form( product_id, rand_id );
	return ( form ) ? jQuery( form ) : jQuery( document );
}

var wpeasycart_state_timers = {};
var wpeasycart_state_prices = {};
/* 6.0.2: one product instance changed ( price, stock, SKU, image ): wpeasycart_product_state is announced once, after the
 * page's own updates for this change have run. price is the single price the form shows, when the caller knows it. */
function wpeasycart_product_state_queue( product_id, rand_id, price ) {
	var key = product_id + '_' + rand_id;
	if ( 'undefined' != typeof price && null !== price && '' !== price && ! isNaN( Number( price ) ) ) {
		wpeasycart_state_prices[ key ] = Number( price );
	}
	if ( wpeasycart_state_quiet > 0 || wpeasycart_state_timers[ key ] ) {
		return;
	}
	wpeasycart_state_timers[ key ] = setTimeout( function( ) {
		delete wpeasycart_state_timers[ key ];
		var state = null;
		try {
			state = wpeasycart_product_state( product_id, rand_id );
		} catch ( err ) {
			return;
		}
		try {
			jQuery( document ).trigger( 'wpeasycart_product_state', [ state ] );
		} catch ( err2 ) {}
	}, 0 );
}

/* 6.0.2: plain text with runs of white space made single. */
function wpeasycart_state_text( value ) {
	return String( ( null == value ) ? '' : value ).replace( /\s+/g, ' ' ).replace( /^\s+|\s+$/g, '' );
}

/**
 * 6.0.2: what one product instance shows now ( the payload of wpeasycart_product_state ): { product_id ( number ), rand_id,
 * price ( number or null ), price_display, list_price_display, stock ( number, or null when not counted ), stock_display, sku,
 * image ( URL ), options ( [ { name, value } ] of the form's ec_option fields ) }. Display values are plain text.
 */
function wpeasycart_product_state( product_id, rand_id ) {
	var key = product_id + '_' + rand_id;
	var form = wpeasycart_details_form( product_id, rand_id );
	var price = ( 'undefined' != typeof wpeasycart_state_prices[ key ] ) ? wpeasycart_state_prices[ key ] : null;
	var price_display = ( null !== price && document.getElementById( 'currency_symbol_' + key ) ) ? wpeasycart_minicart_text( ec_details_format_money_v2( product_id, rand_id, price ) ) : '';

	var list_el = jQuery( '.ec_product_details_page[data-product-id="' + product_id + '"][data-rand-id="' + rand_id + '"]' ).find( '.ec_product_old_price, .ec_product_old_price_ele' ).first( );
	if ( ! list_el.length ) {
		list_el = jQuery( '[data-wpec-linked-product="' + product_id + '"]' ).find( '.ec_product_old_price, .ec_product_old_price_ele' ).first( );
	}

	var stock_el = document.getElementById( 'ec_details_stock_quantity_' + key );
	var stock_raw = stock_el ? wpeasycart_state_text( jQuery( stock_el ).text( ) ) : '';
	var stock = ( '' === stock_raw || 'inf' == stock_raw || '10000000' == stock_raw || isNaN( Number( stock_raw ) ) ) ? null : Number( stock_raw );
	var stock_display = '';
	if ( null !== stock ) {
		stock_display = wpeasycart_state_text( jQuery( stock_el ).parent( '.ec_details_stock_total, .ec_details_stock_total_ele' ).text( ) );
		if ( ! /[^\d\s.,]/.test( stock_display ) ) {
			stock_display = wpeasycart_state_text( jQuery( '.ec_details_stock_total_ele[data-wpec-linked-product="' + product_id + '"]' ).first( ).text( ) );
		}
		if ( '' === stock_display ) {
			stock_display = String( stock );
		}
	}

	var sku = wpeasycart_state_text( jQuery( '.ec_details_model_number_sku_' + key ).first( ).text( ) );
	if ( '' === sku && document.getElementById( 'ec_default_sku_' + key ) ) {
		sku = wpeasycart_state_text( jQuery( document.getElementById( 'ec_default_sku_' + key ) ).val( ) );
	}
	if ( '' === sku ) {
		sku = wpeasycart_state_text( jQuery( '.ec_details_sku[data-wpec-linked-product="' + product_id + '"]' ).first( ).text( ) );
	}

	var main = jQuery( '.ec_details_main_image_' + key ).first( );
	if ( ! main.length ) {
		main = jQuery( '.ec_details_main_image[data-product-id="' + product_id + '"]' ).first( );
	}
	var main_img = main.find( 'img' ).first( );
	var image = main_img.length ? String( main_img.attr( 'src' ) || main_img.attr( 'data-src' ) || '' ) : '';

	var options = [];
	if ( form ) {
		jQuery.each( jQuery( form ).serializeArray( ), function( i, field ) {
			if ( field && 0 === String( field.name ).indexOf( 'ec_option' ) ) {
				options.push( { name: field.name, value: field.value } );
			}
		} );
	}

	return {
		product_id: Number( product_id ),
		rand_id: String( rand_id ),
		price: price,
		price_display: price_display,
		list_price_display: list_el.length ? wpeasycart_state_text( list_el.text( ) ) : '',
		stock: stock,
		stock_display: stock_display,
		sku: sku,
		image: image,
		options: options
	};
}

/* 6.0.2: the separate Price widgets / [ec_product_details_price] of this product ( data-wpec-linked-product ) show the
 * price chosen in any of its add to cart forms. price is the single price the form shows ( VAT as the form handles it ). */
function wpeasycart_details_linked_price( product_id, rand_id, price ) {
	wpeasycart_product_state_queue( product_id, rand_id, price ); /* 6.0.2 */
	wpeasycart_unit_price_update( product_id, rand_id, price ); /* 6.0.5 */
	var linked = jQuery( '.ec_details_price[data-wpec-linked-product="' + product_id + '"]' );
	if ( ! linked.length || isNaN( Number( price ) ) ) {
		return;
	}
	var vat_added = jQuery( document.getElementById( 'vat_added_' + product_id + '_' + rand_id ) ).val( );
	var vat_rate_multiplier = Number( jQuery( document.getElementById( 'vat_rate_multiplier_' + product_id + '_' + rand_id ) ).val( ) );
	if ( ! vat_rate_multiplier ) {
		vat_rate_multiplier = 1;
	}
	linked.each( function( ){
		var linked_rand = jQuery( this ).attr( 'data-wpec-linked-rand' );
		var amount = Number( price );
		/* The widget's own VAT ( the shopper's rate it was drawn with ), else the form's. */
		var widget_vat_added = jQuery( this ).attr( 'data-wpec-vat-added' );
		var widget_vat_multiplier = Number( jQuery( this ).attr( 'data-wpec-vat-multiplier' ) );
		var use_vat_added = ( 'undefined' != typeof widget_vat_added ) ? widget_vat_added : vat_added;
		var use_vat_multiplier = ( widget_vat_multiplier > 0 ) ? widget_vat_multiplier : vat_rate_multiplier;
		if ( jQuery( this ).hasClass( 'ec_details_no_vat_price' ) ) {
			amount = ( '1' == use_vat_added ) ? amount : amount / use_vat_multiplier;
		} else if ( jQuery( this ).hasClass( 'ec_details_vat_price' ) ) {
			amount = ( '1' == use_vat_added ) ? amount * use_vat_multiplier : amount;
		}
		jQuery( this ).children( '.ec_product_price_ele_' + product_id + '_' + linked_rand + ', .ec_product_sale_price_ele_' + product_id + '_' + linked_rand + ', .ec_product_price_' + product_id + '_' + linked_rand + ', .ec_product_sale_price_' + product_id + '_' + linked_rand ).html( ec_details_format_money_v2( product_id, rand_id, amount ) );
	} );
}

/* 6.0.5 ( admin review M16 ): the price per kg, litre, metre or item follows the price the options make. wp_easycart_unit_price::
 * note_html() puts the factor ( the base over what one item holds ), the sentence ( "[price] / kg" ), the VAT price flag ( the
 * store shows both prices ) and a running offer's rules on the note. */
function wpeasycart_unit_price_update( product_id, rand_id, price ) {
	var notes = jQuery( '.ec_product_unit_price[data-wpec-unit-product="' + product_id + '"]' );
	if ( ! notes.length || isNaN( Number( price ) ) || ! ( Number( price ) > 0 ) ) {
		return;
	}
	var vat_added = jQuery( document.getElementById( 'vat_added_' + product_id + '_' + rand_id ) ).val( );
	var vat_rate_multiplier = Number( jQuery( document.getElementById( 'vat_rate_multiplier_' + product_id + '_' + rand_id ) ).val( ) ) || 1;
	notes.each( function( ){
		var note = jQuery( this );
		var factor = Number( note.attr( 'data-wpec-unit-factor' ) );
		if ( ! ( factor > 0 ) ) {
			return;
		}
		var amount = Number( price );
		if ( '1' == note.attr( 'data-wpec-unit-vat' ) && '1' == vat_added ) {
			amount = amount * vat_rate_multiplier;
		}
		var rules = note.attr( 'data-wpec-unit-rules' );
		if ( rules && 'function' == typeof wpeasycart_offer_price ) {
			try {
				amount = wpeasycart_offer_price( JSON.parse( rules ), amount );
			} catch ( err ) {}
		}
		var words = note.attr( 'data-wpec-unit-words' ) || '[price] / [unit]';
		note.text( words.split( '[price]' ).join( ec_details_format_money_v2( product_id, rand_id, amount * factor ) ) );
	} );
}

/* 6.0.2: the separate Stock widgets / [ec_product_details_stock] of this product follow the variant chosen in a form. */
function wpeasycart_details_linked_stock( product_id, rand_id ) {
	wpeasycart_product_state_queue( product_id, rand_id ); /* 6.0.2 */
	var linked = jQuery( '.ec_details_stock_total_ele[data-wpec-linked-product="' + product_id + '"]' );
	var own = document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id );
	if ( ! linked.length || ! own ) {
		return;
	}
	var quantity = String( jQuery( own ).html( ) ).replace( /^\s+|\s+$/g, '' );
	if ( '' == quantity || '10000000' == quantity ) {
		return;
	}
	linked.each( function( ){
		if ( 'inf' == quantity ) {
			jQuery( this ).hide( );
		} else {
			jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + jQuery( this ).attr( 'data-wpec-linked-rand' ) ) ).html( quantity );
			jQuery( this ).show( );
		}
	} );
}

function ec_minus_quantity( product_id, min_quantity ){	
	/* 6.0.4: a cart line at its minimum says so ( the box's data-min-note, printed by the cart templates ). */
	var wpec_box = jQuery( document.getElementById( 'ec_quantity_' + product_id ) );
	if ( wpec_box.attr( 'data-min-note' ) && Number( wpec_box.val() ) <= Number( min_quantity ) ) {
		wpeasycart_cart_quantity_limit_say( wpec_box, -1 );
	}
	var currval = jQuery( document.getElementById( 'ec_quantity_' + product_id ) ).val( );
	currval = Number( currval ) - 1;
	if( currval <= 0 ){
		currval = 1;
	}
	if( currval < min_quantity ){
		currval = min_quantity;
	}
	jQuery( document.getElementById( 'ec_quantity_' + product_id ) ).val( currval );
}

function ec_plus_quantity( product_id, track_quantity, max_quantity ){
	/* 6.0.4: a cart line's box knows its most as the cart is now ( the same product on other lines counted, kept up to date by
	   ec_update_cart() ), and says why it stops there. */
	var wpec_box = jQuery( document.getElementById( 'ec_quantity_' + product_id ) );
	if ( wpec_box.is( '[data-max-note]' ) ) {
		var wpec_line_max = parseInt( wpec_box.attr( 'max' ), 10 );
		if ( ! isNaN( wpec_line_max ) && wpec_line_max > 0 ) {
			max_quantity = wpec_line_max;
			if ( Number( wpec_box.val() ) >= wpec_line_max ) {
				wpeasycart_cart_quantity_limit_say( wpec_box, 1 );
				return;
			}
		}
	}
	if( jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id ) ).length && jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id ) ).val( ) != 10000000 ){
		max_quantity = ( 'inf' == jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id ) ).html( ) ) ? 10000000 : Number( jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id ) ).html( ) );
	}
	if( max_quantity > 0 && max_quantity != 10000000 ){
		jQuery( document.getElementById( 'ec_quantity_' + product_id ) ).attr( 'max', max_quantity );
	}
	var currval = Number( jQuery( document.getElementById( 'ec_quantity_' + product_id ) ).val( ) );
	if( currval < Number( max_quantity ) ){
		currval = currval + 1;
	}else if( max_quantity != 10000000 ){
		currval = Number( max_quantity );
	}else{
		currval = currval + 1;
	}
	jQuery( document.getElementById( 'ec_quantity_' + product_id ) ).val( currval );
}

function ec_cartitem_delete( cartitem_id, model_number, nonce ){
	var data = {
		action: 'ec_ajax_cartitem_delete',
		ec_v3_24: 'true',
		cartitem_id: cartitem_id,
		nonce: nonce
	}
	
	jQuery( document.getElementById( 'ec_cartitem_delete_' + cartitem_id ) ).hide( );
	jQuery( document.getElementById( 'ec_cartitem_deleting_' + cartitem_id ) ).show( );
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function(data){ 
			jQuery( document.getElementById( 'ec_cartitem_row_' + cartitem_id ) ).remove( );
			jQuery( '.ec_cartitem_' + cartitem_id ).remove();
			jQuery( '[id="ec_cartitem_min_error_' + cartitem_id + '"], [id="ec_cartitem_max_error_' + cartitem_id + '"]' ).remove( ); /* 6.0.4: every copy */
			jQuery( document.getElementById( 'ec_cart_widget_row_' + cartitem_id ) ).remove( );
			var response_obj = JSON.parse( data );
			ec_update_cart( response_obj );
		}
	} );
}

/* 6.0.4: the one-page cart's quantity stepper ( − box + ) and the order summary's ( ec_cart_summary_line_v2.php ): the buttons change
   the box within its min and max, and the box's own change event saves it ( ec_cartitem_update_v2(), or the summary's handler below ),
   as typing a number does. A summary button at the end of the range says so with aria-disabled and stays in the tab order. A button
   that cannot go further says why ( the stock, the item's purchase limit or its minimum: wpeasycart_cart_quantity_limit_say() ). */
jQuery( document ).on( 'click', '.ec_cart_table_qty_step, .ec_cart_summary_qty_step', function( e ) {
	e.preventDefault();
	var input = jQuery( this ).closest( '.ec_cart_table_qty, .ec_cart_summary_qty' ).find( 'input.ec_quantity, input.ec_quantity_small_v2' ).first();
	var step = parseInt( jQuery( this ).attr( 'data-step' ), 10 ) || 0;
	if ( ! input.length || input.prop( 'disabled' ) ) {
		return;
	}
	if ( 'true' === jQuery( this ).attr( 'aria-disabled' ) ) {
		wpeasycart_cart_quantity_limit_say( input, step );
		return;
	}
	var value = parseInt( input.val(), 10 ) || 0;
	var min = parseInt( input.attr( 'min' ), 10 ), max = parseInt( input.attr( 'max' ), 10 );
	if ( isNaN( min ) || min < 1 ) {
		min = 1;
	}
	var next = value + step;
	if ( next < min ) {
		next = min;
	}
	if ( ! isNaN( max ) && max > 0 && next > max ) {
		next = max;
	}
	if ( next === value ) {
		wpeasycart_cart_quantity_limit_say( input, step );
		return;
	}
	input.val( next );
	input[0].dispatchEvent( new Event( 'change', { bubbles: true } ) );
} );

/* 6.0.4: a click on the words, or the empty part of the row, of a checkout tick box ( terms, newsletter ) or a payment choice works as
   a click on the box ( Matt ). WP EasyCart's own templates put each one in a label since 6.0.4; this does the same for copies of the
   templates made before, and for rows other code prints without a label. Only the row's own box, never a link, field or button in it. */
jQuery( document ).on( 'click', '.ec_cart_input_row, .ec_cart_option_row, .ec_cart_payment_table_row', function( e ) {
	if ( e.isDefaultPrevented() || jQuery( e.target ).closest( '.ec_cart_input_row, .ec_cart_option_row, .ec_cart_payment_table_row' )[0] !== this ) {
		return;
	}
	if ( jQuery( e.target ).closest( 'a, label, input, select, textarea, button, iframe' ).length ) {
		return;
	}
	var box = jQuery( this ).find( '#ec_terms_agree[type="checkbox"], #ec_cart_is_subscriber[type="checkbox"], input[type="radio"][name="ec_cart_payment_selection"]' );
	if ( 1 !== box.length || box.prop( 'disabled' ) || box.closest( 'label' ).length || ( box.attr( 'id' ) && jQuery( 'label[for="' + box.attr( 'id' ) + '"]' ).length ) ) {
		return;
	}
	if ( box.is( ':radio' ) && box.prop( 'checked' ) ) {
		return;
	}
	box[0].click();
} );

/* 6.0.5: the same for a product's radio and checkbox options ( Matt: only the small box could be clicked ). WP EasyCart's templates
   give each choice a label covering its row since 6.0.5 ( .ec_details_choice ); this covers copies of the templates made before. The
   box is the row's own input ( the option scripts read it as the row's first child ), clicked so their click handlers run. */
jQuery( document ).on( 'click', '.ec_details_checkbox_row, .ec_details_radio_row', function( e ) {
	if ( e.isDefaultPrevented() || jQuery( e.target ).closest( '.ec_details_checkbox_row, .ec_details_radio_row' )[0] !== this ) {
		return;
	}
	if ( jQuery( e.target ).closest( 'a, label, input, select, textarea, button, iframe' ).length ) {
		return;
	}
	var box = jQuery( this ).children( 'input[type="checkbox"], input[type="radio"]' );
	if ( 1 !== box.length || box.prop( 'disabled' ) || ( box.attr( 'id' ) && jQuery( 'label[for="' + box.attr( 'id' ) + '"]' ).length ) ) {
		return;
	}
	if ( box.is( ':radio' ) && box.prop( 'checked' ) ) {
		return;
	}
	box[0].click();
} );

var ec_cartitem_update_clickTimeout;
function ec_cartitem_update_v2( cartitem_id, model_number, nonce ){
	clearTimeout( ec_cartitem_update_clickTimeout );
	ec_cartitem_update_clickTimeout = setTimeout( function() {
		ec_cartitem_update( cartitem_id, model_number, nonce );
	}, 500 );
}

/* 6.0.2: quantities on the one-page checkout's order summary ( mobile and desktop copies kept in step ).
   6.0.4: each line saves on its own ( a change to a second line within the wait dropped the first line's ), only the newest answer for
   a line counts, the line shows that it is saving, and the answer brings the summary's lines when the cart's lines changed with it
   ( an Offers gift added or taken off, wpeasycart_summary_sync() ). The stepper's buttons ( ec_cart_summary_line_v2.php ) change the box. */
var wpeasycart_summary_quantity_timers = {}, wpeasycart_summary_quantity_seq = {};
jQuery( document ).on( 'change', 'input.ec_quantity_small_v2[data-cartitem]', function() {
	var input = jQuery( this ), cartitem = input.attr( 'data-cartitem' ), nonce = input.attr( 'data-nonce' );
	var quantity = parseInt( input.val(), 10 ), min = parseInt( input.attr( 'min' ), 10 ), max = parseInt( input.attr( 'max' ), 10 );
	if ( isNaN( quantity ) || quantity < 1 ) {
		quantity = 1;
	}
	/* 6.0.4: a box that knows what its limits say ( data-max-note / data-min-note ) sends what was typed: the store keeps what it can
	   and says why ( quantity_notice, wpeasycart_cart_quantity_note() ). Template copies made before keep the limits here, as before. */
	var says_limits = input.is( '[data-max-note]' );
	if ( ! says_limits && ! isNaN( min ) && min > 0 && quantity < min ) {
		quantity = min;
	}
	if ( ! says_limits && ! isNaN( max ) && max > 0 && quantity > max ) {
		quantity = max;
	}
	jQuery( 'input.ec_quantity_small_v2[data-cartitem="' + cartitem + '"]' ).val( quantity );
	wpeasycart_summary_qty_state( cartitem );
	wpeasycart_cart_quantity_notes_clear( cartitem ); /* 6.0.4: the answer says again if a limit still applies */
	/* 6.0.2: a refused update ( an expired page answers with an empty body ) puts the last saved quantity back and says so. */
	var failed = function() {
		var copies = jQuery( 'input.ec_quantity_small_v2[data-cartitem="' + cartitem + '"]' );
		wpeasycart_summary_rows( cartitem ).removeClass( 'is-busy' ).removeAttr( 'aria-busy' );
		copies.each( function() {
			this.value = this.defaultValue;
		} );
		wpeasycart_summary_qty_state( cartitem );
		wpeasycart_summary_error( copies, input.attr( 'data-error' ) );
	};
	clearTimeout( wpeasycart_summary_quantity_timers[ cartitem ] );
	wpeasycart_summary_quantity_timers[ cartitem ] = setTimeout( function() {
		wpeasycart_summary_quantity_timers[ cartitem ] = 0;
		var seq = ( wpeasycart_summary_quantity_seq[ cartitem ] || 0 ) + 1;
		wpeasycart_summary_quantity_seq[ cartitem ] = seq;
		wpeasycart_summary_rows( cartitem ).addClass( 'is-busy' ).attr( 'aria-busy', 'true' );
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			cache: false,
			data: { action: 'ec_ajax_cartitem_update', ec_v3_24: 'true', cartitem_id: cartitem, quantity: quantity, nonce: nonce, wpec_summary: 1 },
			success: function( data ) {
				if ( seq !== wpeasycart_summary_quantity_seq[ cartitem ] ) {
					return; /* a newer change of this line is on its way */
				}
				var response_obj = wpeasycart_summary_parse( data );
				if ( ! response_obj ) {
					failed();
					return;
				}
				wpeasycart_summary_rows( cartitem ).removeClass( 'is-busy' ).removeAttr( 'aria-busy' );
				jQuery( '.wpec-quantity-error' ).hide();
				jQuery( 'input.ec_quantity_small_v2[data-cartitem="' + cartitem + '"]' ).each( function() {
					this.defaultValue = String( quantity );
				} );
				ec_update_cart( response_obj );
				if ( wpeasycart_summary_sync( response_obj ) ) {
					return; /* the checkout is drawn again */
				}
				wpeasycart_summary_say( jQuery( '.ec_cart_summary_lines' ).first().attr( 'data-updated' ) );
				/* 6.0.4: the shipping method list's prices follow the new quantity ( a heavier parcel can cost more ). */
				wpeasycart_onepage_refresh_shipping();
			},
			error: function() {
				if ( seq === wpeasycart_summary_quantity_seq[ cartitem ] ) {
					failed();
				}
			}
		} );
	}, 400 );
} );

/* 6.0.4: the order summary's remove button ( ec_cart_summary_line_v2.php ). The line leaves both copies of the summary once the store
   answers; the totals, the shipping choices and any line the store added or took off with it follow, and the focus moves to the next
   line. An empty cart reloads the page ( ec_update_cart() ), which then shows the empty cart. */
jQuery( document ).on( 'click', '.ec_cart_summary_remove', function( e ) {
	e.preventDefault();
	var button = jQuery( this ), cartitem = button.attr( 'data-cartitem' ), nonce = button.attr( 'data-nonce' );
	var line = button.closest( '.ec_cart_summary_line' ), rows = wpeasycart_summary_rows( cartitem );
	if ( ! cartitem || rows.hasClass( 'is-removing' ) ) {
		return;
	}
	/* A quantity change of this line still waiting to be saved is dropped, and an answer on its way no longer counts. */
	clearTimeout( wpeasycart_summary_quantity_timers[ cartitem ] );
	wpeasycart_summary_quantity_timers[ cartitem ] = 0;
	wpeasycart_summary_quantity_seq[ cartitem ] = ( wpeasycart_summary_quantity_seq[ cartitem ] || 0 ) + 1;
	var quantity = line.find( 'input.ec_quantity_small_v2' ).val() || button.attr( 'data-quantity' ) || 1;
	rows.removeClass( 'is-busy' ).addClass( 'is-removing' ).attr( 'aria-busy', 'true' );
	var restore = function() {
		var now = wpeasycart_summary_rows( cartitem );
		now.removeClass( 'is-removing' ).removeAttr( 'aria-busy' );
		wpeasycart_summary_error( now.find( '.ec_cart_summary_remove' ), button.attr( 'data-error' ) );
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		cache: false,
		data: { action: 'ec_ajax_cartitem_delete', ec_v3_24: 'true', cartitem_id: cartitem, nonce: nonce, wpec_summary: 1 },
		success: function( data ) {
			var response_obj = wpeasycart_summary_parse( data );
			if ( ! response_obj ) {
				restore();
				return;
			}
			wpeasycart_summary_ga4_remove( button, quantity );
			var focus_id = '', focus_list = line.closest( '.ec_cart_summary_lines' ).attr( 'data-wpec-summary' );
			if ( line.length && line[0].contains( document.activeElement ) ) {
				var to = line.nextAll( '.ec_cart_summary_line:not(.is-removing)' ).first();
				if ( ! to.length ) {
					to = line.prevAll( '.ec_cart_summary_line:not(.is-removing)' ).first();
				}
				focus_id = to.attr( 'data-cartitem' ) || '';
			}
			jQuery( document.getElementById( 'ec_cartitem_row_' + cartitem ) ).remove(); /* the cart screen's copy, drawn hidden */
			jQuery( document.getElementById( 'ec_cartitem_min_error_' + cartitem ) ).remove();
			jQuery( document.getElementById( 'ec_cartitem_max_error_' + cartitem ) ).remove();
			ec_update_cart( response_obj );
			if ( ! response_obj.cart.length ) {
				return; /* the page reloads */
			}
			if ( wpeasycart_summary_sync( response_obj ) ) {
				return; /* the checkout is drawn again ( no account or shipping needed any more ) */
			}
			if ( focus_id ) {
				var target = jQuery( '.ec_cart_summary_lines[data-wpec-summary="' + focus_list + '"] .ec_cart_summary_line[data-cartitem="' + focus_id + '"]' );
				var control = target.find( '.ec_cart_summary_remove' ).first();
				( control.length ? control : target.find( 'button, input' ).first() ).trigger( 'focus' );
			}
			var reduce = ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
			wpeasycart_summary_rows( cartitem ).slideUp( reduce ? 0 : 160, function() {
				jQuery( this ).remove();
			} );
			wpeasycart_summary_say( jQuery( '.ec_cart_summary_lines' ).first().attr( 'data-updated' ) );
			if ( 'function' === typeof wpeasycart_onepage_refresh_shipping ) {
				wpeasycart_onepage_refresh_shipping();
			}
		},
		error: restore
	} );
} );

/* 6.0.4: both copies of one summary line ( the summary column and the phone summary ). */
function wpeasycart_summary_rows( cartitem ) {
	return jQuery( '.ec_cart_summary_line[data-cartitem="' + cartitem + '"]' );
}

/* 6.0.4: a line whose quantity change waits to be saved or is being saved. */
function wpeasycart_summary_pending( cartitem ) {
	return ( !! wpeasycart_summary_quantity_timers[ cartitem ] || wpeasycart_summary_rows( cartitem ).hasClass( 'is-busy' ) );
}

/* 6.0.4: the cart AJAX answer, or null when it is not one ( an expired page answers with nothing ). */
function wpeasycart_summary_parse( data ) {
	var response_obj = null;
	if ( data && 'object' === typeof data ) {
		response_obj = data;
	} else {
		try {
			response_obj = JSON.parse( data );
		} catch ( e ) {
			response_obj = null;
		}
	}
	return ( response_obj && 'object' === typeof response_obj && response_obj.cart && 'number' === typeof response_obj.cart.length ) ? response_obj : null;
}

/* 6.0.4: the stepper's − and + say when the box is at its minimum or maximum ( aria-disabled: the button keeps the focus ). */
function wpeasycart_summary_qty_state( cartitem ) {
	jQuery( 'input.ec_quantity_small_v2[data-cartitem="' + cartitem + '"]' ).each( function() {
		var input = jQuery( this ), box = input.closest( '.ec_cart_summary_qty' );
		if ( ! box.length ) {
			return;
		}
		var value = parseInt( input.val(), 10 ) || 0, min = parseInt( input.attr( 'min' ), 10 ), max = parseInt( input.attr( 'max' ), 10 );
		if ( isNaN( min ) || min < 1 ) {
			min = 1;
		}
		var at_min = ( value <= min ), at_max = ( ! isNaN( max ) && max > 0 && value >= max );
		box.find( '.ec_cart_summary_qty_minus' ).attr( 'aria-disabled', at_min ? 'true' : null );
		box.find( '.ec_cart_summary_qty_plus' ).attr( 'aria-disabled', at_max ? 'true' : null );
	} );
}

/* 6.0.4: a message under each copy of a line ( under its buttons, or after the box in template copies made before 6.0.4 ). */
var wpeasycart_summary_error_timer;
function wpeasycart_summary_error( elements, message ) {
	if ( ! message ) {
		return;
	}
	jQuery( elements ).each( function() {
		var anchor = jQuery( this ).closest( '.ec_cart_summary_controls' );
		if ( ! anchor.length ) {
			anchor = jQuery( this );
		}
		var note = anchor.next( '.wpec-quantity-error' );
		if ( ! note.length ) {
			note = jQuery( '<div class="ec_cart_error_message wpec-quantity-error" role="alert"></div>' ).insertAfter( anchor );
		}
		note.text( message ).show();
	} );
	clearTimeout( wpeasycart_summary_error_timer );
	wpeasycart_summary_error_timer = setTimeout( function() { jQuery( '.wpec-quantity-error' ).fadeOut(); }, 5000 );
}

/* 6.0.4: a quantity limit said by its cart line ( Matt: a box the store put back, with nothing saying it was the stock, looked like an
   error ). Every copy of the line shows it: the order summary's lines ( under the line's buttons ), the one-page cart screen's and the
   classic cart's rows ( the max / min error rows their templates always printed and nothing showed; which = 'min' uses the min row ).
   One note at a time ( the last thing the shopper did ): it stays until the line's quantity changes again, another line says
   something, or a cart answer shows the line is no longer at that limit ( wpeasycart_cart_quantity_limits() ); ec_update_cart() shows
   the answer's quantity_notice. */
function wpeasycart_cart_quantity_note( cartitem, message, which ) {
	cartitem = String( cartitem || '' );
	if ( ! cartitem || ! message ) {
		return;
	}
	var own = ( 'min' === which ) ? 'min' : 'max', other = ( 'min' === which ) ? 'max' : 'min';
	wpeasycart_summary_rows( cartitem ).each( function() {
		var line = jQuery( this ), anchor = line.children( '.ec_cart_summary_controls' ).first();
		if ( ! anchor.length ) {
			anchor = line.find( 'input.ec_quantity_small_v2' ).first();
		}
		if ( ! anchor.length ) {
			return;
		}
		var note = anchor.next( '.wpec-quantity-limit' );
		if ( ! note.length ) {
			note = jQuery( '<div class="ec_cart_error_message wpec-quantity-limit" role="alert"></div>' ).insertAfter( anchor );
		}
		note.attr( 'data-wpec-limit', own ).text( message ).show();
	} );
	var rows = jQuery( '[id="ec_cartitem_' + own + '_error_' + cartitem + '"]' );
	if ( ! rows.length ) {
		rows = jQuery( '[id="ec_cartitem_' + other + '_error_' + cartitem + '"]' );
	}
	rows.each( function() {
		var row = jQuery( this ), cell = row.children( 'td, div' ).first();
		( cell.length ? cell : row ).text( message );
		row.addClass( 'wpec-quantity-limit-row' ).attr( { role: 'alert', 'data-wpec-limit': own } );
		/* a table row shows as one, unless the phone layout made the cart's rows blocks */
		var display = 'block';
		if ( 'TR' === this.nodeName ) {
			display = ( this.parentNode && window.getComputedStyle && 'block' === window.getComputedStyle( this.parentNode ).display ) ? 'block' : 'table-row';
		}
		this.style.display = display;
	} );
}

/* 6.0.4: takes the quantity limit notes away ( one line's, or every line's ). */
function wpeasycart_cart_quantity_notes_clear( cartitem ) {
	if ( cartitem ) {
		wpeasycart_summary_rows( cartitem ).find( '.wpec-quantity-limit' ).remove();
		jQuery( '[id="ec_cartitem_max_error_' + cartitem + '"], [id="ec_cartitem_min_error_' + cartitem + '"]' ).filter( '.wpec-quantity-limit-row' ).removeClass( 'wpec-quantity-limit-row' ).removeAttr( 'role data-wpec-limit' ).css( 'display', '' );
		return;
	}
	jQuery( '.wpec-quantity-limit' ).remove();
	jQuery( '.wpec-quantity-limit-row' ).removeClass( 'wpec-quantity-limit-row' ).removeAttr( 'role data-wpec-limit' ).css( 'display', '' );
}

/* 6.0.4: the limit a line's note is about ( max | min ), or '' when it shows none. */
function wpeasycart_cart_quantity_note_kind( cartitem ) {
	var note = wpeasycart_summary_rows( cartitem ).find( '.wpec-quantity-limit' ).first();
	if ( ! note.length ) {
		note = jQuery( '[id="ec_cartitem_max_error_' + cartitem + '"], [id="ec_cartitem_min_error_' + cartitem + '"]' ).filter( '.wpec-quantity-limit-row' ).first();
	}
	return note.length ? String( note.attr( 'data-wpec-limit' ) || 'max' ) : '';
}

/* 6.0.4: a − or + that cannot go further says why: the box's data-max-note ( the stock, counting the same product on the cart's other
   lines, or the item's purchase limit ) or data-min-note ( its minimum ), printed by the cart templates and kept up to date by
   ec_update_cart(). A box without them says nothing, as before. */
function wpeasycart_cart_quantity_limit_say( input, step ) {
	input = jQuery( input );
	if ( ! input.length || ! step ) {
		return;
	}
	var note = input.attr( step > 0 ? 'data-max-note' : 'data-min-note' );
	var cartitem = wpeasycart_cart_line_of( input );
	if ( note && cartitem ) {
		wpeasycart_cart_quantity_notes_clear();
		wpeasycart_cart_quantity_note( cartitem, note, step > 0 ? 'max' : 'min' );
	}
}

/* 6.0.4: the cart line a quantity box belongs to ( data-cartitem, else its id: ec_quantity_<line> / ec_quantity_mobile_<line> ). */
function wpeasycart_cart_line_of( input ) {
	input = jQuery( input );
	var cartitem = input.attr( 'data-cartitem' );
	if ( ! cartitem ) {
		var found = /^ec_quantity_(?:mobile_)?(\d+)$/.exec( input.attr( 'id' ) || '' );
		cartitem = found ? found[1] : '';
	}
	return String( cartitem || '' );
}

/* 6.0.4: every quantity box of one cart line ( the cart's, the summary's two copies; ids can repeat on the one-page checkout ). */
function wpeasycart_cart_line_inputs( cartitem ) {
	return jQuery( '[id="ec_quantity_' + cartitem + '"], [id="ec_quantity_mobile_' + cartitem + '"], input.ec_quantity_small_v2[data-cartitem="' + cartitem + '"]' );
}

/* 6.0.4: a line's least and most and what each says, from the cart answer ( ec_get_cart_data(): another line's change moves them ).
   A note the line no longer needs goes ( below its most again, or above its least ). */
function wpeasycart_cart_quantity_limits( item ) {
	if ( ! item || 'undefined' === typeof item.max_quantity ) {
		return;
	}
	var kind = wpeasycart_cart_quantity_note_kind( item.id ), quantity = Number( item.quantity );
	if ( ( 'max' === kind && Number( item.max_quantity ) > 0 && quantity < Number( item.max_quantity ) ) || ( 'min' === kind && quantity > Math.max( 1, Number( item.min_quantity ) || 1 ) ) ) {
		wpeasycart_cart_quantity_notes_clear( item.id );
	}
	wpeasycart_cart_line_inputs( item.id ).each( function() {
		var box = jQuery( this );
		if ( Number( item.max_quantity ) > 0 ) {
			box.attr( 'max', item.max_quantity );
		}
		if ( Number( item.min_quantity ) > 0 ) {
			box.attr( 'min', item.min_quantity );
		}
		box.attr( 'data-max-note', item.max_note || '' ).attr( 'data-min-note', item.min_note || '' );
	} );
	wpeasycart_summary_qty_state( item.id );
}

/* 6.0.4: the summary's lines follow the cart after a change. When the checkout's own parts no longer fit the cart ( the only download
   removed, so no account is needed; the last item to ship removed: wp_easycart_summary_shape() ) the page is drawn again and this
   answers true. When the cart's lines are not the ones shown ( an Offers gift added or taken off with the change ), each copy is drawn
   again from the answer's summary_lines, keeping the focus on the same button; the quantity the store kept ( a stock or purchase limit )
   goes into the boxes of lines nobody is changing. */
function wpeasycart_summary_sync( response_obj ) {
	if ( ! response_obj || ! response_obj.cart ) {
		return false;
	}
	var ids = [], i;
	for ( i = 0; i < response_obj.cart.length; i++ ) {
		ids.push( String( response_obj.cart[ i ].id ) );
	}
	var lines = ( response_obj.summary_lines && 'object' === typeof response_obj.summary_lines ) ? response_obj.summary_lines : null;
	var shape = jQuery( '.ec_cart_summary_lines[data-shape]' ).first().attr( 'data-shape' );
	if ( lines && 'string' === typeof lines.shape && '' !== lines.shape && shape && shape !== lines.shape && response_obj.cart.length ) {
		ec_reload_cart();
		return true;
	}
	var controls = [ 'ec_cart_summary_qty_minus', 'ec_cart_summary_qty_plus', 'ec_quantity_small_v2', 'ec_cart_summary_remove' ];
	jQuery( '.ec_cart_summary_lines' ).each( function() {
		var list = jQuery( this ), shown = [], copy = list.attr( 'data-wpec-summary' );
		list.children( '.ec_cart_summary_line' ).not( '.is-removing' ).each( function() {
			shown.push( String( jQuery( this ).attr( 'data-cartitem' ) ) );
		} );
		if ( shown.join( ',' ) === ids.join( ',' ) || ! lines || 'string' !== typeof lines[ copy ] ) {
			return;
		}
		var active = document.activeElement, keep_id = '', keep_class = '';
		if ( active && list[0].contains( active ) ) {
			keep_id = jQuery( active ).closest( '.ec_cart_summary_line' ).attr( 'data-cartitem' ) || '';
			for ( var c = 0; c < controls.length; c++ ) {
				if ( jQuery( active ).hasClass( controls[ c ] ) ) {
					keep_class = controls[ c ];
				}
			}
		}
		list.html( lines[ copy ] );
		if ( keep_id && keep_class ) {
			list.find( '.ec_cart_summary_line[data-cartitem="' + keep_id + '"] .' + keep_class ).first().trigger( 'focus' );
		}
	} );
	for ( i = 0; i < response_obj.cart.length; i++ ) {
		var id = String( response_obj.cart[ i ].id ), kept = String( response_obj.cart[ i ].quantity );
		if ( wpeasycart_summary_pending( id ) || '' === kept || 'undefined' === kept ) {
			continue;
		}
		jQuery( 'input.ec_quantity_small_v2[data-cartitem="' + id + '"]' ).each( function() {
			this.value = kept;
			this.defaultValue = kept;
		} );
		wpeasycart_summary_qty_state( id );
	}
	return false;
}

/* 6.0.4: tells screen readers that the cart changed ( one polite status region on the page ). */
var wpeasycart_summary_say_timer;
function wpeasycart_summary_say( text ) {
	if ( ! text || ! document.body ) {
		return;
	}
	var region = document.getElementById( 'wpec-summary-status' );
	if ( ! region ) {
		region = document.createElement( 'div' );
		region.id = 'wpec-summary-status';
		region.className = 'wpec-summary-status';
		region.setAttribute( 'role', 'status' );
		region.setAttribute( 'aria-live', 'polite' );
		document.body.appendChild( region );
	}
	region.textContent = '';
	clearTimeout( wpeasycart_summary_say_timer );
	wpeasycart_summary_say_timer = setTimeout( function() {
		region.textContent = text;
	}, 100 );
}

/* 6.0.4: Google Analytics' remove_from_cart for a line removed from the summary ( the button carries the line's details when GA4 is on ). */
function wpeasycart_summary_ga4_remove( button, quantity ) {
	button = jQuery( button );
	if ( '1' !== button.attr( 'data-ga4' ) || 'function' !== typeof ec_ga4_remove_from_cart ) {
		return;
	}
	var tag_manager = ( '1' === button.attr( 'data-ga4-tm' ) );
	if ( tag_manager ? ( 'undefined' === typeof dataLayer ) : ( 'undefined' === typeof gtag ) ) {
		return;
	}
	try {
		ec_ga4_remove_from_cart( button.attr( 'data-model' ), button.attr( 'data-title' ), Number( quantity ) || 1, button.attr( 'data-price' ), button.attr( 'data-currency' ), button.attr( 'data-brand' ), tag_manager );
	} catch ( e ) {}
}

function ec_cartitem_update( cartitem_id, model_number, nonce ){
	wpeasycart_cart_quantity_notes_clear( cartitem_id ); /* 6.0.4: the answer says again if a limit still applies */
	var data = {
		action: 'ec_ajax_cartitem_update',
		ec_v3_24: 'true',
		cartitem_id: cartitem_id,
		quantity: jQuery( document.getElementById( 'ec_quantity_' + model_number ) ).val( ),
		nonce: nonce
	};

	jQuery( document.getElementById( 'ec_cartitem_updating_' + cartitem_id ) ).show( );

	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){
			jQuery( document.getElementById( 'ec_cartitem_updating_' + cartitem_id ) ).hide( );
			/* 6.0.4: an answer that is not the cart ( an expired page answers with nothing ) leaves the page as it was. */
			var response_obj = null;
			try {
				response_obj = JSON.parse( data );
			} catch ( e ) {
				response_obj = null;
			}
			if ( response_obj && 'object' === typeof response_obj ) {
				ec_update_cart( response_obj );
			}
		},
		error: function() {
			jQuery( document.getElementById( 'ec_cartitem_updating_' + cartitem_id ) ).hide( );
		}
	} );
}

function ec_apply_coupon( nonce, is_mobile = false ){
	jQuery( document.getElementById( 'ec_apply_coupon' ) ).hide( );
	if ( jQuery( '#ec_applying_coupon_v2' ).length ) {
		jQuery( '#ec_applying_coupon_v2' ).addClass( 'ec_cart_button_working_active' );
		jQuery( '#ec_applying_coupon_v2_mobile' ).addClass( 'ec_cart_button_working_active' );
		jQuery( document.getElementById( 'ec_apply_coupon_mobile' ) ).hide( );
	} else {
		jQuery( document.getElementById( 'ec_applying_coupon' ) ).show( );
	}
	
	var coupon_code = ( is_mobile ) ? jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val() : jQuery( document.getElementById( 'ec_coupon_code' ) ).val( );
	if ( is_mobile ) {
		jQuery( document.getElementById( 'ec_coupon_code' ) ).val( coupon_code );
	} else {
		if ( jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).length ) {
			jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val( coupon_code )
		}
	}
	
	var data = {
		action: 'ec_ajax_redeem_coupon_code',
		ec_v3_24: 'true',
		couponcode: coupon_code,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ) {
			jQuery( document.getElementById( 'ec_apply_coupon' ) ).show( );
			if ( jQuery( '#ec_applying_coupon_v2' ).length ) {
				jQuery( '#ec_applying_coupon_v2' ).removeClass( 'ec_cart_button_working_active' );
				jQuery( '#ec_applying_coupon_v2_mobile' ).removeClass( 'ec_cart_button_working_active' );
				jQuery( document.getElementById( 'ec_apply_coupon_mobile' ) ).show( );
			} else {
				jQuery( document.getElementById( 'ec_applying_coupon' ) ).hide( );
			}
			var response_obj = JSON.parse( data );
			ec_update_cart( response_obj );
			/* 6.0.4: a code can change what each shipping method costs ( the one-page checkout's list ). */
			wpeasycart_onepage_refresh_shipping();
			if ( response_obj.is_coupon_valid ) {
				jQuery( document.getElementById( 'ec_coupon_error' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_success' ) ).html( response_obj.coupon_message ).show( );
				if ( jQuery( '#ec_applying_coupon_v2' ).length ) {
					jQuery( document.getElementById( 'ec_coupon_error_mobile' ) ).hide( );
					jQuery( document.getElementById( 'ec_coupon_success_mobile' ) ).html( response_obj.coupon_message ).show( );
				}
			} else {
				jQuery( document.getElementById( 'ec_coupon_success' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_error' ) ).html( response_obj.coupon_message ).show( );
				if ( jQuery( '#ec_applying_coupon_v2' ).length ) {
					jQuery( document.getElementById( 'ec_coupon_success_mobile' ) ).hide( );
					jQuery( document.getElementById( 'ec_coupon_error_mobile' ) ).html( response_obj.coupon_message ).show( );
				}
				if ( jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).length ) {
					jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val( '' );
				}
				if ( jQuery( document.getElementById( 'ec_coupon_code' ) ).length ) {
					jQuery( document.getElementById( 'ec_coupon_code' ) ).val( '' );
				}
			}
		} 
	} );
}

function subscription_create_account( nonce ) {
	var email_complete = ec_validate_email_block( 'ec_contact' );
	var create_account_complete = ec_validate_create_account( 'ec_contact' );
	var recaptcha_complete = true;
	var recaptcha_response = '';

	if ( document.getElementById( 'ec_account_register_recaptcha' ) ) {
		recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val();
		if ( ! recaptcha_response.length ) {
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', '1px solid red' );
			recaptcha_complete = false;
		} else {
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', 'none' );
		}
	}

	var account_terms_complete = true; /* 6.0.2 */
	if ( jQuery( document.getElementById( 'ec_account_terms_agree' ) ).length ) {
		account_terms_complete = jQuery( document.getElementById( 'ec_account_terms_agree' ) ).is( ':checked' );
		jQuery( document.getElementById( 'ec_account_terms_error' ) ).toggle( ! account_terms_complete );
	}

	if ( email_complete && create_account_complete && recaptcha_complete && account_terms_complete ) {
		jQuery( document.getElementById( 'ec_cart_create_account_loader' ) ).show();

		var email = jQuery( document.getElementById( 'ec_contact_email' ) ).val();
		var first_name = ( jQuery( document.getElementById( 'ec_contact_first_name' ) ).length ) ? jQuery( document.getElementById( 'ec_contact_first_name' ) ).val() : '';
		var last_name = ( jQuery( document.getElementById( 'ec_contact_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_contact_last_name' ) ).val() : '';
		var password = jQuery( document.getElementById( 'ec_contact_password' ) ).val();

		var data = {
			action: 'ec_ajax_subscription_create_account',
			nonce: nonce,
			ec_contact_email: email,
			ec_contact_first_name: first_name,
			ec_contact_last_name: last_name,
			ec_contact_password: password,
			recaptcha_response: recaptcha_response
		};

		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( response ){
				var data_arr = JSON.parse( response );
				if ( data_arr['error'] ) {
					if ( data_arr['error']['id'] && 'email_busy' === data_arr['error']['id'] ) {
						wpeasycart_email_busy_note( data_arr['error']['message'] ); /* 6.0.5: too many emails looked up: nothing said about this one */
					} else {
						jQuery( document.getElementById( 'ec_subscription_email_exists' ) ).show();
					}
					jQuery( document.getElementById( 'ec_cart_create_account_loader' ) ).hide();
					if ( '' != recaptcha_response ) {
						jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val( '' );
						grecaptcha.reset( wpeasycart_register_recaptcha );
					}
				} else {
					ec_reload_cart();
					jQuery( document.getElementById( 'ec_subscription_email_exists' ) ).hide();
					jQuery( document.getElementById( 'ec_user_contact_form' ) ).hide();
					jQuery( document.getElementById( 'ec_user_logged_out_header' ) ).hide();
					jQuery( document.getElementById( 'ec_cart_user_logged_in_name' ) ).html( data_arr['name'] );
					wpeasycart_signed_in_card( document.getElementById( 'ec_user_logged_in_form' ), data_arr['name'], email );
					jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).val( data_arr['first_name'] );
					jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).val( data_arr['last_name'] );
					jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).val( data_arr['first_name'] );
					jQuery( document.getElementById( 'ec_cart_shipping_last_name' ) ).val( data_arr['last_name'] );
					jQuery( document.getElementById( 'ec_user_logged_in_form' ) ).show();
					jQuery( document.getElementById( 'ec_cart_billing_form' ) ).show();
					jQuery( document.getElementById( 'ec_cart_billing_locked' ) ).hide();
					if ( jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).hide();
					}
					if ( ! wpeasycart_subscription_short() ) {
						jQuery( document.getElementById( 'ec_cart_subscription_end_form' ) ).hide();
					}
					if ( wpeasycart_subscription_step() ) { /* 6.0.5: the email step gives way to the signed-in card and the checkout opens */
						jQuery( wpeasycart_subscription_step() ).hide();
						wpeasycart_subscription_checkout( true, true );
					}
				}
			} 
		} );
	}
}

/* 6.0.5: the subscription page's email first ( ec_cart_subscription.php #ec_subscription_account, the class wp_easycart_subscription_signup;
   Matt: after Sign up a shopper who is not signed in found an almost empty page ). The checkout below ( #ec_subscription_after_account )
   waits, blurred and out of reach, while the step's data-state is email ( the field and Continue ), existing ( the account's password,
   then the page's own sign in ) or new ( a password for a new account, Create account ). later ( "New subscribers choose a password after
   paying": the email is locked for the purchase, which makes the account ) and an account made here open it. Change goes back to email:
   the lock is taken away and the checkout waits again. ec-store.css shows each state's parts.
   6.0.5: the checkout's Contact is the same step ( ec_checkout_account.php, data-context checkout ) for an order that needs an account.
   Its data-open names the states that open the rest ( [data-wpec-after-account]: the classic page's billing and shipping, opened for a
   new account too, whose password is saved with them ); data-reload asks for the page again when the rest is not drawn yet ( the
   one-page checkout draws it only once the email is settled ). data-state optional is guest checkout: no Continue, a sign in that opens
   under the email ( wpeasycart_checkout_signin() ). */
var wpeasycart_subscription_unlocking = null;

function wpeasycart_subscription_step() {
	return document.getElementById( 'ec_subscription_account' );
}

function wpeasycart_subscription_checkout( open, focus ) {
	var parts = document.querySelectorAll( '[data-wpec-after-account]' );
	for ( var p = 0; p < parts.length; p++ ) { /* 6.0.5: the checkout's ( ec_checkout_details.php .ec_checkout_after_account ) */
		if ( open ) {
			parts[ p ].removeAttribute( 'data-locked' );
			parts[ p ].removeAttribute( 'inert' );
			parts[ p ].removeAttribute( 'aria-hidden' );
		} else {
			parts[ p ].setAttribute( 'data-locked', '1' );
			parts[ p ].setAttribute( 'inert', '' );
			parts[ p ].setAttribute( 'aria-hidden', 'true' );
		}
	}
	if ( open && focus && parts.length && ! document.getElementById( 'ec_subscription_after_account' ) ) {
		var first_part = jQuery( parts[0] ).find( 'input:visible, select:visible' ).first();
		if ( first_part.length ) {
			first_part.trigger( 'focus' );
		}
	}
	var wrap = document.getElementById( 'ec_subscription_after_account' );
	if ( ! wrap ) {
		return;
	}
	var body = wrap.querySelector( '.ec_subscription_preview_body' );
	if ( open ) {
		wrap.removeAttribute( 'data-locked' );
		if ( body ) {
			body.removeAttribute( 'inert' );
			body.removeAttribute( 'aria-hidden' );
		}
		if ( focus ) {
			var first = jQuery( document.getElementById( 'ec_cart_billing_form' ) ).find( 'input:visible, select:visible' ).first();
			if ( first.length ) {
				first.trigger( 'focus' );
			}
		}
	} else {
		/* the payment card's sketch stands in only while the real one is not drawn yet ( Change after Save & Continue ) */
		jQuery( wrap ).toggleClass( 'ec_subscription_has_payment', 'none' !== jQuery( document.getElementById( 'ec_cart_subscription_end_form' ) ).css( 'display' ) );
		wrap.setAttribute( 'data-locked', '1' );
		if ( body ) {
			body.setAttribute( 'inert', '' );
			body.setAttribute( 'aria-hidden', 'true' );
		}
	}
}

function wpeasycart_subscription_state( state, email ) {
	var step = wpeasycart_subscription_step();
	if ( ! step ) {
		return;
	}
	var holder = jQuery( step );
	step.setAttribute( 'data-state', state );
	jQuery( document.getElementById( 'ec_subscription_after_account' ) ).toggleClass( 'ec_subscription_quiet', 'email' !== state ); /* the step says what to do next */
	if ( email ) {
		holder.find( '.ec_subscription_email_value' ).text( email );
		holder.find( '.ec_subscription_email_mark' ).text( String( Array.from( email )[0] || '' ).toUpperCase() );
		jQuery( document.getElementById( 'ec_cart_login_email' ) ).val( email );
	}
	var note = holder.find( '.ec_subscription_account_note' );
	note.text( note.attr( 'data-note-' + state ) || '' );
	jQuery( document.getElementById( 'ec_contact_email' ) ).prop( 'readonly', 'email' !== state );
	jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', 'existing' === state );
	jQuery( document.getElementById( 'ec_subscription_email_busy' ) ).text( '' ).hide();
	ec_hide_error( 'ec_contact_email' );
	wpeasycart_email_suggestion( false );
	/* 6.0.5: the checkout's step ( data-context checkout ): which states open the rest, and the new account's switch on the classic page */
	var opens = String( step.getAttribute( 'data-open' ) || 'later' ).split( ' ' );
	var open = -1 !== opens.indexOf( state );
	jQuery( '#ec_subscription_account input#ec_create_account_selector[type="hidden"]' ).prop( 'disabled', 'new' !== state );
	/* 6.0.5: the checkout's questions ( checkout fields, the newsletter box ) show once the email is settled: a new email, or the
	   account signed in ( the page then draws them again ); never while the email or its password is asked */
	jQuery( '[data-wpec-account-fields]' ).prop( 'hidden', 'email' === state || 'existing' === state );
	if ( 'email' === state ) {
		wpeasycart_email_check_clear();
	}
	if ( open && '1' === step.getAttribute( 'data-reload' ) && ! document.querySelector( '[data-wpec-after-account]' ) ) {
		wpeasycart_checkout_veil( jQuery( step ), 'ec_cart_goto_page_loader', 'loading_checkout' );
		window.location.reload();
		return;
	}
	wpeasycart_subscription_checkout( open, open && 'later' === state );
	if ( 'existing' === state ) {
		jQuery( document.getElementById( 'ec_cart_login_password' ) ).trigger( 'focus' );
	} else if ( 'new' === state ) {
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).find( 'input:visible' ).first().trigger( 'focus' );
	} else if ( 'email' === state ) {
		jQuery( document.getElementById( 'ec_contact_email' ) ).trigger( 'focus' );
	}
}

function wpeasycart_subscription_email_continue() {
	var step = wpeasycart_subscription_step();
	var input = jQuery( document.getElementById( 'ec_contact_email' ) );
	var button = jQuery( document.getElementById( 'ec_subscription_email_continue' ) );
	if ( ! step || ! input.length || ! button.length || 'true' === button.attr( 'aria-busy' ) ) {
		return false; /* 6.0.5: guest checkout's Contact has no Continue */
	}
	var email = String( input.val() || '' ).trim();
	if ( ! ec_validate_email( email ) ) {
		ec_show_error( 'ec_contact_email' );
		input.attr( 'aria-invalid', 'true' ).trigger( 'focus' );
		return false;
	}
	ec_hide_error( 'ec_contact_email' );
	input.removeAttr( 'aria-invalid' );
	jQuery( document.getElementById( 'ec_subscription_email_busy' ) ).text( '' ).hide();
	button.attr( 'aria-busy', 'true' ).prop( 'disabled', true );
	var done = function() {
		button.removeAttr( 'aria-busy' ).prop( 'disabled', false );
	};
	var check = function( token ) {
		var post = { action: 'ec_ajax_subscription_email', 'do': 'check', email: email, nonce: step.getAttribute( 'data-nonce' ) };
		if ( 'string' === typeof token && '' !== token ) {
			post.check_token = token;
		}
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			dataType: 'json',
			data: post,
			success: function( data ) {
				done();
				if ( ! data ) {
					return;
				}
				/* 6.0.5: the store asks a human check before it says whether this email has an account ( wp_easycart_email_lookups );
				   a passed check posts the email again */
				if ( 'check' === data.error ) {
					wpeasycart_email_check( data, function( answer ) {
						button.attr( 'aria-busy', 'true' ).prop( 'disabled', true );
						check( answer );
					} );
					return;
				}
				wpeasycart_email_check_clear();
				if ( 'signed_in' === data.state || 'expired' === data.error ) {
					window.location.reload();
					return;
				}
				if ( 'email' === data.error ) {
					ec_show_error( 'ec_contact_email' );
					input.attr( 'aria-invalid', 'true' ).trigger( 'focus' );
					return;
				}
				if ( data.error ) {
					jQuery( document.getElementById( 'ec_subscription_email_busy' ) ).text( data.message || '' ).show();
					return;
				}
				wpeasycart_subscription_state( data.state, data.email || email );
			},
			error: done
		} );
	};
	/* a Change just before took the lock away: the new email is checked once that answer is in */
	if ( wpeasycart_subscription_unlocking ) {
		jQuery.when( wpeasycart_subscription_unlocking ).always( check );
	} else {
		check();
	}
	return false;
}

function wpeasycart_subscription_email_change() {
	var step = wpeasycart_subscription_step();
	if ( ! step ) {
		return false;
	}
	var reload = ( 'later' === step.getAttribute( 'data-state' ) && '1' === step.getAttribute( 'data-reload' ) ); /* 6.0.5: the one-page checkout hides the rest again */
	if ( 'later' === step.getAttribute( 'data-state' ) ) {
		if ( reload ) {
			wpeasycart_checkout_veil( jQuery( step ), 'ec_cart_goto_page_loader', 'loading_checkout' );
		}
		wpeasycart_subscription_unlocking = jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			dataType: 'json',
			data: { action: 'ec_ajax_subscription_email', 'do': 'unlock', nonce: step.getAttribute( 'data-nonce' ) },
			complete: function() {
				wpeasycart_subscription_unlocking = null;
				if ( reload ) {
					window.location.reload();
				}
			}
		} );
		if ( reload ) {
			return false;
		}
	}
	jQuery( document.getElementById( 'ec_cart_login_password' ) ).val( '' );
	jQuery( document.getElementById( 'ec_contact_password' ) ).val( '' );
	wpeasycart_subscription_state( 'email' );
	var input = document.getElementById( 'ec_contact_email' );
	if ( input && input.select ) {
		input.select();
	}
	return false;
}

/* 6.0.5: the human check the store asks before the email step says whether an email has an account ( wp_easycart_email_lookups: the
   browser's 3rd new email in an hour, the address's 4th, a rush of lookups ): Turnstile or reCAPTCHA, as Settings › Checkout protection
   set it up, drawn under the email, in the step's own place ( #ec_subscription_email_check, made here so store copies of the templates get
   it too ). The provider's script loads only then. */
var wpeasycart_email_check_waiting = [];
function wpeasycart_email_check_ready() {
	var waiting = wpeasycart_email_check_waiting;
	wpeasycart_email_check_waiting = [];
	for ( var w = 0; w < waiting.length; w++ ) {
		waiting[ w ]();
	}
}
function wpeasycart_email_check_provider( provider, ready ) {
	var loaded = ( 'recaptcha' === provider ) ? ( window.grecaptcha && window.grecaptcha.render ) : ( window.turnstile && window.turnstile.render );
	if ( loaded ) {
		ready();
		return;
	}
	wpeasycart_email_check_waiting.push( ready );
	var source = ( 'recaptcha' === provider ) ? 'https://www.google.com/recaptcha/api.js' : 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	var scripts = document.getElementsByTagName( 'script' );
	for ( var s = 0; s < scripts.length; s++ ) {
		if ( scripts[ s ].src && 0 === scripts[ s ].src.indexOf( source ) ) {
			/* another script loads it ( Checkout protection, the registration form ): wait for it */
			var tries = 0;
			var wait = window.setInterval( function() {
				tries++;
				if ( ( 'recaptcha' === provider ) ? ( window.grecaptcha && window.grecaptcha.render ) : ( window.turnstile && window.turnstile.render ) ) {
					window.clearInterval( wait );
					wpeasycart_email_check_ready();
				} else if ( tries > 100 ) {
					window.clearInterval( wait );
				}
			}, 100 );
			return;
		}
	}
	var tag = document.createElement( 'script' );
	tag.src = source + '?render=explicit&onload=wpeasycart_email_check_ready';
	tag.async = true;
	tag.defer = true;
	document.head.appendChild( tag );
}
function wpeasycart_email_check_clear() {
	var holder = document.getElementById( 'ec_subscription_email_check' );
	if ( holder ) {
		holder.hidden = true;
		holder.innerHTML = '';
	}
}
function wpeasycart_email_check( data, passed ) {
	var holder = document.getElementById( 'ec_subscription_email_check' );
	if ( ! holder ) {
		var after = document.getElementById( 'ec_subscription_email_busy' ) || document.getElementById( 'ec_contact_email' );
		if ( ! after || ! after.parentNode ) {
			return;
		}
		holder = document.createElement( 'div' );
		holder.id = 'ec_subscription_email_check';
		holder.className = 'ec_subscription_email_check';
		after.parentNode.insertBefore( holder, after.nextSibling );
	}
	holder.innerHTML = '';
	var text = document.createElement( 'p' );
	text.className = 'ec_subscription_email_check_text';
	text.setAttribute( 'role', 'status' );
	text.textContent = data.message || '';
	holder.appendChild( text );
	var box = document.createElement( 'div' );
	box.className = 'ec_subscription_email_check_box';
	holder.appendChild( box );
	holder.hidden = false;
	var done = false;
	var answer = function( token ) {
		if ( done || ! token ) {
			return;
		}
		done = true;
		passed( token );
	};
	wpeasycart_email_check_provider( data.provider, function() {
		try {
			if ( 'recaptcha' === data.provider ) {
				window.grecaptcha.render( box, { sitekey: data.site_key, callback: answer } );
			} else {
				window.turnstile.render( box, { sitekey: data.site_key, callback: answer } );
			}
		} catch ( e ) {
			/* the provider's own problem: the step's Continue posts again */
		}
	} );
}

/* 6.0.5: too many emails looked up ( wp_easycart_email_lookups ): "Too many tries", nothing about the email. The email step's own line
   ( #ec_subscription_email_busy ), else the create account email row. */
function wpeasycart_email_busy_note( message ) {
	message = message || ( wpeasycart_ajax_object.checkout_text && wpeasycart_ajax_object.checkout_text.email_busy ) || '';
	var line = document.getElementById( 'ec_subscription_email_busy' );
	if ( line && message ) {
		jQuery( line ).text( message ).show();
		return;
	}
	wpeasycart_create_account_error( 'email_busy' );
}

/* The one-page checkout's create account email row: "email exists", "Too many tries" or nothing ( its own words kept for the first ). */
function wpeasycart_create_account_error( error ) {
	var row = jQuery( document.getElementById( 'ec_create_account_email_error' ) );
	if ( ! row.length ) {
		return;
	}
	if ( undefined === row.data( 'wpecText' ) ) {
		row.data( 'wpecText', row.html() );
	}
	var busy = ( wpeasycart_ajax_object.checkout_text && wpeasycart_ajax_object.checkout_text.email_busy ) || '';
	if ( 'email_busy' === error && '' !== busy ) {
		row.empty().append( jQuery( '<div>' ).text( busy ) ).show();
	} else if ( 'user_create_error' === error ) {
		row.html( row.data( 'wpecText' ) ).show();
	} else {
		row.hide();
	}
}

/* The account made after paying ( wp_easycart_subscription_signup::card_html(): the order confirmation, the subscription in My Account,
   the account dashboard ): its password. */
function wpeasycart_subscription_set_password( card ) {
	card = jQuery( card );
	var input = card.find( '.ec_subscription_set_password_input' );
	var button = card.find( '.ec_subscription_set_password_save' );
	var note = card.find( '.ec_subscription_set_password_error' );
	var password = String( input.val() || '' );
	var min = parseInt( card.attr( 'data-min' ), 10 ) || 6;
	if ( 'true' === button.attr( 'aria-busy' ) ) {
		return false;
	}
	if ( password.length < min ) {
		note.text( card.attr( 'data-short' ) || '' ).prop( 'hidden', false );
		input.attr( 'aria-invalid', 'true' ).trigger( 'focus' );
		return false;
	}
	note.text( '' ).prop( 'hidden', true );
	input.removeAttr( 'aria-invalid' );
	button.attr( 'aria-busy', 'true' ).prop( 'disabled', true );
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: { action: 'ec_ajax_subscription_set_password', password: password, nonce: card.attr( 'data-nonce' ) },
		success: function( data ) {
			button.removeAttr( 'aria-busy' ).prop( 'disabled', false );
			if ( data && data.saved ) {
				card.find( '.ec_subscription_set_password_form' ).prop( 'hidden', true );
				card.find( '.ec_subscription_set_password_done' ).prop( 'hidden', false );
				card.addClass( 'is-saved' );
				return;
			}
			note.text( ( data && data.message ) ? data.message : ( card.attr( 'data-failed' ) || '' ) ).prop( 'hidden', false );
			input.attr( 'aria-invalid', 'true' ).trigger( 'focus' );
		},
		error: function() {
			button.removeAttr( 'aria-busy' ).prop( 'disabled', false );
			note.text( card.attr( 'data-failed' ) || '' ).prop( 'hidden', false );
		}
	} );
	return false;
}

jQuery( document ).on( 'click', '#ec_subscription_email_continue', function( e ) {
	e.preventDefault();
	wpeasycart_subscription_email_continue();
} );
jQuery( document ).on( 'click', '.ec_subscription_email_change', function( e ) {
	e.preventDefault();
	wpeasycart_subscription_email_change();
} );
jQuery( document ).on( 'keydown', '.ec_subscription_email_change', function( e ) {
	if ( ' ' === e.key ) { /* a link that acts as a button answers Space too */
		e.preventDefault();
		wpeasycart_subscription_email_change();
	}
} );
/* Enter in the step never posts the page's form ( that is the payment, or the sign in while the password shows ). */
jQuery( document ).on( 'keydown', '#ec_subscription_account #ec_contact_email', function( e ) {
	if ( 'Enter' === e.key || 13 === e.which ) {
		e.preventDefault();
		if ( ! this.readOnly ) {
			wpeasycart_subscription_email_continue();
		}
	}
} );

/* 6.0.5: the checkout's Contact ( ec_checkout_account.php ). Guest checkout: "Already have an account? Log in" opens the password under
   the email ( no second email ); the classic page then posts its form with ec_login_selector ticked. */
function wpeasycart_checkout_signin( open, focus ) {
	var step = wpeasycart_subscription_step();
	if ( ! step || 'optional' !== step.getAttribute( 'data-state' ) ) {
		return false;
	}
	if ( 'undefined' === typeof open || null === open ) {
		open = '1' !== step.getAttribute( 'data-signin' );
	}
	if ( open ) {
		step.setAttribute( 'data-signin', '1' );
		jQuery( document.getElementById( 'ec_create_account_selector' ) ).prop( 'checked', false );
		jQuery( document.getElementById( 'ec_user_create_form' ) ).hide();
	} else {
		step.removeAttribute( 'data-signin' );
		jQuery( document.getElementById( 'ec_cart_login_password' ) ).val( '' );
		ec_hide_error( 'ec_cart_login_password' );
		ec_hide_error( 'ec_cart_login_email' );
	}
	jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', !! open );
	jQuery( document.getElementById( 'ec_user_login_link' ) ).attr( 'aria-expanded', open ? 'true' : 'false' );
	jQuery( document.getElementById( 'ec_checkout_email_known' ) ).prop( 'hidden', true );
	if ( focus ) {
		var email = jQuery( document.getElementById( 'ec_contact_email' ) );
		if ( open && ec_validate_email( String( email.val() || '' ).trim() ) ) {
			jQuery( document.getElementById( 'ec_cart_login_password' ) ).trigger( 'focus' );
		} else {
			email.trigger( 'focus' );
		}
	}
	return false;
}

/* The classic page's Sign in: the email above is the account's. */
function wpeasycart_checkout_signin_submit() {
	jQuery( document.getElementById( 'ec_cart_login_email' ) ).val( String( jQuery( document.getElementById( 'ec_contact_email' ) ).val() || '' ).trim() );
	jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', true );
	var valid = ec_validate_cart_login();
	if ( ! valid && jQuery( document.getElementById( 'ec_cart_login_email_error' ) ).is( ':visible' ) ) {
		ec_hide_error( 'ec_cart_login_email' );
		ec_show_error( 'ec_contact_email' );
		jQuery( document.getElementById( 'ec_contact_email' ) ).trigger( 'focus' );
	}
	return valid;
}

jQuery( document ).on( 'click', '.ec_checkout_signin_toggle', function( e ) {
	e.preventDefault();
	wpeasycart_checkout_signin( null, true );
} );
jQuery( document ).on( 'click', '.ec_checkout_signin_cancel', function( e ) {
	e.preventDefault();
	wpeasycart_checkout_signin( false, false );
	jQuery( document.getElementById( 'ec_user_login_link' ) ).trigger( 'focus' );
} );

/* An email typed on guest checkout that has an account: a note offers the sign in ( the check only asks: nothing is locked ). One check
   for each email typed; the one-page checkout's own sign in opens with the email filled in. */
var wpeasycart_checkout_known_asked = {};
function wpeasycart_checkout_email_known( email ) {
	var note = document.getElementById( 'ec_checkout_email_known' );
	var step = wpeasycart_subscription_step();
	var nonce = note ? note.getAttribute( 'data-nonce' ) : null;
	if ( ! nonce && step && 'optional' === step.getAttribute( 'data-state' ) ) {
		nonce = step.getAttribute( 'data-nonce' );
	}
	if ( ! note || ! nonce ) {
		return;
	}
	email = String( email || '' ).trim().toLowerCase();
	if ( ! ec_validate_email( email ) || ( step && '1' === step.getAttribute( 'data-signin' ) ) ) {
		note.hidden = true;
		return;
	}
	var show = function( state ) {
		note.hidden = ( 'existing' !== state ) || ( String( jQuery( document.getElementById( 'ec_contact_email' ) ).val() || '' ).trim().toLowerCase() !== email );
	};
	if ( Object.prototype.hasOwnProperty.call( wpeasycart_checkout_known_asked, email ) ) {
		show( wpeasycart_checkout_known_asked[ email ] );
		return;
	}
	wpeasycart_checkout_known_asked[ email ] = '';
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		dataType: 'json',
		data: { action: 'ec_ajax_subscription_email', 'do': 'check', lock: 0, email: email, nonce: nonce },
		success: function( data ) {
			wpeasycart_checkout_known_asked[ email ] = ( data && data.state ) ? data.state : '';
			show( wpeasycart_checkout_known_asked[ email ] );
		}
	} );
}

jQuery( document ).on( 'change', '#ec_contact_email', function() {
	if ( ! this.readOnly ) {
		wpeasycart_checkout_email_known( this.value );
	}
} );
jQuery( document ).on( 'click', '.ec_checkout_email_known_signin', function( e ) {
	e.preventDefault();
	var email = String( jQuery( document.getElementById( 'ec_contact_email' ) ).val() || '' ).trim();
	var step = wpeasycart_subscription_step();
	if ( step && 'optional' === step.getAttribute( 'data-state' ) ) {
		wpeasycart_checkout_signin( true, true );
		return;
	}
	if ( 'function' === typeof ec_cart_toggle_login_v2 && ! jQuery( document.getElementById( 'ec_user_login_form' ) ).is( ':visible' ) ) {
		ec_cart_toggle_login_v2();
	}
	jQuery( document.getElementById( 'ec_cart_login_email' ) ).val( email );
	jQuery( document.getElementById( 'ec_cart_login_password' ) ).trigger( 'focus' );
} );

/* 6.0.5: no retyped email: a likely typo in a common address is offered instead ( "Did you mean name@gmail.com?" ). */
var wpeasycart_email_domains = [ 'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'ymail.com', 'hotmail.com', 'hotmail.co.uk', 'hotmail.fr', 'outlook.com', 'live.com', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'comcast.net', 'verizon.net', 'att.net', 'sbcglobal.net', 'bellsouth.net', 'cox.net', 'charter.net', 'protonmail.com', 'proton.me', 'gmx.com', 'gmx.de', 'gmx.net', 'web.de', 't-online.de', 'orange.fr', 'free.fr', 'wanadoo.fr', 'btinternet.com', 'sky.com', 'virginmedia.com', 'shaw.ca', 'rogers.com', 'sympatico.ca', 'bigpond.com', 'optusnet.com.au', 'xtra.co.nz', 'libero.it', 'mail.ru', 'yandex.ru' ];
function wpeasycart_email_distance( a, b ) {
	if ( Math.abs( a.length - b.length ) > 2 ) {
		return 3;
	}
	var row = [];
	for ( var j = 0; j <= b.length; j++ ) {
		row.push( j );
	}
	for ( var i = 1; i <= a.length; i++ ) {
		var prev = row[0];
		row[0] = i;
		for ( var k = 1; k <= b.length; k++ ) {
			var keep = row[ k ];
			row[ k ] = Math.min( row[ k ] + 1, row[ k - 1 ] + 1, prev + ( a.charAt( i - 1 ) === b.charAt( k - 1 ) ? 0 : 1 ) );
			prev = keep;
		}
	}
	return row[ b.length ];
}
function wpeasycart_email_fix( email ) {
	email = String( email || '' ).trim();
	var at = email.lastIndexOf( '@' );
	if ( at < 1 || at === email.length - 1 ) {
		return '';
	}
	var name = email.slice( 0, at );
	var domain = email.slice( at + 1 ).toLowerCase();
	if ( -1 !== wpeasycart_email_domains.indexOf( domain ) ) {
		return '';
	}
	var typed_dot = domain.lastIndexOf( '.' );
	var typed_tld = domain.slice( typed_dot + 1 );
	var tld_typo = /^(con|cmo|ocm|comm|cpm|cim|vom|xom|co|cm|nte|ner|nett)$/.test( typed_tld );
	var best = '';
	var best_distance = 3;
	for ( var d = 0; d < wpeasycart_email_domains.length; d++ ) {
		var known = wpeasycart_email_domains[ d ];
		var known_dot = known.lastIndexOf( '.' );
		/* the same ending ( a typo in the name ), or a mistyped .com / .net on the same name: never another country's address ( yahoo.co.jp ) */
		if ( known.slice( known_dot + 1 ) !== typed_tld && ! ( tld_typo && known.slice( 0, known_dot ) === domain.slice( 0, typed_dot ) ) ) {
			continue;
		}
		var distance = wpeasycart_email_distance( domain, known );
		if ( distance < best_distance && distance <= ( known.length > 7 ? 2 : 1 ) ) {
			best = known;
			best_distance = distance;
		}
	}
	if ( ! best && typed_dot > 0 ) {
		var tld = domain.replace( /\.(con|cmo|ocm|comm|cpm|cim|vom|xom)$/, '.com' ).replace( /\.(nte|nett)$/, '.net' );
		best = ( tld !== domain ) ? tld : '';
	}
	return best ? name + '@' + best : '';
}
function wpeasycart_email_suggestion( email ) {
	var holder = document.getElementById( 'ec_contact_email_suggest' );
	if ( ! holder ) {
		return '';
	}
	var fixed = ( false === email ) ? '' : wpeasycart_email_fix( email );
	if ( ! fixed ) {
		holder.hidden = true;
		holder.textContent = '';
		return '';
	}
	var words = String( holder.getAttribute( 'data-text' ) || 'Did you mean [email]?' ).split( '[email]' );
	holder.textContent = '';
	holder.appendChild( document.createTextNode( words[0] || '' ) );
	var button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'ec_checkout_email_suggest_use';
	button.setAttribute( 'data-email', fixed );
	button.textContent = fixed;
	holder.appendChild( button );
	holder.appendChild( document.createTextNode( words.slice( 1 ).join( '[email]' ) ) );
	holder.hidden = false;
	return fixed;
}
jQuery( document ).on( 'change blur', '#ec_contact_email', function() {
	if ( ! this.readOnly ) {
		wpeasycart_email_suggestion( this.value );
	}
} );
jQuery( document ).on( 'click', '.ec_checkout_email_suggest_use', function( e ) {
	e.preventDefault();
	var input = jQuery( document.getElementById( 'ec_contact_email' ) );
	input.val( this.getAttribute( 'data-email' ) || '' );
	wpeasycart_email_suggestion( false );
	input.trigger( 'focus' ).trigger( 'change' );
} );

/* 6.0.5: no retyped password: every new or sign in password can be shown ( .ec_password_toggle ). */
jQuery( document ).on( 'click', '.ec_password_toggle', function( e ) {
	e.preventDefault();
	var input = document.getElementById( this.getAttribute( 'aria-controls' ) || '' );
	if ( ! input ) {
		return;
	}
	var shown = ( 'password' === input.type );
	input.type = shown ? 'text' : 'password';
	this.setAttribute( 'aria-pressed', shown ? 'true' : 'false' );
	this.textContent = shown ? ( this.getAttribute( 'data-hide' ) || 'Hide' ) : ( this.getAttribute( 'data-show' ) || 'Show' );
	input.focus();
} );
jQuery( document ).on( 'keydown', '#ec_subscription_account #ec_user_contact_form input', function( e ) {
	if ( 'Enter' === e.key || 13 === e.which ) {
		e.preventDefault();
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).find( '.ec_cart_button_row > .ec_cart_button' ).first().trigger( 'click' );
	}
} );
jQuery( document ).on( 'click', '.ec_subscription_set_password_save', function( e ) {
	e.preventDefault();
	wpeasycart_subscription_set_password( jQuery( this ).closest( '.ec_subscription_set_password' ) );
} );
jQuery( document ).on( 'keydown', '.ec_subscription_set_password_input', function( e ) {
	if ( 'Enter' === e.key || 13 === e.which ) {
		e.preventDefault();
		wpeasycart_subscription_set_password( jQuery( this ).closest( '.ec_subscription_set_password' ) );
	}
} );

function update_subscription_totals( product_id, nonce, quiet ){
	var wpec_short = ( 'function' === typeof wpeasycart_subscription_short ) && wpeasycart_subscription_short();
	var billing_complete = quiet ? true : ( wpec_short ? wpeasycart_subscription_short_check() : ec_validate_address_block( 'ec_cart_billing' ) );
	var shipping_complete = true;

	if( jQuery( document.getElementById( 'ec_shipping_selector' ) ).is( ':checked' ) ) {
		shipping_complete = ec_validate_address_block( 'ec_cart_shipping' );
	}

	if ( billing_complete && shipping_complete ) {
		if ( ! quiet ) {
			jQuery( document.getElementById( 'ec_cart_address_loader' ) ).show();
		}
		var wpec_seq = ( 'undefined' !== typeof wpeasycart_subscription_short_seq ) ? ++wpeasycart_subscription_short_seq : 0;

		var shipping_selector = 0;
		if( jQuery( document.getElementById( 'ec_shipping_selector' ) ).length && jQuery( document.getElementById( 'ec_shipping_selector' ) ).is( ':checked' ) ){
			shipping_selector = 1;
		}

		var vat_registration_number = '';
		if( jQuery( document.getElementById( 'ec_cart_billing_vat_registration_number' ) ).length ){
			vat_registration_number = jQuery( document.getElementById( 'ec_cart_billing_vat_registration_number' ) ).val( );
		}

		var billing_first_name = ( jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).val() : '';
		var billing_last_name = ( jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).val() : '';
		var billing_company_name = ( jQuery( document.getElementById( 'ec_cart_billing_company_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_company_name' ) ).val() : '';
		var billing_address_line_1 = ( jQuery( document.getElementById( 'ec_cart_billing_address' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_address' ) ).val() : '';
		var billing_address_line_2 = ( jQuery( document.getElementById( 'ec_cart_billing_address2' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_address2' ) ).val() : '';
		var billing_city = ( jQuery( document.getElementById( 'ec_cart_billing_city' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_city' ) ).val() : '';
		var billing_state = jQuery( document.getElementById( 'ec_cart_billing_state' ) ).val();
		var billing_zip = ( jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).val() : '';
		var billing_country = jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val();
		if( '0' != billing_country && jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).length ){
			billing_state = jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).val();
		}
		var billing_phone = ( jQuery( document.getElementById( 'ec_cart_billing_phone' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_billing_phone' ) ).val() : '';

		var shipping_first_name = billing_first_name;
		var shipping_last_name = billing_last_name;
		var shipping_company_name = billing_company_name;
		var shipping_address_line_1 = billing_address_line_1;
		var shipping_address_line_2 = billing_address_line_2;
		var shipping_city = billing_city;
		var shipping_state = billing_state;
		var shipping_zip = billing_zip;
		var shipping_country = billing_country;
		var shipping_phone = billing_phone;

		if ( shipping_selector ) {
			shipping_state = jQuery( document.getElementById( 'ec_cart_shipping_state' ) ).val();
			shipping_country = jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).val();
			if( '0' != shipping_country && jQuery( document.getElementById( 'ec_cart_shipping_state_' + shipping_country ) ).length ){
				shipping_state = jQuery( document.getElementById( 'ec_cart_shipping_state_' + shipping_country ) ).val();
			}
			shipping_first_name = ( jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).val() : '';
			shipping_last_name = ( jQuery( document.getElementById( 'ec_cart_shipping_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_last_name' ) ).val() : '';
			shipping_company_name = ( jQuery( document.getElementById( 'ec_cart_shipping_company_name' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_company_name' ) ).val() : '';
			shipping_address_line_1 = ( jQuery( document.getElementById( 'ec_cart_shipping_address' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_address' ) ).val() : '';
			shipping_address_line_2 = ( jQuery( document.getElementById( 'ec_cart_shipping_address2' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_address2' ) ).val() : '';
			shipping_city = ( jQuery( document.getElementById( 'ec_cart_shipping_city' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_city' ) ).val() : '';
			shipping_zip = ( jQuery( document.getElementById( 'ec_cart_shipping_zip' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_zip' ) ).val() : '';
			shipping_phone = ( jQuery( document.getElementById( 'ec_cart_shipping_phone' ) ).length ) ? jQuery( document.getElementById( 'ec_cart_shipping_phone' ) ).val() : '';

		}

		var data = {
			action: 'ec_ajax_update_subscription_tax',
			product_id: product_id,
			shipping_selector: shipping_selector,
			nonce: nonce,

			vat_registration_number: vat_registration_number,

			billing_first_name: billing_first_name,
			billing_last_name: billing_last_name,
			billing_company_name: billing_company_name,
			billing_country: billing_country,
			billing_address: billing_address_line_1,
			billing_address2: billing_address_line_2,
			billing_city: billing_city,
			billing_state: billing_state,
			billing_zip: billing_zip,
			billing_phone: billing_phone,

			shipping_first_name: shipping_first_name,
			shipping_last_name: shipping_last_name,
			shipping_company_name: shipping_company_name,
			shipping_country: shipping_country,
			shipping_address: shipping_address_line_1,
			shipping_address2: shipping_address_line_2,
			shipping_city: shipping_city,
			shipping_state: shipping_state,
			shipping_zip: shipping_zip,
			shipping_phone: shipping_phone
		};

		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( response ) {
				if ( quiet ) {
					if ( wpec_seq !== wpeasycart_subscription_short_seq ) {
						return; /* a newer place was sent since */
					}
					try {
						wpeasycart_subscription_cart_update_totals( JSON.parse( response ) );
						if ( wpec_short ) {
							wpec_short.setAttribute( 'data-session-country', billing_country );
						}
					} catch ( wpec_totals_error ) {
						wpeasycart_subscription_short_sent = '';
					}
					return;
				}
				var data_arr = JSON.parse( response );
				wpeasycart_subscription_cart_update_totals( data_arr );
				jQuery( document.getElementById( 'ec_cart_billing_form' ) ).hide();
				jQuery( document.getElementById( 'ec_cart_billing_address_display' ) ).html( data_arr['billing_address_display'] );
				jQuery( document.getElementById( 'ec_cart_billing_locked' ) ).show();
				if ( jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).length ) {
					jQuery( document.getElementById( 'ec_cart_shipping_address_display' ) ).html( data_arr['shipping_address_display'] );
					jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).show();
				}
				jQuery( document.getElementById( 'ec_cart_subscription_end_form' ) ).show();
				jQuery( document.getElementById( 'ec_cart_address_loader' ) ).hide();
			},
			error: function() {
				if ( quiet ) {
					wpeasycart_subscription_short_sent = '';
				} else {
					jQuery( document.getElementById( 'ec_cart_address_loader' ) ).hide();
				}
			}
		} );
	}
}

function wpeasycart_subscription_cart_update_totals( data_arr ) {
	jQuery( document.getElementById( 'ec_cart_discount' ) ).html( data_arr['discount_total'] );
	jQuery( document.getElementById( 'ec_cart_discount_mobile' ) ).html( data_arr['discount_total'] );
	if ( data_arr['has_discount'] ) {
		jQuery( '.ec_cart_price_row_discount_total' ).removeClass( 'ec_no_discount' );
	} else {
		jQuery( '.ec_cart_price_row_discount_total' ).removeClass( 'ec_no_discount' ).addClass( 'ec_no_discount' );
	}
	if( jQuery( document.getElementById( 'ec_cart_tax' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_tax' ) ).html( data_arr['tax_total'] );
		jQuery( document.getElementById( 'ec_cart_tax_mobile' ) ).html( data_arr['tax_total'] );
	}
	if( jQuery( document.getElementById( 'ec_cart_vat' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_vat' ) ).html( data_arr['vat_total'] );
		jQuery( document.getElementById( 'ec_cart_vat_mobile' ) ).html( data_arr['vat_total'] );
	}
	if( jQuery( document.getElementById( 'ec_cart_vat_rate' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_vat_rate' ) ).html( data_arr['vat_rate_formatted'] );
	}
	if( jQuery( document.getElementById( 'ec_cart_vat_rate_mobile' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_vat_rate_mobile' ) ).html( data_arr['vat_rate_formatted'] );
	}
	if( jQuery( document.getElementById( 'ec_cart_hst' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_hst' ) ).html( data_arr['hst_total'] );
		jQuery( document.getElementById( 'ec_cart_hst_mobile' ) ).html( data_arr['hst_total'] );
		jQuery( document.getElementById( 'ec_cart_hst_rate' ) ).html( data_arr['hst_rate'] );
		jQuery( document.getElementById( 'ec_cart_hst_rate_mobile' ) ).html( data_arr['hst_rate'] );
		if( data_arr['hst_rate'] > 0 ){
			jQuery( document.getElementById( 'ec_cart_hst_row' ) ).show( );
			jQuery( document.getElementById( 'ec_cart_hst_row_mobile' ) ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_hst_row' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_hst_row_mobile' ) ).hide( );
		}
	}

	if( jQuery( document.getElementById( 'ec_cart_pst' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_pst' ) ).html( data_arr['pst_total'] );
		jQuery( document.getElementById( 'ec_cart_pst_mobile' ) ).html( data_arr['pst_total'] );
		jQuery( document.getElementById( 'ec_cart_pst_rate' ) ).html( data_arr['pst_rate'] );
		jQuery( document.getElementById( 'ec_cart_pst_rate_mobile' ) ).html( data_arr['pst_rate'] );
		if ( data_arr['pst_label'] ) { // QST for Quebec addresses
			jQuery( document.getElementById( 'ec_cart_pst_label' ) ).text( data_arr['pst_label'] );
			jQuery( document.getElementById( 'ec_cart_pst_label_mobile' ) ).text( data_arr['pst_label'] );
		}
		if( data_arr['pst_rate'] > 0 ){
			jQuery( document.getElementById( 'ec_cart_pst_row' ) ).show( );
			jQuery( document.getElementById( 'ec_cart_pst_row_mobile' ) ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_pst_row' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_pst_row_mobile' ) ).hide( );
		}
	}

	if( jQuery( document.getElementById( 'ec_cart_gst' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_gst' ) ).html( data_arr['gst_total'] );
		jQuery( document.getElementById( 'ec_cart_gst_mobile' ) ).html( data_arr['gst_total'] );
		jQuery( document.getElementById( 'ec_cart_gst_rate' ) ).html( data_arr['gst_rate'] );
		jQuery( document.getElementById( 'ec_cart_gst_rate_mobile' ) ).html( data_arr['gst_rate'] );
		if( data_arr['gst_rate'] > 0 ){
			jQuery( document.getElementById( 'ec_cart_gst_row' ) ).show( );
			jQuery( document.getElementById( 'ec_cart_gst_row_mobile' ) ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_gst_row' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_gst_row_mobile' ) ).hide( );
		}
	}

	if( jQuery( document.getElementById( 'ec_cart_shipping' ) ).length ){
		jQuery( document.getElementById( 'ec_cart_shipping' ) ).html( data_arr['shipping_total'] );
		jQuery( document.getElementById( 'ec_cart_shipping_mobile' ) ).html( data_arr['shipping_total'] );
	}

	if ( jQuery( document.getElementById( 'ec_cart_subscription_shipping_methods' ) ).length ) {
		jQuery( document.getElementById( 'ec_cart_subscription_shipping_methods' ) ).html( data_arr['shipping_methods'] );
	}

	jQuery( document.getElementById( 'ec_cart_total' ) ).html( data_arr['grand_total'] );
	jQuery( document.getElementById( 'ec_cart_total_mobile' ) ).html( data_arr['grand_total'] );
	/* 6.0.3: what the subscription renews at when the code comes off the first payment only ( plain text; '' hides it ). */
	if ( 'undefined' != typeof data_arr['renewal_note'] ) {
		jQuery( '.ec_subscription_renewal' ).text( data_arr['renewal_note'] ).css( 'display', ( '' == data_arr['renewal_note'] ) ? 'none' : '' );
	}
	if ( 'undefined' != typeof data_arr['trial_text'] && '' != data_arr['trial_text'] ) { /* 6.0.3: the free trial's first payment, with the code */
		jQuery( '.ec_subscription_trial_text' ).text( data_arr['trial_text'] );
	}

	if( Number( data_arr['has_tax'] ) == 1 ){ 
		jQuery( '#ec_cart_tax_row' ).show( ); 
		jQuery( '#ec_cart_tax_row_mobile' ).show( ); 
	}else{ 
		jQuery( '#ec_cart_tax_row' ).hide( );
		jQuery( '#ec_cart_tax_row_mobile' ).hide( );
	}
	if( Number( data_arr['has_vat'] ) == 1 ){
		jQuery( '#ec_cart_vat_row' ).show( );
		jQuery( '#ec_cart_vat_row_mobile' ).show( );
	}else{
		jQuery( '#ec_cart_vat_row' ).hide( );
		jQuery( '#ec_cart_vat_row_mobile' ).hide( );
	}
}

/* 6.0.5: the subscription page's choices under the item in its summary ( wp_easycart_subscription_choices ). The usage level is a
   line ( "750 tasks per month · Change" ), the summary of a <details>: a click opens its list ( the select's own picker where the
   browser has one ) and picking another level opens the page again with it. A level moved with the arrow keys on a closed list ( each
   key changes it ) waits for the Update button, so a keyboard can look through the list. Escape, or the focus leaving, puts the line
   back. The billing period is a row of links ( pills or a switch ). Nothing here runs on load: the page may be drawn by AJAX. */
function wpeasycart_subscription_pick_close( form, focus ) {
	var box = form.find( '.ec_subscription_pick_box' ), select = form.find( '.ec_subscription_pick_select' );
	if ( undefined !== select.data( 'wpecStart' ) ) {
		select.val( select.data( 'wpecStart' ) );
	}
	form.removeClass( 'is-keyed' );
	if ( box.length ) {
		box[0].open = false;
	}
	if ( focus ) {
		form.find( '.ec_subscription_pick_line' ).trigger( 'focus' );
	}
}
jQuery( document ).on( 'click', '.ec_subscription_pick_line', function( e ) {
	var form = jQuery( this ).closest( '.ec_subscription_pick' ), box = form.find( '.ec_subscription_pick_box' ), select = form.find( '.ec_subscription_pick_select' );
	if ( ! box.length || ! select.length ) {
		return;
	}
	e.preventDefault();
	if ( box[0].open ) {
		wpeasycart_subscription_pick_close( form, true );
		return;
	}
	jQuery( '.ec_subscription_pick_box[open]' ).each( function() {
		wpeasycart_subscription_pick_close( jQuery( this ).closest( '.ec_subscription_pick' ), false );
	} );
	form.addClass( 'is-scripted' ).removeClass( 'is-keyed' );
	box[0].open = true;
	select.data( 'wpecStart', select.val() ).trigger( 'focus' );
	try {
		if ( select[0].showPicker ) {
			select[0].showPicker();
		}
	} catch ( err ) {}
} );
jQuery( document ).on( 'keydown', '.ec_subscription_pick_select', function( e ) {
	var form = jQuery( this ).closest( '.ec_subscription_pick' );
	if ( 'Escape' === e.key ) {
		wpeasycart_subscription_pick_close( form, true );
		e.preventDefault();
	} else if ( /^(ArrowUp|ArrowDown|ArrowLeft|ArrowRight|Home|End|PageUp|PageDown)$/.test( e.key ) || ( 1 === String( e.key ).length ) ) {
		form.addClass( 'is-keyed' );
	}
} );
jQuery( document ).on( 'change', '.ec_subscription_pick_select', function() {
	var form = jQuery( this ).closest( '.ec_subscription_pick' );
	if ( ! form.hasClass( 'is-scripted' ) || form.hasClass( 'is-keyed' ) || jQuery( this ).val() === jQuery( this ).data( 'wpecStart' ) ) {
		return; /* the Update button */
	}
	form.addClass( 'is-busy' );
	form[0].submit();
} );
jQuery( document ).on( 'submit', 'form.ec_subscription_pick', function() {
	jQuery( this ).addClass( 'is-busy' );
} );
jQuery( document ).on( 'focusout', 'form.ec_subscription_pick', function() {
	var form = this;
	setTimeout( function() {
		var box = jQuery( form ).find( '.ec_subscription_pick_box' );
		if ( box.length && box[0].open && jQuery( form ).hasClass( 'is-scripted' ) && ! jQuery( form ).hasClass( 'is-busy' ) && ! form.contains( document.activeElement ) ) {
			wpeasycart_subscription_pick_close( jQuery( form ), false );
		}
	}, 0 );
} );
jQuery( document ).on( 'click', '.ec_subscription_pick_links a', function() {
	jQuery( this ).closest( '.ec_subscription_pick' ).addClass( 'is-busy' );
} );
window.addEventListener( 'pageshow', function() {
	jQuery( '.ec_subscription_pick.is-busy' ).each( function() {
		jQuery( this ).removeClass( 'is-busy is-keyed' );
		wpeasycart_subscription_pick_close( jQuery( this ), false );
	} );
} );

/* 6.0.5: the short subscription checkout ( ec_cart_subscription.php #ec_cart_billing_form[data-short], the class wp_easycart_subscription_short;
   Matt: as simple as possible, for a subscription that ships nothing ). The name, the country, the state or province only where the country has
   a list, the postcode only where the country uses one, and the payment on the same screen ( no Save & Continue ). The totals follow the place
   as it is typed ( the tax, through the subscription tax call, quietly ). The street, town, company and phone are hidden fields keeping what the
   session or the account holds; the street and town are emptied while another country than theirs is chosen ( data-kept-country ). */
var wpeasycart_subscription_short_timer = null;
var wpeasycart_subscription_short_seq = 0;
var wpeasycart_subscription_short_sent = '';

function wpeasycart_subscription_short() {
	var form = document.getElementById( 'ec_cart_billing_form' );
	return ( form && '1' === form.getAttribute( 'data-short' ) ) ? form : null;
}

function wpeasycart_subscription_short_place() {
	var form = wpeasycart_subscription_short();
	if ( ! form ) {
		return;
	}
	var holder = jQuery( form );
	var country = String( jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val() || '' );
	var list = ( '' !== country && '0' !== country ) ? document.getElementById( 'ec_cart_billing_state_' + country ) : null;
	var nozip = ( 'function' === typeof wpeasycart_postcode_none ) && wpeasycart_postcode_none( country );
	holder.find( '.ec_subscription_short_state' ).prop( 'hidden', ! list );
	holder.find( '.ec_subscription_short_zip' ).prop( 'hidden', nozip );
	holder.find( '.ec_subscription_short_place' ).attr( 'data-cells', 1 + ( list ? 1 : 0 ) + ( nozip ? 0 : 1 ) );
	if ( ! list && 'function' === typeof ec_hide_error ) {
		ec_hide_error( 'ec_cart_billing_state' );
	}
	if ( nozip && 'function' === typeof ec_hide_error ) {
		ec_hide_error( 'ec_cart_billing_zip' );
	}
	var kept_country = form.getAttribute( 'data-kept-country' ) || '';
	holder.find( 'input[data-wpec-kept="place"]' ).each( function() {
		/* what the page was printed with, kept on the element ( a hidden input's value is its attribute, so it cannot be read back ) */
		if ( undefined === this.wpecKeptValue ) {
			this.wpecKeptValue = this.value;
		}
		this.value = ( '' === kept_country || kept_country === country ) ? this.wpecKeptValue : '';
	} );
}

/* What Place order needs from the short form ( ec_validate_address_block() without the street, town, company and phone ). */
function wpeasycart_subscription_short_check() {
	var prefix = 'ec_cart_billing', ok = true;
	var mark = function( field, good ) {
		if ( good ) {
			ec_hide_error( field );
		} else {
			ok = false;
			ec_show_error( field );
		}
	};
	var country = jQuery( document.getElementById( prefix + '_country' ) ).val();
	mark( prefix + '_country', ec_validate_select( country ) );
	mark( prefix + '_first_name', ec_validate_text( jQuery( document.getElementById( prefix + '_first_name' ) ).val() ) );
	mark( prefix + '_last_name', ec_validate_text( jQuery( document.getElementById( prefix + '_last_name' ) ).val() ) );
	var list = jQuery( document.getElementById( prefix + '_state_' + country ) );
	if ( list.length && ! wpeasycart_region_optional( country ) ) {
		mark( prefix + '_state', ec_validate_select( list.val() ) );
	} else {
		ec_hide_error( prefix + '_state' );
	}
	mark( prefix + '_zip', ec_validate_zip_code( jQuery( document.getElementById( prefix + '_zip' ) ).val(), country ) );
	return ok;
}

/* The place as the tax call sees it ( a new request only when it changed ). */
function wpeasycart_subscription_short_key() {
	var country = String( jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val() || '' );
	var state = jQuery( document.getElementById( 'ec_cart_billing_state_' + country ) ).length ? jQuery( document.getElementById( 'ec_cart_billing_state_' + country ) ).val() : '';
	return [ country, state, String( jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).val() || '' ).trim(), String( jQuery( document.getElementById( 'ec_cart_billing_vat_registration_number' ) ).val() || '' ).trim() ].join( '|' );
}

function wpeasycart_subscription_short_refresh( wait ) {
	var form = wpeasycart_subscription_short();
	if ( ! form ) {
		return;
	}
	clearTimeout( wpeasycart_subscription_short_timer );
	wpeasycart_subscription_short_timer = setTimeout( function() {
		var key = wpeasycart_subscription_short_key();
		if ( key === wpeasycart_subscription_short_sent || ! ec_validate_select( key.split( '|' )[0] ) ) {
			return;
		}
		wpeasycart_subscription_short_sent = key;
		update_subscription_totals( form.getAttribute( 'data-product' ), form.getAttribute( 'data-nonce' ), true );
	}, ( 'number' === typeof wait ) ? wait : 700 );
}

jQuery( document ).on( 'change', '#ec_cart_billing_form[data-short] .ec_subscription_short_place select, #ec_cart_billing_form[data-short] .ec_subscription_short_place input, #ec_cart_billing_form[data-short] #ec_cart_billing_vat_registration_number', function() {
	wpeasycart_subscription_short_place();
	wpeasycart_subscription_short_refresh( 0 );
} );
jQuery( document ).on( 'input', '#ec_cart_billing_form[data-short] .ec_subscription_short_zip input, #ec_cart_billing_form[data-short] #ec_cart_billing_vat_registration_number', function() {
	wpeasycart_subscription_short_refresh( 700 );
} );
jQuery( function() {
	var form = wpeasycart_subscription_short();
	if ( ! form ) {
		return;
	}
	wpeasycart_subscription_short_place();
	var country = String( jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val() || '' );
	if ( ec_validate_select( country ) && country !== ( form.getAttribute( 'data-session-country' ) || '' ) ) {
		wpeasycart_subscription_short_refresh( 0 ); /* the page's totals were worked out without this country ( the store's default, or the account's ) */
	} else {
		wpeasycart_subscription_short_sent = wpeasycart_subscription_short_key();
	}
} );

function ec_apply_subscription_coupon( product_id, manufacturer_id, nonce, is_mobile = false ){
	
	jQuery( document.getElementById( 'ec_apply_coupon' ) ).hide( );
	jQuery( document.getElementById( 'ec_applying_coupon' ) ).show( );
	jQuery( document.getElementById( 'ec_apply_coupon_mobile' ) ).hide( );
	jQuery( document.getElementById( 'ec_applying_coupon_mobile' ) ).show( );
	
	var coupon_code = ( is_mobile ) ? jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val() : jQuery( document.getElementById( 'ec_coupon_code' ) ).val();
	jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val( coupon_code );
	jQuery( document.getElementById( 'ec_coupon_code' ) ).val(  coupon_code);
	
	var data = {
		action: 'ec_ajax_redeem_subscription_coupon_code',
		product_id: product_id,
		manufacturer_id: manufacturer_id,
		couponcode: coupon_code,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			jQuery( document.getElementById( 'ec_apply_coupon' ) ).show( );
			jQuery( document.getElementById( 'ec_applying_coupon' ) ).hide( );
			jQuery( document.getElementById( 'ec_apply_coupon_mobile' ) ).show( );
			jQuery( document.getElementById( 'ec_applying_coupon_mobile' ) ).hide( );

			var data_arr = JSON.parse( data );

			if( data_arr['coupon_status'] == "valid" ){
				jQuery( document.getElementById( 'ec_coupon_error' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_success' ) ).html( data_arr['coupon_message'] ).show( );
				jQuery( document.getElementById( 'ec_coupon_error_mobile' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_success_mobile' ) ).html( data_arr['coupon_message'] ).show( );
			}else{
				jQuery( document.getElementById( 'ec_coupon_success' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_error' ) ).html( data_arr['coupon_message'] ).show( );
				jQuery( document.getElementById( 'ec_coupon_success_mobile' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_error_mobile' ) ).html( data_arr['coupon_message'] ).show( );
			}

			wpeasycart_subscription_cart_update_totals( data_arr );
		}
	} );
}

function ec_apply_gift_card( nonce, is_mobile = false ){
	jQuery( document.getElementById( 'ec_apply_gift_card' ) ).hide( );
	if ( jQuery( '#ec_applying_gift_card_v2' ).length ) {
		jQuery( '#ec_applying_gift_card_v2' ).addClass( 'ec_cart_button_working_active' );
		jQuery( '#ec_applying_gift_card_v2_mobile' ).addClass( 'ec_cart_button_working_active' );
		jQuery( document.getElementById( 'ec_apply_gift_card_mobile' ) ).hide();
	} else {
		jQuery( document.getElementById( 'ec_applying_gift_card' ) ).show( );
	}
	
	var gift_card = ( is_mobile ) ? jQuery( document.getElementById( 'ec_gift_card_mobile' ) ).val() : jQuery( document.getElementById( 'ec_gift_card' ) ).val();
	if ( is_mobile ) {
		jQuery( document.getElementById( 'ec_gift_card' ) ).val( gift_card );
	} else {
		if ( jQuery( document.getElementById( 'ec_gift_card_mobile' ) ).length ) {
			jQuery( document.getElementById( 'ec_gift_card_mobile' ) ).val( gift_card );
		}
	}
	
	var data = {
		action: 'ec_ajax_redeem_gift_card',
		ec_v3_24: 'true',
		giftcard: gift_card,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			jQuery( document.getElementById( 'ec_apply_gift_card' ) ).show( );
			if ( jQuery( '#ec_applying_gift_card_v2' ).length ) {
				jQuery( '#ec_applying_gift_card_v2' ).removeClass( 'ec_cart_button_working_active' );
				jQuery( '#ec_applying_gift_card_v2_mobile' ).removeClass( 'ec_cart_button_working_active' );
				jQuery( document.getElementById( 'ec_apply_gift_card_mobile' ) ).show( );
			} else {
				jQuery( document.getElementById( 'ec_applying_gift_card' ) ).hide( );
			}

			// Get Response Data
			var response_obj = JSON.parse( data );
			
			// Update Cart
			ec_update_cart( response_obj );
			
			// Update Gift Card Info
			if( response_obj.is_giftcard_valid ){
				jQuery( document.getElementById( 'ec_gift_card_error' ) ).hide( );
				jQuery( document.getElementById( 'ec_gift_card_success' ) ).html( response_obj.giftcard_message ).show( );
				if ( jQuery( '#ec_applying_gift_card_v2' ).length ) {
					jQuery( document.getElementById( 'ec_gift_card_error_mobile' ) ).hide( );
					jQuery( document.getElementById( 'ec_gift_card_success_mobile' ) ).html( response_obj.giftcard_message ).show( );
				}
			}else{
				jQuery( document.getElementById( 'ec_gift_card_success' ) ).hide( );
				jQuery( document.getElementById( 'ec_gift_card_error' ) ).html( response_obj.giftcard_message ).show( );
				if ( jQuery( '#ec_applying_gift_card_v2' ).length ) {
					jQuery( document.getElementById( 'ec_gift_card_success_mobile' ) ).hide( );
					jQuery( document.getElementById( 'ec_gift_card_error_mobile' ) ).html( response_obj.giftcard_message ).show( );
				}
			}
		} 
	} );
}

function ec_estimate_shipping( nonce ){
	
	jQuery( document.getElementById( 'ec_estimate_shipping' ) ).hide( );
	jQuery( document.getElementById( 'ec_estimating_shipping' ) ).show( );
	
	var data = {
		action: 'ec_ajax_estimate_shipping',
		ec_v3_24: 'true',
		zipcode: jQuery( document.getElementById( 'ec_estimate_zip' ) ).val( ),
		country: jQuery( document.getElementById( 'ec_estimate_country' ) ).val( ),
		nonce: nonce
	};
	
	jQuery.ajax({
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			jQuery( document.getElementById( 'ec_estimate_shipping' ) ).show( );
			jQuery( document.getElementById( 'ec_estimating_shipping' ) ).hide( );
			
			// Get Response Data
			var response_obj = JSON.parse( data );
			
			// Update Cart
			ec_update_cart( response_obj );
			
			// Show the Shipping Row if Hidden
			jQuery( document.getElementById( 'ec_cart_shipping_row' ) ).show( );
		} 
	} );
}

function ec_update_cart( response_obj ){
	
	if( response_obj.cart.length == 0 ){
		ec_reload_cart( );
		
	}else{
		/* 6.0.4: each line's quantity limits follow the cart, and a quantity the store did not keep says why ( quantity_notice ). */
		// Update Cart Data
		for( var i=0; i<response_obj.cart.length; i++ ){
			wpeasycart_cart_quantity_limits( response_obj.cart[i] );
			jQuery( document.getElementById( 'ec_cartitem_details_' + response_obj.cart[i].id ) ).find( '.ec_details_price_promo_discount' ).remove();
			jQuery( document.getElementById( 'ec_cartitem_details_' + response_obj.cart[i].id ) ).find( '.ec_details_price_coupon_discount' ).remove();
			if ( '' != response_obj.cart[i].promo_message ) {
				jQuery( document.getElementById( 'ec_cartitem_details_' + response_obj.cart[i].id ) ).append( response_obj.cart[i].promo_message );
			}
			jQuery( document.getElementById( 'ec_quantity_' + response_obj.cart[i].id ) ).val( response_obj.cart[i].quantity );
			jQuery( document.getElementById( 'ec_cartitem_price_' + response_obj.cart[i].id ) ).html( response_obj.cart[i].unit_price );
			jQuery( document.getElementById( 'ec_cartitem_price_mobile_' + response_obj.cart[i].id ) ).html( response_obj.cart[i].unit_price ); /* 6.0.4: the one-page cart's phone price */
			jQuery( document.getElementById( 'ec_cartitem_total_' + response_obj.cart[i].id ) ).html( response_obj.cart[i].total_price );

			jQuery( '.ec_cartitem_' + response_obj.cart[i].id + ' > .ec_cart_price_row_total_v2' ).html( response_obj.cart[i].total_price );

			if( response_obj.cart[i].allow_backorders == "1" && response_obj.cart[i].use_optionitem_quantity_tracking == "1" && Number( response_obj.cart[i].quantity ) > Number( response_obj.cart[i].optionitem_stock_quantity ) ){
				jQuery( document.getElementById( 'ec_cartitem_backorder_' + response_obj.cart[i].id ) ).show( );
			
			}else if( response_obj.cart[i].allow_backorders == "1" && response_obj.cart[i].use_optionitem_quantity_tracking == "1" && Number( response_obj.cart[i].quantity ) <= Number( response_obj.cart[i].optionitem_stock_quantity ) ){
				jQuery( document.getElementById( 'ec_cartitem_backorder_' + response_obj.cart[i].id ) ).hide( );
			
			}else if( response_obj.cart[i].allow_backorders == "1" && response_obj.cart[i].use_optionitem_quantity_tracking == "0" && Number( response_obj.cart[i].quantity ) > Number( response_obj.cart[i].stock_quantity ) ){
				jQuery( document.getElementById( 'ec_cartitem_backorder_' + response_obj.cart[i].id ) ).show( );
			
			}else if( response_obj.cart[i].allow_backorders == "1" && response_obj.cart[i].use_optionitem_quantity_tracking == "0" && Number( response_obj.cart[i].quantity ) <= Number( response_obj.cart[i].stock_quantity ) ){
				jQuery( document.getElementById( 'ec_cartitem_backorder_' + response_obj.cart[i].id ) ).hide( );
			
			}
			if ( jQuery( document.getElementById( 'ec_cartitem_shipping_restriction_' + response_obj.cart[i].id ) ).length && '1' == response_obj.cart[i].shipping_restricted ) {
				jQuery( document.getElementById( 'ec_cartitem_shipping_restriction_' + response_obj.cart[i].id ) ).show( );
			} else if ( jQuery( document.getElementById( 'ec_cartitem_shipping_restriction_' + response_obj.cart[i].id ) ).length ) {
				jQuery( document.getElementById( 'ec_cartitem_shipping_restriction_' + response_obj.cart[i].id ) ).hide( );
			}
		}
		
		if ( response_obj.quantity_notice && response_obj.quantity_notice.message ) {
			wpeasycart_cart_quantity_notes_clear();
			wpeasycart_cart_quantity_note( response_obj.quantity_notice.id, response_obj.quantity_notice.message, ( 'min' === response_obj.quantity_notice.reason ) ? 'min' : 'max' );
		}

		// Update Cart Totals
		jQuery( document.getElementById( 'ec_cart_subtotal' ) ).html( response_obj.order_totals.sub_total );
		if ( jQuery( '.paylater_message_v2' ).length ) {
			if ( Number( jQuery( '.paylater_message_v2' ).attr( 'data-min-price' ) ) <= Number( response_obj.order_totals.sub_total_amt ) ) {
				jQuery( '.paylater_message_v2' ).hide();
			} else {
				jQuery( '.paylater_message_v2' ).show();
			}
		}
		if ( jQuery( '#wpec-payment-method-messaging-element' ).length ) {
			ec_cart_stripe_paylater_messaging_v2( Math.round( response_obj.order_totals.sub_total_amt * 100 ) );
			/* 6.0.4: without a minimum ( no .paylater_message_v2 ) the message stays; it was hidden on every change. */
			if ( ! jQuery( '.paylater_message_v2' ).length || Number( jQuery( '.paylater_message_v2' ).attr( 'data-min-price' ) ) <= Number( response_obj.order_totals.sub_total_amt ) ) {
				jQuery( '#wpec-payment-method-messaging-element' ).show();
			} else {
				jQuery( '#wpec-payment-method-messaging-element' ).hide();
			}
		}
		if ( jQuery( '.ec_cart_table_subtotal_amount' ).length ) {
			jQuery( '.ec_cart_table_subtotal_amount' ).html( response_obj.order_totals.sub_total );
		}
		/* 6.0.4: the one-page cart screen's discounts and estimated total ( ec_cartpage::cart_screen_totals() ). */
		if ( jQuery( '.ec_cart_table_estimate_amount' ).length && 'undefined' !== typeof response_obj.order_totals.estimated_total ) {
			jQuery( '.ec_cart_table_discount_amount' ).html( response_obj.order_totals.cart_discount );
			jQuery( '.ec_cart_table_discount_row' ).toggle( '1' == response_obj.order_totals.cart_discount_show );
			jQuery( '.ec_cart_table_estimate_amount' ).html( response_obj.order_totals.estimated_total );
			jQuery( '.ec_cart_table_estimate_row' ).toggle( '1' == response_obj.order_totals.estimated_total_show );
		}
		jQuery( document.getElementById( 'ec_cart_tax' ) ).html( response_obj.order_totals.tax_total );
		if( response_obj.order_totals.has_tax == '1' ){
			jQuery( document.getElementById( 'ec_cart_tax' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_tax' ) ).parent( ).hide( );
		}
		jQuery( document.getElementById( 'ec_cart_shipping' ) ).html( response_obj.order_totals.shipping_total );
		jQuery( document.getElementById( 'ec_cart_duty' ) ).html( response_obj.order_totals.duty_total );
		if( response_obj.order_totals.has_duty == '1' ){
			jQuery( document.getElementById( 'ec_cart_duty' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_duty' ) ).parent( ).hide( );
		}
		jQuery( document.getElementById( 'ec_cart_vat' ) ).html( response_obj.order_totals.vat_total );
		if( response_obj.order_totals.has_vat == '1' ){
			jQuery( document.getElementById( 'ec_cart_vat' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_vat' ) ).parent( ).hide( );
		}
        if( jQuery( document.getElementById( 'ec_cart_vat_rate' ) ).length ){
            jQuery( document.getElementById( 'ec_cart_vat_rate' ) ).html( response_obj.order_totals.vat_rate_formatted );
        }
		jQuery( document.getElementById( 'ec_cart_gst' ) ).html( response_obj.order_totals.gst_total );
		if( response_obj.order_totals.has_gst == '1' ){
			jQuery( document.getElementById( 'ec_cart_gst' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_gst' ) ).parent( ).hide( );
		}
		jQuery( document.getElementById( 'ec_cart_hst' ) ).html( response_obj.order_totals.hst_total );
		if( response_obj.order_totals.has_hst == '1' ){
			jQuery( document.getElementById( 'ec_cart_hst' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_hst' ) ).parent( ).hide( );
		}
		jQuery( document.getElementById( 'ec_cart_pst' ) ).html( response_obj.order_totals.pst_total );
		/* 6.0.5: each Canadian tax row's name ( QST in Quebec ) and rate follow the address ( ec_cart_totals.php's spans ). */
		jQuery.each( [ 'gst', 'pst', 'hst' ], function( i, kind ) {
			if ( 'undefined' != typeof response_obj.order_totals[ kind + '_label' ] ) {
				jQuery( '.ec_cart_' + kind + '_label' ).text( response_obj.order_totals[ kind + '_label' ] );
				jQuery( '.ec_cart_' + kind + '_rate' ).text( response_obj.order_totals[ kind + '_rate' ] );
			}
		} );
		if( response_obj.order_totals.has_pst == '1' ){
			jQuery( document.getElementById( 'ec_cart_pst' ) ).parent( ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_pst' ) ).parent( ).hide( );
		}
        if( jQuery( document.getElementById( 'ec_cart_tip' ) ).length ){
			jQuery( document.getElementById( 'ec_cart_tip' ) ).html( response_obj.order_totals.tip_total );
		}
		if ( response_obj.order_totals.fees ) {
			jQuery( '.ec_cart_price_row_fee' ).remove();
			var fee_html = '';
			for ( var j=0; j < response_obj.order_totals.fees.length; j++ ) {
				fee_html += '<div class="ec_cart_price_row ec_cart_price_row_fee"><div class="ec_cart_price_row_label">' + response_obj.order_totals.fees[j].fee_label + '</div><div class="ec_cart_price_row_total" id="ec_cart_fee_' + response_obj.order_totals.fees[j].fee_id + '">' + response_obj.order_totals.fees[j].fee_total + '</div></div>';
			}
			if ( '' != fee_html ) {
				jQuery( fee_html ).insertBefore( '.ec_cart_price_row.ec_order_total' );
			}
		}
		jQuery( document.getElementById( 'ec_cart_discount' ) ).html( response_obj.order_totals.discount_total );
		jQuery( '.ec_cart_promotions_list.ec_cart_promotions_discount' ).remove();
		if ( '' != response_obj.order_totals.discount_message ) {
			jQuery( '.ec_cart_price_row_discount_total > .ec_cart_price_row_label' ).append( response_obj.order_totals.discount_message );
		}
		jQuery( '.ec_cart_promotions_list.ec_cart_shipping_discount' ).remove();
		if ( '' != response_obj.order_totals.shipping_discount_message ) {
			jQuery( '.ec_cart_price_row_shipping_total > .ec_cart_price_row_label' ).append( response_obj.order_totals.shipping_discount_message );
		}
		jQuery( document.getElementById( 'ec_cart_total' ) ).html( response_obj.order_totals.grand_total );
		jQuery( document.getElementById( 'ec_cart_mobile_total' ) ).html( response_obj.order_totals.grand_total );
		
		if ( jQuery( '.wp-easycart-widget-cart-quantity' ).length ) {
			jQuery( '.wp-easycart-widget-cart-quantity' ).html( response_obj.items_total );
		}
		
		jQuery( ".ec_cart_items_total" ).html( response_obj.items_total );
		jQuery( ".ec_cart_price_total" ).html( response_obj.order_totals.grand_total );
		
		// Hide/Show Discount
		if( response_obj.has_discount == '1' ){
			jQuery( '.ec_no_discount' ).show( );
			jQuery( '.ec_has_discount' ).show( );
		}else{
			jQuery( '.ec_no_discount' ).hide( );
			jQuery( '.ec_has_discount' ).hide( );
		}
		
		// Hide/Show Backorder
		if( response_obj.has_backorder ){
			jQuery( document.getElementById( 'ec_cart_backorder_message' ) ).show( );
		}else{
			jQuery( document.getElementById( 'ec_cart_backorder_message' ) ).hide( );
		}
        
        // Hide/Show Minimum ( 6.0.2: never for an empty cart, which has nothing to top up )
        if( jQuery( '.ec_minimum_purchase_box' ).length ){
            if( Number( response_obj.items_total ) > 0 && Number( response_obj.order_totals.sub_total_amt ) < Number( jQuery( '.ec_minimum_purchase_box' ).attr( 'data-min-cart' ) ) ){
                jQuery( '.ec_minimum_purchase_box' ).show( );
                if( jQuery( document.getElementById( 'sq-walletbox' ) ).length ){
                    jQuery( document.getElementById( 'sq-walletbox' ) ).hide( );
                }
            }else{
                jQuery( '.ec_minimum_purchase_box' ).hide( );
                if( jQuery( document.getElementById( 'sq-walletbox' ) ).length ){
                    jQuery( document.getElementById( 'sq-walletbox' ) ).show( );
                }
            }
        }
        
        // Stripe Update Wallet
        if( jQuery( document.getElementById( 'ec-stripe-wallet-button' ) ).length && ! jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).length ){
            jQuery( document.getElementById( 'ec-stripe-wallet-button' ) ).replaceWith( response_obj.stripe_wallet );
        }
	
		// PayPal Express Update
		if( response_obj.paypal_express_button ){
			jQuery( document.getElementById( 'paypal-button-container' ) ).find( '.paypal-button' ).remove( );
			jQuery( document.getElementById( 'paypal-button-container' ) ).append( response_obj.paypal_express_button );
		}

		if ( jQuery( '#ec_cart_onepage_payment' ).length ) {
			var header_html = jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_header' );
			if ( jQuery( '#ec_cart_onepage_payment' ).length && jQuery( '.ec_cart_payment_table' ).length && response_obj.order_totals.grand_total_amt <= 0 ) {
				if ( jQuery( '.ec_cart_payment_table' ).length ) {
					jQuery( '.ec_cart_payment_table' ).remove();
				}
				jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).html( '<div id="ec_cart_shipping_loader"><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div></div>' ).prepend( header_html );
				ec_reload_cart();
			} else if ( jQuery( '#ec_cart_onepage_payment' ).length && ! jQuery( '.ec_cart_payment_table' ).length && response_obj.order_totals.grand_total_amt > 0 ) {
				jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).html( '<div id="ec_cart_shipping_loader"><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div></div>' ).prepend( header_html );
				ec_reload_cart();
			}
		}
	}
	/* 6.0.2: cart badges, mini carts and the Elementor cart widgets follow the cart page's changes ( quantity, remove ). */
	if ( wpeasycart_cart_widgets_present( ) ) {
		wpeasycart_refresh_cart_widgets_soon( true );
	}
	jQuery( document ).trigger( 'wpeasycart_cart_updated', [ response_obj ] ); /* 6.0.2: for payment scripts that show the total ( Square wallets ) */
}

function ec_reload_cart( ){
	location.reload( );
}

function ec_open_login_click( ){
	jQuery( document.getElementById( 'ec_alt_login' ) ).slideToggle(300);
	
	return false;
}

function ec_update_shipping_view( ){
	if( jQuery( document.getElementById( 'ec_shipping_selector' ) ).is( ':checked' ) ){
		jQuery( document.getElementById( 'ec_shipping_form' ) ).show( );
	}else{
		jQuery( document.getElementById( 'ec_shipping_form' ) ).hide( );
	}
}

function ec_cart_toggle_login( ){
	if( jQuery( document.getElementById( 'ec_login_selector' ) ).is( ':checked' ) ){
		jQuery( document.getElementById( 'ec_user_login_form' ) ).show( );
	}else{
		jQuery( document.getElementById( 'ec_user_login_form' ) ).hide( );
	}
}

function ec_cart_toggle_login_v2() {
	if ( jQuery( document.getElementById( 'ec_user_login_form' ) ).is( ':visible' ) ) {
		jQuery( document.getElementById( 'ec_user_login_form' ) ).hide( );
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_link' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_cancel_link' ) ).hide( );
		jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', false );
		if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
			jQuery( document.getElementById( 'ec_user_create_form' ) ).show();
		}
		jQuery( '#ec_cart_onepage_info > div.ec_cart_input_row, #ec_cart_onepage_info > .ec_cart_header' ).show( );
		jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).show( );
		jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).show( );
		if ( jQuery( document.getElementById( 'shipping-address-element' ) ).length ) {
			jQuery( document.getElementById( 'shipping-address-element' ) ).show();
		}
	} else {
		jQuery( document.getElementById( 'ec_user_login_form' ) ).show( );
		/* 6.0.5: the email typed above is the one to sign in with ( no typing it twice ) */
		var wpec_typed_email = String( jQuery( document.getElementById( 'ec_contact_email' ) ).val() || '' ).trim();
		if ( '' === String( jQuery( document.getElementById( 'ec_cart_login_email' ) ).val() || '' ).trim() && ec_validate_email( wpec_typed_email ) ) {
			jQuery( document.getElementById( 'ec_cart_login_email' ) ).val( wpec_typed_email );
			setTimeout( function() {
				jQuery( document.getElementById( 'ec_cart_login_password' ) ).trigger( 'focus' );
			}, 0 );
		}
		jQuery( document.getElementById( 'ec_checkout_email_known' ) ).prop( 'hidden', true );
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).hide( );
		jQuery( document.getElementById( 'ec_user_login_link' ) ).hide( );
		jQuery( document.getElementById( 'ec_user_login_cancel_link' ) ).show( );
		jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', true );
		if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
			jQuery( document.getElementById( 'ec_user_create_form' ) ).hide();
		}
		jQuery( '#ec_cart_onepage_info > div.ec_cart_input_row, #ec_cart_onepage_info > .ec_cart_header' ).hide( );
		jQuery( '#ec_cart_contact_header' ).show( );
		jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).hide( );
		jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).hide( );
		if ( jQuery( document.getElementById( 'shipping-address-element' ) ).length ) {
			jQuery( document.getElementById( 'shipping-address-element' ) ).hide();
		}
	}
	return false;
}

/* 6.0.4: one look for every wait on the one-page checkout ( Matt: signing in or out showed a grey 2015 spinner over the text, and on
   a slow server nothing said what was happening ). The checkout's column goes under a light veil with a card in the middle: a spinner
   in the store's colour and what the page is doing ( Signing you in…, Signing you out…, Loading… ), and after a while that it is
   taking longer than usual ( ec-store.css .wpec-goto-loader ). A second veil with the same id replaces the first. Wording from
   wpeasycart_ajax_object.checkout_text ( language section cart_onepage ), else English. */
function wpeasycart_checkout_text( key ) {
	var fallback = {
		loading: 'Loading\u2026',
		signing_in: 'Signing you in\u2026',
		signing_out: 'Signing you out\u2026',
		creating_account: 'Creating your account\u2026',
		saving: 'Saving\u2026',
		sending: 'Sending\u2026',
		slow: 'This is taking a little longer than usual.'
	};
	var text = '';
	if ( 'undefined' !== typeof wpeasycart_ajax_object && wpeasycart_ajax_object && wpeasycart_ajax_object.checkout_text && 'string' === typeof wpeasycart_ajax_object.checkout_text[ key ] ) {
		text = wpeasycart_ajax_object.checkout_text[ key ];
	}
	return '' !== text ? text : ( fallback[ key ] || '' );
}

function wpeasycart_checkout_veil( near, id, key ) {
	jQuery( document.getElementById( id ) ).remove();
	var holder = jQuery( near ).first();
	if ( holder.length && holder.closest( '.ec_cart_left' ).length ) {
		holder = holder.closest( '.ec_cart_left' );
	}
	if ( ! holder.length ) {
		return null;
	}
	var position = holder.css( 'position' );
	if ( ! position || 'static' === position ) {
		holder.addClass( 'wpec-goto-host' );
	}
	var veil = jQuery( '<div class="wpec-goto-loader" role="status" aria-live="polite" aria-busy="true"><span class="wpec-goto-card"><span class="wpec-goto-spinner" aria-hidden="true"></span><span class="wpec-goto-words"><span class="wpec-goto-text"></span><span class="wpec-goto-slow"></span></span></span></div>' );
	veil.attr( 'id', id );
	veil.attr( 'data-wait', key || 'loading' );
	veil.find( '.wpec-goto-text' ).text( wpeasycart_checkout_text( key || 'loading' ) );
	veil.find( '.wpec-goto-slow' ).text( wpeasycart_checkout_text( 'slow' ) );
	holder.append( veil );
	return veil;
}

/* A page the browser shows again from its back / forward cache keeps the veil it had when the shopper left ( signing out goes on to
   another page ): take it off. */
window.addEventListener( 'pageshow', function( event ) {
	if ( event && event.persisted ) {
		jQuery( '.wpec-goto-loader' ).remove();
	}
} );

/* 6.0.4: the account pages wait the way the one-page checkout does ( Matt: one look for the cart and the account, from signing in to
   an order's details ). Signing in, creating an account, every save, sending a password link, signing out and moving between the
   account's own screens put the account page under the veil with a card saying what is happening, until the next page draws. A form
   a script keeps in the page ( it stopped the post ) and a link that opens elsewhere, prints or downloads are left alone. */
function wpeasycart_account_wait_key( form ) {
	var keys = {
		login: 'signing_in',
		register: 'creating_account',
		retrieve_password: 'sending',
		resend_activation: 'sending',
		reset_password: 'saving',
		update_personal_information: 'saving',
		update_password: 'saving',
		update_billing_information: 'saving',
		update_shipping_information: 'saving'
	};
	var action = String( jQuery( form ).find( 'input[name="ec_account_form_action"]' ).first().val() || '' );
	return Object.prototype.hasOwnProperty.call( keys, action ) ? keys[ action ] : '';
}

function wpeasycart_account_link_key( link, event ) {
	if ( ! link || ! link.href || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
		return '';
	}
	if ( ( link.target && '_self' !== link.target ) || link.hasAttribute( 'download' ) ) {
		return '';
	}
	var url = null;
	var here = null;
	try {
		url = new URL( link.href, window.location.href );
		here = new URL( window.location.href );
	} catch ( e ) {
		return '';
	}
	/* The account page's own screens only: the same page ( and the same page_id / p without pretty permalinks ), not an anchor. */
	if ( url.origin !== here.origin || url.pathname !== here.pathname || url.searchParams.get( 'page_id' ) !== here.searchParams.get( 'page_id' ) || url.searchParams.get( 'p' ) !== here.searchParams.get( 'p' ) ) {
		return '';
	}
	if ( url.hash && url.href.split( '#' )[0] === here.href.split( '#' )[0] ) {
		return '';
	}
	var page = String( url.searchParams.get( 'ec_page' ) || '' );
	if ( 'logout' === page ) {
		return 'signing_out';
	}
	var views = [ '', 'dashboard', 'login', 'orders', 'order_details', 'subscriptions', 'subscription_details', 'billing_information', 'shipping_information', 'personal_information', 'password', 'register', 'forgot_password' ];
	return ( -1 !== views.indexOf( page ) ) ? 'loading' : '';
}

/* The veil goes on at once, in the same task as the post or the click ( the browser starts the next page right after it and a later
   task may not draw before that page arrives ); a listener after this one that keeps the page ( it stopped the post or the click )
   takes it off again on the next tick. */
function wpeasycart_account_wait( event, host, key ) {
	var prevented = function() {
		return event.isDefaultPrevented() || ( event.originalEvent && event.originalEvent.defaultPrevented );
	};
	if ( prevented() ) {
		return;
	}
	var veil = wpeasycart_checkout_veil( host, 'ec_account_wait_loader', key );
	setTimeout( function() {
		if ( veil && prevented() ) {
			veil.remove();
		}
	}, 0 );
}

jQuery( document ).on( 'submit', '.ec_account_page form', function( event ) {
	var form = this;
	var key = wpeasycart_account_wait_key( form );
	if ( '' === key || ( form.target && '_self' !== form.target ) ) {
		return;
	}
	wpeasycart_account_wait( event, jQuery( form ).closest( '.ec_account_page' ).length ? jQuery( form ).closest( '.ec_account_page' ) : jQuery( form ), key );
} );

jQuery( document ).on( 'click', '.ec_account_page a[href]', function( event ) {
	var link = this;
	var key = wpeasycart_account_link_key( link, event );
	if ( '' === key ) {
		return;
	}
	wpeasycart_account_wait( event, jQuery( link ).closest( '.ec_account_page' ), key );
} );

function ec_cart_validate_login_v2( nonce ) {
	var recaptcha_complete = true;
	var recaptcha_response = '';
	if ( document.getElementById( 'ec_account_login_recaptcha' ) ) {
		recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_login' ) ).val();
		if ( ! recaptcha_response.length ) {
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', '1px solid red' );
			recaptcha_complete = false;
		} else {
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	if ( ec_validate_cart_login() && recaptcha_complete ) {
		wpeasycart_checkout_veil( jQuery( document.getElementById( 'ec_user_login_form' ) ), 'ec_cart_login_form_loader', 'signing_in' );
		var data = {
			action: 'ec_ajax_cart_login_v2',
			ec_cart_login_email: jQuery( document.getElementById( 'ec_cart_login_email' ) ).val(),
			ec_cart_login_password: jQuery( document.getElementById( 'ec_cart_login_password' ) ).val(),
			ec_grecaptcha_response_login: recaptcha_response,
			wpeasycart_nonce: nonce
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			/* 6.0.4: a request that fails leaves the form usable ( the spinner stayed for ever ). */
			error: function() {
				jQuery( document.getElementById( 'ec_cart_login_form_loader' ) ).remove();
				jQuery( document.getElementById( 'ec_cart_login_invalid_error' ) ).show();
			},
			success: function( data ) {
				var response_obj = null;
				try {
					response_obj = JSON.parse( data );
				} catch ( e ) {
					response_obj = { error: 'invalid' };
				}
				jQuery( document.getElementById( 'ec_cart_login_invalid_error' ) ).hide();
				jQuery( document.getElementById( 'ec_cart_not_activated_error' ) ).hide();
				if ( response_obj.error ) {
					jQuery( document.getElementById( 'ec_cart_login_form_loader' ) ).remove();
					if ( 'not_activated' == response_obj.error ) {
						jQuery( document.getElementById( 'ec_cart_not_activated_error' ) ).show();
					} else {
						jQuery( document.getElementById( 'ec_cart_login_invalid_error' ) ).show();
					}
					if ( '' != recaptcha_response ) {
						jQuery( document.getElementById( 'ec_grecaptcha_response_login' ) ).val( '' );
						grecaptcha.reset( wpeasycart_login_recaptcha );
					}
				} else {
					window.location.href = response_obj.url;
				}
			}
		} );
		return false;
	} else {
		return false;
	}
}

function ec_cart_stripe_paylater_messaging_v2( sub_total ) {
	var holder = jQuery( '#wpec-payment-method-messaging-element' );
	if ( ! holder.length || 'undefined' === typeof stripe || ! stripe || 'function' !== typeof stripe.elements ) {
		return;
	}
	/* 6.0.4: only Affirm, Klarna and Afterpay show a message. With none switched on the list was [ '' ] and Stripe threw an
	   IntegrationError, which stopped the cart's own scripts ( ec_update_cart() left the subtotal at its old amount, Edit Cart's
	   loader never closed ). A message already on the page is updated rather than mounted again, and nothing Stripe throws stops
	   the page. */
	var types = String( holder.attr( 'data-types' ) || '' ).split( ',' ).filter( function( type ) {
		return '' !== type;
	} );
	if ( ! types.length ) {
		return;
	}
	try {
		if ( window.wpeasycart_paylater_element && window.wpeasycart_paylater_holder === holder[0] ) {
			window.wpeasycart_paylater_element.update( { amount: sub_total } );
			return;
		}
		var elements = stripe.elements( {
			theme: holder.attr( 'data-theme' ),
		} );
		var message = elements.create( 'paymentMethodMessaging', {
			amount: sub_total,
			currency: holder.attr( 'data-currency' ),
			paymentMethodTypes: types,
			countryCode: holder.attr( 'data-country' ),
		} );
		message.mount( holder[0] );
		window.wpeasycart_paylater_element = message;
		window.wpeasycart_paylater_holder = holder[0];
	} catch ( e ) {
		if ( window.console && console.warn ) {
			console.warn( 'WP EasyCart: Stripe pay later message', e );
		}
	}
}

/* 6.0.5: fills in a signed-in card ( wp_easycart_signed_in_card() ) printed empty, as the subscription page's once a shopper makes an
   account there: the name ( else the email ) as its title, the email under it, the initials. A template copy without the card is left alone. */
function wpeasycart_signed_in_card( holder, name, email ) {
	var card = holder ? jQuery( holder ).find( '.ec_cart_signed_in_v2' ) : jQuery();
	if ( ! card.length ) {
		return;
	}
	name = String( name || '' ).trim();
	email = String( email || '' ).trim();
	var words = name.length ? name.split( /\s+/ ) : [ email ];
	var letters = '';
	if ( words[0] ) {
		letters += Array.from( words[0] )[0];
	}
	if ( words.length > 1 && words[ words.length - 1 ] ) {
		letters += Array.from( words[ words.length - 1 ] )[0];
	}
	card.find( '.ec_cart_signed_in_name' ).text( name.length ? name : email );
	card.find( '.ec_cart_signed_in_email' ).text( name.length ? email : '' );
	card.find( '.ec_cart_signed_in_avatar' ).text( letters.toUpperCase() );
}

function ec_cart_logout_v2() {
	/* 6.0.4: the link goes on to the cart page, which signs the customer out and draws the checkout again: the veil stays until then. */
	wpeasycart_checkout_veil( jQuery( document.getElementById( 'ec_cart_logged_in_section' ) ), 'ec_cart_logout_loader', 'signing_out' );
}

function ec_cart_toggle_address_edit( ) {
	if ( jQuery( document.getElementById( 'ec_cart_billing_form' ) ).is( ':visible' ) ) {
		jQuery( document.getElementById( 'ec_cart_billing_form' ) ).hide();
		jQuery( document.getElementById( 'ec_cart_billing_locked' ) ).show();
		if ( jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).show();
		}
		jQuery( document.getElementById( 'ec_cart_subscription_end_form' ) ).show();
	} else {
		jQuery( document.getElementById( 'ec_cart_billing_form' ) ).show();
		jQuery( document.getElementById( 'ec_cart_billing_locked' ) ).hide();
		if ( jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_shipping_locked' ) ).hide();
		}
		jQuery( document.getElementById( 'ec_cart_subscription_end_form' ) ).hide();
	}
}

function wp_easycart_update_contact_email_v2() {
	var data = {
		action: 'ec_ajax_update_contact_email',
		ec_create_account: ( ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) ? 1 : 0 ),
		ec_contact_email: jQuery( document.getElementById( 'ec_contact_email' ) ).val(),
		wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val()
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ) {
			var response_obj = JSON.parse( data );
			if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
				wpeasycart_create_account_error( response_obj.error );
			} else {
				jQuery( document.getElementById( 'ec_create_account_email_error' ) ).hide();
			}
			if ( response_obj.coupon_message ) {
				jQuery( document.getElementById( 'ec_coupon_success' ) ).hide( );
				jQuery( document.getElementById( 'ec_coupon_error' ) ).html( response_obj.coupon_message ).show( );
				if ( jQuery( '#ec_applying_coupon_v2' ).length ) {
					jQuery( document.getElementById( 'ec_coupon_success_mobile' ) ).hide( );
					jQuery( document.getElementById( 'ec_coupon_error_mobile' ) ).html( response_obj.coupon_message ).show( );
				}
				if ( jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).length ) {
					jQuery( document.getElementById( 'ec_coupon_code_mobile' ) ).val( '' );
				}
				if ( jQuery( document.getElementById( 'ec_coupon_code' ) ).length ) {
					jQuery( document.getElementById( 'ec_coupon_code' ) ).val( '' );
				}
			}
			ec_update_cart( response_obj.cart_data );
		},
	} );
}

function wp_easycart_goto_page_v2( page, nonce, add_state = true ) {
	if ( 'shipping' == page && jQuery( '#wpeasycart_shipping_page_link' ).hasClass( 'wpeasycart-deactivated-link' ) ) {
		return false;
	}
	if ( 'payment' == page && jQuery( '#wpeasycart_payment_page_link' ).hasClass( 'wpeasycart-deactivated-link' ) ) {
		return false;
	}

	var valid_pages = [ 'cart', 'information', 'shipping', 'payment' ];
	if ( ! valid_pages.includes( page ) ) {
		page = 'information';
	}
	/* 6.0.4: the checkout's veil ( wpeasycart_checkout_veil() ), in place of the 2015 spinner. */
	jQuery( '#ec_cart_goto_page_loader' ).remove();
	wpeasycart_checkout_veil( jQuery( '.ec_cart_left' ), 'ec_cart_goto_page_loader', 'loading' );

	if ( add_state ) {
		wp_easycart_goto_push_state( page );
	}

	var data = {
		action: 'ec_ajax_goto_page_v2',
		page: page,
		wpeasycart_checkout_nonce: nonce,
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		/* 6.0.4: a failed request leaves the page usable ( the loader stayed for ever ). */
		error: function() {
			jQuery( '#ec_cart_goto_page_loader' ).remove();
		},
		success: function( data ) {
			var response_obj = null;
			try {
				response_obj = JSON.parse( data );
			} catch ( e ) {
				response_obj = null;
			}
			if ( ! response_obj || 'object' !== typeof response_obj ) {
				jQuery( '#ec_cart_goto_page_loader' ).remove();
				return;
			}
			/* 6.0.4: the cart totals' update can never keep the new screen from drawing ( a Stripe error inside it left the loader up ). */
			try {
				ec_update_cart( response_obj.cart_data );
			} catch ( e ) {
				if ( window.console && console.warn ) {
					console.warn( 'WP EasyCart: cart update', e );
				}
			}
			/* 6.0.4: the phone's order summary belongs to the checkout, not the cart screen ( which is the summary ). */
			if ( 'cart' == page ) {
				jQuery( '.ec_cart_mobile_summary' ).hide();
			} else {
				jQuery( '.ec_cart_mobile_summary' ).css( 'display', '' );
			}
			if ( jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).length ) {
				jQuery( document.getElementById( 'ec_cart_goto_page_loader' ) ).remove();
				if ( 'cart' == page ) {
					jQuery( '.ec_cart_left' ).addClass( 'ec_cart_full' );
					jQuery( '.ec_cart_right' ).hide();
					if ( jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).show();
						jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).html( response_obj.html_content );
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).hide();
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).hide();
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).hide();
					}
				} else {
					jQuery( '.ec_cart_left' ).removeClass( 'ec_cart_full' );
					jQuery( '.ec_cart_right' ).show();
					if ( jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_cart' ) ).remove();
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).remove();
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).remove();
					}
					if ( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).length ) {
						jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).remove();
					}
					jQuery( document.getElementById( 'wpeasycart_checkout_details_form' ) ).append( response_obj.html_content );
				}
			} else {
				jQuery( '.ec_cart_left' ).html( response_obj.html_content );
				wpeasycart_address_fields(); /* 6.0.5 */
				if ( 'cart' == page ) {
					jQuery( '.ec_cart_left' ).addClass( 'ec_cart_full' );
					jQuery( '.ec_cart_right' ).hide();
				} else {
					jQuery( '.ec_cart_left' ).removeClass( 'ec_cart_full' );
					jQuery( '.ec_cart_right' ).show();
				}
				if ( '1' == response_obj.shipping_allowed ) {
					jQuery( '#wpeasycart_shipping_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
				}
				if ( '1' == response_obj.payment_allowed ) {
					jQuery( '#wpeasycart_payment_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
				}
			}
		}
	} );
	return false;
}

function wp_easycart_goto_push_state( page ) {
	var url = window.location.href;
	var var_key = 'eccheckout';
	var re = new RegExp( "([?&])" + var_key + "=.*?(&|$)", "i");
	var separator = url.indexOf( '?' ) !== -1 ? '&' : '?';
	if ( url.match( re ) ) {
		url = url.replace( re, '$1' + var_key + "=" + page + '$2');
	} else {
		url = url + separator + var_key + "=" + page;
	}
	window.history.pushState( { eccheckout: page }, document.title, url );
}

function wp_easycart_save_order_notes_v2() {
	var data = {
		action: 'ec_ajax_update_order_notes',
		ec_order_notes: jQuery( document.getElementById( 'ec_order_notes' ) ).val(),
		wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val()
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

function wp_easycart_save_email_other_v2() {
	var data = {
		action: 'ec_ajax_update_email_other',
		ec_email_other: jQuery( document.getElementById( 'ec_email_other' ) ).val(),
		wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val()
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

/* 6.0.2: the same / different billing address choice sent with the contact and address fields: '1' different, '0' the same,
   '' when the page does not show the choice ( the saved choice stays ). */
function wp_easycart_checkout_billing_choice() {
	if ( jQuery( document.getElementById( 'ec_shipping_selector' ) ).length ) {
		return jQuery( document.getElementById( 'ec_shipping_selector' ) ).val();
	}
	if ( jQuery( document.getElementById( 'billing_address_type_different' ) ).length ) {
		return jQuery( document.getElementById( 'billing_address_type_different' ) ).is( ':checked' ) ? '1' : '0';
	}
	return '';
}

/* 6.0.2: the contact and address fields the one-page checkout saves ( ec_ajax_save_checkout_info ), and the step they lead to. */
function wp_easycart_checkout_info_request() {
	var data, page;
	if ( jQuery( document.getElementById( 'ec_shipping_complete' ) ).length || jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).length ) {
		var shipping_country = ( jQuery( document.getElementById( 'ec_shipping_country' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_country' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).val();
		var shipping_state = ( jQuery( document.getElementById( 'ec_shipping_state' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_state' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_state' ) ).val();
		if ( jQuery( document.getElementById( 'ec_cart_shipping_state_' + shipping_country ) ).length ) {
			shipping_state = jQuery( document.getElementById( 'ec_cart_shipping_state_' + shipping_country ) ).val();
		}

		var data = {
			action: 'ec_ajax_save_checkout_info',
			ec_cart_is_subscriber: ( jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).is( ':checked' ) ) ? '1' : '0',
			ec_shipping_selector: wp_easycart_checkout_billing_choice(),
			ec_shipping_address_line_1: ( jQuery( document.getElementById( 'ec_shipping_address_line_1' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_address_line_1' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_address' ) ).val(),
			ec_shipping_address_line_2: ( jQuery( document.getElementById( 'ec_shipping_address_line_2' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_address_line_2' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_address2' ) ).val(),
			ec_shipping_city: ( jQuery( document.getElementById( 'ec_shipping_city' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_city' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_city' ) ).val(),
			ec_shipping_state: shipping_state,
			ec_shipping_zip: ( jQuery( document.getElementById( 'ec_shipping_zip' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_zip' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_zip' ) ).val(),
			ec_shipping_country: shipping_country,
			ec_shipping_phone: ( jQuery( document.getElementById( 'ec_shipping_phone' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_phone' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_phone' ) ).val(),
			ec_shipping_name: ( jQuery( document.getElementById( 'ec_shipping_name' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).val(),
			ec_shipping_last_name: ( jQuery( document.getElementById( 'ec_shipping_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_last_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_last_name' ) ).val(),
			ec_shipping_company_name: ( jQuery( document.getElementById( 'ec_shipping_company_name' ) ).length ) ? jQuery( document.getElementById( 'ec_shipping_company_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_shipping_company_name' ) ).val(),
			ec_contact_email: jQuery( document.getElementById( 'ec_contact_email' ) ).val(),
			ec_order_notes: jQuery( document.getElementById( 'ec_order_notes' ) ).val(),
			wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val(),
		};
		page = 'shipping';
	} else {
		var billing_country = ( jQuery( document.getElementById( 'ec_billing_country' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_country' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val();
		var billing_state = ( jQuery( document.getElementById( 'ec_billing_state' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_state' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_state' ) ).val();
		if ( jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).length ) {
			billing_state = jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).val();
		}

		var data = {
			action: 'ec_ajax_save_checkout_info',
			ec_cart_is_subscriber: ( jQuery( document.getElementById( 'ec_cart_is_subscriber' ) ).is( ':checked' ) ) ? '1' : '0',
			ec_billing_address_line_1: ( jQuery( document.getElementById( 'ec_billing_address_line_1' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_address_line_1' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_address' ) ).val(),
			ec_billing_address_line_2: ( jQuery( document.getElementById( 'ec_billing_address_line_2' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_address_line_2' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_address2' ) ).val(),
			ec_billing_city: ( jQuery( document.getElementById( 'ec_billing_city' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_city' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_city' ) ).val(),
			ec_billing_state: billing_state,
			ec_billing_zip: ( jQuery( document.getElementById( 'ec_billing_zip' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_zip' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).val(),
			ec_billing_country: billing_country,
			ec_billing_phone: ( jQuery( document.getElementById( 'ec_billing_phone' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_phone' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_phone' ) ).val(),
			ec_billing_name: ( jQuery( document.getElementById( 'ec_billing_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).val(),
			ec_billing_last_name: ( jQuery( document.getElementById( 'ec_billing_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_last_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).val(),
			ec_billing_company_name: ( jQuery( document.getElementById( 'ec_billing_company_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_company_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_company_name' ) ).val(),
			ec_contact_email: jQuery( document.getElementById( 'ec_contact_email' ) ).val(),
			ec_order_notes: jQuery( document.getElementById( 'ec_order_notes' ) ).val(),
			wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val(),
		};
		page = 'payment';
	}

	if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
		data.ec_create_account = 1;
		if ( jQuery( document.getElementById( 'ec_contact_first_name' ) ).length ) {
			data.ec_contact_first_name = jQuery( document.getElementById( 'ec_contact_first_name' ) ).val();
		}
		if ( jQuery( document.getElementById( 'ec_contact_last_name' ) ).length ) {
			data.ec_contact_last_name = jQuery( document.getElementById( 'ec_contact_last_name' ) ).val();
		}
		data.ec_contact_password = jQuery( document.getElementById( 'ec_contact_password' ) ).val();
	}

	if ( jQuery( document.getElementById( 'ec_cart_billing_vat_registration_number' ) ).length ) {
		data.ec_vat_registration_number = jQuery( document.getElementById( 'ec_cart_billing_vat_registration_number' ) ).val();
	}
	/* 6.0.2: the extra email ( Settings › Checkout ), only when the page shows it: without it the saved one stays. */
	if ( jQuery( document.getElementById( 'ec_email_other' ) ).length ) {
		data.ec_email_other = jQuery( document.getElementById( 'ec_email_other' ) ).val();
	}
	return { data: data, page: page };
}

function wp_easycart_goto_shipping_v2( should_scroll = false ) {
	var has_errors = false;
	var scrolled_top = false;
	var has_shipping_error = false;
	if ( jQuery( document.getElementById( 'ec_contact_email' ) ).length && ! ec_validate_email_block( 'ec_contact' ) ) {
		jQuery( document.getElementById( 'ec_email_order2_error' ) ).show();
		has_errors = true;
		if ( should_scroll && ! scrolled_top ) {
			jQuery( [ document.documentElement, document.body ] ).animate( {
				scrollTop: jQuery( document.getElementById( 'ec_cart_contact_header' ) ).offset().top
			}, 750 );
			scrolled_top = true;
		}
	} else {
		jQuery( document.getElementById( 'ec_email_order2_error' ) ).hide();
	}

	if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
		jQuery( document.getElementById( 'ec_user_login_form' ) ).hide( );
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_link' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_cancel_link' ) ).hide( );
		jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', false );
		jQuery( document.getElementById( 'ec_user_create_form' ) ).show();
		if ( ! ec_validate_create_account( 'ec_contact' ) ) {
			jQuery( document.getElementById( 'ec_create_account_order_error' ) ).show();
			has_errors = true;
			if ( should_scroll && ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_create_account_header' ) ).offset().top
				}, 750 );
				scrolled_top = true;
			}
		} else {
			jQuery( document.getElementById( 'ec_create_account_order_error' ) ).hide();
		}
	}

	if ( jQuery( document.getElementById( 'ec_shipping_complete' ) ).length ) {
		if ( '0' == jQuery( document.getElementById( 'ec_shipping_complete' ) ).val() ) {
			jQuery( '#ec_shipping_order_error' ).show();
			has_errors = true;
			has_shipping_error = true;
		} else {
			jQuery( '#ec_shipping_order_error' ).hide();
		}
	} else if ( jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).length && ! ec_validate_address_block( 'ec_cart_shipping' ) ) {
		jQuery( '#ec_shipping_order_error' ).show();
		has_errors = true;
		has_shipping_error = true;
	} else if ( ! jQuery( document.getElementById( 'ec_cart_shipping_first_name' ) ).length && jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).length && ! ec_validate_address_block( 'ec_cart_billing' ) ) {
		jQuery( '#ec_shipping_order_error' ).show();
		has_errors = true;
		has_shipping_error = true;
	} else if ( jQuery( document.getElementById( 'ec_shipping_address_line_1' ) ).length && '' == jQuery( document.getElementById( 'ec_shipping_address_line_1' ) ).val() || '' == jQuery( document.getElementById( 'ec_shipping_name' ) ).val() ) {
		jQuery( '#ec_shipping_order_error' ).show();
		has_errors = true;
		has_shipping_error = true;
	} else {
		jQuery( '#ec_shipping_order_error' ).hide();
	}
	if ( should_scroll && has_shipping_error && ! scrolled_top ) {
		jQuery( [ document.documentElement, document.body ] ).animate( {
			scrollTop: jQuery( document.getElementById( 'ec_cart_shipping_header' ) ).offset().top
		}, 750 );
		scrolled_top = true;
	}
	
	if ( has_errors ) {
		return false;
	}

	if( jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).length ) {
		var header_html = jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).find( '.ec_cart_header' );
		jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).html( '<div id="ec_cart_shipping_loader"><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div></div>' ).prepend( header_html );
		if ( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_locked_panel' ).length ) {
			var payment_header_html = jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_header' );
			jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).html( '<div id="ec_cart_shipping_loader"><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div></div>' ).prepend( payment_header_html );
		}

	} else if( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).length ) {
		var header_html = jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_header' );
		if ( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_locked_panel' ).length ) {
			jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).html( '<div id="ec_cart_shipping_loader"><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div><div class="ec_cart_shipping_animate_pulse"><div class="ec_cart_shipping_rounded_loader"></div><div class="ec_cart_shipping_animate_line_container"><div class="ec_cart_shipping_animate_line_top"></div><div class="ec_cart_shipping_animate_line_bottom"></div></div></div></div>' ).prepend( header_html );
		}
	} else {
		wpeasycart_checkout_veil( jQuery( '.ec_cart_left' ), 'ec_cart_shipping_loader', 'loading' );
	}

	var request = wp_easycart_checkout_info_request();
	var data = request.data;
	wp_easycart_goto_push_state( request.page );

	/* 6.0.4 ( a customer's report: rates "sometimes do not show" after typing an address ): every pause while typing sends one of
	   these saves, and answers can come back in any order. Only the newest save draws; an older answer that lands later is
	   dropped ( it used to draw an earlier address's rates, or none, over the newer ones ). A shipping list refresh that is
	   still open draws nothing over this save's answer. */
	var wpec_save_seq = ++wpeasycart_address_save_seq;
	wpeasycart_address_saves_open++;
	wpeasycart_shipping_refresh_seq++;

	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		/* 6.0.4: a request that fails leaves the page usable. */
		error: function() {
			if ( wpec_save_seq !== wpeasycart_address_save_seq ) {
				return;
			}
			jQuery( '#ec_cart_shipping_loader.wpec-goto-loader' ).remove();
		},
		complete: function() {
			wpeasycart_address_saves_open = Math.max( 0, wpeasycart_address_saves_open - 1 );
			if ( 0 === wpeasycart_address_saves_open && wpeasycart_shipping_refresh_wanted ) {
				wpeasycart_shipping_refresh_wanted = false;
				wpeasycart_onepage_refresh_shipping();
			}
		},
		success: function( data ) {
			if ( wpec_save_seq !== wpeasycart_address_save_seq ) {
				return; /* a newer save is on its way and draws the page */
			}
			var response_obj = JSON.parse( data );
			jQuery( document.getElementById( 'ec_cart_shipping_loader' ) ).remove();
			wpeasycart_create_account_error( response_obj.error );
			ec_update_cart( response_obj.cart_data );
			if( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).length ) {
				if ( jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).length ) {
					jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).html( response_obj.shipping_content );
					if ( jQuery( 'input[name=ec_cart_shipping_method]:checked' ).length ) {
						if ( jQuery( document.getElementById( 'ec_shipping_method_order_error' ) ).length ) {
							jQuery( document.getElementById( 'ec_shipping_method_order_error' ) ).hide();
						}
					}
				}
				if ( jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_locked_panel' ).length || jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).find( '.ec_cart_shipping_animate_pulse' ).length ) {
					jQuery( document.getElementById( 'ec_cart_onepage_payment' ) ).html( response_obj.payment_content );
				}
			} else {
				if ( ! response_obj.error ) {
					jQuery( '.ec_cart_left' ).html( response_obj.html_content );
					wpeasycart_address_fields(); /* 6.0.5 */
					if ( '1' == response_obj.shipping_allowed ) {
						jQuery( '#wpeasycart_shipping_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
					}
					if ( '1' == response_obj.payment_allowed ) {
						jQuery( '#wpeasycart_payment_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
					}
				}
			}
		}
	} );
}

/* 6.0.4: the one-page checkout's address saves ( wp_easycart_goto_shipping_v2() ) and shipping list refreshes in flight. */
var wpeasycart_address_save_seq = 0;
var wpeasycart_address_saves_open = 0;
var wpeasycart_shipping_refresh_seq = 0;
var wpeasycart_shipping_refresh_wanted = false;

/**
 * 6.0.4 ( a customer's report ): draws the one-page checkout's shipping method list again for the cart as it is now. A quantity raised
 * in the order summary moved the summary's Shipping total to the new rate ( a heavier parcel ) while the list kept the old prices until
 * the next screen or a reload. Called after the summary's quantity and coupon changes, and by WP EasyCart PRO's Offers code box
 * ( window.wpeasycart_onepage_refresh_shipping ); does nothing where the list is not shown ( no address yet, the classic checkout ).
 * The answer ( AJAX ec_ajax_onepage_shipping_table ) replaces .ec_cart_shipping_table, so the list's heading, the delivery questions
 * and the tabbed step's buttons stay. While an address save is open it waits for that save; only the newest refresh draws.
 */
function wpeasycart_onepage_refresh_shipping() {
	var table = jQuery( '.ec_cart_onepage_page .ec_cart_shipping_table' );
	var nonce = jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val();
	if ( ! table.length || ! nonce ) {
		return;
	}
	if ( wpeasycart_address_saves_open > 0 ) {
		wpeasycart_shipping_refresh_wanted = true;
		return;
	}
	var seq = ++wpeasycart_shipping_refresh_seq;
	var done = function() {
		jQuery( '.ec_cart_onepage_page .ec_cart_shipping_table' ).removeAttr( 'aria-busy' ).removeClass( 'wpec-shipping-refreshing' );
	};
	table.attr( 'aria-busy', 'true' ).addClass( 'wpec-shipping-refreshing' );
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		cache: false,
		data: { action: 'ec_ajax_onepage_shipping_table', wpeasycart_checkout_nonce: nonce },
		success: function( data ) {
			if ( seq !== wpeasycart_shipping_refresh_seq ) {
				return;
			}
			done();
			var response_obj = null;
			try {
				response_obj = JSON.parse( data );
			} catch ( e ) {
				response_obj = null;
			}
			if ( ! response_obj || ! response_obj.ok || 'string' !== typeof response_obj.table ) {
				return;
			}
			jQuery( '.ec_cart_onepage_page .ec_cart_shipping_table' ).html( response_obj.table );
			if ( jQuery( 'input[name=ec_cart_shipping_method]:checked' ).length && jQuery( document.getElementById( 'ec_shipping_method_order_error' ) ).length ) {
				jQuery( document.getElementById( 'ec_shipping_method_order_error' ) ).hide();
			}
			/* The totals follow only when the list changed them ( a chosen rate that is no longer offered ). */
			var totals = ( response_obj.cart_data && response_obj.cart_data.order_totals ) ? response_obj.cart_data.order_totals : null;
			if ( totals && ( totals.grand_total !== jQuery( '#ec_cart_total' ).first().text().trim() || totals.shipping_total !== jQuery( '#ec_cart_shipping' ).first().text().trim() ) ) {
				ec_update_cart( response_obj.cart_data );
			}
		},
		error: function() {
			if ( seq === wpeasycart_shipping_refresh_seq ) {
				done();
			}
		}
	} );
}
window.wpeasycart_onepage_refresh_shipping = wpeasycart_onepage_refresh_shipping;

function wp_easycart_goto_payment_v2() {
	wpeasycart_checkout_veil( jQuery( '.ec_cart_left' ), 'ec_cart_payment_loader', 'loading' );
	
	var ship_express = 0;
	var shipping_method = jQuery( '.ec_cart_shipping_method_row > input:checked' ).val();
	if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) { 
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).is( ':checked' ) ) {
			ship_express = 1;
		}
	}

	if ( 'shipexpress' == shipping_method ) {
		shipping_method = 'standard';
		if ( jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).length ) { 
			jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).prop( 'checked', false );
		}
		jQuery( document.getElementById( 'ec_cart_shipping_method' ) ).prop( 'checked', 'checked' );

	} else if( 'free' == shipping_method ) {
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_ship_express' ) ).prop( 'checked', false );
		}
	}

	wp_easycart_goto_push_state( 'payment' );
	var data = {
		action: 'ec_ajax_save_shipping_method',
		shipping_method: shipping_method,
		ship_express: ship_express,
		wpeasycart_checkout_nonce: jQuery( document.getElementById( 'wpeasycart_checkout_nonce' ) ).val(),
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		/* 6.0.4: a request that fails leaves the page usable. */
		error: function() {
			jQuery( document.getElementById( 'ec_cart_payment_loader' ) ).remove();
		},
		success: function( data ) {
			var response_obj = JSON.parse( data );
			jQuery( '.ec_cart_left' ).html( response_obj.html_content );
			wpeasycart_address_fields(); /* 6.0.5 */
			if ( '1' == response_obj.shipping_allowed ) {
				jQuery( '#wpeasycart_shipping_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
			}
			if ( '1' == response_obj.payment_allowed ) {
				jQuery( '#wpeasycart_payment_page_link' ).removeClass( 'wpeasycart-deactivated-link' );
			}
		}
	} );
}

function ec_toggle_create_account( ){
	if( jQuery( document.getElementById( 'ec_user_create_form' ) ).is( ':visible' ) ){
		jQuery( document.getElementById( 'ec_user_create_form' ) ).hide( );
	}else{
		jQuery( document.getElementById( 'ec_user_create_form' ) ).show( );
	}
}

function ec_update_payment_display( nonce ) {
	jQuery( '.ec_cart_payment_table_row ' ).removeClass( 'ec_payment_row_selected' );
	
	var payment_method = "manual_bill";
	
	jQuery( document.getElementById( 'ec_apple_pay_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_manual_payment_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_amazonpay_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_affirm_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_third_party_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_credit_card_form' ) ).hide( );
	jQuery( document.getElementById( 'ec_ideal_form' ) ).hide( );
	
	if( jQuery( document.getElementById( 'ec_payment_apple' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_apple' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_apple_pay_form' ) ).show( );

		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
		
		
		payment_method = "apple_pay";
	
	}else if( jQuery( document.getElementById( 'ec_payment_manual' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_manual' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_manual_payment_form' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
		
		
		payment_method = "manual_bill";
	
	}else if( jQuery( document.getElementById( 'ec_payment_affirm' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_affirm' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_affirm_form' ) ).show( );

		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
			
		payment_method = "affirm";
	
	}else if( jQuery( document.getElementById( 'ec_payment_third_party' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_third_party' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_third_party_form' ) ).show( );

		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length ){
			
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).show( );
			
			if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
				jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
			
			if( jQuery( document.getElementById( 'ec_submit_order_error' ) ).length )
				jQuery( document.getElementById( 'ec_submit_order_error' ) ).hide( );
			
			if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
				jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
			
			if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
				jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).hide( );
		}
		
		payment_method = "third_party";
	
	}else if( jQuery( document.getElementById( 'ec_payment_credit_card' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_credit_card' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_credit_card_form' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
			
		payment_method = "credit_card";
	
	}else if( jQuery( document.getElementById( 'ec_payment_ideal' ) ).is( ':checked' ) ){
		jQuery( '#ec_payment_ideal' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_ideal_form' ) ).show( );

		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
			
		payment_method = "ideal";

	}else if( jQuery( document.getElementById( 'ec_amazonpay' ) ).is( ':checked' ) ){
		jQuery( '#ec_amazonpay' ).parent().parent().addClass( 'ec_payment_row_selected' );

		jQuery( document.getElementById( 'ec_amazonpay_form' ) ).show( );

		if( jQuery( document.getElementById( 'ec_terms_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).length )
			jQuery( document.getElementById( 'ec_terms_agreement_row' ) ).show( );
		
		if( jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_order_row' ) ).show( );
			
		if( jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).length )
			jQuery( document.getElementById( 'wpeasycart_submit_paypal_order_row' ) ).hide( );
			
		payment_method = "amazonpay";
	}
	
	var data = {
		action: 'ec_ajax_update_payment_method',
		payment_method: payment_method,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( response ){
			var response_obj = JSON.parse( response );
			ec_update_cart( response_obj.cart_data );
		}
	} );
	
}

function ec_update_billing_address_display( billing_address_type, nonce ) {
	jQuery( '.ec_cart_billing_table_row' ).removeClass( 'ec_billing_row_selected' );
	jQuery( '.ec_cart_billing_table_column > input[value="' + billing_address_type + '"]' ).parent().parent().addClass( 'ec_billing_row_selected' );
	
	if ( '0' == billing_address_type ) {
		jQuery( '.ec_cart_billing_table_address' ).hide();
		if ( jQuery( document.getElementById( 'ec_shipping_selector' ) ).length ) {
			jQuery( document.getElementById( 'ec_shipping_selector' ) ).val( '0' );
		}
		jQuery( document.getElementById( 'ec_billing_order_error' ) ).hide();
	} else {
		jQuery( '.ec_cart_billing_table_address' ).show();
		if ( jQuery( document.getElementById( 'ec_shipping_selector' ) ).length ) {
			jQuery( document.getElementById( 'ec_shipping_selector' ) ).val( '1' );
		}
	}

	var billing_country = ( ( jQuery( document.getElementById( 'ec_billing_country' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_country' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_country' ) ).val() );
	var billing_state = '';
	if ( jQuery( document.getElementById( 'ec_billing_state' ) ).length ) {
		billing_state = jQuery( document.getElementById( 'ec_billing_state' ) ).val();
	} else if ( jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).length ) {
		billing_state = jQuery( document.getElementById( 'ec_cart_billing_state_' + billing_country ) ).val();
	} else {
		billing_state = jQuery( document.getElementById( 'ec_cart_billing_state' ) ).val()
	}
	var data = {
		action: 'ec_ajax_update_billing_address_type',
		billing_address_type: billing_address_type,
		ec_billing_address_line_1: ( ( jQuery( document.getElementById( 'ec_billing_address_line_1' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_address_line_1' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_address' ) ).val() ),
		ec_billing_address_line_2: ( ( jQuery( document.getElementById( 'ec_billing_address_line_2' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_address_line_2' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_address2' ) ).val() ),
		ec_billing_city: ( ( jQuery( document.getElementById( 'ec_billing_city' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_city' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_city' ) ).val() ),
		ec_billing_state: billing_state,
		ec_billing_zip: ( ( jQuery( document.getElementById( 'ec_billing_zip' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_zip' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_zip' ) ).val() ),
		ec_billing_country: billing_country,
		ec_billing_phone: ( ( jQuery( document.getElementById( 'ec_billing_phone' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_phone' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_phone' ) ).val() ),
		ec_billing_name: ( ( jQuery( document.getElementById( 'ec_billing_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_first_name' ) ).val() ),
		ec_billing_last_name: ( ( jQuery( document.getElementById( 'ec_billing_last_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_last_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_last_name' ) ).val() ),
		ec_billing_company_name: ( ( jQuery( document.getElementById( 'ec_billing_company_name' ) ).length ) ? jQuery( document.getElementById( 'ec_billing_company_name' ) ).val() : jQuery( document.getElementById( 'ec_cart_billing_company_name' ) ).val() ),
		nonce: nonce
	};

	return jQuery.ajax({
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

function ec_show_cc_type( type ){
	
	if( jQuery( document.getElementById( 'ec_card_visa' ) ) ){
		if( type == "visa" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_visa' ) ).show( );
			jQuery( document.getElementById( 'ec_card_visa_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_visa' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_visa_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_discover' ) ) ){
		if( type == "discover" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_discover' ) ).show( );
			jQuery( document.getElementById( 'ec_card_discover_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_discover' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_discover_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_mastercard' ) ) ){
		if( type == "mastercard" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_mastercard' ) ).show( );
			jQuery( document.getElementById( 'ec_card_mastercard_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_mastercard' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_mastercard_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_amex' ) ) ){
		if( type == "amex" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_amex' ) ).show( );
			jQuery( document.getElementById( 'ec_card_amex_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_amex' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_amex_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_jcb' ) ) ){
		if( type == "jcb" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_jcb' ) ).show( );
			jQuery( document.getElementById( 'ec_card_jcb_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_jcb' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_jcb_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_diners' ) ) ){
		if( type == "diners" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_diners' ) ).show( );
			jQuery( document.getElementById( 'ec_card_diners_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_diners' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_diners_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_laser' ) ) ){
		if( type == "laser" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_laser' ) ).show( );
			jQuery( document.getElementById( 'ec_card_laser_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_laser' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_laser_inactive' ) ).show( );
		}
	}
	
	if( jQuery( document.getElementById( 'ec_card_maestro' ) ) ){
		if( type == "maestro" || type == "all" ){
			jQuery( document.getElementById( 'ec_card_maestro' ) ).show( );
			jQuery( document.getElementById( 'ec_card_maestro_inactive' ) ).hide( );
		}else{
			jQuery( document.getElementById( 'ec_card_maestro' ) ).hide( );
			jQuery( document.getElementById( 'ec_card_maestro_inactive' ) ).show( );
		}
	}
	
}

function wpeasycart_bluecheck_verify( ){
	try {
		
        BlueCheckService.showModal();
        return false;
		
    } catch(e) {        
        console.error('[BlueCheckService::customValidation]', e);
        BlueCheckService.BcWebLogger('Error: ' + (e.message || e));
        return false;
    }
}

function ec_validate_cart_details( ){
	
	/* 6.0.5: an order that needs an account: the email step comes first ( ec_checkout_account.php ) */
	var wpec_step = wpeasycart_subscription_step();
	if ( wpec_step && 'checkout' === wpec_step.getAttribute( 'data-context' ) && 'email' === wpec_step.getAttribute( 'data-state' ) ) {
		wpeasycart_subscription_email_continue();
		jQuery( 'html, body' ).animate( { scrollTop: Math.max( 0, jQuery( wpec_step ).offset().top - 120 ) }, 200 );
		return false;
	}

	var login_complete = true;
	var billing_complete = ec_validate_address_block( 'ec_cart_billing' );
	var shipping_complete = true;
	var email_complete = true;
	var create_account_complete = true;
	var terms_complete = true;
    var recaptcha_complete = true;
	var bluecheck_complete = true;
	
	if( jQuery( document.getElementById( 'ec_login_selector' ) ).is( ':checked' ) )
		login_complete = ec_validate_cart_login( );
	
	if( jQuery( document.getElementById( 'ec_shipping_selector' ) ).is( ':checked' ) )
		shipping_complete = ec_validate_address_block( 'ec_cart_shipping' );
	
	if( jQuery( document.getElementById( 'ec_contact_email' ) ).length )
		email_complete = ec_validate_email_block( 'ec_contact' );
	
	if( jQuery( document.getElementById( 'ec_create_account_selector' ) ).is( ':checked' ) || ( jQuery( document.getElementById( 'ec_create_account_selector' ) ).is(':hidden' ) && ! jQuery( document.getElementById( 'ec_create_account_selector' ) ).prop( 'disabled' ) && jQuery( document.getElementById( 'ec_create_account_selector' ) ).val( ) == "create_account" ) ) {
		create_account_complete = ec_validate_create_account( 'ec_contact' );
		terms_complete = ec_validate_terms_section();
	}

    if( document.getElementById( 'ec_account_register_recaptcha' ) ){
        var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', '1px solid red' );
			recaptcha_complete = false;
		}else{
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', 'none' );
		}
    }
		
	if( document.getElementById( 'bcvTrigger' ) ){
		bluecheck_complete = wpeasycart_bluecheck_verify( );
		if( !bluecheck_complete )
			return false;
	}
		
	if( login_complete && billing_complete && shipping_complete && email_complete && create_account_complete && terms_complete && recaptcha_complete ){
		ec_hide_error( 'ec_checkout' );
		ec_hide_error( 'ec_checkout2' );
		jQuery( '.ec_checkout_details_submit' ).parent().addClass( 'wp-easycart-running' );
		return true;
	}else{
		ec_show_error( 'ec_checkout' );
		ec_show_error( 'ec_checkout2' );
		return false;
	}
	
}

function ec_validate_paypal_express_submit_order( ){
	var terms_complete = ec_validate_terms( );
	if( terms_complete ){
		jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
		jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
		ec_hide_error( 'ec_submit_order' );
		return true;
	}else{
		jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
		jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
		ec_show_error( 'ec_submit_order' );
		return false;
	}
}

function ec_validate_submit_order( validate_only ){ /* 6.0.2: validate_only answers without placing the order */
	var email_complete = true;
	var create_account_complete = true;
	var shipping_info_complete = true;
	var shipping_method_complete = true;
	var billing_info_complete = true;
	var pickup_info_complete = true;
	var account_terms_complete = true;
	var scrolled_top = false;
	/* 6.0.2: not on the one-page checkout: Place order saves the email with the rest of the page, one save after another
	   ( wpeasycart_onepage_ready_to_pay() ). A second save running beside them could put back an older copy of the session. */
	if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length && ! document.getElementById( 'wpeasycart_onepage_place' ) ) {
		wp_easycart_update_contact_email_v2();
	}
	if ( jQuery( document.getElementById( 'ec_contact_email_complete' ) ).length ) {
		if ( '0' == jQuery( document.getElementById( 'ec_contact_email_complete' ) ).val() ) {
			email_complete = false;
			if ( ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).offset().top
				}, 750 );
				scrolled_top = true;
			}
		}
	} else if ( jQuery( document.getElementById( 'ec_contact_email' ) ).length ) {
		if ( ! ec_validate_email_block( 'ec_contact' ) || ( jQuery( document.getElementById( 'ec_create_account_email_error' ) ).length && jQuery( document.getElementById( 'ec_create_account_email_error' ) ).is( ':visible' ) ) ) {
			email_complete = false;
			if ( ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_contact_header' ) ).offset().top
				}, 750 );
				scrolled_top = true;
			}
		}
	}
	if ( jQuery( document.getElementById( 'ec_user_create_form' ) ).length ) {
		jQuery( document.getElementById( 'ec_user_login_form' ) ).hide( );
		jQuery( document.getElementById( 'ec_user_contact_form' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_link' ) ).show( );
		jQuery( document.getElementById( 'ec_user_login_cancel_link' ) ).hide( );
		jQuery( document.getElementById( 'ec_login_selector' ) ).prop( 'checked', false );
		jQuery( document.getElementById( 'ec_user_create_form' ) ).show();
		if ( ! ec_validate_create_account( 'ec_contact' ) ) {
			jQuery( document.getElementById( 'ec_create_account_order_error' ) ).show();
			create_account_complete = false;
			if ( ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_create_account_header' ) ).offset().top
				}, 750 );
				scrolled_top = true;
			}
		} else {
			jQuery( document.getElementById( 'ec_create_account_order_error' ) ).hide();
		}
		/* 6.0.2: Place order creates the account too, so the account terms box ( when the store asks for it ) must be ticked
		   here as well, not only on the form's own Create account button. */
		if ( document.getElementById( 'ec_account_terms_agree' ) ) {
			account_terms_complete = jQuery( document.getElementById( 'ec_account_terms_agree' ) ).is( ':checked' );
			jQuery( document.getElementById( 'ec_account_terms_error' ) ).toggle( ! account_terms_complete );
			if ( ! account_terms_complete && ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_account_terms_agree' ) ).offset().top - 50
				}, 750 );
				scrolled_top = true;
			}
		}
	}
	if ( jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).length ) {
		if ( '0' == jQuery( document.getElementById( 'ec_shipping_complete' ) ).val() ) {
			shipping_info_complete = false;
			if ( ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).offset().top
				}, 750 );
				scrolled_top = true;
			}
		} else if ( jQuery( document.getElementById( 'ec_cart_shipping_country' ) ).length ) {
			if ( ! ec_validate_address_block( 'ec_cart_shipping' ) ) {
				shipping_info_complete = false;
				if ( ! scrolled_top ) {
					jQuery( [ document.documentElement, document.body ] ).animate( {
						scrollTop: jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).offset().top
					}, 750 );
					scrolled_top = true;
				}
			}
		} else if ( jQuery( document.getElementById( 'ec_cart_billing_country' ) ).length ) {
			if ( ! ec_validate_address_block( 'ec_cart_billing' ) ) {
				billing_info_complete = false;
				if ( ! scrolled_top ) {
					jQuery( [ document.documentElement, document.body ] ).animate( {
						scrollTop: jQuery( document.getElementById( 'ec_cart_onepage_info' ) ).offset().top
					}, 750 );
					scrolled_top = true;
				}
			}
		}
	}
	if ( jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).length ) {
		if ( ! jQuery( 'input[name=ec_cart_shipping_method]:checked' ).length ) {
			shipping_method_complete = false;
			if ( ! scrolled_top ) {
				jQuery( [ document.documentElement, document.body ] ).animate( {
					scrollTop: jQuery( document.getElementById( 'ec_cart_onepage_shipping' ) ).offset().top - 50
				}, 750 );
				scrolled_top = true;
			}
		}
	}
	if ( jQuery( document.getElementById( 'billing_address_type_different' ) ).length && jQuery( document.getElementById( 'billing_address_type_different' ) ).is( ':checked' ) ) {
		if ( jQuery( document.getElementById( 'ec_billing_complete' ) ).length && '0' == jQuery( document.getElementById( 'ec_billing_complete' ) ).val() ) {
			billing_info_complete = false;
		} else if ( jQuery( document.getElementById( 'ec_cart_billing_country' ) ).length && ! ec_validate_address_block( 'ec_cart_billing' ) ) {
			billing_info_complete = false;
		}
	}

	if ( jQuery( '#preorder_pickup_date' ).length ) {
		if ( ! jQuery( '#preorder_pickup_date' ).datepicker( 'getDate' ) ) {
			pickup_info_complete = false;
			jQuery( '#ec_preorder_pickup_error' ).show();
		} else if ( '' == jQuery( '#preorder_pickup_time' ).val() ) {
			pickup_info_complete = false;
			jQuery( '#ec_preorder_pickup_error' ).show();
		} else {
			jQuery( '#ec_preorder_pickup_error' ).hide();
		}
	}

	var payment_method_complete = ec_validate_payment_method( );
	var terms_complete = ec_validate_terms( );

	if ( jQuery( document.getElementById( 'ec_email_order1_error' ) ).length ) {
		if ( email_complete ) {
			ec_hide_error( 'ec_email_order1' );
			ec_hide_error( 'ec_email_order2' );
		} else {
			ec_show_error( 'ec_email_order1' );
			ec_show_error( 'ec_email_order2' );
		}
	}

	if ( jQuery( document.getElementById( 'ec_shipping_order_error' ) ).length ) {
		if ( shipping_info_complete ) {
			ec_hide_error( 'ec_shipping_order' );
		} else {
			ec_show_error( 'ec_shipping_order' );
		}
	}

	if ( jQuery( document.getElementById( 'ec_shipping_method_order_error' ) ).length ) {
		if ( shipping_method_complete ) {
			ec_hide_error( 'ec_shipping_method_order' );
		} else {
			ec_show_error( 'ec_shipping_method_order' );
		}
	}

	if ( billing_info_complete ) {
		ec_hide_error( 'ec_billing_order' );
	} else {
		ec_show_error( 'ec_billing_order' );
	}

	if ( payment_method_complete && terms_complete && email_complete && shipping_info_complete && shipping_method_complete && billing_info_complete && pickup_info_complete && create_account_complete && account_terms_complete ) {
		if ( validate_only ) {
			return true;
		}
		if ( document.getElementById( 'wpeasycart_onepage_place' ) && 'function' === typeof wpeasycart_onepage_place_order && ! wpeasycart_onepage_place.submitting ) {
			wpeasycart_onepage_place_order(); /* 6.0.2: ec-checkout-onepage.js */
			return false;
		}
		if( !document.getElementById( 'ec_stripe_card_row' ) && !document.getElementById( 'wpec_braintree_dropin' ) ){
			jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
			ec_hide_error( 'ec_submit_order' );
			if( jQuery( document.getElementById( 'ec_card_number' ) ).length )
				jQuery( document.getElementById( 'ec_card_number' ) ).val( jQuery( document.getElementById( 'ec_card_number' ) ).val( ).replace( /\s+/g, '' ) );
		}
		return true;
	}else{
		jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
		jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
		ec_show_error( 'ec_submit_order' );
		return false;
	}
	
}

function ec_validate_submit_invoice( ){
	
	var payment_method_complete = ec_validate_payment_method( );
	var terms_complete = ec_validate_terms( );
	var billing_complete = ec_validate_address_block( 'ec_cart_billing' );
	
	if( payment_method_complete && terms_complete && billing_complete ){
		if( !document.getElementById( 'ec_stripe_card_row' ) && !document.getElementById( 'wpec_braintree_dropin' ) ){
			jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
			ec_hide_error( 'ec_submit_order' );
			if( jQuery( document.getElementById( 'ec_card_number' ) ).length )
				jQuery( document.getElementById( 'ec_card_number' ) ).val( jQuery( document.getElementById( 'ec_card_number' ) ).val( ).replace( /\s+/g, '' ) );
		}
		return true;
	}else{
		jQuery( document.getElementById( 'ec_cart_submit_order' ) ).show( );
		jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).hide( );
		ec_show_error( 'ec_submit_order' );
		return false;
	}
	
}

function ec_validate_submit_subscription( ){
	var login_complete = true;
	var billing_complete = ( 'function' === typeof wpeasycart_subscription_short && wpeasycart_subscription_short() ) ? wpeasycart_subscription_short_check() : ec_validate_address_block( 'ec_cart_billing' ); /* 6.0.5: the short form checks only what it asks */
	var shipping_complete = true;
	var email_complete = true;
	var create_account_complete = true;
	var payment_method_complete = ec_validate_payment_method( );
	var terms_complete = ec_validate_terms( );
	
	if ( jQuery( document.getElementById( 'ec_user_login_form' ) ).is( ':visible' ) ) {
		login_complete = ec_validate_cart_login( );
	}

	if ( jQuery( document.getElementById( 'ec_user_contact_form' ) ).is( ':visible' ) ) {
		email_complete = ec_validate_email_block( 'ec_contact' );
	}
	
	if ( jQuery( document.getElementById( 'ec_user_contact_form' ) ).is( ':visible' ) ) {
		create_account_complete = ec_validate_create_account( 'ec_contact' );
	}

	if ( jQuery( document.getElementById( 'ec_shipping_selector' ) ).is( ':checked' ) ) {
		shipping_complete = ec_validate_address_block( 'ec_cart_shipping' );
	}
		
	if( login_complete && billing_complete && shipping_complete && email_complete && create_account_complete && payment_method_complete && terms_complete ){
		ec_hide_error( 'ec_checkout' ); /* 6.0.5: "Please correct the errors" goes once they are, with Stripe too ( it stayed over a corrected form ) */
		if( !document.getElementById( 'ec_stripe_card_row' ) && !document.getElementById( 'wpec_braintree_dropin' ) ){
			jQuery( document.getElementById( 'ec_cart_submit_order' ) ).hide( );
			jQuery( document.getElementById( 'ec_cart_submit_order_working' ) ).show( );
		}
		return true;
	}else{
		ec_show_error( 'ec_checkout' );
		return false;
	}
	
}

function ec_validate_cart_login( ){
	
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_cart_login_email' ) ).val( ).trim( );
	var password = jQuery( document.getElementById( 'ec_cart_login_password' ) ).val( );
	
	if( !ec_validate_email( email ) ){
		errors = true;
		ec_show_error( 'ec_cart_login_email' );
	}else{
		ec_hide_error( 'ec_cart_login_email' );
	}
	
	if( !ec_validate_text( password ) ){
		errors = true;
		ec_show_error( 'ec_cart_login_password' );
	}else{
		ec_hide_error( 'ec_cart_login_password' );
	}
    
    if( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_login' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', '1px solid red' );
			errors = true;
		}else{
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	return ( !errors );
	
}

function ec_validate_address_block( prefix ){
	
	var errors = false;
	var country = jQuery( document.getElementById( '' + prefix + '_country' ) ).val( );
	var first_name = jQuery( document.getElementById( '' + prefix + '_first_name' ) ).val( );
	var last_name = jQuery( document.getElementById( '' + prefix + '_last_name' ) ).val( );
	var city = jQuery( document.getElementById( '' + prefix + '_city' ) ).val( );
	var address = jQuery( document.getElementById( '' + prefix + '_address' ) ).val( );
	if( jQuery( document.getElementById( '' + prefix + '_state_' + country ) ) )
		var state = jQuery( document.getElementById( '' + prefix + '_state_' + country ) ).val( );
	else
		var state = jQuery( document.getElementById( '' + prefix + '_state' ) ).val( );
	var zip = jQuery( document.getElementById( '' + prefix + '_zip' ) ).val( );
	var phone = jQuery( document.getElementById( '' + prefix + '_phone' ) ).val( );
	var company_name = ( jQuery( document.getElementById( '' + prefix + '_company_name' ) ).length ) ? jQuery( document.getElementById( '' + prefix + '_company_name' ) ).val( ) : '';
	
	if( !ec_validate_select( country ) ){
		errors = true;
		ec_show_error( prefix + '_country' );
	}else{
		ec_hide_error( prefix + '_country' );
	}
	
	if( !ec_validate_text( first_name ) ){
		errors = true;
		ec_show_error( prefix + '_first_name' );
	}else{
		ec_hide_error( prefix + '_first_name' );
	}
	
	if( !ec_validate_text( last_name ) ){
		errors = true;
		ec_show_error( prefix + '_last_name' );
	}else{
		ec_hide_error( prefix + '_last_name' );
	}
	
	if( !ec_validate_text( city ) ){
		errors = true;
		ec_show_error( prefix + '_city' );
	}else{
		ec_hide_error( prefix + '_city' );
	}
	
	if( !ec_validate_text( address ) ){
		errors = true;
		ec_show_error( prefix + '_address' );
	}else{
		ec_hide_error( prefix + '_address' );
	}
	
	if( jQuery( document.getElementById( '' + prefix + '_state_' + country ) ).length && ! wpeasycart_region_optional( country ) ){
		if( !ec_validate_select( state ) ){
			errors = true;
			ec_show_error( prefix + '_state' );
		}else{
			ec_hide_error( prefix + '_state' );
		}
	}else{
		ec_hide_error( prefix + '_state' );
	}
	
	if( !ec_validate_zip_code( zip, country ) ){
		errors = true;
		ec_show_error( prefix + '_zip' );
	}else{
		ec_hide_error( prefix + '_zip' );
	}
	
	if( jQuery( document.getElementById( '' + prefix + '_phone_error' ) ).length ){
		if( jQuery( document.getElementById( '' + prefix + '_phone' ) ).length && !ec_validate_text( phone ) ){
			errors = true;
			ec_show_error( prefix + '_phone' );
		}else{
			ec_hide_error( prefix + '_phone' );
		}
	}

	if( jQuery( document.getElementById( '' + prefix + '_company_name_error' ) ).length ){
		if( jQuery( document.getElementById( '' + prefix + '_company_name' ) ).length && !ec_validate_text( company_name ) ){
			errors = true;
			ec_show_error( prefix + '_company_name' );
		}else{
			ec_hide_error( prefix + '_company_name' );
		}
	}

	return ( !errors );
	
}

function ec_validate_email_block( prefix ){
	
	var errors = false;
	var email = jQuery( document.getElementById( '' + prefix + '_email' ) ).val( ).trim();
	var retype_email = "";
	if( jQuery( document.getElementById( '' + prefix + '_email_retype' ) ).length ) {
		retype_email = jQuery( document.getElementById( '' + prefix + '_email_retype' ) ).val( ).trim();
	} else if ( jQuery( document.getElementById( '' + prefix + '_retype_email' ) ).length ) {
		retype_email = jQuery( document.getElementById( '' + prefix + '_retype_email' ) ).val( ).trim();
	} else {
		retype_email = email;
	}
	
	if( !ec_validate_email( email ) ){
		errors = true;
		ec_show_error( prefix + '_email' );
		if ( jQuery( document.getElementById( 'ec_create_account_email_error' ) ).length ) {
			jQuery( document.getElementById( 'ec_create_account_email_error' ) ).hide();
		}
	}else{
		ec_hide_error( prefix + '_email' );
	}
	
	if( !ec_validate_match( email, retype_email) ){
		errors = true;
		ec_show_error( prefix + '_email_retype' );
	}else{
		ec_hide_error( prefix + '_email_retype' );
	}
	
	return ( !errors );
	
}

function ec_validate_create_account( prefix ){
	
	var errors = false;
	var first_name = ( jQuery( document.getElementById( '' + prefix + '_first_name' ) ).length ) ? jQuery( document.getElementById( '' + prefix + '_first_name' ) ).val() : '';
	var last_name = ( jQuery( document.getElementById( '' + prefix + '_last_name' ) ).length ) ? jQuery( document.getElementById( '' + prefix + '_last_name' ) ).val() : '';
	var password = jQuery( document.getElementById( '' + prefix + '_password' ) ).val( );
	var retype_password = '';
	
	if ( jQuery( document.getElementById( '' + prefix + '_password_retype' ) ).length ) {
		retype_password = jQuery( document.getElementById( '' + prefix + '_password_retype' ) ).val( );
	} else {
		retype_password = password;
	}
	
	if( jQuery( document.getElementById( '' + prefix + '_first_name' ) ).length && ! ec_validate_text( first_name ) ){
		errors = true;
		ec_show_error( prefix + '_first_name' );
	}else{
		ec_hide_error( prefix + '_first_name' );
	}
	
	if( jQuery( document.getElementById( '' + prefix + '_last_name' ) ).length && ! ec_validate_text( last_name ) ){
		errors = true;
		ec_show_error( prefix + '_last_name' );
	}else{
		ec_hide_error( prefix + '_last_name' );
	}
	
	if ( ! ec_validate_password( password, retype_password ) ) {
		errors = true;
		ec_show_error( prefix + '_password' );
	} else {
		ec_hide_error( prefix + '_password' );
	}
	
	if( jQuery( document.getElementById( prefix + '_password_retype' ) ).length && ! ec_validate_match( password, retype_password ) ){
		errors = true;
		ec_show_error( prefix + '_password_retype' );
	}else{
		ec_hide_error( prefix + '_password_retype' );
	}
	
	return ( !errors );
	
}

function ec_validate_terms_section() {
	var errors = false;
	if ( jQuery( document.getElementById( 'ec_terms_agree' ) ).length ) {
		if ( ! ec_validate_terms() ) {
			errors = true;
		}
	}
	return ( ! errors );
}

function ec_validate_payment_method( ){
	
	var errors = false;
	var payment_method = "credit_card";
	if( jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).length ){
		ec_hide_error( 'ec_payment_method' );
		payment_method = jQuery( 'input:radio[name=ec_cart_payment_selection]:checked' ).val( );
	}else if( jQuery( 'input:radio[name=ec_cart_payment_selection]' ).length ){
		ec_show_error( 'ec_payment_method' );
		return false;
	}else{ // free order or no payment methods
		ec_hide_error( 'ec_payment_method' );
		return true;
	}
	
	var card_holder_name = "-1";
	if( document.getElementById( 'ec_card_holder_name' ) ){
		card_holder_name = jQuery( document.getElementById( 'ec_card_holder_name' ) ).val( );
	}
	
	if( payment_method == "affirm" && document.getElementById( 'wpeasycart_onepage_place' ) ){
		return true; /* 6.0.2: opened from the submit, after Place order saved and checked the order */

	}else if( payment_method == "affirm" ){
		ec_checkout_with_affirm( );
		ec_hide_error( 'ec_submit_order' );
		return false;
		
	}else if( payment_method == "credit_card" && jQuery( document.getElementById( 'wp-easycart-square-card-container' ) ).length ) {
		return true;

	}else if( payment_method == "credit_card" && ( document.getElementById( 'ec_stripe_card_row' ) || document.getElementById( 'wpec_braintree_dropin' ) ) ){ 
		return true;

	}else if( payment_method == "credit_card" && document.getElementById( 'cardpointe_token' ) ){
		if( jQuery( document.getElementById( 'cardpointe_token' ) ).val( ) == '' ){
			return false;
		}else{
			return true;
		}

	}else if( payment_method == "credit_card" ){
		
		var cardType = jQuery.payment.cardType( jQuery( document.getElementById( 'ec_card_number' ) ).val( ) );
		
		if( card_holder_name != "-1" && card_holder_name == "" ){
			errors = true;
			ec_show_error( 'ec_card_holder_name' );
		}else{
			ec_hide_error( 'ec_card_holder_name' );
		}
		
		if( !jQuery.payment.validateCardNumber( jQuery( document.getElementById( 'ec_card_number' ) ).val( ) ) ){
			errors = true;
			ec_show_error( 'ec_card_number' );
		}else{
			ec_hide_error( 'ec_card_number' );
		}
		
		if( !jQuery.payment.validateCardExpiry( jQuery( document.getElementById( 'ec_cc_expiration' ) ).payment( 'cardExpiryVal' ) ) ){
			errors = true;
			ec_show_error( 'ec_expiration_date' );
		}else{
			ec_hide_error( 'ec_expiration_date' );
		}
		
		if( !jQuery.payment.validateCardCVC( jQuery( document.getElementById( 'ec_security_code' ) ).val( ), cardType) ){
			errors = true;
			ec_show_error( 'ec_security_code' );
		}else{
			ec_hide_error( 'ec_security_code' );
		}
		
	}
	
	return ( !errors );
	
}

function ec_validate_terms( ){
	
	var errors = false;
	
	if( jQuery( document.getElementById( 'ec_terms_agree' ) ).is( ':checked' ) || jQuery( document.getElementById( 'ec_terms_agree' ) ).val( ) == '2' ){
		ec_hide_error( 'ec_terms' );
	}else{
		errors = true;
		ec_show_error( 'ec_terms' );
	}
	
	return ( !errors );
	
}

function ec_validate_email( email ){
	
	return /^([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22))*\x40([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d))*$/.test( email );

}

function ec_validate_password( pw ){
	
	if( pw && pw.length > 5 )
		return true;
	else
		return false;
	
}

function ec_validate_text( str ){
	
	if( str && str.length > 0 )
		return true;
	else
		return false;
	
}

function ec_validate_select( val ){
	
	if( val && val != 0 )
		return true;
	else
		return false;
	
}

function ec_validate_match( val1, val2 ){
	
	if( val1 == val2 )
		return true;
	else
		return false;
	
}

function ec_validate_zip_code( zip, country ){
	
	zip = String( zip || '' ).trim( );
	if ( 'function' === typeof wpeasycart_postcode_none && wpeasycart_postcode_none( country ) ) {
		return true; /* 6.0.5: the country has no postcodes */
	}
	
	if( country == "US" )
		return /(^\d{5}$)|(^\d{5}-\d{4}$)/.test( zip );
	else if( country == "AU" )
		return /^([0-9]{4})$/.test( zip );
	else if( country == "CA" ){
		var regex = new RegExp( /^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ]( )?\d[ABCEGHJKLMNPRSTVWXYZ]\d$/i );
    	return regex.test( zip );
	}else if( country == "GB" ){
		var postcode = zip.replace(/\s/g, "");
		var regex = /^(([A-Za-z]{2}[0-9][A-Za-z] ?[0-9][A-Za-z]{2})|([A-Za-z][0-9][A-Za-z] ?[0-9][A-Za-z]{2})|([A-Za-z][0-9] ?[0-9][A-Za-z]{2})|([A-Za-z][0-9]{2} ?[0-9][A-Za-z]{2})|([A-Za-z]{2}[0-9] ?[0-9][A-Za-z]{2})|([A-Za-z]{2}[0-9]{2} ?[0-9][A-Za-z]{2}))$/i;
		return regex.test( postcode );
	}else
		return ec_validate_text( zip );
	
}

/* 6.0.5: with the form's prefix, required = the country has a region list here and its region is not optional ( what
 * ec_validate_address_block() checks ); without one, the older list less the optional countries. */
function ec_is_state_required( country, prefix ){
	if ( wpeasycart_region_optional( country ) )
		return false;
	if ( prefix )
		return !! document.getElementById( prefix + '_state_' + country );
	if( country == "AU" || country == "BR" || country == "CA" || country == "CN" || country == "GB" || country == "IN" || country == "JP" || country == "US" )
		return true;
	else
		return false; 
}

function ec_get_card_type( card_number ){
	
	var num = card_number;
	
	num = num.replace(/[^\d]/g,'');
	
	if( num.match( /^5[1-5]\d{14}$/ ) )														return "mastercard";
	else if( num.match( /^4\d{15}/ ) || num.match( /^4\d{12}/ ) )							return "visa";
	else if( num.match( /(^3[47])((\d{11}$)|(\d{13}$))/ ) )									return "amex";
	else if( num.match( /^6(?:011\d{12}|5\d{14}|4[4-9]\d{13}|22(?:1(?:2[6-9]|[3-9]\d)|[2-8]\d{2}|9(?:[01]\d|2[0-5]))\d{10})$/ ) )									
																							return "discover";
	else if( num.match( /^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/ ) )					return "maestro";
	else if( num.match( /(^(352)[8-9](\d{11}$|\d{12}$))|(^(35)[3-8](\d{12}$|\d{13}$))/ ) )	return "jcb";
	else if( num.match( /(^(30)[0-5]\d{11}$)|(^(36)\d{12}$)|(^(38[0-8])\d{11}$)/ ) )		return "diners";
	else																					return "all";
		
}

function ec_validate_credit_card( card_number ){
	
	var card_type = ec_get_card_type( card_number );
	
	if( card_type == "visa" || card_type == "delta" || card_type == "uke" ){
		if( /^4[0-9]{12}(?:[0-9]{3}|[0-9]{6})?$/.test( card_number ) )								return true;
		else 																						return false;
	
	}else if( card_type == "discover" ){
		if( /^6(?:011\d{12}|5\d{14}|4[4-9]\d{13}|22(?:1(?:2[6-9]|[3-9]\d)|[2-8]\d{2}|9(?:[01]\d|2[0-5]))\d{10})$/.test( card_number ) )	
																									return true;
		else																						return false;
	
	}else if( card_type == "mastercard" || card_type == "mcdebit" ){
		if( /^5[1-5]\d{14}$/.test( card_number ) )													return true;
		else																						return false;
	
	}else if( card_type == "amex" ){
		if( /^3[47][0-9]{13}$/.test( card_number ) )												return true;
		else																						return false;
	
	}else if( card_type == "diners" ){
		if( /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/.test( card_number ) )								return true;
		else																						return false;
	
	}else if( card_type == "jcb" ){
		if( /^(?:2131|1800|35\d{3})\d{11}$/.test( card_number ) )											return true;
		else																						return false;
	
	}else if( card_type == "maestro" ){
		if( /(^(5[0678]\d{11,18}$))|(^(6[^0357])\d{11,18}$)|(^(3)\d{13,20}$)/.test( card_number ) )	return true;
		else																						return false;	
	}
}

function ec_validate_security_code( security_code ){
	
	if( /^[0-9]{3,4}$/.test( security_code ) )													return true;
	else																						return false;

}

function ec_show_error( error_field ){
	jQuery( document.getElementById( '' + error_field + '_error' ) ).show( );
}

function ec_hide_error( error_field ){
	jQuery( document.getElementById( '' + error_field + '_error' ) ).hide( );
}

function ec_cart_subscription_shipping_method_change( shipping_method, price, nonce ){
	jQuery( document.getElementById( 'ec_cart_subscription_shipping_methods_loader' ) ).show();

	var ship_express = 0;
	if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) { 
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).is( ':checked' ) ) {
			ship_express = 1;
		}
	}

	if ( 'shipexpress' == shipping_method ) {
		shipping_method = 'standard';
		if ( jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).length ) { 
			jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).prop( 'checked', false );
		}
		jQuery( document.getElementById( 'ec_cart_shipping_method' ) ).prop( 'checked', 'checked' );

	} else if( 'free' == shipping_method ) {
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_ship_express' ) ).prop( 'checked', false );
		}
	}

	var data = {
		action: 'ec_ajax_update_subscription_shipping_method',
		product_id: jQuery( document.getElementById( 'product_id' ) ).val(),
		shipping_method: shipping_method,
		ship_express: ship_express,
		nonce: nonce
	};
	
	jQuery.ajax({
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( response ){
			var data_arr = JSON.parse( response );
			wpeasycart_subscription_cart_update_totals( data_arr );
			jQuery( document.getElementById( 'ec_cart_subscription_shipping_methods_loader' ) ).hide();
		}
	} );
	
}

function ec_cart_shipping_method_change( shipping_method, price, nonce ){
	jQuery( '.ec_cart_shipping_method_row' ).removeClass( 'ec_method_selected' );
	jQuery( '.ec_cart_shipping_method_row > input[value="' + shipping_method + '"]' ).parent().addClass( 'ec_method_selected' );
	jQuery( '.ec_cart_price_row.ec_cart_price_row_shipping_total, .ec_cart_price_row.ec_order_total' ).addClass( 'ec_cart_price_row_loading' );

	var ship_express = 0;
	if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) { 
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).is( ':checked' ) ) {
			ship_express = 1;
		}
	}

	if ( 'shipexpress' == shipping_method ) {
		shipping_method = 'standard';
		if ( jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).length ) { 
			jQuery( document.getElementById( 'ec_cart_shipping_method_free' ) ).prop( 'checked', false );
		}
		jQuery( document.getElementById( 'ec_cart_shipping_method' ) ).prop( 'checked', 'checked' );

	} else if( 'free' == shipping_method ) {
		if ( jQuery( document.getElementById( 'ec_cart_ship_express' ) ).length ) {
			jQuery( document.getElementById( 'ec_cart_ship_express' ) ).prop( 'checked', false );
		}
	}

	var data = {
		action: 'ec_ajax_update_shipping_method',
		shipping_method: shipping_method,
		ship_express: ship_express,
		nonce: nonce
	};
	
	jQuery.ajax({
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){
			var response_obj = JSON.parse( data );
			ec_update_cart( response_obj );
			jQuery( '.ec_cart_price_row.ec_cart_price_row_shipping_total, .ec_cart_price_row.ec_order_total' ).removeClass( 'ec_cart_price_row_loading' );
		}
	} );
	
}

jQuery( document ).ready( function( $ ){
    $( ".ec_menu_vertical" ).accordion({
        accordion:true,
        speed: 500,
        closedSign: '[+]',
        openedSign: '[-]'
    });
});
(function(jQuery){
    jQuery.fn.extend({
    accordion: function(options) {
        
		var defaults = {
			accordion: 'true',
			speed: 300,
			closedSign: '[+]',
			openedSign: '[-]'
		};
		var opts = jQuery.extend(defaults, options);
 		var jQuerythis = jQuery(this);
 		jQuerythis.find("li").each(function() {
 			if(jQuery(this).find("ul").size() != 0){
 				jQuery(this).find("a:first").append("<span>"+ opts.closedSign +"</span>");
 				if(jQuery(this).find("a:first").attr('href') == "#"){
 		  			jQuery(this).find("a:first").click(function(){return false;});
 		  		}
 			}
 		});
 		jQuerythis.find("li.active").each(function() {
 			jQuery(this).parents("ul").slideDown(opts.speed);
 			jQuery(this).parents("ul").parent("li").find("span:first").html(opts.openedSign);
 		});
  		jQuerythis.find("li a").click(function() {
  			if(jQuery(this).parent().find("ul").size() != 0){
  				if(opts.accordion){
  					if(!jQuery(this).parent().find("ul").is(':visible')){
  						parents = jQuery(this).parent().parents("ul");
  						visible = jQuerythis.find("ul:visible");
  						visible.each(function(visibleIndex){
  							var close = true;
  							parents.each(function(parentIndex){
  								if(parents[parentIndex] == visible[visibleIndex]){
  									close = false;
  									return false;
  								}
  							});
  							if(close){
  								if(jQuery(this).parent().find("ul") != visible[visibleIndex]){
  									jQuery(visible[visibleIndex]).slideUp(opts.speed, function(){
  										jQuery(this).parent("li").find("span:first").html(opts.closedSign);
  									});
  									
  								}
  							}
  						});
  					}
  				}
  				if(jQuery(this).parent().find("ul:first").is(":visible")){
  					jQuery(this).parent().find("ul:first").slideUp(opts.speed, function(){
  						jQuery(this).parent("li").find("span:first").delay(opts.speed).html(opts.closedSign);
  					});
  					
  					
  				}else{
  					jQuery(this).parent().find("ul:first").slideDown(opts.speed, function(){
  						jQuery(this).parent("li").find("span:first").delay(opts.speed).html(opts.openedSign);
  					});
  				}
  			}
  		});
    }
});
})(jQuery);

function ec_cart_widget_click( ){
	if( !jQuery('.ec_cart_widget_minicart_wrap').is(':visible') ) 
		jQuery('.ec_cart_widget_minicart_wrap').fadeIn( 200 );
	else
		jQuery('.ec_cart_widget_minicart_wrap').fadeOut( 100 );
}

function ec_cart_widget_mouseover( ){
	if( !jQuery('.ec_cart_widget_minicart_wrap').is(':visible') ){
		jQuery('.ec_cart_widget_minicart_wrap').fadeIn( 200 );
		jQuery('.ec_cart_widget_minicart_bg').css( "display", "block" );
	}
}

function ec_cart_widget_mouseout( ){
	if( jQuery('.ec_cart_widget_minicart_wrap').is(':visible') ) {
		jQuery('.ec_cart_widget_minicart_wrap').fadeOut( 100 );
		jQuery('.ec_cart_widget_minicart_bg').css( "display", "none" );
	}
}

var wpeasycart_last_search = "";
function ec_live_search_update( nonce ){
	
	var code = event.which || event.keyCode;
	var search_val = jQuery( '.ec_search_input' ).val( );
	
	if( code != 16 && code != 17 && code != 18 && code != 20 && code != 37 && code != 38 && code != 39 && code != 40 && wpeasycart_last_search != search_val ){
		
		wpeasycart_last_search = search_val;
		
		var data = {
			action: 'ec_ajax_live_search',
			search_val: search_val,
			nonce: nonce
		};
		
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){
				if( wpeasycart_last_search == search_val ){
					data = JSON.parse( data );
					jQuery( document.getElementById( 'ec_search_suggestions' ) ).empty( );
					for( var i=0; i<data.length; i++ ){
						jQuery( document.getElementById( 'ec_search_suggestions' ) ).append( "<option value='" + data[i].title + "'>" );
					}
				}
			}
		} );
	}
}

function ec_account_forgot_password_button_click() {
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_account_forgot_password_email' ) ).val();
	if ( ! ec_validate_email( email ) ) {
		errors = true;
		ec_show_error( 'ec_account_forgot_password_email' );
	} else {
		ec_hide_error( 'ec_account_forgot_password_email' );
	}
	return( ! errors );
}

function ec_account_register_button_click2() {
	var top_half = ec_account_register_button_click( );
	var bottom_half = true;
	
	if( jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).length )
		bottom_half = ec_account_billing_information_update_click( );
	
	var extra_notes_validated = ec_account_register_validate_notes( );
	
	var recaptcha_validated = true;
	if( jQuery( document.getElementById( 'ec_account_register_recaptcha' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', '1px solid red' );
			recaptcha_validated = false;
		}else{
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	if( top_half && bottom_half && extra_notes_validated && recaptcha_validated ){
		return true;
	}else{
		return false;
	}
}

function ec_account_register_button_click( ){
	var email_validated = ec_validate_email_block( 'ec_account_register' );
	var contact_validated = ec_validate_create_account( 'ec_account_register' );
	var terms_validated = ec_validate_terms_section();
	
	var recaptcha_validated = true;
	if( jQuery( document.getElementById( 'ec_account_register_recaptcha' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_register' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', '1px solid red' );
			recaptcha_validated = false;
		}else{
			jQuery( '#ec_account_register_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	if( email_validated && contact_validated && terms_validated && recaptcha_validated )
		return true;
	else
		return false;
	
}

function ec_account_billing_information_update_click( ){
	var address_validated = ec_validate_address_block( 'ec_account_billing_information' );
	
	if( address_validated )
		return true;
	else
		return false;
	
}

function ec_account_shipping_information_update_click( ){
	var address_validated = ec_validate_address_block( 'ec_account_shipping_information' );
	
	if( address_validated )
		return true;
	else
		return false;
	
}

function ec_account_personal_information_update_click( ){
	
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_account_personal_information_email' ) ).val( );
	
	if( jQuery( document.getElementById( 'ec_account_personal_information_first_name' ) ).length && !ec_validate_text( jQuery( document.getElementById( 'ec_account_personal_information_first_name' ) ).val( ) ) ){
		errors = true;
		ec_show_error( 'ec_account_personal_information_first_name' );
	}else{
		ec_hide_error( 'ec_account_personal_information_first_name' );
	}
	
	if( jQuery( document.getElementById( 'ec_account_personal_information_last_name' ) ).length && !ec_validate_text( jQuery( document.getElementById( 'ec_account_personal_information_last_name' ) ).val( ) ) ){
		errors = true;
		ec_show_error( 'ec_account_personal_information_last_name' );
	}else{
		ec_hide_error( 'ec_account_personal_information_last_name' );
	}
	
	if( !ec_validate_email( email ) ){
		errors = true;
		ec_show_error( 'ec_account_personal_information_email' );
	}else{
		ec_hide_error( 'ec_account_personal_information_email' );
	}
	
	return( !errors );
}

function ec_account_password_button_click( ){
	
	var errors = false;
	var current_password = jQuery( document.getElementById( 'ec_account_password_current_password' ) ).val( );
	var new_password = jQuery( document.getElementById( 'ec_account_password_new_password' ) ).val( );
	var retype_password = jQuery( document.getElementById( 'ec_account_password_retype_new_password' ) ).val( );
	
	if( !ec_validate_password( current_password ) ){
		errors = true;
		ec_show_error( 'ec_account_password_current_password' );
	}else{
		ec_hide_error( 'ec_account_password_current_password' );
	}
	
	if( !ec_validate_password( new_password ) ){
		errors = true;
		ec_show_error( 'ec_account_password_new_password' );
	}else{
		ec_hide_error( 'ec_account_password_new_password' );
	}
	
	if( !ec_validate_match( new_password, retype_password ) ){
		errors = true;
		ec_show_error( 'ec_account_password_retype_new_password' );
	}else{
		ec_hide_error( 'ec_account_password_retype_new_password' );
	}
	
	return( !errors );
	
}

function ec_account_register_validate_notes( ){
	if( !jQuery( document.getElementById( 'ec_account_register_user_notes' ) ).length || ( jQuery( document.getElementById( 'ec_account_register_user_notes' ) ).length && jQuery( document.getElementById( 'ec_account_register_user_notes' ) ).val( ) != "" ) ){
		ec_hide_error( 'ec_account_register_user_notes' );
		return true;
	}else{
		ec_show_error( 'ec_account_register_user_notes' );
		return false;
	}
}

/* 6.0.2: the Elementor Connect Order form's check ( its button called this, which did not exist ). */
function ec_account_connect_order_button_click( button ){
	/* 6.0.2: the clicked button's own form ( two Connect Order forms on one page each check their own field ); called with no
	 * button, the page's first form, as before. */
	var form = ( button && button.form ) ? jQuery( button.form ) : null;
	var input = ( form ) ? form.find( 'input[name="ec_account_connect_order_id"]' ).first( ) : jQuery( document.getElementById( 'ec_account_connect_order_id' ) );
	var error_row = ( form ) ? form.find( '[id="ec_account_connect_order_id_error"]' ) : jQuery( document.getElementById( 'ec_account_connect_order_id_error' ) );
	var order_id = input.val( );
	if ( ! order_id || ! /^\s*[0-9]+\s*$/.test( order_id ) ) {
		error_row.show( );
		return false;
	}
	error_row.hide( );
	return true;
}

function ec_account_login_button_click( ){
	
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_account_login_email' ) ).val( );
	var password = jQuery( document.getElementById( 'ec_account_login_password' ) ).val( );
	
	if( !ec_validate_email( email ) ){
		errors = true;
		ec_show_error( 'ec_account_login_email' );
	}else{
		ec_hide_error( 'ec_account_login_email' );
	}
	
	if( !ec_validate_text( password ) ){
		errors = true;
		ec_show_error( 'ec_account_login_password' );
	}else{
		ec_hide_error( 'ec_account_login_password' );
	}
	
	if( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_login' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', '1px solid red' );
			errors = true;
		}else{
			jQuery( '#ec_account_login_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	return ( !errors );

}

function ec_account_login_widget_button_click( ){
	
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_account_login_widget_email' ) ).val( );
	var password = jQuery( document.getElementById( 'ec_account_login_widget_password' ) ).val( );
	
	if( !ec_validate_email( email ) ){
		errors = true;
		ec_show_error( 'ec_account_login_widget_email' );
	}else{
		ec_hide_error( 'ec_account_login_widget_email' );
	}
	
	if( !ec_validate_text( password ) ){
		errors = true;
		ec_show_error( 'ec_account_login_widget_password' );
	}else{
		ec_hide_error( 'ec_account_login_widget_password' );
	}
	
	if( jQuery( document.getElementById( 'ec_account_login_widget_recaptcha' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_login_widget' ) ).val( );
		if( ! recaptcha_response.length ){
			jQuery( '#ec_account_login_widget_recaptcha > div' ).css( 'border', '1px solid red' );
			errors = true;
		}else{
			jQuery( '#ec_account_login_widget_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	return ( !errors );

}

function ec_close_popup_newsletter( nonce ){
	
	jQuery( '.ec_newsletter_container' ).fadeOut( 'slow' );
	
	var data = {
		action: 'ec_ajax_close_newsletter',
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

function ec_submit_newsletter_signup( nonce ){
	
	jQuery( '.ec_newsletter_pre_submit' ).hide( );
	jQuery( '.ec_newsletter_post_submit' ).show( );
		
	var email_address = jQuery( document.getElementById( 'ec_newsletter_email' ) ).val( );
	var newsletter_name = "";
	if( document.getElementById( 'ec_newsletter_name' ) )
		newsletter_name = jQuery( document.getElementById( 'ec_newsletter_name' ) ).val( );
	
	var data = {
		action: 'ec_ajax_submit_newsletter_signup',
		email_address: email_address,
		newsletter_name: newsletter_name,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

function ec_submit_newsletter_signup_widget( nonce ) {
	jQuery( '.ec_newsletter_pre_submit' ).hide( );
	jQuery( '.ec_newsletter_post_submit' ).show( );
		
	var email_address = jQuery( '#ec_newsletter_email_widget' ).val( );
	var newsletter_name = "";
	if( document.getElementById( 'ec_newsletter_name_widget' ) )
		newsletter_name = jQuery( document.getElementById( 'ec_newsletter_name_widget' ) ).val( );
	
	var data = {
		action: 'ec_ajax_submit_newsletter_signup',
		email_address: email_address,
		newsletter_name: newsletter_name,
		nonce: nonce
	};
	
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

function update_download_count( orderdetail_id ){
	
	if( jQuery( document.getElementById( 'ec_download_count_' + orderdetail_id ) ).length ){
		var count = Number( jQuery( document.getElementById( 'ec_download_count_' + orderdetail_id ) ).html( ) );
		var max_count = Number( jQuery( document.getElementById( 'ec_download_count_max_' + orderdetail_id ) ).html( ) );
		if( count < max_count ){
			count++;
			jQuery( document.getElementById( 'ec_download_count_' + orderdetail_id ) ).html( count );
		}
	}
	
}

function ec_show_update_subscription_payment() {
	jQuery( '.ec_account_subscription_details_payment_form' ).show();
	jQuery( '.ec_account_subscription_details_card_change' ).hide();
	jQuery( '.ec_account_subscription_upgrade_row' ).hide();
	jQuery( '.ec_account_subscription_details_plan_change' ).show();
	return false;
}

function ec_show_update_subscription_details() {
	jQuery( '.ec_account_subscription_details_payment_form' ).hide();
	jQuery( '.ec_account_subscription_details_card_change' ).show();
	jQuery( '.ec_account_subscription_upgrade_row' ).show();
	jQuery( '.ec_account_subscription_details_plan_change' ).hide();
	return false;
}

function show_billing_info( ){
	jQuery( document.getElementById( 'ec_account_subscription_billing_information' ) ).slideToggle(600);
	return false;
}

function ec_update_subscription_info( subscription_id, nonce ){
	jQuery( document.getElementById( 'stripe-success-cover' ) ).show( );
	var data = {
		action: 'ec_ajax_stripe_update_customer_subscription_plan',
		language: wpeasycart_ajax_object.current_language,
		subscription_id: subscription_id,
		quantity: jQuery( document.getElementById( 'ec_quantity_' + subscription_id ) ).val(),
		ec_selected_plan: jQuery( document.getElementById( 'ec_selected_plan' ) ).val( ),
		nonce: nonce
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( result ){
			var json = JSON.parse( result );
			jQuery( location ).attr( 'href', json.url );
		}
	} );
	return false;
}

function ec_check_update_subscription_info( ){
		
	if( jQuery( document.getElementById( 'ec_account_subscription_billing_information' ) ).is(":visible") ){
		
		var address_validated = ec_validate_address_block( 'ec_account_billing_information' );
		var payment_method_complete = ec_validate_payment_method( );
		var terms_complete = ec_validate_terms( );
		
		if( address_validated && payment_method_complete && terms_complete )
			return true;
		else
			return false;
			
	}else{
		return true;
	}
}

function ec_cancel_subscription_check( confirm_text ){
	return confirm( confirm_text );
}

function ec_details_show_inquiry_form( product_id ){
	jQuery( '.ec_details_inquiry_popup_' + product_id ).fadeIn( 'fast' );
	return false;
}

function ec_details_hide_inquiry_popup( product_id ){
	jQuery( '.ec_details_inquiry_popup_' + product_id ).fadeOut( 'fast' );
}

function ec_details_show_image_popup( model_number ){
	jQuery( document.getElementById( 'ec_details_large_popup_' + model_number ) ).show( );
	jQuery( 'html' ).css( 'overflow', 'hidden' );
}

function ec_details_hide_large_popup( model_number ){
	jQuery( document.getElementById( 'ec_details_large_popup_' + model_number ) ).hide( );
	jQuery( 'html' ).css( 'overflow', 'scroll' );
}

function ec_create_ideal_order_redirect( source, nonce ){
	var redirect = source.redirect.url;
	var data = {
		action: 'ec_ajax_create_stripe_ideal_order',
		source: source,
		nonce: nonce
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ) {
			window.location.href = redirect;
		}
	} );
}

function ec_stripe_check_order_status( source, nonce, count = 1 ) {
	/* 6.0.2: ask again after a pause that grows ( 1 s, 2 s … 5 s ) and stop after 20 tries ( about a minute and a half ),
	   leaving the checkout usable; it used to ask again at once, for ever, from an open tab. */
	var again = function() {
		if ( count >= 20 ) {
			jQuery( '.wpeasycart-stripe-already-paid' ).remove();
			return;
		}
		setTimeout( function() {
			ec_stripe_check_order_status( source, nonce, count + 1 );
		}, Math.min( 1000 * count, 5000 ) );
	};
	var data = {
		action: 'ec_ajax_stripe_check_order_status',
		source: source,
		nonce: nonce,
		count: count,
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){
			var response_obj = null;
			try {
				response_obj = JSON.parse( data );
			} catch ( e ) {
				response_obj = null;
			}
			if ( ! response_obj || 'requires_confirmation' == response_obj.status ) {
				again();
			} else if ( response_obj.redirect ) {
				window.location.href = response_obj.redirect;
			} else {
				jQuery( '.wpeasycart-stripe-already-paid' ).remove();
			}
		},
		error: again
	} );
}

function ec_notify_submit( product_id, rand_id, nonce ){
	var errors = false;
	var email = jQuery( document.getElementById( 'ec_email_notify_' + product_id + '_' + rand_id ) ).val( );
	if( !ec_validate_email( email ) ){
		errors = true;
		jQuery( document.getElementById( 'ec_email_notify_' + product_id + '_' + rand_id + '_error' ) ).show( );
	}else{
		jQuery( document.getElementById( 'ec_email_notify_' + product_id + '_' + rand_id + '_error' ) ).hide( );
	}
	
	var recaptcha_response = false;
	if( jQuery( document.getElementById( 'ec_grecaptcha_response_product_details' ) ).length ){
		recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_product_details' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_product_details_recaptcha > div' ).css( 'border', '1px solid red' );
			errors = true;
		}else{
			jQuery( '#ec_product_details_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	
	if( !errors ){
		jQuery( document.getElementById( 'ec_product_details_stock_notify_' + product_id + '_' + rand_id + '_loader_cover' ) ).show( );
		jQuery( document.getElementById( 'ec_product_details_stock_notify_' + product_id + '_' + rand_id + '_loader' ) ).show( );
		var data = {
			action: 'ec_ajax_subscribe_to_stock_notification',
			email: email,
			product_id: product_id,
			recaptcha_response: recaptcha_response,
			nonce: nonce
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){
				jQuery( document.getElementById( 'ec_product_details_stock_notify_complete_' + product_id + '_' + rand_id ) ).show( );
				jQuery( document.getElementById( 'ec_product_details_stock_notify_' + product_id + '_' + rand_id ) ).hide( );
				jQuery( document.getElementById( 'ec_product_details_stock_notify_' + product_id + '_' + rand_id + '_loader_cover' ) ).hide( );
				jQuery( document.getElementById( 'ec_product_details_stock_notify_' + product_id + '_' + rand_id + '_loader' ) ).hide( );
			}
		} );
	}
	
}

function wpeasycart_load_cart( cart_page, success_code, error_code, language = 'NONE', nonce ){
	if( language == 'NONE' ){
	   language = wpeasycart_ajax_object.current_language;
	}
	var data = {
		action: 'ec_ajax_get_dynamic_cart_page',
		cart_page: cart_page,
		success_code: success_code,
		error_code: error_code,
		language: language,
		nonce: nonce
	};
	/* 6.0.5: the subscription page's usage level from the page's address ( a pricing table's or the order summary's ec_level / ec_units ) */
	var wpec_holder = document.getElementById( 'wpeasycart_cart_holder' );
	if ( wpec_holder && wpec_holder.getAttribute( 'data-level' ) ) {
		data.ec_level = wpec_holder.getAttribute( 'data-level' );
	}
	if ( wpec_holder && wpec_holder.getAttribute( 'data-units' ) ) {
		data.ec_units = wpec_holder.getAttribute( 'data-units' );
	}
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			jQuery( document.getElementById( 'wpeasycart_cart_holder' ) ).replaceWith( data );
			wp_easycart_init_location_buttons();
			wpeasycart_address_fields(); /* 6.0.5 */
		}
	} );
}

function wpeasycart_load_account( account_page, page_id, success_code, error_code, language = 'NONE', nonce = '', form_only = false ){
	if( language == 'NONE' ){
	   language = wpeasycart_ajax_object.current_language;
	}
	var data = {
		action: 'ec_ajax_get_dynamic_account_page',
		account_page: account_page,
		page_id: page_id,
		success_code: success_code,
		error_code: error_code,
		language: language,
		nonce: nonce,
		form_only: form_only
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			jQuery( document.getElementById( 'wpeasycart_account_holder' ) ).replaceWith( data );
			wpeasycart_init( document ); /* 6.0.2: sets up only what the account page just drew ( order paging ) */
			wpeasycart_address_fields(); /* 6.0.5 */
		}
	} );
}

function wpeasycart_update_tip( tip_rate, nonce ){
	jQuery( '.ec_cart_tip_item' ).removeClass( 'ec_tip_selected' );
	var tip_amount = Number( jQuery( document.getElementById( 'ec_cart_tip_custom' ) ).val( ) );
	jQuery( document.getElementById( 'ec_apply_tip_button' ) ).hide( );
	jQuery( document.getElementById( 'ec_applying_tip' ) ).show( );
	var data = {
		action: 'ec_ajax_update_tip_amount',
		tip_rate: tip_rate,
		tip_amount: tip_amount,
		language: wpeasycart_ajax_object.current_language,
		nonce: nonce
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){ 
			var response_obj = JSON.parse( data );
			ec_update_cart( response_obj );
			jQuery( document.getElementById( 'ec_apply_tip_button' ) ).show( );
			jQuery( document.getElementById( 'ec_applying_tip' ) ).hide( );
		}
	} );
}

function wp_easycart_text_notification_subscribe( wpeasycart_text_iti ) {
	var isValid = wpeasycart_text_iti.isValidNumber();
	if( ! isValid ) {
		jQuery( '#text_notification_error' ).show();
		jQuery( document.getElementById( 'text_notification_success' ) ).hide();
		return;
	} else {
		jQuery( '#text_notification_error' ).hide();
	}
	jQuery( document.getElementById( 'text_notification_loader' ) ).show();
	var text_number = wpeasycart_text_iti.getNumber();
	var order_id = jQuery( document.getElementById( 'text_order_id' ) ).val( );
	var data = {
		action: 'ec_ajax_subscribe_text_notification',
		text_number: text_number,
		order_id: order_id
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function( data ){
			jQuery( document.getElementById( 'text_notification_loader' ) ).hide();
			jQuery( document.getElementById( 'text_notification_success' ) ).fadeIn( 'fast' );
			jQuery( document.getElementById( 'text_phone_number' ) ).val( '' )
		}
	} );
}
/*
 * 6.0.5: the product page shows what the cart charges. wpeasycart_details_line() prices the line the cart will make for this
 * form's choices by the cart's own rules ( ec_cartitem::__construct(), then ec_cart::make_vat_adjustments() and
 * ec_promotion::apply_promotion() ):
 * - a variation choice adds its own price ( the cart charges no one-time price, override or multiplier on one ); a variant with
 *   a price of its own replaces the price and those adds, a variant's SKU the model number and the choices' SKU endings;
 * - modifiers in option order, rows hidden by their conditions included ( the form still sends them ): a choice's price, else
 *   its one-time price, else its override ( a later one replaces an earlier one ); multipliers multiply together; a price per
 *   character counts the bytes of what is sent without any white space; a number box multiplies its price and multiplier by
 *   its number ( 0 included ); a size scales the price only, not the adds; a grid row adds price × its quantity, else its
 *   one-time price, else ( override − the price ) × its quantity, else sets the grid's change to the price × ( multiplier − 1 );
 * - a donation, else the shopper's role price, else a volume price the quantity reaches, replaces the price ( the adds stay );
 * - prices that include VAT move to the shopper's rate, then classic promotions come off the whole unit.
 * Its data is wpeasycart_price_data_<product>_<rand> ( wp_easycart_product_pricing ); a template copy without it prices from
 * the page's hidden fields ( the price there already has the role price, promotion and VAT rate in it ).
 * area_one: price a size as one unit of area ( 12 × 12 inches, 1000 × 1000 mm ), for the price under the title.
 */
function wpeasycart_details_line( product_id, rand_id, area_one ) {
	var key = product_id + '_' + rand_id;
	var data = window[ 'wpeasycart_price_data_' + key ];
	data = ( data && 'object' == typeof data ) ? data : null;
	var num = function( value ) {
		value = Number( value );
		return isFinite( value ) ? value : 0;
	};
	var line = { unit: 0, quantity: 1, onetime: 0, grid: 0, total: 0, has_grid: false, has_area: false, sku: '', key: '', all_chosen: false, priced: true };
	var default_price = document.getElementById( 'ec_default_price_' + key );
	if ( ! data && ! default_price ) {
		line.priced = false; /* the page hides this product's price */
	}
	var quantity_box = document.getElementById( 'ec_quantity_' + key );
	var quantity = quantity_box ? parseInt( quantity_box.value, 10 ) : 1;
	quantity = ( quantity > 0 ) ? quantity : 1;
	var price = data ? num( data.price ) : num( default_price ? default_price.value : 0 );
	var product_price = price;
	var options_price = 0;
	var onetime = 0;
	var grid = 0;
	var grid_quantity = 0;
	var multiplier = 0;
	var ext = data ? String( data.ext || '' ) : '';
	var sku = data ? String( data.model || '' ) : String( jQuery( document.getElementById( 'ec_default_sku_' + key ) ).val() || '' );
	var item_of = function( id, el ) {
		var item = ( data && data.items && data.items[ id ] ) ? data.items[ id ] : null;
		if ( item ) {
			return { p: num( item.p ), t: num( item.t ), v: ( undefined === item.v ) ? -1 : num( item.v ), m: num( item.m ), c: num( item.c ), n: num( item.n ), s: String( item.s || '' ) };
		}
		var attr = function( name, fallback ) {
			var value = el ? el.getAttribute( 'data-optionitem-' + name ) : null;
			return ( null === value || '' === value ) ? fallback : num( value );
		};
		return { p: attr( 'price', 0 ), t: attr( 'price-onetime', 0 ), v: attr( 'price-override', -1 ), m: attr( 'price-multiplier', 0 ), c: attr( 'price-per-character', 0 ), n: 0, s: '' };
	};

	/* Variation sets: each choice's own price, its SKU ending. */
	var ids = [ 0, 0, 0, 0, 0 ];
	var chosen = 0;
	for ( var slot = 1; slot <= 5; slot++ ) {
		var choice = wpeasycart_details_basic_choice( product_id, rand_id, slot );
		if ( choice ) {
			var basic = item_of( choice.id, choice.el );
			ids[ slot - 1 ] = choice.id;
			chosen++;
			options_price += basic.p;
			if ( '' != basic.s ) {
				sku += ext + basic.s;
			}
		}
	}
	var basic_block = jQuery( '.ec_details_options_basic[data-product-id="' + product_id + '"][data-rand-id="' + rand_id + '"]' );
	var total_basic = ( basic_block.length ) ? basic_block.find( '.ec_details_option_row' ).length : jQuery( '.ec_details_options_basic .ec_details_option_row' ).length;
	line.all_chosen = ( total_basic == chosen );
	line.key = ids.join( '' );
	var variant = null;
	if ( data ) {
		/* the five ids with dashes: 1 + 23 and 12 + 3 are two variants */
		var row = ( data.variants ) ? data.variants[ ids.join( '-' ) ] : null;
		if ( row ) {
			variant = { price: num( row[0] ), sku: String( row[1] || '' ) };
		}
	} else if ( line.all_chosen && window[ 'varitation_data_' + key ] && window[ 'varitation_data_' + key ][ line.key ] ) {
		variant = { price: num( window[ 'varitation_data_' + key ][ line.key ].price ), sku: String( window[ 'varitation_data_' + key ][ line.key ].sku || '' ) };
	}
	if ( variant ) {
		if ( -1 != variant.price ) {
			options_price = 0;
			price = variant.price;
		}
		if ( '' != variant.sku ) {
			sku = variant.sku;
		}
	}

	/* Modifiers, in option order. */
	wpeasycart_details_scope( product_id, rand_id ).find( '.ec_details_option_row[data-product-option-id]' ).each( function() {
		var row_el = jQuery( this );
		if ( row_el.attr( 'data-product-id' ) && String( row_el.attr( 'data-product-id' ) ) != String( product_id ) ) {
			return;
		}
		var type_match = /(?:^|\s)ec_option_type_([a-z0-9]+)/.exec( this.className );
		var type = type_match ? type_match[1] : '';
		var picks = wpeasycart_details_option_picks( row_el, type, row_el.attr( 'data-product-option-id' ), data );
		for ( var i = 0; i < picks.length; i++ ) {
			var pick = picks[ i ];
			if ( pick.el && pick.el.getAttribute && pick.el.getAttribute( 'data-product-id' ) && String( pick.el.getAttribute( 'data-product-id' ) ) != String( product_id ) ) {
				continue; /* another product's form ( a template copy without the scope markers ) */
			}
			if ( 'dimensions1' == type || 'dimensions2' == type ) {
				line.has_area = true;
				if ( ! area_one ) {
					price = price * pick.area;
				}
				continue;
			}
			var item = item_of( pick.id, pick.el );
			if ( '' != item.s ) {
				sku += ext + item.s;
			}
			if ( 'grid' == type ) {
				line.has_grid = true;
				grid_quantity += pick.value;
				if ( 0 != item.p ) {
					grid += item.p * pick.value;
				} else if ( 0 != item.t ) {
					grid += item.t;
				} else if ( item.v >= 0 ) {
					grid += ( item.v - price ) * pick.value;
				} else if ( item.m > 1 ) {
					grid = price * ( item.m - 1 );
				}
			} else if ( 'number' == type ) {
				var amount = parseFloat( pick.value );
				amount = isFinite( amount ) ? amount : 0;
				if ( 0 != item.p ) {
					options_price += item.p * amount;
				} else if ( 0 != item.t ) {
					onetime += item.t;
				} else if ( item.v >= 0 ) {
					price = item.v;
				}
				if ( 0 != item.m ) {
					multiplier = ( ( 0 == multiplier ) ? 1 : multiplier ) * item.m * amount;
				}
			} else {
				if ( 0 != item.p ) {
					options_price += item.p;
				} else if ( 0 != item.t ) {
					onetime += item.t;
				} else if ( item.v >= 0 ) {
					price = item.v;
				}
				if ( 0 != item.m ) {
					multiplier = ( ( 0 == multiplier ) ? 1 : multiplier ) * item.m;
				}
				if ( item.c > 0 ) {
					options_price += item.c * ( pick.typed ? wpeasycart_details_bytes( pick.value ) : item.n );
				}
			}
		}
	} );
	if ( grid_quantity > 0 ) {
		quantity = grid_quantity;
	}

	/* The unit price. */
	var unit = price + options_price;
	var donation_box = document.getElementById( 'ec_donation_amount_' + key );
	if ( donation_box ) {
		unit = Math.max( 0, product_price, num( donation_box.value ) ) + options_price;
	} else if ( data && null !== data.role && undefined !== data.role ) {
		unit = num( data.role ) + options_price;
	} else {
		var tier_quantities = data ? [] : ( window[ 'tier_quantities_' + key ] || [] );
		var tier_prices = data ? [] : ( window[ 'tier_prices_' + key ] || [] );
		if ( data && data.tiers ) {
			for ( var t = 0; t < data.tiers.length; t++ ) {
				tier_quantities.push( data.tiers[ t ][0] );
				tier_prices.push( data.tiers[ t ][1] );
			}
		}
		for ( var j = 0; j < tier_quantities.length; j++ ) {
			if ( quantity >= num( tier_quantities[ j ] ) ) {
				unit = num( tier_prices[ j ] ) + options_price;
			}
		}
	}
	if ( multiplier > 0 ) {
		unit = unit * multiplier;
	}
	var total = ( unit * quantity ) + onetime + grid;

	/* Prices that include VAT, at the shopper's rate ( ec_cart::make_vat_adjustments() ). */
	if ( data && data.vat ) {
		var included = num( data.vat[0] ) / 100;
		var rate = num( data.vat[1] ) / 100;
		unit = wpeasycart_price_round( unit / ( included + 1 ), 2 ) * ( 1 + rate );
		total = wpeasycart_price_round( total / ( included + 1 ), 2 ) * ( 1 + rate );
	}

	/* Classic promotions on the whole unit ( ec_promotion::apply_promotion() ). */
	if ( data && data.promo && data.promo.length ) {
		var best = 0;
		for ( var r = 0; r < data.promo.length; r++ ) {
			var rule = data.promo[ r ];
			if ( quantity < num( rule[2] ) ) {
				continue;
			}
			var off = ( 0 != num( rule[0] ) ) ? num( rule[0] ) : ( ( 0 != num( rule[1] ) ) ? unit * num( rule[1] ) / 100 : 0 );
			if ( off > best ) {
				best = off;
			}
		}
		if ( best > 0 ) {
			unit = Math.max( 0, wpeasycart_price_round( unit - best, num( data.dec ) ) );
			total = ( unit * quantity ) + onetime + grid;
		}
	}

	line.unit = unit;
	line.quantity = quantity;
	line.onetime = onetime;
	line.grid = grid;
	line.total = total;
	line.sku = sku;
	return line;
}

/* 6.0.5: the choice made in a variation set ( slot 1 to 5 ), as the form sends it: { id, el } or null. */
function wpeasycart_details_basic_choice( product_id, rand_id, slot ) {
	var key = product_id + '_' + rand_id;
	var swatch = jQuery( '.ec_details_swatches > li.ec_option' + slot + '_' + key + '.ec_selected, .ec_details_swatches_ele > li.ec_option' + slot + '_' + key + '.ec_selected' ).first();
	if ( swatch.length ) {
		var id = parseInt( jQuery( document.getElementById( 'ec_option' + slot + '_' + key ) ).val(), 10 ) || parseInt( swatch.attr( 'data-optionitem-id' ), 10 ) || 0;
		return ( id > 0 ) ? { id: id, el: swatch.get( 0 ) } : null;
	}
	var combo = jQuery( '.ec_details_combo.ec_option' + slot + '_' + key ).first();
	if ( combo.length && parseInt( combo.val(), 10 ) > 0 ) {
		return { id: parseInt( combo.val(), 10 ), el: combo.find( 'option:selected' ).get( 0 ) };
	}
	return null;
}

/*
 * 6.0.5: a size row ( dimensions1 / dimensions2 ) as the cart reads it ( wp_easycart_size_option::state() ):
 * { state, area, sides }. state 'size' when its width and height are each a positive number, with area the price is scaled by
 * ( square feet, or square metres on a metric store ); 'empty' when nothing is typed in the width or the height ( the cart leaves
 * the size out ); 'invalid' when a box holds 0, a negative number or words, or the area is 0 or too big to work out ( the cart
 * refuses the add ). A side is the number it starts with after sanitize_text_field()'s clean-up ( '24"' is 24, PHP's arithmetic ),
 * with fractions its whole inches ( intval() ) plus the fraction; sides holds each side's length, null ( nothing typed ) or false.
 */
function wpeasycart_details_size_state( row, product_option_id, data ) {
	var field = function( part ) {
		var found = row.find( '[name="ec_option_adv_' + product_option_id + '_' + part + '"]' ).first();
		return ( found.length ) ? found : row.find( '[name="ec_option_' + row.attr( 'data-option-id' ) + '_' + part + '"]' ).first(); /* the older name the cart also reads */
	};
	var side = function( input, part ) {
		if ( ! input.length ) {
			return null;
		}
		var typed = wpeasycart_details_text_value( null, input.val() );
		if ( '' === typed ) {
			return null;
		}
		var match = /^[ \t\n\r\x0B\f]*([+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)/.exec( typed );
		var number = match ? parseFloat( match[1] ) : NaN;
		if ( ! isFinite( number ) || number < 0 ) {
			return false;
		}
		var length = part.length ? Math.floor( number ) + ec_details_get_sub_dimension_decimal( part.val() ) : number;
		return ( isFinite( length ) && length > 0 ) ? length : false;
	};
	var width = field( 'width' );
	var sides = [ side( width, field( 'sub_width' ) ), side( field( 'height' ), field( 'sub_height' ) ) ];
	var size = { state: 'empty', area: 0, sides: sides };
	if ( false === sides[0] || false === sides[1] ) {
		size.state = 'invalid';
	} else if ( null !== sides[0] && null !== sides[1] ) {
		var metric = ( data && undefined !== data.metric ) ? ( 1 == data.metric ) : ( '1' == width.attr( 'data-is-metric' ) );
		var per = metric ? 1000 : 12;
		var area = ( sides[0] / per ) * ( sides[1] / per );
		if ( isFinite( area ) && area > 0 ) {
			size.state = 'size';
			size.area = area;
		} else {
			size.state = 'invalid';
		}
	}
	return size;
}

/*
 * 6.0.5: a size row's error ( the option's own error text ), for the add to cart ( adding ) and as one of its boxes changes. A box
 * holding 0, a negative number or words always shows it, since the cart refuses that size; a required size shows it on the add
 * while it is not a whole size. A box that changes to a whole size, or to nothing on a size that is not required, takes it away.
 * Boxes that are not a positive number get aria-invalid. Returns 1 when the add must stop.
 */
function wpeasycart_details_size_check( row, product_id, rand_id, adding ) {
	var product_option_id = row.attr( 'data-product-option-id' );
	var error_id = 'ec_details_adv_option_row_error_' + product_option_id + '_' + product_id + '_' + rand_id;
	var error_row = jQuery( document.getElementById( error_id ) );
	var size = wpeasycart_details_size_state( row, product_option_id, window[ 'wpeasycart_price_data_' + product_id + '_' + rand_id ] );
	var required = ( '1' == row.attr( 'data-option-required' ) );
	var show;
	if ( 'invalid' == size.state ) {
		show = true;
	} else if ( adding ) {
		show = required && 'size' != size.state;
	} else if ( 'size' == size.state || ! required ) {
		show = false;
	} else {
		show = null; /* a required size still being filled in: the add says so */
	}
	row.find( '.ec_dimensions_box' ).each( function( index ) {
		var bad = ( false === size.sides[ index ] ) || ( true === show && required && null === size.sides[ index ] );
		if ( bad ) {
			this.setAttribute( 'aria-invalid', 'true' );
		} else if ( null !== show ) {
			this.removeAttribute( 'aria-invalid' );
		}
	} );
	if ( true === show && ! error_row.length ) {
		/* A store copy of the product template prints no error row for a size that is not required: make one, worded as the templates word it. */
		var missing = ( window.wpeasycart_ajax_object && wpeasycart_ajax_object.missing_option ) ? String( wpeasycart_ajax_object.missing_option ) : '';
		error_row = jQuery( '<div class="ec_details_option_row_error"></div>' ).attr( 'id', error_id ).text( jQuery.trim( missing + ' ' + jQuery.trim( row.find( '.ec_details_option_label' ).first( ).text( ) ) ) ).insertBefore( row );
	}
	if ( true === show ) {
		error_row.show( );
	} else if ( false === show ) {
		error_row.hide( );
	}
	return ( true === show ) ? 1 : 0;
}

/*
 * 6.0.5: what one modifier set's row sends ( ec_cartpage::get_advanced_option_vals(); an empty value is not kept,
 * ec_db::add_option_to_cart() ): [ { id, value, el, typed } ], a size as [ { area } ]. A text, number or date set sends its value
 * once for each of its items.
 */
function wpeasycart_details_option_picks( row, type, product_option_id, data ) {
	var picks = [];
	if ( 'checkbox' == type ) {
		row.find( 'input[type="checkbox"]:checked' ).each( function() {
			picks.push( { id: parseInt( this.getAttribute( 'data-optionitem-id' ), 10 ) || 0, value: this.value, el: this } );
		} );
	} else if ( 'radio' == type ) {
		row.find( 'input[type="radio"]:checked' ).first().each( function() {
			picks.push( { id: parseInt( this.value, 10 ) || 0, value: this.value, el: this } );
		} );
	} else if ( 'combo' == type ) {
		var select = row.find( 'select' ).first();
		if ( select.length && parseInt( select.val(), 10 ) > 0 ) {
			picks.push( { id: parseInt( select.val(), 10 ), value: select.val(), el: select.find( 'option:selected' ).get( 0 ) } );
		}
	} else if ( 'swatch' == type ) {
		row.find( '.ec_details_swatch.ec_selected, .ec_details_swatch_ele.ec_selected' ).first().each( function() {
			picks.push( { id: parseInt( this.getAttribute( 'data-optionitem-id' ), 10 ) || 0, value: '', el: this } );
		} );
	} else if ( 'grid' == type ) {
		row.find( 'input' ).each( function() {
			var quantity = parseInt( this.value, 10 );
			var item = /_(\d+)$/.exec( String( this.name || '' ) );
			if ( quantity > 0 && item ) {
				picks.push( { id: parseInt( item[1], 10 ), value: quantity, el: this } );
			}
		} );
	} else if ( 'dimensions1' == type || 'dimensions2' == type ) {
		/* Only a size the cart keeps scales the price ( 6.0.5: an empty size is left out, and one with a box that is not a positive
		   number is refused, so neither scales anything ). */
		var size = wpeasycart_details_size_state( row, product_option_id, data );
		if ( 'size' == size.state ) {
			picks.push( { area: size.area } );
		}
	} else if ( '' != type ) {
		var input = row.find( 'textarea, input' ).not( '[type="hidden"], [type="button"], [type="submit"]' ).first();
		if ( input.length ) {
			var typed = ( 'text' == type || 'textarea' == type );
			var value = typed ? wpeasycart_details_text_value( input.get( 0 ), input.val() ) : jQuery.trim( String( input.val() || '' ) );
			if ( '' !== value ) {
				var set_items = ( data && data.sets && data.sets[ product_option_id ] ) ? data.sets[ product_option_id ] : [ 0 ];
				for ( var i = 0; i < set_items.length; i++ ) {
					picks.push( { id: set_items[ i ], value: value, el: input.get( 0 ), typed: true } );
				}
			}
		}
	}
	return picks;
}

/* 6.0.5: a text box's value as the cart keeps it ( sanitize_text_field(), then the option's input rules and maximum length ). */
function wpeasycart_details_text_value( el, value ) {
	value = String( null === value || undefined === value ? '' : value ).replace( /<[^>]*>/g, '' );
	while ( /%[a-f0-9]{2}/i.test( value ) ) {
		value = value.replace( /%[a-f0-9]{2}/i, '' );
	}
	value = jQuery.trim( value.replace( /[\r\n\t ]+/g, ' ' ) );
	if ( el && window.wpeasycart_text_input_rules && 'function' == typeof window.wpeasycart_text_input_rules.clean && ( el.getAttribute( 'data-ec-text-case' ) || el.getAttribute( 'data-ec-text-allowed' ) ) ) {
		value = window.wpeasycart_text_input_rules.clean( value, el );
	}
	var max = el ? parseInt( el.getAttribute( 'maxlength' ), 10 ) : 0;
	if ( max > 0 && value.length > max ) {
		value = value.slice( 0, max );
	}
	return jQuery.trim( value );
}

/* 6.0.5: what a price per character counts: the bytes of the text without any white space ( strlen() in UTF-8 ). */
function wpeasycart_details_bytes( value ) {
	value = String( value ).replace( /[ \t\n\v\f\r]+/g, '' );
	var bytes = 0;
	for ( var i = 0; i < value.length; i++ ) {
		var code = value.charCodeAt( i );
		if ( code < 0x80 ) {
			bytes += 1;
		} else if ( code < 0x800 ) {
			bytes += 2;
		} else if ( code >= 0xD800 && code <= 0xDBFF && i + 1 < value.length ) {
			bytes += 4;
			i++;
		} else {
			bytes += 3;
		}
	}
	return bytes;
}

/* 6.0.5: money rounded as PHP's number_format() rounds it ( half away from zero, on the number as written ). */
function wpeasycart_price_round( amount, places ) {
	amount = Number( amount );
	if ( ! isFinite( amount ) ) {
		return 0;
	}
	places = Number( places );
	places = ( places >= 0 ) ? places : 2;
	var factor = Math.pow( 10, places );
	var scaled = Number( ( Math.abs( amount ) * factor ).toPrecision( 15 ) );
	return ( ( amount < 0 ) ? -1 : 1 ) * Math.round( scaled ) / factor;
}

/*
 * 6.0.5: show the line's price. "Your price" is one item as the cart prices it ( a quantity grid: the grid's line, its rows'
 * own charges included ); the price under the title is the same, except a price by area, which stays the price of one unit of
 * area; the one-time price line is the cart's one-time option charges.
 */
function wpeasycart_details_show_price( product_id, rand_id, line ) {
	var key = product_id + '_' + rand_id;
	if ( ! line || ! line.priced ) {
		return;
	}
	var your = line.has_grid ? line.total - line.onetime : line.unit;
	var shown = your;
	if ( line.has_area ) {
		shown = wpeasycart_details_line( product_id, rand_id, true ).unit;
	}
	var vat_added = jQuery( document.getElementById( 'vat_added_' + key ) ).val();
	var vat_rate_multiplier = Number( jQuery( document.getElementById( 'vat_rate_multiplier_' + key ) ).val() );
	if ( ! vat_rate_multiplier ) {
		vat_rate_multiplier = 1;
	}
	jQuery( document.getElementById( 'ec_final_price_' + product_id + '_' + rand_id ) ).html( wpeasycart_final_price_text( product_id, rand_id, line.unit, your ) );
	jQuery( '.ec_details_price.ec_details_single_price > .ec_product_sale_price_' + key + ', .ec_details_price.ec_details_single_price > .ec_product_price_' + key ).each( function() {
		jQuery( this ).html( ec_details_format_money_v2( product_id, rand_id, shown ) );
	} );
	jQuery( '.ec_details_price.ec_details_no_vat_price > .ec_product_sale_price_' + key + ', .ec_details_price.ec_details_no_vat_price > .ec_product_price_' + key ).each( function() {
		jQuery( this ).html( ec_details_format_money_v2( product_id, rand_id, ( '1' == vat_added ) ? shown : shown / vat_rate_multiplier ) );
	} );
	jQuery( '.ec_details_price.ec_details_vat_price > .ec_product_sale_price_' + key + ', .ec_details_price.ec_details_vat_price > .ec_product_price_' + key ).each( function() {
		jQuery( this ).html( ec_details_format_money_v2( product_id, rand_id, ( '1' == vat_added ) ? shown * vat_rate_multiplier : shown ) );
	} );
	if ( 0 != line.onetime ) {
		jQuery( '.ec_details_added_price_' + key ).show();
		jQuery( document.getElementById( 'ec_added_price_' + key ) ).html( ec_details_format_money_v2( product_id, rand_id, line.onetime ) );
	} else {
		jQuery( '.ec_details_added_price_' + key ).hide();
	}
	jQuery( document.getElementById( 'ec_base_option_price_' + key ) ).val( line.unit );
	/* 6.0.2: this product's separate Price widgets show the same price. */
	wpeasycart_details_linked_price( product_id, rand_id, shown );
}

function ec_details_advanced_adjust_price( product_id, rand_id ) {
	if( jQuery( document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id ) ).length == 0 ) {
		return;
	}
	wpeasycart_details_show_price( product_id, rand_id, wpeasycart_details_line( product_id, rand_id ) );
}

/* 6.0.5: the lowest price of the last 30 days follows the chosen variant. wp_easycart_price_history::note_html() puts the
 * words of every variant that sets its own price and shows a reduction on the note ( data-wpec-prior-variants, keyed by the
 * five option item ids run together, as varitation_data_ ); a variant with its own price and no reduction shows none, and
 * anything else ( no full choice yet, a variant that keeps the product's price ) the product's own ( data-wpec-prior-base ). */
function wpeasycart_prior_price_variant( product_id, rand_id, key ) {
	var notes = jQuery( '.ec_product_prior_price[data-wpec-prior-product="' + product_id + '"]' );
	if ( ! notes.length ) {
		return;
	}
	var variations = window[ 'varitation_data_' + product_id + '_' + rand_id ] || {};
	notes.each( function() {
		var note = jQuery( this ), map = {}, text = String( note.attr( 'data-wpec-prior-base' ) || '' );
		try {
			map = JSON.parse( note.attr( 'data-wpec-prior-variants' ) || '{}' ) || {};
		} catch ( e ) {
			map = {};
		}
		if ( key && Object.prototype.hasOwnProperty.call( map, key ) ) {
			text = String( map[ key ] );
		} else if ( key && variations[ key ] && Number( variations[ key ].price ) != -1 ) {
			text = '';
		}
		note.text( text ).css( 'display', '' === text ? 'none' : '' );
	} );
}
function ec_details_base_adjust_price( product_id, rand_id ) {
	if( jQuery( document.getElementById( 'ec_default_price_' + product_id + '_' + rand_id ) ).length == 0 ) {
		return;
	}
	/* 6.0.5: priced and named as the cart's line will be ( wpeasycart_details_line() ): the SKU is the order line's ( the variant's
	 * SKU or the model number, then the choices' SKU endings ). */
	var line = wpeasycart_details_line( product_id, rand_id );
	var default_sku = document.getElementById( 'ec_default_sku_' + product_id + '_' + rand_id );
	if ( '' != line.sku || default_sku ) {
		jQuery( '.ec_details_model_number_sku_' + product_id + '_' + rand_id ).text( line.sku );
		/* 6.0.2: this product's separate SKU widgets / [ec_product_details_sku] follow the chosen variant. */
		jQuery( '.ec_details_sku[data-wpec-linked-product="' + product_id + '"]' ).text( line.sku );
	}
	wpeasycart_details_linked_stock( product_id, rand_id );
	wpeasycart_prior_price_variant( product_id, rand_id, ( line.all_chosen ) ? line.key : '' );
	jQuery( document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id ) ).html( line.unit );
	if ( document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id ) ) {
		ec_details_advanced_adjust_price( product_id, rand_id );
	} else {
		wpeasycart_details_show_price( product_id, rand_id, line );
	}
}
function ec_details_advanced_conditional_logic( product_id, rand_id ) {
	/* 6.0.2: rules read and show / hide this instance's rows only ( a second form of the product no longer interferes ). */
	var wpec_scope = wpeasycart_details_scope( product_id, rand_id );
	var ec_advanced_logic_rules = window['ec_advanced_logic_rules_' + product_id + '_' + rand_id];
	for( var i=0; i<ec_advanced_logic_rules.length; i++ ){
		if( ec_advanced_logic_rules[i].rules.length > 0 ){
			if( ec_advanced_logic_rules[i].show_field ){
				wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hide( );
			}else{
				wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).show( );
			}
			if( ec_advanced_logic_rules[i].and_rules == 'AND' ){
				var rules_match = true;
				for( var j=0; j<ec_advanced_logic_rules[i].rules.length; j++ ){
					wpec_scope.find( '.ec_details_option_row' ).each( function( ){
						if( jQuery( this ).attr( 'data-product-option-id' ) == ec_advanced_logic_rules[i].rules[j].option_id ){
							var val = 0;
							var selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
							if ( jQuery( this ).hasClass( 'ec_option_type_checkbox' ) ) {
								if ( jQuery( this ).attr( 'data-product-option-id' ) == ec_advanced_logic_rules[i].rules[j].option_id ) {
									jQuery( this ).find( 'input' ).each( function( ){ 
										if ( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id && jQuery( this ).is( ':checked' ) ) {
											val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
										}
									} );
								}

							}else if( jQuery( this ).hasClass( 'ec_option_type_radio' ) ){
								jQuery( this ).find( 'input' ).each( function( ){ 
									if( jQuery( this ).val( ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										if( jQuery( this ).is( ':checked' ) ){
											val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
										}
									}
								} );

							}else if( jQuery( this ).hasClass( 'ec_option_type_swatch' ) ){
								jQuery( this ).find( '.ec_details_swatch.ec_selected' ).each( function( ){
									if( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
									}
								} );
								jQuery( this ).find( '.ec_details_swatch_ele.ec_selected' ).each( function( ){
									if( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
									}
								} );

							}else if( jQuery( this ).hasClass( 'ec_option_type_combo' ) ){
								var val = jQuery( this ).find( 'select' ).val( );

							}else if( jQuery( this ).hasClass( 'ec_option_type_textarea' ) ){
								var val = jQuery( this ).find( 'textarea' ).val( );
								selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_value;

							}else{
								var val = jQuery( this ).find( 'input' ).val( );
								selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_value;
							}
							if( ec_advanced_logic_rules[i].rules[j].operator == '=' ){
								if( val != selected_val ){
									rules_match = false;
								}
							}else if( ec_advanced_logic_rules[i].rules[j].operator == '!=' ){
								if( val == selected_val ){
									rules_match = false;
								}
							}
						}
					} );
				}
			}else{
				var rules_match = false;
				for( var j=0; j<ec_advanced_logic_rules[i].rules.length; j++ ){
					wpec_scope.find( '.ec_details_option_row' ).each( function( ){
						if( jQuery( this ).attr( 'data-product-option-id' ) == ec_advanced_logic_rules[i].rules[j].option_id ){
							var val = 0;
							var selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
							if ( jQuery( this ).hasClass( 'ec_option_type_checkbox' ) ) {
								if ( jQuery( this ).attr( 'data-product-option-id' ) == ec_advanced_logic_rules[i].rules[j].option_id ) {
									jQuery( this ).find( 'input' ).each( function( ){ 
										if ( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id && jQuery( this ).is( ':checked' ) ) {
											val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
										}
									} );
								}

							}else if( jQuery( this ).hasClass( 'ec_option_type_radio' ) ){
								jQuery( this ).find( 'input' ).each( function( ){ 
									if( jQuery( this ).val( ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										if( jQuery( this ).is( ':checked' ) ){
											val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
										}
									}
								} );

							}else if( jQuery( this ).hasClass( 'ec_option_type_swatch' ) ){
								jQuery( this ).find( '.ec_details_swatch.ec_selected' ).each( function( ){
									if( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
									}
								} );
								jQuery( this ).find( '.ec_details_swatch_ele.ec_selected' ).each( function( ){
									if( jQuery( this ).attr( 'data-optionitem-id' ) == ec_advanced_logic_rules[i].rules[j].optionitem_id ){
										val = ec_advanced_logic_rules[i].rules[j].optionitem_id;
									}
								} );

							}else if( jQuery( this ).hasClass( 'ec_option_type_combo' ) ){
								var val = jQuery( this ).find( 'select' ).val( );

							}else if( jQuery( this ).hasClass( 'ec_option_type_textarea' ) ){
								var val = jQuery( this ).find( 'textarea' ).val( );
								selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_value;

							}else{
								var val = jQuery( this ).find( 'input' ).val( );
								selected_val = ec_advanced_logic_rules[i].rules[j].optionitem_value;
							}
							if( ec_advanced_logic_rules[i].rules[j].operator == '=' ){
								if( val == selected_val ){
									rules_match = true;
								}
							}else if( ec_advanced_logic_rules[i].rules[j].operator == '!=' ){
								if( val != selected_val ){
									rules_match = true;
								}
							}
						}
					} );
				}
			}
			if( rules_match ){
				if( ec_advanced_logic_rules[i].show_field ){
					wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).show( );
				}else{
					wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hide( );
					if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_checkbox' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).attr( 'checked', false ); } );
					}else if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_radio' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).prop( 'checked', false ); } );
					}else if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_swatch' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( '.ec_details_swatch' ).each( function( ){ jQuery( this ).removeClass( 'ec_selected' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( '.ec_details_swatch_ele' ).each( function( ){ jQuery( this ).removeClass( 'ec_selected' ); } );
						jQuery( document.getElementById( 'ec_option_adv_' + ec_advanced_logic_rules[i].id + '_' + product_id + '_' + rand_id ) ).val( '0' );
					}else{
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).val( '' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'textarea' ).each( function( ){ jQuery( this ).val( '' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'select' ).each( function( ){ jQuery( this ).val( 0 ); } );
					}
				}
			}else{
				if( ec_advanced_logic_rules[i].show_field ){
					if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_checkbox' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).attr( 'checked', false ); } );
					}else if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_radio' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).prop( 'checked', false ); } );
					}else if( wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).hasClass( 'ec_option_type_swatch' ) ){
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( '.ec_details_swatch' ).each( function( ){ jQuery( this ).removeClass( 'ec_selected' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( '.ec_details_swatch_ele' ).each( function( ){ jQuery( this ).removeClass( 'ec_selected' ); } );
						jQuery( document.getElementById( 'ec_option_adv_' + ec_advanced_logic_rules[i].id + '_' + product_id + '_' + rand_id ) ).val( '0' );
					}else{
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'input' ).each( function( ){ jQuery( this ).val( '' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'textarea' ).each( function( ){ jQuery( this ).val( '' ); } );
						wpec_scope.find( '.ec_details_option_row[data-product-option-id="' + ec_advanced_logic_rules[i].id + '"]' ).find( 'select' ).each( function( ){ jQuery( this ).val( 0 ); } );
					}
				}
			}
		}
	}
}

function ec_option1_image_change( product_id, rand_id, optionitem_id_1, quantity ){
	var use_optionitem_images = jQuery( document.getElementById( 'use_optionitem_images_' + product_id + '_' + rand_id ) ).val();
	if( '1' == use_optionitem_images ){
		/* 6.0.2: every gallery of this product follows the choice ( the form's own, and each separate Images widget or
		 * [ec_product_details_images] ), not just the first one on the page. */
		var gallery_rands = [];
		if ( jQuery( '.ec_details_main_image_' + product_id + '_' + rand_id ).length ) {
			gallery_rands.push( String( rand_id ) );
		}
		jQuery( '.ec_details_main_image[data-product-id="' + product_id + '"]' ).each( function( ){
			var gallery_rand = jQuery( this ).attr( 'data-rand-id' );
			/* A gallery that belongs to another form of the product ( it has its own option images flag ) follows that form. */
			if ( 'undefined' != typeof gallery_rand && String( gallery_rand ) != String( rand_id ) && document.getElementById( 'use_optionitem_images_' + product_id + '_' + gallery_rand ) ) {
				return;
			}
			if ( 'undefined' != typeof gallery_rand && '' !== gallery_rand && -1 == jQuery.inArray( String( gallery_rand ), gallery_rands ) ) {
				gallery_rands.push( String( gallery_rand ) );
			}
		} );
		if ( ! gallery_rands.length ) {
			gallery_rands.push( String( rand_id ) );
		}
		for ( var g = 0; g < gallery_rands.length; g++ ) {
			ec_option1_image_change_gallery( product_id, gallery_rands[ g ], optionitem_id_1 );
		}
		wpeasycart_product_state_queue( product_id, rand_id ); /* 6.0.2: the image changed */
	}
}
/* 6.0.2: one gallery's part of ec_option1_image_change(). */
function ec_option1_image_change_gallery( product_id, rand_id, optionitem_id_1 ){
		if( jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).length ){
			jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
			jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
			jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).find( '.ec_details_thumbnail' ).first( ).trigger( 'click' );
			if( !jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_no_thumbnails' ) ){
				jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_inactive' );
				if( jQuery( document.getElementById( 'ec_details_large_popup_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ) ){
					jQuery( document.getElementById( 'ec_details_large_popup_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_inactive' );
				}
			}
		} else if( jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).length ){
			jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
			jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
			jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).first().find( '.ec_details_thumbnail' ).first( ).trigger( 'click' );
			if ( ! jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).first().hasClass( 'ec_no_thumbnails' ) ){
				jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).first().removeClass( 'ec_inactive' );
				if ( jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id ).length ) {
					jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id ).first().removeClass( 'ec_inactive' );
				}
			}
		}
}
function ec_option1_init_combo( product_id, rand_id ){
	if (
		! jQuery( 'select.ec_option2_' + product_id + '_' + rand_id ).length &&
		! jQuery( 'li.ec_option2_' + product_id + '_' + rand_id ).length
	) {
		jQuery( 'select.ec_option1_' + product_id + '_' + rand_id + ' > option' ).each( function() {
			var optionitem_id_1 = jQuery( this ).attr( 'value' );
			if (
				window['varitation_data_' + product_id + '_' + rand_id ] &&
				window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000']
			) {
				var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
				if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
					use_optionitem_quantity_tracking = '0';
				}
				if (
					window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].enabled &&
					(
						'0' == use_optionitem_quantity_tracking ||
						! window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].tracking || 
						window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].quantity > 0
					)
				) {
					jQuery( this ).show().attr( 'disabled', false );
				} else {
					jQuery( this ).hide().attr( 'disabled', true );
				}
			} else {
				jQuery( this ).show().attr( 'disabled', false );
			}
		} );
	}
}
function ec_option1_init_swatches( product_id, rand_id, swatch, optionitem_id_1 ){
	if (
		! jQuery( 'select.ec_option2_' + product_id + '_' + rand_id ).length &&
		! jQuery( 'li.ec_option2_' + product_id + '_' + rand_id ).length
	) {
		if ( 
			window['varitation_data_' + product_id + '_' + rand_id ] && 
			window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000']
		) {
			var use_optionitem_quantity_tracking = jQuery( document.getElementById( 'use_optionitem_quantity_tracking_' + product_id + '_' + rand_id ) ).val();
			/* 6.0.2: this product's backorder flag ( the old id did not exist, so out of stock swatches of a backorder product could not be chosen ). */
			if ( jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).length && '1' == jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val() ) {
				use_optionitem_quantity_tracking = '0';
			}
			if (
				window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].enabled &&
				(
					'0' == use_optionitem_quantity_tracking ||
					! window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].tracking ||
					window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].quantity > 0
				)
			) {
				swatch.addClass( 'ec_active' ).removeClass( 'ec_selected' );
			} else if (
				! window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'].enabled
			) {
				swatch.hide().removeClass( 'ec_active' ).removeClass( 'ec_selected' );
			} else {
				swatch.removeClass( 'ec_active' ).removeClass( 'ec_selected' );
			}
		} else {
			swatch.addClass( 'ec_active' ).removeClass( 'ec_selected' );
		}
	}
}
function ec_option1_updated( product_id, rand_id, optionitem_id_1 ) {
	if ( jQuery( 'select.ec_option2_' + product_id + '_' + rand_id ).length ) {
		if ( ! jQuery( 'select.ec_option2_' + product_id + '_' + rand_id + '_backup' ).length ) {
			jQuery( '<select style="display:none" class="ec_option2_' + product_id + '_' + rand_id + '_backup"></select>' ).insertBefore( 'select.ec_option2_' + product_id + '_' + rand_id );
			var options_copy = jQuery( 'select.ec_option2_' + product_id + '_' + rand_id + ' > option' ).clone();
			jQuery( 'select.ec_option2_' + product_id + '_' + rand_id + '_backup' ).append( options_copy );
		} else {
			jQuery ( 'select.ec_option2_' + product_id + '_' + rand_id + ' > option' ).remove();
			var options_backup_copy = jQuery ( 'select.ec_option2_' + product_id + '_' + rand_id + '_backup > option' ).clone();
			jQuery ( 'select.ec_option2_' + product_id + '_' + rand_id ).append( options_backup_copy );
		}

		jQuery ( 'select.ec_option2_' + product_id + '_' + rand_id + ' > option' ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'value' ) + '000'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'value' ) + '000'].enabled ) {
					jQuery( this ).show().attr( 'disabled', false );
				} else {
					jQuery( this ).remove();
				}
			} else {
				jQuery( this ).show().attr( 'disabled', false );
			}
		} );
	} else if ( jQuery( 'li.ec_option2_' + product_id + '_' + rand_id ).length ) {
		jQuery ( 'li.ec_option2_' + product_id + '_' + rand_id ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'data-optionitem-id' ) + '000'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'data-optionitem-id' ) + '000'].enabled ) {
					jQuery( this ).addClass( 'ec_active' );
				} else {
					if ( jQuery( this ).hasClass( 'ec_selected' ) ) {
						jQuery( '.ec_details_option_label_selected_2' ).html( '' );
					}
					jQuery( this ).removeClass( 'ec_active' ).removeClass( 'ec_selected' );
				}
			} else {
				jQuery( this ).addClass( 'ec_active' );
			}
		} );
	}
}
function ec_option1_selected( product_id, rand_id, optionitem_id_1, quantity ){
	var variation = false;
	if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'] ) {
		variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + '0000'];
	}
	if( variation ) {
		quantity = ( variation.tracking ) ? variation.quantity : 'inf';
		quantity = ( variation.enabled ) ? quantity : 0;
	}

	var use_optionitem_images = jQuery( document.getElementById( 'use_optionitem_images_' + product_id + '_' + rand_id ) ).val();
	if( '1' == use_optionitem_images ){
		jQuery( '.ec_details_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
		jQuery( '.ec_details_large_popup_thumbnails_' + product_id + '_' + rand_id ).addClass( 'ec_inactive' );
		jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).find( '.ec_details_thumbnail' ).first( ).trigger( 'click' );
		if( !jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_no_thumbnails' ) ){
			jQuery( document.getElementById( 'ec_details_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_inactive' );
			jQuery( document.getElementById( 'ec_details_large_popup_thumbnails_' + optionitem_id_1 + '_' + product_id + '_' + rand_id ) ).removeClass( 'ec_inactive' );
		}
	}
	jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option4_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option4_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_option_label_selected_2, .ec_details_option_label_selected_3, .ec_details_option_label_selected_4, .ec_details_option_label_selected_5' ).html( '' );
	jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
	if ( 'inf' != quantity ) {
		jQuery( '.ec_details_stock_total' ).show();
	} else {
		jQuery( '.ec_details_stock_total' ).hide();
	}
	jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).attr( 'max', quantity );
	if( Number( jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > Number( quantity ) ){
		 jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( quantity );
	}
	jQuery( document.getElementById( 'ec_option_loading_2_' + product_id + '_' + rand_id ) ).show( );
	var next_options = jQuery( '.ec_details_swatches > li.ec_option2_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option2_' + product_id + '_' + rand_id );
	if( next_options.length ){
		next_options.each( function( ){
			jQuery( this ).show().removeClass( 'ec_active' ).removeClass( 'ec_disabled_variant' ).addClass( 'ec_no_data_variant' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_2_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).attr( 'data-optionitem-id' ) == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ) {
						jQuery( this ).attr( 'data-optionitem-quantity', json_data[ found_i ].quantity );
						if ( ! json_data[ found_i ].is_enabled ) {
							jQuery( this ).show().addClass( 'ec_disabled_variant' ).removeClass( 'ec_no_data_variant' );
						} else if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().addClass( 'ec_active' ).removeClass( 'ec_no_data_variant' );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'data-optionitem-id' ) + '000'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).attr( 'data-optionitem-id' ) + '000'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide();
							}
						}
					}
					found_i = -1;
				} );
			}
		} );
	}else{
		next_options = jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id + ' option' );
		next_options.each( function( ){
			jQuery( this ).removeClass( 'ec_active' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).prop( 'disabled', false ).removeClass( 'ec_inactive' );
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_2_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).val( ) == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ) {
						jQuery( this ).attr( 'data-optionitem-quantity', ( ( '1' == json_data[ found_i ].tracking ) ? json_data[ found_i ].quantity : 'inf' ) );
						if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().attr( 'disabled', false );
						} else {
							jQuery( this ).hide().attr( 'disabled', true );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).val() + '000'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + jQuery( this ).val() + '000'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide().prop( 'disabled', true );
							}
						}
					} else {
						jQuery( this ).attr( 'data-optionitem-quantity', 0 ).hide().attr( 'disabled', true );
					}
					found_i = -1;
				} );
			}
		} );
	}
}
function ec_option2_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2 ) {
	if ( jQuery( 'select.ec_option3_' + product_id + '_' + rand_id ).length ) {
		if ( ! jQuery( 'select.ec_option3_' + product_id + '_' + rand_id + '_backup' ).length ) {
			jQuery( '<select style="display:none" class="ec_option3_' + product_id + '_' + rand_id + '_backup"></select>' ).insertBefore( 'select.ec_option3_' + product_id + '_' + rand_id );
			var options_copy = jQuery( 'select.ec_option3_' + product_id + '_' + rand_id + ' > option' ).clone();
			jQuery( 'select.ec_option3_' + product_id + '_' + rand_id + '_backup' ).append( options_copy );
		} else {
			jQuery ( 'select.ec_option3_' + product_id + '_' + rand_id + ' > option' ).remove();
			var options_backup_copy = jQuery ( 'select.ec_option3_' + product_id + '_' + rand_id + '_backup > option' ).clone();
			jQuery ( 'select.ec_option3_' + product_id + '_' + rand_id ).append( options_backup_copy );
		}

		jQuery ( 'select.ec_option3_' + product_id + '_' + rand_id + ' > option' ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'value' ) + '00'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'value' ) + '00'].enabled ) {
					jQuery( this ).show().attr( 'disabled', false );
				} else {
					jQuery( this ).remove();
				}
			} else {
				jQuery( this ).show().attr( 'disabled', false );
			}
		} );
	} else if ( jQuery( 'li.ec_option3_' + product_id + '_' + rand_id ).length ) {
		jQuery ( 'li.ec_option3_' + product_id + '_' + rand_id ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'data-optionitem-id' ) + '00'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'data-optionitem-id' ) + '00'].enabled ) {
					jQuery( this ).addClass( 'ec_active' );
				} else {
					if ( jQuery( this ).hasClass( 'ec_selected' ) ) {
						jQuery( '.ec_details_option_label_selected_3' ).html( '' );
					}
					jQuery( this ).removeClass( 'ec_active' ).removeClass( 'ec_selected' );
				}
			} else {
				jQuery( this ).addClass( 'ec_active' );
			}
		} );
	}
}
function option2_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, quantity ){	
	var variation = false;
	if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + '000'] ) {
		variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + '000'];
	}
	if( variation ) {
		quantity = ( variation.tracking ) ? variation.quantity : 'inf';
		quantity = ( variation.enabled ) ? quantity : 0;
	}

	jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option4_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option4_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_option_label_selected_3, .ec_details_option_label_selected_4, .ec_details_option_label_selected_5' ).html( '' );
	jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
	jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
	if ( 'inf' != quantity ) {
		jQuery( '.ec_details_stock_total' ).show();
	} else {
		jQuery( '.ec_details_stock_total' ).hide();
	}
	jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).attr( 'max', quantity );
	if( Number( jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > Number( quantity ) ){
		 jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( quantity );
	}
	var next_options = jQuery( '.ec_details_swatches > li.ec_option3_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option3_' + product_id + '_' + rand_id );
	jQuery( document.getElementById( 'ec_option_loading_3_' + product_id + '_' + rand_id ) ).show( );
	if( next_options.length ){
		next_options.each( function( ){
			jQuery( this ).show().removeClass( 'ec_active' ).removeClass( 'ec_disabled_variant' ).addClass( 'ec_no_data_variant' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_3_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).attr( 'data-optionitem-id' ) == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ){
						jQuery( this ).attr( 'data-optionitem-quantity', json_data[ found_i ].quantity );
						if ( ! json_data[ found_i ].is_enabled ) {
							jQuery( this ).show().addClass( 'ec_disabled_variant' ).removeClass( 'ec_no_data_variant' );
						} else if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().addClass( 'ec_active' ).removeClass( 'ec_no_data_variant' );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'data-optionitem-id' ) + '00'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).attr( 'data-optionitem-id' ) + '00'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide();
							}
						}
					}
					found_i = -1;
				} );
			}
		} );
	}else{
		next_options = jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id + ' option' );
		next_options.each( function( ){
			jQuery( this ).removeClass( 'ec_active' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).prop( 'disabled', false ).removeClass( 'ec_inactive' );
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_3_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).val() == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ) {
						jQuery( this ).attr( 'data-optionitem-quantity', ( ( '1' == json_data[ found_i ].tracking ) ? json_data[ found_i ].quantity : 'inf' ) );
						if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().attr( 'disabled', false );
						} else {
							jQuery( this ).hide().attr( 'disabled', true );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).val() + '00'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + jQuery( this ).val() + '00'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide().prop( 'disabled', true );
							}
						}
					} else {
						jQuery( this ).attr( 'data-optionitem-quantity', 0 ).hide().attr( 'disabled', true );
					}
					found_i = -1;
				} );
			}
		} );
	}
}
function ec_option3_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3 ) {
	if ( jQuery( 'select.ec_option4_' + product_id + '_' + rand_id ).length ) {
		if ( ! jQuery( 'select.ec_option4_' + product_id + '_' + rand_id + '_backup' ).length ) {
			jQuery( '<select style="display:none" class="ec_option4_' + product_id + '_' + rand_id + '_backup"></select>' ).insertBefore( 'select.ec_option4_' + product_id + '_' + rand_id );
			var options_copy = jQuery( 'select.ec_option4_' + product_id + '_' + rand_id + ' > option' ).clone();
			jQuery( 'select.ec_option4_' + product_id + '_' + rand_id + '_backup' ).append( options_copy );
		} else {
			jQuery ( 'select.ec_option4_' + product_id + '_' + rand_id + ' > option' ).remove();
			var options_backup_copy = jQuery ( 'select.ec_option4_' + product_id + '_' + rand_id + '_backup > option' ).clone();
			jQuery ( 'select.ec_option4_' + product_id + '_' + rand_id ).append( options_backup_copy );
		}

		jQuery ( 'select.ec_option4_' + product_id + '_' + rand_id + ' > option' ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'value' ) + '0'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'value' ) + '0'].enabled ) {
					jQuery( this ).show().attr( 'disabled', false );
				} else {
					jQuery( this ).remove();
				}
			} else {
				jQuery( this ).show().attr( 'disabled', false );
			}
		} );
	} else if ( jQuery( 'li.ec_option4_' + product_id + '_' + rand_id ).length ) {
		jQuery ( 'li.ec_option4_' + product_id + '_' + rand_id ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'data-optionitem-id' ) + '0'] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'data-optionitem-id' ) + '0'].enabled ) {
					jQuery( this ).addClass( 'ec_active' );
				} else {
					if ( jQuery( this ).hasClass( 'ec_selected' ) ) {
						jQuery( '.ec_details_option_label_selected_4' ).html( '' );
					}
					jQuery( this ).removeClass( 'ec_active' ).removeClass( 'ec_selected' );
				}
			} else {
				jQuery( this ).addClass( 'ec_active' );
			}
		} );
	}
}
function option3_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, quantity ){	
	var variation = false;
	if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + '00'] ) {
		variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + '00'];
	}
	if( variation ) {
		quantity = ( variation.tracking ) ? variation.quantity : 'inf';
		quantity = ( variation.enabled ) ? quantity : 0;
	}

	jQuery( '.ec_details_swatches > li.ec_option4_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option4_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_option_label_selected_4, .ec_details_option_label_selected_5' ).html( '' );
	jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
	jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
	if ( 'inf' != quantity ) {
		jQuery( '.ec_details_stock_total' ).show();
	} else {
		jQuery( '.ec_details_stock_total' ).hide();
	}
	jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).attr( 'max', quantity );
	if( Number( jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > Number( quantity ) ){
		 jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( quantity );
	}
	var next_options = jQuery( '.ec_details_swatches > li.ec_option4_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option4_' + product_id + '_' + rand_id );
	jQuery( document.getElementById( 'ec_option_loading_4_' + product_id + '_' + rand_id ) ).show( );
	if( next_options.length ){
		next_options.each( function( ){
			jQuery( this ).show().removeClass( 'ec_active' ).removeClass( 'ec_disabled_variant' ).addClass( 'ec_no_data_variant' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			optionitem_id_3: optionitem_id_3,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_4_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).attr( 'data-optionitem-id' ) == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length > found_i ){
						jQuery( this ).attr( 'data-optionitem-quantity', json_data[ found_i ].quantity );
						if ( ! json_data[ found_i ].is_enabled ) {
							jQuery( this ).show().addClass( 'ec_disabled_variant' ).removeClass( 'ec_no_data_variant' );
						} else if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().addClass( 'ec_active' ).removeClass( 'ec_no_data_variant' );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'data-optionitem-id' ) + '0'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).attr( 'data-optionitem-id' ) + '0'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide();
							}
						}
					}
					found_i = -1;
				} );
			}
		} );
	} else {
		next_options = jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id + ' option' );
		next_options.each( function( ){
			jQuery( this ).removeClass( 'ec_active' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			optionitem_id_3: optionitem_id_3,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).prop( 'disabled', false ).removeClass( 'ec_inactive' );
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_4_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).val() == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ) {
						jQuery( this ).attr( 'data-optionitem-quantity', ( ( '1' == json_data[ found_i ].tracking ) ? json_data[ found_i ].quantity : 'inf' ) );
						if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().attr( 'disabled', false );
						} else {
							jQuery( this ).hide().attr( 'disabled', true );
						}
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).val() + '0'] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + jQuery( this ).val() + '0'];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide().prop( 'disabled', true );
							}
						}
					}else{
						jQuery( this ).attr( 'data-optionitem-quantity', 0 ).hide().attr( 'disabled', true );
					}
					found_i = -1;
				} );
			}
		} );
	}
}
function ec_option4_updated( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4 ) {
	if ( jQuery( 'select.ec_option5_' + product_id + '_' + rand_id ).length ) {
		if ( ! jQuery( 'select.ec_option5_' + product_id + '_' + rand_id + '_backup' ).length ) {
			jQuery( '<select style="display:none" class="ec_option5_' + product_id + '_' + rand_id + '_backup"></select>' ).insertBefore( 'select.ec_option5_' + product_id + '_' + rand_id );
			var options_copy = jQuery( 'select.ec_option5_' + product_id + '_' + rand_id + ' > option' ).clone();
			jQuery( 'select.ec_option5_' + product_id + '_' + rand_id + '_backup' ).append( options_copy );
		} else {
			jQuery ( 'select.ec_option5_' + product_id + '_' + rand_id + ' > option' ).remove();
			var options_backup_copy = jQuery ( 'select.ec_option5_' + product_id + '_' + rand_id + '_backup > option' ).clone();
			jQuery ( 'select.ec_option5_' + product_id + '_' + rand_id ).append( options_backup_copy );
		}

		jQuery ( 'select.ec_option5_' + product_id + '_' + rand_id + ' > option' ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'value' )] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'value' )].enabled ) {
					jQuery( this ).show().attr( 'disabled', false );
				} else {
					jQuery( this ).remove();
				}
			} else {
				jQuery( this ).show().attr( 'disabled', false );
			}
		} );
	} else if ( jQuery( 'li.ec_option5_' + product_id + '_' + rand_id ).length ) {
		jQuery ( 'li.ec_option5_' + product_id + '_' + rand_id ).each( function() {
			if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'data-optionitem-id' )] ) {
				if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'data-optionitem-id' )].enabled ) {
					jQuery( this ).addClass( 'ec_active' );
				} else {
					if ( jQuery( this ).hasClass( 'ec_selected' ) ) {
						jQuery( '.ec_details_option_label_selected_5' ).html( '' );
					}
					jQuery( this ).removeClass( 'ec_active' ).removeClass( 'ec_selected' );
				}
			} else {
				jQuery( this ).addClass( 'ec_active' );
			}
		} );
	}
}
function option4_selected( product_id, rand_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, quantity ){	
	var variation = false;
	if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + '0'] ) {
		variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + '0'];
	}
	if( variation ) {
		quantity = ( variation.tracking ) ? variation.quantity : 'inf';
		quantity = ( variation.enabled ) ? quantity : 0;
	}

	jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id ).removeClass( 'ec_selected' ).removeClass( 'ec_active' );
	jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).val( '0' ).prop( 'disabled', true ).addClass( 'ec_inactive' );
	jQuery( '.ec_details_option_label_selected_5' ).html( '' );
	jQuery( '.ec_option4.ec_details_option_row_error' ).hide( );
	jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) ).html( quantity );
	if ( 'inf' != quantity ) {
		jQuery( '.ec_details_stock_total' ).show();
	} else {
		jQuery( '.ec_details_stock_total' ).hide();
	}
	jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).attr( 'max', quantity );
	if( Number( jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( ) ) > Number( quantity ) ){
		 jQuery( document.getElementById('ec_quantity_' + product_id + '_' + rand_id ) ).val( quantity );
	}
	var next_options = jQuery( '.ec_details_swatches > li.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatches_ele > li.ec_option5_' + product_id + '_' + rand_id );
	jQuery( document.getElementById( 'ec_option_loading_5_' + product_id + '_' + rand_id ) ).show( );
	if( next_options.length ){
		next_options.each( function( ){
			jQuery( this ).show().removeClass( 'ec_active' ).removeClass( 'ec_disabled_variant' ).addClass( 'ec_no_data_variant' )
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			optionitem_id_3: optionitem_id_3,
			optionitem_id_4: optionitem_id_4,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_5_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).attr( 'data-optionitem-id' ) == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length > found_i ){
						jQuery( this ).attr( 'data-optionitem-quantity', json_data[i].quantity );
						if ( ! json_data[ found_i ].is_enabled ) {
							jQuery( this ).show().addClass( 'ec_disabled_variant' ).removeClass( 'ec_no_data_variant' );
						} else if( json_data[i].quantity > 0 || '0' == json_data[i].tracking ){
							jQuery( this ).show().addClass( 'ec_active' ).removeClass( 'ec_no_data_variant' );
						}
						i++;
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'data-optionitem-id' ) ] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).attr( 'data-optionitem-id' ) ];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide();
							}
						}
					}
					found_i = -1;
				} );
			}
		} );
	} else {
		next_options = jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id + ' option' );
		next_options.each( function( ){
			jQuery( this ).removeClass( 'ec_active' );
		} );
		var data = {
			action: 'ec_ajax_get_optionitem_quantities',
			optionitem_id_1: optionitem_id_1,
			optionitem_id_2: optionitem_id_2,
			optionitem_id_3: optionitem_id_3,
			optionitem_id_4: optionitem_id_4,
			product_id: product_id,
			nonce: jQuery( document.getElementById( 'product_details_nonce_' + product_id + '_' + rand_id ) ).val()
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){ 
				jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).prop( 'disabled', false ).removeClass( 'ec_inactive' );
				var json_data = JSON.parse( data );
				jQuery( document.getElementById( 'ec_option_loading_5_' + product_id + '_' + rand_id ) ).hide( );
				var found_i = -1;
				next_options.each( function( ){
					for ( var i = 0; i < json_data.length && -1 == found_i; i++ ) {
						if ( jQuery( this ).val() == json_data[i].optionitem_id ) { 
							found_i = i;
						}
					}
					if ( -1 != found_i && found_i < json_data.length ) {
						jQuery( this ).attr( 'data-optionitem-quantity', ( ( '1' == json_data[ found_i ].tracking ) ? json_data[ found_i ].quantity : 'inf' ) );
						if( json_data[ found_i ].quantity > 0 || '0' == json_data[ found_i ].tracking ){
							jQuery( this ).show().attr( 'disabled', false );
						} else {
							jQuery( this ).hide().attr( 'disabled', true );
						}
						i++;
						var subitem_variation = false;
						if ( window['varitation_data_' + product_id + '_' + rand_id ] && window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).val() ] ) {
							subitem_variation = window['varitation_data_' + product_id + '_' + rand_id][optionitem_id_1 + optionitem_id_2 + optionitem_id_3 + optionitem_id_4 + jQuery( this ).val() ];
						}
						if( subitem_variation ) {
							if ( ! subitem_variation.enabled ) {
								jQuery( this ).hide().prop( 'disabled', true );
							}
						}
					} else {
						jQuery( this ).attr( 'data-optionitem-quantity', 0 ).hide().attr( 'disabled', true );
					}
					found_i = -1;
				} );
			}
		} );
	}
}

function ec_details_submit_inquiry( product_id, rand_id ){
	var errors = 0;
	if( document.getElementById( 'ec_inquiry_name_' + product_id + '_' + rand_id ) ){
		if( jQuery( document.getElementById( 'ec_inquiry_name_' + product_id + '_' + rand_id ) ).val( ) == "" || !ec_validate_email( jQuery( document.getElementById( 'ec_inquiry_email_' + product_id + '_' + rand_id ) ).val( ) ) || jQuery( document.getElementById( 'ec_inquiry_message_' + product_id + '_' + rand_id ) ).val( ) == "" ){
			jQuery( document.getElementById( 'ec_details_inquiry_error_' + product_id + '_' + rand_id ) ).show( );
			errors++;
		}else{
			jQuery( document.getElementById( 'ec_details_inquiry_error_' + product_id + '_' + rand_id ) ).hide( );
		}
	}
	if( jQuery( document.getElementById( 'ec_grecaptcha_response_inquiry' ) ).length ){
		var recaptcha_response = jQuery( document.getElementById( 'ec_grecaptcha_response_inquiry' ) ).val( );
		if( !recaptcha_response.length ){
			jQuery( '#ec_product_details_inquiry_recaptcha > div' ).css( 'border', '1px solid red' );
			errors++;
		}else{
			jQuery( '#ec_product_details_inquiry_recaptcha > div' ).css( 'border', 'none' );
		}
	}
	if( ! ec_details_add_to_cart( product_id, rand_id ) ){
		errors++;
	}
	if( errors > 0 ) {
		return false;
	} else {
		jQuery( document.getElementById( 'ec_inquiry_loader_' + product_id + '_' + rand_id ) ).show( );
		jQuery( document.getElementById( 'ec_inquiry_loader_bg_' + product_id + '_' + rand_id ) ).show( );
		return true;
	}
}

function ec_details_add_to_cart( product_id, rand_id ) {
	/* 6.0.2: the session pre-warm request ( ec_ajax_try_wpec_session ) is no longer sent. It posted a nonce no product form
	 * has, so the server always refused it; made to work, it would race the add itself for a first-time visitor ( two new
	 * cart sessions, and the cookie of either could win ). The add request starts the session on its own. */
	var wpec_form = wpeasycart_details_form( product_id, rand_id );
	if ( wpec_form && jQuery( wpec_form ).data( 'wpeasycartAdding' ) ) {
		return false; /* 6.0.2: a background add is still running ( no second add, no second analytics event ) */
	}
	var wpec_scope = wpeasycart_details_scope( product_id, rand_id );
	var errors = 0;
	// Basic Option 1 Error Check
	if( jQuery( '.ec_details_swatch.ec_option1_' + product_id + '_' + rand_id + ', .ec_details_swatch_ele.ec_option1_' + product_id + '_' + rand_id ).length && !jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' ) ){ 
		jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else if( jQuery( '.ec_details_combo.ec_option1_' + product_id + '_' + rand_id ).length && ( jQuery( '.ec_details_combo.ec_option1.ec_option1_' + product_id + '_' + rand_id ).val( ) == null || jQuery( '.ec_details_combo.ec_option1.ec_option1_' + product_id + '_' + rand_id ).val( ) == '0' ) ){ 
		jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else{ 
		jQuery( '.ec_option1_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( );
	}
	// Basic Option 2 Error Check
	if( jQuery( '.ec_details_swatch.ec_option2_' + product_id + '_' + rand_id + ', .ec_details_swatch_ele.ec_option2_' + product_id + '_' + rand_id ).length && !jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' ) ){ 
		jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else if( jQuery( '.ec_details_combo.ec_option2_' + product_id + '_' + rand_id ).length && ( jQuery( '.ec_details_combo.ec_option2.ec_option2_' + product_id + '_' + rand_id ).val( ) == null || jQuery( '.ec_details_combo.ec_option2.ec_option2_' + product_id + '_' + rand_id ).val( ) == '0' ) ){ 
		jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else{ 
		jQuery( '.ec_option2_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( ); 
	}
	// Basic Option 3 Error Check
	if( jQuery( '.ec_details_swatch.ec_option3_' + product_id + '_' + rand_id + ', .ec_details_swatch_ele.ec_option3_' + product_id + '_' + rand_id ).length && !jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' ) ){ 
		jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( );
		errors++; 
	}else if( jQuery( '.ec_details_combo.ec_option3_' + product_id + '_' + rand_id ).length && ( jQuery( '.ec_details_combo.ec_option3.ec_option3_' + product_id + '_' + rand_id ).val( ) == null || jQuery( '.ec_details_combo.ec_option3.ec_option3_' + product_id + '_' + rand_id ).val( ) == '0' ) ){ 
		jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else{ 
		jQuery( '.ec_option3_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( ); 
	}
	// Basic Option 4 Error Check
	if( jQuery( '.ec_details_swatch.ec_option4_' + product_id + '_' + rand_id + ', .ec_details_swatch_ele.ec_option4_' + product_id + '_' + rand_id ).length && !jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' ) ){ 
		jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else if( jQuery( '.ec_details_combo.ec_option4_' + product_id + '_' + rand_id ).length && ( jQuery( '.ec_details_combo.ec_option4.ec_option4_' + product_id + '_' + rand_id ).val( ) == null || jQuery( '.ec_details_combo.ec_option4.ec_option4_' + product_id + '_' + rand_id ).val( ) == '0' ) ){ 
		jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else{ 
		jQuery( '.ec_option4_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( ); 
	}
	// Basic Option 5 Error Check
	if( jQuery( '.ec_details_swatch.ec_option5_' + product_id + '_' + rand_id + ', .ec_details_swatch_ele.ec_option5_' + product_id + '_' + rand_id ).length && !jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_selected' ).attr( 'data-optionitem-id' ) ){ 
		jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else if( jQuery( '.ec_details_combo.ec_option5_' + product_id + '_' + rand_id ).length && ( jQuery( '.ec_details_combo.ec_option5.ec_option5_' + product_id + '_' + rand_id ).val( ) == null || jQuery( '.ec_details_combo.ec_option5.ec_option5_' + product_id + '_' + rand_id ).val( ) == '0' ) ){ 
		jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).show( ); 
		errors++; 
	}else{ 
		jQuery( '.ec_option5_' + product_id + '_' + rand_id + '.ec_details_option_row_error' ).hide( ); 
	}
	// --------Advanced Option Checks---------- //
	// Select Box Check
	var advanced_select_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_combo:visible' );
	advanced_select_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			if( jQuery( document.getElementById( 'ec_option_adv_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).val( ) == '0' ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}
		}
	} );
	// Check Box Check
	var advanced_checkbox_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_checkbox:visible' );
	advanced_checkbox_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			var selected_checkboxes = jQuery( this ).find( "input.ec_option_adv_" + jQuery( this ).attr( 'data-product-option-id' ) + ":checked" );
			if( selected_checkboxes.length ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}
		}
	} );
	// Date Box Check
	var advanced_date_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_date:visible' );
	advanced_date_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			if( jQuery( document.getElementById( 'ec_option_adv_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).val( ) == "" ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}
		}
	} );
	// File Upload Check ( required, and whether the chosen file's type and size can be accepted )
	wpec_scope.find( '.ec_details_option_row.ec_option_type_file:visible' ).each( function( ){
		if ( jQuery( this ).attr( 'data-product-id' ) != product_id ) {
			return;
		}
		var option_key = jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id;
		var input = document.getElementById( 'ec_option_adv_' + option_key );
		var error_row = document.getElementById( 'ec_details_adv_option_row_error_' + option_key );
		if ( ! input ) {
			return;
		}
		if ( error_row && undefined === jQuery( error_row ).data( 'ec-default-error' ) ) {
			jQuery( error_row ).data( 'ec-default-error', jQuery( error_row ).html( ) );
		}
		var problem = '';
		var file = ( input.files && input.files.length ) ? input.files[0] : null;
		if ( ! jQuery( input ).val( ) ) {
			problem = ( jQuery( this ).attr( 'data-option-required' ) == '1' ) ? 'missing' : '';
		} else if ( file ) {
			var extensions = ( input.getAttribute( 'data-ec-file-ext' ) || '' ).toLowerCase( ).split( ',' );
			var dot = file.name.lastIndexOf( '.' );
			var extension = ( dot > -1 ) ? file.name.slice( dot + 1 ).toLowerCase( ) : '';
			var max_size = parseInt( input.getAttribute( 'data-ec-max-size' ), 10 );
			if ( input.getAttribute( 'data-ec-file-ext' ) && ( '' === extension || -1 === jQuery.inArray( extension, extensions ) ) ) {
				problem = 'file-type';
			} else if ( max_size > 0 && file.size > max_size ) {
				problem = 'file-size';
			}
		}
		if ( problem ) {
			if ( error_row ) {
				if ( 'missing' !== problem && input.getAttribute( 'data-ec-error-' + problem ) ) {
					jQuery( error_row ).text( input.getAttribute( 'data-ec-error-' + problem ) );
				} else {
					jQuery( error_row ).html( jQuery( error_row ).data( 'ec-default-error' ) );
				}
				jQuery( error_row ).show( );
			}
			errors++;
		} else if ( error_row ) {
			jQuery( error_row ).hide( );
		}
	} );
	// Swatch Check
	var advanced_swatch_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_swatch:visible' );
	advanced_swatch_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			var advanced_selected_swatches = jQuery( ".ec_details_swatch.ec_option_adv_" + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id + ".ec_selected, .ec_details_swatch_ele.ec_option_adv_" + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id + ".ec_selected" );
			if( advanced_selected_swatches.length ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}
		}
	} );
	// Radio Button Check
	var advanced_radio_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_radio:visible' );
	advanced_radio_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			var selected_radios = jQuery( this ).find( "input[name='ec_option_adv_" + jQuery( this ).attr( 'data-product-option-id' ) + "']:checked" );
			if( selected_radios.length ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}
		}
	} );
	// Text Box + Text Area Check ( required, and the minimum length whenever something was typed )
	wpec_scope.find( '.ec_details_option_row.ec_option_type_text:visible, .ec_details_option_row.ec_option_type_textarea:visible' ).each( function( ){
		if ( jQuery( this ).attr( 'data-product-id' ) != product_id ) {
			return;
		}
		var option_key = jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id;
		var input = document.getElementById( 'ec_option_adv_' + option_key );
		var error_row = document.getElementById( 'ec_details_adv_option_row_error_' + option_key );
		if ( ! input ) {
			return;
		}
		if ( error_row && undefined === jQuery( error_row ).data( 'ec-default-error' ) ) {
			jQuery( error_row ).data( 'ec-default-error', jQuery( error_row ).html( ) );
		}
		var value = jQuery( input ).val( );
		var is_textarea = jQuery( this ).hasClass( 'ec_option_type_textarea' );
		var missing = jQuery( this ).attr( 'data-option-required' ) == '1' && ( value == '' || ( is_textarea && value == 0 ) );
		var short_by = ( ! missing && window.wpeasycart_text_input_rules && window.wpeasycart_text_input_rules.too_short ) ? window.wpeasycart_text_input_rules.too_short( input ) : 0;
		if ( missing || short_by ) {
			if ( error_row ) {
				if ( short_by && input.getAttribute( 'data-ec-min-length-error' ) ) {
					jQuery( error_row ).text( input.getAttribute( 'data-ec-min-length-error' ) );
				} else {
					jQuery( error_row ).html( jQuery( error_row ).data( 'ec-default-error' ) );
				}
				jQuery( error_row ).show( );
			}
			errors++;
		} else if ( error_row ) {
			jQuery( error_row ).hide( );
		}
	} );
	// Number Box Check
	var advanced_text_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_number:visible' );
	advanced_text_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required	
			if( jQuery( document.getElementById( 'ec_option_adv_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).val( ) != "" ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}
		}
	} );
	// Quantity Grid Check
	var advanced_grid_rows = wpec_scope.find( '.ec_details_option_row.ec_option_type_grid:visible' );
	advanced_grid_rows.each( function( ){
		if( jQuery( this ).attr( 'data-product-id' ) == product_id && jQuery( this ).attr( 'data-option-required' ) == '1' ){ // Option is Required
			var grid_items = jQuery( this ).find( ".ec_details_option_data > .ec_details_grid_row > input" );
			var total_grid_quantity = 0;
			grid_items.each( 
				function( ){ 
					total_grid_quantity += jQuery( this ).val( ); 
				} 
			);
			if( total_grid_quantity > 0 ){
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).hide( );
			}else{
				jQuery( document.getElementById( 'ec_details_adv_option_row_error_' + jQuery( this ).attr( 'data-product-option-id' ) + '_' + product_id + '_' + rand_id ) ).show( );
				errors++;
			}
		}
	} );
	// Dimensions Type 1 + 2 Check ( 6.0.5: a required size needs a width and height, and any size a width and height that are each a
	// positive number, as the cart reads them: wpeasycart_details_size_check() )
	wpec_scope.find( '.ec_details_option_row.ec_option_type_dimensions1:visible, .ec_details_option_row.ec_option_type_dimensions2:visible' ).each( function( ){
		if ( jQuery( this ).attr( 'data-product-id' ) == product_id ) {
			errors += wpeasycart_details_size_check( jQuery( this ), product_id, rand_id, true );
		}
	} );
	// -------END Advanced Option Checks------- //
	// -------START GIFT CARD CHECK ----------- //
	var gift_card_errors = 0;
	if( document.getElementById( 'ec_giftcard_to_name_' + product_id + '_' + rand_id ) && jQuery( document.getElementById( 'ec_giftcard_to_name_' + product_id + '_' + rand_id ) ).val( ) == "" ){
		errors++;
		gift_card_errors++;
	}
	if( document.getElementById( 'ec_giftcard_to_email_' + product_id + '_' + rand_id ) && jQuery( document.getElementById( 'ec_giftcard_to_email_' + product_id + '_' + rand_id ) ).val( ) == "" ){
		errors++;
		gift_card_errors++;
	}
	if( document.getElementById( 'ec_giftcard_from_name_' + product_id + '_' + rand_id ) && jQuery( document.getElementById( 'ec_giftcard_from_name_' + product_id + '_' + rand_id ) ).val( ) == "" ){
		errors++;
		gift_card_errors++;
	}
	if( document.getElementById( 'ec_giftcard_message_' + product_id + '_' + rand_id ) && jQuery( document.getElementById( 'ec_giftcard_message_' + product_id + '_' + rand_id ) ).val( ) == "" ){
		errors++;
		gift_card_errors++;
	}
	if( gift_card_errors ){
		jQuery( document.getElementById( 'ec_details_giftcard_error_' + product_id + '_' + rand_id ) ).show( );
	}else{
		jQuery( document.getElementById( 'ec_details_giftcard_error_' + product_id + '_' + rand_id ) ).hide( );
	}
	// -------END GIFT CARD CHECK   ----------- //
	// -------START DONATION CHECK  ----------- //
	if( document.getElementById( 'ec_donation_amount_' + product_id + '_' + rand_id ) ){
		var price = jQuery( document.getElementById( 'price_' + product_id + '_' + rand_id ) ).val();
		if( isNaN( jQuery( document.getElementById( 'ec_donation_amount_' + product_id + '_' + rand_id ) ).val( ) ) || Number( jQuery( document.getElementById( 'ec_donation_amount_' + product_id + '_' + rand_id ) ).val( ) ) < price ){
			jQuery( document.getElementById( 'ec_details_donation_error_' + product_id + '_' + rand_id ) ).show( );
			errors++;
		}else{
			jQuery( document.getElementById( 'ec_details_donation_error_' + product_id + '_' + rand_id ) ).hide( );
		}
	}
	// -------END DONATION CHECK    ----------- //
	// -------START INQUIRY CHECK  ----------- //
	if( document.getElementById( 'ec_inquiry_name' ) ){
		if( jQuery( document.getElementById( 'ec_inquiry_name' ) ).val( ) == "" || jQuery( document.getElementById( 'ec_inquiry_email' ) ).val( ) == "" || jQuery( document.getElementById( 'ec_inquiry_message' ) ).val( ) == "" ){
			jQuery( document.getElementById( 'ec_details_inquiry_error' ) ).show( );
			errors++;
		}else{
			jQuery( document.getElementById( 'ec_details_inquiry_error' ) ).hide( );
		}
	}
	// -------END INQUIRY CHECK    ----------- //
	// Stock Quantity Check ( 6.0.1: this product's own stock, never a quick view's elsewhere on the page )
	var entered_quantity = Number( jQuery( document.getElementById( 'ec_quantity_' + product_id + '_' + rand_id ) ).val( ) );
	var allowed_quantity = 9999999999999;
	var stock_element = jQuery( document.getElementById( 'ec_details_stock_quantity_' + product_id + '_' + rand_id ) );
	if( stock_element.length && '1' != jQuery( document.getElementById( 'ec_allow_backorders_' + product_id + '_' + rand_id ) ).val( ) ){
		var stock_text = String( stock_element.html( ) ).replace( /^\s+|\s+$/g, '' );
		if( '' != stock_text && isFinite( Number( stock_text ) ) ){ // 'inf' ( an option without stock tracking ) sets no limit
			allowed_quantity = Number( stock_text );
		}
	}
	// Backorder Check
	if( allowed_quantity <= 0 && jQuery( document.getElementById( 'ec_back_order_info_' + product_id + '_' + rand_id ) ).length ){
		allowed_quantity = 1000000;
	}
	// Check Stock Quantity
	if( entered_quantity > allowed_quantity ){
		jQuery( document.getElementById( 'ec_addtocart_quantity_exceeded_error_' + product_id + '_' + rand_id ) ).show( );
		errors++;
	}else{
		jQuery( document.getElementById( 'ec_addtocart_quantity_exceeded_error_' + product_id + '_' + rand_id ) ).hide( );
	}
	// Minimum Quantity Check
	var min_quantity = jQuery( document.getElementById( 'min_quantity_' + product_id + '_' + rand_id ) ).val();
	/* 6.0.2: with a quantity grid the minimum applies to the grid's total ( its rows may stay at 0; they no longer carry the minimum themselves ). */
	var min_check_quantity = entered_quantity;
	var min_grid_inputs = wpec_scope.find( '.ec_details_grid_row:visible > input' ).filter( function( ){
		return jQuery( this ).attr( 'data-product-id' ) == product_id;
	} );
	if ( min_grid_inputs.length ) {
		min_check_quantity = 0;
		min_grid_inputs.each( function( ){
			min_check_quantity += Number( jQuery( this ).val( ) ) || 0;
		} );
	}
	if( min_check_quantity < min_quantity ){
		jQuery( document.getElementById( 'ec_addtocart_quantity_minimum_error_' + product_id + '_' + rand_id ) ).show( );
		errors++;
	}else{
		jQuery( document.getElementById( 'ec_addtocart_quantity_minimum_error_' + product_id + '_' + rand_id ) ).hide( );
	}
	// Maximum Quantity Check
	var max_quantity = jQuery( document.getElementById( 'max_quantity_' + product_id + '_' + rand_id ) ).val();
	if( max_quantity > 0 && entered_quantity > max_quantity ){
		jQuery( document.getElementById( 'ec_addtocart_quantity_maximum_error_' + product_id + '_' + rand_id ) ).show( );
		errors++;
	}else{
		jQuery( document.getElementById( 'ec_addtocart_quantity_maximum_error_' + product_id + '_' + rand_id ) ).hide( );
	}
	errors = window['wp_easycart_add_to_cart_js_validation_end_' + product_id + '_' + rand_id]( errors );
	if( errors > 0 ) {
		return false;
	} else {
		if ( typeof gtag !== "undefined" ) {
			var final_price = '';
			if ( jQuery( '.ec_details_title_' + product_id + '_' + rand_id ).length ) {
				if ( jQuery( document.getElementById( 'ec_final_price_' + product_id + '_' + rand_id ) ).length ) {
					final_price = wpeasycart_ga4_shown_price( product_id, rand_id, jQuery( document.getElementById( 'ec_final_price_' + product_id + '_' + rand_id ) ).text() );
				}
				ec_ga4_add_to_cart( jQuery( '.ec_details_model_number_sku_' + product_id + '_' + rand_id ).html(), jQuery( '.ec_details_title_' + product_id + '_' + rand_id ).html(), entered_quantity, final_price, jQuery( document.getElementById( 'currency_code_' + product_id + '_' + rand_id ) ).val() );
			} else if ( jQuery( document.getElementById( 'ec_product_title_' + product_id + '_' + rand_id ) ).length ) {
				if ( jQuery( document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id ) ).length ) {
					final_price = jQuery( document.getElementById( 'ec_base_price_' + product_id + '_' + rand_id ) ).html().replace( /[^\d\.\,]/g, '' );
				}
				ec_ga4_add_to_cart( jQuery( document.getElementById( 'ec_default_sku_' + product_id + '_' + rand_id ) ).val(), jQuery( document.getElementById( 'ec_product_title_' + product_id + '_' + rand_id ) ).val(), entered_quantity, final_price, jQuery( document.getElementById( 'currency_code_' + product_id + '_' + rand_id ) ).val() );
			}
		}
		return true;
	}
}

function ec_submit_product_review( product_id, rand_id, nonce ){
	var review_title = jQuery( document.getElementById( 'ec_review_title_' + product_id + '_' + rand_id ) ).val( );
	var review_score = 0;
	for( var i=1; i<=5; i++ ){
		if( jQuery( document.getElementById( 'ec_details_review_star' + i + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_product_details_star_on' ) || jQuery( document.getElementById( 'ec_details_review_star' + i + '_' + product_id + '_' + rand_id ) ).hasClass( 'ec_product_details_star_on_ele' ) ){
			review_score++;
		}
	}
	var review_message = jQuery( document.getElementById( 'ec_review_message_' + product_id + '_' + rand_id ) ).val( );
	if( review_title != "" && review_score != 0 && review_message != "" ){
		// Submit a filled out review
		jQuery( document.getElementById( 'ec_details_customer_review_loader_' + product_id + '_' + rand_id ) ).show( );
		jQuery( document.getElementById( 'ec_details_review_error_' + product_id + '_' + rand_id ) ).hide( );
		jQuery( document.getElementById( 'ec_details_review_save_error_' + product_id + '_' + rand_id ) ).hide( );
		/* 6.0.2: one request at a time; the button comes back, with the "could not be saved" message, when the review was not saved. */
		var review_buttons = jQuery( document.getElementById( 'ec_details_submit_review_button_row_' + product_id + '_' + rand_id ) ).find( 'input, button' );
		review_buttons.prop( 'disabled', true );
		var review_not_saved = function( ){
			jQuery( document.getElementById( 'ec_details_customer_review_loader_' + product_id + '_' + rand_id ) ).hide( );
			jQuery( document.getElementById( 'ec_details_review_save_error_' + product_id + '_' + rand_id ) ).show( );
			review_buttons.prop( 'disabled', false );
		};
		var data = {
			action: 'ec_ajax_insert_customer_review',
			review_title: review_title,
			review_score: review_score,
			review_message: review_message,
			product_id: product_id,
			nonce: nonce
		};
		jQuery.ajax( {
			url: wpeasycart_ajax_object.ajax_url,
			type: 'post',
			data: data,
			cache: false,
			success: function( data ){
				/* 6.0.2: only '1' means the review was saved ( '0', or nothing when the request was refused, used to show "submitted" too ). The form stays so the shopper can try again. */
				if ( '1' !== String( ( null == data ) ? '' : data ).replace( /^[﻿\s]+|\s+$/g, '' ) ) {
					review_not_saved( );
					return;
				}
				jQuery( document.getElementById( 'ec_details_customer_review_loader_' + product_id + '_' + rand_id ) ).hide( );
				jQuery( document.getElementById( 'ec_details_submit_review_button_row_' + product_id + '_' + rand_id ) ).hide( );
				jQuery( document.getElementById( 'ec_details_review_submitted_button_row_' + product_id + '_' + rand_id ) ).show( );
				jQuery( document.getElementById( 'ec_details_customer_review_success_' + product_id + '_' + rand_id ) ).show( ).delay( 1500 ).fadeOut( 'slow' );
			},
			error: function( ){
				review_not_saved( );
			}
		} );
	} else {
		jQuery( document.getElementById( 'ec_details_review_error_' + product_id + '_' + rand_id ) ).show( );
	}
}

function ec_details_get_sq_footage( is_metric, width, sub_width, height, sub_height ){
	var sub_width_decimal = ec_details_get_sub_dimension_decimal( sub_width );
	var sub_height_decimal = ec_details_get_sub_dimension_decimal( sub_height );
	if( '1' == is_metric ) {
		width = Number( Number( width ) + sub_width_decimal ) / 1000;
		height = Number( Number( height ) + sub_height_decimal ) / 1000;
	} else {
		width = Number( Number( width ) + sub_width_decimal ) / 12;
		height = Number( Number( height ) + sub_height_decimal ) / 12;
	}
	return width * height;
}

function ec_details_get_sub_dimension_decimal( sub_dimension ) {
	if( sub_dimension == "0" ){
		return 0;
	}else if( sub_dimension == "1/16" ){
		return .0625;
	}else if( sub_dimension == "1/8" ){
		return .1250;
	}else if( sub_dimension == "3/16" ){
		return .1875;
	}else if( sub_dimension == "1/4" ){
		return .2500;
	}else if( sub_dimension == "5/16" ){
		return .3125;
	}else if( sub_dimension == "3/8" ){
		return .3750;
	}else if( sub_dimension == "7/16" ){
		return .4375;
	}else if( sub_dimension == "1/2" ){
		return .5000;
	}else if( sub_dimension == "9/16" ){
		return .5625;
	}else if( sub_dimension == "5/8" ){
		return .6250;
	}else if( sub_dimension == "11/16" ){
		return .6875;
	}else if( sub_dimension == "3/4" ){
		return .7500;
	}else if( sub_dimension == "13/16" ){
		return .8125;
	}else if( sub_dimension == "7/8" ){
		return .8750;
	}else if( sub_dimension == "15/16" ){
		return .9375;
	}else{
		return 0;
	}
}

/*
 * 6.0.2: the Offers price preview for "Your price". WP EasyCart PRO 6.0.2 prints a running offer's rules on the price's
 * wrapper ( data-wpec-offer-rules, from ec_offer_display::get_product_price_rules() ); this is the lowest price they give
 * ( the price itself when none lowers it ), worked out as ec_offer_display::apply_price_rules() does. The Elementor
 * product widgets' script ( product-buy.js ) uses the same sums. 6.0.4: when every rule carries stack ( offers the cart
 * takes together, WP EasyCart PRO 6.0.4 ), what they take off is added up instead.
 */
function wpeasycart_offer_price( rules, price ) {
	price = Number( price );
	var best = price;
	if ( ! jQuery.isArray( rules ) || ! ( price > 0 ) ) {
		return price;
	}
	var stack = rules.length > 1;
	var taken = 0;
	jQuery.each( rules, function( i, rule ) {
		var value = Number( rule && rule.value ) || 0;
		var floor = Number( rule && rule.floor ) || 0;
		var kind = rule ? rule.kind : 'percent';
		var preview;
		if ( 'percent' === kind ) {
			preview = Math.max( 0, price - ( price * value / 100 ) );
		} else if ( 'amount' === kind ) {
			preview = Math.max( 0, price - value );
		} else {
			preview = Math.min( price, value );
		}
		if ( floor > 0 && preview < floor ) {
			preview = Math.min( price, floor );
		}
		if ( preview < best ) {
			best = preview;
		}
		taken += price - preview;
		if ( ! ( rule && rule.stack ) ) {
			stack = false;
		}
	} );
	return stack ? Math.max( 0, price - taken ) : best;
}

/*
 * 6.0.2: "Your price" as the options come to ( amount ), with a running offer applied to the unit price ( unit ) and
 * scaled to the amount ( area pricing, a price multiplier ), when the price's wrapper carries the offer's rules
 * ( wp_easycart_offers_your_price() ). Marks the price so the Elementor Add to Cart widget does not take the offer off twice.
 */
function wpeasycart_final_price_text( product_id, rand_id, unit, amount ) {
	var el = document.getElementById( 'ec_final_price_' + product_id + '_' + rand_id );
	var wrap = el ? jQuery( el ).closest( '[data-wpec-offer-rules]' ) : jQuery( [] );
	unit = Number( unit );
	if ( wrap.length && unit > 0 ) {
		var rules = null;
		try {
			rules = JSON.parse( wrap.attr( 'data-wpec-offer-rules' ) );
		} catch ( err ) {
			rules = null;
		}
		var offered = wpeasycart_offer_price( rules, unit );
		if ( offered < unit - 0.000001 ) {
			var text = ec_details_format_money_v2( product_id, rand_id, Number( amount ) * ( offered / unit ) );
			jQuery( el ).data( 'wpecOfferText', jQuery.trim( text ) );
			return text;
		}
	}
	return ec_details_format_money_v2( product_id, rand_id, amount );
}

function ec_details_format_money_v2( product_id, rand_id, price ) {
	var currency_symbol = jQuery( document.getElementById( 'currency_symbol_' + product_id + '_' + rand_id ) ).val();
	var num_decimals = Number( jQuery( document.getElementById( 'num_decimals_' + product_id + '_' + rand_id ) ).val() );
	var decimal_symbol = jQuery( document.getElementById( 'decimal_symbol_' + product_id + '_' + rand_id ) ).val();
	var grouping_symbol = jQuery( document.getElementById( 'grouping_symbol_' + product_id + '_' + rand_id ) ).val();
	var conversion_rate = Number( jQuery( document.getElementById( 'conversion_rate_' + product_id + '_' + rand_id ) ).val() );
	var symbol_location = Number( jQuery( document.getElementById( 'symbol_location_' + product_id + '_' + rand_id ) ).val() );
	var currency_code = jQuery( document.getElementById( 'currency_code_' + product_id + '_' + rand_id ) ).val();
	var show_currency_code = Number( jQuery( document.getElementById( 'show_currency_code_' + product_id + '_' + rand_id ) ).val() );
	price = wpeasycart_price_round( price * Number( conversion_rate ), num_decimals ); /* 6.0.5: as the cart rounds it ( number_format() ) */
	var n = price,
		num_decimals = isNaN(num_decimals = Math.abs(num_decimals)) ? 2 : num_decimals,
		decimal_symbol = decimal_symbol == undefined ? "." : decimal_symbol,
		grouping_symbol = grouping_symbol == undefined ? "," : grouping_symbol,
		i = parseInt(n = price.toFixed( num_decimals ) ) + "",
		j = (j = i.length) > 3 ? j % 3 : 0;
	var formatted = (j ? i.substr(0, j) + grouping_symbol : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + grouping_symbol) + (num_decimals ? decimal_symbol + Math.abs(n - i).toFixed(num_decimals).slice(2) : "");
	if( symbol_location ){
		formatted = currency_symbol + formatted;
	}else{
		formatted = formatted + currency_symbol;
	}
	if( show_currency_code == '1' ){
		formatted = currency_code + ' ' +  formatted;
	}
	return formatted;
}
function ec_pricing_round( number, places ) {
	var multiplier = Math.pow(10, places+2); // get two extra digits
	var fixed = Math.floor(number*multiplier); // convert to integer
	fixed += 50; // round down on anything less than x.xxx50
	fixed = Math.floor(fixed/100); // chop off last 2 digits
	return fixed/Math.pow(10, places);
}
function ec_admin_save_product_details_options( ){
	jQuery( "#ec_admin_page_updated_loader" ).show( );
	jQuery( "#ec_admin_loader_bg" ).show( );
	var data = {
		action: 'ec_ajax_save_product_details_options',
		ec_option_details_main_color: jQuery( document.getElementById( 'ec_option_details_main_color' ) ).val( ),
		ec_option_details_second_color: jQuery( document.getElementById( 'ec_option_details_second_color' ) ).val( ),
		ec_option_details_columns_desktop: jQuery( document.getElementById( 'ec_option_details_columns_desktop' ) ).val( ),
		ec_option_details_columns_laptop: jQuery( document.getElementById( 'ec_option_details_columns_laptop' ) ).val( ),
		ec_option_details_columns_tablet_wide: jQuery( document.getElementById( 'ec_option_details_columns_tablet_wide' ) ).val( ),
		ec_option_details_columns_tablet: jQuery( document.getElementById( 'ec_option_details_columns_tablet' ) ).val( ),
		ec_option_details_columns_smartphone: jQuery( document.getElementById( 'ec_option_details_columns_smartphone' ) ).val( ),
		ec_option_use_dark_bg: jQuery( document.getElementById( 'ec_option_use_dark_bg' ) ).val( ),
		nonce: jQuery( '#product_details_save_options_nonce' ).val()
	}
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false,
		success: function(data){ 
			jQuery( document.getElementById( 'ec_admin_page_updated_loader' ) ).hide( );
			jQuery( document.getElementById( 'ec_admin_page_updated' ) ).show( );
			jQuery( document.getElementById( 'ec_admin_loader_bg' ) ).fadeOut( 'slow' );
			location.reload( );
		}
	} );
	jQuery( document.getElementById( 'ec_page_editor' ) ).animate( { left:'-290px' }, {queue:false, duration:220} ).removeClass( 'ec_display_editor_true' ).addClass( 'ec_display_editor_false' );
}
function ec_admin_show_description_editor( product_id, rand_id ) {
	jQuery( '.ec_details_description_content_' + product_id + '_' + rand_id ).hide( );
	jQuery( '.ec_details_description_editor_' + product_id + '_' + rand_id ).show( );
}
function ec_admin_save_description_editor( model_number, product_id, rand_id ) {
	var new_html = ( jQuery( document.getElementById( 'desc_' + model_number ) ).is( ':visible' ) ) ? jQuery( document.getElementById( 'desc_' + model_number ) ).val() : tinymce.get( 'desc_' + model_number ).getContent();
	jQuery( '.ec_details_description_editor_' + product_id + '_' + rand_id ).hide( );
	jQuery( '.ec_details_description_content_' + product_id + '_' + rand_id ).html( new_html ).show( );
	var data = {
		action: 'ec_ajax_ec_update_product_description',
		description: new_html,
		product_id: product_id,
		nonce: jQuery( document.getElementById( 'product_details_update_product_description_nonce_' + product_id + '_' + rand_id ) ).val()
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}
function ec_admin_show_specifications_editor( product_id, rand_id ) {
	jQuery( '.ec_details_specifications_content_' + product_id + '_' + rand_id ).hide( );
	jQuery( '.ec_details_specifications_editor_' + product_id + '_' + rand_id ).show( );
}
function ec_admin_save_specifications_editor( model_number, product_id, rand_id ){
	var new_html = ( jQuery( document.getElementById( 'specs_' + model_number ) ).is( ':visible' ) ) ? jQuery( document.getElementById( 'specs_' + model_number ) ).val() : tinymce.get( 'specs_' + model_number ).getContent();
	jQuery( '.ec_details_specifications_editor_' + product_id + '_' + rand_id ).hide( );
	jQuery( '.ec_details_specifications_content_' + product_id + '_' + rand_id ).html( new_html ).show( );
	var data = {
		action: 'ec_ajax_ec_update_product_specifications',
		specifications: new_html,
		product_id: product_id,
		nonce: jQuery( document.getElementById( 'product_details_update_product_specs_nonce_' + product_id + '_' + rand_id ) ).val()
	};
	jQuery.ajax( {
		url: wpeasycart_ajax_object.ajax_url,
		type: 'post',
		data: data,
		cache: false
	} );
}

if ( '' != wpeasycart_ajax_object.ga4_id || '' != wpeasycart_ajax_object.ga4_conv_id ) {
	/* 6.0.2: Google's script is added once tags may run ( wpeasycart_gtag_load() ); gtag() only queues until then. */
	window.wpeasycart_gtag_load = function() {
		if ( window.wpeasycart_gtag_loaded ) {
			return;
		}
		window.wpeasycart_gtag_loaded = true;
		var newScript = document.createElement( 'script' );
		newScript.type = 'text/javascript';
		newScript.setAttribute( 'async', 'true' );
		if ( '' != wpeasycart_ajax_object.ga4_id ) {
			newScript.setAttribute( 'src', 'https://www.googletagmanager.com/gtag/js?id=' + wpeasycart_ajax_object.ga4_id );
		} else {
			newScript.setAttribute( 'src', 'https://www.googletagmanager.com/gtag/js?id=' + wpeasycart_ajax_object.ga4_conv_id );
		}
		jQuery( 'head' ).append( newScript );
	};
	window.dataLayer = window.dataLayer || [];
	function gtag(){ dataLayer.push( arguments ); }
	/* 6.0.2: Google consent mode ( Settings › Integrations › Cookie consent ). While consent is asked, Google's script waits
	   until the shopper accepts marketing or statistics cookies ( Google's basic consent mode: nothing reaches Google before
	   that ), and ads and analytics storage follow each answer. The consent bridge ( window.wpeasycart_consent: the cookie
	   banner found, Cookiebot, CookieYes, Complianz ) or the WP Consent API's change event tells it. With Google Consent Mode
	   chosen, the script loads at once and the store's cookie banner sets Google's consent. */
	( function() {
		var mode = wpeasycart_ajax_object.consent_mode || 'off';
		if ( 'google_consent_mode' === mode ) {
			window.wpeasycart_gtag_load();
			return;
		}
		var bridge = ( 'off' !== mode && window.wpeasycart_consent ) ? window.wpeasycart_consent : null;
		var consent_api = ( 'wp_consent_api' === mode );
		var ads = ( '0' !== wpeasycart_ajax_object.consent_marketing );
		var stats = ( '0' !== wpeasycart_ajax_object.consent_statistics );
		if ( bridge ) {
			ads = bridge.has( 'marketing' );
			stats = bridge.has( 'statistics' );
		} else if ( consent_api && 'function' === typeof window.wp_has_consent ) {
			ads = !! window.wp_has_consent( 'marketing' );
			stats = !! window.wp_has_consent( 'statistics' );
		}
		var asking = ( 'off' !== mode );
		if ( asking || ! ads || ! stats ) {
			gtag( 'consent', 'default', {
				ad_storage: ads ? 'granted' : 'denied',
				ad_user_data: ads ? 'granted' : 'denied',
				ad_personalization: ads ? 'granted' : 'denied',
				analytics_storage: stats ? 'granted' : 'denied',
				wait_for_update: 500
			} );
		}
		if ( ! asking || ads || stats ) {
			window.wpeasycart_gtag_load();
		}
		if ( bridge ) {
			bridge.on( function( state ) {
				var update = {};
				var has_update = false;
				if ( null !== state.marketing ) {
					update.ad_storage = update.ad_user_data = update.ad_personalization = state.marketing ? 'granted' : 'denied';
					has_update = true;
				}
				if ( null !== state.statistics ) {
					update.analytics_storage = state.statistics ? 'granted' : 'denied';
					has_update = true;
				}
				if ( has_update ) {
					gtag( 'consent', 'update', update );
				}
				if ( true === state.marketing || true === state.statistics ) {
					window.wpeasycart_gtag_load();
				}
			} );
		} else if ( consent_api ) {
			document.addEventListener( 'wp_listen_for_consent_change', function( e ) {
				var changed = ( e && e.detail ) ? e.detail : {};
				var update = {};
				var has_update = false;
				if ( changed.marketing ) {
					update.ad_storage = update.ad_user_data = update.ad_personalization = ( 'allow' === changed.marketing ) ? 'granted' : 'denied';
					has_update = true;
				}
				if ( changed.statistics ) {
					update.analytics_storage = ( 'allow' === changed.statistics ) ? 'granted' : 'denied';
					has_update = true;
				}
				if ( has_update ) {
					gtag( 'consent', 'update', update );
				}
				if ( 'allow' === changed.marketing || 'allow' === changed.statistics ) {
					window.wpeasycart_gtag_load();
				}
			} );
		}
	} )();
	gtag( 'js', new Date() );
	if ( '' != wpeasycart_ajax_object.ga4_id ) {
		gtag( 'config', wpeasycart_ajax_object.ga4_id ); // , {'debug_mode': false} );
	}
	if ( '' != wpeasycart_ajax_object.ga4_conv_id ) {
		gtag( 'config', wpeasycart_ajax_object.ga4_conv_id ); // , {'debug_mode': false} );
	}
}

/* 6.0.2: GA4 events carry the store's base currency ( ec_option_base_currency ), which every amount they send is in, never the shopper's display currency. */
function wpeasycart_ga4_currency( currency ) {
	if ( typeof wpeasycart_ajax_object !== 'undefined' && wpeasycart_ajax_object.ga4_currency ) {
		return wpeasycart_ajax_object.ga4_currency;
	}
	return currency;
}

/* 6.0.2: a price as shown on a product ( ec_details_format_money_v2(): the shopper's currency, symbol and number format ) as a plain amount in the base currency. */
function wpeasycart_ga4_shown_price( product_id, rand_id, shown ) {
	var field = function( name ) {
		var input = document.getElementById( name + '_' + product_id + '_' + rand_id );
		return ( input ) ? String( input.value ) : '';
	};
	var text = String( shown );
	var grouping_symbol = field( 'grouping_symbol' );
	var decimal_symbol = field( 'decimal_symbol' );
	var conversion_rate = Number( field( 'conversion_rate' ) );
	var strip = [ field( 'currency_code' ), field( 'currency_symbol' ) ];
	for ( var i = 0; i < strip.length; i++ ) {
		if ( '' != strip[ i ] ) {
			text = text.split( strip[ i ] ).join( '' );
		}
	}
	if ( '' != grouping_symbol && grouping_symbol != decimal_symbol ) {
		text = text.split( grouping_symbol ).join( '' );
	}
	if ( '' != decimal_symbol && '.' != decimal_symbol ) {
		text = text.split( decimal_symbol ).join( '.' );
	}
	var amount = parseFloat( text.replace( /[^\d\.]/g, '' ) );
	if ( isNaN( amount ) ) {
		return '';
	}
	if ( conversion_rate > 0 ) {
		amount = amount / conversion_rate;
	}
	return ( Math.round( amount * 100 ) / 100 ).toFixed( 2 );
}

function ec_ga4_remove_from_cart( model_number, title, quantity, price, currency, manufacturer_name, is_tag_manager = false ) {
	currency = wpeasycart_ga4_currency( currency );
	if ( is_tag_manager ) {
		dataLayer.push( { ecommerce: null } );  // Clear the previous ecommerce object.
		dataLayer.push( {
			event: 'remove_from_cart',
			ecommerce: {
				currency: currency,
				value: Number( price ) * Number( quantity ),
				items: [ {
					item_id: model_number,
					item_name: title,
					index: 0,
					price: parseFloat( price ),
					item_brand: manufacturer_name,
					quantity: quantity
				} ]
			}
		} );
	} else {
		gtag( 'event', 'remove_from_cart', {
			currency: currency,
			value: Number( price ) * Number( quantity ),
			items: [ {
				item_id: model_number,
				item_name: title,
				index: 0,
				price: parseFloat( price ),
				item_brand: manufacturer_name,
				quantity: quantity
			} ]
		} );
	}
}

function ec_ga4_add_to_cart( model_number, title, quantity, price, currency, manufacturer_name, is_tag_manager = false ) {
	currency = wpeasycart_ga4_currency( currency );
	if ( is_tag_manager ) {
		dataLayer.push( { ecommerce: null } );  // Clear the previous ecommerce object.
		dataLayer.push( {
			event: 'add_to_cart',
			ecommerce: {
				currency: currency,
				value: Number( price ) * Number( quantity ),
				items: [ {
					item_id: model_number,
					item_name: title,
					index: 0,
					price: parseFloat( price ),
					item_brand: manufacturer_name,
					quantity: quantity
				} ]
			}
		} );
	} else {
		gtag( 'event', 'add_to_cart', {
			currency: currency,
			value: Number( price ) * Number( quantity ),
			items: [ {
				item_id: model_number,
				item_name: title,
				index: 0,
				price: parseFloat( price ),
				item_brand: manufacturer_name,
				quantity: quantity
			} ]
		} );
	}
}

function wp_easycart_cart_checkout_click() {
	jQuery( '.ec_cart_button_checkout' ).addClass( 'wp-easycart-running' );
}

function wp_easycart_cart_shipping_next() {
	jQuery( '.ec_cart_button_shipping_next' ).parent().addClass( 'wp-easycart-running' );
}

function wpeasycart_mobile_summary() {
	if ( jQuery( '.ec_cart_mobile_summary_content' ).is( ':visible' ) ) {
		jQuery( '.ec_cart_mobile_summary_content' ).hide();
		jQuery( '.dashicons-arrow-down-alt2' ).show();
		jQuery( '.dashicons-arrow-up-alt2' ).hide();
	} else {
		jQuery( '.ec_cart_mobile_summary_content' ).show();
		jQuery( '.dashicons-arrow-down-alt2' ).hide();
		jQuery( '.dashicons-arrow-up-alt2' ).show();
	}
}

function wpeasycart_load_locations( locations ) {
	for ( var i = 0; i < locations.length; i++ ) {
		jQuery( '.wpeasycart-location-list' ).append( locations[ i ].location_html );
	}
}
function wpeasycart_trigger_location_geo( product_id = false, type = false ) {
	jQuery( '.wp-easycart-location-popup-error' ).hide();
	if (navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(
			( position ) => {
				jQuery( '.wpeasycart-location-list-loader' ).show();
				jQuery( '.wpeasycart-location-list' ).html( '' );
				var lat = position.coords.latitude;
				var long = position.coords.longitude;
				var data = {
					action: 'ec_ajax_location_find_by_geo',
					lat: lat,
					long: long,
					product_id: product_id,
					type: type,
					nonce: jQuery( '#wpeasycart_location_nonce' ).val()
				};
				jQuery.ajax( {
					url: wpeasycart_ajax_object.ajax_url,
					type: 'post',
					data: data,
					cache: false,
					success: function( response ) {
						jQuery( '.wpeasycart-location-list-loader' ).hide();
						if ( response.data.locations ) {
							wpeasycart_load_locations( response.data.locations );
						}
					}
				} );
			},
			( error ) => {
				switch( error.code ) {
					case error.PERMISSION_DENIED:
						jQuery( '.wp-easycart-location-popup-error.error1' ).show();
						break;
					case error.POSITION_UNAVAILABLE:
						jQuery( '.wp-easycart-location-popup-error.error2' ).show();
						break;
					case error.TIMEOUT:
						jQuery( '.wp-easycart-location-popup-error.error3' ).show();
						break;
					case error.UNKNOWN_ERROR:
						jQuery( '.wp-easycart-location-popup-error.error4' ).show();
						break;
				}
				jQuery( '.wpeasycart-location-list-loader' ).show();
				jQuery( '.wpeasycart-location-list' ).html( '' );
				var data = {
					action: 'ec_ajax_location_find_by_geo',
					lat: 0,
					long: 0,
					nonce: jQuery( '#wpeasycart_location_nonce' ).val()
				};
				jQuery.ajax( {
					url: wpeasycart_ajax_object.ajax_url,
					type: 'post',
					data: data,
					cache: false,
					success: function( response ) {
						jQuery( '.wpeasycart-location-list-loader' ).hide();
						if ( response.data.locations ) {
							wpeasycart_load_locations( response.data.locations );
						}
					}
				} );
			}
		);
	} else {
		jQuery( '.wp-easycart-location-popup-error.error5' ).show();
	}
}

/**
 * Product tabs ( 6.0.2 ). The panel a tab opens: Description, Specifications and Customer Reviews by class, an extension's
 * tab by its data-tab-id ( panel class ec_details_{id}_tab ), looked up inside the tab list's own product block first so
 * the same product twice on a page opens one panel.
 */
function wpeasycart_tabs_panel( tab ) {
	var li = jQuery( tab ), list = li.parent( ), product_id = list.attr( 'data-product-id' ), rand_id = list.attr( 'data-rand-id' ), area, id, panel;
	if ( li.hasClass( 'ec_description' ) ) {
		return jQuery( '.ec_details_description_tab_' + product_id + '_' + rand_id );
	} else if ( li.hasClass( 'ec_specifications' ) ) {
		return jQuery( '.ec_details_specifications_tab_' + product_id + '_' + rand_id );
	} else if ( li.hasClass( 'ec_customer_reviews' ) ) {
		return jQuery( '.ec_details_customer_reviews_tab_' + product_id + '_' + rand_id );
	}
	id = li.attr( 'data-tab-id' );
	if ( ! id ) {
		return jQuery( );
	}
	area = wpeasycart_tabs_area( list );
	panel = area.children( '.ec_details_' + id + '_tab' );
	return panel.length ? panel : jQuery( '.ec_details_' + id + '_tab' );
}

/** The block that holds a tab list and its panels ( .ec_details_extra_area ). */
function wpeasycart_tabs_area( list ) {
	var area = jQuery( list ).closest( '.ec_details_extra_area' );
	return area.length ? area : jQuery( '.ec_details_extra_area_' + jQuery( list ).attr( 'data-product-id' ) + '_' + jQuery( list ).attr( 'data-rand-id' ) );
}

/**
 * Open a tab: mark it selected, hide the other panels and show its own. toggle_open opens or closes the list on phones,
 * where it folds into one row ( a click, Enter or Space; the arrow keys leave it as it is ).
 */
function wpeasycart_tabs_select( tab, toggle_open ) {
	var li = jQuery( tab ).first( ), list = li.parent( );
	if ( ! li.length ) {
		return;
	}
	list.children( '.ec_details_tab' ).removeClass( 'ec_active' ).attr( { 'aria-selected': 'false', tabindex: '-1' } );
	li.addClass( 'ec_active' ).attr( { 'aria-selected': 'true', tabindex: '0' } );
	wpeasycart_tabs_area( list ).children( 'div' ).hide( );
	wpeasycart_tabs_panel( li ).show( );
	if ( toggle_open ) {
		list.toggleClass( 'ec_is_open' );
	}
	li.trigger( 'wpeasycart_tab_selected' );
}

/**
 * Give tab lists the ARIA tabs pattern ( tablist, tab, tabpanel, aria-selected, aria-controls ), once each. Tabs added by
 * extensions and theme copies of the product templates get it too. Leaves exactly one tab open: the one marked active,
 * else the first.
 */
function wpeasycart_tabs_init( lists ) {
	jQuery( lists ).each( function( ){
		var list = jQuery( this ), tabs, active, base, open = 0;
		if ( list.attr( 'data-wpec-tabs' ) ) {
			return;
		}
		list.attr( { 'data-wpec-tabs': '1', role: 'tablist' } );
		tabs = list.children( '.ec_details_tab' );
		active = tabs.filter( '.ec_active' ).first( );
		if ( ! active.length ) {
			active = tabs.first( );
		}
		base = 'ec_details_tab_' + list.attr( 'data-product-id' ) + '_' + list.attr( 'data-rand-id' ) + '_';
		tabs.each( function( i ){
			var li = jQuery( this ), panel = wpeasycart_tabs_panel( li ).first( ), selected = li.is( active );
			if ( ! this.id ) {
				this.id = base + i;
			}
			li.attr( { role: 'tab', 'aria-selected': selected ? 'true' : 'false', tabindex: selected ? '0' : '-1' } );
			if ( panel.length ) {
				if ( ! panel.attr( 'id' ) ) {
					panel.attr( 'id', this.id + '_panel' );
				}
				li.attr( 'aria-controls', panel.attr( 'id' ) );
				panel.attr( { role: 'tabpanel', 'aria-labelledby': this.id } );
				if ( undefined === panel.attr( 'tabindex' ) ) {
					panel.attr( 'tabindex', '0' );
				}
				if ( 'none' !== panel.css( 'display' ) ) {
					open++;
				}
			}
		} );
		if ( active.length && ( 1 !== tabs.filter( '.ec_active' ).length || 1 !== open ) ) {
			wpeasycart_tabs_select( active, false );
		}
	} );
}

/**
 * #reviews ( 6.0.2 ): the Product Rating widget links to a product page's #reviews. Opens that page's Customer Reviews tab
 * ( or goes to the reviews list when it is not in tabs ) and brings it into view, when the page loads and when the address
 * changes. A page with the Elementor product widgets' own reviews leaves it to their script ( product-info.js ).
 */
function wpeasycart_reviews_from_hash( ) {
	var hash = '', li, target, reduced;
	try {
		hash = decodeURIComponent( ( window.location.hash || '' ).replace( /^#/, '' ) );
	} catch ( err ) {
		hash = '';
	}
	if ( 'reviews' !== hash || jQuery( '[data-wpec-pi-reviews]' ).length ) {
		return;
	}
	li = jQuery( '.ec_details_tabs > .ec_customer_reviews' ).first( );
	if ( li.length ) {
		wpeasycart_tabs_init( li.parent( ) );
		wpeasycart_tabs_select( li, false );
		target = li.closest( '.ec_details_extra_area' );
		if ( ! target.length ) {
			target = li.parent( );
		}
	} else {
		target = jQuery( '.ec_details_customer_review_list' ).first( ).parent( );
	}
	if ( target.length && target[0].scrollIntoView ) {
		reduced = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		target[0].scrollIntoView( { behavior: reduced ? 'auto' : 'smooth', block: 'start' } );
	}
}
