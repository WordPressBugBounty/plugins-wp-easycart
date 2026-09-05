/**
 * WP EasyCart Admin — Customer Details V2 ( FREE ).
 *
 * ecudv2 namespace ( user-details flavor of the product ecdv2 chassis ).
 * Dirty tracking mirrors products; saving is holistic: the whole form posts
 * to ecudv2_user_save which delegates to the legacy insert/update handlers,
 * so WP sync and all side effects are preserved. Dirty state drives UX only.
 *
 * Localized: ecudv2_lang { strings }. Utility nonce read from
 * #ecudv2_utility_nonce ( email check / approve ).
 *
 * @since 5.9.3
 */

( function( $ ) {
	'use strict';

	/*
	 * No parse-time DOM guard here: this file may be enqueued in the head,
	 * where #ecdv2_wrap doesn't exist yet. The enqueue is already gated to
	 * the details view server-side, all document-level bindings below are
	 * delegated ( safe pre-DOM ), and the boot block runs on DOM-ready.
	 */

	var dirty = {};
	var saving = false;
	var email_timer = null;

	function _t( key, fallback ) {
		return ( typeof ecudv2_lang !== 'undefined' && ecudv2_lang[ key ] ) ? ecudv2_lang[ key ] : fallback;
	}

	function toast( message, type ) {
		type = type || 'success';
		var icon = type === 'success' ? 'yes' : ( type === 'error' ? 'no' : 'info-outline' );
		var $toast = $( '<div class="ecv2-toast ecv2-toast-' + type + '"><span class="dashicons dashicons-' + icon + '"></span> <span></span></div>' );
		$toast.find( 'span' ).last().text( message );
		$( '#ecv2-toast-container' ).append( $toast );
		setTimeout( function() { $toast.fadeOut( 300, function() { $( this ).remove(); } ); }, 3500 );
	}

	function is_new() {
		return $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' );
	}

	/* ------------------------------------------------------------------ */
	/* Dirty tracking                                                      */
	/* ------------------------------------------------------------------ */

	function mark_dirty( section ) {
		if ( ! section ) { return; }
		dirty[ section ] = true;
		$( '#ecdv2_dirty_pill' ).addClass( 'is-visible' );
		var card = $( '[data-ecdv2-sec="' + section + '"]' ).first().closest( '.ecdv2-card' );
		card.addClass( 'is-dirty' );
		var panel = card.closest( '.ecdv2-panel' );
		if ( panel.length ) {
			$( '.ecdv2-tab[data-ecdv2-tab="' + panel.attr( 'data-ecdv2-panel' ) + '"]' ).addClass( 'has-dirty' );
		}
	}

	function clear_dirty() {
		dirty = {};
		$( '#ecdv2_dirty_pill' ).removeClass( 'is-visible' );
		$( '.ecdv2-card.is-dirty' ).removeClass( 'is-dirty' );
		$( '.ecdv2-tab.has-dirty' ).removeClass( 'has-dirty' );
	}

	function has_dirty() {
		var key;
		for ( key in dirty ) {
			if ( dirty.hasOwnProperty( key ) && dirty[ key ] ) { return true; }
		}
		return false;
	}

	$( document ).on( 'input change', '[data-ecdv2-sec]', function() {
		mark_dirty( $( this ).attr( 'data-ecdv2-sec' ) );
	} );

	window.addEventListener( 'beforeunload', function( e ) {
		if ( has_dirty() && ! saving ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	$( document ).on( 'keydown', function( e ) {
		if ( ( e.metaKey || e.ctrlKey ) && 's' === String( e.key ).toLowerCase() ) {
			e.preventDefault();
			ecudv2.save_all();
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Validation                                                          */
	/* ------------------------------------------------------------------ */

	function field_error( name, message ) {
		$( '[data-ecudv2-error="' + name + '"]' ).text( message || '' );
		if ( message ) {
			var $input = $( '#ecudv2_' + name );
			$input.addClass( 'ecudv2-input-error' );
			var panel = $input.closest( '.ecdv2-panel' ).attr( 'data-ecdv2-panel' );
			if ( panel ) { ecudv2.go_tab( panel ); }
			$input.focus();
		}
	}

	function clear_errors() {
		$( '.ecudv2-field-error' ).text( '' );
		$( '.ecudv2-input-error' ).removeClass( 'ecudv2-input-error' );
	}

	function validate() {
		clear_errors();
		if ( '' === $.trim( $( '#ecudv2_first_name' ).val() ) ) {
			field_error( 'first_name', _t( 'first_required', 'Please enter a first name.' ) );
			return false;
		}
		if ( '' === $.trim( $( '#ecudv2_last_name' ).val() ) ) {
			field_error( 'last_name', _t( 'last_required', 'Please enter a last name.' ) );
			return false;
		}
		var email = $.trim( $( '#ecudv2_email' ).val() );
		if ( '' === email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			field_error( 'email', _t( 'email_required', 'Please enter a valid email address.' ) );
			return false;
		}
		if ( '' === $( '#ecudv2_user_level' ).val() ) {
			field_error( 'user_level', _t( 'role_required', 'Please select a user access level.' ) );
			return false;
		}
		var pass_needed = is_new() || $( '#ecudv2_update_password' ).is( ':checked' );
		if ( pass_needed ) {
			var pass = $( '#ecudv2_password' ).val();
			if ( pass.length < 8 ) {
				field_error( 'password', _t( 'password_length', 'Please enter a password 8 characters or greater.' ) );
				return false;
			}
			if ( pass !== $( '#ecudv2_retype_password' ).val() ) {
				field_error( 'retype_password', _t( 'password_match', 'Passwords do not match.' ) );
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Save ( holistic delegation )                                        */
	/* ------------------------------------------------------------------ */

	function save_all() {
		if ( saving ) { return false; }
		if ( ! is_new() && ! has_dirty() ) {
			toast( _t( 'nothing_to_save', 'No changes to save.' ), 'info' );
			return false;
		}
		if ( ! validate() ) { return false; }

		saving = true;
		$( '#ecdv2_save_btn' ).addClass( 'is-saving' );
		$( '.ecdv2-card.is-dirty' ).addClass( 'is-saving' );

		/* When "set a new password" is off, blank the fields so the legacy
		 * handler keeps the existing password ( empty password = keep ).   */
		if ( ! is_new() && ! $( '#ecudv2_update_password' ).is( ':checked' ) ) {
			$( '#ecudv2_password, #ecudv2_retype_password' ).val( '' );
		}

		var payload = $( '#wpeasycart_admin_form' ).serialize() + '&action=ecudv2_user_save';

		$.ajax( {
			url: wpeasycart_admin_ajax_object.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: payload,
			success: function( response ) {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				$( '.ecdv2-card' ).removeClass( 'is-saving' );

				if ( ! response.success ) {
					var d = response.data || {};
					if ( d.field ) { field_error( d.field, d.message ); }
					toast( d.message || _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
					return;
				}
				if ( response.data && response.data.created ) {
					clear_dirty();
					saving = true; /* suppress beforeunload */
					window.location.href = 'admin.php?page=wp-easycart-users&subpage=accounts&user_id=' + response.data.user_id + '&ec_admin_form_action=edit&ecudv2_created=1';
					return;
				}
				clear_dirty();
				/* Password fields never stay populated after a save. */
				$( '#ecudv2_password, #ecudv2_retype_password' ).val( '' );
				$( '#ecudv2_update_password' ).prop( 'checked', false ).trigger( 'ecudv2:silent' );
				$( '#ecudv2_password_fields' ).removeClass( 'is-open' );
				dirty = {};
				$( '#ecdv2_dirty_pill' ).removeClass( 'is-visible' );
				toast( ( response.data && response.data.message ) || _t( 'saved', 'Customer saved.' ), 'success' );
				sync_header();
			},
			error: function() {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				$( '.ecdv2-card' ).removeClass( 'is-saving' );
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			}
		} );
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Tabs / header / menu                                                */
	/* ------------------------------------------------------------------ */

	function go_tab( key ) {
		if ( is_new() && 'general' !== key ) {
			toast( _t( 'save_first', 'Save the essentials first to unlock this section.' ), 'info' );
			return;
		}
		$( '.ecdv2-tab' ).removeClass( 'is-active' );
		$( '.ecdv2-tab[data-ecdv2-tab="' + key + '"]' ).addClass( 'is-active' );
		$( '.ecdv2-panel' ).removeClass( 'is-active' );
		$( '.ecdv2-panel[data-ecdv2-panel="' + key + '"]' ).addClass( 'is-active' );
		try { window.history.replaceState( null, '', location.href.replace( /(#.*)?$/, '#' + key ) ); } catch ( e ) {}
		$( '.ecdv2-panel-col' )[0].scrollTop = 0;
		window.scrollTo( { top: 0 } );
	}

	function sync_header() {
		var name = $.trim( $( '#ecudv2_first_name' ).val() + ' ' + $( '#ecudv2_last_name' ).val() );
		if ( '' !== name ) { $( '#ecudv2_header_title' ).text( name ); }
		var email = $.trim( $( '#ecudv2_email' ).val() );
		if ( '' !== email ) { $( '#ecudv2_header_email' ).text( email ); }
		var initials = ( $.trim( $( '#ecudv2_first_name' ).val() ).charAt( 0 ) + $.trim( $( '#ecudv2_last_name' ).val() ).charAt( 0 ) ).toUpperCase();
		if ( '' !== $.trim( initials ) ) { $( '#ecudv2_header_avatar' ).text( initials ); }
	}

	function menu_toggle() {
		$( '#ecudv2_header_menu' ).toggleClass( 'is-open' );
	}
	$( document ).on( 'click', function( e ) {
		if ( ! $( e.target ).closest( '.ecdv2-header-menu-wrap' ).length ) {
			$( '#ecudv2_header_menu' ).removeClass( 'is-open' );
		}
	} );

	/* ------------------------------------------------------------------ */
	/* Email uniqueness ( debounced )                                      */
	/* ------------------------------------------------------------------ */

	function email_changed( input ) {
		sync_header();
		clearTimeout( email_timer );
		var email = $.trim( $( input ).val() );
		field_error( 'email', '' );
		if ( '' === email ) { return; }
		email_timer = setTimeout( function() {
			$.post( wpeasycart_admin_ajax_object.ajax_url, {
				action: 'ecudv2_email_exists',
				email: email,
				user_id: $( '#ecudv2_user_id' ).val(),
				wp_easycart_nonce: $( '#ecudv2_utility_nonce' ).val()
			}, function( response ) {
				if ( response && response.success && response.data.exists ) {
					$( '[data-ecudv2-error="email"]' ).text( _t( 'email_in_use', 'Another account already uses this email address.' ) );
					$( '#ecudv2_email' ).addClass( 'ecudv2-input-error' );
				}
			}, 'json' );
		}, 450 );
	}

	/* ------------------------------------------------------------------ */
	/* Password tools                                                      */
	/* ------------------------------------------------------------------ */

	$( document ).on( 'change', '#ecudv2_update_password', function( e ) {
		$( '#ecudv2_password_fields' ).toggleClass( 'is-open', this.checked );
		if ( ! this.checked ) {
			$( '#ecudv2_password, #ecudv2_retype_password' ).val( '' );
			$( '#ecudv2_strength span' ).attr( 'class', '' ).css( 'width', 0 );
		}
	} );

	function password_generate() {
		var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
		var out = '';
		var buf = new Uint32Array( 16 );
		( window.crypto || window.msCrypto ).getRandomValues( buf );
		for ( var i = 0; i < 16; i++ ) {
			out += chars.charAt( buf[ i ] % chars.length );
		}
		if ( ! is_new() && ! $( '#ecudv2_update_password' ).is( ':checked' ) ) {
			$( '#ecudv2_update_password' ).prop( 'checked', true ).trigger( 'change' );
		}
		$( '#ecudv2_password' ).val( out ).attr( 'type', 'text' ).trigger( 'input' );
		$( '#ecudv2_retype_password' ).val( out ).trigger( 'input' );
		$( '.ecudv2-password-eye .dashicons' ).removeClass( 'dashicons-visibility' ).addClass( 'dashicons-hidden' );
		mark_dirty( is_new() ? 'basic' : 'security' );
	}

	function password_reveal( btn ) {
		var $input = $( '#ecudv2_password' );
		var show = 'password' === $input.attr( 'type' );
		$input.attr( 'type', show ? 'text' : 'password' );
		$( btn ).find( '.dashicons' ).toggleClass( 'dashicons-visibility', ! show ).toggleClass( 'dashicons-hidden', show );
	}

	function password_strength( input ) {
		var val = $( input ).val();
		var score = 0;
		if ( val.length >= 8 ) { score++; }
		if ( val.length >= 12 ) { score++; }
		if ( /[A-Z]/.test( val ) && /[a-z]/.test( val ) ) { score++; }
		if ( /[0-9]/.test( val ) ) { score++; }
		if ( /[^A-Za-z0-9]/.test( val ) ) { score++; }
		var $bar = $( '#ecudv2_strength span' );
		var levels = [ '', 'is-weak', 'is-weak', 'is-fair', 'is-good', 'is-strong' ];
		$bar.attr( 'class', levels[ score ] ).css( 'width', ( score * 20 ) + '%' );
	}

	/* ------------------------------------------------------------------ */
	/* Actions: copy address, approve, reset, delete                       */
	/* ------------------------------------------------------------------ */

	function copy_to_shipping() {
		var fields = [ 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ];
		$.each( fields, function( i, field ) {
			$( '#ecudv2_shipping_' + field ).val( $( '#ecudv2_billing_' + field ).val() );
		} );
		mark_dirty( 'shipping' );
		toast( _t( 'copied', 'Billing address copied to shipping.' ), 'info' );
	}

	function approve( btn ) {
		$( btn ).prop( 'disabled', true );
		$.post( wpeasycart_admin_ajax_object.ajax_url, {
			action: 'ecudv2_user_approve',
			user_id: $( '#ecudv2_user_id' ).val(),
			wp_easycart_nonce: $( '#ecudv2_utility_nonce' ).val()
		}, function( response ) {
			if ( response && response.success ) {
				$( '#ecudv2_state' ).removeClass( 'ecudv2-state-pending' ).html( '<span class="ecdv2-status-pill is-active">' + _t( 'active', 'Active' ) + '</span>' );
				$( '#ecudv2_user_level' ).val( 'shopper' );
				$( '#ecudv2_header_role' ).text( 'shopper' );
				$( '#ecudv2_activation_readout' ).text( _t( 'activated', 'Activated' ) );
				toast( response.data.message, 'success' );
			} else {
				$( btn ).prop( 'disabled', false );
				toast( ( response.data && response.data.message ) || _t( 'error', 'An error occurred.' ), 'error' );
			}
		}, 'json' );
	}

	function confirm_native( title, message ) {
		return window.confirm( title + '\n\n' + message );
	}

	function password_reset( url ) {
		$( '#ecudv2_header_menu' ).removeClass( 'is-open' );
		if ( confirm_native( _t( 'reset_title', 'Send password reset?' ), _t( 'reset_body', 'This invalidates the customer\u2019s current password immediately and emails them a reset link. Continue?' ) ) ) {
			window.location.href = url;
		}
	}

	function delete_account( url, el ) {
		$( '#ecudv2_header_menu' ).removeClass( 'is-open' );
		var extra = $( el ).data( 'extra-warning' ) || '';
		var body = _t( 'delete_body', 'Permanently delete this customer account? The profile and addresses are removed. Orders are kept. This cannot be undone.' );
		if ( extra ) { body += '\n\n' + extra; }
		if ( confirm_native( _t( 'delete_title', 'Delete customer?' ), body ) ) {
			saving = true; /* suppress beforeunload */
			window.location.href = url;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	$( function() {
		if ( /[?&]ecudv2_created=1/.test( location.search ) ) {
			toast( _t( 'created', 'Customer created. All sections are now unlocked.' ), 'success' );
		}
		var hash = location.hash.replace( '#', '' );
		if ( hash && $( '.ecdv2-panel[data-ecdv2-panel="' + hash + '"]' ).length && ! is_new() ) {
			go_tab( hash );
		}
	} );

	window.ecudv2 = {
		save_all: save_all,
		go_tab: go_tab,
		menu_toggle: menu_toggle,
		sync_header: sync_header,
		email_changed: email_changed,
		password_generate: password_generate,
		password_reveal: password_reveal,
		password_strength: password_strength,
		copy_to_shipping: copy_to_shipping,
		approve: approve,
		password_reset: password_reset,
		delete_account: delete_account,
		mark_dirty: mark_dirty,
		toast: toast
	};

} )( jQuery );