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
 * 6.0.2: MailerLite, ConvertKit ( Kit ) and ActiveCampaign moved to their own
 * page, Settings › Email marketing ( email-marketing.php ); a short section
 * here points there.
 *
 * Only the Universal Analytics ID was editable in the free plugin; every
 * other service is declared with 'pro' => true and unlocks through the gate.
 * PRO attaches the pieces a declaration cannot express ( the DecoNetwork setup
 * notes, the ConvertKit form and ActiveCampaign list pickers that are filled
 * from each service's API ) from wp-easycart-pro/admin/template/settings/integrations.php.
 * 6.0.2: the Google Merchant attribute spreadsheet moved to Settings › Search &
 * AI ( section google-attributes ); the Google Merchant section here is a
 * signpost, unless a PRO older than 6.0.2 still draws the spreadsheet here.
 *
 * 6.0.2: the Meta Pixel section adds sitewide loading, advanced matching and
 * Limited Data Use ( browser side in the free plugin, wp_easycart_meta ) and the
 * Conversions API rows ( PRO 6.0.2 attaches their runtime, a recent events
 * table as the section 'render' and a Send test event section action ). The
 * Cookie consent section ( free ) makes the Meta and Google tags wait for the
 * shopper's answer in the store's cookie banner ( wp_easycart_consent,
 * wp_easycart_has_marketing_consent() ); 6.0.2 says which banner it found and how
 * to check it.
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

if ( ! function_exists( 'wp_easycart_settings_integrations_clear_activity' ) ) {
	/**
	 * Settings › Integrations › Store activity › Clear.
	 *
	 * @since 6.0.2
	 * @return string|WP_Error
	 */
	function wp_easycart_settings_integrations_clear_activity() {
		if ( ! class_exists( 'wp_easycart_store_activity' ) ) {
			return new WP_Error( 'unavailable', __( 'This action is not available.', 'wp-easycart' ) );
		}
		wp_easycart_store_activity::clear();
		return __( 'Store activity cleared.', 'wp-easycart' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_activity_consent_rows' ) ) {
	/**
	 * Store activity: one note per Cookie consent choice that nothing on this site can answer
	 * ( wp_easycart_store_activity::consent_block() ), shown while that choice is picked ( so it follows the select ).
	 *
	 * @since 6.0.2
	 * @return array Field key => html row.
	 */
	function wp_easycart_settings_integrations_activity_consent_rows() {
		$rows = array();
		if ( ! class_exists( 'wp_easycart_store_activity' ) || ! method_exists( 'wp_easycart_store_activity', 'consent_block_choices' ) ) {
			return $rows;
		}
		foreach ( wp_easycart_store_activity::consent_block_choices() as $choice ) {
			$rows[ 'ecst_store_activity_consent_' . $choice ] = array(
				'type'      => 'html',
				'label'     => __( 'Cookie consent', 'wp-easycart' ),
				'parent'    => 'ec_option_marketing_consent',
				'show_when' => $choice,
				'consent'   => $choice,
				'render'    => 'wp_easycart_settings_integrations_render_activity_consent',
			);
		}
		return $rows;
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_activity_consent' ) ) {
	/**
	 * Store activity: while Cookie consent is set to a choice nothing on this site can answer, product views, searches and
	 * visits are never counted. Says so, with a one-click switch ( data-ecst-set ) to the choice that fixes it.
	 *
	 * @since 6.0.2
	 * @param array $field Field declaration ( 'consent' => the choice ).
	 * @param array $page  Page declaration.
	 * @return void
	 */
	function wp_easycart_settings_integrations_render_activity_consent( $field, $page = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings render callable signature.
		if ( ! class_exists( 'wp_easycart_store_activity' ) || ! method_exists( 'wp_easycart_store_activity', 'consent_block' ) ) {
			return;
		}
		$block = wp_easycart_store_activity::consent_block( isset( $field['consent'] ) ? (string) $field['consent'] : null );
		if ( ! $block ) {
			return;
		}
		echo '<div class="ecck-note is-error">';
		echo '<span class="ecck-ic dashicons dashicons-dismiss" aria-hidden="true"></span>';
		echo '<div class="ecck-body">';
		echo '<p class="ecck-msg">' . esc_html( $block['message'] ) . '</p>';
		echo '<p class="ecck-actions">';
		echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecck-switch" data-ecst-set="ec_option_marketing_consent" data-ecst-value="' . esc_attr( $block['suggest'] ) . '">' . esc_html( $block['suggest_label'] ) . '</button>';
		echo '<a class="ecst-link" href="#ecst-sec-cookie-consent">' . esc_html__( 'Open Cookie consent', 'wp-easycart' ) . '</a>';
		echo '</p></div></div>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_activity_check' ) ) {
	/**
	 * Store activity: how to check that the store counts ( the storefront check, ?wpec_activity_check=1 ).
	 *
	 * @since 6.0.2
	 * @param array $field Field declaration.
	 * @param array $page  Page declaration.
	 * @return void
	 */
	function wp_easycart_settings_integrations_render_activity_check( $field, $page = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings render callable signature.
		$url = ( class_exists( 'wp_easycart_store_activity' ) && method_exists( 'wp_easycart_store_activity', 'check_url' ) ) ? wp_easycart_store_activity::check_url() : home_url( '/?wpec_activity_check=1' );
		echo '<p class="ecst-row-desc" style="margin:0;">' . esc_html__( 'Open your store in a private window with the store activity check, then search or open a product. A small panel shows whether that browser is counted, what it sent and what Reports counted. Store admins signed in to WordPress are never counted.', 'wp-easycart' ) . ' <a class="ecst-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open the store activity check', 'wp-easycart' ) . ' ↗</a></p>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_sanitize_hosts' ) ) {
	/**
	 * Order sources ignore list ( 6.0.2 ): one site per line, stored as bare host names ( no scheme, path or www. ).
	 */
	function wp_easycart_settings_integrations_sanitize_hosts( $value, $field ) {
		$hosts = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( (string) $value ) ) as $host ) {
			$host = preg_replace( '#^[a-z][a-z0-9+.\-]*://#', '', trim( $host ) );
			$host = preg_replace( '/^www\./', '', preg_replace( '#[/?\#:].*$#', '', $host ) );
			if ( preg_match( '/^[a-z0-9.\-]{1,253}$/', $host ) && false !== strpos( $host, '.' ) && ! in_array( $host, $hosts, true ) ) {
				$hosts[] = trim( $host, '.' );
			}
		}
		return implode( "\n", array_slice( $hosts, 0, 50 ) );
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

if ( ! function_exists( 'wp_easycart_settings_integrations_render_email_marketing' ) ) {
	/**
	 * Where MailerLite, Kit and ActiveCampaign went ( 6.0.2: Settings › Email marketing ).
	 *
	 * @since 6.0.2
	 * @param array $page    Page declaration.
	 * @param array $section Section declaration.
	 */
	function wp_easycart_settings_integrations_render_email_marketing( $page, $section ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the engine's section 'render' signature.
		$url = class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_easycart_admin_settings_registry::page_url( 'newsletter-services' ) : admin_url( 'admin.php?page=wp-easycart-settings&subpage=newsletter-services' );
		echo '<p class="ecst-row-desc" style="margin:0;">' . esc_html__( 'Newsletter subscribers, customers, orders and abandoned carts for these services are set up on their own page.', 'wp-easycart' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Email marketing', 'wp-easycart' ) . '</a></p>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_google_merchant' ) ) {
	/**
	 * Section body for Google Merchant.
	 *
	 * 6.0.2: everything for Google Shopping is on Settings › Search & AI ( the product feed, Connect Google, feed options and
	 * the attribute spreadsheet, moved from here ), so this section points there and says whether Merchant Center is
	 * connected. With WP EasyCart PRO older than 6.0.2 the section keeps its old form ( 'moved_to' is not set ): that PRO
	 * swaps in the spreadsheet tool here, and without it this short summary shows under the lock.
	 *
	 * @param array $page    Page declaration.
	 * @param array $section Section declaration.
	 */
	function wp_easycart_settings_integrations_render_google_merchant( $page, $section ) {
		$search_ai = admin_url( 'admin.php?page=wp-easycart-settings&subpage=search-ai' );
		if ( empty( $section['moved_to'] ) ) {
			echo '<p class="ecst-row-desc" style="margin:0;">' . esc_html__( 'Download a CSV of your catalogue, fill in the Google Shopping attributes ( product category, gender, age group, sizes ) and upload it back. The product feed Google fetches every day is on Settings › Search & AI.', 'wp-easycart' ) . ' <a href="' . esc_url( $search_ai . '#ecst-sec-google-feed' ) . '">' . esc_html__( 'Google product feed', 'wp-easycart' ) . '</a></p>';
			return;
		}
		echo '<p class="ecst-row-desc" style="margin:0 0 10px;">' . esc_html__( 'Your Google product feed, the Connect Google option for Merchant Center and the Google attributes spreadsheet are all on Settings › Search & AI.', 'wp-easycart' ) . '</p>';
		if ( class_exists( 'wp_easycart_google_merchant_pro' ) && method_exists( 'wp_easycart_google_merchant_pro', 'connected' ) ) {
			$connected = wp_easycart_google_merchant_pro::connected();
			echo '<p class="ecst-row-desc" style="margin:0 0 10px;"><span class="dashicons dashicons-' . esc_attr( $connected ? 'yes-alt' : 'minus' ) . '" aria-hidden="true" style="font-size:16px;width:16px;height:16px;vertical-align:-3px;margin-right:4px;"></span>' . esc_html( $connected ? __( 'Google Merchant Center is connected.', 'wp-easycart' ) : __( 'Google Merchant Center is not connected.', 'wp-easycart' ) ) . '</p>';
		}
		echo '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
		echo '<a class="ecv2-btn ecv2-btn-primary" href="' . esc_url( $search_ai . '#ecst-sec-google-feed' ) . '"><span class="dashicons dashicons-rss" aria-hidden="true"></span> ' . esc_html__( 'Google product feed', 'wp-easycart' ) . '</a>';
		echo '<a class="ecv2-btn" href="' . esc_url( $search_ai . '#ecst-sec-google-attributes' ) . '"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span> ' . esc_html__( 'Google attributes in bulk', 'wp-easycart' ) . '</a>';
		echo '</div>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_consent_status' ) ) {
	/**
	 * Cookie consent: how one choice of "Ask before tracking" works on this site ( an html child row per choice, 'consent',
	 * so the engine shows the one that matches as the select changes ). Says what was found ( wp_easycart_consent::found() ),
	 * what WP EasyCart reads, and offers a one-click switch ( data-ecst-set ) to the choice that matches the banner found.
	 *
	 * @since 6.0.2
	 * @param array $field Field declaration ( 'consent' => the choice ).
	 * @param array $page  Page declaration.
	 * @return void
	 */
	function wp_easycart_settings_integrations_render_consent_status( $field, $page = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings render callable signature.
		if ( ! class_exists( 'wp_easycart_consent' ) ) {
			return;
		}
		$choice = isset( $field['consent'] ) ? (string) $field['consent'] : 'off';
		$status = wp_easycart_consent::status( $choice );
		$icons  = array(
			'ok'     => 'yes-alt',
			'notice' => 'info-outline',
			'warn'   => 'warning',
			'error'  => 'dismiss',
			'off'    => 'minus',
		);
		$icon   = isset( $icons[ $status['level'] ] ) ? $icons[ $status['level'] ] : 'info-outline';
		echo '<div class="ecck-note is-' . esc_attr( $status['level'] ) . '">';
		echo '<span class="ecck-ic dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<div class="ecck-body">';
		echo '<p class="ecck-msg">' . esc_html( $status['message'] ) . '</p>';
		echo '<dl class="ecck-facts">';
		echo '<dt>' . esc_html__( 'Found on this site', 'wp-easycart' ) . '</dt><dd>' . esc_html( wp_easycart_consent::found_text() ) . '</dd>';
		if ( '' !== $status['reads'] ) {
			echo '<dt>' . esc_html__( 'WP EasyCart reads', 'wp-easycart' ) . '</dt><dd>' . esc_html( $status['reads'] ) . '</dd>';
		}
		echo '</dl>';
		// Bug round 7: nothing on the site can answer this choice ( no banner plugin, no WP Consent API ): offer to stop asking,
		// for stores that need no cookie banner.
		$stop = ( class_exists( 'wp_easycart_store_activity' ) && method_exists( 'wp_easycart_store_activity', 'consent_block' ) ) ? wp_easycart_store_activity::consent_block( $choice ) : array();
		$stop = ( $stop && 'off' === $stop['suggest'] ) ? $stop : array();
		if ( '' !== $status['suggest'] || $status['find_api'] || $stop ) {
			echo '<p class="ecck-actions">';
			if ( '' !== $status['suggest'] ) {
				echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecck-switch" data-ecst-set="ec_option_marketing_consent" data-ecst-value="' . esc_attr( $status['suggest'] ) . '">' . esc_html( $status['suggest_label'] ) . '</button>';
			}
			if ( $stop ) {
				echo '<button type="button" class="ecv2-btn ecck-switch" data-ecst-set="ec_option_marketing_consent" data-ecst-value="off">' . esc_html( $stop['suggest_label'] ) . '</button>';
			}
			if ( $status['find_api'] ) {
				echo '<a class="ecst-link" href="' . esc_url( admin_url( 'plugin-install.php?s=' . rawurlencode( 'WP Consent API' ) . '&tab=search&type=term' ) ) . '">' . esc_html__( 'Find the WP Consent API plugin', 'wp-easycart' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</div></div>';
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_render_consent_help' ) ) {
	/**
	 * Cookie consent section body: what consent changes ( tracking, never buying ) and how to check it on the storefront
	 * ( wp_easycart_consent::check_url(): the consent check panel ).
	 *
	 * @since 6.0.2
	 * @param array $page    Page declaration.
	 * @param array $section Section declaration.
	 * @return void
	 */
	function wp_easycart_settings_integrations_render_consent_help( $page, $section ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the engine's section 'render' signature.
		$check      = class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::check_url() : home_url( '/?wpec_consent_check=1' );
		$status_url = admin_url( 'admin.php?page=wp-easycart-status&subpage=store-status' );
		?>
		<div class="ecck-help">
			<div class="ecck-col">
				<h4><?php esc_html_e( 'What consent changes', 'wp-easycart' ); ?></h4>
				<p><?php esc_html_e( 'Until the shopper accepts, the Meta Pixel and Google tags don\'t load, order sources aren\'t recorded, Reports doesn\'t count their product views, searches or visits, and WP EasyCart sends no tracking events from your server. Marketing consent covers ads and the Meta Pixel; statistics consent covers Google Analytics and the product views and searches Reports counts.', 'wp-easycart' ); ?></p>
				<p><?php esc_html_e( 'Consent never blocks buying: shoppers who refuse cookies can still check out and create an account. The newsletter box is its own consent to email, separate from cookies, so it works either way.', 'wp-easycart' ); ?></p>
			</div>
			<div class="ecck-col">
				<h4><?php esc_html_e( 'Check it works', 'wp-easycart' ); ?></h4>
				<ol>
					<li><?php esc_html_e( 'Choose how to ask above, then open your store in a private window with the consent check. A small panel shows what WP EasyCart reads.', 'wp-easycart' ); ?> <a class="ecst-link" href="<?php echo esc_url( $check ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the consent check', 'wp-easycart' ); ?> ↗</a></li>
					<li><?php esc_html_e( 'Refuse cookies in your banner. Marketing and statistics should read refused, and the Meta Pixel and Google Analytics held back.', 'wp-easycart' ); ?></li>
					<li><?php esc_html_e( 'Accept cookies ( or clear the site\'s cookies and accept ). They switch to running.', 'wp-easycart' ); ?></li>
					<li><?php esc_html_e( 'Still no change? Your banner may not be the one selected above, or a caching plugin may serve an old page: clear its cache.', 'wp-easycart' ); ?> <a class="ecst-link" href="<?php echo esc_url( $status_url ); ?>"><?php esc_html_e( 'Store Status', 'wp-easycart' ); ?></a></li>
				</ol>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_enqueue_consent' ) ) {
	/**
	 * Cookie consent section 'enqueue': the status notes' and help panel's styles ( scoped to .ecck-* ).
	 *
	 * @since 6.0.2
	 * @param array $page    Page declaration.
	 * @param array $section Section declaration.
	 * @return void
	 */
	function wp_easycart_settings_integrations_enqueue_consent( $page, $section ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the engine's section 'enqueue' signature.
		wp_add_inline_style( 'wp_easycart_admin_settings_page_v2_css', wp_easycart_settings_integrations_consent_css() );
	}
}

if ( ! function_exists( 'wp_easycart_settings_integrations_consent_css' ) ) {
	/**
	 * The Cookie consent styles.
	 *
	 * @since 6.0.2
	 * @return string
	 */
	function wp_easycart_settings_integrations_consent_css() {
		return '.ecck-note{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid var(--ecv2-g200,#e5e7eb);border-left-width:3px;border-radius:var(--ecv2-rs,6px);background:#fff;font-size:12.5px;line-height:1.5;color:var(--ecv2-g700,#374151)}
.ecck-note.is-ok{border-left-color:#10b981}.ecck-note.is-ok .ecck-ic{color:#059669}
.ecck-note.is-notice{border-left-color:#3b82f6}.ecck-note.is-notice .ecck-ic{color:#2563eb}
.ecck-note.is-warn{border-left-color:#f59e0b;background:#fffbeb}.ecck-note.is-warn .ecck-ic{color:#b45309}
.ecck-note.is-error{border-left-color:#ef4444;background:#fef2f2}.ecck-note.is-error .ecck-ic{color:#dc2626}
.ecck-note.is-off{border-left-color:var(--ecv2-g300,#d1d5db)}.ecck-note.is-off .ecck-ic{color:var(--ecv2-g500,#6b7280)}
.ecck-ic{flex:0 0 auto;font-size:18px;width:18px;height:18px;margin-top:1px}
.ecck-body{min-width:0;flex:1 1 auto}
.ecck-msg{margin:0 0 6px;font-weight:600;color:var(--ecv2-g900,#111827)}
.ecck-facts{display:grid;grid-template-columns:max-content minmax(0,1fr);gap:2px 12px;margin:0}
.ecck-facts dt{color:var(--ecv2-g500,#6b7280);font-weight:500}
.ecck-facts dd{margin:0;overflow-wrap:anywhere}
.ecck-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin:10px 0 0}
.ecck-help{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px 24px;padding:4px 0;font-size:12.5px;line-height:1.55;color:var(--ecv2-g700,#374151)}
.ecck-help h4{margin:0 0 6px;font-size:13px;color:var(--ecv2-g900,#111827)}
.ecck-help p{margin:0 0 8px}
.ecck-help ol{margin:0;padding-left:18px}
.ecck-help li{margin:0 0 6px}
@media (max-width:600px){.ecck-facts{grid-template-columns:minmax(0,1fr)}.ecck-facts dd{margin-bottom:4px}}';
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
			/* 6.0.2: the Shopify import calls send this; ecv2_shopify_import_precheck() ( admin/inc/wp_easycart_admin_cart_importer.php ) and PRO's ecv2_shopify_import_guard() check it. */
			wp_localize_script(
				'wp_easycart_admin_cart_importer_js',
				'wp_easycart_cart_importer',
				array(
					'shopify_nonce' => wp_create_nonce( 'wp-easycart-shopify-import' ),
				)
			);
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
							<li><?php esc_html_e( 'Published products that are visible in the catalog arrive active; drafts, pending, scheduled, private and hidden products arrive inactive', 'wp-easycart' ); ?></li>
						</ul>
					</div>
					<div class="ecimp-foot">
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<button type="button" class="ecv2-btn ecv2-btn-primary" id="wpeasycart_woo_start_button" onclick="wpeasycart_start_woo_import(); return false;"><?php esc_html_e( 'Import WooCommerce data', 'wp-easycart' ); ?></button>
							<button type="button" class="ecv2-btn ecv2-btn-busy" id="wpeasycart_woo_processing_button" style="display:none;" disabled="disabled"><span class="dashicons dashicons-update ecv2-spin"></span><?php esc_html_e( 'Importing...', 'wp-easycart' ); ?></button>
						<?php else : ?>
							<button type="button" class="ecv2-btn" disabled="disabled"><?php esc_html_e( 'Import WooCommerce data', 'wp-easycart' ); ?></button>
						<?php endif; ?>
						<span class="ecst-row-desc"><?php esc_html_e( 'Runs 50 products per request so large stores finish without a server timeout. Keep this tab open until it reports done. If it stops, run it again: it continues where it stopped. Running it again later adds only the products that are new in WooCommerce; products it already imported are left as they are.', 'wp-easycart' ); ?></span>
						<div class="ecimp-progress" id="wpeasycart_woo_import_progress_bar" style="display:none;" data-l-starting="<?php esc_attr_e( 'Copying categories and option sets...', 'wp-easycart' ); ?>" data-l-products="<?php esc_attr_e( 'products imported', 'wp-easycart' ); ?>" data-l-done="<?php esc_attr_e( 'All products imported.', 'wp-easycart' ); ?>" data-l-skipped="<?php /* translators: %d: number of products. */ esc_attr_e( '%d were already imported and were left as they are.', 'wp-easycart' ); ?>" data-l-error="<?php esc_attr_e( 'The import stopped because the server did not answer. Run it again to continue where it stopped; products already imported are kept and not imported twice.', 'wp-easycart' ); ?>">
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
	'description' => __( 'Analytics and ads tags, affiliate tracking, Amazon S3 downloads, DecoNetwork and the cart importer.', 'wp-easycart' ),
	'group'       => 'advanced',
	'order'       => 20,
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
					'desc'     => __( 'Posts view, add to cart, checkout and purchase events from your server to your server-side Google Tag Manager container ( Measurement Protocol ), so they are recorded even when the browser blocks tracking. Needs all three values below, the container URL included.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'server side', 'measurement protocol', 'direct' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Google Analytics GA4 Setup', 'label' => 'Enable Tag Manager Direct Integration' ),
				),
				'ec_option_google_ga4_tag_manager_measurement_id' => array(
					'type'        => 'text',
					'label'       => __( 'Measurement ID for server events', 'wp-easycart' ),
					'desc'        => __( 'From the GA4 data stream the server container sends the events to. Required.', 'wp-easycart' ),
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
					'desc'        => __( 'Your server-side Tag Manager container ( for example https://gtm.example.com ). Required: server events go only there, and your container passes them on to Google.', 'wp-easycart' ),
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

		/* 6.0.2: a signpost to Settings › Search & AI ( the feed, Connect Google and the attribute spreadsheet ), except with a
		 * WP EasyCart PRO older than 6.0.2, which still draws the spreadsheet here ( 'moved_to' tells PRO 6.0.2 to leave it be ). */
		'google-merchant' => ( defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) && version_compare( WP_EASYCART_ADMIN_PRO_VERSION, '6.0.2', '<' ) ) ? array(
			'title'  => __( 'Google Merchant', 'wp-easycart' ),
			'hint'   => __( 'Edit the Google attributes of many products at once in a spreadsheet', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(),
			'render' => 'wp_easycart_settings_integrations_render_google_merchant',
		) : array(
			'title'    => __( 'Google Merchant Center', 'wp-easycart' ),
			'hint'     => __( 'Your product feed, Connect Google and bulk attributes are on Search & AI', 'wp-easycart' ),
			'moved_to' => 'search-ai',
			'keywords' => array( 'google merchant', 'merchant center', 'google shopping', 'product feed', 'connect google', 'google attributes', 'csv' ),
			'fields'   => array(),
			'render'   => 'wp_easycart_settings_integrations_render_google_merchant',
		),

		// 6.0.2: the browser Pixel runs in FREE ( wp_easycart_meta ); the Conversions API rows need WP EasyCart PRO 6.0.2, which
		// attaches its runtime, the recent events table ( section 'render' ) and the Send test event button ( section
		// 'actions' ) through wp_easycart_settings_page_integrations.
		'meta-pixel' => array(
			'title'  => __( 'Meta Pixel', 'wp-easycart' ),
			'hint'   => __( 'Facebook and Instagram ads tracking, from the browser and from your server', 'wp-easycart' ),
			'fields' => array(
				'ec_option_fb_pixel' => array(
					'type'     => 'text',
					'label'    => __( 'Pixel ID', 'wp-easycart' ),
					'desc'     => __( 'Adds the Meta Pixel to store pages and sends product views, searches, add to cart, checkout, payment, sign-up and purchase events with the product IDs your catalog uses.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'keywords' => array( 'facebook', 'instagram', 'pixel', 'meta' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Facebook Pixel Setup', 'label' => 'Facebook Pixel ID' ),
				),
				'ec_option_fb_pixel_sitewide' => array(
					'type'     => 'toggle',
					'label'    => __( 'Load the Pixel on every page', 'wp-easycart' ),
					'desc'     => __( 'Meta also sees visits to your blog and other pages, not only store, cart and account pages, so audiences and page view counts are complete.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'facebook', 'pixel', 'sitewide', 'all pages', 'page view', 'base code' ),
					'legacy'   => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_fb_advanced_matching' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match events to shoppers', 'wp-easycart' ),
					'desc'     => __( 'Sends a hashed email and customer ID with the Pixel once a shopper is signed in or has entered their email, so Meta can credit more sales to your ads. Server events send the customer details, also hashed.', 'wp-easycart' ),
					'default'  => 1,
					'pro'      => true,
					'keywords' => array( 'facebook', 'advanced matching', 'hashed', 'email', 'match quality' ),
					'legacy'   => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_fb_capi' => array(
					'type'            => 'toggle',
					'label'           => __( 'Also send events from your server', 'wp-easycart' ),
					'desc'            => __( 'Sends every event through the Conversions API as well, with the same event IDs as the Pixel, so Meta still counts sales when a browser blocks the Pixel and counts each one only once.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'keywords'        => array( 'facebook', 'conversions api', 'capi', 'server side', 'server events' ),
					'legacy'          => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_fb_capi_token' => array(
					'type'            => 'password',
					'label'           => __( 'Conversions API access token', 'wp-easycart' ),
					'desc'            => __( 'Generate it in Meta Events Manager › your Pixel › Settings › Conversions API. Only ever sent from your server.', 'wp-easycart' ),
					'default'         => '',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_fb_capi',
					'keywords'        => array( 'facebook', 'conversions api', 'capi', 'access token', 'token' ),
					'legacy'          => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_fb_test_event_code' => array(
					'type'            => 'text',
					'label'           => __( 'Test event code', 'wp-easycart' ),
					'desc'            => __( 'From Meta Events Manager › Test events. Server events show there while a code is set; clear it once you have checked them.', 'wp-easycart' ),
					'default'         => '',
					'placeholder'     => 'TEST12345',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'advanced'        => true,
					'parent'          => 'ec_option_fb_capi',
					'keywords'        => array( 'facebook', 'test events', 'test event code', 'debug' ),
					'legacy'          => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_fb_ldu' => array(
					'type'     => 'toggle',
					'label'    => __( 'Limited Data Use', 'wp-easycart' ),
					'desc'     => __( 'Asks Meta to process events from US states with consumer privacy laws under Limited Data Use. Turn on if your privacy policy promises it.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'advanced' => true,
					'keywords' => array( 'facebook', 'ldu', 'limited data use', 'ccpa', 'privacy' ),
					'legacy'   => array( 'page' => 'integrations', 'section' => 'Meta Pixel', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		'cookie-consent' => array(
			'title'    => __( 'Cookie consent', 'wp-easycart' ),
			'hint'     => __( 'Wait for the shopper to agree before ads and analytics tags run', 'wp-easycart' ),
			'keywords' => array( 'consent', 'cookies', 'gdpr', 'wp consent api', 'privacy', 'cookie banner', 'complianz', 'cookieyes', 'cookiebot' ),
			/* 6.0.2: what consent changes and how to check it on the storefront ( wp_easycart_consent::check_url() ). */
			'render'   => 'wp_easycart_settings_integrations_render_consent_help',
			'enqueue'  => 'wp_easycart_settings_integrations_enqueue_consent',
			'fields'   => array(
				'ec_option_marketing_consent' => array(
					'type'     => 'select',
					'label'    => __( 'Ask before tracking', 'wp-easycart' ),
					'desc'     => __( 'Where your cookie banner records the shopper\'s answer. Until the shopper accepts, the Meta Pixel and Google tags don\'t load, order sources aren\'t recorded and no server events are sent. Checkout and newsletter sign-up work either way.', 'wp-easycart' ),
					'default'  => 'off',
					/* 6.0.2: Google Consent Mode v2, Cookiebot, CookieYes and Complianz too ( wp_easycart_consent reads each ), and auto:
					   the banner found on the site ( wp_easycart_consent::source() ). */
					'options'  => class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::modes() : array(
						'off'                 => __( 'Load tags without asking', 'wp-easycart' ),
						'auto'                => __( 'Follow my cookie banner ( find it automatically )', 'wp-easycart' ),
						'wp_consent_api'      => __( 'WP Consent API ( banners that support it )', 'wp-easycart' ),
						'google_consent_mode' => __( 'Google Consent Mode v2 ( set by my cookie banner )', 'wp-easycart' ),
						'cookiebot'           => __( 'Cookiebot', 'wp-easycart' ),
						'cookieyes'           => __( 'CookieYes', 'wp-easycart' ),
						'complianz'           => __( 'Complianz', 'wp-easycart' ),
					),
					'keywords' => array( 'consent', 'cookies', 'gdpr', 'wp consent api', 'cookie banner', 'consent mode', 'google consent mode', 'cookiebot', 'cookieyes', 'complianz', 'detect' ),
					'legacy'   => array( 'page' => 'integrations', 'section' => 'Cookie consent', 'label' => 'New in 6.0.2' ),
				),
				/* 6.0.2: what the choice means on this site ( wp_easycart_consent::status() ), one note per choice, shown as the select
				   changes; a note can switch the select to the choice that matches the banner found. */
				'ecst_consent_note_auto' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'auto',
					'consent'   => 'auto',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_on'  => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'wp_consent_api',
					'consent'   => 'wp_consent_api',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_gcm' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'google_consent_mode',
					'consent'   => 'google_consent_mode',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_cookiebot' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'cookiebot',
					'consent'   => 'cookiebot',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_cookieyes' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'cookieyes',
					'consent'   => 'cookieyes',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_complianz' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'complianz',
					'consent'   => 'complianz',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
				'ecst_consent_note_off' => array(
					'type'      => 'html',
					'label'     => __( 'Cookie banner status', 'wp-easycart' ),
					'parent'    => 'ec_option_marketing_consent',
					'show_when' => 'off',
					'consent'   => 'off',
					'render'    => 'wp_easycart_settings_integrations_render_consent_status',
				),
			),
		),

		/* 6.0.2: where each order came from, recorded in the browser on every storefront page. */
		'order-sources' => array(
			'title'  => __( 'Order sources', 'wp-easycart' ),
			'hint'   => __( 'Where each order came from: a search, an ad, a newsletter, or an AI assistant such as ChatGPT', 'wp-easycart' ),
			'icon'   => 'target',
			'fields' => array(
				'ec_option_order_sources'         => array(
					'type'     => 'toggle',
					'label'    => __( 'Record where orders come from', 'wp-easycart' ),
					'desc'     => __( 'Notes the referring site and campaign tags when a shopper arrives, and saves them on the order. The orders list shows each order\'s source.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'order source', 'attribution', 'utm', 'referrer', 'chatgpt', 'ai', 'campaign', 'tracking' ),
					'legacy'   => array( 'page' => 'integrations', 'section' => 'Order sources', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_order_sources_consent' => array(
					'type'      => 'select',
					'label'     => __( 'With a cookie banner, wait for', 'wp-easycart' ),
					'desc'      => __( 'When Cookie consent above follows your cookie banner, or a consent plugin that supports the WP Consent API is active, nothing is recorded until the shopper gives this consent. In the EU and the UK this record usually needs consent.', 'wp-easycart' ),
					'options'   => array(
						'marketing'  => __( 'Marketing consent', 'wp-easycart' ),
						'statistics' => __( 'Statistics consent', 'wp-easycart' ),
					),
					'default'   => 'marketing',
					'parent'    => 'ec_option_order_sources',
					'show_when' => '1',
					'keywords'  => array( 'consent', 'cookie banner', 'gdpr', 'wp consent api' ),
					'legacy'    => array( 'page' => 'integrations', 'section' => 'Order sources', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_order_sources_ignore'  => array(
					'type'        => 'textarea',
					'label'       => __( 'Ignore visits from these sites', 'wp-easycart' ),
					'desc'        => __( 'One site per line. A shopper coming back from one of these is never counted as a new source. Your own site and payment pages ( PayPal, Stripe, Square, Klarna, Afterpay, Affirm, Amazon Pay and card checks ) are already ignored.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'pay.mybank.example',
					'advanced'    => true,
					'parent'      => 'ec_option_order_sources',
					'show_when'   => '1',
					'sanitize'    => 'wp_easycart_settings_integrations_sanitize_hosts',
					'keywords'    => array( 'ignore', 'referral exclusion', 'payment' ),
					'legacy'      => array( 'page' => 'integrations', 'section' => 'Order sources', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		/* 6.0.2: the daily counts Reports reads ( wp_easycart_store_activity ). */
		'store-activity' => array(
			'title'   => __( 'Store activity', 'wp-easycart' ),
			'hint'    => __( 'Daily counts of product views, add to cart, visits, checkout steps and searches for Reports', 'wp-easycart' ),
			'icon'    => 'chart',
			// Bug round 7: a note while Cookie consent is set to a choice nothing on this site can answer ( views, searches and
			// visits are then never counted ), and how to check the store counts ( ?wpec_activity_check=1 ).
			'fields'  => array_merge(
				array(
					'ec_option_store_activity' => array(
						'type'     => 'toggle',
						'label'    => __( 'Count store activity for Reports', 'wp-easycart' ),
						'desc'     => __( 'Keeps only daily totals: nothing about who viewed or searched is stored. While Cookie consent is on, product views and searches count only after the shopper allows statistics in your cookie banner, and visits only where order sources are recorded; add to cart and checkout steps always count.', 'wp-easycart' ),
						'default'  => 1,
						'keywords' => array( 'reports', 'analytics', 'views', 'conversion', 'funnel', 'search terms', 'privacy', 'statistics' ),
						'legacy'   => array( 'page' => 'integrations', 'section' => 'Store activity', 'label' => 'New in 6.0.2' ),
					),
				),
				wp_easycart_settings_integrations_activity_consent_rows(),
				array(
					'ec_option_store_activity_days' => array(
						'type'      => 'select',
						'label'     => __( 'Keep daily counts for', 'wp-easycart' ),
						'desc'      => __( 'Older days are deleted each night. Orders, payments and refunds are never deleted.', 'wp-easycart' ),
						'options'   => array(
							'90'   => __( '90 days', 'wp-easycart' ),
							'365'  => __( '1 year', 'wp-easycart' ),
							'730'  => __( '2 years', 'wp-easycart' ),
							'1825' => __( '5 years', 'wp-easycart' ),
						),
						'default'   => '730',
						'parent'    => 'ec_option_store_activity',
						'show_when' => '1',
						'keywords'  => array( 'retention', 'data', 'privacy' ),
						'legacy'    => array( 'page' => 'integrations', 'section' => 'Store activity', 'label' => 'New in 6.0.2' ),
					),
					'ecst_store_activity_check'     => array(
						'type'      => 'html',
						'label'     => __( 'Check it counts', 'wp-easycart' ),
						'parent'    => 'ec_option_store_activity',
						'show_when' => '1',
						'keywords'  => array( 'test', 'check', 'not counting', 'searches', 'views' ),
						'render'    => 'wp_easycart_settings_integrations_render_activity_check',
					),
				)
			),
			'actions' => array(
				'clear-activity' => array(
					'id'             => 'clear-activity',
					'label'          => __( 'Clear store activity', 'wp-easycart' ),
					'desc'           => __( 'Deletes every daily count. Reports start counting again from today.', 'wp-easycart' ),
					'button'         => __( 'Clear', 'wp-easycart' ),
					'confirm'        => __( 'Clear all store activity?', 'wp-easycart' ),
					'confirm_title'  => __( 'Product views, add to cart, visits, checkout steps and searches are deleted. Orders are not touched.', 'wp-easycart' ),
					'confirm_button' => __( 'Clear activity', 'wp-easycart' ),
					'danger'         => true,
					'callback'       => 'wp_easycart_settings_integrations_clear_activity',
				),
			),
		),

		'email-marketing' => array(
			'title'  => __( 'MailerLite, Kit and ActiveCampaign', 'wp-easycart' ),
			'hint'   => __( 'Moved to Settings › Email marketing in 6.0.2', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'wp_easycart_settings_integrations_render_email_marketing',
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

		/* 6.0.2: the accounting and sales-channel extensions ( Premium ), with a way to the rest. Each row says whether the
		   extension is on, or what Premium adds ( wp_easycart_admin_extensions::print_integrations_section() ). */
		'premium-extensions' => array(
			'title'  => __( 'Premium extensions', 'wp-easycart' ),
			'hint'   => __( 'QuickBooks Desktop, Facebook & Instagram and the rest of the extensions, with QuickBooks Online and Xero coming soon', 'wp-easycart' ),
			'icon'   => 'puzzle',
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_extensions', 'print_integrations_section' ),
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
