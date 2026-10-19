<?php
/**
 * Step 1 — Location & currency: the store's country ( and US state / Canadian province ), currency, language and tax.
 *
 * 6.0.5: the countries, currencies and formats come from wp_easycart_admin_setup_wizard::data() / facts() ( this page and its save
 * had a copy each, of 36 countries' formats ). Coming back shows what was saved: the state or province, the VAT, EU and PST
 * choices and the store's language ( the state used to fall back to the first in the list, Alabama or Alberta, and the choices to
 * their defaults, which Continue then saved ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard           = wp_easycart_admin_setup_wizard();
$countries        = $wizard::data( 'countries' );
$states           = $wizard::data( 'states' );
$currency_names   = $wizard::data( 'currency_names' );
$currency_symbols = $wizard::data( 'currency_symbols' );

/* ---- Pre-fill: what was saved, else WordPress's language and region ---- */
$wp_locale = get_locale();
$parts     = explode( '_', $wp_locale );
/* ec_option_store_locale is US on every store until this step is saved, so it only counts once it was ( 6.0.5: a German site was offered the US ). */
$saved     = get_option( 'ec_option_store_country' ) ? strtoupper( (string) get_option( 'ec_option_store_country' ) ) : ( $wizard->completed_through() >= wp_easycart_admin_setup_wizard::STEP_LOCATION ? strtoupper( (string) get_option( 'ec_option_store_locale' ) ) : '' );
$guess     = ( $saved && isset( $countries[ $saved ] ) ) ? $saved : ( ( isset( $parts[1] ) && isset( $countries[ $parts[1] ] ) ) ? $parts[1] : 'US' );
$facts     = $wizard::facts( $guess );
$saved_cur = strtoupper( (string) get_option( 'ec_option_base_currency' ) );
/* The base currency is USD on every store until saved, so a new store gets its country's. */
$guess_cur = ( $saved && $saved_cur && isset( $currency_names[ $saved_cur ] ) ) ? $saved_cur : $facts['currency'];
$tz        = wp_timezone_string();
$tax_ready = class_exists( 'wp_easycart_tax_setup' ) && class_exists( 'wp_easycart_admin_tax_setup' );
$has_tax   = ( $tax_ready && method_exists( 'wp_easycart_tax_setup', 'tax_in_use' ) ) ? wp_easycart_tax_setup::tax_in_use() : ( (bool) $GLOBALS['wpdb']->get_var( 'SELECT taxrate_id FROM ec_taxrate LIMIT 1' ) || get_option( 'ec_option_enable_easy_canada_tax' ) );
$choices   = $tax_ready ? wp_easycart_tax_setup::choices( $guess ) : array( 'state' => '', 'included' => 1, 'eu' => 'home', 'pst' => 'home' );
$saved_st  = ( isset( $states[ $guess ] ) && isset( $states[ $guess ][ $choices['state'] ] ) ) ? $choices['state'] : '';
$languages = $wizard::store_languages();
$language  = $wizard->store_language_choice();
$step_error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message key read for display.

/* Each country's currency, how it writes amounts and its units, for the preview ( setup-wizard-v2.js ); VAT / GST countries and
   what their tax is called ( the recommended tax settings ), EU countries. */
