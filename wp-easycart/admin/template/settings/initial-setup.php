<?php
/**
 * Settings › Store details ( V2 declaration ).
 *
 * Replaces the classic "Initial Setup" page ( slug kept as `initial-setup` so old
 * links keep working; load_settings_content() still sends the slug to the setup
 * wizard until ec_option_setup_wizard_done is set ). Holds the three pages the
 * store runs on, price formatting, the admin sales goal and the demo data
 * installer. The wizard itself is untouched; this page links to it.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_store_details_page_title' ) ) {
	/** A page's title for a chip, or "(no title) #id" when it has none. */
	function ecv2_store_details_page_title( $id ) {
		$title = function_exists( 'get_the_title' ) ? trim( (string) get_the_title( (int) $id ) ) : '';
		/* translators: %d: page id */
		return '' !== $title ? $title : sprintf( __( '(no title) #%d', 'wp-easycart' ), (int) $id );
	}
}

if ( ! function_exists( 'ecv2_store_details_search_pages' ) ) {
	/**
	 * 'search_callback' for the three page pickers: published pages whose title or
	 * content matches the typed text, twenty at a time, so a site with tens of
	 * thousands of pages never loads them all ( the classic page called get_pages() ).
	 * Only ids are fetched; titles come from the primed post cache. An empty term
	 * lists the most recently edited pages.
	 *
	 * @since 6.0.0
	 * @return array rows of value / label / hint
	 */
	function ecv2_store_details_search_pages( $term, $field, $limit ) {
		if ( ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$term = trim( (string) $term );
		$args = array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby'                => ( '' === $term ) ? 'modified' : 'relevance',
			'order'                  => 'DESC',
		);
		if ( '' !== $term ) {
			$args['s'] = $term;
		}
		$query = new WP_Query( $args );
		$ids   = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
		if ( empty( $ids ) ) {
			return array();
		}
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, false );
		}
		$rows = array();
		foreach ( $ids as $id ) {
			$post   = get_post( $id );
			$rows[] = array( 'value' => (string) $id, 'label' => ecv2_store_details_page_title( $id ), 'hint' => ( $post && '' !== (string) $post->post_name ) ? '/' . $post->post_name : '' );
		}
		return $rows;
	}
}

if ( ! function_exists( 'ecv2_store_details_lookup_pages' ) ) {
	/**
	 * 'validate_callback' for the page pickers: the page behind a posted or stored id,
	 * whatever its status ( the chip says so when it is not published, as the classic
	 * select did ), so the setting never silently shows the wrong page. Only pages count.
	 *
	 * @since 6.0.0
	 * @return array rows of value / label / hint
	 */
	function ecv2_store_details_lookup_pages( $values, $field ) {
		if ( ! function_exists( 'get_post' ) ) {
			return array();
		}
		$rows = array();
		foreach ( (array) $values as $value ) {
			$id = (int) $value;
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
				continue;
			}
			$hint = ( '' !== (string) $post->post_name ) ? '/' . $post->post_name : '';
			if ( 'publish' !== $post->post_status ) {
				/* translators: %s: post status such as draft */
				$hint = sprintf( __( 'not published (%s)', 'wp-easycart' ), $post->post_status );
			}
			$rows[] = array( 'value' => (string) $id, 'label' => ecv2_store_details_page_title( $id ), 'hint' => $hint );
		}
		return $rows;
	}
}

if ( ! function_exists( 'ecv2_store_details_shortcode_warning' ) ) {
	/**
	 * Message after a page is chosen: nothing selected, the page is gone or unpublished,
	 * or it does not carry the shortcode the store expects on it ( [ec_store], [ec_cart]
	 * or [ec_account] ). The check itself lives in
	 * wp_easycart_admin_settings_page_v2::store_page_problem(), which the page banner,
	 * the Settings home and the admin notice share ( one get_post() per page, cached for
	 * the request ). Block-editor sites hold the shortcode inside a shortcode block, which
	 * still matches. $shortcode is kept for callers; the option key names the page.
	 *
	 * @since 6.0.0 delegates to the shared check
	 */
	function ecv2_store_details_shortcode_warning( $value, $field, $shortcode ) {
		if ( ! class_exists( 'wp_easycart_admin_settings_page_v2' ) || ! method_exists( 'wp_easycart_admin_settings_page_v2', 'store_page_problem' ) ) {
			return '';
		}
		$key = ( is_array( $field ) && ! empty( $field['key'] ) ) ? $field['key'] : '';
		if ( '' === $key ) {
			foreach ( wp_easycart_admin_settings_page_v2::store_pages() as $option => $spec ) {
				if ( $spec['shortcode'] === $shortcode ) {
					$key = $option;
					break;
				}
			}
		}
		$problem = wp_easycart_admin_settings_page_v2::store_page_problem( $key, $value );
		return $problem ? $problem['text'] : '';
	}
}

if ( ! function_exists( 'ecv2_store_details_health' ) ) {
	/**
	 * 'health' callable for the page: every store page that is missing, unpublished or
	 * without its shortcode, from the stored options. Rendered as the red banner at the
	 * top of the page and as a red note under each affected row; re-sent after every save
	 * and after "Create … page" so the page never has to reload to clear it.
	 *
	 * @since 6.0.0
	 * @return array list of array( key, label, text, edit_url )
	 */
	function ecv2_store_details_health( $page ) {
		if ( ! class_exists( 'wp_easycart_admin_settings_page_v2' ) || ! method_exists( 'wp_easycart_admin_settings_page_v2', 'store_pages_health' ) ) {
			return array();
		}
		return array_values( wp_easycart_admin_settings_page_v2::store_pages_health( true ) );
	}
}

