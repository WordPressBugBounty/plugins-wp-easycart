<?php 

class ec_currency{
	
	/* Keep for backward compatibility */
	public $symbol;
	public $symbol_location;
	public $negative_location;
	public $decimal_symbol;
	public $decimal_length;
	public $grouping_symbol;
	public $currency_code;
	public $show_currency_code;
	public $conversion_rate;
	/* end */
	
	public static $static_symbol;
	public static $static_symbol_location;
	public static $static_negative_location;
	public static $static_decimal_symbol;
	public static $static_decimal_length;
	public static $static_grouping_symbol;
	public static $static_currency_code;
	public static $static_show_currency_code;
	public static $static_conversion_rate;
	/** 6.0.5: a non-breaking space between the symbol and the amount ( ec_option_currency_symbol_space: 93,00 €, CHF 12.00 ). */
	public static $static_symbol_space = false;
	
	function __construct( ){
		/* 6.0.5 ( FREE #333 ): the symbol as characters. The setup wizard saved it as an HTML entity ( &pound;, &euro;, &#107;&#114; ),
		   which pages that print it into HTML show right but scripts and plain text showed as the code ( Reports: &pound;125.55 ). */
		self::$static_symbol = $symbol = self::plain_symbol( get_option( 'ec_option_currency' ) );
		self::$static_symbol_location = $symbol_location = get_option( 'ec_option_currency_symbol_location' );
		self::$static_symbol_space = (bool) get_option( 'ec_option_currency_symbol_space' );
		self::$static_negative_location = $negative_location = get_option( 'ec_option_currency_negative_location' );
		self::$static_decimal_symbol = $decimal_symbol = get_option( 'ec_option_currency_decimal_symbol' );
		if(  get_option( 'ec_option_currency_decimal_places' ) == '' || is_nan(  get_option( 'ec_option_currency_decimal_places' ) ) ||  get_option( 'ec_option_currency_decimal_places' ) < 0 ){
			self::$static_decimal_length = $decimal_length = 2;
		}else{
			self::$static_decimal_length = $decimal_length = get_option( 'ec_option_currency_decimal_places' );
		}
		self::$static_grouping_symbol = $grouping_symbol = get_option( 'ec_option_currency_thousands_seperator' );
		self::$static_currency_code = $currency_code = get_option( 'ec_option_base_currency' );
		self::$static_show_currency_code = $show_currency_code = get_option( 'ec_option_show_currency_code' );
		self::$static_conversion_rate = $conversion_rate = 1.000;
		
		if( ( !is_admin( ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) && isset( $_COOKIE['ec_convert_to'] ) ){
			$from = strtoupper( get_option( 'ec_option_base_currency' ) );
			$to = substr( preg_replace( '/[^A-Z]/', '', strtoupper( sanitize_text_field( $_COOKIE['ec_convert_to'] ) ) ), 0, 3 );
			self::$static_currency_code = $to;
			if( $to != $from ){
				if( get_option( 'ec_option_payment_third_party' ) == 'payfort' && get_option( 'ec_option_payfort_use_currency_service' ) ){
					$payfort = new ec_payfort( );
					$exchange_rate = $payfort->convert_price( 1 );
					$rates = array( $to . "=" . $exchange_rate );
				}else{
					$rates = explode( ",", get_option( 'ec_option_exchange_rates' ) );
				}
				//Set Symbol
				self::$static_symbol = self::get_currency_symbol( $to );
				//Get rate from string
				for( $i=0; $i<count( $rates ); $i++ ){
					$rate = explode( "=", $rates[$i] );
					if( $rate[0] == $to ){
						self::$static_conversion_rate = $rate[1];
						break;
					}
				}
			}else{ self::$static_symbol = self::get_currency_symbol( $to ); }
		}
		/* 6.0.5: made again while an order's amounts are being printed ( wp_easycart_order_currency::push() ): keep the order's format. */
		if ( class_exists( 'wp_easycart_order_currency' ) ) {
			wp_easycart_order_currency::reapply();
		}
	}
	
	public static function get_stats_currency_display( $amount ){
		$display_amount = '';
		
		//no currency code display for stats
		
		if( $amount < 0 && self::$static_negative_location )
			$display_amount .= '-';
		
		if( self::$static_symbol_location )
			$display_amount .= self::get_display_symbol( );
			
		if( $amount < 0 && !self::$static_negative_location )
			$display_amount .= '-';
		
		if( $amount < 0 )
		$amount = $amount * -1;
		
		$amount = floatval( $amount );
		$amount = $amount * self::$static_conversion_rate;
		$display_amount .= number_format( $amount, self::$static_decimal_length, self::$static_decimal_symbol, self::$static_grouping_symbol );
		
		if( !self::$static_symbol_location )
			$display_amount .= self::get_display_symbol( );
			
		return $display_amount;
	}
	
	
	public static function get_currency_display( $amount, $convert = true ){
		$display_amount = '';
		
		if( self::$static_show_currency_code )
			$display_amount .= self::$static_currency_code . ' ';
		
		if( $amount < 0 && self::$static_negative_location )
			$display_amount .= '-';
		
		if( self::$static_symbol_location )
			$display_amount .= self::get_display_symbol( );
			
		if( $amount < 0 && !self::$static_negative_location )
			$display_amount .= '-';
		
		if( $amount < 0 )
		$amount = $amount * -1;
		
		$amount = floatval( $amount );
		if( $convert )
			$amount = $amount * self::$static_conversion_rate;
		$display_amount .= number_format( $amount, self::$static_decimal_length, self::$static_decimal_symbol, self::$static_grouping_symbol );
		
		if( !self::$static_symbol_location )
			$display_amount .= self::get_display_symbol( );
			
		return $display_amount;
	}
	
	public static function get_number_only( $amount ){
		$amount = floatval( $amount );
		//convert if needed
		$amount = $amount * self::$static_conversion_rate;
		return number_format( $amount, self::$static_decimal_length, self::$static_decimal_symbol, '' );
	}
	
	public static function get_number_safe( $amount ){
		$amount = floatval( $amount );
		//convert if needed
		$amount = $amount * self::$static_conversion_rate;
		return number_format( $amount, self::$static_decimal_length, '.', '' );
	}
	
	public static function format_cents( $cents ){
		return substr( $cents, 0, self::$static_decimal_length );
	}
	
	public static function convert_price( $amount ){
		$amount = number_format( $amount * self::$static_conversion_rate, self::$static_decimal_length, '.', '' );
		return $amount;
	}
	
	public static function get_currency_symbol( $static_currency_code ){
		$currencies = array( 'AED' => 'د.إ',
							 'ALL' => 'Lek',
							 'AFN' => '؋', 
							 'ARS' => '$',
							 'AWG' => 'ƒ',
							 'AUD' => '$', 
							 'AZN' => 'ман', 
							 'BSD' => '$', 
							 'BBD' => '$',
							 'BYR' => 'p.', 
							 'BZD' => 'BZ$', 
							 'BMD' => '$',
							 'BOB' => '$b',	 
							 'BAM' => 'KM', 
							 'BWP' => 'P', 
							 'BGN' => 'лв', 
							 'BRL' => 'R$',
							 'BND' => '$',
							 'KHR' => '៛	៛',
							 'CAD' => '$',
							 'KYD' => '$',
							 'CLP' => '$',
							 'CNY' => '¥',
							 'COP' => '$',
							 'CRC' => '₡', 
							 'HRK' => 'kn',
							 'CUP' => '₱',	 
							 'CZK' => 'Kč', 
							 'DKK' => 'kr',
							 'DOP' => 'RD$',
							 'XCD' => '$', 
							 'EGP' => '£', 
							 'SVC' => '$', 
							 'EEK' => 'kr',
							 'EUR' => '€', 
							 'FKP' => '£',
							 'FJD' => '$',
							 'GHC' => '¢', 
							 'GIP' => '£',	 
							 'GTQ' => 'Q', 
							 'GGP' => '£',	 
							 'GYD' => '$', 
							 'HNL' => 'L',
							 'HKD' => '$',
							 'HUF' => 'Ft',
							 'ISK' => 'kr',
							 'INR' => 'INR',
							 'IDR' => 'Rp',
							 'IRR' => '﷼',
							 'IMP' => '£',
							 'ILS' => '₪', 
							 'JMD' => 'J$',
							 'JPY' => '¥',
							 'JEP' => '£',
							 'KZT' => 'лв',
							 'KPW' => '₩', 
							 'KRW' => '₩',
							 'KGS' => 'лв', 
							 'LAK' => '₭',
							 'LVL' => 'Ls',
							 'LBP' => '£',
							 'LRD' => '$',
							 'LTL' => 'Lt',
							 'MKD' => 'ден',
							 'MYR' => 'RM',
							 'MUR' => '₨',
							 'MXN' => '$',
							 'MNT' => '₮',
							 'MZN' => 'MT',
							 'NAD' => '$',
							 'NPR' => '₨',
							 'ANG' => 'ƒ',
							 'NZD' => '$',
							 'NIO' => 'C$',
							 'NGN' => '₦',
							 'KPW' => '₩',
							 'NOK' => 'kr',
							 'OMR' => '﷼',
							 'PKR' => '₨',
							 'PAB' => 'B/.',
							 'PYG' => 'Gs',
							 'PEN' => 'S/.',
							 'PHP' => '₱',
							 'PLN' => 'zł',
							 'QAR' => '﷼',
							 'RON' => 'lei',
							 'RUB' => 'руб',
							 'SHP' => '£',
							 'SAR' => '﷼',
							 'RSD' => 'Дин.',
							 'SCR' => '₨',
							 'SGD' => '$',
							 'SBD' => '$',
							 'SOS' => 'S',
							 'ZAR' => 'R',
							 'KRW' => '₩',
							 'LKR' => '₨',
							 'SEK' => 'kr',
							 'CHF' => 'CHF',
							 'SRD' => '$',
							 'SYP' => '£',
							 'TWD' => 'NT$',
							 'THB' => '฿',
							 'TTD' => 'TT$',
							 'TRY' => 'TRY',
							 'TRL' => '₤',
							 'TVD' => '$',
							 'UAH' => '₴',
							 'GBP' => '£',
							 'USD' => '$',
							 'UYU' => '$U',
							 'UZS' => 'лв',
							 'VEF' => 'Bs',
							 'VND' => '₫',
							 'YER' => '﷼',
							 'ZWD' => 'Z$'
							);
							
		return $currencies[$static_currency_code];
	}
	
	public static function get_symbol( ){
		return self::$static_symbol;
	}
	/**
	 * A currency symbol as characters: one saved as an HTML entity ( &pound;, &#107;&#114; ) is decoded; one already
	 * in characters is returned as it is.
	 *
	 * @since 6.0.5
	 * @param string $symbol Symbol.
	 * @return string
	 */
	public static function plain_symbol( $symbol ){
		$symbol = (string) $symbol;
		if ( false === strpos( $symbol, '&' ) ) {
			return $symbol;
		}
		return html_entity_decode( $symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	/**
	 * The saved symbol as characters, once. The setup wizard saved an HTML entity before 6.0.5 ( &pound; ): prices read it
	 * through plain_symbol(), but Settings › Store details showed the code in the symbol box, and a merchant took it for a
	 * setting they had broken. Runs on admin_init and on activation; does nothing once the symbol is characters.
	 *
	 * @since 6.0.5
	 * @return bool Whether the saved symbol changed.
	 */
	public static function repair_saved_symbol(){
		$saved = get_option( 'ec_option_currency' );
		if ( ! is_string( $saved ) || false === strpos( $saved, '&' ) ) {
			return false;
		}
		$plain = self::plain_symbol( $saved );
		if ( $plain === $saved || '' === trim( $plain ) ) {
			return false;
		}
		update_option( 'ec_option_currency', $plain );
		return true;
	}
	/**
	 * The symbol as prices print it: with a non-breaking space on the amount's side when the store writes one ( 6.0.5,
	 * ec_option_currency_symbol_space ). The product templates hand it to ec_details_format_money_v2(), so prices the page
	 * redraws keep the space.
	 *
	 * @param string|null $symbol A symbol ( the store's when null ).
	 * @return string
	 */
	public static function get_display_symbol( $symbol = null ){
		$symbol = ( null === $symbol ) ? (string) self::$static_symbol : (string) $symbol;
		if ( ! self::$static_symbol_space || '' === $symbol ) {
			return $symbol;
		}
		return self::$static_symbol_location ? $symbol . "\xC2\xA0" : "\xC2\xA0" . $symbol;
	}
	public static function get_symbol_space( ){
		return self::$static_symbol_space;
	}
	/**
	 * How a country writes amounts, its usual style ( CLDR ): the symbol before or after, a space between them, the decimal and
	 * thousands separators. The setup wizard and Settings › Store details' usual format read it ( 6.0.5, admin review M9:
	 * Germany writes 1.234,56 €, France 1 234,56 €, the Netherlands € 1.234,56, Switzerland CHF 1'234.56 ).
	 *
	 * @param string $country ISO code.
	 * @param string $locale  WordPress locale ( fr_CA and fr_BE write as French does ), '' for the site's.
	 * @return array|null before ( bool ), space ( bool ), dec, thou; null for a country it does not list
	 */
	public static function country_format( $country, $locale = '' ){
		$nb  = "\xC2\xA0";
		$uk  = array( 1, 0, '.', ',' );
		$map = array(
			'US' => $uk, 'CA' => $uk, 'GB' => $uk, 'IE' => $uk, 'MT' => $uk, 'AU' => $uk, 'NZ' => $uk, 'JP' => $uk, 'MX' => $uk, 'IN' => $uk,
			'SG' => $uk, 'HK' => $uk, 'ZA' => $uk, 'TH' => $uk, 'PH' => $uk, 'MY' => $uk, 'CN' => $uk, 'KR' => $uk, 'IL' => $uk, 'AE' => $uk,
			'DE' => array( 0, 1, ',', '.' ), 'AT' => array( 1, 1, ',', '.' ), 'CH' => array( 1, 1, '.', "'" ), 'LI' => array( 1, 1, '.', "'" ),
			'FR' => array( 0, 1, ',', $nb ), 'BE' => array( 1, 1, ',', '.' ), 'LU' => array( 0, 1, ',', '.' ), 'NL' => array( 1, 1, ',', '.' ),
			'ES' => array( 0, 1, ',', '.' ), 'IT' => array( 0, 1, ',', '.' ), 'PT' => array( 0, 1, ',', $nb ), 'GR' => array( 0, 1, ',', '.' ),
			'CY' => array( 0, 1, ',', '.' ), 'FI' => array( 0, 1, ',', $nb ), 'EE' => array( 0, 1, ',', $nb ), 'LV' => array( 0, 1, ',', $nb ),
			'LT' => array( 0, 1, ',', $nb ), 'SK' => array( 0, 1, ',', $nb ), 'SI' => array( 0, 1, ',', '.' ), 'HR' => array( 0, 1, ',', '.' ),
			'DK' => array( 0, 1, ',', '.' ), 'SE' => array( 0, 1, ',', $nb ), 'NO' => array( 0, 1, ',', $nb ), 'IS' => array( 0, 1, ',', '.' ),
			'PL' => array( 0, 1, ',', $nb ), 'CZ' => array( 0, 1, ',', $nb ), 'HU' => array( 0, 1, ',', $nb ), 'RO' => array( 0, 1, ',', '.' ),
			'BG' => array( 0, 1, ',', $nb ), 'BR' => array( 1, 1, ',', '.' ), 'TR' => array( 1, 0, ',', '.' ),
		);
		$country = strtoupper( (string) $country );
		$locale  = ( '' === (string) $locale && function_exists( 'get_locale' ) ) ? (string) get_locale() : (string) $locale;
		/* French in Canada and Belgium writes as France does. */
		if ( ( 'CA' === $country && 0 === strpos( $locale, 'fr_' ) ) || ( 'BE' === $country && 0 === strpos( $locale, 'fr_' ) ) ) {
			$map[ $country ] = $map['FR'];
		}
		$format = isset( $map[ $country ] ) ? array( 'before' => (bool) $map[ $country ][0], 'space' => (bool) $map[ $country ][1], 'dec' => $map[ $country ][2], 'thou' => $map[ $country ][3] ) : null;
		/**
		 * How a country writes amounts.
		 *
		 * @since 6.0.5
		 * @param array|null $format  before, space, dec, thou ( null = not known ).
		 * @param string     $country ISO code.
		 * @param string     $locale  WordPress locale.
		 */
		return function_exists( 'apply_filters' ) ? apply_filters( 'wp_easycart_currency_country_format', $format, $country, $locale ) : $format;
	}
	public static function get_symbol_location( ){
		return self::$static_symbol_location;
	}
	public static function get_negative_location( ){
		return self::$static_negative_location;
	}
	public static function get_decimal_symbol( ){
		return self::$static_decimal_symbol;
	}
	public static function get_decimal_length( ){
		return self::$static_decimal_length;
	}
	public static function get_grouping_symbol( ){
		return self::$static_grouping_symbol;
	}
	/**
	 * The shopper's display currency ( ec_convert_to cookie ). Cart, order and analytics amounts are in the base currency: wp_easycart_base_currency_code().
	 *
	 * @return string
	 */
	public static function get_currency_code( ){
		return self::$static_currency_code;
	}
	public static function get_show_currency_code( ){
		return self::$static_show_currency_code;
	}
	public static function get_conversion_rate( ){
		return self::$static_conversion_rate;
	}
}

if ( ! function_exists( 'wp_easycart_base_currency_code' ) ) {
	/**
	 * ISO 4217 code of the store's base currency ( ec_option_base_currency ), the currency every cart and order amount is in.
	 *
	 * Analytics events send it with those amounts. ec_currency::get_currency_code() is the shopper's display currency, and
	 * ec_option_currency is the symbol, never the code.
	 *
	 * @since 6.0.2
	 * @return string Three upper case letters, USD when the option holds anything else.
	 */
	function wp_easycart_base_currency_code() {
		$code = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) get_option( 'ec_option_base_currency', 'USD' ) ), 0, 3 ) );
		return ( 3 === strlen( $code ) ) ? $code : 'USD';
	}
}

?>