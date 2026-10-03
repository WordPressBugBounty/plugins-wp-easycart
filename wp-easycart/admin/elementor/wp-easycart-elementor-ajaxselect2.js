/**
 * WP EasyCart Elementor Ajax Select2 Controler for  Elementor
 *
 * @package  Wp_Easycart_Elementor
 * @author   WP EasyCart
 */

/**
 * The ids in a saved control value ( array for a multiple picker, string for a single one ), in saved order.
 *
 * @since 6.0.2
 */
function wpecAjaxSelect2SavedIds( value ) {
	var list = [];
	if ( Array.isArray( value ) ) {
		list = value;
	} else if ( 'number' === typeof value ) {
		list = [ value ];
	} else if ( 'string' === typeof value && '' !== value ) {
		list = value.split( ',' );
	}
	var ids = [];
	for ( var i = 0; i < list.length; i++ ) {
		var id = String( list[ i ] ).trim();
		if ( '' !== id && -1 === ids.indexOf( id ) ) {
			ids.push( id );
		}
	}
	return ids;
}

jQuery( window ).on(
	'elementor:init',
	function() {
		var WPECControlAjaxselect2ItemView = elementor.modules.controls.BaseData.extend(
			{
				onReady: function() {
					var self = this,
						el = self.ui.select,
						url = el.attr( 'data-ajax-url' ),
						nonce = el.attr( 'data-ajax-nonce' ),
						multiple = !! el.prop( 'multiple' ),
						savedIds = wpecAjaxSelect2SavedIds( self.getControlValue() );

					/* 6.0.2: the saved ids are selected as "#id" before Select2 starts ( in saved order, without telling Elementor ),
					 * so a pick made before the names arrive adds to them instead of replacing them. The lookup below only renames. */
					jQuery.each(
						savedIds,
						function( i, id ) {
							var present = el.find( 'option' ).filter(
								function() {
									return String( this.value ) === id;
								}
							);
							if ( ! present.length ) {
								el.append( new Option( '#' + id, id, true, true ) );
							}
						}
					);
					if ( savedIds.length ) {
						el.val( multiple ? savedIds : savedIds[0] );
					}

					/* 6.0.2: searches are paged ( 30 at a time, more as the list scrolls ). */
					el.select2(
						{
							ajax: {
								url: url,
								dataType: 'json',
								delay: 250,
								cache: true,
								data: function( params ) {
									return {
										s: params.term || '',
										page: params.page || 1
									};
								},
								processResults: function( data ) {
									return {
										results: ( data && data.results ) ? data.results : [],
										pagination: {
											more: !! ( data && data.pagination && data.pagination.more )
										}
									};
								},
								beforeSend: function( xhr ) {
									xhr.setRequestHeader( 'X-WP-Nonce', nonce );
								}
							}
						}
					);

					if ( ! savedIds.length ) {
						return;
					}

					/* 6.0.2: name the saved selection without writing it back. The "#id" options were added in saved order above
					 * ( the Products widget shows picked products in that order ); here they are only renamed and Select2 is told
					 * ( change.select2 ), so opening a widget never rewrites, reorders or re-renders it, and a pick made meanwhile
					 * is kept. An id the lookup does not return ( deleted, or the lookup failed ) stays as "#id", so saving the
					 * page never drops it. */
					var showSaved = function( results ) {
						var names = {};
						jQuery.each(
							results || [],
							function( i, row ) {
								if ( row && 'undefined' !== typeof row.id ) {
									names[ String( row.id ) ] = row.text;
								}
							}
						);
						el.find( 'option' ).each(
							function() {
								var id = String( this.value );
								if ( -1 !== savedIds.indexOf( id ) && 'undefined' !== typeof names[ id ] && null !== names[ id ] && '' !== names[ id ] ) {
									/* A fresh option ( same place, same selected state ): Select2 caches what it drew for the old one. */
									jQuery( this ).replaceWith( new Option( String( names[ id ] ), id, this.defaultSelected, this.selected ) );
								}
							}
						);
						el.trigger( 'change.select2' );
					};

					jQuery.ajax(
						{
							url: url,
							dataType: 'json',
							data: {
								ids: savedIds.join( ',' )
							},
							beforeSend: function( xhr ) {
								xhr.setRequestHeader( 'X-WP-Nonce', nonce );
							}
						}
					).done(
						function( ret ) {
							showSaved( ( ret && ret.results ) ? ret.results : [] );
						}
					).fail(
						function() {
							showSaved( [] );
						}
					);
				},
				onBeforeDestroy: function onBeforeDestroy() {
					if ( this.ui.select.data( 'select2' ) ) {
						this.ui.select.select2( 'destroy' );
					}
					this.$el.remove();
				}
			}
		);
		elementor.addControlView( 'wpecajaxselect2', WPECControlAjaxselect2ItemView );
	}
);
