/**
 * WP EasyCart in the Elementor editor panel ( 6.0.2 ).
 *
 * - "Replace with <new widget>" ( event wpeasycart:replaceLegacyWidget, the button on a retired widget's Content tab ): asks
 *   first, gets the new widget's settings from the server ( AJAX ecv2_elementor_replace_widget, the module's map ), inserts the
 *   new widget where the old one was and removes the old one, as one Undo step where Elementor allows it. Never automatic.
 * - "Save and show in preview" ( event wpeasycart:applyPreview, Page Settings › WP EasyCart preview ): saves the page's preview
 *   product / category and reloads the preview, which reads them.
 *
 * Needs Elementor 3.x ( $e commands ); every call is guarded, so an older editor only loses these buttons.
 */
( function( $, config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	var bound = false;
	var busy = false;

	function text( key, fallback ) {
		return ( config.i18n && config.i18n[ key ] ) ? config.i18n[ key ] : fallback;
	}

	/* The element a panel control belongs to ( Elementor 3.0+ control views carry their container ). */
	function containerFor( view ) {
		if ( view && view.container ) {
			return view.container;
		}
		if ( view && view.options && view.options.container ) {
			return view.options.container;
		}
		try {
			var page = window.elementor.getPanelView().getCurrentPageView();
			var edited = page && page.getOption ? page.getOption( 'editedElementView' ) : null;
			if ( edited && edited.getContainer ) {
				return edited.getContainer();
			}
		} catch ( err ) {}
		return null;
	}

	/* Plain text for the dialog, which reads its strings as markup. */
	function escapeText( value ) {
		return $( '<div></div>' ).text( String( value ) ).html( );
	}

	function confirmThen( title, message, onConfirm ) {
		if ( window.elementorCommon && window.elementorCommon.dialogsManager ) {
			try {
				window.elementorCommon.dialogsManager.createWidget( 'confirm', {
					id: 'wpeasycart-replace-widget',
					headerMessage: escapeText( title ),
					message: escapeText( message ),
					position: { my: 'center center', at: 'center center' },
					strings: { confirm: text( 'replace', 'Replace' ), cancel: text( 'cancel', 'Cancel' ) },
					onConfirm: onConfirm
				} ).show();
				return;
			} catch ( err ) {}
		}
		if ( window.confirm( title + '\n\n' + message ) ) {
			onConfirm();
		}
	}

	function notify( message ) {
		try {
			if ( window.elementor && window.elementor.notifications ) {
				window.elementor.notifications.showToast( { message: message } );
				return;
			}
		} catch ( err ) {}
		window.alert( message );
	}

	/* The widget's saved settings: only the values that differ from the defaults, as Elementor saves them. */
	function savedSettings( container ) {
		try {
			if ( container.settings && container.settings.toJSON ) {
				return container.settings.toJSON( { remove: [ 'default' ] } );
			}
			if ( container.model && container.model.get( 'settings' ) ) {
				return container.model.get( 'settings' ).toJSON( { remove: [ 'default' ] } );
			}
		} catch ( err ) {}
		return {};
	}

	function insertReplacement( container, answer ) {
		var parent = container.parent;
		var at = -1;
		try {
			at = parent.model.get( 'elements' ).indexOf( container.model );
		} catch ( err ) {}
		var historyId = null;
		try {
			if ( $e.internal && window.elementor && window.elementor.documents ) {
				historyId = $e.internal( 'document/history/start-log', {
					container: container,
					type: 'add',
					title: answer.title
				} );
			}
		} catch ( err ) {
			historyId = null;
		}
		var created = null;
		try {
			var options = ( at > -1 ) ? { at: at } : {};
			created = $e.run( 'document/elements/create', {
				container: parent,
				model: {
					elType: 'widget',
					widgetType: answer.replacement,
					settings: answer.settings || {}
				},
				options: options
			} );
			$e.run( 'document/elements/delete', { container: container } );
		} finally {
			if ( null !== historyId ) {
				try {
					$e.internal( 'document/history/end-log', { id: historyId } );
				} catch ( err ) {}
			}
		}
		if ( created ) {
			try {
				if ( $e.commands && $e.commands[ 'document/elements/select' ] ) {
					$e.run( 'document/elements/select', { container: created } );
				} else if ( created.view && created.model ) {
					$e.run( 'panel/editor/open', { model: created.model, view: created.view } );
				}
			} catch ( err ) {}
		}
	}

	function replaceLegacy( view ) {
		if ( busy || ! window.$e ) {
			return;
		}
		var container = containerFor( view );
		if ( ! container || ! container.model || ! container.parent ) {
			notify( text( 'failed', 'The widget could not be replaced.' ) );
			return;
		}
		var legacy = container.model.get( 'widgetType' );
		var newTitle = '';
		if ( view && view.model && view.model.get ) {
			newTitle = String( view.model.get( 'wpec_title' ) || view.model.get( 'text' ) || '' );
		}
		newTitle = newTitle || legacy;
		var message = text( 'replaceMessage', 'This puts %s in its place.' ).replace( '%s', newTitle );
		confirmThen( text( 'replaceTitle', 'Replace this widget?' ), message, function() {
			busy = true;
			$.ajax( {
				url: config.ajaxUrl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'ecv2_elementor_replace_widget',
					nonce: config.nonce,
					widget: legacy,
					settings: JSON.stringify( savedSettings( container ) )
				}
			} ).done( function( response ) {
				if ( ! response || ! response.success || ! response.data || ! response.data.replacement ) {
					notify( ( response && response.data && response.data.message ) ? response.data.message : text( 'failed', 'The widget could not be replaced.' ) );
					return;
				}
				try {
					insertReplacement( container, response.data );
				} catch ( err ) {
					notify( text( 'failed', 'The widget could not be replaced.' ) );
				}
			} ).fail( function() {
				notify( text( 'failed', 'The widget could not be replaced.' ) );
			} ).always( function() {
				busy = false;
			} );
		} );
	}

	function applyPreview() {
		if ( ! window.$e || ! window.elementor ) {
			return;
		}
		var reload = function() {
			try {
				if ( window.elementor.dynamicTags && window.elementor.dynamicTags.cleanCache ) {
					window.elementor.dynamicTags.cleanCache();
				}
				window.elementor.reloadPreview();
			} catch ( err ) {}
		};
		try {
			$e.run( 'document/save/auto', { force: true, onSuccess: reload } );
		} catch ( err ) {
			reload();
		}
	}

	function bind() {
		if ( bound || ! window.elementor || ! window.elementor.channels || ! window.elementor.channels.editor ) {
			return;
		}
		bound = true;
		window.elementor.channels.editor.on( 'wpeasycart:replaceLegacyWidget', replaceLegacy );
		window.elementor.channels.editor.on( 'wpeasycart:applyPreview', applyPreview );
	}

	/* Elementor fires elementor:init ( jQuery, all versions ) and elementor/init ( window, 3.x ). */
	$( window ).on( 'elementor:init', bind );
	window.addEventListener( 'elementor/init', bind );
	bind();
}( jQuery, window.wpeasycartElementorEditor ) );
