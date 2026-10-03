<?php
/**
 * Reports ( 6.0.2 ).
 *
 * The page is drawn by admin/js/reports-v2.js from wp_easycart_reports::page_config() ( the Overview for the last
 * 30 days comes with the page ). Kept from the older page for WP EasyCart PRO and extensions: the hooks
 * wp_easycart_admin_dashboard_pre_chart / _post, wp_easycart_reports_filters_pre / _post ( PRO's #location_filter
 * calls wpeasycart_admin_update_chart_data() ), wp_easycart_dashboard_reports_links_start / _end ( JavaScript that adds to
 * `modal` or `body` in the export dialog ), the jQuery event wpeasycart_reports_filters_changed with the older request
 * data, the ids #daily_filter, #product_filter, #country_filter, #billing_country_filter, #wpeasycart_admin_report_range1,
 * and the export job ( ec_admin_create_report_export ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'wp_easycart_admin_dashboard_pre_chart' );

$ecv2_rp_config      = wp_easycart_reports::page_config();
$ecv2_rp_license     = false;
$ecv2_rp_days_left   = 0;
$ecv2_rp_is_premium  = false;
$ecv2_rp_is_trial    = false;
$ecv2_rp_key         = '';
$ecv2_rp_renew_url   = 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
$ecv2_rp_upgrade_url = 'https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/';
if ( function_exists( 'wp_easycart_admin_license' ) ) {
	$ecv2_rp_license      = wp_easycart_admin_license()->license_data;
	$ecv2_rp_license_info = get_option( 'wp_easycart_license_info' );
	$ecv2_rp_key          = ( is_array( $ecv2_rp_license_info ) && isset( $ecv2_rp_license_info['transaction_key'] ) ) ? $ecv2_rp_license_info['transaction_key'] : '';
	if ( $ecv2_rp_license && isset( $ecv2_rp_license->support_end_date ) ) {
		$ecv2_rp_days_left  = max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( strtotime( $ecv2_rp_license->support_end_date ) ) : (int) round( ( strtotime( $ecv2_rp_license->support_end_date ) - time() ) / DAY_IN_SECONDS ) );
		$ecv2_rp_is_premium = ( 'ec410' === strtolower( trim( (string) $ecv2_rp_license->model_number ) ) );
		$ecv2_rp_is_trial   = ! empty( $ecv2_rp_license->is_trial );
		if ( $ecv2_rp_is_trial ) {
			$ecv2_rp_renew_url   = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $ecv2_rp_key;
			$ecv2_rp_upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $ecv2_rp_key . '&license_type=Premium';
		} else {
			$ecv2_rp_renew_url   = ( ! $ecv2_rp_is_premium ) ? 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . $ecv2_rp_key : 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $ecv2_rp_key;
			$ecv2_rp_upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $ecv2_rp_key;
		}
	}
}

/* One-line license strip. Only states that need attention render; an active license in good standing shows nothing. */
$ecv2_rp_strip = null;
if ( ! $ecv2_rp_license ) {
	$ecv2_rp_strip = array(
		'tone' => 'free',
		'icon' => 'dashicons-chart-area',
		/* translators: %s: plan name, "Pro/Premium". */
		'text' => sprintf( __( 'Free reports cover the Overview and Taxes. %s opens Products, Customers, Codes, Carts, Sources, Profit, Fulfillment, Searches and Subscriptions, with saved views and scheduled summaries.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
		'cta'  => array( wp_easycart_admin()->pro_install_url( 'trial', true ), __( 'Try Pro free', 'wp-easycart' ) ),
		'more' => true,
	);
} elseif ( $ecv2_rp_is_trial && $ecv2_rp_days_left > 0 ) {
	$ecv2_rp_strip = array(
		'tone' => 'trial',
		'icon' => 'dashicons-clock',
		/* translators: %d: days left. */
		'text' => sprintf( _n( '%d day left on your Pro trial.', '%d days left on your Pro trial.', $ecv2_rp_days_left, 'wp-easycart' ), $ecv2_rp_days_left ),
		'cta'  => array( $ecv2_rp_upgrade_url, __( 'Upgrade now', 'wp-easycart' ) ),
		'more' => false,
	);
} elseif ( $ecv2_rp_is_trial ) {
	$ecv2_rp_strip = array(
		'tone' => 'expired',
		'icon' => 'dashicons-warning',
		'text' => __( 'Your Pro trial has ended. Upgrade to reopen the Pro reports; nothing has been lost.', 'wp-easycart' ),
		'cta'  => array( 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/', __( 'Upgrade now', 'wp-easycart' ) ),
		'more' => false,
	);
} elseif ( $ecv2_rp_days_left <= 0 ) {
	$ecv2_rp_strip = array(
		'tone' => 'expired',
		'icon' => 'dashicons-warning',
		'text' => __( 'Your license has expired. Renew to keep updates and support.', 'wp-easycart' ),
		'cta'  => array( $ecv2_rp_renew_url, __( 'Renew', 'wp-easycart' ) ),
		'more' => false,
	);
} elseif ( $ecv2_rp_days_left < 100 ) {
	$ecv2_rp_strip = array(
		'tone' => 'trial',
		'icon' => 'dashicons-clock',
		/* translators: %d: days left. */
		'text' => sprintf( _n( '%d day of support and updates remaining.', '%d days of support and updates remaining.', $ecv2_rp_days_left, 'wp-easycart' ), $ecv2_rp_days_left ),
		'cta'  => array( $ecv2_rp_renew_url, __( 'Renew', 'wp-easycart' ) ),
		'more' => false,
	);
}

global $wpdb;
$ecv2_rp_countries = $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC' );
$ecv2_rp_card      = ( class_exists( 'wp_easycart_order_source' ) && wp_easycart_order_source::pro() );
?>
<div id="ec_admin_chart" class="ec_admin_chart_holder ec_admin_chart_holder_active ecv2-wrap ecrp ecrp3">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-chart-line ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Reports', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right ecrp-header-tools">
			<div class="ecrp3-menu" data-ecrp-menu="views">
				<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrp_views_btn" aria-haspopup="true" aria-expanded="false"><span class="dashicons dashicons-star-empty"></span> <?php esc_html_e( 'Views', 'wp-easycart' ); ?></button>
				<div class="ecrp3-menu-list" id="ecrp_views_menu" role="menu" hidden></div>
			</div>
			<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrp_summary_btn"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Summary email', 'wp-easycart' ); ?></button>
			<div class="ecrp3-menu" data-ecrp-menu="export">
				<button type="button" class="wpeasycart_admin_chart_export ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecrp_export_btn" aria-haspopup="true" aria-expanded="false"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export', 'wp-easycart' ); ?></button>
				<div class="ecrp3-menu-list is-right" id="ecrp_export_menu" role="menu" hidden>
					<button type="button" role="menuitem" data-ecrp-export="orders"><strong><?php esc_html_e( 'Orders and taxes', 'wp-easycart' ); ?></strong><span><?php esc_html_e( 'Every order in the range, and the tax report, as CSV files.', 'wp-easycart' ); ?></span></button>
					<button type="button" role="menuitem" data-ecrp-export="research"><strong><?php esc_html_e( 'Research export', 'wp-easycart' ); ?> <span class="ecrp3-chip" data-ecrp-research-chip></span></strong><span><?php esc_html_e( 'Orders, lines, payments, refunds, customers and daily store activity, for a spreadsheet or analysis tool.', 'wp-easycart' ); ?></span></button>
				</div>
			</div>
		</div>
	</div>

	<?php if ( $ecv2_rp_strip ) : ?>
	<div class="ecrp-strip is-<?php echo esc_attr( $ecv2_rp_strip['tone'] ); ?>">
		<span class="dashicons <?php echo esc_attr( $ecv2_rp_strip['icon'] ); ?>"></span>
		<span class="ecrp-strip-text"><?php echo esc_html( $ecv2_rp_strip['text'] ); ?></span>
		<?php if ( $ecv2_rp_strip['more'] && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
		<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2_upsell( { context: 'reports' } ); return false;"><?php esc_html_e( "See what's included", 'wp-easycart' ); ?></button>
		<?php endif; ?>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecv2_rp_strip['cta'][0] ); ?>"<?php echo ( 0 === strpos( $ecv2_rp_strip['cta'][0], 'http' ) ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $ecv2_rp_strip['cta'][1] ); ?></a>
	</div>
	<?php endif; ?>

	<?php /* ---- Toolbar: range, compare, grouping, chart type, filters ---- */ ?>
	<div class="ecrp-toolbar ecrp3-toolbar">
		<div class="ecrp-ranges">
			<div id="wpeasycart_admin_report_range1" class="ec_admin_dashboard_chart_range_button ecrp-range" role="button" tabindex="0" aria-haspopup="dialog">
				<div class="ecrp-range-label"><?php esc_html_e( 'Date range', 'wp-easycart' ); ?></div>
				<i class="dashicons dashicons-calendar"></i>
				<span id="ecrp_range_text"></span>
				<i class="dashicons dashicons-arrow-down"></i>
			</div>
			<label class="ecrp3-field">
				<span class="ecrp-range-label"><?php esc_html_e( 'Compare to', 'wp-easycart' ); ?></span>
				<select id="ecrp_compare" class="ecv2-select ecv2-select-sm">
					<option value="none"><?php esc_html_e( 'No comparison', 'wp-easycart' ); ?></option>
					<option value="previous"><?php esc_html_e( 'Previous period', 'wp-easycart' ); ?></option>
					<option value="year"><?php esc_html_e( 'Same dates last year', 'wp-easycart' ); ?></option>
				</select>
			</label>
			<label class="ecrp3-field">
				<span class="ecrp-range-label"><?php esc_html_e( 'Group by', 'wp-easycart' ); ?></span>
				<select id="daily_filter" class="ecv2-select ecv2-select-sm">
					<option value="daily"><?php esc_html_e( 'Day', 'wp-easycart' ); ?></option>
					<option value="weekly"><?php esc_html_e( 'Week', 'wp-easycart' ); ?></option>
					<option value="monthly"><?php esc_html_e( 'Month', 'wp-easycart' ); ?></option>
					<option value="yearly"><?php esc_html_e( 'Year', 'wp-easycart' ); ?></option>
				</select>
			</label>
			<?php /* The icon is a span inside the button: the admin shell gives every button font:inherit, which took the dashicons font off a button that was itself the icon. */ ?>
			<div class="wpeasycart_admin_chart_types ecrp-chart-types" role="group" aria-label="<?php esc_attr_e( 'Chart type', 'wp-easycart' ); ?>">
				<button type="button" class="ecrp3-chart-type wpeasycart_admin_chart_type_line" data-ecrp-chart="line" title="<?php esc_attr_e( 'Show charts as lines', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Show charts as lines', 'wp-easycart' ); ?>" aria-pressed="false"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span></button>
				<button type="button" class="ecrp3-chart-type wpeasycart_admin_chart_type_bar" data-ecrp-chart="bar" title="<?php esc_attr_e( 'Show charts as bars', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Show charts as bars', 'wp-easycart' ); ?>" aria-pressed="false"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span></button>
			</div>
		</div>
		<div class="ec_admin_dashboard_chart_filters ecrp-filters">
			<?php do_action( 'wp_easycart_reports_filters_pre' ); ?>
			<input type="hidden" id="product_filter" value="0" />
			<?php
			wp_easycart_admin::print_picker(
				array(
					'id'          => 'wpec_report_product_pick',
					'mode'        => 'product',
					'target'      => 'product_filter',
					'multiple'    => false,
					'placeholder' => __( 'All products', 'wp-easycart' ),
					'on_change'   => 'wpeasycart_admin_update_chart_data',
					'class'       => 'ecv2-input ecv2-input-sm',
				)
			);
			?>
			<select id="country_filter" class="ecv2-select ecv2-select-sm" aria-label="<?php esc_attr_e( 'Ships to', 'wp-easycart' ); ?>">
				<option value="0" selected="selected"><?php esc_html_e( 'Ships to: anywhere', 'wp-easycart' ); ?></option>
				<?php foreach ( (array) $ecv2_rp_countries as $ecv2_rp_country ) : ?>
				<option value="<?php echo esc_attr( $ecv2_rp_country->iso2_cnt ); ?>"><?php echo esc_html( $ecv2_rp_country->name_cnt ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="billing_country_filter" class="ecv2-select ecv2-select-sm" aria-label="<?php esc_attr_e( 'Billed in', 'wp-easycart' ); ?>">
				<option value="0" selected="selected"><?php esc_html_e( 'Billed in: anywhere', 'wp-easycart' ); ?></option>
				<?php foreach ( (array) $ecv2_rp_countries as $ecv2_rp_country ) : ?>
				<option value="<?php echo esc_attr( $ecv2_rp_country->iso2_cnt ); ?>"><?php echo esc_html( $ecv2_rp_country->name_cnt ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php do_action( 'wp_easycart_reports_filters_post' ); ?>
		</div>
	</div>

	<?php /* ---- Tabs ---- */ ?>
	<div class="ecrp3-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Reports', 'wp-easycart' ); ?>" id="ecrp_tabs">
		<?php foreach ( $ecv2_rp_config['tabs'] as $ecv2_rp_tab ) : ?>
		<button type="button" role="tab" class="ecrp3-tab<?php echo ( 'free' === $ecv2_rp_tab['state'] || 'enabled' === $ecv2_rp_tab['state'] ) ? '' : ' is-locked'; ?>" id="ecrp_tab_<?php echo esc_attr( $ecv2_rp_tab['id'] ); ?>" data-tab="<?php echo esc_attr( $ecv2_rp_tab['id'] ); ?>" aria-controls="ecrp_panel" aria-selected="<?php echo 'overview' === $ecv2_rp_tab['id'] ? 'true' : 'false'; ?>" tabindex="<?php echo 'overview' === $ecv2_rp_tab['id'] ? '0' : '-1'; ?>" title="<?php echo esc_attr( $ecv2_rp_tab['desc'] ); ?>">
			<?php echo esc_html( $ecv2_rp_tab['label'] ); ?>
			<?php if ( '' !== $ecv2_rp_tab['badge'] ) : ?>
			<span class="ecrp3-chip<?php echo 'update' === $ecv2_rp_tab['state'] ? ' is-update' : ''; ?>"><?php echo esc_html( $ecv2_rp_tab['badge'] ); ?></span>
			<?php endif; ?>
		</button>
		<?php endforeach; ?>
	</div>

	<?php /* ---- Summary email offer ( D4: one click, off until then ) and the store activity note ---- */ ?>
	<div class="ecrp3-offer" id="ecrp_offer" hidden>
		<span class="dashicons dashicons-email-alt"></span>
		<span class="ecrp3-offer-text"><?php esc_html_e( 'Get these numbers by email every week: sales, orders, refunds and new customers, compared with the week before.', 'wp-easycart' ); ?></span>
		<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecrp-offer="on"><?php esc_html_e( 'Turn on', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecrp-offer="dismiss"><?php esc_html_e( 'No thanks', 'wp-easycart' ); ?></button>
	</div>

	<div class="ecrp3-panel" id="ecrp_panel" role="tabpanel" aria-labelledby="ecrp_tab_overview" aria-live="polite"></div>

	<?php if ( $ecv2_rp_card ) : ?>
	<div id="ecrp_sources_card" class="ecrp3-sources-card" hidden>
		<?php wp_easycart_order_source::reports_card(); ?>
	</div>
	<?php endif; ?>

	<script type="application/json" id="ecrp_config"><?php echo wp_json_encode( $ecv2_rp_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with the HEX flags. ?></script>
</div>

<?php do_action( 'wp_easycart_admin_dashboard_post' ); ?>

<script type="text/javascript">
/* The export dialog and job. Extensions add rows through wp_easycart_dashboard_reports_links_start / _end: JavaScript that
	appends to `body` or to `modal`. */
function wpeasycart_admin_report_download_modal( reports ){
	var esc = function( s ){ return jQuery( '<span>' ).text( s == null ? '' : s ).html(); };
	var icon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3v5h5"/><path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M12 12v5"/><path d="m9.5 14.5 2.5 2.5 2.5-2.5"/></svg>';
	var row = function( url, title, sub ){
		return '<a class="ecrp-dl-row" href="' + esc( url ) + '" download>' +
			'<span class="ecrp-dl-ic">' + icon + '</span>' +
			'<span class="ecrp-dl-text"><b>' + esc( title ) + '</b><span>' + esc( sub ) + '</span></span>' +
			'<span class="ecrp-dl-go"><?php echo esc_js( __( 'Download CSV', 'wp-easycart' ) ); ?></span></a>';
	};
	var modal = '';
	var body = '<p class="ecrp-dl-lede"><?php echo esc_js( __( 'Your export is ready. Each file covers the filters and dates set on this page.', 'wp-easycart' ) ); ?></p><div class="ecrp-dl-rows">';
	<?php do_action( 'wp_easycart_dashboard_reports_links_start' ); ?>
	body += row( reports.report1, '<?php echo esc_js( __( 'Main report', 'wp-easycart' ) ); ?>', '<?php echo esc_js( __( 'Every order in the selected range, with totals, fees and taxes.', 'wp-easycart' ) ); ?>' );
	if( reports.report2 ){
		body += row( reports.report2, '<?php echo esc_js( __( 'Compare range report', 'wp-easycart' ) ); ?>', '<?php echo esc_js( __( 'The same columns for the range you are comparing against.', 'wp-easycart' ) ); ?>' );
	}
	body += row( reports.reporttax, '<?php echo esc_js( __( 'Tax report', 'wp-easycart' ) ); ?>', '<?php echo esc_js( __( 'Tax collected per order, grouped the way filings ask for it.', 'wp-easycart' ) ); ?>' );
	<?php do_action( 'wp_easycart_dashboard_reports_links_end' ); ?>
	body += modal;
	body += '</div>';

	jQuery( '#ecrp_export_modal' ).remove();
	var $m = jQuery(
		'<div class="ecv2-modal-overlay ecrp-dl" id="ecrp_export_modal" role="dialog" aria-modal="true" aria-labelledby="ecrp_export_title">' +
			'<div class="ecv2-modal ecrp-dl-modal">' +
				'<div class="ecv2-modal-header"><h2 id="ecrp_export_title"><?php echo esc_js( __( 'Export reports', 'wp-easycart' ) ); ?></h2>' +
				'<button type="button" class="ecv2-modal-close" data-close aria-label="<?php echo esc_js( __( 'Close', 'wp-easycart' ) ); ?>">&times;</button></div>' +
				'<div class="ecv2-modal-body">' + body + '</div>' +
				'<div class="ecv2-modal-footer"><div class="ecv2-modal-footer-right">' +
					'<button type="button" class="ecv2-btn" data-close><?php echo esc_js( __( 'Done', 'wp-easycart' ) ); ?></button>' +
				'</div></div>' +
			'</div>' +
		'</div>'
	);
	var close = function(){ jQuery( document ).off( 'keydown.ecrpdl' ); $m.remove(); };
	jQuery( 'body' ).append( $m );
	$m.on( 'click', function( e ){ if( jQuery( e.target ).is( $m ) || jQuery( e.target ).is( '[data-close]' ) ){ close(); } } );
	jQuery( document ).on( 'keydown.ecrpdl', function( e ){ if( 'Escape' === e.key ){ close(); } } );
	setTimeout( function(){ $m.find( '.ecrp-dl-row' ).first().trigger( 'focus' ); }, 30 );
}
function wpeasycart_admin_export_report( ){
	var state = ( window.wpecReports && window.wpecReports.state ) ? window.wpecReports.state : {};
	var button = jQuery( '#ecrp_export_btn' );
	button.find( '.dashicons' ).removeClass( 'dashicons-download' ).addClass( 'dashicons-image-rotate' );
	var data = {
		action: 'ec_admin_create_report_export',
		start_date: state.start,
		end_date: state.end,
		start_date2: state.start2 || 0,
		end_date2: state.end2 || 0,
		range: jQuery( '#daily_filter' ).val( ),
		product: jQuery( '#product_filter' ).val( ),
		country: jQuery( '#country_filter' ).val( ),
		billing_country: jQuery( '#billing_country_filter' ).val( ),
		location_id: ( jQuery( '#location_filter' ).length ) ? jQuery( '#location_filter' ).val() : 0,
		wp_easycart_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-export-stats' ) ); ?>'
	};
	var export_finish = function( ){
		button.find( '.dashicons' ).removeClass( 'dashicons-image-rotate' ).addClass( 'dashicons-download' );
		button.removeAttr( 'title' );
	};
	var export_fail = function( message ){
		export_finish( );
		window.alert( message || '<?php echo esc_js( __( 'The export could not be completed. Please try again.', 'wp-easycart' ) ); ?>' );
	};
	var export_step = function( cursor ){
		var step_data = jQuery.extend( {}, data, cursor || {} );
		jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: step_data, dataType: 'json', success: function( response ){
			if( !response || response.error ){
				export_fail( response && response.message ? response.message : '' );
				return;
			}
			if( !response.done ){
				button.attr( 'title', '<?php echo esc_js( __( 'Exporting…', 'wp-easycart' ) ); ?> ' + response.total );
				export_step( { job: response.next.job, phase: response.next.phase, last_order_id: response.next.last_order_id } );
				return;
			}
			export_finish( );
			wpeasycart_admin_report_download_modal( response.reports );
		}, error: function( ){
			export_fail( '' );
		} } );
	};
	export_step( null );
}
</script>
