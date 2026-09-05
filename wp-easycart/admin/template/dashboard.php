<?php
/**
 * Reports ( V2 ).
 *
 * Markup rebuilt on the V2 tokens; every id / class the inline chart script
 * depends on is preserved verbatim ( range buttons, filters, stat cells,
 * chart canvases, export and chart-type controls ), so the script below is
 * unchanged. Free installs get the standard upsell line instead of the
 * legacy status bubble.
 */
?>
<?php do_action( 'wp_easycart_admin_dashboard_pre_chart' ); ?>
<?php $single_stats = wp_easycart_admin()->get_single_stats( date( 'Y-m-d', strtotime( '-13 days' ) ), date( 'Y-m-d' ) ); ?>
<?php
$status = new wp_easycart_admin_store_status();
$license_data = false; $days_left = 0; $is_premium = false; $is_trial = false; $transaction_key = '';
$renew_url = 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
$upgrade_url = 'https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/';
if ( function_exists( 'wp_easycart_admin_license' ) ) {
	$license_data    = wp_easycart_admin_license()->license_data;
	$license_info    = get_option( 'wp_easycart_license_info' );
	$transaction_key = ( is_array( $license_info ) && isset( $license_info['transaction_key'] ) ) ? $license_info['transaction_key'] : '';
	$days_left       = max( 0, class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::days_until( strtotime( $license_data->support_end_date ) ) : (int) round( ( strtotime( $license_data->support_end_date ) - time() ) / DAY_IN_SECONDS ) );
	$is_premium      = ( 'ec410' === strtolower( trim( (string) $license_data->model_number ) ) );
	$is_trial        = ! empty( $license_data->is_trial );
	if ( $is_trial ) {
		$renew_url   = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $transaction_key;
		$upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . $transaction_key . '&license_type=Premium';
	} else {
		$renew_url   = ( ! $is_premium ) ? 'https://www.wpeasycart.com/products/wp-easycart-professional-support-upgrades/?transaction_key=' . $transaction_key : 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $transaction_key;
		$upgrade_url = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/?transaction_key=' . $transaction_key;
	}
}
$ecrp_stats = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::stats() : array();

