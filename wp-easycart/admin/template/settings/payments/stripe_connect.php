<?php
/**
 * Stripe ( free edition ): Stripe through WP EasyCart's Stripe Connect, loaded into the Payments drawer
 * ( wp_easycart_admin_payment_v2 ). A licensed WP EasyCart PRO replaces this file with its own ( payment methods, the
 * payment form's look, subscription emails ).
 *
 * 6.0.2: laid out like the Square panel ( 6.0.1 ): the drawer's groups ( Payments, Currency and country, Payment methods,
 * Checkout, Subscriptions, Notifications from Stripe ) with every option in view. Element ids are unchanged: admin/js/payment.js
 * saves from them. The switches save as they change ( Live and Test mode choose the gateway; the others save the options,
 * which never change the live gateway ); the currency, country and signing secret save from the drawer's Save button ( the
 * hidden ec_admin_save_stripe_connect_options() button ). What needs WP EasyCart PRO shows locked and is saved off, as the
 * one-choice selects it replaces were.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Plan name for the locked rows: the store's own plan, or Pro/Premium when no license is known. */
$ec_stripe_plan  = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' );
$ec_stripe_badge = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro';

$ec_stripe_active    = ( 'stripe_connect' === get_option( 'ec_option_payment_process_method' ) );
$ec_stripe_sandbox   = (bool) get_option( 'ec_option_stripe_connect_use_sandbox' );
$ec_stripe_has_live  = '' !== (string) get_option( 'ec_option_stripe_connect_production_access_token' );
$ec_stripe_has_test  = '' !== (string) get_option( 'ec_option_stripe_connect_sandbox_access_token' );
$ec_stripe_connected = $ec_stripe_has_live || $ec_stripe_has_test;
$ec_stripe_currency  = strtoupper( (string) get_option( 'ec_option_stripe_currency' ) );
$ec_stripe_country   = strtoupper( (string) get_option( 'ec_option_stripe_company_country' ) );
if ( 'PO' === $ec_stripe_country ) {
	$ec_stripe_country = 'PL'; // 6.0.2: the old list stored Poland as PO; the next save stores PL.
}

/* Connect and disconnect links: the same endpoints the gateway card uses. */
if ( class_exists( 'wp_easycart_admin_payment_v2' ) && method_exists( 'wp_easycart_admin_payment_v2', 'connect_urls' ) ) {
	$ec_stripe_urls = wp_easycart_admin_payment_v2::connect_urls( 'stripe_connect' );
} else {
	$ec_stripe_redirect = esc_url_raw( admin_url() ) . '?ec_admin_form_action=stripe_onboard&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-stripe' );
	$ec_stripe_urls     = array(
		'live' => 'https://connect.wpeasycart.com/connect/?step=start&redirect=' . rawurlencode( $ec_stripe_redirect . '&env=production' ) . '&env=production',
		'test' => 'https://connect.wpeasycart.com/connect/?step=start&redirect=' . rawurlencode( $ec_stripe_redirect . '&env=sandbox' ) . '&env=sandbox',
	);
}
$ec_stripe_env        = ( ( $ec_stripe_sandbox && $ec_stripe_has_test ) || ! $ec_stripe_has_live ) ? 'test' : 'live';
$ec_stripe_disconnect = ( $ec_stripe_connected && class_exists( 'wp_easycart_admin_payment_v2' ) && method_exists( 'wp_easycart_admin_payment_v2', 'disconnect_url' ) ) ? wp_easycart_admin_payment_v2::disconnect_url( 'stripe_connect', $ec_stripe_env ) : '';

/* The connected account ( the mode in use ): its name, default currency and country. Asked only when that mode is connected. */
$ec_stripe_account     = false;
$ec_stripe_account_err = '';
if ( class_exists( 'ec_stripe_connect' ) && ( $ec_stripe_sandbox ? $ec_stripe_has_test : $ec_stripe_has_live ) ) {
	$ec_stripe_api      = new ec_stripe_connect();
	$ec_stripe_response = $ec_stripe_api->get_connect_account();
	if ( is_object( $ec_stripe_response ) && isset( $ec_stripe_response->error ) ) {
		$ec_stripe_account_err = ( is_object( $ec_stripe_response->error ) && isset( $ec_stripe_response->error->message ) ) ? (string) $ec_stripe_response->error->message : __( 'No reason given.', 'wp-easycart' );
	} elseif ( is_object( $ec_stripe_response ) && isset( $ec_stripe_response->id ) ) {
		$ec_stripe_account = $ec_stripe_response;
	}
}
$ec_stripe_account_currency = ( $ec_stripe_account && isset( $ec_stripe_account->default_currency ) ) ? strtoupper( $ec_stripe_account->default_currency ) : '';
$ec_stripe_account_country  = ( $ec_stripe_account && isset( $ec_stripe_account->country ) ) ? strtoupper( $ec_stripe_account->country ) : '';
$ec_stripe_account_name     = '';
if ( $ec_stripe_account ) {
	if ( isset( $ec_stripe_account->settings->dashboard->display_name ) && '' !== (string) $ec_stripe_account->settings->dashboard->display_name ) {
		$ec_stripe_account_name = (string) $ec_stripe_account->settings->dashboard->display_name;
	} elseif ( isset( $ec_stripe_account->business_profile->name ) && '' !== (string) $ec_stripe_account->business_profile->name ) {
		$ec_stripe_account_name = (string) $ec_stripe_account->business_profile->name;
	} elseif ( isset( $ec_stripe_account->email ) ) {
		$ec_stripe_account_name = (string) $ec_stripe_account->email;
	}
}