$meta = array();
foreach ( $countries as $code => $name ) {
	$f             = ( $code === $guess ) ? $facts : $wizard::facts( $code );
	$meta[ $code ] = array(
		'c'   => $f['currency'],
		'pos' => ( $f['before'] ? 'left' : 'right' ) . ( $f['space'] ? '_space' : '' ),
		'th'  => $f['thou'],
		'dec' => $f['dec'],
		'w'   => ( 'lbs' === $f['weight'] ) ? 'lb' : 'kg',
		'd'   => $f['metric'] ? 'cm' : 'in',
		'n'   => $f['decimals'],
	);
}
if ( class_exists( 'wp_easycart_tax_setup' ) ) {
	foreach ( wp_easycart_tax_setup::vat_countries() as $code => $vat_info ) {
		if ( ! isset( $meta[ $code ] ) ) {
			continue;
		}
		$meta[ $code ]['v']  = ( class_exists( 'wp_easycart_vat' ) && in_array( $code, wp_easycart_vat::gst_countries(), true ) ) ? 'GST' : 'VAT';
		$meta[ $code ]['vr'] = (float) $vat_info['rate'];
		$meta[ $code ]['eu'] = ( class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $code ) ) ? 1 : 0;
	}
}
/* Currencies written without decimals or with three ( the preview's decimals follow the currency chosen ). */
$decimals = array();
foreach ( wp_easycart_admin_setup_wizard::ZERO_DECIMALS as $code ) {
	$decimals[ $code ] = 0;
}
foreach ( wp_easycart_admin_setup_wizard::THREE_DECIMALS as $code ) {
	$decimals[ $code ] = 3;
}
?>
<form action="" method="POST" name="wpeasycart_admin_setup_wizard_form" id="wpeasycart_admin_setup_wizard_form" novalidate="novalidate" class="ecwz-form">
	<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-process-wizard-location' ); ?>
	<input type="hidden" name="ec_admin_form_action" id="ec_admin_form_action" value="process-wizard-location">
	<input type="hidden" name="locale" id="wp_easycart_locale" value="<?php echo esc_attr( $guess . ( '' !== $saved_st ? '_' . $saved_st : '' ) ); ?>">

	<div class="ecwz-body">
		<h2><?php esc_html_e( 'Where is your store based?', 'wp-easycart' ); ?></h2>
		<p class="ecwz-lede"><?php esc_html_e( 'Your country sets your currency, how prices are written, weight and size units, and the tax you charge. You can change any of it later in Settings.', 'wp-easycart' ); ?></p>

		<?php if ( 'state' === $step_error ) { ?>
		<div class="ecwz-note ecwz-note-amber" role="alert">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
			<div><?php esc_html_e( 'Choose your state or province so the right tax can be set up. Everything else on this step was saved.', 'wp-easycart' ); ?></div>
		</div>
		<?php } elseif ( ! $saved ) { ?>
		<div class="ecwz-note ecwz-note-brand">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
			<div><?php echo sprintf( esc_html__( 'Pre-filled from your WordPress settings (language %1$s, timezone %2$s). Change anything that isn\'t right.', 'wp-easycart' ), '<strong>' . esc_html( $wp_locale ) . '</strong>', '<strong>' . esc_html( $tz ) . '</strong>' ); ?></div>
		</div>
		<?php } ?>

		<div class="ecwz-frow ecwz-frow-first">
			<div class="ecwz-lab"><label for="ecwz_country"><?php esc_html_e( 'Country', 'wp-easycart' ); ?></label><small><?php esc_html_e( 'Where your business is. It decides your tax and the countries offered first at checkout.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val ecwz-inline">
				<select id="ecwz_country" class="ecwz-select ecwz-select2" data-saved="<?php echo esc_attr( $saved ); ?>">
					<?php foreach ( $countries as $country_code => $country_name ) { ?>
					<option value="<?php echo esc_attr( $country_code ); ?>" data-currency="<?php echo esc_attr( isset( $meta[ $country_code ] ) ? $meta[ $country_code ]['c'] : '' ); ?>" data-has-states="<?php echo isset( $states[ $country_code ] ) ? '1' : '0'; ?>"<?php selected( $country_code, $guess ); ?>><?php echo esc_html( html_entity_decode( $country_name ) ); ?></option>
					<?php } ?>
				</select>
				<div class="ecwz-state-wrap" id="ecwz_state_wrap"<?php if ( ! isset( $states[ $guess ] ) ) { echo ' hidden'; } ?>>
					<select id="ecwz_state" class="ecwz-select ecwz-select2" aria-label="<?php esc_attr_e( 'State or province', 'wp-easycart' ); ?>" aria-describedby="ecwz_state_error" data-saved="<?php echo esc_attr( $saved_st ); ?>">
						<option value="" data-none="1" data-us="<?php esc_attr_e( 'Choose your state', 'wp-easycart' ); ?>" data-ca="<?php esc_attr_e( 'Choose your province', 'wp-easycart' ); ?>"><?php echo ( 'CA' === $guess ) ? esc_html__( 'Choose your province', 'wp-easycart' ) : esc_html__( 'Choose your state', 'wp-easycart' ); ?></option>
						<?php foreach ( ( isset( $states[ $guess ] ) ? $states[ $guess ] : array() ) as $state_code => $state_name ) { ?>
						<option value="<?php echo esc_attr( $state_code ); ?>"<?php selected( $state_code, $saved_st ); ?>><?php echo esc_html( $state_name ); ?></option>
						<?php } ?>
					</select>
					<script type="application/json" id="ecwz_states"><?php
						$state_lists = array();
						foreach ( $states as $country_code => $state_list ) {
							foreach ( $state_list as $state_code => $state_name ) {
								$state_lists[ $country_code ][] = array( $state_code, html_entity_decode( $state_name ) );
							}
						}
						echo wp_json_encode( $state_lists );
					?></script>
					<div class="ecwz-field-error" id="ecwz_state_error" hidden data-us="<?php esc_attr_e( 'Choose your state: it sets the sales tax you charge.', 'wp-easycart' ); ?>" data-ca="<?php esc_attr_e( 'Choose your province: it sets your GST / HST and PST.', 'wp-easycart' ); ?>"></div>
				</div>
			</div>
		</div>

		<div class="ecwz-frow">
			<div class="ecwz-lab"><label for="wp_easycart_currency"><?php esc_html_e( 'Currency', 'wp-easycart' ); ?></label><small><?php esc_html_e( 'What your prices are in.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val">
				<select name="currency" id="wp_easycart_currency" class="ecwz-select ecwz-select2">
					<?php foreach ( $currency_names as $currency_code => $currency_name ) { ?>
					<option value="<?php echo esc_attr( $currency_code ); ?>" data-symbol="<?php echo isset( $currency_symbols[ $currency_code ] ) ? esc_attr( html_entity_decode( $currency_symbols[ $currency_code ] ) ) : ''; ?>"<?php selected( $currency_code, $guess_cur ); ?>><?php echo esc_html( ucwords( $currency_name ) ); ?><?php if ( isset( $currency_symbols[ $currency_code ] ) ) { ?> (<?php echo esc_html( html_entity_decode( $currency_symbols[ $currency_code ] ) ); ?>)<?php } ?></option>
					<?php } ?>
				</select>
				<div class="ecwz-facts" aria-live="polite">
					<span><?php esc_html_e( 'Prices look like', 'wp-easycart' ); ?> <strong id="ecwz_currency_preview"></strong></span>
					<span><?php esc_html_e( 'Weights in', 'wp-easycart' ); ?> <strong id="ecwz_weight_unit" data-lb="<?php esc_attr_e( 'pounds', 'wp-easycart' ); ?>" data-kg="<?php esc_attr_e( 'kilograms', 'wp-easycart' ); ?>"><?php echo 'lbs' === $facts['weight'] ? esc_html__( 'pounds', 'wp-easycart' ) : esc_html__( 'kilograms', 'wp-easycart' ); ?></strong></span>
					<span><?php esc_html_e( 'Sizes in', 'wp-easycart' ); ?> <strong id="ecwz_size_unit" data-in="<?php esc_attr_e( 'inches', 'wp-easycart' ); ?>" data-cm="<?php esc_attr_e( 'centimetres', 'wp-easycart' ); ?>"><?php echo $facts['metric'] ? esc_html__( 'centimetres', 'wp-easycart' ) : esc_html__( 'inches', 'wp-easycart' ); ?></strong></span>
				</div>
				<script type="application/json" id="ecwz_locale_meta"><?php echo wp_json_encode( $meta ); ?></script>
				<script type="application/json" id="ecwz_currency_decimals"><?php echo wp_json_encode( (object) $decimals ); ?></script>
			</div>
		</div>

		<div class="ecwz-frow">
			<div class="ecwz-lab"><label for="ecwz_store_language"><?php esc_html_e( 'Store language', 'wp-easycart' ); ?></label><small><?php esc_html_e( 'The words shoppers see in your store, cart, checkout and emails.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val">
				<select name="store_language" id="ecwz_store_language" class="ecwz-select">
					<?php foreach ( $languages as $file => $name ) { ?>
					<option value="<?php echo esc_attr( $file ); ?>"<?php selected( $file, $language ); ?>><?php echo esc_html( $name ); ?></option>
					<?php } ?>
				</select>
				<div class="ecwz-hint"><?php echo sprintf( esc_html__( 'Change any wording, or add more languages, under %s.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=language-editor" target="_blank" class="ecwz-lnk">' . esc_html__( 'Settings › Language', 'wp-easycart' ) . '</a>' ); ?></div>
			</div>
		</div>

		<?php
		/* 6.0.5: the recommended tax settings for the country chosen ( wp_easycart_tax_setup ): what will be set, and for a store with tax
		   set up before, what would change. setup-wizard-v2.js asks again ( ecv2_tax_setup_preview ) as the country and choices change,
		   and turns "Update my tax settings to these" on when one of them is changed ( the old country's tax stayed otherwise ). */
		?>
		<div class="ecwz-frow">
			<?php /* 6.0.5 ( Matt ): named for the country chosen ( setup-wizard-v2.js refreshTax() swaps it as the country changes ). */ ?>
			<div class="ecwz-lab"><span id="ecwz_tax_title" data-us="<?php esc_attr_e( 'Sales tax', 'wp-easycart' ); ?>" data-ca="<?php esc_attr_e( 'GST / HST and PST', 'wp-easycart' ); ?>" data-vat="<?php esc_attr_e( 'VAT', 'wp-easycart' ); ?>" data-gst="<?php esc_attr_e( 'GST', 'wp-easycart' ); ?>" data-other="<?php esc_attr_e( 'Tax', 'wp-easycart' ); ?>"><?php echo esc_html( class_exists( 'wp_easycart_tax_setup' ) && method_exists( 'wp_easycart_tax_setup', 'own_tax_name' ) ? wp_easycart_tax_setup::own_tax_name( $guess ) : __( 'Sales tax', 'wp-easycart' ) ); ?></span><small><?php esc_html_e( 'The settings stores in your country are expected to use, with today\'s rates.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val">
				<label class="ecwz-tg-row">
					<span class="ecwz-tg"><input type="checkbox" name="sales_tax" id="wp_easycart_sales_tax" value="1"<?php checked( ! $has_tax ); ?>><span></span></span>
					<span class="ecwz-t"><?php echo $has_tax ? esc_html__( 'Update my tax settings to these', 'wp-easycart' ) : esc_html__( 'Set up tax for my country', 'wp-easycart' ); ?></span>
				</label>
				<div id="wp_easycart_wizard_tax_info" class="ecwz-tax<?php echo $has_tax ? ' is-off' : ''; ?>">
					<div class="ecwz-tax-summary" id="ecwz_tax_summary" aria-live="polite" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-ecv2-tax-setup' ) ); ?>"><?php
					if ( $tax_ready ) {
						echo wp_easycart_admin_tax_setup::wizard_html( wp_easycart_tax_setup::recommend( $guess, $choices ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wizard_html() escapes every part.
					}
					?></div>
					<?php /* VAT / GST stores say whether prices include it; EU stores how they charge other EU countries; Canadian stores where they collect PST. */ ?>
					<div class="ecwz-vat" id="ecwz_vat" hidden>
						<label class="ecwz-tg-row">
							<span class="ecwz-tg"><input type="checkbox" name="tax_included" id="ecwz_tax_included" value="1"<?php checked( ! empty( $choices['included'] ) ); ?>><span></span></span>
							<span class="ecwz-t" data-ecwz-vat="included" data-vat="<?php esc_attr_e( 'My prices include VAT', 'wp-easycart' ); ?>" data-gst="<?php esc_attr_e( 'My prices include GST', 'wp-easycart' ); ?>"><?php esc_html_e( 'My prices include VAT', 'wp-easycart' ); ?></span>
						</label>
						<div class="ecwz-hint"><?php esc_html_e( 'Shoppers see the price they pay, and checkout shows the tax inside it: usual for consumer prices in the UK, the EU, Australia and New Zealand. Off adds it at checkout.', 'wp-easycart' ); ?></div>
						<fieldset class="ecwz-vat-eu" id="ecwz_eu_vat" hidden>
							<legend><?php esc_html_e( 'Customers in other EU countries', 'wp-easycart' ); ?></legend>
							<label class="ecwz-vat-choice"><input type="radio" name="eu_vat" value="home"<?php checked( 'oss' !== $choices['eu'] ); ?>> <span><b><?php esc_html_e( 'Charge my country’s VAT', 'wp-easycart' ); ?></b> <?php esc_html_e( 'My sales to consumers in other EU countries are under €10,000 a year.', 'wp-easycart' ); ?></span></label>
							<label class="ecwz-vat-choice"><input type="radio" name="eu_vat" value="oss"<?php checked( 'oss', $choices['eu'] ); ?>> <span><b><?php esc_html_e( 'Charge each country’s own VAT', 'wp-easycart' ); ?></b> <?php esc_html_e( 'Over €10,000 a year, or I am registered for the One-Stop Shop ( OSS ).', 'wp-easycart' ); ?></span></label>
						</fieldset>
					</div>
					<div class="ecwz-vat" id="ecwz_canada" hidden>
						<label class="ecwz-tg-row">
							<span class="ecwz-tg"><input type="checkbox" name="canada_pst" id="ecwz_canada_pst" value="1"<?php checked( 'all', $choices['pst'] ); ?>><span></span></span>
							<span class="ecwz-t"><?php esc_html_e( 'I\'m registered for PST in every PST province ( BC, SK, MB, QC )', 'wp-easycart' ); ?></span>
						</label>
						<div class="ecwz-hint"><?php esc_html_e( 'Most stores collect PST only in their own province.', 'wp-easycart' ); ?></div>
					</div>
					<div class="ecwz-hint"><?php echo sprintf( esc_html__( 'Change anything later under %s.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=tax" class="ecwz-lnk">' . esc_html__( 'Settings › Taxes', 'wp-easycart' ) . '</a>' ); ?><?php if ( class_exists( 'wp_easycart_admin_edition' ) && in_array( wp_easycart_admin_edition::tier(), array( 'free' ), true ) ) { echo ' ' . esc_html( wp_easycart_admin_edition::requires_text( __( 'Live sales tax from TaxJar or TaxCloud', 'wp-easycart' ) ) ); } /* 6.0.5 ( M19 ): tax services are Pro */ ?></div>
				</div>
			</div>
		</div>
	</div>

	<?php $wizard->render_footer( wp_easycart_admin_setup_wizard::STEP_LOCATION ); ?>
</form>