/* One-line license strip. Only states that need attention render; an active license in good standing shows nothing. */
$ecrp_strip = null;
if ( ! $license_data ) {
	$ecrp_strip = array( 'tone' => 'free', 'icon' => 'dashicons-chart-area',
		'text' => __( 'Free edition. These reports cover the basics; PRO adds product, customer and coupon breakdowns, the abandoned-cart recovery report and scheduled email summaries.', 'wp-easycart' ),
		'cta' => array( 'admin.php?page=wp-easycart-registration&ec_trial=start', __( 'Try PRO free', 'wp-easycart' ) ), 'more' => true );
} else if ( $is_trial && $days_left > 0 ) {
	$ecrp_strip = array( 'tone' => 'trial', 'icon' => 'dashicons-clock', 'text' => sprintf( _n( '%d day left on your PRO trial.', '%d days left on your PRO trial.', $days_left, 'wp-easycart' ), $days_left ), 'cta' => array( $upgrade_url, __( 'Upgrade now', 'wp-easycart' ) ), 'more' => false );
} else if ( $is_trial ) {
	$ecrp_strip = array( 'tone' => 'expired', 'icon' => 'dashicons-warning', 'text' => __( 'Your PRO trial has ended. Upgrade to reopen the PRO panels; nothing has been lost.', 'wp-easycart' ), 'cta' => array( 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/', __( 'Upgrade now', 'wp-easycart' ) ), 'more' => false );
} else if ( $days_left <= 0 ) {
	$ecrp_strip = array( 'tone' => 'expired', 'icon' => 'dashicons-warning', 'text' => __( 'Your license has expired. Renew to keep updates and support.', 'wp-easycart' ), 'cta' => array( $renew_url, __( 'Renew', 'wp-easycart' ) ), 'more' => false );
} else if ( $days_left < 100 ) {
	$ecrp_strip = array( 'tone' => 'trial', 'icon' => 'dashicons-clock', 'text' => sprintf( _n( '%d day of support and updates remaining.', '%d days of support and updates remaining.', $days_left, 'wp-easycart' ), $days_left ), 'cta' => array( $renew_url, __( 'Renew', 'wp-easycart' ) ), 'more' => false );
}

global $wpdb;
$products  = $wpdb->get_results( 'SELECT ec_product.title, ec_product.product_id FROM ec_product ORDER BY ec_product.title ASC LIMIT 500' );
$countries = $wpdb->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC' );

/* Stat cards: [ id, label, value, money? ] — ids 1–10 are fixed ( the chart script updates them by number ). */
$ecrp_cards = array(
	array( 1,  __( 'Total payments', 'wp-easycart' ),   $single_stats->gross_revenue->set1, true ),
	array( 6,  __( 'Net revenue', 'wp-easycart' ),      $single_stats->net_revenue->set1,   true ),
	array( 7,  __( 'Orders', 'wp-easycart' ),           $single_stats->orders->set1,        false ),
	array( 8,  __( 'Items sold', 'wp-easycart' ),       $single_stats->items->set1,         false ),
	array( 9,  __( 'Unique customers', 'wp-easycart' ), $single_stats->customers->set1,     false ),
	array( 10, __( 'Abandoned carts', 'wp-easycart' ),  $single_stats->carts->set1,         false ),
	array( 2,  __( 'Shipping', 'wp-easycart' ),         $single_stats->shipping->set1,      true ),
	array( 3,  __( 'Taxes', 'wp-easycart' ),            $single_stats->tax->set1,           true ),
	array( 4,  __( 'Discounts', 'wp-easycart' ),        $single_stats->discount->set1,      true ),
	array( 5,  __( 'Refunds', 'wp-easycart' ),          $single_stats->refund->set1,        true ),
);
?>
<div id="ec_admin_chart" class="ec_admin_chart_holder ec_admin_chart_holder_active ecv2-wrap ecrp">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-chart-line ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Reports', 'wp-easycart' ); ?></h2>
		</div>
		<div class="ecv2-page-header-right ecrp-header-tools">
			<?php /* Chart type + granularity: classes / ids are bound by the chart script. */ ?>
			<div class="wpeasycart_admin_chart_types ecrp-chart-types" role="group" aria-label="<?php esc_attr_e( 'Chart type', 'wp-easycart' ); ?>">
				<span class="dashicons dashicons-chart-line wpeasycart_admin_chart_type_line selected" onclick="wpeasycart_admin_update_chart_type( 'line' );" title="<?php esc_attr_e( 'Line', 'wp-easycart' ); ?>"></span>
				<span class="dashicons dashicons-chart-bar wpeasycart_admin_chart_type_bar" onclick="wpeasycart_admin_update_chart_type( 'bar' );" title="<?php esc_attr_e( 'Bars', 'wp-easycart' ); ?>"></span>
			</div>
			<select id="daily_filter" class="ecv2-select ecv2-select-sm" onchange="wpeasycart_admin_update_chart_data( );">
				<option value="daily" selected="selected"><?php esc_html_e( 'Daily', 'wp-easycart' ); ?></option>
				<option value="weekly"><?php esc_html_e( 'Weekly', 'wp-easycart' ); ?></option>
				<option value="monthly"><?php esc_html_e( 'Monthly', 'wp-easycart' ); ?></option>
				<option value="yearly"><?php esc_html_e( 'Yearly', 'wp-easycart' ); ?></option>
			</select>
			<div class="wpeasycart_admin_chart_export ecv2-btn ecv2-btn-primary ecv2-btn-sm" onclick="wpeasycart_admin_export_report( );" role="button" tabindex="0">
				<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'wp-easycart' ); ?>
			</div>
		</div>
	</div>

	<?php if ( $ecrp_strip ) : ?>
	<div class="ecrp-strip is-<?php echo esc_attr( $ecrp_strip['tone'] ); ?>">
		<span class="dashicons <?php echo esc_attr( $ecrp_strip['icon'] ); ?>"></span>
		<span class="ecrp-strip-text"><?php echo esc_html( $ecrp_strip['text'] ); ?></span>
		<?php if ( $ecrp_strip['more'] && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
		<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2_upsell( { context: 'default' } ); return false;"><?php esc_html_e( "See what's included", 'wp-easycart' ); ?></button>
		<?php endif; ?>
		<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="<?php echo esc_url( $ecrp_strip['cta'][0] ); ?>"<?php echo ( 0 === strpos( $ecrp_strip['cta'][0], 'http' ) ) ? ' target="_blank"' : ''; ?>><?php echo esc_html( $ecrp_strip['cta'][1] ); ?></a>
	</div>
	<?php endif; ?>

	<?php /* ---- Toolbar: ranges + filters ---- */ ?>
	<div class="ecrp-toolbar">
		<div class="ecrp-ranges">
			<div id="wpeasycart_admin_report_range1" class="ec_admin_dashboard_chart_range_button ecrp-range">
				<div class="ecrp-range-label"><?php esc_html_e( 'Date range', 'wp-easycart' ); ?></div>
				<i class="dashicons dashicons-calendar"></i>&nbsp;
				<span></span>
				<i class="dashicons dashicons-arrow-down"></i>
			</div>
			<div id="wpeasycart_admin_report_range2" class="ec_admin_dashboard_chart_range_button ecrp-range ecrp-range-compare">
				<div class="ecrp-range-label"><?php esc_html_e( 'Compare to', 'wp-easycart' ); ?></div>
				<i class="dashicons dashicons-calendar"></i>&nbsp;
				<span></span>
				<i class="dashicons dashicons-arrow-down"></i>
			</div>
		</div>
		<div class="ec_admin_dashboard_chart_filters ecrp-filters">
			<?php do_action( 'wp_easycart_admin_reports_filters_pre' ); ?>
			<?php if ( count( $products ) >= 500 ) : ?>
				<input type="text" class="ecv2-input ecv2-input-sm" name="product_filter" placeholder="<?php esc_attr_e( 'Product ID', 'wp-easycart' ); ?>" value="" onkeydown="wpeasycart_admin_update_chart_data( );" />
			<?php else : ?>
				<select id="product_filter" class="ecv2-select ecv2-select-sm" onchange="wpeasycart_admin_update_chart_data( );">
					<option value="0" selected="selected"><?php esc_html_e( 'All products', 'wp-easycart' ); ?></option>
					<?php foreach ( $products as $product ) : ?>
					<option value="<?php echo esc_attr( $product->product_id ); ?>"><?php echo esc_html( $product->title ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<select id="country_filter" class="ecv2-select ecv2-select-sm" onchange="wpeasycart_admin_update_chart_data( );">
				<option value="0" selected="selected"><?php esc_html_e( 'Ships to: anywhere', 'wp-easycart' ); ?></option>
				<?php foreach ( $countries as $country ) : ?>
				<option value="<?php echo esc_attr( $country->iso2_cnt ); ?>"><?php echo esc_html( $country->name_cnt ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="billing_country_filter" class="ecv2-select ecv2-select-sm" onchange="wpeasycart_admin_update_chart_data( );">
				<option value="0" selected="selected"><?php esc_html_e( 'Billed in: anywhere', 'wp-easycart' ); ?></option>
				<?php foreach ( $countries as $country ) : ?>
				<option value="<?php echo esc_attr( $country->iso2_cnt ); ?>"><?php echo esc_html( $country->name_cnt ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php do_action( 'wp_easycart_admin_reports_filters_post' ); ?>
		</div>
	</div>

	<?php /* ---- Stat cards. Inner structure is what the chart script updates. ---- */ ?>
	<div class="ec_admin_dashboard_stat_items ecrp-stats">
		<?php foreach ( $ecrp_cards as $c ) : ?>
		<div class="ec_admin_dashboard_stat_item ecrp-stat<?php echo $c[3] ? ' is-money' : ''; ?>" id="ec_admin_dashboard_stat_item<?php echo (int) $c[0]; ?>">
			<div class="ec_admin_dashboard_stat_item_title"><?php echo esc_html( $c[1] ); ?></div>
			<div class="ec_admin_dashboard_stat_item_total"><?php echo esc_html( $c[2] ); ?></div>
			<div class="ec_admin_dashboard_stat_item_change decrease" style="display:none"><span class="dashicons dashicons-arrow-down-alt"></span> -36.3%</div>
			<div class="ec_admin_dashboard_stat_item_prev_total" style="display:none"><?php esc_html_e( 'Previous period', 'wp-easycart' ); ?><br />$0.00</div>
		</div>
		<?php endforeach; ?>
		<?php for ( $i = 0; $i < count( $single_stats->fees ); $i++ ) : ?>
		<div class="ec_admin_dashboard_stat_item ecrp-stat is-money is-fee" id="ec_admin_dashboard_stat_item<?php echo esc_attr( 11 + $i ); ?>">
			<div class="ec_admin_dashboard_stat_item_title"><?php echo esc_html( '' !== trim( (string) $single_stats->fees[ $i ]->fee_label ) && '0' !== trim( (string) $single_stats->fees[ $i ]->fee_label ) ? $single_stats->fees[ $i ]->fee_label : __( 'Fee', 'wp-easycart' ) ); ?></div>
			<div class="ec_admin_dashboard_stat_item_total"><?php echo esc_html( $single_stats->fees[ $i ]->set1 ); ?></div>
			<div class="ec_admin_dashboard_stat_item_change decrease" style="display:none"><span class="dashicons dashicons-arrow-down-alt"></span> -36.3%</div>
			<div class="ec_admin_dashboard_stat_item_prev_total" style="display:none"><?php esc_html_e( 'Previous period', 'wp-easycart' ); ?><br />$0.00</div>
		</div>
		<?php endfor; ?>
	</div>

	<?php /* ---- Charts ---- */ ?>
	<div class="ecrp-charts">
		<div class="ec_admin_dashboard_chart ecrp-chart"><canvas id="ec_admin_chart_data_1" class="ec_admin_chart"></canvas></div>
		<div class="ec_admin_dashboard_chart ecrp-chart"><canvas id="ec_admin_chart_data_2" class="ec_admin_chart"></canvas></div>
		<div class="ec_admin_dashboard_chart ecrp-chart"><canvas id="ec_admin_chart_data_3" class="ec_admin_chart"></canvas></div>
	</div>

	<?php if ( ! $license_data && class_exists( 'wp_easycart_admin_upsell' ) ) : ?>
	<?php /* Free edition: what PRO reporting adds, in the standard clickable strip. */ ?>
	<div class="ecrp-upsell">
		<?php
		$ecrp_e = wp_easycart_admin_upsell::entry( 'reports' );
		if ( '' !== $ecrp_e['stat_line'] ) {
			echo '<p class="ecv2-page-intro ecv2-upsell-stat-inline"><span class="dashicons dashicons-chart-line"></span> ' . esc_html( $ecrp_e['stat_line'] ) . '</p>';
		}
		wp_easycart_admin_upsell::print_feature_strip( 'reports' );
		?>
	</div>
	<?php endif; ?>

</div>

<?php do_action( 'wp_easycart_admin_dashboard_post' ); ?>

<script type="text/javascript">
function get_currency_display( amount ){
	var display_amount = '';
	var show_currency_code = <?php echo ( $GLOBALS['currency']->get_symbol_location( ) ) ? 1 : 0; ?>;
	var currency_code = '<?php echo esc_attr( $GLOBALS['currency']->get_currency_code( ) ); ?>';
	var negative_location = <?php echo ( $GLOBALS['currency']->get_negative_location( ) ) ? 1 : 0; ?>;
	var symbol_location = <?php echo ( $GLOBALS['currency']->get_symbol_location( ) ) ?  1 : 0; ?>;
	var symbol = '<?php echo esc_attr( $GLOBALS['currency']->get_symbol( ) ); ?>';
	var decimal_length = <?php echo esc_attr( $GLOBALS['currency']->get_decimal_length( ) ); ?>;
	var decimal_symbol = '<?php echo esc_attr( $GLOBALS['currency']->get_decimal_symbol( ) ); ?>';
	var grouping_symbol = '<?php echo esc_attr( $GLOBALS['currency']->get_grouping_symbol( ) ); ?>';
	if( show_currency_code )
		display_amount += currency_code + ' ';
	if( amount < 0 && negative_location )
		display_amount += '-';
	if( symbol_location )
		display_amount += symbol;
	if( amount < 0 && !negative_location )
		display_amount += '-';
	if( amount < 0 )
		amount = amount * -1;
	display_amount += ec_admin_chart_number_format( amount, decimal_length, decimal_symbol, grouping_symbol );
	if( !symbol_location )
		display_amount += symbol;
	return display_amount;
}
function ec_admin_chart_number_format( number, decimals, dec_point, thousands_sep ){
	number = ( number + '' ).replace( /[^0-9+\-Ee.]/g, '' );
	var n = !isFinite( +number ) ? 0 : +number,
		prec = !isFinite( +decimals ) ? 0 : Math.abs( decimals ),
		sep = ( typeof thousands_sep === 'undefined' ) ? ',' : thousands_sep,
		dec = ( typeof dec_point === 'undefined' ) ? '.' : dec_point,
		s = '',
		toFixedFix = function ( n, prec ){
			var k = Math.pow( 10, prec );
			return '' + Math.round( n * k ) / k;
		};
	s = ( prec ? toFixedFix( n, prec ) : '' + Math.round( n ) ).split( '.' );
	if( s[0].length > 3 ){
		s[0] = s[0].replace( /\B(?=(?:\d{3})+(?!\d))/g, sep );
	}
	if( ( s[1] || '' ).length < prec ){
		s[1] = s[1] || '';
		s[1] += new Array( prec - s[1].length + 1 ).join( '0' );
	}
	return s.join( dec );
}
var start1 = moment( ).subtract( 13, 'days' );
var end1 = moment( );
function wpeasycart_admin_report_cb1( start, end ){
	jQuery( '#wpeasycart_admin_report_range1 span' ).html( start.format( 'MMMM D, YYYY' ) + ' - ' + end.format( 'MMMM D, YYYY' ) );
}
jQuery( '#wpeasycart_admin_report_range1' ).daterangepicker( {
	chosenLabel: 'Last 14 Days',
	startDate: start1,
	endDate: end1,
	ranges: {
	   '<?php esc_attr_e( 'Today', 'wp-easycart' ); ?>': [moment(), moment()],
	   '<?php esc_attr_e( 'Yesterday', 'wp-easycart' ); ?>': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
	   '<?php esc_attr_e( 'Last 7 Days', 'wp-easycart' ); ?>': [moment().subtract(6, 'days'), moment()],
	   '<?php esc_attr_e( 'Last 14 Days', 'wp-easycart' ); ?>': [moment().subtract(13, 'days'), moment()],
	   '<?php esc_attr_e( 'Last 30 Days', 'wp-easycart' ); ?>': [moment().subtract(29, 'days'), moment()],
	   '<?php esc_attr_e( 'This Month', 'wp-easycart' ); ?>': [moment().startOf('month'), moment().endOf('month')],
	   '<?php esc_attr_e( 'Last Month', 'wp-easycart' ); ?>': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
	   '<?php esc_attr_e( 'Last 3 Months', 'wp-easycart' ); ?>': [moment().subtract(2, 'month').startOf('month'), moment().endOf('month')],
	   '<?php esc_attr_e( 'Last 6 Months', 'wp-easycart' ); ?>': [moment().subtract(5, 'month').startOf('month'), moment().endOf('month')],
	   '<?php esc_attr_e( 'Last 12 Months', 'wp-easycart' ); ?>': [moment().subtract(11, 'month').startOf('month'), moment().endOf('month')],
	   '<?php esc_attr_e( 'This Quarter', 'wp-easycart' ); ?>': [moment().startOf('quarter'), moment().endOf('quarter')],
	   '<?php esc_attr_e( 'Last Quarter', 'wp-easycart' ); ?>': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')],
	   '<?php esc_attr_e( 'This Year', 'wp-easycart' ); ?>': [moment().startOf('year'), moment().endOf('year')],
	   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
	   '<?php esc_attr_e( 'Last 2 Years', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').startOf('year'), moment().endOf('year')],
	   '<?php esc_attr_e( 'Last 3 Years', 'wp-easycart' ); ?>': [moment().subtract(2, 'year').startOf('year'), moment().endOf('year')],
	   '<?php esc_attr_e( 'Last 5 Years', 'wp-easycart' ); ?>': [moment().subtract(4, 'year').startOf('year'), moment().endOf('year')]
	}
}, wpeasycart_admin_report_cb1 ).on( 'apply.daterangepicker', function( ev, picker ){
	wpeasycart_admin_report_update_range2( );
	wpeasycart_admin_update_chart_data( );
} );
wpeasycart_admin_report_cb1( start1, end1 );
jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').chosenLabel = 'Last 14 Days';
var start2 = moment().add(1, 'days');
var end2 = moment().add(1, 'days');
function wpeasycart_admin_report_cb2( start, end ){
	var selected_range = jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel;
	if( selected_range == '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2 span' ).html( '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' );
	}else{
		jQuery( '#wpeasycart_admin_report_range2 span' ).html( start.format( 'MMMM D, YYYY' ) + ' - ' + end.format( 'MMMM D, YYYY' ) );
	}
}
wpeasycart_admin_report_update_range2( );
wpeasycart_admin_report_cb2( start2, end2 );
function wpeasycart_admin_report_update_range2( ){
	var selected_range = jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').chosenLabel;
	if( selected_range == '<?php esc_attr_e( 'Last 7 Days', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(13, 'days'), moment().subtract(7, 'days')],
			   '<?php esc_attr_e( 'Last Month', 'wp-easycart' ); ?>': [moment().subtract(1, 'month').subtract( 6, 'days' ), moment().subtract(1, 'month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract( 6, 'days' ), moment().subtract(1, 'year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 14 Days', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(27, 'days'), moment().subtract(14, 'days')],
			   '<?php esc_attr_e( 'Last Month', 'wp-easycart' ); ?>': [moment().subtract(1, 'month').subtract( 13, 'days' ), moment().subtract(1, 'month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract( 13, 'days' ), moment().subtract(1, 'year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 30 Days', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(59, 'days'), moment().subtract(30, 'days')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(29, 'days'), moment().subtract(1, 'year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'This Month', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').startOf('month'), moment().subtract(1, 'year').endOf('month')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last Month', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(2, 'month').startOf('month'), moment().subtract(2, 'month').endOf('month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(1, 'month').startOf('month'), moment().subtract(1, 'year').subtract(1, 'month').endOf('month')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 3 Months', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(5, 'month').startOf('month'), moment().subtract(3, 'month').endOf('month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(2, 'month').startOf('month'), moment().subtract(1, 'year').endOf('month')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 6 Months', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(11, 'month').startOf('month'), moment().subtract(6, 'month').endOf('month')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(5, 'month').startOf('month'), moment().subtract(1, 'year').endOf('month')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 12 Months', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(23, 'month').startOf('month'), moment().subtract(12, 'month').endOf('month')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'This Quarter', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').startOf('quarter'), moment().subtract(1, 'year').endOf('quarter')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last Quarter', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(2, 'quarter').startOf('quarter'), moment().subtract(2, 'quarter').endOf('quarter')],
			   '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'year').subtract(1, 'quarter').endOf('quarter')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'This Year', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last Year', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(2, 'year').startOf('year'), moment().subtract(2, 'year').endOf('year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 2 Years', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(3, 'year').startOf('year'), moment().subtract(2, 'year').endOf('year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 3 Years', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(5, 'year').startOf('year'), moment().subtract(3, 'year').endOf('year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else if( selected_range == '<?php esc_attr_e( 'Last 5 Years', 'wp-easycart' ); ?>' ){
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Previous Period', 'wp-easycart' ); ?>': [moment().subtract(9, 'year').startOf('year'), moment().subtract(5, 'year').endOf('year')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}else{
		jQuery( '#wpeasycart_admin_report_range2' ).daterangepicker( {
			chosenLabel: '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>',
			startDate: start2,
			endDate: end2,
			ranges: {
			   '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>': [moment().add(1, 'days'), moment().add(1, 'days')],
			   '<?php esc_attr_e( 'Yesterday', 'wp-easycart' ); ?>': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
			   '<?php esc_attr_e( 'Last 7 Days, Previous Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract( 6, 'days' ), moment().subtract(1, 'year')],
			   '<?php esc_attr_e( 'Last Month', 'wp-easycart' ); ?>': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
			   '<?php esc_attr_e( 'Last 3 Months, Previous Year', 'wp-easycart' ); ?>': [moment().subtract(1, 'year').subtract(3, 'month').startOf('month'), moment().subtract(1, 'year').endOf('month')],
			   '<?php esc_attr_e( 'Last Quarter', 'wp-easycart' ); ?>': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')]
			}
		}, wpeasycart_admin_report_cb2 ).on( 'apply.daterangepicker', function( ev, picker ){
			wpeasycart_admin_update_chart_data( );
		} );
	}
	jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel = '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>';
	jQuery( '#wpeasycart_admin_report_range2 span' ).html( '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' );
}
var dashboard_data_sales = <?php echo wp_easycart_admin( )->get_stats( 'sales', esc_attr( date( 'Y-m-d', strtotime( '-14 days' ) ) ), esc_attr( date( 'Y-m-d' ) ) ); // Printing Pre-Escaped JSON Encoded Data ?>;
var dashboard_data_items = <?php echo wp_easycart_admin( )->get_stats( 'items', esc_attr( date( 'Y-m-d', strtotime( '-14 days' ) ) ), esc_attr( date( 'Y-m-d' ) ) ); // Printing Pre-Escaped JSON Encoded Data ?>;
var dashboard_data_abandoned = <?php echo wp_easycart_admin( )->get_stats( 'carts', esc_attr( date( 'Y-m-d', strtotime( '-14 days' ) ) ), esc_attr( date( 'Y-m-d' ) ) ); // Printing Pre-Escaped JSON Encoded Data ?>;
var options_sales = {
	scaleBeginAtZero : true,
	scaleShowGridLines : true,
	scaleGridLineColor : "rgba(0,0,0,.90)",
	scaleGridLineWidth : 1,
	scaleShowHorizontalLines: true,
	scaleShowVerticalLines: true,
	barShowStroke : true,
	barStrokeWidth : 2,
	barValueSpacing : 5,
	barDatasetSpacing : 1,
	cubicInterpolationMode: 'default',
	bezierCurve: false,
	lineTension: 0,
	tooltips: {
		enabled: true,
		mode: 'single',
		callbacks: {
			title: function( tooltipItems, data ){
				return data.datasets[tooltipItems[0].datasetIndex].datalabels[tooltipItems[0].index];
			},
			label: function( tooltipItems, data ){
				return get_currency_display( tooltipItems.yLabel );
			}
		}
	},
	elements: {
		line: {
			tension: 0
		}
	}
};
var options_items = {
	scaleBeginAtZero : true,
	scaleShowGridLines : true,
	scaleGridLineColor : "rgba(0,0,0,.90)",
	scaleGridLineWidth : 1,
	scaleShowHorizontalLines: true,
	scaleShowVerticalLines: true,
	barShowStroke : true,
	barStrokeWidth : 2,
	barValueSpacing : 5,
	barDatasetSpacing : 1,
	cubicInterpolationMode: 'default',
	bezierCurve: false,
	lineTension: 0,
	tooltips: {
		enabled: true,
		mode: 'single',
		callbacks: {
			title: function( tooltipItems, data ){
				return data.datasets[tooltipItems[0].datasetIndex].datalabels[tooltipItems[0].index];
			},
			label: function( tooltipItems, data ){
				return tooltipItems.yLabel + ' <?php esc_attr_e( 'Items', 'wp-easycart' ); ?>';
			}
		}
	},
	elements: {
		line: {
			tension: 0
		}
	}
};
var options_carts = {
	scaleBeginAtZero : true,
	scaleShowGridLines : true,
	scaleGridLineColor : "rgba(0,0,0,.90)",
	scaleGridLineWidth : 1,
	scaleShowHorizontalLines: true,
	scaleShowVerticalLines: true,
	barShowStroke : true,
	barStrokeWidth : 2,
	barValueSpacing : 5,
	barDatasetSpacing : 1,
	cubicInterpolationMode: 'default',
	bezierCurve: false,
	lineTension: 0,
	tooltips: {
		enabled: true,
		mode: 'single',
		callbacks: {
			title: function( tooltipItems, data ){
				return data.datasets[tooltipItems[0].datasetIndex].datalabels[tooltipItems[0].index];
			},
			label: function( tooltipItems, data ){
				return tooltipItems.yLabel + ' <?php esc_attr_e( 'Abandoned Carts', 'wp-easycart' ); ?>';
			}
		}
	},
	elements: {
		line: {
			tension: 0
		}
	}
};
var ctx_1 = 'ec_admin_chart_data_1';
var chart1 = new Chart( ctx_1, {
	type: 'line',
	data: dashboard_data_sales,
	options: options_sales
} );
var ctx_2 = 'ec_admin_chart_data_2';
var chart2 = new Chart( ctx_2, {
	type: 'line',
	data: dashboard_data_items,
	options: options_items
} );
var ctx_3 = 'ec_admin_chart_data_3';
var chart3 = new Chart( ctx_3, {
	type: 'line',
	data: dashboard_data_abandoned,
	options: options_carts
} );
document.addEventListener( 'DOMContentLoaded', function( ){ // Fixing load display, sizing issue.
	wpeasycart_admin_update_chart_type( 'line' )
}, false );
function wpeasycart_admin_update_chart_type( type ){
	jQuery( '.wpeasycart_admin_chart_types > .dashicons' ).removeClass( 'selected' );
	jQuery( '.wpeasycart_admin_chart_types > .wpeasycart_admin_chart_type_' + type ).addClass( 'selected' );
	chart1.destroy( );
	chart1 = new Chart( ctx_1, {
		type: type,
		data: dashboard_data_sales,
		options: options_sales
	} );
	chart2.destroy( );
	chart2 = new Chart( ctx_2, {
		type: type,
		data: dashboard_data_items,
		options: options_items
	} );
	chart3.destroy( );
	chart3 = new Chart( ctx_3, {
		type: type,
		data: dashboard_data_abandoned,
		options: options_carts
	} );
}
function wpeasycart_admin_export_report( ){
	jQuery( '.wpeasycart_admin_chart_export > .dashicons' ).removeClass( 'dashicons-download' ).addClass( 'dashicons-image-rotate' );
	var start_date = jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').startDate.format( 'YYYY-MM-DD' );
	var end_date = jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').endDate.format( 'YYYY-MM-DD' );
	var start_date2 = 0;
	if(  jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel != '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' && jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').startDate ){
		start_date2 = jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').startDate.format( 'YYYY-MM-DD' );
	}
	var end_date2 = 0;
	if( jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel != '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' && jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').endDate ){
		end_date2 = jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').endDate.format( 'YYYY-MM-DD' );
	}
	var range = jQuery( '#daily_filter' ).val( );
	var product_filter = jQuery( '#product_filter' ).val( );
	var country_filter = jQuery( '#country_filter' ).val( );
	var billing_country_filter = jQuery( '#billing_country_filter' ).val( );
	var location_filter = ( jQuery( '#location_filter' ).length ) ? jQuery( '#location_filter' ).val() : 0;
	var data = {
		action: 'ec_admin_create_report_export',
		start_date: start_date,
		end_date: end_date,
		start_date2: start_date2,
		end_date2: end_date2,
		range: range,
		product: product_filter,
		country: country_filter,
		billing_country: billing_country_filter,
		location_id: location_filter,
		wp_easycart_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-export-stats' ) ); ?>'
	};
	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function( response ){ 
		jQuery( '.wpeasycart_admin_chart_export > .dashicons' ).removeClass( 'dashicons-image-rotate' ).addClass( 'dashicons-download' );
		var reports = JSON.parse( response );
		var modal = '<div class="wpeasycart_admin_modal"><div class="wpeasycart_admin_modal_content">';
		modal += '<div class="wpeasycart_admin_modal_close" onclick="jQuery( this ).parent( ).parent( ).remove( )">X</div>';
		<?php do_action( 'wp_easycart_dashboard_reports_links_start' ); ?>
		modal += '<a href="' + reports.report1 + '" download class="wpeasycart_admin_download_report"><?php esc_attr_e( 'Download Main Report', 'wp-easycart' ); ?></a>';
		if( reports.report2 ){
			modal += '<a href="' + reports.report2 + '" download class="wpeasycart_admin_download_report"><?php esc_attr_e( 'Download Compare Range Report', 'wp-easycart' ); ?></a>';
		}
		modal += '<a href="' + reports.reporttax + '" download class="wpeasycart_admin_download_report"><?php esc_attr_e( 'Download Tax Report', 'wp-easycart' ); ?></a>';
		<?php do_action( 'wp_easycart_dashboard_reports_links_end' ); ?>
		modal += '</div></div>';

		jQuery( 'body' ).append( modal );
	} } );
}
function wpeasycart_admin_update_chart_data( ){
	jQuery( '.wpeasycart_admin_chart_types' ).prepend( '<div class="dashicons dashicons-image-rotate"></div>' );
	var start_date = jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').startDate.format( 'YYYY-MM-DD' );
	var end_date = jQuery( '#wpeasycart_admin_report_range1' ).data('daterangepicker').endDate.format( 'YYYY-MM-DD' );
	var start_date2 = 0;
	if(  jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel != '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' && jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').startDate ){
		start_date2 = jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').startDate.format( 'YYYY-MM-DD' );
	}
	var end_date2 = 0;
	if( jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').chosenLabel != '<?php esc_attr_e( 'Disabled', 'wp-easycart' ); ?>' && jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').endDate ){
		end_date2 = jQuery( '#wpeasycart_admin_report_range2' ).data('daterangepicker').endDate.format( 'YYYY-MM-DD' );
	}
	var range = jQuery( '#daily_filter' ).val( );
	var product_filter = jQuery( '#product_filter' ).val( );
	var country_filter = jQuery( '#country_filter' ).val( );
	var billing_country_filter = jQuery( '#billing_country_filter' ).val( );
	var location_filter = ( jQuery( '#location_filter' ).length ) ? jQuery( '#location_filter' ).val() : 0;
	var data = {
		action: 'ec_admin_get_updated_stat_list',
		start_date: start_date,
		end_date: end_date,
		start_date2: start_date2,
		end_date2: end_date2,
		range: range,
		product: product_filter,
		country: country_filter,
		billing_country: billing_country_filter,
		location_id: location_filter,
		wp_easycart_nonce: '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-updated-stats' ) ); ?>'
	};
	jQuery.ajax({url: wpeasycart_admin_ajax_object.ajax_url, type: 'post', data: data, success: function( response ){ 
		jQuery( '.wpeasycart_admin_chart_types .dashicons-image-rotate' ).remove( );
		var stats = JSON.parse( response );
		var single_stats = stats.single;
		dashboard_data_sales = JSON.parse( stats.sales );
		chart1.data = dashboard_data_sales;
		chart1.update( );
		dashboard_data_items = JSON.parse( stats.items );
		chart2.data = dashboard_data_items;
		chart2.update( );
		dashboard_data_abandoned = JSON.parse( stats.carts );
		chart3.data = dashboard_data_abandoned;
		chart3.update( );

		jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.gross_revenue.set1 );
		jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.shipping.set1 );
		jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.tax.set1 );
		jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.discount.set1 );
		jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.refund.set1 );

		jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.net_revenue.set1 );
		jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.orders.set1 );
		jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.items.set1 );
		jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.customers.set1 );
		jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_total' ).html( single_stats.carts.set1 );

		if ( single_stats.fees.length > 0 ) {
			for ( var fee_i = 0; fee_i < single_stats.fees.length; fee_i++ ) {
				jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_total' ).html( single_stats.fees[ fee_i ].set1 );
			}
		}

		if( start_date2 ){
			if( single_stats.gross_revenue.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.gross_revenue.diff + '%' );
			}else if( single_stats.gross_revenue.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.gross_revenue.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.gross_revenue.diff + '%' );
			}

			if( single_stats.shipping.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.shipping.diff + '%' );
			}else if( single_stats.shipping.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.shipping.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.shipping.diff + '%' );
			}

			if( single_stats.tax.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.tax.diff + '%' );
			}else if( single_stats.tax.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.tax.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.tax.diff + '%' );
			}

			if( single_stats.discount.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.discount.diff + '%' );
			}else if( single_stats.discount.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.discount.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.discount.diff + '%' );
			}

			if( single_stats.refund.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'descrease' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.refund.diff + '%' );
			}else if( single_stats.refund.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.refund.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.refund.diff + '%' );
			}

			if( single_stats.net_revenue.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.net_revenue.diff + '%' );
			}else if( single_stats.net_revenue.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.net_revenue.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.net_revenue.diff + '%' );
			}

			if( single_stats.orders.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.orders.diff + '%' );
			}else if( single_stats.orders.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.orders.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.orders.diff + '%' );
			}

			if( single_stats.items.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.items.diff + '%' );
			}else if( single_stats.items.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.items.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.items.diff + '%' );
			}

			if( single_stats.customers.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.customers.diff + '%' );
			}else if( single_stats.customers.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.customers.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.customers.diff + '%' );
			}

			if( single_stats.carts.diff > 0 ){
				jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.carts.diff + '%' );
			}else if( single_stats.carts.diff < 0 ){
				jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.carts.diff + '%' );
			}else{
				jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.carts.diff + '%' );
			}

			jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.gross_revenue.set2 );
			jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.shipping.set2 );
			jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.tax.set2 );
			jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.discount.set2 );
			jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.refund.set2 );

			jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.net_revenue.set2 );
			jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.orders.set2 );
			jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.items.set2 );
			jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.customers.set2 );
			jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_prev_total' ).show( ).html( single_stats.carts.set2 );
			
			if ( single_stats.fees.length > 0 ) {
				for ( fee_i = 0; fee_i < single_stats.fees.length; fee_i++ ) {
					if( single_stats.fees[ fee_i ].diff > 0 ){
						jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-arrow-up-alt"></span>' + single_stats.fees[ fee_i ].diff + '%' );
					}else if( single_stats.fees[ fee_i ].diff < 0 ){
						jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).addClass( 'decrease' ).show( ).html( '<span class="dashicons dashicons-arrow-down-alt"></span>' + single_stats.fees[ fee_i ].diff + '%' );
					}else{
						jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_change' ).removeClass( 'decrease' ).removeClass( 'increase' ).show( ).html( '<span class="dashicons dashicons-minus"></span>' + single_stats.fees[ fee_i ].diff + '%' );
					}
					
					jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_prev_total' ).show().html( single_stats.fees[ fee_i ].set2 );
				}
			}
		}else{
			jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_change' ).hide( );

			jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_change' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_change' ).hide( );

			jQuery( '#ec_admin_dashboard_stat_item1 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item2 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item3 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item4 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item5 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );

			jQuery( '#ec_admin_dashboard_stat_item6 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item7 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item8 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item9 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );
			jQuery( '#ec_admin_dashboard_stat_item10 > .ec_admin_dashboard_stat_item_prev_total' ).hide( );

			if ( single_stats.fees.length > 0 ) {
				for ( fee_i = 0; fee_i < single_stats.fees.length; fee_i++ ) {
					jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_change' ).hide( );
					jQuery( '#ec_admin_dashboard_stat_item' + Number( fee_i + 11 ) + ' > .ec_admin_dashboard_stat_item_prev_total' ).hide();
				}
			}
		}

		if( product_filter != '0' ){
			jQuery( '#ec_admin_dashboard_stat_item2' ).addClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item3' ).addClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item4' ).addClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item5' ).addClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item6' ).addClass( 'deactivate' );
		}else{
			jQuery( '#ec_admin_dashboard_stat_item2' ).removeClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item3' ).removeClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item4' ).removeClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item5' ).removeClass( 'deactivate' );
			jQuery( '#ec_admin_dashboard_stat_item6' ).removeClass( 'deactivate' );
		}
	} } );
}
</script>