if ( ! function_exists( 'ecv2_store_details_validate_storepage' ) ) {
	function ecv2_store_details_validate_storepage( $value, $field ) {
		return ecv2_store_details_shortcode_warning( $value, $field, '[ec_store]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_validate_cartpage' ) ) {
	function ecv2_store_details_validate_cartpage( $value, $field ) {
		return ecv2_store_details_shortcode_warning( $value, $field, '[ec_cart]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_validate_accountpage' ) ) {
	function ecv2_store_details_validate_accountpage( $value, $field ) {
		return ecv2_store_details_shortcode_warning( $value, $field, '[ec_account]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_storepage_saved' ) ) {
	/**
	 * Store page side effect, ported from wp_easycart_admin_initial_setup::save_storepage():
	 * product, category and manufacturer permalinks live under the store page's slug,
	 * so the permastruct is rebuilt and the rewrite rules flushed straight away.
	 * WordPress core also fires update_option_ec_option_storepage → wp_easycart_reset_rewrite_check(),
	 * which re-checks the rules on the next load; both are kept.
	 */
	function ecv2_store_details_storepage_saved( $value, $old, $field ) {
		global $wp_rewrite;
		$id = (int) $value;
		if ( $id <= 0 || ! function_exists( 'ec_get_the_slug' ) || ! is_object( $wp_rewrite ) ) {
			return;
		}
		$store_slug = ec_get_the_slug( $id );
		if ( '' === (string) $store_slug ) {
			return;
		}
		$wp_rewrite->add_permastruct( 'ec_store', $store_slug . '/%ec_store%/', true, 1 );
		add_rewrite_rule( '^' . $store_slug . '/([^/]*)/?$', 'index.php?ec_store=$matches[1]', 'top' );
		$wp_rewrite->flush_rules();
	}
}

if ( ! function_exists( 'ecv2_store_details_create_page' ) ) {
	/**
	 * "Create New … Page" from the classic page: inserts a published page holding
	 * the shortcode and points the option at it. Hands the new id back as a field
	 * update so the picker chip, the row note and the banner refresh in place.
	 *
	 * @since 6.0.0 no longer reloads the page
	 */
	function ecv2_store_details_create_page( $option_name, $title, $shortcode ) {
		$post_id = wp_insert_post( array(
			'post_content' => $shortcode,
			'post_title'   => $title,
			'post_type'    => 'page',
			'post_status'  => 'publish',
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$old = get_option( $option_name );
		update_option( $option_name, (int) $post_id );
		if ( 'ec_option_storepage' === $option_name ) {
			ecv2_store_details_storepage_saved( (int) $post_id, $old, array() );
		}
		return array(
			/* translators: 1: page title, 2: shortcode such as [ec_store] */
			'message' => sprintf( __( 'Published “%1$s” with %2$s and selected it.', 'wp-easycart' ), $title, $shortcode ),
			'fields'  => array( $option_name => (string) (int) $post_id ),
		);
	}
}

if ( ! function_exists( 'ecv2_store_details_create_storepage' ) ) {
	function ecv2_store_details_create_storepage( $action, $page ) {
		return ecv2_store_details_create_page( 'ec_option_storepage', __( 'Store', 'wp-easycart' ), '[ec_store]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_create_cartpage' ) ) {
	function ecv2_store_details_create_cartpage( $action, $page ) {
		return ecv2_store_details_create_page( 'ec_option_cartpage', __( 'Cart', 'wp-easycart' ), '[ec_cart]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_create_accountpage' ) ) {
	function ecv2_store_details_create_accountpage( $action, $page ) {
		return ecv2_store_details_create_page( 'ec_option_accountpage', __( 'Account', 'wp-easycart' ), '[ec_account]' );
	}
}

if ( ! function_exists( 'ecv2_store_details_render_wizard_note' ) ) {
	/** Top of the Store pages card: where these came from, with a link back to the wizard. */
	function ecv2_store_details_render_wizard_note( $field, $page ) {
		$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=1' );
		?>
		<div class="ecst-row-text">
			<span class="ecst-row-desc"><?php esc_html_e( 'The setup wizard created these pages and picked your currency when EasyCart was installed. Change them here any time, or run the wizard again to redo location, payments and shipping in one pass.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open the setup wizard', 'wp-easycart' ); ?> ↗</a></span>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ecv2_store_details_sample_price' ) ) {
	/**
	 * How 1234.56 currently displays, built from the stored options the same way
	 * ec_currency::get_currency_display() does, for the Currency card hint.
	 */
	function ecv2_store_details_sample_price() {
		$symbol   = (string) get_option( 'ec_option_currency', '$' );
		$decimals = get_option( 'ec_option_currency_decimal_places', '2' );
		$decimals = ( '' === (string) $decimals || ! is_numeric( $decimals ) || (int) $decimals < 0 ) ? 2 : (int) $decimals;
		$amount   = number_format( 1234.56, $decimals, (string) get_option( 'ec_option_currency_decimal_symbol', '.' ), (string) get_option( 'ec_option_currency_thousands_seperator', ',' ) );
		$space    = get_option( 'ec_option_currency_symbol_space', '0' ) ? "\xC2\xA0" : ''; /* 6.0.5 */
		$sample   = get_option( 'ec_option_currency_symbol_location', '1' ) ? $symbol . $space . $amount : $amount . $space . $symbol;
		if ( get_option( 'ec_option_show_currency_code', '0' ) ) {
			$sample = (string) get_option( 'ec_option_base_currency', 'USD' ) . ' ' . $sample;
		}
		return html_entity_decode( $sample, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_currency_code' ) ) {
	/** Three letters, upper-cased. The classic page kept up to 3 alphanumerics; anything shorter breaks gateways, so it is rejected here. */
	function ecv2_store_details_sanitize_currency_code( $raw, $field ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', trim( (string) $raw ) ) );
		if ( 3 !== strlen( $code ) ) {
			return new WP_Error( 'currency_code', __( 'Enter a three-letter currency code such as USD, EUR or GBP.', 'wp-easycart' ) );
		}
		return $code;
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_symbol' ) ) {
	/** Up to 10 characters ( classic filter_length( …, 10 ) ), multibyte-safe for € and ₹. */
	function ecv2_store_details_sanitize_symbol( $raw, $field ) {
		/* 6.0.5: a symbol typed as an HTML code ( &pound; ) is saved as the character, as prices print it. */
		$raw    = (string) $raw;
		$raw    = false === strpos( $raw, '&' ) ? $raw : html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$symbol = sanitize_text_field( $raw );
		return function_exists( 'mb_substr' ) ? mb_substr( $symbol, 0, 10 ) : substr( $symbol, 0, 10 );
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_separator' ) ) {
	/** A single character ( classic filter_length( …, 1 ) / substr( …, 0, 1 ) ); may be empty. */
	function ecv2_store_details_sanitize_separator( $raw, $field ) {
		/* 6.0.5: a space ( typed, &nbsp; or a non-breaking space ) is stored as a non-breaking space; sanitize_text_field() trims it away. */
		if ( in_array( (string) $raw, array( ' ', '&nbsp;', "\xC2\xA0", "\xE2\x80\xAF" ), true ) ) {
			return "\xE2\x80\xAF" === (string) $raw ? "\xE2\x80\xAF" : "\xC2\xA0";
		}
		$value = sanitize_text_field( (string) $raw );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1 ) : substr( $value, 0, 1 );
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_decimal_places' ) ) {
	/** Whole number 0–4. ec_currency falls back to 2 when the option is blank, so blank is refused rather than stored. */
	function ecv2_store_details_sanitize_decimal_places( $raw, $field ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^[0-4]$/', $raw ) ) {
			return new WP_Error( 'decimal_places', __( 'Enter a whole number from 0 to 4.', 'wp-easycart' ) );
		}
		return (int) $raw;
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_exchange_rates' ) ) {
	/**
	 * CODE=rate list. Accepts commas or new lines between entries and stores the
	 * comma-separated form ec_currency explodes. The classic handler silently dropped
	 * malformed entries; here a bad entry is reported so a typo cannot wipe a rate.
	 */
	function ecv2_store_details_sanitize_exchange_rates( $raw, $field ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$entries = preg_split( '/[\s,]+/', $raw );
		$clean   = array();
		$seen    = array();
		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			$parts = explode( '=', $entry );
			if ( 2 !== count( $parts ) ) {
				/* translators: %s: the entry as typed */
				return new WP_Error( 'exchange_rates', sprintf( __( '“%s” is not in the form CODE=rate. Example: EUR=0.92,GBP=0.79', 'wp-easycart' ), $entry ) );
			}
			$code = strtoupper( trim( $parts[0] ) );
			$rate = trim( $parts[1] );
			if ( ! preg_match( '/^[A-Z]{3}$/', $code ) ) {
				/* translators: %s: the code as typed */
				return new WP_Error( 'exchange_rates', sprintf( __( '“%s” is not a three-letter currency code.', 'wp-easycart' ), $parts[0] ) );
			}
			if ( ! is_numeric( $rate ) || (float) $rate <= 0 ) {
				/* translators: %s: currency code */
				return new WP_Error( 'exchange_rates', sprintf( __( 'The rate for %s must be a number greater than zero.', 'wp-easycart' ), $code ) );
			}
			if ( isset( $seen[ $code ] ) ) {
				/* translators: %s: currency code */
				return new WP_Error( 'exchange_rates', sprintf( __( '%s is listed twice.', 'wp-easycart' ), $code ) );
			}
			$seen[ $code ] = true;
			$clean[]       = $code . '=' . $rate;
		}
		return implode( ',', $clean );
	}
}

if ( ! function_exists( 'ecv2_store_details_currency_presets' ) ) {
	/**
	 * The usual way common currencies are written, offered as a one-click "apply"
	 * under the currency preview. Currencies normally grouped with a space use "."
	 * here, and symbols carry no spaces, because both are saved through
	 * sanitize_text_field() ( which trims ).
	 *
	 * @since 6.0.0
	 */
	function ecv2_store_details_currency_presets() {
		$presets = array(
			'USD' => array( 'name' => __( 'US dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'EUR' => array( 'name' => __( 'Euro', 'wp-easycart' ), 'symbol' => '€', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2', 'space' => '1' ),
			'GBP' => array( 'name' => __( 'British pound', 'wp-easycart' ), 'symbol' => '£', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'CAD' => array( 'name' => __( 'Canadian dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'AUD' => array( 'name' => __( 'Australian dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'NZD' => array( 'name' => __( 'New Zealand dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'JPY' => array( 'name' => __( 'Japanese yen', 'wp-easycart' ), 'symbol' => '¥', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '0', 'space' => '0' ),
			'CHF' => array( 'name' => __( 'Swiss franc', 'wp-easycart' ), 'symbol' => 'CHF', 'before' => '1', 'decimal' => '.', 'thousands' => "'", 'places' => '2', 'space' => '1' ),
			'SEK' => array( 'name' => __( 'Swedish krona', 'wp-easycart' ), 'symbol' => 'kr', 'before' => '0', 'decimal' => ',', 'thousands' => "\xC2\xA0", 'places' => '2', 'space' => '1' ),
			'NOK' => array( 'name' => __( 'Norwegian krone', 'wp-easycart' ), 'symbol' => 'kr', 'before' => '0', 'decimal' => ',', 'thousands' => "\xC2\xA0", 'places' => '2', 'space' => '1' ),
			'DKK' => array( 'name' => __( 'Danish krone', 'wp-easycart' ), 'symbol' => 'kr.', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2', 'space' => '1' ),
			'PLN' => array( 'name' => __( 'Polish złoty', 'wp-easycart' ), 'symbol' => 'zł', 'before' => '0', 'decimal' => ',', 'thousands' => "\xC2\xA0", 'places' => '2', 'space' => '1' ),
			'MXN' => array( 'name' => __( 'Mexican peso', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'BRL' => array( 'name' => __( 'Brazilian real', 'wp-easycart' ), 'symbol' => 'R$', 'before' => '1', 'decimal' => ',', 'thousands' => '.', 'places' => '2', 'space' => '1' ),
			'INR' => array( 'name' => __( 'Indian rupee', 'wp-easycart' ), 'symbol' => '₹', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'ZAR' => array( 'name' => __( 'South African rand', 'wp-easycart' ), 'symbol' => 'R', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'SGD' => array( 'name' => __( 'Singapore dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'HKD' => array( 'name' => __( 'Hong Kong dollar', 'wp-easycart' ), 'symbol' => 'HK$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'CNY' => array( 'name' => __( 'Chinese yuan', 'wp-easycart' ), 'symbol' => '¥', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2', 'space' => '0' ),
			'KRW' => array( 'name' => __( 'South Korean won', 'wp-easycart' ), 'symbol' => '₩', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '0', 'space' => '0' ),
		);
		/* 6.0.5 ( admin review M9 ): the euro and the Canadian dollar are written the way the store's country writes them
		 * ( 1.234,56 € in Germany, 1 234,56 € in France, € 1.234,56 in the Netherlands, 1 234,56 $ in French Canada ). */
		$country = class_exists( 'wp_easycart_carriers' ) ? (string) wp_easycart_carriers::store_country() : '';
		$format  = ( '' !== $country && class_exists( 'ec_currency' ) && method_exists( 'ec_currency', 'country_format' ) ) ? ec_currency::country_format( $country ) : null;
		$euro    = array( 'AT', 'BE', 'HR', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES' );
		$home    = in_array( $country, $euro, true ) ? 'EUR' : ( 'CA' === $country ? 'CAD' : '' );
		if ( $format && '' !== $home && isset( $presets[ $home ] ) ) {
			$presets[ $home ]['before']    = $format['before'] ? '1' : '0';
			$presets[ $home ]['space']     = $format['space'] ? '1' : '0';
			$presets[ $home ]['decimal']   = $format['dec'];
			$presets[ $home ]['thousands'] = $format['thou'];
		}
		return $presets;
	}
}

if ( ! function_exists( 'ecv2_store_details_position_desc' ) ) {
	/** "Before the amount gives €40,00; after gives 40,00€." in the store's own symbol and separators ( 6.0.5, admin review L1 ). */
	function ecv2_store_details_position_desc() {
		$symbol = function_exists( 'get_option' ) ? html_entity_decode( (string) get_option( 'ec_option_currency', '$' ), ENT_QUOTES, 'UTF-8' ) : '$';
		$dec    = function_exists( 'get_option' ) ? (string) get_option( 'ec_option_currency_decimal_symbol', '.' ) : '.';
		$amount = '40' . ( '' === $dec ? '.' : $dec ) . '00';
		/* translators: 1: an amount with the symbol before it, e.g. $40.00, 2: with the symbol after it, e.g. 40.00$. */
		return sprintf( __( 'Before the amount gives %1$s; after gives %2$s.', 'wp-easycart' ), ( '' !== $symbol ? $symbol : '$' ) . $amount, $amount . ( '' !== $symbol ? $symbol : '$' ) );
	}
}

if ( ! function_exists( 'ecv2_store_details_separator_options' ) ) {
	/**
	 * Pill choices for a separator, each labelled with how it reads. A stored
	 * character outside the list is kept as an extra choice so it is never lost.
	 *
	 * @since 6.0.0
	 * @param string $which 'thousands' or 'decimal'.
	 */
	function ecv2_store_details_separator_options( $which ) {
		if ( 'decimal' === $which ) {
			$options = array(
				'.' => '1.99',
				',' => '1,99',
			);
			$current = function_exists( 'get_option' ) ? (string) get_option( 'ec_option_currency_decimal_symbol', '.' ) : '.';
		} else {
			$options = array(
				','  => '1,000',
				'.'  => '1.000',
				"'"  => "1'000",
				"\xC2\xA0" => __( '1 000 (space)', 'wp-easycart' ), /* 6.0.5: France, Scandinavia, Poland … */
				''   => __( '1000 (none)', 'wp-easycart' ),
			);
			$current = function_exists( 'get_option' ) ? (string) get_option( 'ec_option_currency_thousands_seperator', ',' ) : ',';
		}
		if ( ! isset( $options[ $current ] ) ) {
			$options[ $current ] = ( 'decimal' === $which ) ? '1' . $current . '99' : '1' . $current . '000';
		}
		return $options;
	}
}

if ( ! function_exists( 'ecv2_store_details_separator_warning' ) ) {
	/** The same character for both separators makes prices unreadable ( 1.234.56 ). */
	function ecv2_store_details_separator_warning( $value, $field ) {
		if ( '' !== (string) $value && (string) $value === (string) get_option( 'ec_option_currency_decimal_symbol', '.' ) ) {
			return __( 'This matches your decimal separator, so prices will be hard to read. Pick a different one.', 'wp-easycart' );
		}
		return '';
	}
}

if ( ! function_exists( 'ecv2_store_details_render_currency_preview' ) ) {
	/**
	 * Live preview of the currency format ( updated in the browser as the fields
	 * below change, before they are saved ) plus a one-click usual format for the
	 * chosen currency code.
	 *
	 * @since 6.0.0
	 */
	function ecv2_store_details_render_currency_preview( $field, $page ) {
		?>
		<div class="ecsd-preview" id="ecsd_preview" data-presets="<?php echo esc_attr( wp_json_encode( ecv2_store_details_currency_presets() ) ); ?>">
			<span class="ecsd-preview-label"><?php esc_html_e( 'Preview', 'wp-easycart' ); ?></span>
			<span class="ecsd-samples">
				<span class="ecsd-sample" data-amount="1234.56"><?php echo esc_html( ecv2_store_details_sample_price() ); ?></span>
				<span class="ecsd-sample" data-amount="19.99"></span>
				<span class="ecsd-sample is-negative" data-amount="-5"></span>
			</span>
			<span class="ecsd-preset" hidden>
				<span class="ecsd-preset-text"></span>
				<button type="button" class="ecv2-btn ecv2-btn-sm ecsd-preset-apply"><?php esc_html_e( 'Apply', 'wp-easycart' ); ?></button>
			</span>
		</div>
		<?php
		echo ecv2_store_details_order_currency_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts ( 6.0.5: past orders in another currency ).
	}
}

if ( ! function_exists( 'ecv2_store_details_exchange_rates_warning' ) ) {
	/** Listing the base currency is harmless but pointless: say so. */
	function ecv2_store_details_exchange_rates_warning( $value, $field ) {
		$base = strtoupper( (string) get_option( 'ec_option_base_currency', 'USD' ) );
		if ( '' !== $base && preg_match( '/(^|,)' . preg_quote( $base, '/' ) . '=/', (string) $value ) ) {
			/* translators: %s: base currency code */
			return sprintf( __( '%s is your base currency; its rate is always 1 and the entry is ignored.', 'wp-easycart' ), $base );
		}
		return '';
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_goal' ) ) {
	/**
	 * Monthly goal as a plain number. The classic handler kept the typed commas
	 * ( "2,500" ), which the sidebar meter then read as 2 via (float); strip them.
	 */
	function ecv2_store_details_sanitize_goal( $raw, $field ) {
		$raw = str_replace( array( ',', ' ' ), '', trim( (string) $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
			return new WP_Error( 'goal', __( 'Enter an amount such as 2500.', 'wp-easycart' ) );
		}
		return (string) ( 0 + $raw );
	}
}

if ( ! function_exists( 'ecv2_store_details_legacy_nonce' ) ) {
	/**
	 * The classic demo data installer verifies its own nonce from $_POST. The V2
	 * action request has already passed ecv2_settings_guard() ( capability + V2
	 * nonce ), so supply the legacy nonce it expects rather than duplicating 900
	 * lines of installer.
	 */
	function ecv2_store_details_legacy_nonce( $action ) {
		$_POST['wp_easycart_nonce'] = wp_create_nonce( $action );
	}
}

if ( ! function_exists( 'ecv2_store_details_install_demo_data' ) ) {
	function ecv2_store_details_install_demo_data( $action, $page ) {
		/* The V2 guard admits wpec_settings too, but the installer itself requires manage_options / wpec_manager and
		   silently returns false otherwise: say so instead of "could not be installed". @since 6.0.0 */
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			return new WP_Error( 'wp_easycart_demo_permission', __( 'Only administrators and store managers can install or remove demo data.', 'wp-easycart' ) );
		}
		if ( ! function_exists( 'wp_easycart_admin_initial_setup' ) ) {
			return new WP_Error( 'demo', __( 'The demo data installer is not available.', 'wp-easycart' ) );
		}
		if ( get_option( 'ec_option_demo_data_installed' ) ) {
			return new WP_Error( 'demo', __( 'Demo data is already installed.', 'wp-easycart' ) );
		}
		ecv2_store_details_legacy_nonce( 'wp-easycart-initial-setup-demo-setup' );
		wp_easycart_admin_initial_setup()->install_demo_data();
		if ( ! get_option( 'ec_option_demo_data_installed' ) ) {
			return new WP_Error( 'demo', __( 'The demo data could not be installed.', 'wp-easycart' ) );
		}
		return array( 'reload' => true );
	}
}

if ( ! function_exists( 'ecv2_store_details_uninstall_demo_data' ) ) {
	function ecv2_store_details_uninstall_demo_data( $action, $page ) {
		/* Same capability as the installer's verify_access(). @since 6.0.0 */
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			return new WP_Error( 'wp_easycart_demo_permission', __( 'Only administrators and store managers can install or remove demo data.', 'wp-easycart' ) );
		}
		if ( ! function_exists( 'wp_easycart_admin_initial_setup' ) ) {
			return new WP_Error( 'demo', __( 'The demo data installer is not available.', 'wp-easycart' ) );
		}
		if ( ! get_option( 'ec_option_demo_data_installed' ) ) {
			return new WP_Error( 'demo', __( 'No demo data is installed.', 'wp-easycart' ) );
		}
		ecv2_store_details_legacy_nonce( 'wp-easycart-initial-setup-demo-setup' );
		wp_easycart_admin_initial_setup()->uninstall_demo_data();
		if ( get_option( 'ec_option_demo_data_installed' ) ) {
			return new WP_Error( 'demo', __( 'The demo data could not be removed.', 'wp-easycart' ) );
		}
		return array( 'reload' => true );
	}
}

if ( ! function_exists( 'ecv2_store_details_country_options' ) ) {
	/**
	 * Store country choices: every country in Countries & Regions ( ec_country ), by name, also those the store does not ship to.
	 *
	 * @since 6.0.5
	 * @return array ISO code => name.
	 */
	function ecv2_store_details_country_options() {
		$options = array( '' => __( 'Choose your country', 'wp-easycart' ) );
		if ( empty( $GLOBALS['wpdb'] ) ) {
			return $options;
		}
		foreach ( (array) $GLOBALS['wpdb']->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY name_cnt ASC' ) as $row ) {
			$code = strtoupper( trim( (string) $row->iso2_cnt ) );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && ! isset( $options[ $code ] ) ) {
				$options[ $code ] = (string) $row->name_cnt;
			}
		}
		return $options;
	}
}

if ( ! function_exists( 'ecv2_store_details_store_country' ) ) {
	/**
	 * The store country as the page shows it before one is saved: what WP EasyCart works out today
	 * ( wp_easycart_carriers::store_country(): the setup wizard's choice, the checkout's default country, Stripe's, the site language ).
	 *
	 * @since 6.0.5
	 * @return string
	 */
	function ecv2_store_details_store_country() {
		return ( function_exists( 'get_option' ) && class_exists( 'wp_easycart_carriers' ) ) ? (string) wp_easycart_carriers::store_country() : '';
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_country' ) ) {
	/**
	 * Store country: an ISO code from the list ( UK is GB ), else nothing.
	 *
	 * @since 6.0.5
	 */
	function ecv2_store_details_sanitize_country( $value, $field ) {
		$value = strtoupper( trim( (string) $value ) );
		if ( 'UK' === $value ) {
			$value = 'GB';
		}
		return ( '' !== $value && array_key_exists( $value, ecv2_store_details_country_options() ) ) ? $value : '';
	}
}

if ( ! function_exists( 'ecv2_store_details_country_saved' ) ) {
	/**
	 * Store country saved: the setup wizard starts from it too ( ec_option_store_locale ).
	 *
	 * @since 6.0.5
	 */
	function ecv2_store_details_country_saved( $value, $old, $field ) {
		if ( '' !== (string) $value ) {
			update_option( 'ec_option_store_locale', (string) $value );
		}
	}
}

if ( ! function_exists( 'ecv2_store_details_country_warning' ) ) {
	/**
	 * After the store country is saved: a store in the EU or the EEA must offer the withdrawal button ( the switch was set once,
	 * from the country WP EasyCart worked out when 6.0.4 arrived ).
	 *
	 * @since 6.0.5
	 */
	function ecv2_store_details_country_warning( $value, $field ) {
		if ( '' === (string) $value || ! class_exists( 'wp_easycart_withdrawals' ) || ! in_array( (string) $value, wp_easycart_withdrawals::eu_countries(), true ) || wp_easycart_withdrawals::enabled() ) {
			return '';
		}
		return __( 'Stores in the EU and the EEA must offer a withdrawal button to consumers. Switch it on in Settings › Checkout › Withdrawal button.', 'wp-easycart' );
	}
}

if ( ! function_exists( 'ecv2_store_details_render_units_note' ) ) {
	/**
	 * Top of the Units card: changing a unit does not convert what was entered.
	 *
	 * @since 6.0.5
	 */
	function ecv2_store_details_render_units_note( $field, $page ) {
		?>
		<div class="ecst-row-text">
			<span class="ecst-row-desc"><?php esc_html_e( 'The units you enter weights and sizes in for products, variants and boxes. Shipping rates by weight, live carriers, PayPal and search engines read them in these units. When you change the weight unit, you can convert the weights already saved.', 'wp-easycart' ); ?></span>
			<?php echo ecv2_store_details_weight_convert_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ecv2_store_details_weight_convert_html' ) ) {
	/**
	 * The offer to convert saved weights after the weight unit changed ( wp_easycart_packages::pending_conversion() ), or an
	 * empty holder the save reply fills ( ecv2_store_details_weight_save_reply() ).
	 *
	 * @since 6.0.5
	 * @return string
	 */
	function ecv2_store_details_weight_convert_html() {
		$pending = ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'pending_conversion' ) ) ? wp_easycart_packages::pending_conversion() : null;
		if ( ! $pending ) {
			return '<div id="ecsd_weight_convert" hidden></div>';
		}
		$counts = wp_easycart_packages::weight_counts();
		$parts  = array();
		$words  = array(
			/* translators: %s: number of products */
			'products' => _n_noop( '%s product', '%s products', 'wp-easycart' ),
			/* translators: %s: number of variants */
			'variants' => _n_noop( '%s variant', '%s variants', 'wp-easycart' ),
			/* translators: %s: number of option choices */
			'options'  => _n_noop( '%s option choice', '%s option choices', 'wp-easycart' ),
			/* translators: %s: number of boxes */
			'boxes'    => _n_noop( '%s box', '%s boxes', 'wp-easycart' ),
			/* translators: %s: number of shipping rates */
			'rates'    => _n_noop( '%s weight-based shipping rate', '%s weight-based shipping rates', 'wp-easycart' ),
		);
		foreach ( $words as $kind => $noop ) {
			if ( ! empty( $counts[ $kind ] ) ) {
				$parts[] = sprintf( translate_nooped_plural( $noop, $counts[ $kind ], 'wp-easycart' ), number_format_i18n( $counts[ $kind ] ) );
			}
		}
		$from = wp_easycart_packages::weight_unit_name( $pending['from'] );
		$to   = wp_easycart_packages::weight_unit_name( $pending['to'] );
		/* translators: 1: list of what has weights ( "12 products, 3 boxes" ), 2: old unit ( kilograms ), 3: new unit ( grams ) */
		$text = sprintf( __( 'Weights are saved for %1$s, entered in %2$s. Convert them to %3$s?', 'wp-easycart' ), implode( ', ', $parts ), $from, $to );
		$amounts = array( 'lb' => 1.5, 'kg' => 1.5, 'oz' => 8, 'g' => 500 );
		$amount  = isset( $amounts[ $pending['from'] ] ) ? $amounts[ $pending['from'] ] : 1.5;
		$fmt     = function ( $n ) {
			return rtrim( rtrim( number_format( (float) $n, 3, '.', '' ), '0' ), '.' );
		};
		/* translators: 1: an amount, 2: old unit ( kg ), 3: the amount converted, 4: new unit ( g ) */
		$sample = sprintf( __( 'For example %1$s %2$s becomes %3$s %4$s.', 'wp-easycart' ), $fmt( $amount ), $pending['from'], $fmt( wp_easycart_packages::convert( $amount, $pending['from'], $pending['to'] ) ), $pending['to'] );
		ob_start();
		?>
		<div id="ecsd_weight_convert" class="ecst-action ecsd-weight-convert" role="group" aria-label="<?php esc_attr_e( 'Convert saved weights', 'wp-easycart' ); ?>">
			<div class="ecst-action-text">
				<b><?php esc_html_e( 'Convert saved weights', 'wp-easycart' ); ?></b>
				<span><?php echo esc_html( $text . ' ' . $sample ); ?></span>
			</div>
			<div class="ecsd-weight-convert-buttons">
				<button type="button" class="ecv2-btn ecv2-btn-primary" data-action="convert_weights" data-sec="location" data-confirm-title="<?php /* translators: %s: new unit ( grams ) */ echo esc_attr( sprintf( __( 'Convert saved weights to %s?', 'wp-easycart' ), $to ) ); ?>" data-confirm="<?php esc_attr_e( 'Products, variants, option choices, boxes, weight-based shipping rates and the weights recorded on orders are converted. This cannot be undone, but you can convert back the same way.', 'wp-easycart' ); ?>" data-confirm-ok="<?php esc_attr_e( 'Convert weights', 'wp-easycart' ); ?>"><?php /* translators: %s: new unit ( grams ) */ echo esc_html( sprintf( __( 'Convert to %s', 'wp-easycart' ), $to ) ); ?></button>
				<button type="button" class="ecv2-btn" data-action="keep_weights" data-sec="location"><?php /* translators: %s: new unit ( grams ) */ echo esc_html( sprintf( __( 'They are already in %s', 'wp-easycart' ), $to ) ); ?></button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ecv2_store_details_convert_weights' ) ) {
	/**
	 * Action convert_weights: every saved weight from the unit it was entered in to the store's unit.
	 *
	 * @since 6.0.5
	 * @return array|string|WP_Error
	 */
	function ecv2_store_details_convert_weights() {
		$pending = wp_easycart_packages::pending_conversion();
		if ( ! $pending ) {
			return __( 'There are no weights to convert.', 'wp-easycart' );
		}
		$changed = wp_easycart_packages::convert_weights( $pending['from'], $pending['to'] );
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}
		return array(
			/* translators: %s: new unit ( grams ) */
			'message' => sprintf( __( 'Saved weights are now in %s.', 'wp-easycart' ), wp_easycart_packages::weight_unit_name( $pending['to'] ) ),
			'reload'  => true,
		);
	}
}

if ( ! function_exists( 'ecv2_store_details_keep_weights' ) ) {
	/**
	 * Action keep_weights: the saved weights were already in the new unit; the offer goes.
	 *
	 * @since 6.0.5
	 * @return array
	 */
	function ecv2_store_details_keep_weights() {
		wp_easycart_packages::dismiss_conversion();
		return array(
			'message' => __( 'Saved weights kept as they are.', 'wp-easycart' ),
			'reload'  => true,
		);
	}
}

if ( ! function_exists( 'ecv2_store_details_order_currency_html' ) ) {
	/**
	 * 6.0.5 ( Matt's H2 ): orders keep the currency they were placed in. When some were placed in another currency than the
	 * store's now, the Currency section says so and offers to show them in the store's ( a store that changed currency because
	 * the old one was wrong ).
	 *
	 * @since 6.0.5
	 * @return string
	 */
	function ecv2_store_details_order_currency_html() {
		$others = class_exists( 'wp_easycart_order_currency' ) ? wp_easycart_order_currency::other_currencies() : array();
		if ( ! $others ) {
			return '<div id="ecsd_order_currency" hidden></div>';
		}
		$store = strtoupper( (string) get_option( 'ec_option_base_currency', 'USD' ) );
		$parts = array();
		$total = 0;
		foreach ( $others as $code => $orders ) {
			/* translators: 1: number of orders, 2: currency code ( USD ) */
			$parts[] = sprintf( _n( '%1$s order in %2$s', '%1$s orders in %2$s', $orders, 'wp-easycart' ), number_format_i18n( $orders ), $code );
			$total  += $orders;
		}
		/* translators: 1: "12 orders in USD" ( one or more, joined by commas ), 2: the store's currency code ( EUR ) */
		$text = sprintf( __( 'Orders keep the currency they were placed in: %1$s still show in that currency, although your store now uses %2$s.', 'wp-easycart' ), implode( ', ', $parts ), $store );
		ob_start();
		?>
		<div id="ecsd_order_currency" class="ecst-action ecsd-weight-convert" role="group" aria-label="<?php esc_attr_e( 'Past orders in another currency', 'wp-easycart' ); ?>">
			<div class="ecst-action-text">
				<b><?php esc_html_e( 'Past orders in another currency', 'wp-easycart' ); ?></b>
				<span><?php echo esc_html( $text ); ?></span>
			</div>
			<div class="ecsd-weight-convert-buttons">
				<button type="button" class="ecv2-btn" data-action="relabel_order_currency" data-sec="currency" data-confirm-title="<?php /* translators: %s: the store's currency code ( EUR ) */ echo esc_attr( sprintf( __( 'Show every order in %s?', 'wp-easycart' ), $store ) ); ?>" data-confirm="<?php /* translators: %s: the store's currency code ( EUR ) */ echo esc_attr( sprintf( _n( 'Only when the order was really paid in %s: its amounts stay the same numbers and are shown with your store\'s currency from now on.', 'Only when these orders were really paid in %s: their amounts stay the same numbers and are shown with your store\'s currency from now on.', $total, 'wp-easycart' ), $store ) ); ?>" data-confirm-ok="<?php /* translators: %s: the store's currency code ( EUR ) */ echo esc_attr( sprintf( __( 'Show them in %s', 'wp-easycart' ), $store ) ); ?>"><?php /* translators: %s: the store's currency code ( EUR ) */ echo esc_html( sprintf( __( 'They were paid in %s', 'wp-easycart' ), $store ) ); ?></button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'ecv2_store_details_relabel_order_currency' ) ) {
	/**
	 * Action relabel_order_currency: orders recorded in another currency take the store's.
	 *
	 * @since 6.0.5
	 * @return array|string
	 */
	function ecv2_store_details_relabel_order_currency() {
		if ( ! class_exists( 'wp_easycart_order_currency' ) ) {
			return __( 'There are no orders to change.', 'wp-easycart' );
		}
		$done = wp_easycart_order_currency::relabel_all();
		return array(
			/* translators: 1: number of orders, 2: the store's currency code ( EUR ) */
			'message' => sprintf( _n( '%1$s order now shows in %2$s.', '%1$s orders now show in %2$s.', $done, 'wp-easycart' ), number_format_i18n( $done ), strtoupper( (string) get_option( 'ec_option_base_currency', 'USD' ) ) ),
			'reload'  => true,
		);
	}
}

if ( ! function_exists( 'ecv2_store_details_weight_unit_warning' ) ) {
	/**
	 * After the weight unit is saved: label extensions that read only pounds and kilograms ( older versions ) would send an
	 * ounce or gram store's weights to the carrier as pounds.
	 *
	 * @since 6.0.5
	 * @param string $value Saved value.
	 * @return string
	 */
	function ecv2_store_details_weight_unit_warning( $value ) {
		if ( ! in_array( (string) $value, array( 'oz', 'g' ), true ) ) {
			return '';
		}
		$old = ecv2_store_details_weight_unit_extensions();
		if ( ! $old ) {
			return '';
		}
		/* translators: 1: extension names and the versions that read ounces and grams ( "Shippo 2.0.4" ), 2: unit ( grams ) */
		return sprintf( __( 'Update %1$s before you ship: older versions send %2$s to the carrier as pounds.', 'wp-easycart' ), implode( ', ', $old ), wp_easycart_packages::weight_unit_name( $value ) );
	}
}

if ( ! function_exists( 'ecv2_store_details_weight_unit_extensions' ) ) {
	/**
	 * Active label and rate extensions older than the version that reads ounces and grams, as "Name version".
	 *
	 * @since 6.0.5
	 * @return array
	 */
	function ecv2_store_details_weight_unit_extensions() {
		$needs = apply_filters(
			'wp_easycart_weight_unit_extensions',
			array(
				'WP_EASYCART_SHIPPO_VERSION'      => array( 'WP EasyCart for Shippo', '2.0.4' ),
				'WP_EASYCART_SHIPSTATION_VERSION' => array( 'WP EasyCart for ShipStation', '2.1.4' ),
				'WP_EASYCART_STAMPS_VERSION'      => array( 'WP EasyCart for Stamps.com', '2.0.3' ),
				'WP_EASYCART_OPTIMALSHIP_VERSION' => array( 'WP EasyCart for OptimalShip', '2.0.1' ),
			)
		);
		$old = array();
		foreach ( (array) $needs as $constant => $need ) {
			if ( defined( $constant ) && version_compare( (string) constant( $constant ), $need[1], '<' ) ) {
				$old[] = $need[0] . ' ' . $need[1];
			}
		}
		return $old;
	}
}

if ( ! function_exists( 'ecv2_store_details_weight_save_reply' ) ) {
	/**
	 * Filter wp_easycart_settings_save_reply: a saved weight unit sends the conversion offer as it now stands, so the page
	 * shows it without a reload ( settings-store-details-v2.js ).
	 *
	 * @since 6.0.5
	 * @param array  $reply Save reply.
	 * @param string $slug  Page slug.
	 * @param array  $keys  Saved keys.
	 * @return array
	 */
	function ecv2_store_details_weight_save_reply( $reply, $slug, $keys ) {
		if ( 'initial-setup' === $slug && in_array( 'ec_option_paypal_weight_unit', (array) $keys, true ) ) {
			$reply['weight_convert'] = ecv2_store_details_weight_convert_html();
		}
		return $reply;
	}
	if ( function_exists( 'add_filter' ) ) {
		add_filter( 'wp_easycart_settings_save_reply', 'ecv2_store_details_weight_save_reply', 10, 3 );
	}
}

$ecv2_store_details_demo_installed = (bool) get_option( 'ec_option_demo_data_installed' );
$ecv2_store_details_demo_actions   = $ecv2_store_details_demo_installed
	? array(
		array(
			'id'       => 'uninstall_demo_data',
			'label'    => __( 'Remove the demo data', 'wp-easycart' ),
			'desc'     => __( 'Deletes every demo product, category, option set, customer, order and shipping rate that the installer added. Your own data is not touched.', 'wp-easycart' ),
			'button'   => __( 'Remove demo data', 'wp-easycart' ),
			'confirm'  => __( 'Remove all demo products, categories, customers, orders and shipping rates? This cannot be undone.', 'wp-easycart' ),
			'danger'   => true,
			'callback' => 'ecv2_store_details_uninstall_demo_data',
			'legacy'   => 'initial-setup › Uninstall Demo Data › Uninstall Demo Data',
		),
	)
	: array(
		array(
			'id'       => 'install_demo_data',
			'label'    => __( 'Install the demo data', 'wp-easycart' ),
			'desc'     => __( 'Adds sample clothing products, categories, option sets, a customer, orders and three shipping rates so you can try the store before adding your own. Everything it adds can be removed again from here.', 'wp-easycart' ),
			'button'   => __( 'Install demo data', 'wp-easycart' ),
			'confirm'  => __( 'Install the sample products, categories, customer, orders and shipping rates now?', 'wp-easycart' ),
			'danger'   => false,
			'callback' => 'ecv2_store_details_install_demo_data',
			'legacy'   => 'initial-setup › Install Demo Data (Optional) › Install Demo Data Now!',
		),
	);

return array(
	'slug'        => 'initial-setup',
	'title'       => __( 'Store details', 'wp-easycart' ),
	'description' => __( 'The pages your store runs on, where it is based, its units, how prices are written and your monthly sales goal. The setup wizard filled these in; change them here at any time.', 'wp-easycart' ),
	'group'       => 'store',
	'order'       => 10,
	'icon'        => 'store',
	'docs'        => array( 'settings', 'initial-setup', 'product-page' ),
	'legacy'      => array( 'initial-setup' ),
	'upsell'      => 'default',
	/* Store pages that are missing, unpublished or without their shortcode: red banner at the top, red note under the row, re-checked after every save. */
	'health'      => 'ecv2_store_details_health',
	'sections'    => array(

		'store-pages' => array(
			'title'  => __( 'Store pages', 'wp-easycart' ),
			'hint'   => __( 'The three WordPress pages EasyCart needs; each holds one shortcode', 'wp-easycart' ),
			'fields' => array(
				'ecv2_store_details_wizard_note' => array(
					'type'   => 'html',
					'label'  => __( 'Setup wizard', 'wp-easycart' ),
					'render' => 'ecv2_store_details_render_wizard_note',
				),
				/* The three page rows are search pickers ( the stored value is still the page id ):
				 * only the chosen page's title is loaded, and matches come from WP_Query as the
				 * merchant types, so a site with tens of thousands of pages renders instantly. */
				'ec_option_storepage' => array(
					'type'              => 'select',
					'label'             => __( 'Store page', 'wp-easycart' ),
					'desc'              => __( 'Your product catalog. Must contain the [ec_store] shortcode. Product, category and manufacturer links are built under this page’s slug, so changing it changes those URLs. Type to find the page.', 'wp-easycart' ),
					'default'           => '',
					'placeholder'       => __( 'Search pages by title…', 'wp-easycart' ),
					'search_callback'   => 'ecv2_store_details_search_pages',
					'validate_callback' => 'ecv2_store_details_lookup_pages',
					'validate'          => 'ecv2_store_details_validate_storepage',
					'on_save'           => 'ecv2_store_details_storepage_saved',
					'keywords'          => array( 'shop', 'catalog', 'landing', 'products page', 'ec_store' ),
					'legacy'            => array( 'page' => 'initial-setup', 'section' => 'Store Landing Page', 'label' => 'Store Landing Page' ),
				),
				'ec_option_cartpage' => array(
					'type'              => 'select',
					'label'             => __( 'Cart and checkout page', 'wp-easycart' ),
					'desc'              => __( 'Where shoppers review their cart and pay. Must contain the [ec_cart] shortcode. Type to find the page.', 'wp-easycart' ),
					'default'           => '',
					'placeholder'       => __( 'Search pages by title…', 'wp-easycart' ),
					'search_callback'   => 'ecv2_store_details_search_pages',
					'validate_callback' => 'ecv2_store_details_lookup_pages',
					'validate'          => 'ecv2_store_details_validate_cartpage',
					'keywords'          => array( 'basket', 'checkout', 'ec_cart' ),
					'legacy'            => array( 'page' => 'initial-setup', 'section' => 'Cart Page', 'label' => 'Cart Page' ),
				),
				'ec_option_accountpage' => array(
					'type'              => 'select',
					'label'             => __( 'Account page', 'wp-easycart' ),
					'desc'              => __( 'Where shoppers sign in, register and see their order history. Must contain the [ec_account] shortcode. Type to find the page.', 'wp-easycart' ),
					'default'           => '',
					'placeholder'       => __( 'Search pages by title…', 'wp-easycart' ),
					'search_callback'   => 'ecv2_store_details_search_pages',
					'validate_callback' => 'ecv2_store_details_lookup_pages',
					'validate'          => 'ecv2_store_details_validate_accountpage',
					'keywords'          => array( 'login', 'register', 'order history', 'ec_account' ),
					'legacy'            => array( 'page' => 'initial-setup', 'section' => 'Account Page', 'label' => 'Account Page' ),
				),
			),
			/* 'confirm_title' + 'confirm' ( one-line body ) + 'confirm_button' open the V2 confirm dialog the rest of the admin uses. */
			'actions' => array(
				array(
					'id'             => 'create_storepage',
					'label'          => __( 'Create a new store page', 'wp-easycart' ),
					'desc'           => __( 'Publishes a page called “Store” containing [ec_store] and selects it above.', 'wp-easycart' ),
					'button'         => __( 'Create store page', 'wp-easycart' ),
					'confirm_title'  => __( 'Create the store page?', 'wp-easycart' ),
					'confirm'        => __( 'A page called “Store” containing [ec_store] will be published and selected as your store page.', 'wp-easycart' ),
					'confirm_button' => __( 'Create page', 'wp-easycart' ),
					'callback'       => 'ecv2_store_details_create_storepage',
					'legacy'         => 'initial-setup › Store Landing Page › Create New Store Page',
				),
				array(
					'id'             => 'create_cartpage',
					'label'          => __( 'Create a new cart page', 'wp-easycart' ),
					'desc'           => __( 'Publishes a page called “Cart” containing [ec_cart] and selects it above.', 'wp-easycart' ),
					'button'         => __( 'Create cart page', 'wp-easycart' ),
					'confirm_title'  => __( 'Create the cart page?', 'wp-easycart' ),
					'confirm'        => __( 'A page called “Cart” containing [ec_cart] will be published and selected as your cart and checkout page.', 'wp-easycart' ),
					'confirm_button' => __( 'Create page', 'wp-easycart' ),
					'callback'       => 'ecv2_store_details_create_cartpage',
					'legacy'         => 'initial-setup › Cart Page › Create New Cart Page',
				),
				array(
					'id'             => 'create_accountpage',
					'label'          => __( 'Create a new account page', 'wp-easycart' ),
					'desc'           => __( 'Publishes a page called “Account” containing [ec_account] and selects it above.', 'wp-easycart' ),
					'button'         => __( 'Create account page', 'wp-easycart' ),
					'confirm_title'  => __( 'Create the account page?', 'wp-easycart' ),
					'confirm'        => __( 'A page called “Account” containing [ec_account] will be published and selected as your account page.', 'wp-easycart' ),
					'confirm_button' => __( 'Create page', 'wp-easycart' ),
					'callback'       => 'ecv2_store_details_create_accountpage',
					'legacy'         => 'initial-setup › Account Page › Create New Account Page',
				),
			),
		),

		/* 6.0.5: where the store is based ( it was set only by the setup wizard, and read nowhere else ) and the units it weighs and
		 * measures in ( the weight unit lived in the PayPal drawer, the dimension unit among the shipping settings ). */
		'location' => array(
			'title'  => __( 'Country and units', 'wp-easycart' ),
			'hint'   => __( 'Where your business is based and how it weighs and measures', 'wp-easycart' ),
			/* 6.0.5: after the weight unit changes, the units note offers these ( ecv2_store_details_weight_convert_html() ). */
			'actions' => array(
				array(
					'id'       => 'convert_weights',
					'label'    => __( 'Convert saved weights', 'wp-easycart' ),
					'desc'     => __( 'Converts the weights of products, variants, option choices, boxes, weight-based shipping rates and orders to the new unit.', 'wp-easycart' ),
					'inline'   => true,
					'callback' => 'ecv2_store_details_convert_weights',
				),
				array(
					'id'       => 'keep_weights',
					'label'    => __( 'Keep saved weights', 'wp-easycart' ),
					'desc'     => __( 'The weights were already entered in the new unit.', 'wp-easycart' ),
					'inline'   => true,
					'callback' => 'ecv2_store_details_keep_weights',
				),
			),
			'fields' => array(
				'ec_option_store_country' => array(
					'type'     => 'select',
					'label'    => __( 'Store country', 'wp-easycart' ),
					'desc'     => __( 'Where your business is based. Decides which carriers are listed first, whether EU rules such as the withdrawal button apply, and the wallet country for Square.', 'wp-easycart' ),
					'default'  => ecv2_store_details_store_country(),
					'options'  => 'ecv2_store_details_country_options',
					'sanitize' => 'ecv2_store_details_sanitize_country',
					'on_save'  => 'ecv2_store_details_country_saved',
					'validate' => 'ecv2_store_details_country_warning',
					'keywords' => array( 'country', 'location', 'based', 'business address', 'region', 'eu', 'uk', 'canada', 'australia' ),
					'legacy'   => array( 'page' => 'setup-wizard', 'section' => 'Location', 'label' => 'Where is your store based?' ),
				),
				'ecv2_store_details_units_note' => array(
					'type'   => 'html',
					'label'  => __( 'Units', 'wp-easycart' ),
					'render' => 'ecv2_store_details_render_units_note',
				),
				'ec_option_paypal_weight_unit' => array(
					'type'     => 'pills',
					'label'    => __( 'Weight unit', 'wp-easycart' ),
					'desc'     => __( 'Live carriers and PayPal are given pounds or kilograms: ounces and grams are worked out for them. DHL, FedEx and UPS follow this unit when you change it.', 'wp-easycart' ),
					'default'  => 'lbs',
					'options'  => array(
						'lbs' => __( 'Pounds ( lb )', 'wp-easycart' ),
						'oz'  => __( 'Ounces ( oz )', 'wp-easycart' ),
						'kgs' => __( 'Kilograms ( kg )', 'wp-easycart' ),
						'g'   => __( 'Grams ( g )', 'wp-easycart' ),
					),
					'validate' => 'ecv2_store_details_weight_unit_warning',
					'keywords' => array( 'weight', 'pounds', 'kilograms', 'kg', 'lb', 'ounces', 'oz', 'grams', 'g', 'metric', 'imperial' ),
					'legacy'   => array( 'page' => 'payment', 'section' => 'PayPal', 'label' => 'Weight Unit' ),
				),
				'ec_option_enable_metric_unit_display' => array(
					'type'     => 'pills',
					'label'    => __( 'Dimension unit', 'wp-easycart' ),
					'desc'     => __( 'Length, width and height of products and boxes, and sizes that price an option.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => array(
						'0' => __( 'Inches ( in )', 'wp-easycart' ),
						'1' => __( 'Centimetres ( cm )', 'wp-easycart' ),
					),
					'keywords' => array( 'metric', 'inches', 'centimetres', 'centimeters', 'dimensions', 'size', 'length' ),
					'legacy'   => array( 'page' => 'shipping-settings', 'section' => 'Additional Shipping Options', 'label' => 'Dimension Unit' ),
				),
			),
		),

		/* 6.0.5 ( admin review M7, M5 ): the registered name, address and tax number receipts and invoices show ( wp_easycart_business ). */
		'business' => array(
			'title'  => __( 'Business details', 'wp-easycart' ),
			'hint'   => __( 'Your registered name, address and tax number, on receipts and invoices', 'wp-easycart' ),
			'fields' => array(
				'ec_option_business_name' => array(
					'type'        => 'text',
					'label'       => __( 'Registered business name', 'wp-easycart' ),
					'desc'        => __( 'The legal name receipts and invoices show. EU and UK invoices must show it.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => ( function_exists( 'get_bloginfo' ) && function_exists( 'wp_specialchars_decode' ) ) ? wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) : '',
					'keywords'    => array( 'legal name', 'company', 'seller', 'invoice', 'receipt' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Business details', 'label' => 'Registered business name ( 6.0.5 )' ),
				),
				'ec_option_business_address' => array(
					'type'        => 'textarea',
					'label'       => __( 'Registered address', 'wp-easycart' ),
					'desc'        => __( 'The address on your business registration, one line each.', 'wp-easycart' ),
					'default'     => '',
					'keywords'    => array( 'business address', 'company address', 'seller', 'invoice' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Business details', 'label' => 'Registered address ( 6.0.5 )' ),
				),
				'ec_option_business_tax_id' => array(
					'type'        => 'text',
					'label'       => class_exists( 'wp_easycart_business' ) ? wp_easycart_business::auto_label() : __( 'VAT number', 'wp-easycart' ),
					'desc'        => __( 'Your VAT, GST or ABN number. Tax invoices in the EU, the UK, Australia and New Zealand must show it.', 'wp-easycart' ),
					'default'     => '',
					'keywords'    => array( 'vat number', 'abn', 'gst number', 'tax id', 'ein', 'tax invoice' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Business details', 'label' => 'Tax number ( 6.0.5 )' ),
				),
				'ec_option_business_tax_label' => array(
					'type'        => 'text',
					'label'       => __( 'What the number is called', 'wp-easycart' ),
					'desc'        => __( 'Leave it empty to use your country\'s name for it.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => class_exists( 'wp_easycart_business' ) ? wp_easycart_business::auto_label() : '',
					'advanced'    => true,
					'keywords'    => array( 'vat number', 'abn', 'tax id label' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Business details', 'label' => 'Tax number label ( 6.0.5 )' ),
				),
			),
		),

		'currency' => array(
			'title'  => __( 'Currency', 'wp-easycart' ),
			'hint'    => __( 'How every price is written across the store, emails and admin', 'wp-easycart' ),
			'enqueue' => function ( $page, $section ) {
				$base = plugins_url( '/admin/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
				wp_enqueue_style( 'wp_easycart_admin_settings_store_details_v2_css', $base . 'css/settings-store-details-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_admin_settings_store_details_v2_js', $base . 'js/settings-store-details-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
				wp_localize_script( 'wp_easycart_admin_settings_store_details_v2_js', 'ecsd_vars', array(
					/* translators: 1: currency code, 2: sample price in that currency's usual format */
					'preset' => __( 'Usual %1$s format: %2$s', 'wp-easycart' ),
				) );
			},
			/* 6.0.5 ( Matt's H2 ): past orders keep their currency; this offers to show them in the store's ( ecv2_store_details_order_currency_html() ). */
			'actions' => array(
				array(
					'id'       => 'relabel_order_currency',
					'label'    => __( 'Show past orders in the store\'s currency', 'wp-easycart' ),
					'desc'     => __( 'For orders that were really paid in the store\'s currency although they were recorded in another.', 'wp-easycart' ),
					'inline'   => true,
					'callback' => 'ecv2_store_details_relabel_order_currency',
				),
			),
			'fields'  => array(
				'ecv2_store_details_currency_preview' => array(
					'type'   => 'html',
					'label'  => __( 'Price preview', 'wp-easycart' ),
					'render' => 'ecv2_store_details_render_currency_preview',
				),
				'ec_option_base_currency' => array(
					'suggestions' => array_map( function ( $preset ) { return $preset['name']; }, ecv2_store_details_currency_presets() ),
					'type'        => 'text',
					'label'       => __( 'Currency code', 'wp-easycart' ),
					'desc'        => __( 'Three-letter ISO code such as USD, EUR or GBP. Sent to your payment gateway with every charge and used as the base for the currency switcher.', 'wp-easycart' ),
					'default'     => 'USD',
					'placeholder' => 'USD',
					'sanitize'    => 'ecv2_store_details_sanitize_currency_code',
					'keywords'    => array( 'iso', 'usd', 'eur', 'gbp', 'base currency' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Code' ),
				),
				'ec_option_currency' => array(
					'type'        => 'text',
					'label'       => __( 'Currency symbol', 'wp-easycart' ),
					'desc'        => __( 'Printed with every price: $, €, £, kr and so on. Up to 10 characters.', 'wp-easycart' ),
					'default'     => '$',
					'placeholder' => '$',
					'sanitize'    => 'ecv2_store_details_sanitize_symbol',
					'keywords'    => array( 'dollar', 'euro', 'pound', 'sign' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Symbol' ),
				),
				'ec_option_currency_symbol_location' => array(
					'type'     => 'pills',
					'label'    => __( 'Symbol position', 'wp-easycart' ),
					'desc'     => ecv2_store_details_position_desc(),
					'default'  => '1',
					'options'  => array(
						'1' => __( 'Before the amount', 'wp-easycart' ),
						'0' => __( 'After the amount', 'wp-easycart' ),
					),
					'keywords' => array( 'left', 'right', 'prefix', 'suffix' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Location' ),
				),
				/* 6.0.5 ( admin review M9 ): 93,00 € or CHF 12.00, as most of Europe writes prices. */
				'ec_option_currency_symbol_space' => array(
					'type'     => 'toggle',
					'label'    => __( 'Space between the symbol and the amount', 'wp-easycart' ),
					'desc'     => __( 'Writes 93,00 € or CHF 12.00 instead of 93,00€. The space never lets a price break over two lines.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'space', 'gap', 'euro', 'format', 'nbsp' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Space ( 6.0.5 )' ),
				),
				'ec_option_show_currency_code' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the currency code with prices', 'wp-easycart' ),
					'desc'     => __( 'Prefixes every price with the code, e.g. USD $40.00. Helpful when you sell to more than one country.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'iso', 'code display', 'usd' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency Code: Display' ),
				),
				'ec_option_currency_decimal_symbol' => array(
					'type'     => 'pills',
					'label'    => __( 'Decimal separator', 'wp-easycart' ),
					'desc'     => __( 'Between whole units and fractions: a point in the US and UK, a comma across most of Europe.', 'wp-easycart' ),
					'default'  => '.',
					'options'  => ecv2_store_details_separator_options( 'decimal' ),
					'sanitize' => 'ecv2_store_details_sanitize_separator',
					'keywords' => array( 'fraction', 'decimal point', 'cents', 'comma' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Fractional Symbol' ),
				),
				'ec_option_currency_thousands_seperator' => array(
					'type'     => 'pills',
					'label'    => __( 'Thousands separator', 'wp-easycart' ),
					'desc'     => __( 'Between groups of three digits. Pick one that differs from the decimal separator.', 'wp-easycart' ),
					'default'  => ',',
					'options'  => ecv2_store_details_separator_options( 'thousands' ),
					'sanitize' => 'ecv2_store_details_sanitize_separator',
					'validate' => 'ecv2_store_details_separator_warning',
					'keywords' => array( 'grouping', 'group symbol', 'comma', 'digit grouping' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Group Symbol' ),
				),
				'ec_option_currency_decimal_places' => array(
					'type'     => 'pills',
					'label'    => __( 'Decimal places', 'wp-easycart' ),
					'desc'     => __( 'Digits after the separator: 2 for most currencies, 0 for currencies such as the yen.', 'wp-easycart' ),
					'default'  => '2',
					'options'  => array( '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
					'sanitize' => 'ecv2_store_details_sanitize_decimal_places',
					'keywords' => array( 'fraction', 'precision', 'cents', 'rounding' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Fractional Length' ),
				),
				'ec_option_currency_negative_location' => array(
					'type'     => 'pills',
					'label'    => __( 'Minus sign position', 'wp-easycart' ),
					'desc'     => __( 'For refunds and discounts: before the symbol gives -$5.00; after gives $-5.00.', 'wp-easycart' ),
					'default'  => '1',
					'advanced' => true,
					'options'  => array(
						'1' => __( 'Before the symbol', 'wp-easycart' ),
						'0' => __( 'After the symbol', 'wp-easycart' ),
					),
					'keywords' => array( 'negative', 'minus', 'refund', 'discount' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Negative Location' ),
				),
				'ec_option_exchange_rates' => array(
					'type'      => 'pairs',
					'separator' => ',',
					'pair'      => array(
						'key'   => array( 'label' => __( 'Currency', 'wp-easycart' ), 'placeholder' => 'EUR', 'maxlength' => 3, 'upper' => true ),
						'value' => array( 'label' => __( 'Rate', 'wp-easycart' ), 'placeholder' => '0.92', 'inputmode' => 'decimal' ),
						'join'  => '=',
						'add'   => __( 'Add currency', 'wp-easycart' ),
					),
					'label'     => __( 'Exchange rates', 'wp-easycart' ),
					'desc'      => __( 'Used only when a shopper switches currency with the currency widget. The rate is how much of that currency one unit of your base currency buys. Leave your base currency out.', 'wp-easycart' ),
					'default'   => '',
					'advanced'  => true,
					'sanitize'  => 'ecv2_store_details_sanitize_exchange_rates',
					'validate'  => 'ecv2_store_details_exchange_rates_warning',
					'keywords'  => array( 'conversion', 'currency switcher', 'multi currency', 'rates' ),
					'legacy'    => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Exchange Rates' ),
				),
			),
		),

		'goals' => array(
			'title'  => __( 'Sales goal', 'wp-easycart' ),
			'hint'   => __( 'The progress meter in the EasyCart admin sidebar', 'wp-easycart' ),
			'fields' => array(
				'ec_option_admin_display_sales_goal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the monthly goal meter', 'wp-easycart' ),
					'desc'     => __( 'Shows this month’s sales against your goal in the admin sidebar.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'goal', 'target', 'sidebar', 'progress' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'eCommerce Goals', 'label' => 'eCommerce Goals' ),
				),
				'ec_option_admin_sales_goal' => array(
					'type'        => 'number',
					'label'       => __( 'Monthly sales goal', 'wp-easycart' ),
					'desc'        => __( 'The revenue you are aiming for each month, in your store currency.', 'wp-easycart' ),
					'default'     => '1',
					'placeholder' => '5000',
					'min'         => 0,
					'step'        => 0.01,
					'parent'      => 'ec_option_admin_display_sales_goal',
					'sanitize'    => 'ecv2_store_details_sanitize_goal',
					'keywords'    => array( 'goal', 'target', 'revenue', 'monthly' ),
					'legacy'      => array( 'page' => 'initial-setup', 'section' => 'eCommerce Goals', 'label' => 'Monthly Goal' ),
				),
			),
		),

		'demo-data' => array(
			'title'   => __( 'Demo data', 'wp-easycart' ),
			'hint'    => $ecv2_store_details_demo_installed ? __( 'Sample products, customers and orders are installed', 'wp-easycart' ) : __( 'Sample products and orders for trying the store out', 'wp-easycart' ),
			'fields'  => array(),
			'actions' => $ecv2_store_details_demo_actions,
		),
	),
);
