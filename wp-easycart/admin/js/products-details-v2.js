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

window.ecdv2 = ( function( $ ) {
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
		'short_description'            : 'short_description',
		'specifications'               : 'specifications',
		'images'                       : 'images',
		'options'                      : 'options',
		'pricing'                      : 'pricing',
		'tax'                          : 'tax',
		'quantities'                   : 'quantities',
		'shipping'                     : 'shipping',
		'packaging'                    : 'packaging',
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
		'googlemerchant'               : 'google_merchant_pro',
		'order_completed_note'         : 'order_completed_note',
		'order_completed_email_note'   : 'order_completed_email_note',
		'order_completed_details_note' : 'order_completed_details_note'
	};

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

	function ed( id ) { /* wp_editor content: tinymce when in visual mode */
		if ( typeof tinymce !== 'undefined' && tinymce.editors && tinymce.editors[ id ] && ! $( el( id ) ).is( ':visible' ) ) {
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
			return data;
		},

		tax: function() {
			var data = {
				is_taxable : c( 'is_taxable' )
			};
			maybe( data, 'vat_rate', c( 'vat_rate' ) );
			maybe( data, 'TIC', v( 'TIC' ) );
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
			return {
				seo_description : v( 'seo_description' ),
				seo_keywords    : v( 'seo_keywords' ),
				post_excerpt    : ed( 'post_excerpt' ),
				featured_image  : v( 'featured_image' )
			};
		},

		/* Google Merchant ( PRO ) — same field set the legacy panel button
		 * posted; server action is ec_admin_ajax_save_product_details_ +
		 * this endpoint key. Every gm_* element is rendered by the v2
		 * section, so plain v() reads are safe. */
		google_merchant_pro: function() {
			return {
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

	function mark_dirty( section ) {
		if ( ! section || saving ) {
			return;
		}
		dirty[ section ] = true;
		$( '#ecdv2_dirty_pill' ).addClass( 'is-visible' );
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

	function validate_basic() {
		if ( '' === $.trim( v( 'title' ) ) ) {
			toast( _t( 'title_required', 'Please enter a product title before saving.' ), 'error' );
			go_tab( 'general' );
			$( el( 'title' ) ).focus();
			return false;
		}
		return true;
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
				cards.removeClass( 'is-saving is-dirty' );
				d.resolve( response );
			},
			error   : function() {
				cards.removeClass( 'is-saving' );
				d.reject( endpoint );
			}
		} );
		return d.promise();
	}

	function save_all() {
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
			} ).fail( function() {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			} );
			return false;
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

		saving = true;
		$( '#ecdv2_save_btn' ).addClass( 'is-saving' );
		var failed = [];

		( function run( i ) {
			if ( i >= queue.length ) {
				saving = false;
				$( '#ecdv2_save_btn' ).removeClass( 'is-saving' );
				if ( failed.length ) {
					toast( _t( 'partial_save', 'Some sections failed to save:' ) + ' ' + failed.join( ', ' ), 'error' );
				} else {
					clear_dirty();
					toast( _t( 'saved', 'Product saved.' ), 'success' );
					update_header_meta();
				}
				return;
			}
			save_endpoint( queue[ i ], seen[ queue[ i ] ] )
				.always( function() {
					run( i + 1 );
				} )
				.fail( function( endpoint_key ) {
					failed.push( endpoint_key );
				} );
		} )( 0 );

		return false;
	}

	function update_header_meta() {
		$( '#ecdv2_header_title' ).text( v( 'title' ) );
		var sku = $( '#ecdv2_header_sku' );
		if ( sku.length ) {
			sku.text( v( 'model_number' ) );
		}
		var price = $( '#ecdv2_header_price' );
		if ( price.length ) {
			price.text( money( v( 'price' ) ) );
		}
	}

	/* Header status toggle: instant save of the basic section. */
	function quick_activate( node ) {
		if ( $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) ) {
			return; /* nothing to persist yet */
		}
		var pill = $( '#ecdv2_status_pill' );
		pill.toggleClass( 'is-active', node.checked )
			.text( node.checked ? _t( 'active', 'Active' ) : _t( 'draft', 'Draft' ) );
		var data = $.extend( base_data( 'basic' ), payloads.basic() );
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : data,
			success : function() {
				delete dirty.basic;
				$( '.ecdv2-card[data-ecdv2-section="basic"]' ).removeClass( 'is-dirty' );
				if ( ! has_dirty() ) {
					$( '#ecdv2_dirty_pill' ).removeClass( 'is-visible' );
				}
				toast( node.checked ? _t( 'activated', 'Product is now live in your store.' ) : _t( 'deactivated', 'Product hidden from your store.' ), 'success' );
			},
			error   : function() {
				node.checked = ! node.checked;
				pill.toggleClass( 'is-active', node.checked );
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
		'tags'               : 'organize',
		'subscription'       : 'behavior',
		'downloads'          : 'behavior',
		'deconetwork'        : 'behavior',
		'shipping'           : 'inventory',
		'packaging'          : 'inventory',
		'tax'                : 'pricing'
	};

	function go_tab( key ) {
		if ( $( '#ecdv2_wrap' ).hasClass( 'ecdv2-is-new' ) && 'general' !== key ) {
			return;
		}
		var tab = $( '.ecdv2-tab[data-ecdv2-tab="' + key + '"]' );
		if ( ! tab.length ) {
			return;
		}
		$( '.ecdv2-tab' ).removeClass( 'is-active' );
		tab.addClass( 'is-active' );
		$( '.ecdv2-panel' ).removeClass( 'is-active' );
		$( '.ecdv2-panel[data-ecdv2-panel="' + key + '"]' ).addClass( 'is-active' );
		if ( history.replaceState ) {
			history.replaceState( null, '', '#' + key );
		}
		if ( 'activity' === key && ! activity_loaded ) {
			load_activity();
		}
		/* mobile pill bar: keep active pill in view */
		if ( tab[ 0 ].scrollIntoView && window.innerWidth <= 782 ) {
			tab[ 0 ].scrollIntoView( { block: 'nearest', inline: 'center', behavior: 'smooth' } );
		}
		window.scrollTo( { top: 0 } );
	}

	function route_initial() {
		var hash = window.location.hash.replace( '#', '' );
		if ( ! hash ) {
			return;
		}
		if ( legacy_anchor_map[ hash ] ) {
			hash = legacy_anchor_map[ hash ];
		}
		if ( $( '.ecdv2-tab[data-ecdv2-tab="' + hash + '"]' ).length ) {
			go_tab( hash );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Settings search                                                      */
	/* ------------------------------------------------------------------ */

	function build_search_index() {
		search_index = [];
		$( '.ecdv2-panel' ).each( function() {
			var tab = $( this ).attr( 'data-ecdv2-panel' );
			var tab_label = $( '.ecdv2-tab[data-ecdv2-tab="' + tab + '"] span' ).eq( 1 ).text();
			$( this ).find( '.ecdv2-label, .ecdv2-toggle-label, .ecdv2-gate-label' ).each( function() {
				var text = $.trim( $( this ).text() );
				if ( text.length > 1 ) {
					search_index.push( {
						label     : text,
						lower     : text.toLowerCase(),
						tab       : tab,
						tab_label : tab_label,
						node      : this

					} );
				}
			} );
		} );
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
			for ( i = 0; i < search_index.length && matches.length < 8; i++ ) {
				if ( -1 !== search_index[ i ].lower.indexOf( query ) ) {
					matches.push( search_index[ i ] );
				}
			}
			if ( ! matches.length ) {
				results.append( $( '<div class="ecdv2-search-empty"/>' ).text( _t( 'no_results', 'No settings found' ) ) );
			} else {
				$.each( matches, function( idx, match ) {
					var row = $( '<button type="button" class="ecdv2-search-result"/>' );
					row.append( $( '<span class="ecdv2-search-result-label"/>' ).text( match.label ) );
					row.append( $( '<span class="ecdv2-search-result-tab"/>' ).text( match.tab_label ) );
					row.on( 'click', function() {
						go_tab( match.tab );
						results.removeClass( 'is-open' ).empty();
						input.val( '' );
						var field = $( match.node ).closest( '.ecdv2-field, .ecdv2-toggle-row, .ecdv2-gate' );
						if ( ! field.length ) {
							field = $( match.node );
						}
						field[ 0 ].scrollIntoView( { block: 'center', behavior: 'smooth' } );
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

	function media_pick( field_id, is_attachment ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		if ( ! media_frames[ field_id ] ) {
			media_frames[ field_id ] = wp.media( {
				title    : _t( 'select_media', 'Select or upload media' ),
				button   : { text: _t( 'use_file', 'Use this file' ) },
				multiple : false
			} );
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

	function category_add() {
		var select = $( '#ecdv2_cat_select' );
		var category_id = parseInt( select.val(), 10 );
		if ( ! category_id ) {
			return false;
		}
		var label = select.find( 'option:selected' ).text();
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_product_details_add_category',
				product_id        : v( 'product_id' ),
				category_id       : category_id,
				wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
			},
			success : function() {
				$( '#ecdv2_cat_empty' ).remove();
				var token = $( '<span class="ecdv2-cat-token"/>' ).attr( 'data-category-id', category_id ).text( label );
				$( '<button type="button" aria-label="Remove">&times;</button>' )
					.on( 'click', function() {
						category_remove( category_id );
					} )
					.appendTo( token );
				$( '#ecdv2_cat_tokens' ).append( token );
				select.find( 'option:selected' ).remove();
				select.val( '0' ).trigger( 'change' );
				toast( _t( 'category_added', 'Category added.' ), 'success' );
			},
			error   : function() {
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			}
		} );
		return false;
	}

	function category_remove( category_id ) {
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : {
				action            : 'ec_admin_ajax_product_details_delete_category',
				product_id        : v( 'product_id' ),
				category_id       : category_id,
				wp_easycart_nonce : v( 'wp_easycart_product_details_nonce' )
			},
			success : function() {
				var token = $( '.ecdv2-cat-token[data-category-id="' + category_id + '"]' );
				var label = $.trim( token.clone().children().remove().end().text() );
				token.remove();
				$( '#ecdv2_cat_select' ).append( $( '<option/>' ).val( category_id ).text( label ) );
				if ( ! $( '#ecdv2_cat_tokens .ecdv2-cat-token' ).length ) {
					$( '#ecdv2_cat_tokens' ).append( '<span class="ecdv2-cat-empty" id="ecdv2_cat_empty">' + _t( 'no_categories', 'No categories assigned yet.' ) + '</span>' );
				}
				toast( _t( 'category_removed', 'Category removed.' ), 'success' );
			},
			error   : function() {
				toast( _t( 'save_failed', 'Save failed. Please try again.' ), 'error' );
			}
		} );
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
		return ( negative ? '-' : '' ) + ( left ? symbol + number : number + symbol );
	}

	function load_activity() {
		activity_loaded = true;
		var holder = $( '#ecdv2_activity_content' );
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
					holder.html( '<div class="ecdv2-activity-empty">' + esc_html( _t( 'activity_failed', 'Could not load activity.' ) ) + '</div>' );
					return;
				}
				render_activity( holder, response.data );
			},
			error   : function() {
				activity_loaded = false;
				holder.html( '<div class="ecdv2-activity-empty">' + esc_html( _t( 'activity_failed', 'Could not load activity.' ) ) + '</div>' );
			}
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
		html += metric( data.avg_rating ? Number( data.avg_rating ) + '<span class="ecdv2-metric-star">★</span>' : '&mdash;', _t( 'avg_rating', 'Avg rating' ), true );
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

		/* Reviews with inline moderation */
		html += '<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' + esc_html( _t( 'reviews', 'Reviews' ) ) + '</h3>';
		if ( parseInt( data.pending_reviews, 10 ) > 0 ) {
			html += '<span class="ecdv2-activity-pending">' + esc_html( data.pending_reviews ) + ' ' + esc_html( _t( 'pending', 'pending approval' ) ) + '</span>';
		}
		html += '</div><div class="ecdv2-card-body ecdv2-activity-body">';
		if ( data.reviews && data.reviews.length ) {
			$.each( data.reviews, function( idx, review ) {
				var approved = parseInt( review.approved, 10 ) ? 1 : 0;
				html += '<div class="ecdv2-activity-row" data-review-id="' + parseInt( review.review_id, 10 ) + '">';
				html += '<span class="ecdv2-activity-row-main"><span class="ecdv2-activity-stars">' + esc_html( review.rating ) + ' ★</span><span class="ecdv2-activity-name">' + esc_html( review.title || '' ) + '</span><span class="ecdv2-activity-reviewer">&mdash; ' + esc_html( review.reviewer_name || '' ) + '</span></span>';
				html += '<span class="ecdv2-activity-row-meta">';
				html += '<button type="button" class="ecdv2-review-toggle' + ( approved ? ' is-approved' : '' ) + '" onclick="ecdv2.review_toggle( this, ' + parseInt( review.review_id, 10 ) + ', ' + ( approved ? 0 : 1 ) + ' );">' + ( approved ? esc_html( _t( 'unapprove', 'Unapprove' ) ) : esc_html( _t( 'approve', 'Approve' ) ) ) + '</button>';
				html += '</span></div>';
			} );
		} else {
			html += '<div class="ecdv2-activity-empty">' + esc_html( _t( 'no_reviews', 'No reviews yet.' ) ) + '</div>';
		}
		html += '</div></div>';

		holder.html( html );
	}

	function metric( value, label, raw_html ) {
		return '<div class="ecdv2-metric"><span class="ecdv2-metric-value">' + ( raw_html ? String( value ) : esc_html( value ) ) + '</span><span class="ecdv2-metric-label">' + esc_html( label ) + '</span></div>';
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
		return '<div class="ec_admin_role_price_row" id="ec_admin_product_details_role_price_row_' + id + '" data-role="' + role_attr( role ) + '">' +
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
		var data = {
			action                  : 'ec_admin_ajax_product_details_add_role_price',
			product_id              : v( 'product_id' ),
			add_new_role_price_role : role,
			ec_admin_new_role_price : price,
			wp_easycart_nonce       : v( 'wp_easycart_product_details_nonce' )
		};
		$.ajax( {
			url     : wpeasycart_admin_ajax_object.ajax_url,
			type    : 'post',
			data    : data,
			success : function( resp ) {
				$( '#ec_admin_no_role_prices' ).remove();
				var id = '';
				var m = String( resp ).match( /role_price_row_(\d+)/ );
				if ( m ) {
					id = m[1];
				}
				$( '#role_prices_holder' ).append( role_row_html( id, role, price ) );
				$( '#ec_admin_new_role_price' ).val( '' );
				toast( _t( 'role_added', 'Role price added.' ), 'success' );
			},
			error   : function() {
				toast( _t( 'role_add_failed', 'Could not add the role price.' ), 'error' );
			}
		} );
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
				var row = $( '<div class="ecdv2-featured-row"></div>' );
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
		bind_sale_preview();
		bind_variant_stock_note();
		bind_price_context();
		bind_featured_products();

		/* Override the legacy handler from products.js ( ready runs after
		 * every script has parsed, so this assignment always wins ). */
		window.ec_admin_product_details_add_new_manufacturer = create_manufacturer;

		$( document ).on( 'change input', '#tag_text, #tag_type, #tag_bg_color, #tag_text_color', update_badge_preview );
		update_badge_preview();

		if ( $.fn.select2 ) {
			$( '.ecdv2-select2' ).each( function() {
				if ( ! $( this ).hasClass( 'select2-hidden-accessible' ) ) {
					$( this ).select2( { width: '100%' } );
				}
			} );
		}

		route_initial();

		if ( -1 !== window.location.search.indexOf( 'ecdv2_created=1' ) ) {
			toast( _t( 'created', 'Product created. All sections are now unlocked.' ), 'success' );
		}
	} );

	/* Public API ( used by inline onclick handlers in PHP templates ). */
	return {
		go_tab           : go_tab,
		save_all         : save_all,
		quick_activate   : quick_activate,
		mark_dirty       : mark_dirty,
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
		add_role_price   : add_role_price,
		toast            : toast
	};

} )( window.jQuery );