$ec_stripe_currencies = array(
	'USD' => 'U.S. Dollar',
	'CAD' => 'Canadian Dollar',
	'AUD' => 'Australian Dollar',
	'EUR' => 'Euro',
	'GBP' => 'British Pound',
	'DEM' => 'German Mark',
	'CHF' => 'Swiss Franc',
	'AFN' => 'Afghanistan Afghani',
	'ALL' => 'Albanian Lek',
	'AMD' => 'Armenian Dram',
	'AOA' => 'Angolan Kwanza',
	'ARS' => 'Argentine Peso',
	'AWG' => 'Aruban Florin',
	'AZN' => 'Azerbaijani Manat',
	'BSD' => 'Bahamanian Dollar',
	'BHD' => 'Bahraini Dinar',
	'BDT' => 'Bangladeshi Taka',
	'BBD' => 'Barbados Dollar',
	'BYR' => 'Belarussian Ruble',
	'BZD' => 'Belize Dollar',
	'BMD' => 'Bermudian Dollar',
	'BOB' => 'Bolivian Boliviano',
	'BWP' => 'Botswana Pula',
	'BRL' => 'Brazilian Real',
	'BND' => 'Brunei Dollar',
	'BGN' => 'Bulgarian Lev',
	'BIF' => 'Burundi Franc',
	'KHR' => 'Cambodian Riel',
	'CVE' => 'Cape Verde Escudo',
	'KYD' => 'Cayman Islands Dollar',
	'XAF' => 'Central African Republic Franc BCEAO',
	'XPF' => 'CFP Franc',
	'CLP' => 'Chilean Peso',
	'CNY' => 'Chinese Yuan Renminbi',
	'COP' => 'Colombian Peso',
	'KMF' => 'Comoros Franc',
	'BAM' => 'Convertible Marks',
	'CRC' => 'Costa Rican Colon',
	'HRK' => 'Croatian Kuna',
	'CUP' => 'Cuban Peso',
	'CYP' => 'Cyprus Pound',
	'CZK' => 'Czech Republic Koruna',
	'DKK' => 'Danish Krone',
	'DJF' => 'Djibouti Franc',
	'DOP' => 'Dominican Peso',
	'XCD' => 'East Caribbean Dollar',
	'ECS' => 'Ecuador Sucre',
	'EGP' => 'Egyptian Pound',
	'SVC' => 'El Salvador Colon',
	'ERN' => 'Eritrea Nakfa',
	'EEK' => 'Estonian Kroon',
	'ETB' => 'Ethiopian Birr',
	'FKP' => 'Falkland Islands Pound',
	'FJD' => 'Fiji Dollar',
	'CDF' => 'Franc Congolais',
	'GMD' => 'Gambian Dalasi',
	'GEL' => 'Georgian Lari',
	'GHS' => 'Ghanaian Cedi',
	'GIP' => 'Gibraltar Pound',
	'GTQ' => 'Guatemalan Quetzal',
	'GNF' => 'Guinea Franc',
	'GWP' => 'Guinea-Bissau Peso',
	'GYD' => 'Guyanan Dollar',
	'HTG' => 'Haitian Gourde',
	'HNL' => 'Honduran Lempira',
	'HKD' => 'Hong Kong Dollar',
	'HUF' => 'Hungarian Forint',
	'ISK' => 'Iceland Krona',
	'INR' => 'Indian Rupee',
	'IDR' => 'Indonesian Rupiah',
	'IRR' => 'Iranian Rial',
	'IQD' => 'Iraqi Dinar',
	'ILS' => 'Israeli New Shekel',
	'JMD' => 'Jamaican Dollar',
	'JPY' => 'Japanese Yen',
	'JOD' => 'Jordanian Dinar',
	'KZT' => 'Kazakhstan Tenge',
	'KES' => 'Kenyan Shilling',
	'KWD' => 'Kuwaiti Dinar',
	'GKS' => 'Kyrgyzstan Som',
	'KIP' => 'Laos Kip',
	'LAK' => 'Laosian Kip',
	'LVL' => 'Latvian Lat',
	'LBP' => 'Lebanese Pound',
	'LRD' => 'Liberian Dollar',
	'LYD' => 'Libyan Dinar',
	'LTL' => 'Lithuanian Litas',
	'LSL' => 'Loti',
	'MOP' => 'Macanese Pataca',
	'MKD' => 'Macedonian Denar',
	'MGF' => 'Malagasy Franc',
	'MGA' => 'Malagasy Ariary',
	'MWK' => 'Malawi Kwacha',
	'MYR' => 'Malaysian Ringgit',
	'MVR' => 'Maldive Rufiyaa',
	'MTL' => 'Maltese Lira',
	'MRO' => 'Mauritanian Ouguiya',
	'MUR' => 'Mauritius Rupee',
	'MXN' => 'Mexican Peso',
	'MNT' => 'Mongolian Tugrik',
	'MAD' => 'Moroccan Dirham',
	'MZM' => 'Mozambique Metical',
	'MMK' => 'Myanmar Kyat',
	'NAD' => 'Namibia Dollar',
	'NPR' => 'Nepalese Rupee',
	'ANG' => 'Netherlands Antillean Guilder',
	'PGK' => 'New Guinea Kina',
	'TWD' => 'New Taiwan Dollar',
	'TRY' => 'New Turkish Lira',
	'NZD' => 'New Zealand Dollar',
	'NIO' => 'Nicaraguan Cordoba Oro',
	'NGN' => 'Nigerian Naira',
	'KPW' => 'North Korea Won',
	'NOK' => 'Norwegian Kroner',
	'PKR' => 'Pakistan Rupee',
	'PAB' => 'Panamanian Balboa',
	'PYG' => 'Paraguay Guarani',
	'PEN' => 'Peruvian Nuevo Sol',
	'PHP' => 'Philippine Peso',
	'PLN' => 'Polish Zloty',
	'QAR' => 'Qatari Rial',
	'OMR' => 'Rial Omani',
	'RON' => 'Romanian Leu',
	'RUB' => 'Russian Rouble',
	'RWF' => 'Rwanda Franc',
	'WST' => 'Samoan Tala',
	'STD' => 'Sao Tome/Principe Dobra',
	'SAR' => 'Saudi Riyal',
	'RSD' => 'Serbian Dinar',
	'SCR' => 'Seychelles Rupee',
	'SLL' => 'Sierra Leone Leone',
	'SGD' => 'Singapore Dollar',
	'SKK' => 'Slovak Koruna',
	'SIT' => 'Slovenian Tolar',
	'SBD' => 'Solomon Islands Dollar',
	'SOS' => 'Somalia Shilling',
	'ZAR' => 'South African Rand',
	'KRW' => 'South-Korean Won',
	'LKR' => 'Sri Lanka Rupee',
	'SHP' => 'St. Helena Pound',
	'SDD' => 'Sudanese Dollar',
	'SRD' => 'Suriname Dollar',
	'SZL' => 'Swaziland Lilangeni',
	'SEK' => 'Swedish Krona',
	'SYP' => 'Syrian Arab Republic Pound',
	'TJS' => 'Tajikistani Somoni',
	'TZS' => 'Tanzanian Shilling',
	'THB' => 'Thai Baht',
	'TOP' => "Tonga Pa'anga",
	'TTD' => 'Trinidad/Tobago Dollar',
	'TND' => 'Tunisian Dinar',
	'TMM' => 'Turkmenistan Manat',
	'UGX' => 'Uganda Shilling',
	'UAH' => 'Ukraine Hryvnia',
	'AED' => 'Utd. Arab Emir. Dirham',
	'UYU' => 'Uruguayo Peso',
	'UZS' => 'Uzbekistan Som',
	'VUV' => 'Vanuatu Vatu',
	'VEF' => 'Venezuelan Bolivar Fuerte',
	'VND' => 'Vietnamese Dong',
	'XOF' => 'West African CFA Franc BCEAO',
	'YER' => 'Yemeni Rial',
	'YUM' => 'Yugoslav New Dinar',
	'ZMK' => 'Zambian Kwacha',
	'ZWD' => 'Zimbabwean Dollar',
);
if ( '' !== $ec_stripe_currency && ! isset( $ec_stripe_currencies[ $ec_stripe_currency ] ) ) {
	$ec_stripe_currencies[ $ec_stripe_currency ] = $ec_stripe_currency; // A code saved by an older list stays selected.
}
$ec_stripe_countries = array(
	'US' => __( 'United States (US)', 'wp-easycart' ),
	'AU' => __( 'Australia (AU)', 'wp-easycart' ),
	'AT' => __( 'Austria (AT)', 'wp-easycart' ),
	'BE' => __( 'Belgium (BE)', 'wp-easycart' ),
	'BR' => __( 'Brazil (BR)', 'wp-easycart' ),
	'CA' => __( 'Canada (CA)', 'wp-easycart' ),
	'HR' => __( 'Croatia (HR)', 'wp-easycart' ),
	'CY' => __( 'Cyprus (CY)', 'wp-easycart' ),
	'CZ' => __( 'Czech Republic (CZ)', 'wp-easycart' ),
	'DK' => __( 'Denmark (DK)', 'wp-easycart' ),
	'EE' => __( 'Estonia (EE)', 'wp-easycart' ),
	'FI' => __( 'Finland (FI)', 'wp-easycart' ),
	'FR' => __( 'France (FR)', 'wp-easycart' ),
	'DE' => __( 'Germany (DE)', 'wp-easycart' ),
	'GI' => __( 'Gibraltar (GI)', 'wp-easycart' ),
	'GR' => __( 'Greece (GR)', 'wp-easycart' ),
	'HK' => __( 'Hong Kong SAR China (HK)', 'wp-easycart' ),
	'HU' => __( 'Hungary (HU)', 'wp-easycart' ),
	'IN' => __( 'India (IN)', 'wp-easycart' ),
	'IE' => __( 'Ireland (IE)', 'wp-easycart' ),
	'IT' => __( 'Italy (IT)', 'wp-easycart' ),
	'JP' => __( 'Japan (JP)', 'wp-easycart' ),
	'LV' => __( 'Latvia (LV)', 'wp-easycart' ),
	'LI' => __( 'Liechtenstein (LI)', 'wp-easycart' ),
	'LT' => __( 'Lithuania (LT)', 'wp-easycart' ),
	'LU' => __( 'Luxembourg (LU)', 'wp-easycart' ),
	'MY' => __( 'Malaysia (MY)', 'wp-easycart' ),
	'MT' => __( 'Malta (MT)', 'wp-easycart' ),
	'MX' => __( 'Mexico (MX)', 'wp-easycart' ),
	'NL' => __( 'Netherlands (NL)', 'wp-easycart' ),
	'NZ' => __( 'New Zealand (NZ)', 'wp-easycart' ),
	'NO' => __( 'Norway (NO)', 'wp-easycart' ),
	'PL' => __( 'Poland (PL)', 'wp-easycart' ),
	'PT' => __( 'Portugal (PT)', 'wp-easycart' ),
	'RO' => __( 'Romania (RO)', 'wp-easycart' ),
	'SG' => __( 'Singapore (SG)', 'wp-easycart' ),
	'SK' => __( 'Slovakia (SK)', 'wp-easycart' ),
	'SI' => __( 'Slovenia (SI)', 'wp-easycart' ),
	'ES' => __( 'Spain (ES)', 'wp-easycart' ),
	'SE' => __( 'Sweden (SE)', 'wp-easycart' ),
	'CH' => __( 'Switzerland (CH)', 'wp-easycart' ),
	'TH' => __( 'Thailand (TH)', 'wp-easycart' ),
	'AE' => __( 'United Arab Emirates (AE)', 'wp-easycart' ),
	'GB' => __( 'United Kingdom (GB)', 'wp-easycart' ),
);
if ( '' !== $ec_stripe_country && ! isset( $ec_stripe_countries[ $ec_stripe_country ] ) ) {
	$ec_stripe_countries[ $ec_stripe_country ] = $ec_stripe_country;
}

