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
		$sample   = get_option( 'ec_option_currency_symbol_location', '1' ) ? $symbol . $amount : $amount . $symbol;
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
		$symbol = sanitize_text_field( (string) $raw );
		return function_exists( 'mb_substr' ) ? mb_substr( $symbol, 0, 10 ) : substr( $symbol, 0, 10 );
	}
}

if ( ! function_exists( 'ecv2_store_details_sanitize_separator' ) ) {
	/** A single character ( classic filter_length( …, 1 ) / substr( …, 0, 1 ) ); may be empty. */
	function ecv2_store_details_sanitize_separator( $raw, $field ) {
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
		return array(
			'USD' => array( 'name' => __( 'US dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'EUR' => array( 'name' => __( 'Euro', 'wp-easycart' ), 'symbol' => '€', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'GBP' => array( 'name' => __( 'British pound', 'wp-easycart' ), 'symbol' => '£', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'CAD' => array( 'name' => __( 'Canadian dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'AUD' => array( 'name' => __( 'Australian dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'NZD' => array( 'name' => __( 'New Zealand dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'JPY' => array( 'name' => __( 'Japanese yen', 'wp-easycart' ), 'symbol' => '¥', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '0' ),
			'CHF' => array( 'name' => __( 'Swiss franc', 'wp-easycart' ), 'symbol' => 'CHF', 'before' => '1', 'decimal' => '.', 'thousands' => "'", 'places' => '2' ),
			'SEK' => array( 'name' => __( 'Swedish krona', 'wp-easycart' ), 'symbol' => 'kr', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'NOK' => array( 'name' => __( 'Norwegian krone', 'wp-easycart' ), 'symbol' => 'kr', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'DKK' => array( 'name' => __( 'Danish krone', 'wp-easycart' ), 'symbol' => 'kr.', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'PLN' => array( 'name' => __( 'Polish złoty', 'wp-easycart' ), 'symbol' => 'zł', 'before' => '0', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'MXN' => array( 'name' => __( 'Mexican peso', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'BRL' => array( 'name' => __( 'Brazilian real', 'wp-easycart' ), 'symbol' => 'R$', 'before' => '1', 'decimal' => ',', 'thousands' => '.', 'places' => '2' ),
			'INR' => array( 'name' => __( 'Indian rupee', 'wp-easycart' ), 'symbol' => '₹', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'ZAR' => array( 'name' => __( 'South African rand', 'wp-easycart' ), 'symbol' => 'R', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'SGD' => array( 'name' => __( 'Singapore dollar', 'wp-easycart' ), 'symbol' => '$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'HKD' => array( 'name' => __( 'Hong Kong dollar', 'wp-easycart' ), 'symbol' => 'HK$', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'CNY' => array( 'name' => __( 'Chinese yuan', 'wp-easycart' ), 'symbol' => '¥', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '2' ),
			'KRW' => array( 'name' => __( 'South Korean won', 'wp-easycart' ), 'symbol' => '₩', 'before' => '1', 'decimal' => '.', 'thousands' => ',', 'places' => '0' ),
		);
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
	'description' => __( 'The pages your store runs on, how prices are written and your monthly sales goal. The setup wizard filled these in; change them here at any time.', 'wp-easycart' ),
	'group'       => 'store-setup',
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
					'desc'     => __( 'Before the amount gives $40.00; after gives 40.00$.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => array(
						'1' => __( 'Before the amount', 'wp-easycart' ),
						'0' => __( 'After the amount', 'wp-easycart' ),
					),
					'keywords' => array( 'left', 'right', 'prefix', 'suffix' ),
					'legacy'   => array( 'page' => 'initial-setup', 'section' => 'Currency', 'label' => 'Currency: Location' ),
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
					'default'   => 'EUR=.73,GBP=.6,JPY=101.9',
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
