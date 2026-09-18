<?php

class ec_optionitem{
	
	public $option_id;									// INT
	public $optionitem_id;								// INT
	public $optionitem_name;							// VARCHAR 33
	public $optionitem_enable_custom_price_label;		// BOOL
	public $optionitem_custom_price_label;				// TEXT
	public $optionitem_price;							// FLOAT 7,2
	public $optionitem_price_multiplier;				// FLOAT 7,2
	public $optionitem_price_onetime;					// FLOAT 7,2
	public $optionitem_icon;							// VARCHAR 512
	public $optionitem_initially_selected;				// BOOL
	
	function __construct( $option_id, $optionitem_data ){
		$this->option_id = $option_id;
		$this->optionitem_id = $optionitem_data->optionitem_id;
		$this->optionitem_name = wp_easycart_language( )->convert_text( $optionitem_data->optionitem_name );
		$this->optionitem_enable_custom_price_label = (int) $optionitem_data->optionitem_enable_custom_price_label;
		$this->optionitem_custom_price_label = wp_easycart_language( )->convert_text( $optionitem_data->optionitem_custom_price_label );
		$this->optionitem_price = $optionitem_data->optionitem_price;
		$this->optionitem_price_onetime  = $optionitem_data->optionitem_price_onetime ;
		$this->optionitem_price_multiplier = $optionitem_data->optionitem_price_multiplier;
		$this->optionitem_icon = $optionitem_data->optionitem_icon;
		$this->optionitem_initially_selected = $optionitem_data->optionitem_initially_selected;
	}
	
	/* ------------------------------------------------------------------ */
	/* Color swatches                                                     */
	/* optionitem_icon may hold "#rrggbb" or "#rrggbb,#rrggbb" ( two-tone ) */
	/* instead of an image filename / URL. These helpers let every         */
	/* renderer treat a color exactly like an image.                      */
	/* ------------------------------------------------------------------ */

	/** @return array|false  List of 1–2 normalised "#rrggbb" strings, or false when $icon is not a color. */
	public static function swatch_colors( $icon ) {
		$icon = trim( (string) $icon );
		if ( '' === $icon || '#' !== $icon[0] ) {
			return false;
		}
		$parts = array_map( 'trim', explode( ',', $icon ) );
		if ( count( $parts ) > 2 ) {
			return false;
		}
		$out = array();
		foreach ( $parts as $c ) {
			if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $c ) ) {
				return false;
			}
			if ( 4 === strlen( $c ) ) {
				$c = '#' . $c[1] . $c[1] . $c[2] . $c[2] . $c[3] . $c[3];
			}
			$out[] = strtolower( $c );
		}
		return $out;
	}

	/**
	 * Inline SVG data URI for a color swatch, sized to the store's swatch setting so it
	 * drops into the existing <img class="ec_product_swatch"> markup unchanged.
	 * @return string|false
	 */
	public static function swatch_image_src( $icon, $size = 'small' ) {
		$colors = self::swatch_colors( $icon );
		if ( ! $colors ) {
			return false;
		}
		$w = (int) get_option( 'ec_option_swatch_' . $size . '_width' );
		$h = (int) get_option( 'ec_option_swatch_' . $size . '_height' );
		return self::swatch_color_data_uri( $colors, $w, $h );
	}

	/**
	 * SVG data URI for 1–2 validated "#rrggbb" colors ( see swatch_colors() ).
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public static function swatch_color_data_uri( $colors, $w = 30, $h = 30 ) {
		$w = (int) $w > 0 ? (int) $w : 30;
		$h = (int) $h > 0 ? (int) $h : 30;
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" shape-rendering="crispEdges">';
		$svg .= '<rect width="' . $w . '" height="' . $h . '" fill="' . $colors[0] . '"/>';
		if ( 2 === count( $colors ) ) {
			/* diagonal split: top-left = first color, bottom-right = second */
			$svg .= '<polygon points="' . $w . ',0 ' . $w . ',' . $h . ' 0,' . $h . '" fill="' . $colors[1] . '"/>';
		}
		/* hairline edge so white / very light chips stay visible on a white page */
		$svg .= '<rect x="0.5" y="0.5" width="' . ( $w - 1 ) . '" height="' . ( $h - 1 ) . '" fill="none" stroke="#000" stroke-opacity="0.15"/>';
		$svg .= '</svg>';
		return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg );
	}

	/**
	 * The src for any stored swatch: a color ( "#rrggbb" / "#rrggbb,#rrggbb" ) becomes an SVG data URI
	 * of $px × $px, a full URL is used as is, and anything else is a legacy file name under
	 * wp-easycart-data/products/swatches. Storefront templates echo this through esc_attr().
	 *
	 * @since 6.0.0
	 * @param string $icon optionitem_icon.
	 * @param int    $px   Swatch size in pixels ( option_meta swatch_size ).
	 * @return string
	 */
	public static function swatch_src( $icon, $px = 30 ) {
		$icon   = trim( (string) $icon );
		$colors = self::swatch_colors( $icon );
		if ( $colors ) {
			return self::swatch_color_data_uri( $colors, $px, $px );
		}
		if ( 'http://' === substr( $icon, 0, 7 ) || 'https://' === substr( $icon, 0, 8 ) ) {
			return $icon;
		}
		return plugins_url( '/wp-easycart-data/products/swatches/' . $icon, EC_PLUGIN_DATA_DIRECTORY );
	}

	/** Protocol list for esc_url() that also permits the data: URIs produced above. */
	public static function swatch_url_protocols() {
		return array_merge( wp_allowed_protocols(), array( 'data' ) );
	}

	public function get_optionitem_label( ){
		if($this->optionitem_price != 0.00){
			if( $this->optionitem_price > 0.00 ){
				return $this->optionitem_name . " (+" . $GLOBALS['currency']->get_currency_display( $this->optionitem_price ) . ")";
			}else{
				return $this->optionitem_name . " (" . $GLOBALS['currency']->get_currency_display( $this->optionitem_price ) . ")";
			}
		}else{
			return $this->optionitem_name;
		}
	}

}

?>