/*
 * Payment methods WP EasyCart PRO offers through Stripe, by family, with the currencies and business countries each one needs
 * ( the rules payment.js applies as the currency or country changes: ec_admin_update_stripe_connect_display() ). Here they are
 * locked: each family is one row naming the methods that fit this store, and every method's option is saved off.
 */
$ec_stripe_wide     = array( 'AU', 'AT', 'BE', 'BG', 'CA', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HK', 'HU', 'IS', 'IE', 'IT', 'JP', 'LV', 'LI', 'LT', 'LU', 'MT', 'MX', 'NL', 'NZ', 'NO', 'PL', 'PT', 'RO', 'SG', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US' );
$ec_stripe_families = array(
	'stripe_more_wallets'      => array(
		'title'   => __( 'Alipay, GrabPay and WeChat Pay', 'wp-easycart' ),
		'desc'    => __( 'Wallets shoppers in Asia use.', 'wp-easycart' ),
		'methods' => array(
			'stripe_use_alipay'    => array( 'Alipay', array( 'CNY', 'AUD', 'CAD', 'EUR', 'GBP', 'HKD', 'JPY', 'SGD', 'MYR', 'NZD', 'USD' ), array( 'AU', 'AT', 'BE', 'BG', 'CA', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HK', 'HU', 'IE', 'IT', 'JP', 'LV', 'LI', 'LT', 'LU', 'MY', 'NL', 'NZ', 'NO', 'PT', 'RO', 'SG', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US' ) ),
			'stripe_use_grabpay'   => array( 'GrabPay', array( 'SGD', 'MYR' ), array( 'MY', 'SG' ) ),
			'stripe_use_wechatpay' => array( 'WeChat Pay', array( 'CNY', 'AUD', 'CAD', 'EUR', 'GBP', 'HKD', 'JPY', 'SGD', 'USD', 'DKK', 'NOK', 'SEK', 'CHF' ), array( 'AU', 'AT', 'BE', 'CA', 'DK', 'FI', 'FR', 'DE', 'HK', 'IE', 'IT', 'JP', 'LU', 'NL', 'NO', 'PT', 'SG', 'ES', 'SE', 'CH', 'GB', 'US' ) ),
		),
	),
	'stripe_buy_now_later'     => array(
		'title'   => __( 'Buy now, pay later', 'wp-easycart' ),
		'desc'    => __( 'Shoppers split the price into installments; you are paid in full.', 'wp-easycart' ),
		'methods' => array(
			'stripe_use_affirm'   => array( 'Affirm', array( 'USD' ), array( 'US' ) ),
			'stripe_use_afterpay' => array( 'Afterpay', array( 'USD', 'CAD', 'GBP', 'AUD', 'NZD', 'EUR' ), array( 'AU', 'CA', 'FR', 'NZ', 'ES', 'GB', 'US' ) ),
			'stripe_use_klarna'   => array( 'Klarna', array( 'EUR', 'USD', 'GBP', 'DKK', 'SEK', 'NOK' ), array( 'AT', 'BE', 'DK', 'EE', 'FI', 'FR', 'GR', 'DE', 'IE', 'IT', 'LV', 'LT', 'NL', 'NO', 'SK', 'SI', 'ES', 'SE', 'GB', 'US' ) ),
		),
	),
	'stripe_bank_redirects'    => array(
		'title'   => __( 'Bank payments', 'wp-easycart' ),
		'desc'    => __( 'Shoppers approve the payment in their own bank\'s app or website.', 'wp-easycart' ),
		'methods' => array(
			'stripe_use_bancontact' => array( 'Bancontact', array( 'EUR' ), $ec_stripe_wide ),
			'stripe_use_blik'       => array( 'BLIK', array( 'PLN' ), array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE' ) ),
			'stripe_use_eps'        => array( 'EPS', array( 'EUR' ), $ec_stripe_wide ),
			'stripe_use_fpx'        => array( 'FPX', array( 'MYR' ), array( 'MY' ) ),
			'stripe_use_giropay'    => array( 'giropay', array( 'EUR' ), $ec_stripe_wide ),
			'stripe_use_ideal'      => array( 'iDEAL', array( 'EUR' ), $ec_stripe_wide ),
			'stripe_use_p24'        => array( 'Przelewy24', array( 'EUR', 'PLN' ), $ec_stripe_wide ),
			'stripe_use_sofort'     => array( 'Sofort', array( 'EUR' ), $ec_stripe_wide ),
		),
	),
	'stripe_bank_debits'       => array(
		'title'   => __( 'Bank debits', 'wp-easycart' ),
		'desc'    => __( 'Payment is taken straight from the shopper\'s bank account.', 'wp-easycart' ),
		'methods' => array(
			'stripe_use_bacs' => array( 'Bacs Direct Debit', array( 'GBP' ), array( 'GB' ) ),
			'stripe_use_becs' => array( 'BECS Direct Debit', array( 'AUD' ), array( 'AU' ) ),
			'stripe_use_sepa' => array( 'SEPA Direct Debit', array( 'EUR' ), $ec_stripe_wide ),
		),
	),
	'stripe_realtime_payments' => array(
		'title'   => __( 'Real-time payments', 'wp-easycart' ),
		'desc'    => __( 'Shoppers pay by scanning a code with their banking app.', 'wp-easycart' ),
		'methods' => array(
			'stripe_use_pix'       => array( 'Pix', array( 'BRL' ), array( 'BR' ) ),
			'stripe_use_paynow'    => array( 'PayNow', array( 'SGD' ), array( 'SG' ) ),
			'stripe_use_promptpay' => array( 'PromptPay', array( 'THB' ), array( 'TH' ) ),
		),
	),
);
/* Every option ec_admin_save_stripe_connect_options() posts for a method: saved off here. */
$ec_stripe_method_options = array( 'ec_option_stripe_enable_apple_pay', 'ec_option_stripe_link', 'ec_option_stripe_alipay', 'ec_option_stripe_grabpay', 'ec_option_stripe_wechat', 'ec_option_stripe_affirm', 'ec_option_stripe_afterpay', 'ec_option_stripe_klarna', 'ec_option_stripe_bancontact', 'ec_option_stripe_blik', 'ec_option_stripe_eps', 'ec_option_stripe_fpx', 'ec_option_stripe_giropay', 'ec_option_stripe_enable_ideal', 'ec_option_stripe_p24', 'ec_option_stripe_sofort', 'ec_option_stripe_bacs', 'ec_option_stripe_becs', 'ec_option_stripe_sepa', 'ec_option_stripe_pix', 'ec_option_stripe_paynow', 'ec_option_stripe_promptpay', 'ec_option_stripe_boleto', 'ec_option_stripe_konbini', 'ec_option_stripe_oxxo', 'ec_option_stripe_disable_wallet_first' );

$ec_stripe_onepage = function_exists( 'wp_easycart_onepage_active' ) ? wp_easycart_onepage_active() : (bool) get_option( 'ec_option_onepage_checkout' );
?>
<div class="ec_admin_stripe_row ecsq ecsc">
	<div class="ec_admin_slider_row">
		<?php wp_easycart_admin()->preloader->print_preloader( 'ec_admin_stripe_display_loader' ); ?>
		<div class="ec_admin_slider_row_description">
			<div><?php esc_html_e( 'Stripe offers the ability to pay with a credit card directly on your website. Adding Stripe gives your shopping cart a more professional look and increases conversions.', 'wp-easycart' ); ?></div>
			<?php if ( $ec_stripe_connected ) { ?>
				<div class="ecsq-actions">
					<?php if ( $ec_stripe_has_live ) { ?>
						<a href="<?php echo esc_url( $ec_stripe_urls['live'] ); ?>"><?php esc_html_e( 'Switch live account', 'wp-easycart' ); ?></a>
					<?php } ?>
					<?php if ( $ec_stripe_has_test ) { ?>
						<a href="<?php echo esc_url( $ec_stripe_urls['test'] ); ?>"><?php esc_html_e( 'Switch test account', 'wp-easycart' ); ?></a>
					<?php } ?>
					<a href="<?php echo esc_url( ( 'test' === $ec_stripe_env ) ? 'https://dashboard.stripe.com/test/dashboard' : 'https://dashboard.stripe.com/dashboard' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Stripe dashboard', 'wp-easycart' ); ?> &#8599;</a>
					<?php if ( '' !== $ec_stripe_disconnect ) { ?>
						<a class="ecsq-danger" href="<?php echo esc_url( $ec_stripe_disconnect ); ?>" onclick="return window.confirm( <?php echo esc_attr( wp_json_encode( ( 'test' === $ec_stripe_env ) ? __( 'Disconnect your Stripe test account? Stripe stops taking payments here until you connect again.', 'wp-easycart' ) : __( 'Disconnect your live Stripe account? Stripe stops taking payments here until you connect again.', 'wp-easycart' ) ) ); ?> );"><?php echo esc_html( ( 'test' === $ec_stripe_env ) ? __( 'Disconnect test account', 'wp-easycart' ) : __( 'Disconnect', 'wp-easycart' ) ); ?></a>
					<?php } ?>
				</div>
			<?php } ?>
			<input type="hidden" name="use_stripe_connect" id="use_stripe_connect" value="<?php echo $ec_stripe_active ? 1 : 0; ?>" />
			<input type="hidden" name="ec_option_stripe_connect_use_sandbox" id="ec_option_stripe_connect_use_sandbox" value="<?php echo $ec_stripe_sandbox ? 1 : 0; ?>" />
		</div>

		<div class="ecsq-group">
			<div class="ecsq-group-t"><?php esc_html_e( 'Payments', 'wp-easycart' ); ?></div>
			<div class="ec_admin_toggles_wrap">
				<div class="ec_admin_toggle">
					<span><?php esc_html_e( 'Take live payments', 'wp-easycart' ); ?><small><?php echo esc_html( $ec_stripe_has_live ? __( 'Charges real cards through your connected Stripe account.', 'wp-easycart' ) : __( 'Connect your Stripe account to charge real cards.', 'wp-easycart' ) ); ?></small></span>
					<?php if ( ! $ec_stripe_has_live ) { ?><a href="<?php echo esc_url( $ec_stripe_urls['live'] ); ?>" aria-label="<?php esc_attr_e( 'Connect your live Stripe account', 'wp-easycart' ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return stripe_live_on_off();" class="ec_admin_slider_checkbox" value="1" id="ec_option_stripe_connect_enable_live"<?php checked( $ec_stripe_active && ! $ec_stripe_sandbox && $ec_stripe_has_live ); ?><?php echo $ec_stripe_has_live ? '' : ' tabindex="-1"'; ?> aria-label="<?php esc_attr_e( 'Take live payments', 'wp-easycart' ); ?>">
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ec_stripe_has_live ) { ?></a><?php } ?>
				</div>
				<div class="ec_admin_toggle">
					<span><?php esc_html_e( 'Test mode', 'wp-easycart' ); ?><small><?php echo esc_html( $ec_stripe_has_test ? __( 'Uses your Stripe test account; no card is charged.', 'wp-easycart' ) : __( 'Connect a Stripe test account to try your checkout; no card is charged.', 'wp-easycart' ) ); ?></small></span>
					<?php if ( ! $ec_stripe_has_test ) { ?><a href="<?php echo esc_url( $ec_stripe_urls['test'] ); ?>" aria-label="<?php esc_attr_e( 'Connect a Stripe test account', 'wp-easycart' ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return stripe_sandbox_on_off();" class="ec_admin_slider_checkbox" value="1" id="ec_option_stripe_connect_enable_sandbox"<?php checked( $ec_stripe_active && $ec_stripe_sandbox && $ec_stripe_has_test ); ?><?php echo $ec_stripe_has_test ? '' : ' tabindex="-1"'; ?> aria-label="<?php esc_attr_e( 'Test mode', 'wp-easycart' ); ?>">
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ec_stripe_has_test ) { ?></a><?php } ?>
				</div>
			</div>
			<?php if ( $ec_stripe_account ) { ?>
				<div class="ec_admin_toggle_note ecsq-status">
					<span class="ecsq-dot is-on" aria-hidden="true"></span>
					<span>
						<?php
						if ( '' !== $ec_stripe_account_name ) {
							/* translators: 1: Stripe account name, 2: Stripe account ID ( acct_… ). */
							echo esc_html( sprintf( $ec_stripe_sandbox ? __( 'Test account: %1$s ( %2$s ).', 'wp-easycart' ) : __( 'Live account: %1$s ( %2$s ).', 'wp-easycart' ), $ec_stripe_account_name, $ec_stripe_account->id ) );
						} else {
							/* translators: %s: Stripe account ID ( acct_… ). */
							echo esc_html( sprintf( $ec_stripe_sandbox ? __( 'Test account %s.', 'wp-easycart' ) : __( 'Live account %s.', 'wp-easycart' ), $ec_stripe_account->id ) );
						}
						?>
					</span>
				</div>
			<?php } elseif ( '' !== $ec_stripe_account_err ) { ?>
				<div class="ec_admin_toggle_note ecsq-status is-warn">
					<span class="ecsq-dot" aria-hidden="true"></span>
					<?php /* translators: %s: Stripe's reason. */ ?>
					<span><?php echo esc_html( sprintf( __( 'Stripe did not accept this connection: %s Reconnect the account.', 'wp-easycart' ), $ec_stripe_account_err ) ); ?></span>
				</div>
			<?php } elseif ( ! $ec_stripe_connected ) { ?>
				<div class="ec_admin_toggle_note"><?php esc_html_e( 'Once Stripe is connected, its currency, business country, payment methods and notifications are set here.', 'wp-easycart' ); ?></div>
			<?php } ?>
		</div>

		<?php if ( $ec_stripe_connected ) { ?>
			<div class="ecsq-group" id="ec_stripe_account">
				<div class="ecsq-group-t"><?php esc_html_e( 'Currency and country', 'wp-easycart' ); ?></div>
				<div class="ecsq-field">
					<label for="ec_option_stripe_currency"><?php esc_html_e( 'Currency', 'wp-easycart' ); ?></label>
					<select name="ec_option_stripe_currency" id="ec_option_stripe_currency" onchange="ec_admin_update_stripe_connect_display( this.value, jQuery( '#ec_option_stripe_company_country' ).val() );">
						<?php foreach ( $ec_stripe_currencies as $ec_stripe_code => $ec_stripe_label ) { ?>
							<option value="<?php echo esc_attr( $ec_stripe_code ); ?>"<?php selected( $ec_stripe_code, $ec_stripe_currency ); ?>><?php echo esc_html( $ec_stripe_label ); ?></option>
						<?php } ?>
					</select>
					<small><?php esc_html_e( 'The currency your prices are charged in.', 'wp-easycart' ); ?></small>
				</div>
				<?php if ( '' !== $ec_stripe_account_currency ) { ?>
					<div class="ec_method_deactivated" id="stripe_account_currency_note" data-currency="<?php echo esc_attr( $ec_stripe_account_currency ); ?>"<?php echo ( $ec_stripe_currency === $ec_stripe_account_currency ) ? ' style="display:none"' : ''; ?>>
						<?php /* translators: %s: the Stripe account's default currency code, e.g. USD. */ ?>
						<span><?php echo esc_html( sprintf( __( 'Your Stripe account\'s default currency is %s. Charging in another currency can limit the payment methods Stripe offers.', 'wp-easycart' ), $ec_stripe_account_currency ) ); ?></span>
					</div>
				<?php } ?>
				<div class="ecsq-field">
					<label for="ec_option_stripe_company_country"><?php esc_html_e( 'Business country', 'wp-easycart' ); ?></label>
					<select name="ec_option_stripe_company_country" id="ec_option_stripe_company_country" onchange="ec_admin_update_stripe_connect_display( jQuery( '#ec_option_stripe_currency' ).val(), this.value );">
						<?php foreach ( $ec_stripe_countries as $ec_stripe_code => $ec_stripe_label ) { ?>
							<option value="<?php echo esc_attr( $ec_stripe_code ); ?>"<?php selected( $ec_stripe_code, $ec_stripe_country ); ?>><?php echo esc_html( $ec_stripe_label ); ?></option>
						<?php } ?>
					</select>
					<small><?php esc_html_e( 'Where your business is registered with Stripe. It decides which local payment methods can be offered.', 'wp-easycart' ); ?></small>
				</div>
				<?php if ( '' !== $ec_stripe_account_country ) { ?>
					<div class="ec_method_deactivated" id="stripe_account_country_note" data-country="<?php echo esc_attr( $ec_stripe_account_country ); ?>"<?php echo ( $ec_stripe_country === $ec_stripe_account_country ) ? ' style="display:none"' : ''; ?>>
						<?php /* translators: %s: the Stripe account's country code, e.g. US. */ ?>
						<span><?php echo esc_html( sprintf( __( 'Your Stripe account is registered in %s. A different business country here can hide or break local payment methods.', 'wp-easycart' ), $ec_stripe_account_country ) ); ?></span>
					</div>
				<?php } ?>
			</div>

			<div class="ecsq-group" id="ec_stripe_methods">
				<div class="ecsq-group-t"><?php esc_html_e( 'Payment methods', 'wp-easycart' ); ?></div>
				<div class="ec_admin_toggles_wrap">
					<div class="ec_admin_toggle">
						<span><?php esc_html_e( 'Cards', 'wp-easycart' ); ?><small><?php esc_html_e( 'Visa, Mastercard, American Express and the other cards Stripe takes.', 'wp-easycart' ); ?></small></span>
						<em class="ecsq-state"><?php esc_html_e( 'Always on', 'wp-easycart' ); ?></em>
					</div>
					<div class="ec_admin_toggle ecsq-locked" id="stripe_use_applepay">
						<?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
						<span><?php esc_html_e( 'Apple Pay and Google Pay', 'wp-easycart' ); ?><small><?php echo esc_html( sprintf( __( 'Shoppers pay with the wallet on their phone or browser. Available with %s.', 'wp-easycart' ), $ec_stripe_plan ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
					</div>
					<div class="ec_admin_toggle ecsq-locked" id="stripe_use_link">
						<?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
						<span><?php esc_html_e( 'Link', 'wp-easycart' ); ?><small><?php echo esc_html( sprintf( __( 'Returning shoppers pay with the details Stripe\'s Link saved for them. Available with %s.', 'wp-easycart' ), $ec_stripe_plan ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
					</div>
					<?php
					foreach ( $ec_stripe_families as $ec_stripe_family_id => $ec_stripe_family ) {
						$ec_stripe_family_cur = array();
						$ec_stripe_family_cty = array();
						$ec_stripe_family_on  = false;
						foreach ( $ec_stripe_family['methods'] as $ec_stripe_method ) {
							$ec_stripe_family_cur = array_merge( $ec_stripe_family_cur, $ec_stripe_method[1] );
							$ec_stripe_family_cty = array_merge( $ec_stripe_family_cty, $ec_stripe_method[2] );
							if ( in_array( $ec_stripe_currency, $ec_stripe_method[1], true ) && in_array( $ec_stripe_country, $ec_stripe_method[2], true ) ) {
								$ec_stripe_family_on = true;
							}
						}
						?>
						<div id="<?php echo esc_attr( $ec_stripe_family_id ); ?>" class="ecsq-method ec_admin_stripe_section" data-currencies="<?php echo esc_attr( implode( ',', array_unique( $ec_stripe_family_cur ) ) ); ?>" data-countries="<?php echo esc_attr( implode( ',', array_unique( $ec_stripe_family_cty ) ) ); ?>"<?php echo $ec_stripe_family_on ? '' : ' style="display:none;"'; ?>>
							<div class="ec_admin_toggle ecsq-locked">
								<span>
									<?php echo esc_html( $ec_stripe_family['title'] ); ?>
									<?php /* translators: 1: what the payment methods do, 2: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
									<small><?php echo esc_html( sprintf( __( '%1$s Available with %2$s.', 'wp-easycart' ), $ec_stripe_family['desc'], $ec_stripe_plan ) ); ?></small>
									<span class="ecsq-tags">
										<?php foreach ( $ec_stripe_family['methods'] as $ec_stripe_row_id => $ec_stripe_method ) { ?>
											<span id="<?php echo esc_attr( $ec_stripe_row_id ); ?>" class="ec_admin_stripe_settings_row" data-currencies="<?php echo esc_attr( implode( ',', $ec_stripe_method[1] ) ); ?>" data-countries="<?php echo esc_attr( implode( ',', $ec_stripe_method[2] ) ); ?>"<?php echo ( in_array( $ec_stripe_currency, $ec_stripe_method[1], true ) && in_array( $ec_stripe_country, $ec_stripe_method[2], true ) ) ? '' : ' style="display:none;"'; ?>><?php echo esc_html( $ec_stripe_method[0] ); ?></span>
										<?php } ?>
									</span>
								</span>
								<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
							</div>
						</div>
					<?php } ?>
				</div>
				<?php foreach ( $ec_stripe_method_options as $ec_stripe_option ) { ?>
					<input type="hidden" name="<?php echo esc_attr( $ec_stripe_option ); ?>" id="<?php echo esc_attr( $ec_stripe_option ); ?>" value="0" />
				<?php } ?>
				<input type="hidden" name="ec_option_stripe_pay_later_minimum" id="ec_option_stripe_pay_later_minimum" value="<?php echo esc_attr( get_option( 'ec_option_stripe_pay_later_minimum' ) ); ?>" />
			</div>

			<div class="ecsq-group" id="ec_stripe_checkout">
				<div class="ecsq-group-t"><?php esc_html_e( 'Checkout', 'wp-easycart' ); ?></div>
				<div class="ec_admin_toggles_wrap">
					<?php if ( $ec_stripe_onepage ) { ?>
						<div class="ec_admin_toggle">
							<span><?php esc_html_e( 'Suggest addresses as shoppers type', 'wp-easycart' ); ?><small><?php esc_html_e( 'Stripe\'s address autocomplete on the one-page checkout. It works with Link.', 'wp-easycart' ); ?></small></span>
							<label class="ec_admin_switch">
								<input type="checkbox" onchange="this.value = this.checked ? '1' : '0'; ec_admin_save_stripe_connect_options();" class="ec_admin_slider_checkbox" value="<?php echo get_option( 'ec_option_stripe_address_autocomplete' ) ? '1' : '0'; ?>" id="ec_option_stripe_address_autocomplete" name="ec_option_stripe_address_autocomplete"<?php checked( (bool) get_option( 'ec_option_stripe_address_autocomplete' ) ); ?> aria-label="<?php esc_attr_e( 'Suggest addresses as shoppers type', 'wp-easycart' ); ?>">
								<span class="ec_admin_slider round"></span>
							</label>
						</div>
					<?php } ?>
					<div class="ec_admin_toggle ecsq-locked">
						<?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
						<span><?php esc_html_e( 'Payment form style', 'wp-easycart' ); ?><small><?php echo esc_html( sprintf( __( 'Stripe\'s standard look. The night, flat and unstyled looks come with %s.', 'wp-easycart' ), $ec_stripe_plan ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
					</div>
					<div class="ec_admin_toggle ecsq-locked">
						<?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
						<span><?php esc_html_e( 'Payment methods as a list', 'wp-easycart' ); ?><small><?php echo esc_html( sprintf( __( 'Payment methods show as tabs. The stacked accordion list comes with %s.', 'wp-easycart' ), $ec_stripe_plan ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
					</div>
				</div>
				<?php if ( ! $ec_stripe_onepage ) { ?>
					<input type="hidden" name="ec_option_stripe_address_autocomplete" id="ec_option_stripe_address_autocomplete" value="<?php echo esc_attr( (int) get_option( 'ec_option_stripe_address_autocomplete' ) ); ?>" />
				<?php } ?>
				<input type="hidden" name="ec_option_stripe_payment_theme" id="ec_option_stripe_payment_theme" value="stripe" />
				<input type="hidden" name="ec_option_stripe_payment_layout" id="ec_option_stripe_payment_layout" value="tabs" />
			</div>

			<div class="ecsq-group" id="ec_stripe_subscriptions">
				<div class="ecsq-group-t"><?php esc_html_e( 'Subscriptions', 'wp-easycart' ); ?></div>
				<div class="ec_admin_toggles_wrap">
					<div class="ec_admin_toggle ecsq-locked">
						<?php /* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
						<span><?php esc_html_e( 'Email subscribers before each renewal', 'wp-easycart' ); ?><small><?php echo esc_html( sprintf( __( 'A reminder a few days before Stripe charges a renewal. Available with %s.', 'wp-easycart' ), $ec_stripe_plan ) ); ?></small></span>
						<span class="ecsq-pro-chip"><?php echo esc_html( $ec_stripe_badge ); ?></span>
					</div>
				</div>
				<input type="hidden" name="ec_option_stripe_subscription_notices" id="ec_option_stripe_subscription_notices" value="0" />
			</div>

			<?php
			global $wpdb;
			$ec_stripe_heard = (bool) $wpdb->get_var( 'SELECT webhook_id FROM ec_webhook LIMIT 1' );
			?>
			<div class="ecsq-group" id="ec_stripe_webhooks">
				<div class="ecsq-group-t"><?php esc_html_e( 'Notifications from Stripe', 'wp-easycart' ); ?></div>
				<div class="ec_admin_toggle_note ecsq-status<?php echo $ec_stripe_heard ? '' : ' is-warn'; ?>">
					<span class="ecsq-dot<?php echo $ec_stripe_heard ? ' is-on' : ''; ?>" aria-hidden="true"></span>
					<span>
						<?php
						if ( $ec_stripe_heard ) {
							esc_html_e( 'Stripe has sent this store notifications, so refunds, disputes and delayed payments reach your orders.', 'wp-easycart' );
						} else {
							esc_html_e( 'No notification from Stripe has arrived yet. Add the webhook address below to your Stripe account so refunds, disputes and delayed payments reach your orders.', 'wp-easycart' );
						}
						?>
						<a href="<?php echo esc_url( wp_easycart_admin()->helpsystem->print_docs_url( 'settings', 'payment', 'stripe' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'How to add it', 'wp-easycart' ); ?></a>
					</span>
				</div>
				<div class="ecsq-field">
					<label for="stripe_webhook_url"><?php esc_html_e( 'Webhook address', 'wp-easycart' ); ?></label>
					<div class="ecsq-copy">
						<input type="text" id="stripe_webhook_url" value="<?php echo esc_url( wp_easycart_hook_url( 'stripe-webhook' ) ); ?>" readonly="readonly" />
						<button type="button" class="ecsq-copy-btn" onclick="ec_admin_copy_stripe_webhook(); return false;"><?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
					</div>
					<small><?php esc_html_e( 'In Stripe, add it under Developers, Webhooks, for the events your store uses.', 'wp-easycart' ); ?></small>
					<span class="ecsq-copied" id="stripe_webhook_copied" style="display:none;"><?php esc_html_e( 'Copied to your clipboard.', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecsq-field">
					<label for="ec_option_stripe_connect_webhook_secret"><?php esc_html_e( 'Signing secret', 'wp-easycart' ); ?></label>
					<input type="text" name="ec_option_stripe_connect_webhook_secret" id="ec_option_stripe_connect_webhook_secret" value="<?php echo esc_attr( get_option( 'ec_option_stripe_connect_webhook_secret' ) ); ?>" placeholder="whsec_..." autocomplete="off" spellcheck="false" />
					<small><?php esc_html_e( 'From the webhook\'s page in Stripe. Once it is saved, a notification without Stripe\'s signature is refused.', 'wp-easycart' ); ?></small>
				</div>
				<?php /* The drawer's Save button runs this ( one ec_admin_save_ button = footer save ); hidden by the drawer. */ ?>
				<input type="button" onclick="ec_admin_save_stripe_connect_options();" class="ecsq-save" value="<?php esc_attr_e( 'Save', 'wp-easycart' ); ?>" />
			</div>
		<?php } ?>
	</div>
</div>
