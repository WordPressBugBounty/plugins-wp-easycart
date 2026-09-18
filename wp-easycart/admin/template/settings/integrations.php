<?php
/**
 * Settings › Integrations ( V2 declaration ).
 *
 * Merges the classic "Third Party" page ( legacy slug `third-party`: Google
 * Analytics, GA4, Google Ads, Google Merchant, Meta Pixel, MailerLite,
 * ConvertKit, ActiveCampaign, ShareASale, Amazon S3, DecoNetwork ) and the
 * "Cart Importer" page ( legacy slug `cart-importer`: Square, WooCommerce,
 * osCommerce and the retired Shopify importer ) into one page, one section
 * per service. The DecoNetwork "allow blank item purchase" switch moved here
 * from the Additional Settings page ( `miscellaneous` ).
 *
 * Only the Universal Analytics ID was editable in the free plugin; every
 * other service is declared with 'pro' => true and unlocks through the gate.
 * PRO attaches the pieces a declaration cannot express ( the Google Merchant
 * feed tool, the DecoNetwork setup notes, the ConvertKit form and
 * ActiveCampaign list pickers that are filled from each service's API ) from
 * wp-easycart-pro/admin/template/settings/integrations.php.
 *
 * The cart importer is a tool, not a set of options: its section has no
 * fields. Its 'render' prints the importer rebuilt in the V2 look ( source
 * cards, rows, notices ) on top of the unchanged AJAX handlers, nonces, form
 * targets and admin/js/cart-importer.js; its 'enqueue' loads that script plus
 * a thin adapter and scoped styles. Render callables never enqueue.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_settings_integrations_sanitize_ga_id' ) ) {
	/**
	 * The option set ships 'UA-XXXXXXX-X' as the Universal Analytics default and
	 * every reader treats that placeholder as "not set", so store it as empty.
	 */
	function wp_easycart_settings_integrations_sanitize_ga_id( $value, $field ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		return ( 'UA-XXXXXXX-X' === strtoupper( $value ) ) ? '' : $value;
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_sanitize_host' ) ) {
	/**
	 * DecoNetwork store address. The storefront builds links as
	 * 'https://' . option . path, so strip any scheme and trailing slash the
	 * merchant pastes in.
	 */
	function wp_easycart_settings_integrations_sanitize_host( $value, $field ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		$value = preg_replace( '#^[a-z]+://#i', '', $value );
		return rtrim( $value, '/' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_sanitize_hex' ) ) {
	/** Google Ads conversion color: six hex digits without a leading '#'. */
	function wp_easycart_settings_integrations_sanitize_hex( $value, $field ) {
		$value = preg_replace( '/[^0-9a-f]/i', '', (string) $value );
		return substr( $value, 0, 6 );
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_remote_options' ) ) {
	/**
	 * Starting option list for the ConvertKit form / ActiveCampaign list
	 * pickers: the "choose one" entry plus the stored value, so the row renders
	 * and validates even before PRO fills it from the service's API.
	 */
	function wp_easycart_settings_integrations_remote_options( $key, $placeholder, $stored_label ) {
		$options = array( '0' => $placeholder );
		$stored  = (string) get_option( $key, '' );
		if ( '' !== $stored && '0' !== $stored ) {
			$options[ $stored ] = sprintf( $stored_label, $stored );
		}
		return $options;
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_google_merchant' ) ) {
	/**
	 * Section body for Google Merchant. Without PRO the section is locked and
	 * this short summary is all that shows; the PRO page filter swaps in the
	 * CSV download / upload and XML feed tool.
	 */
	function wp_easycart_settings_integrations_render_google_merchant( $page, $section ) {
		echo '<p class="ecst-row-desc" style="margin:0;">' . esc_html__( 'Download a CSV of your catalogue, fill in the Google Shopping attributes ( GTIN, MPN, condition, product category ), upload it back and download the XML feed to submit to your Google Merchant Center account.', 'wp-easycart' ) . '</p>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_enqueue_cart_importer' ) ) {
	/**
	 * Section 'enqueue' ( runs on admin_enqueue_scripts through the engine ): the
	 * classic Square batch importer script with the language strings the classic
	 * page router localized, plus a thin adapter and scoped styles for the V2
	 * markup printed by the render callable. cart-importer.js is untouched: the
	 * rebuilt markup keeps every element id and the two inner class hooks
	 * ( .ec_admin_progress_bar > div, .ec_admin_process_status > span ) it uses.
	 */
	function wp_easycart_settings_integrations_enqueue_cart_importer( $page, $section ) {
		if ( ! wp_script_is( 'wp_easycart_admin_cart_importer_js', 'registered' ) ) {
			wp_register_script( 'wp_easycart_admin_cart_importer_js', plugins_url( 'wp-easycart/admin/js/cart-importer.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script( 'wp_easycart_admin_cart_importer_js', 'wp_easycart_cart_importer_language', array(
				'inventory-items-synced'      => __( 'Items Have Synced Inventory', 'wp-easycart' ),
				'all-inventory-items-synced'  => __( 'All Item Inventory Synced.', 'wp-easycart' ),
				'modifier-items-imported'     => __( 'Modifier Items Imported', 'wp-easycart' ),
				'all-modifiers-imported'      => __( 'All Modifiers Imported, Starting Modifier Items.', 'wp-easycart' ),
				'modifiers-imported'          => __( 'Modifiers Imported', 'wp-easycart' ),
				'all-modifier-items-imported' => __( 'All Modifier Items Imported, Starting Categories', 'wp-easycart' ),
				'items-imported'              => __( 'Items Imported', 'wp-easycart' ),
				'all-products-imported'       => __( 'All Products Imported!', 'wp-easycart' ),
				'categories-imported'         => __( 'Categories Imported', 'wp-easycart' ),
				'all-categories-imported'     => __( 'All Categories Imported!', 'wp-easycart' ),
				'customers-imported'          => __( 'Customers Imported', 'wp-easycart' ),
				'all-customers-imported'      => __( 'All Customers Imported!', 'wp-easycart' ),
			) );
		}
		wp_enqueue_script( 'wp_easycart_admin_cart_importer_js' );
		/* Adapter: source cards switch panels; the "only new" switch gets the V2 toggle look ( cart-importer.js already listens to its change event ). */
		wp_add_inline_script( 'wp_easycart_admin_cart_importer_js', 'jQuery( function( $ ) {
	var $wrap = $( "#ecimp" );
	if ( ! $wrap.length ) { return; }
	function ecimp_show( src ) {
		var $btn = $wrap.find( ".ecimp-src[data-src=\"" + src + "\"]" );
		if ( ! $btn.length ) { $btn = $wrap.find( ".ecimp-src" ).first(); src = $btn.data( "src" ); }
		$wrap.find( ".ecimp-src" ).removeClass( "is-on" ).attr( "aria-selected", "false" );
		$btn.addClass( "is-on" ).attr( "aria-selected", "true" );
		$wrap.find( ".ecimp-panel" ).prop( "hidden", true );
		$wrap.find( "#ecimp-panel-" + src ).prop( "hidden", false );
		try { window.localStorage.setItem( "ecimp_src", src ); } catch ( e ) {}
	}
	$wrap.on( "click", ".ecimp-src", function() { ecimp_show( $( this ).data( "src" ) ); } );
	$wrap.on( "change", ".ecimp-toggle input", function() { $( this ).closest( ".ecst-toggle" ).toggleClass( "is-on", this.checked ); } );
	var start = $wrap.data( "src" ) || "";
	if ( "" === start ) { try { start = window.localStorage.getItem( "ecimp_src" ) || ""; } catch ( e ) {} }
	ecimp_show( start || "square" );
} );' );
		wp_add_inline_style( 'wp_easycart_admin_settings_page_v2_css', '#ecimp .ecimp-srcs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
#ecimp .ecimp-src{display:flex;flex-direction:column;gap:2px;min-width:150px;padding:10px 14px;border:1px solid var(--ecv2-g300);border-radius:var(--ecv2-r);background:#fff;cursor:pointer;text-align:left;font:inherit;color:inherit}
#ecimp .ecimp-src b{font-size:13px;color:var(--ecv2-g900)}
#ecimp .ecimp-src span{font-size:11.5px;color:var(--ecv2-g500)}
#ecimp .ecimp-src.is-on{border-color:var(--ecst-accent);background:var(--ecst-accent-soft);box-shadow:0 0 0 1px var(--ecst-accent)}
#ecimp .ecimp-panel{border:1px solid var(--ecv2-g200);border-radius:var(--ecv2-r);background:#fff;overflow:hidden}
#ecimp .ecimp-panel[hidden]{display:none}
#ecimp .ecimp-row{display:grid;grid-template-columns:minmax(0,44%) minmax(0,1fr);gap:16px 24px;align-items:center;padding:11px 16px;border-bottom:1px solid var(--ecv2-g100);position:relative}
#ecimp .ecimp-row:last-child{border-bottom:0}
#ecimp .ecimp-row.is-child{padding-left:40px;background:var(--ecv2-g50)}
#ecimp .ecimp-row.is-child:before{content:"";position:absolute;left:24px;top:0;bottom:0;border-left:2px solid var(--ecst-accent-soft)}
#ecimp .ecimp-row .ecst-row-control{justify-content:flex-start}
#ecimp .ecimp-intro{padding:12px 16px;border-bottom:1px solid var(--ecv2-g100)}
#ecimp .ecimp-foot{display:flex;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--ecv2-g100);background:var(--ecv2-g50);flex-wrap:wrap}
#ecimp .ecimp-notice{display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:var(--ecv2-rs);font-size:12.5px;line-height:1.45;margin:0 0 12px;border:1px solid}
#ecimp .ecimp-notice:last-child{margin-bottom:0}
#ecimp .ecimp-notice.is-ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
#ecimp .ecimp-notice.is-warn{background:#fffbeb;border-color:#fde68a;color:#92400e}
#ecimp .ecimp-notice.is-danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}
#ecimp .ecimp-notice.is-info{background:var(--ecv2-g50);border-color:var(--ecv2-g200);color:var(--ecv2-g700)}
#ecimp .ecimp-notice a{color:inherit;font-weight:600}
#ecimp .ecimp-notice .dashicons{font-size:16px;width:16px;height:16px;flex-shrink:0;margin-top:1px}
#ecimp .ecimp-list{margin:6px 0 0 18px;padding:0;font-size:12px;color:var(--ecv2-g500);line-height:1.5}
#ecimp .ecimp-check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--ecv2-g900);cursor:pointer}
#ecimp .ecimp-progress{width:100%}
#ecimp .ec_admin_progress_bar{float:none;min-height:0;height:8px;border-radius:4px;background:var(--ecv2-g200)!important;overflow:hidden}
#ecimp .ec_admin_progress_bar>div{min-height:0;height:8px;padding:0;border-radius:4px;background:var(--ecst-accent);background-image:none;box-shadow:none;transition:width .4s ease}
#ecimp .ec_admin_progress_bar>div:after{display:none}
#ecimp .ec_admin_process_status{float:none;text-align:left;font-size:12px;color:var(--ecv2-g500);margin-top:6px}' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_cart_importer' ) ) {
	/**
	 * Section body: the cart importer rebuilt in the V2 look. Same AJAX handlers,
	 * nonces, form targets and admin/js/cart-importer.js as the classic page; only
	 * the markup changed. Source cards: Square ( batch AJAX importer ), WooCommerce
	 * ( batch AJAX importer since 6.0.0: ec_admin_ajax_woo_import ), osCommerce ( POST form -> the classic
	 * template's inline import, run here with its output discarded ), Shopify
	 * ( discontinued notice ). Never enqueues; see the section 'enqueue'.
	 */
	function wp_easycart_settings_integrations_render_cart_importer( $page, $section ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only result flags set by the importer redirects / form targets.
		$ec_success = isset( $_GET['ec_success'] ) ? sanitize_key( wp_unslash( $_GET['ec_success'] ) ) : '';
		$ec_action  = isset( $_GET['ec_action'] ) ? sanitize_key( wp_unslash( $_GET['ec_action'] ) ) : '';
		// phpcs:enable
		$open = '';
		if ( 'woo-imported' === $ec_success ) {
			$open = 'woo';
		} elseif ( 'oscommerce-imported' === $ec_success || 'import-oscommerce-products' === $ec_action ) {
			$open = 'oscommerce';
		}
		$oscommerce_ran = false;
		if ( 'import-oscommerce-products' === $ec_action && current_user_can( 'manage_options' ) ) {
			/* The classic osCommerce template runs its import inline when this flag is present. Run it for its
			   side effects only; its markup ( and the header() redirect that could never succeed mid-page ) is discarded. */
			ob_start();
			include EC_PLUGIN_DIRECTORY . '/admin/template/settings/cart-importer/oscommerce-import.php';
			ob_end_clean();
			$oscommerce_ran = true;
		}
		$square_ready = ( 'square' === get_option( 'ec_option_payment_process_method' ) ) && ( get_option( 'ec_option_square_is_sandbox' ) ? '' !== (string) get_option( 'ec_option_square_sandbox_access_token' ) : '' !== (string) get_option( 'ec_option_square_access_token' ) );
		$square_scope = true;
		if ( $square_ready && class_exists( 'ec_square' ) ) {
			$square       = new ec_square();
			$square_scope = $square->has_inventory_scope();
		}
		$sources = array(
			'square'     => array( __( 'Square', 'wp-easycart' ), __( 'Catalogue, modifiers and stock', 'wp-easycart' ) ),
			'woo'        => array( __( 'WooCommerce', 'wp-easycart' ), __( 'Products, categories, attributes', 'wp-easycart' ) ),
			'oscommerce' => array( __( 'osCommerce', 'wp-easycart' ), __( 'Same database only', 'wp-easycart' ) ),
			'shopify'    => array( __( 'Shopify', 'wp-easycart' ), __( 'No longer available', 'wp-easycart' ) ),
		);
		?>
		<div id="ecimp" data-src="<?php echo esc_attr( $open ); ?>">
			<input type="hidden" id="wpec_cart_importer_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-cart-importer' ) ); ?>" />
			<p class="ecst-row-desc" style="margin:0 0 10px;"><?php esc_html_e( 'Each importer adds to your catalogue; nothing already in EasyCart is deleted. Back up first and run an import only once unless it says otherwise.', 'wp-easycart' ); ?></p>
			<div class="ecimp-srcs" role="tablist">
				<?php foreach ( $sources as $src => $labels ) : ?>
					<button type="button" class="ecimp-src" role="tab" aria-selected="false" data-src="<?php echo esc_attr( $src ); ?>"><b><?php echo esc_html( $labels[0] ); ?></b><span><?php echo esc_html( $labels[1] ); ?></span></button>
				<?php endforeach; ?>
			</div>

			<div class="ecimp-panel" id="ecimp-panel-square" hidden>
				<?php if ( ! $square_ready ) : ?>
					<div class="ecimp-intro" style="border-bottom:0;"><div class="ecimp-notice is-warn"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'Square is not connected yet. Choose Square as the live gateway under Settings > Payment and connect your account, then come back here to import.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=payment' ) ); ?>"><?php esc_html_e( 'Open payment settings', 'wp-easycart' ); ?></a></span></div></div>
				<?php else : ?>
					<div class="ecimp-intro">
						<?php if ( ! $square_scope ) : ?>
							<div class="ecimp-notice is-danger"><span class="dashicons dashicons-lock"></span><span><?php esc_html_e( 'Inventory permission missing. Square needs an extra authorisation before stock counts can be read.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( 'https://connect.wpeasycart.com/' . ( get_option( 'ec_option_square_is_sandbox' ) ? 'square-sandbox' : 'square-v2' ) . '/?url=' . rawurlencode( admin_url( '?ec_admin_form_action=handle-square' ) ) . '&state=' . wp_rand( 1000000, 9999999 ) ); ?>"><?php esc_html_e( 'Grant inventory access', 'wp-easycart' ); ?></a></span></div>
						<?php endif; ?>
						<p class="ecst-row-desc" style="margin:0;"><?php esc_html_e( 'Runs in small batches so slower servers keep up. If it stops part-way, raise your server max execution time and run it again.', 'wp-easycart' ); ?></p>
					</div>
					<div class="ecimp-row" id="wpeasycart_square_only_new_toggle_row">
						<div class="ecst-row-text">
							<label class="ecst-label" for="wpeasycart_square_only_new"><?php esc_html_e( 'Only add new items from Square', 'wp-easycart' ); ?></label>
							<span class="ecst-row-desc"><?php esc_html_e( 'Skips anything already imported. Existing items are not modified in any way.', 'wp-easycart' ); ?></span>
						</div>
						<div class="ecst-row-control">
							<label class="ecst-toggle ecimp-toggle" for="wpeasycart_square_only_new"><input type="checkbox" id="wpeasycart_square_only_new" value="1" /><span class="ecst-toggle-track"><span class="ecst-toggle-knob"></span></span></label>
						</div>
					</div>
					<div id="wpeasycart_square_full_import_options">
						<div class="ecimp-row is-child">
							<div class="ecst-row-text"><label class="ecst-label" for="wpeasycart_square_sync_matches"><?php esc_html_e( 'Update items already imported', 'wp-easycart' ); ?></label><span class="ecst-row-desc"><?php esc_html_e( 'Square to EasyCart: overwrites previously imported categories, options and products with the current Square data.', 'wp-easycart' ); ?></span></div>
							<div class="ecst-row-control"><label class="ecimp-check"><input type="checkbox" id="wpeasycart_square_sync_matches" value="1" checked="checked" /> <?php esc_html_e( 'Sync matches', 'wp-easycart' ); ?></label></div>
						</div>
						<div class="ecimp-row is-child">
							<div class="ecst-row-text"><label class="ecst-label" for="wpeasycart_square_import_products"><?php esc_html_e( 'Import products', 'wp-easycart' ); ?></label><span class="ecst-row-desc"><?php esc_html_e( 'Products plus the categories, option sets and modifiers they need.', 'wp-easycart' ); ?></span></div>
							<div class="ecst-row-control"><label class="ecimp-check"><input type="checkbox" id="wpeasycart_square_import_products" value="1" checked="checked" /> <?php esc_html_e( 'Products', 'wp-easycart' ); ?></label></div>
						</div>
						<div class="ecimp-row is-child">
							<div class="ecst-row-text"><label class="ecst-label" for="wpeasycart_square_import_inventory"><?php esc_html_e( 'Import inventory', 'wp-easycart' ); ?></label><span class="ecst-row-desc"><?php esc_html_e( 'Copies Square stock counts onto the matching products and variants.', 'wp-easycart' ); ?></span></div>
							<div class="ecst-row-control"><label class="ecimp-check"><input type="checkbox" id="wpeasycart_square_import_inventory" value="1" checked="checked" /> <?php esc_html_e( 'Stock', 'wp-easycart' ); ?></label></div>
						</div>
						<div class="ecimp-intro" style="border-bottom:0;">
							<div class="ecimp-notice is-danger" id="wpeasycart_square_import_something_required" style="display:none;"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'Choose products, inventory, or both.', 'wp-easycart' ); ?></span></div>
							<div class="ecimp-notice is-warn" id="wpeasycart_square_import_overwrite_notice"><span class="dashicons dashicons-info"></span><span><?php esc_html_e( 'Importing overwrites any changes you made to products, categories, options or stock that were previously imported from Square.', 'wp-easycart' ); ?></span></div>
						</div>
					</div>
					<div class="ecimp-intro" id="wpeasycart_square_only_new_notice" style="display:none;border-bottom:0;">
						<div class="ecimp-notice is-info"><span class="dashicons dashicons-info"></span><span><?php esc_html_e( 'Only new items are added. Categories, options, option items, products and inventory previously imported from Square are left untouched.', 'wp-easycart' ); ?></span></div>
					</div>
					<div class="ecimp-foot">
						<button type="button" class="ecv2-btn ecv2-btn-primary" id="wpeasycart_square_start_button" onclick="wpeasycart_start_square_import(); return false;"><?php esc_html_e( 'Import from Square', 'wp-easycart' ); ?></button>
						<button type="button" class="ecv2-btn ecv2-btn-busy" id="wpeasycart_square_processing_button" style="display:none;" disabled="disabled"><span class="dashicons dashicons-update ecv2-spin"></span><?php esc_html_e( 'Importing...', 'wp-easycart' ); ?></button>
						<div class="ecimp-progress" id="wpeasycart_square_import_progress_bar" style="display:none;">
							<div class="ec_admin_progress_bar"><div style="width:10%;"></div></div>
							<div class="ec_admin_process_status"><span><?php esc_html_e( 'Importer running', 'wp-easycart' ); ?></span></div>
						</div>
						<div id="wpeasycart_square_inventory_sync_progress_bar" style="display:none;"></div>
					</div>
				<?php endif; ?>
			</div>

			<div class="ecimp-panel" id="ecimp-panel-woo" hidden>
				<div>
					<?php /* Batched AJAX importer ( since 6.0.0 ): ec_admin_ajax_woo_import in admin/inc/wp_easycart_admin_cart_importer.php, driven by wpeasycart_start_woo_import() in admin/js/cart-importer.js. */ ?>
					<input type="hidden" id="wpec_woo_importer_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-woo-importer-settings' ) ); ?>" />
					<div class="ecimp-intro">
						<div class="ecimp-notice is-ok" id="wpeasycart_woo_import_done" <?php echo ( 'woo-imported' === $ec_success ) ? '' : 'style="display:none;"'; ?>><span class="dashicons dashicons-yes"></span><span><?php esc_html_e( 'Your WooCommerce store has been imported. Not every extension can be carried over, so check the products and add anything missing by hand.', 'wp-easycart' ); ?></span></div>
						<div class="ecimp-notice is-danger" id="wpeasycart_woo_import_error" style="display:none;"><span class="dashicons dashicons-warning"></span><span class="ecimp-msg"></span></div>
						<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
							<div class="ecimp-notice is-warn"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'WooCommerce is not active on this site. Install and activate it so its data can be read, then run the import.', 'wp-easycart' ); ?></span></div>
						<?php endif; ?>
						<p class="ecst-row-desc" style="margin:0;"><?php esc_html_e( 'Reads the WooCommerce data on this WordPress install and creates EasyCart records from it:', 'wp-easycart' ); ?></p>
						<ul class="ecimp-list">
							<li><?php esc_html_e( 'Product categories, and attributes as option sets, connected to products the way Woo has them', 'wp-easycart' ); ?></li>
							<li><?php esc_html_e( 'Products: title, descriptions, regular and sale price, taxable, virtual, SKU ( a random model number if empty ), stock, downloads with their limits and expiry, reviews', 'wp-easycart' ); ?></li>
							<li><?php esc_html_e( 'Up to five images from the gallery, or the featured image', 'wp-easycart' ); ?></li>
						</ul>
					</div>
					<div class="ecimp-foot">
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-primary" id="wpeasycart_woo_start_button" onclick="wpeasycart_start_woo_import(); return false;"><?php esc_html_e( 'Import WooCommerce data', 'wp-easycart' ); ?></button>
							<button type="button" class="ecv2-btn ecv2-btn-busy" id="wpeasycart_woo_processing_button" style="display:none;" disabled="disabled"><span class="dashicons dashicons-update ecv2-spin"></span><?php esc_html_e( 'Importing...', 'wp-easycart' ); ?></button>
						<?php else : ?>
							<button type="button" class="ecv2-btn" disabled="disabled"><?php esc_html_e( 'Import WooCommerce data', 'wp-easycart' ); ?></button>
						<?php endif; ?>
						<span class="ecst-row-desc"><?php esc_html_e( 'Runs 50 products per request so large stores finish without a server timeout. Keep this tab open until it reports done.', 'wp-easycart' ); ?></span>
						<div class="ecimp-progress" id="wpeasycart_woo_import_progress_bar" style="display:none;" data-l-starting="<?php esc_attr_e( 'Copying categories and option sets...', 'wp-easycart' ); ?>" data-l-products="<?php esc_attr_e( 'products imported', 'wp-easycart' ); ?>" data-l-done="<?php esc_attr_e( 'All products imported.', 'wp-easycart' ); ?>" data-l-error="<?php esc_attr_e( 'The import stopped because the server did not answer. Run it again to continue; products already imported are kept.', 'wp-easycart' ); ?>">
							<div class="ec_admin_progress_bar"><div style="width:2%;"></div></div>
							<div class="ec_admin_process_status"><span><?php esc_html_e( 'Importer running', 'wp-easycart' ); ?></span></div>
						</div>
					</div>
				</div>
			</div>

			<div class="ecimp-panel" id="ecimp-panel-oscommerce" hidden>
				<form action="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=cart-importer&ec_action=import-oscommerce-products' ) ); ?>" method="POST" enctype="multipart/form-data" novalidate="novalidate">
					<div class="ecimp-intro">
						<?php if ( $oscommerce_ran || 'oscommerce-imported' === $ec_success ) : ?>
							<div class="ecimp-notice is-ok"><span class="dashicons dashicons-yes"></span><span><?php esc_html_e( 'Your osCommerce store has been imported. osCommerce has many extensions, so check the data and add anything missing by hand.', 'wp-easycart' ); ?></span></div>
						<?php endif; ?>
						<div class="ecimp-notice is-danger"><span class="dashicons dashicons-warning"></span><span><?php esc_html_e( 'Only use this if osCommerce shares this WordPress database. Without its tables the import stops with a server error and you will need the browser back button.', 'wp-easycart' ); ?></span></div>
						<p class="ecst-row-desc" style="margin:0;"><?php esc_html_e( 'Creates EasyCart records from the osCommerce tables:', 'wp-easycart' ); ?></p>
						<ul class="ecimp-list">
							<li><?php esc_html_e( 'Categories, manufacturers, option sets and option item price changes', 'wp-easycart' ); ?></li>
							<li><?php esc_html_e( 'Products: stock, model number, weight, image name, manufacturer, title and description, connected to their option sets and categories', 'wp-easycart' ); ?></li>
						</ul>
					</div>
					<div class="ecimp-foot">
						<button type="submit" class="ecv2-btn ecv2-btn-primary"><?php esc_html_e( 'Import osCommerce data', 'wp-easycart' ); ?></button>
					</div>
				</form>
			</div>

			<div class="ecimp-panel" id="ecimp-panel-shopify" hidden>
				<div class="ecimp-intro" style="border-bottom:0;">
					<div class="ecimp-notice is-info"><span class="dashicons dashicons-info"></span><span><?php esc_html_e( 'Shopify discontinued the private app system this importer relied on, so there is no longer a way to pull your data out of Shopify automatically. Export a product CSV from Shopify and use the product importer under Products instead.', 'wp-easycart' ); ?></span></div>
					<?php do_action( 'wp_easycart_admin_shopify_import_end' ); ?>
				</div>
			</div>
		</div>
		<?php
	}
}

return array(
	'slug'        => 'integrations',
	'title'       => __( 'Integrations', 'wp-easycart' ),
	'description' => __( 'Analytics and ads tags, email marketing lists, affiliate tracking, Amazon S3 downloads, DecoNetwork and the cart importer.', 'wp-easycart' ),
	'group'       => 'integrations',
	'icon'        => 'admin-plugins',
	'docs'        => array( 'settings', 'third-party', 'google-analytics' ),
	'legacy'      => array( 'third-party', 'cart-importer' ),
	'upsell'      => 'default',
	'sections'    => array(

		'google-analytics' => array(
			'title'  => __( 'Google Analytics', 'wp-easycart' ),
			'hint'   => __( 'GA4 ecommerce events on the storefront, plus the retired Universal Analytics tag', 'wp-easycart' ),
			'fields' => array(
				'ec_option_google_ga4_property_id' => array(
					'type'        => 'text',
					'label'       => __( 'GA4 measurement ID', 'wp-easycart' ),
					'desc'        => __( 'The G-XXXXXXXXXX ID from your GA4 web data stream. Loads the Google tag on store pages and sends view item, add to cart, checkout and purchase events.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'G-XXXXXXXXXX',
					'pro'         => true,
					'keywords'    => array( 'ga4', 'analytics', 'tracking', 'gtag' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Google GA4 Property ID' ),
				),
				'ec_option_google_ga4_tag_manager' => array(
					'type'     => 'toggle',
					'label'    => __( 'Send events through Google Tag Manager', 'wp-easycart' ),
					'desc'     => __( 'Pushes the ecommerce events to the dataLayer for your Tag Manager container instead of calling gtag() directly. Your container must load GA4 itself.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'gtm', 'tag manager', 'datalayer' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Enable Tag Manager Type' ),
				),
				'ec_option_google_ga4_tag_manager_direct' => array(
					'type'     => 'toggle',
					'label'    => __( 'Also send events from the server', 'wp-easycart' ),
					'desc'     => __( 'Posts view, add to cart, checkout and purchase events from your server to Google through the Measurement Protocol, so they are recorded even when the browser blocks tracking. Needs the three values below.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'server side', 'measurement protocol', 'direct' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Enable Tag Manager Direct Integration' ),
				),
				'ec_option_google_ga4_tag_manager_measurement_id' => array(
					'type'        => 'text',
					'label'       => __( 'Measurement ID for server events', 'wp-easycart' ),
					'desc'        => __( 'From the GA4 data stream. Required even when the events go through a Tag Manager server container.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'G-XXXXXXXXXX',
					'pro'         => true,
					'advanced'    => true,
					'parent'      => 'ec_option_google_ga4_tag_manager_direct',
					'keywords'    => array( 'measurement id', 'server side', 'ga4' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Tags Mesurement ID' ),
				),
				'ec_option_google_ga4_tag_manager_api_secret' => array(
					'type'     => 'password',
					'label'    => __( 'Measurement Protocol API secret', 'wp-easycart' ),
					'desc'     => __( 'Create one under the data stream’s Measurement Protocol API secrets. Only ever sent from your server.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'advanced' => true,
					'parent'   => 'ec_option_google_ga4_tag_manager_direct',
					'keywords' => array( 'api secret', 'server side', 'ga4' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Tags API Secret' ),
				),
				'ec_option_google_ga4_tag_manager_server_url' => array(
					'type'        => 'url',
					'label'       => __( 'Server container URL', 'wp-easycart' ),
					'desc'        => __( 'Your Tag Manager server container endpoint. Server events are posted here rather than straight to Google.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'https://gtm.example.com',
					'pro'         => true,
					'advanced'    => true,
					'parent'      => 'ec_option_google_ga4_tag_manager_direct',
					'keywords'    => array( 'server container', 'tag manager', 'endpoint' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Tags Server Container URL' ),
				),
				'ec_option_googleanalyticsid' => array(
					'type'        => 'text',
					'label'       => __( 'Universal Analytics tracking ID (retired)', 'wp-easycart' ),
					'desc'        => __( 'Legacy UA-XXXXXXX-X property. Google stopped processing Universal Analytics data in 2024; use a GA4 measurement ID instead. Leave empty to stop loading the old analytics.js tag.', 'wp-easycart' ),
					'default'     => '', // ec_wpoptionset ships the 'UA-XXXXXXX-X' placeholder, which every reader treats as empty.
					'placeholder' => 'UA-XXXXXXX-X',
					'advanced'    => true,
					'sanitize'    => 'wp_easycart_settings_integrations_sanitize_ga_id',
					'keywords'    => array( 'universal analytics', 'ua', 'legacy', 'tracking' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Analytics Setup', 'label' => 'Google Analytics ID' ),
				),
			),
		),

		'google-ads' => array(
			'title'  => __( 'Google Ads', 'wp-easycart' ),
			'hint'   => __( 'Conversion tracking and remarketing on the order success page', 'wp-easycart' ),
			'fields' => array(
				'ec_option_google_adwords_tag_id' => array(
					'type'        => 'text',
					'label'       => __( 'Google Ads tag ID', 'wp-easycart' ),
					'desc'        => __( 'AW-XXXXXXXXX from your Google Ads account. Loads the Google tag on store pages alongside GA4 so Ads conversions and remarketing audiences can be measured.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'AW-XXXXXXXXX',
					'pro'         => true,
					'keywords'    => array( 'adwords', 'google ads', 'gtag', 'remarketing' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Tag ID (AW-XXXXXXXXX)' ),
				),
				'ec_option_google_adwords_conversion_id' => array(
					'type'     => 'text',
					'label'    => __( 'Conversion ID', 'wp-easycart' ),
					'desc'     => __( 'The numeric ID from a Google Ads conversion action. When set, the order success page fires the conversion with the order total.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'adwords', 'conversion', 'purchase' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion ID (Conversion Tracking)' ),
				),
				'ec_option_google_adwords_label' => array(
					'type'     => 'text',
					'label'    => __( 'Conversion label', 'wp-easycart' ),
					'desc'     => __( 'The label string from the same conversion action.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'adwords', 'conversion', 'label' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion Label' ),
				),
				'ec_option_google_adwords_currency' => array(
					'type'        => 'text',
					'label'       => __( 'Conversion currency', 'wp-easycart' ),
					'desc'        => __( 'Three-letter code reported with the conversion value.', 'wp-easycart' ),
					'default'     => 'USD',
					'placeholder' => 'USD',
					'pro'         => true,
					'keywords'    => array( 'adwords', 'currency', 'conversion value' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion Currency' ),
				),
				'ec_option_google_adwords_remarketing_only' => array(
					'type'     => 'pills',
					'label'    => __( 'Conversion tag purpose', 'wp-easycart' ),
					'desc'     => __( 'Remarketing only records the visit for audience building without counting a conversion.', 'wp-easycart' ),
					'default'  => 'false',
					'options'  => array(
						'false' => __( 'Count conversions', 'wp-easycart' ),
						'true'  => __( 'Remarketing only', 'wp-easycart' ),
					),
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'adwords', 'remarketing', 'audience' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Remarketing Only' ),
				),
				'ec_option_google_adwords_language' => array(
					'type'        => 'text',
					'label'       => __( 'Conversion language', 'wp-easycart' ),
					'desc'        => __( 'Parameter of the classic conversion.js snippet. Leave as en unless Google gave you another code.', 'wp-easycart' ),
					'default'     => 'en',
					'placeholder' => 'en',
					'pro'         => true,
					'advanced'    => true,
					'keywords'    => array( 'adwords', 'language', 'conversion.js' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion Language' ),
				),
				'ec_option_google_adwords_format' => array(
					'type'        => 'text',
					'label'       => __( 'Conversion format', 'wp-easycart' ),
					'desc'        => __( 'Parameter of the classic conversion.js snippet. 3 shows no notification to the shopper.', 'wp-easycart' ),
					'default'     => '3',
					'placeholder' => '3',
					'pro'         => true,
					'advanced'    => true,
					'keywords'    => array( 'adwords', 'format', 'conversion.js' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion Format' ),
				),
				'ec_option_google_adwords_color' => array(
					'type'        => 'text',
					'label'       => __( 'Conversion pixel color', 'wp-easycart' ),
					'desc'        => __( 'Six hex digits without the #, used by the classic notification formats.', 'wp-easycart' ),
					'default'     => 'ffffff',
					'placeholder' => 'ffffff',
					'pro'         => true,
					'advanced'    => true,
					'sanitize'    => 'wp_easycart_settings_integrations_sanitize_hex',
					'keywords'    => array( 'adwords', 'color', 'color', 'conversion.js' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Google Adwords Setup', 'label' => 'Google Conversion Color' ),
				),
			),
		),

		'google-merchant' => array(
			'title'  => __( 'Google Merchant', 'wp-easycart' ),
			'hint'   => __( 'Product feed for Google Shopping: download the attribute CSV, upload it, export the XML feed', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(),
			'render' => 'wp_easycart_settings_integrations_render_google_merchant',
		),

		'meta-pixel' => array(
			'title'  => __( 'Meta Pixel', 'wp-easycart' ),
			'hint'   => __( 'Facebook and Instagram ads tracking', 'wp-easycart' ),
			'fields' => array(
				'ec_option_fb_pixel' => array(
					'type'     => 'text',
					'label'    => __( 'Pixel ID', 'wp-easycart' ),
					'desc'     => __( 'Adds the Meta Pixel to store pages and fires ViewContent, AddToCart, InitiateCheckout, AddPaymentInfo and Purchase events.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'facebook', 'instagram', 'pixel', 'meta' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Facebook Pixel Setup', 'label' => 'Facebook Pixel ID' ),
				),
			),
		),

		'mailerlite' => array(
			'title'  => __( 'MailerLite', 'wp-easycart' ),
			'hint'   => __( 'Newsletter subscribers synced to your MailerLite account', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_mailerlite' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with MailerLite', 'wp-easycart' ),
					'desc'     => __( 'Shoppers who tick the newsletter box are added to MailerLite; unsubscribing removes them. Products can also add buyers to a MailerLite group.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'subscribers' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Mailer Lite Setup', 'label' => 'Enable Mailer Lite' ),
				),
				'ec_option_mailerlite_api_key' => array(
					'type'     => 'password',
					'label'    => __( 'API token', 'wp-easycart' ),
					'desc'     => __( 'From MailerLite › Integrations › API. Only sent from your server.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_mailerlite',
					'keywords' => array( 'api key', 'token', 'mailerlite' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Mailer Lite Setup', 'label' => 'Mailer Lite API Token' ),
				),
			),
		),

		'convertkit' => array(
			'title'  => __( 'ConvertKit (Kit)', 'wp-easycart' ),
			'hint'   => __( 'Newsletter subscribers added to a Kit form', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_convertkit' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with ConvertKit', 'wp-easycart' ),
					'desc'     => __( 'Shoppers who tick the newsletter box are subscribed to the form chosen below; unsubscribing removes them.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'Enable ConvertKit' ),
				),
				'ec_option_convertkit_api_key' => array(
					'type'     => 'text',
					'label'    => __( 'API key', 'wp-easycart' ),
					'desc'     => __( 'From your Kit account settings. Used to list your forms.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_convertkit',
					'keywords' => array( 'api key', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit API Key' ),
				),
				'ec_option_convertkit_api_secret' => array(
					'type'     => 'password',
					'label'    => __( 'API secret', 'wp-easycart' ),
					'desc'     => __( 'Needed to subscribe and unsubscribe. Only sent from your server.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_convertkit',
					'keywords' => array( 'api secret', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit API Secret' ),
				),
				'ec_option_convertkit_form' => array(
					'type'     => 'select',
					'label'    => __( 'Form to subscribe to', 'wp-easycart' ),
					'desc'     => __( 'Kit only accepts subscribers through a form. Save the API key first, then reload to list your forms.', 'wp-easycart' ),
					'default'  => '',
					'options'  => wp_easycart_settings_integrations_remote_options( 'ec_option_convertkit_form', __( 'Choose a form', 'wp-easycart' ), /* translators: %s: ConvertKit form id */ __( 'Form %s', 'wp-easycart' ) ),
					'pro'      => true,
					'parent'   => 'ec_option_enable_convertkit',
					'keywords' => array( 'form', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit Form' ),
				),
			),
		),

		'activecampaign' => array(
			'title'  => __( 'ActiveCampaign', 'wp-easycart' ),
			'hint'   => __( 'Newsletter subscribers added as contacts on an ActiveCampaign list', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_activecampaign' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with ActiveCampaign', 'wp-easycart' ),
					'desc'     => __( 'Shoppers who tick the newsletter box become contacts on the list chosen below; unsubscribing removes them. Products can also tag buyers.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'crm' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Enable Active Campaign' ),
				),
				'ec_option_activecampaign_api_url' => array(
					'type'        => 'url',
					'label'       => __( 'API URL', 'wp-easycart' ),
					'desc'        => __( 'Your account’s API access URL, found under Settings › Developer in ActiveCampaign.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'https://youraccount.api-us1.com',
					'pro'         => true,
					'parent'      => 'ec_option_enable_activecampaign',
					'keywords'    => array( 'api url', 'activecampaign', 'endpoint' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign API URL' ),
				),
				'ec_option_activecampaign_api_key' => array(
					'type'     => 'password',
					'label'    => __( 'API key', 'wp-easycart' ),
					'desc'     => __( 'From the same Developer settings page. Only sent from your server.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_activecampaign',
					'keywords' => array( 'api key', 'activecampaign', 'token' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign API Key' ),
				),
				'ec_option_activecampaign_list' => array(
					'type'     => 'select',
					'label'    => __( 'List to add contacts to', 'wp-easycart' ),
					'desc'     => __( 'Create a list in ActiveCampaign for store subscribers. Save the API URL and key first, then reload to see your lists.', 'wp-easycart' ),
					'default'  => '',
					'options'  => wp_easycart_settings_integrations_remote_options( 'ec_option_activecampaign_list', __( 'Choose a list', 'wp-easycart' ), /* translators: %s: ActiveCampaign list id */ __( 'List %s', 'wp-easycart' ) ),
					'pro'      => true,
					'parent'   => 'ec_option_enable_activecampaign',
					'keywords' => array( 'list', 'activecampaign', 'contacts' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign List' ),
				),
			),
		),

		'shareasale' => array(
			'title'  => __( 'ShareASale', 'wp-easycart' ),
			'hint'   => __( 'Affiliate sale tracking on the order success page', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_shareasale' => array(
					'type'     => 'toggle',
					'label'    => __( 'Report sales to ShareASale', 'wp-easycart' ),
					'desc'     => __( 'Loads the ShareASale tracking pixel on store pages and records each paid order against the referring affiliate.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'affiliate', 'shareasale', 'referral' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ShareASale Setup', 'label' => 'Enable ShareASale' ),
				),
				'ec_option_shareasale_merchant_id' => array(
					'type'     => 'text',
					'label'    => __( 'Merchant ID', 'wp-easycart' ),
					'desc'     => __( 'Your numeric ShareASale merchant ID. Nothing is tracked until it is set.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_shareasale',
					'keywords' => array( 'merchant id', 'shareasale', 'affiliate' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ShareASale Setup', 'label' => 'ShareASale Merchant ID' ),
				),
				'ec_option_shareasale_send_details' => array(
					'type'     => 'toggle',
					'label'    => __( 'Include line items in the sale report', 'wp-easycart' ),
					'desc'     => __( 'Sends each item’s SKU, price and quantity with the order total so per-product commissions can be set up.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'parent'   => 'ec_option_enable_shareasale',
					'keywords' => array( 'sku', 'line items', 'commission' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ShareASale Setup', 'label' => 'Send Cart Details to ShareASale' ),
				),
				'ec_option_shareasale_currency_conversion' => array(
					'type'     => 'toggle',
					'label'    => __( 'Convert amounts to the checkout currency', 'wp-easycart' ),
					'desc'     => __( 'Reports totals and line items in the currency the shopper checked out with instead of your base currency. Only for stores using currency conversion.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'parent'   => 'ec_option_enable_shareasale',
					'keywords' => array( 'currency', 'conversion', 'shareasale' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ShareASale Setup', 'label' => 'Use Currency Conversion' ),
				),
			),
		),

		'amazon-s3' => array(
			'title'  => __( 'Amazon S3', 'wp-easycart' ),
			'hint'   => __( 'Serve download product files from an S3 bucket instead of the WordPress uploads folder', 'wp-easycart' ),
			'fields' => array(
				'ec_option_amazon_key' => array(
					'type'     => 'text',
					'label'    => __( 'Access key ID', 'wp-easycart' ),
					'desc'     => __( 'From an IAM user with read access to the bucket.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'aws', 's3', 'access key', 'downloads' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Amazon S3 Setup', 'label' => 'Amazon Key' ),
				),
				'ec_option_amazon_secret' => array(
					'type'     => 'password',
					'label'    => __( 'Secret access key', 'wp-easycart' ),
					'desc'     => __( 'Keep this private. Only used from your server to sign download requests.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'aws', 's3', 'secret key' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Amazon S3 Setup', 'label' => 'Amazon Secret' ),
				),
				'ec_option_amazon_bucket' => array(
					'type'     => 'text',
					'label'    => __( 'Download bucket', 'wp-easycart' ),
					'desc'     => __( 'Bucket name that holds the files named on your download products.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'aws', 's3', 'bucket', 'downloads' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Amazon S3 Setup', 'label' => 'Amazon Download Bucket' ),
				),
				'ec_option_amazon_bucket_region' => array(
					'type'     => 'select',
					'label'    => __( 'Bucket region', 'wp-easycart' ),
					'desc'     => __( 'The AWS region the bucket was created in.', 'wp-easycart' ),
					'default'  => '',
					'options'  => array(
						''               => __( 'None selected', 'wp-easycart' ),
						'us-east-2'      => __( 'US East (Ohio)', 'wp-easycart' ),
						'us-east-1'      => __( 'US East (N. Virginia)', 'wp-easycart' ),
						'us-west-1'      => __( 'US West (N. California)', 'wp-easycart' ),
						'us-west-2'      => __( 'US West (Oregon)', 'wp-easycart' ),
						'ca-central-1'   => __( 'Canada (Central)', 'wp-easycart' ),
						'ap-south-1'     => __( 'Asia Pacific (Mumbai)', 'wp-easycart' ),
						'ap-northeast-2' => __( 'Asia Pacific (Seoul)', 'wp-easycart' ),
						'ap-southeast-1' => __( 'Asia Pacific (Singapore)', 'wp-easycart' ),
						'ap-southeast-2' => __( 'Asia Pacific (Sydney)', 'wp-easycart' ),
						'ap-northeast-1' => __( 'Asia Pacific (Tokyo)', 'wp-easycart' ),
						'eu-central-1'   => __( 'EU (Frankfurt)', 'wp-easycart' ),
						'eu-west-1'      => __( 'EU (Ireland)', 'wp-easycart' ),
						'eu-west-2'      => __( 'EU (London)', 'wp-easycart' ),
						'sa-east-1'      => __( 'South America (Sao Paulo)', 'wp-easycart' ),
					),
					'pro'      => true,
					'keywords' => array( 'aws', 's3', 'region' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Amazon S3 Setup', 'label' => 'Amazon Download Bucket Region' ),
				),
			),
		),

		'deconetwork' => array(
			'title'  => __( 'DecoNetwork', 'wp-easycart' ),
			'hint'   => __( 'Products shoppers personalise in your DecoNetwork designer before adding to cart', 'wp-easycart' ),
			'fields' => array(
				'ec_option_deconetwork_url' => array(
					'type'        => 'text',
					'label'       => __( 'DecoNetwork store domain', 'wp-easycart' ),
					'desc'        => __( 'The host name of your DecoNetwork store, without https://. Design links and artwork previews are built from it.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'mystore.deconetwork.com',
					'pro'         => true,
					'sanitize'    => 'wp_easycart_settings_integrations_sanitize_host',
					'keywords'    => array( 'deconetwork', 'domain', 'url', 'designer' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'DecoNetwork Setup', 'label' => 'DecoNetwork URL' ),
				),
				'ec_option_deconetwork_password' => array(
					'type'     => 'password',
					'label'    => __( 'Order commit password', 'wp-easycart' ),
					'desc'     => __( 'The custom order commit value you set under DecoNetwork API settings, not your account password. Sent with each paid order so DecoNetwork marks the designs as ordered.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'deconetwork', 'password', 'order commit', 'auth' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'DecoNetwork Setup', 'label' => 'DecoNetwork Order Password' ),
				),
				'ec_option_deconetwork_allow_blank_products' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sell undecorated items too', 'wp-easycart' ),
					'desc'     => __( 'Shows a quantity box and Add to cart button next to the Design now button on DecoNetwork products, so shoppers can buy the blank item without designing it.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'deconetwork', 'blank', 'design now', 'decoration' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'DecoNetwork - Allow Blank Item Purchase' ),
				),
			),
		),

		'cart-importer' => array(
			'title'  => __( 'Cart importer', 'wp-easycart' ),
			'hint'   => __( 'Bring products in from Square, WooCommerce or osCommerce', 'wp-easycart' ),
			'fields'  => array(),
			'enqueue' => 'wp_easycart_settings_integrations_enqueue_cart_importer',
			'render'  => 'wp_easycart_settings_integrations_render_cart_importer',
		),
	),
);
