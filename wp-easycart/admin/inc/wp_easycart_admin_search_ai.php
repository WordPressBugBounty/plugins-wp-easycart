<?php
/**
 * WP EasyCart Admin — Settings › Search & AI, and the product editor's Search & AI card ( 6.0.2 ).
 *
 * The settings page ( declaration admin/template/settings/search-ai.php ) gets its status card, shipping summary and
 * "who can read your store" check from here. The product editor card edits the barcode, part number and condition,
 * saved into ec_product_google_attributes ( the record WP EasyCart PRO's Google Merchant panel and feed use ), shows a
 * checklist and previews the markup built by wp_easycart_product_schema. Store Status gets its rows from here too.
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_search_ai_guard' ) ) {
	/**
	 * AJAX guard: nonce and capability ( a plain function, so the nonce sniff sees it ).
	 */
	function ecv2_search_ai_guard() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-ecv2-search-ai' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) && ! current_user_can( 'wpec_settings' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register these capabilities.
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'wp-easycart' ) ) );
		}
	}
}

if ( ! class_exists( 'wp_easycart_admin_search_ai' ) ) :

	/**
	 * Search & AI admin.
	 *
	 * @since 6.0.2
	 */
	class wp_easycart_admin_search_ai {

		/** Nonce for the AJAX calls. */
		const NONCE = 'wp-easycart-ecv2-search-ai';

		/** Cached crawler check ( 12 hours ). */
		const CRAWL_TRANSIENT = 'wpec_search_ai_crawlers';

		/**
		 * Register hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ec_admin_ajax_save_product_details_search_ai', array( __CLASS__, 'ajax_save_product' ) );
			add_action( 'wp_ajax_ecv2_search_ai_preview', array( __CLASS__, 'ajax_preview' ) );
			/* The Search & AI card owns barcode, part number and condition: PRO's Google Merchant card stops showing its copies. */
			add_filter( 'wp_easycart_admin_product_details_google_merchant_fields_list', array( __CLASS__, 'google_fields' ), 9990 );
		}

		/**
		 * The settings page's address.
		 *
		 * @param string $section Section.
		 * @return string
		 */
		public static function url( $section = '' ) {
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=search-ai' ) . ( '' !== $section ? '#ecst-sec-' . $section : '' );
		}

		/**
		 * Page assets ( declared as the page's 'enqueue' ).
		 */
		public static function enqueue() {
			wp_enqueue_style( 'wp_easycart_admin_search_ai', plugins_url( 'wp-easycart/admin/css/search-ai-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
		}

		/**
		 * The engine classes are loaded.
		 *
		 * @return bool
		 */
		private static function engine() {
			return class_exists( 'wp_easycart_product_schema' ) && class_exists( 'wp_easycart_store_schema' );
		}

		// ------------------------------------------------------------------
		// Status.
		// ------------------------------------------------------------------

		/**
		 * Catalog numbers for the status card.
		 *
		 * @return array active, hidden, no_id, reviews, rating.
		 */
		public static function counts() {
			global $wpdb;
			$active = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1' );
			$hidden = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ec_product WHERE activate_in_store = 1 AND ( login_for_pricing = 1 OR is_donation = 1 OR ( catalog_mode = 1 AND 1 = %d ) OR ( inquiry_mode = 1 AND 1 = %d ) OR ( replace_price_label = 1 AND enable_price_label IN ( 2, 4, 6, 7 ) ) )',
					get_option( 'ec_option_hide_price_seasonal' ) ? 1 : 0,
					get_option( 'ec_option_hide_price_inquiry' ) ? 1 : 0
				)
			);
			$no_id   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product p LEFT JOIN ec_product_google_attributes g ON g.product_id = p.product_id WHERE p.activate_in_store = 1 AND p.is_donation = 0 AND ( g.attribute_value IS NULL OR NOT ( ( g.attribute_value LIKE '%\"gtin\":\"%' AND g.attribute_value NOT LIKE '%\"gtin\":\"\"%' ) OR ( g.attribute_value LIKE '%\"mpn\":\"%' AND g.attribute_value NOT LIKE '%\"mpn\":\"\"%' ) OR g.attribute_value LIKE '%\"identifier_exists\":\"no\"%' ) )" );
			$reviews = $wpdb->get_row( 'SELECT COUNT(*) AS n, AVG( rating ) AS rating FROM ec_review WHERE approved = 1' );
			return array(
				'active'  => $active,
				'hidden'  => $hidden,
				'no_id'   => $no_id,
				'reviews' => $reviews ? (int) $reviews->n : 0,
				'rating'  => ( $reviews && $reviews->n ) ? round( (float) $reviews->rating, 1 ) : 0,
			);
		}

		/**
		 * One status line.
		 *
		 * @param bool   $ok   Good.
		 * @param string $html Escaped text.
		 */
		private static function line( $ok, $html ) {
			echo '<li class="' . ( $ok ? 'is-ok' : 'is-warn' ) . '">' . $html . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass escaped text and links built with esc_url().
		}

		/**
		 * One number.
		 *
		 * @param string $value    Number.
		 * @param string $label    What it counts.
		 * @param string $modifier is-ok | is-warn.
		 */
		private static function kpi( $value, $label, $modifier = '' ) {
			echo '<div class="ecsa-kpi ' . esc_attr( $modifier ) . '"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span></div>';
		}

		/**
		 * Section Status: what is shared, and what to fix.
		 */
		public static function render_status() {
			if ( ! self::engine() ) {
				return;
			}
			$counts   = self::counts();
			$enabled  = wp_easycart_product_schema::enabled();
			$crawlers = self::crawlers();
			$returns  = wp_easycart_store_schema::return_policy();
			$shipping = wp_easycart_store_schema::shipping();
			$plugin   = wp_easycart_product_schema::seo_plugin();

			echo '<div class="ecsa-kpis">';
			self::kpi( number_format_i18n( max( 0, $counts['active'] - $counts['hidden'] ) ), __( 'products shared with price and stock', 'wp-easycart' ), $enabled ? 'is-ok' : '' );
			self::kpi( number_format_i18n( $counts['hidden'] ), __( 'shared without a price ( catalog, inquiry, log in for price, donations )', 'wp-easycart' ) );
			self::kpi( number_format_i18n( $counts['no_id'] ), __( 'missing a barcode or part number', 'wp-easycart' ), $counts['no_id'] > 0 ? 'is-warn' : 'is-ok' );
			/* translators: %s: number of reviews. */
			self::kpi( $counts['reviews'] ? number_format_i18n( $counts['rating'], 1 ) . ' ★' : '—', sprintf( _n( 'average rating across %s review', 'average rating across %s reviews', $counts['reviews'], 'wp-easycart' ), number_format_i18n( $counts['reviews'] ) ) );
			echo '</div>';

			echo '<ul class="ecsa-lines">';
			if ( $enabled ) {
				self::line( true, esc_html__( 'Product pages share their price, stock, variants, barcode and reviews.', 'wp-easycart' ) );
			} else {
				self::line( false, '<b>' . esc_html__( 'Product details are not shared.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Search engines and AI assistants only see the page text. Turn it on below unless another plugin already adds this data.', 'wp-easycart' ) );
			}
			if ( $returns ) {
				self::line( true, '<b>' . esc_html__( 'Return policy', 'wp-easycart' ) . '</b> ' . esc_html( self::returns_summary() ) );
			} elseif ( in_array( (string) get_option( 'ec_option_returns_policy', '' ), array( 'finite', 'unlimited', 'none' ), true ) ) {
				/* 6.0.2: a policy is set, but Google needs the countries it applies to ( wp_easycart_store_schema::return_policy() ). */
				self::line( false, '<b>' . esc_html__( 'Your return policy isn\'t shared yet.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Google needs the countries it applies to. Choose them under Returns.', 'wp-easycart' ) . ' <a href="#ecst-sec-returns">' . esc_html__( 'Choose countries', 'wp-easycart' ) . '</a>' );
			} else {
				self::line( false, '<b>' . esc_html__( 'No return policy yet.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Google can show your return window next to your products. Add it under Returns.', 'wp-easycart' ) . ' <a href="#ecst-sec-returns">' . esc_html__( 'Add it', 'wp-easycart' ) . '</a>' );
			}
			$listed = 0;
			$other  = 0;
			foreach ( $shipping['zones'] as $zone ) {
				if ( $zone['listed'] ) {
					++$listed;
				} else {
					++$other;
				}
			}
			if ( 'none' === $shipping['method'] ) {
				self::line( true, '<b>' . esc_html__( 'Shipping', 'wp-easycart' ) . '</b> ' . esc_html__( 'is off for this store, so nothing is shared about it.', 'wp-easycart' ) );
			} elseif ( $listed ) {
				/* translators: %d: shipping zones. */
				self::line( 0 === $other, '<b>' . esc_html__( 'Shipping', 'wp-easycart' ) . '</b> ' . esc_html( sprintf( _n( 'shared for %d zone.', 'shared for %d zones.', $listed, 'wp-easycart' ), $listed ) ) . ( $other ? ' ' . esc_html__( 'Some zones can\'t be described; see Shipping.', 'wp-easycart' ) : '' ) );
			} else {
				self::line( false, '<b>' . esc_html__( 'Shipping isn\'t shared.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Your rates can\'t be described as fixed prices ( see Shipping ). Set your shipping in Google Merchant Center instead.', 'wp-easycart' ) );
			}
			$blocked = array();
			foreach ( $crawlers['bots'] as $bot ) {
				if ( ! $bot['allowed'] ) {
					$blocked[] = $bot['name'];
				}
			}
			if ( ! $crawlers['public'] ) {
				self::line( false, '<b>' . esc_html__( 'Search engines are discouraged from this whole site.', 'wp-easycart' ) . '</b> ' . esc_html__( 'Settings › Reading has "Discourage search engines" ticked, so no search engine or AI assistant shows your products.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Open Reading settings', 'wp-easycart' ) . '</a>' );
			} elseif ( $blocked ) {
				/* translators: %s: list of assistants. */
				self::line( false, '<b>' . esc_html( sprintf( __( 'Blocked: %s.', 'wp-easycart' ), implode( ', ', $blocked ) ) ) . '</b> ' . esc_html__( 'A rule in your robots.txt stops them reading your store, so they can\'t show your products.', 'wp-easycart' ) . ' <a href="#ecst-sec-crawlers">' . esc_html__( 'See how to fix it', 'wp-easycart' ) . '</a>' );
			} else {
				self::line( true, '<b>' . esc_html__( 'Google, Bing, ChatGPT, Perplexity, Claude and Gemini', 'wp-easycart' ) . '</b> ' . esc_html__( 'can all read your store.', 'wp-easycart' ) );
			}
			if ( '' !== $plugin ) {
				/* translators: %s: SEO plugin name. */
				self::line( true, esc_html( sprintf( __( '%s is active. See SEO plugin below for who prints what.', 'wp-easycart' ), wp_easycart_product_schema::seo_plugin_name( $plugin ) ) ) );
			}
			$override = self::template_override();
			if ( '' !== $override ) {
				self::line( false, '<b>' . esc_html__( 'Your theme\'s copy of the product page template still prints the old product data.', 'wp-easycart' ) . '</b> ' . esc_html( self::override_fix_text( $override ) ) );
			}
			echo '</ul>';

			$sample = self::sample_url();
			echo '<div class="ecsa-actions">';
			if ( '' !== $sample ) {
				echo '<a class="ecv2-btn ecv2-btn-sm" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( $sample ) ) . '">' . esc_html__( 'Test a product in Google', 'wp-easycart' ) . ' ↗</a>';
			}
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products' ) ) . '">' . esc_html__( 'Add barcodes in Products', 'wp-easycart' ) . '</a>';
			echo '</div>';
		}

		/**
		 * Section Google product feed without WP EasyCart PRO 6.0.2: what it does, and a preview with this store's numbers
		 * under the section's lock ( the header links to the upgrade, or to the update for an older PRO ). The engine calls it
		 * with the page and section, which it doesn't need.
		 */
		public static function render_feed_locked() {
			$counts = self::counts();
			$ready  = max( 0, $counts['active'] - $counts['hidden'] );
			$host   = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			echo '<p class="ecsa-sub">' . esc_html__( 'Google Merchant Center, Bing and Pinterest fetch a private feed address every day. It is built from the same data as your product pages and updates by itself, so there is nothing to upload and products never expire.', 'wp-easycart' ) . '</p>';
			echo '<div class="ecsa-mock" aria-hidden="true">';
			echo '<div class="ecsa-kpis">';
			self::kpi( number_format_i18n( $ready ), __( 'of your products ready for Google', 'wp-easycart' ), 'is-ok' );
			self::kpi( number_format_i18n( $counts['hidden'] ), __( 'left out: they hide their price', 'wp-easycart' ) );
			self::kpi( number_format_i18n( $counts['no_id'] ), __( 'without a barcode or part number', 'wp-easycart' ), $counts['no_id'] > 0 ? 'is-warn' : 'is-ok' );
			self::kpi( '2:00', __( 'rebuilt every night, and after changes', 'wp-easycart' ) );
			echo '</div>';
			echo '<div class="ecsa-mock-url">https://' . esc_html( $host ) . '/ec-feed/••••••••••••/google.xml</div>';
			echo '<ul class="ecsa-lines">';
			self::line( true, esc_html__( 'Google Merchant Center connected: products active, pending and disapproved, with the top problems to fix.', 'wp-easycart' ) );
			self::line( false, esc_html__( 'Products left out and why, missing or wrong barcodes, and images too small for Google.', 'wp-easycart' ) );
			echo '</ul></div>';
		}

		/**
		 * Google attributes in bulk, without WP EasyCart PRO 6.0.2 ( the section is locked; PRO draws the spreadsheet tool ).
		 *
		 * @since 6.0.2
		 */
		public static function render_attributes_locked() {
			echo '<p class="ecsa-sub">' . esc_html__( 'Download a spreadsheet of every product and variant with its Google attributes ( product category, gender, age group, sizes ), fill them in and upload it back. Your product feed picks the changes up within the hour.', 'wp-easycart' ) . '</p>';
		}

		/**
		 * The return policy in one sentence.
		 *
		 * @return string
		 */
		public static function returns_summary() {
			$policy = (string) get_option( 'ec_option_returns_policy', '' );
			if ( 'none' === $policy ) {
				return __( 'set: no returns.', 'wp-easycart' );
			}
			$window = ( 'unlimited' === $policy ) ? __( 'returns any time', 'wp-easycart' ) : sprintf( /* translators: %d: days. */ __( '%d-day returns', 'wp-easycart' ), max( 1, (int) get_option( 'ec_option_returns_days', 30 ) ) );
			$fees   = (string) get_option( 'ec_option_returns_fees', 'free' );
			$cost   = ( 'free' === $fees ) ? __( 'free', 'wp-easycart' ) : ( ( 'flat' === $fees ) ? __( 'for a fee', 'wp-easycart' ) : __( 'the customer pays shipping', 'wp-easycart' ) );
			/* translators: 1: window, 2: cost. */
			return sprintf( __( 'set: %1$s, %2$s.', 'wp-easycart' ), $window, $cost );
		}

		/**
		 * A product page to test in Google.
		 *
		 * @return string
		 */
		private static function sample_url() {
			global $wpdb;
			$post_id = (int) $wpdb->get_var( 'SELECT post_id FROM ec_product WHERE activate_in_store = 1 AND post_id > 0 ORDER BY product_id DESC LIMIT 1' );
			return $post_id ? (string) get_permalink( $post_id ) : '';
		}

		/**
		 * A theme ( wp-easycart-data ) copy of a product template that still prints the old inline product data.
		 *
		 * @return string File name, or ''.
		 */
		public static function template_override() {
			if ( ! defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) {
				return '';
			}
			$layout = (string) get_option( 'ec_option_base_layout' );
			if ( '' === $layout ) {
				return '';
			}
			foreach ( array( 'ec_product_details_page.php', 'ec_product_details_page_meta.php' ) as $file ) {
				$path = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . $layout . '/' . $file;
				if ( is_readable( $path ) ) {
					$text = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local template file.
					if ( false !== strpos( $text, 'application/ld+json' ) && false === strpos( $text, 'wp_easycart_product_schema' ) ) {
						return $file;
					}
				}
			}
			return '';
		}

		/**
		 * How to fix an old template copy.
		 *
		 * @param string $file File name.
		 * @return string
		 */
		private static function override_fix_text( $file ) {
			/* translators: %s: template file name. */
			return sprintf( __( 'In wp-easycart-data/design/layout/%1$s/%2$s, replace the <script type="application/ld+json"> block with: if ( class_exists( \'wp_easycart_product_schema\' ) ) { wp_easycart_product_schema::print_for( $this->product ); } ( $product in the Meta template ). Until then Google keeps reading the old product data from your copy.', 'wp-easycart' ), (string) get_option( 'ec_option_base_layout' ), $file );
		}

		// ------------------------------------------------------------------
		// Shipping.
		// ------------------------------------------------------------------

		/**
		 * Section Shipping: what can be shared for each zone.
		 */
		public static function render_shipping() {
			if ( ! self::engine() ) {
				return;
			}
			$shipping = wp_easycart_store_schema::shipping();
			if ( 'none' === $shipping['method'] ) {
				echo '<div class="ecsa-note">' . esc_html__( 'Shipping is off for this store ( Settings › Shipping ), so there is nothing to share.', 'wp-easycart' ) . '</div>';
				return;
			}
			if ( ! $shipping['zones'] ) {
				echo '<div class="ecsa-note">' . esc_html__( 'No shipping rates yet. Add them in Settings › Shipping rates.', 'wp-easycart' ) . '</div>';
				return;
			}
			$reasons = array(
				'live'                => __( 'Live carrier rates change with every order, so they can\'t be listed. Set this zone\'s shipping in Google Merchant Center instead.', 'wp-easycart' ),
				'countries'           => __( 'These rates apply everywhere. Choose the countries you sell to under Returns so they can be listed.', 'wp-easycart' ),
				'regions'             => __( 'This zone covers part of a country outside the US, Australia and Japan, which Google can\'t read. Set it in Google Merchant Center instead.', 'wp-easycart' ),
				'percentage_handling' => __( 'A percentage of the order plus a handling fee can\'t be described. Set this zone\'s shipping in Google Merchant Center instead.', 'wp-easycart' ),
			);
			echo '<div class="ecsa-zones">';
			foreach ( $shipping['zones'] as $zone_id => $zone ) {
				$name = '' !== $zone['name'] ? $zone['name'] : ( 0 === (int) $zone_id ? __( 'Everywhere you ship', 'wp-easycart' ) : __( 'Zone', 'wp-easycart' ) . ' ' . (int) $zone_id );
				echo '<div class="ecsa-zone">';
				echo '<b>' . esc_html( $name ) . '</b>';
				echo '<span>' . esc_html( $zone['listed'] ? self::zone_summary( $shipping['method'], $zone['rows'] ) : ( isset( $reasons[ $zone['reason'] ] ) ? $reasons[ $zone['reason'] ] : '' ) ) . '</span>';
				echo '<span class="ecsa-state ' . ( $zone['listed'] ? 'is-ok' : 'is-na' ) . '">' . esc_html( $zone['listed'] ? __( 'Shared', 'wp-easycart' ) : __( 'Not shared', 'wp-easycart' ) ) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}

		/**
		 * A zone's rates in a few words.
		 *
		 * @param string $method Rate type.
		 * @param array  $rows   Summary rows.
		 * @return string
		 */
		private static function zone_summary( $method, $rows ) {
			$money = function ( $value ) {
				return isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $value ) : number_format_i18n( $value, 2 );
			};
			$parts = array();
			foreach ( array_slice( $rows, 0, 4 ) as $row ) {
				if ( 'method' === $method ) {
					/* translators: 1: method name, 2: price. */
					$text = sprintf( __( '%1$s %2$s', 'wp-easycart' ), $row['label'], $money( $row['rate'] ) );
					if ( null !== $row['free'] ) {
						/* translators: %s: order value. */
						$text .= ', ' . sprintf( __( 'free over %s', 'wp-easycart' ), $money( $row['free'] ) );
					}
					$parts[] = $text;
				} elseif ( 'percentage' === $method ) {
					/* translators: 1: percent, 2: order value. */
					$parts[] = sprintf( __( '%1$s%% from %2$s', 'wp-easycart' ), number_format_i18n( $row['rate'], 2 ), $money( $row['min'] ) );
				} else {
					$from = ( 'price' === $method ) ? $money( $row['min'] ) : number_format_i18n( $row['min'] );
					/* translators: 1: price, 2: where the band starts. */
					$parts[] = sprintf( __( '%1$s from %2$s', 'wp-easycart' ), $money( $row['rate'] ), $from );
				}
			}
			$labels = array(
				'price'      => __( 'By order value', 'wp-easycart' ),
				'weight'     => __( 'By weight', 'wp-easycart' ),
				'quantity'   => __( 'By number of items', 'wp-easycart' ),
				'percentage' => __( 'Percentage of the order', 'wp-easycart' ),
				'method'     => __( 'Methods', 'wp-easycart' ),
			);
			$more = count( $rows ) > 4 ? ' …' : '';
			return ( isset( $labels[ $method ] ) ? $labels[ $method ] . ': ' : '' ) . implode( '; ', $parts ) . $more;
		}

		// ------------------------------------------------------------------
		// Who can read the store.
		// ------------------------------------------------------------------

		/**
		 * Crawlers that matter for shopping answers.
		 *
		 * @return array key => agent, name, what.
		 */
		public static function bots() {
			return array(
				'googlebot'        => array(
					'agent' => 'Googlebot',
					'name'  => __( 'Google', 'wp-easycart' ),
					'what'  => __( 'Search, the Shopping tab, AI Overviews and AI Mode', 'wp-easycart' ),
				),
				'bingbot'          => array(
					'agent' => 'Bingbot',
					'name'  => __( 'Bing and Copilot', 'wp-easycart' ),
					'what'  => __( 'Bing search and Microsoft Copilot answers', 'wp-easycart' ),
				),
				'oai-searchbot'    => array(
					'agent' => 'OAI-SearchBot',
					'name'  => __( 'ChatGPT search', 'wp-easycart' ),
					'what'  => __( 'Products in ChatGPT\'s search and shopping answers', 'wp-easycart' ),
				),
				'perplexitybot'    => array(
					'agent' => 'PerplexityBot',
					'name'  => __( 'Perplexity', 'wp-easycart' ),
					'what'  => __( 'Perplexity answers and its shopping results', 'wp-easycart' ),
				),
				'claude-searchbot' => array(
					'agent' => 'Claude-SearchBot',
					'name'  => __( 'Claude', 'wp-easycart' ),
					'what'  => __( 'Claude\'s web search answers', 'wp-easycart' ),
				),
				'google-extended'  => array(
					'agent' => 'Google-Extended',
					'name'  => __( 'Gemini app', 'wp-easycart' ),
					'what'  => __( 'Gemini app answers. Doesn\'t affect Google Search.', 'wp-easycart' ),
				),
			);
		}

		/**
		 * The store's robots.txt as crawlers see it: fetched from the site, else the file, else WordPress's own.
		 *
		 * @return array text, source ( live | none | file | virtual ).
		 */
		public static function robots_txt() {
			$response = wp_remote_get(
				home_url( '/robots.txt' ),
				array(
					'timeout'     => 8,
					'redirection' => 3,
					'user-agent'  => 'Mozilla/5.0 (compatible; WP EasyCart robots check)',
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( 200 === $code ) {
					return array(
						'text'   => (string) wp_remote_retrieve_body( $response ),
						'source' => 'live',
					);
				}
				if ( 404 === $code || 410 === $code ) {
					return array(
						'text'   => '',
						'source' => 'none',
					);
				}
			}
			$file = ABSPATH . 'robots.txt';
			if ( is_readable( $file ) ) {
				return array(
					'text'   => (string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the site's own robots.txt.
					'source' => 'file',
				);
			}
			$site   = wp_parse_url( site_url() );
			$path   = ! empty( $site['path'] ) ? $site['path'] : '';
			$output = "User-agent: *\nDisallow: " . $path . "/wp-admin/\nAllow: " . $path . "/wp-admin/admin-ajax.php\n";
			return array(
				'text'   => (string) apply_filters( 'robots_txt', $output, get_option( 'blog_public' ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core's own robots.txt filter, applied to build the same text.
				'source' => 'virtual',
			);
		}

		/**
		 * Parse robots.txt into groups ( RFC 9309 ).
		 *
		 * @param string $text robots.txt.
		 * @return array[] agents ( lower case ), rules ( allow, path, line ).
		 */
		public static function parse_robots( $text ) {
			$groups  = array();
			$current = null;
			$agent   = false;
			foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $number => $line ) {
				$line = trim( preg_replace( '/#.*$/', '', $line ) );
				if ( '' === $line || ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $match ) ) {
					continue;
				}
				$key   = strtolower( $match[1] );
				$value = trim( $match[2] );
				if ( 'user-agent' === $key ) {
					if ( ! $agent || null === $current ) {
						$groups[] = array(
							'agents' => array(),
							'rules'  => array(),
						);
						$current  = count( $groups ) - 1;
					}
					$token                          = strtolower( trim( preg_replace( '#/.*$#', '', $value ) ) );
					$groups[ $current ]['agents'][] = $token;
					$agent                          = true;
				} elseif ( ( 'allow' === $key || 'disallow' === $key ) && null !== $current ) {
					$groups[ $current ]['rules'][] = array(
						'allow' => 'allow' === $key,
						'path'  => $value,
						'line'  => $number + 1,
					);
					$agent                         = false;
				} else {
					$agent = false;
				}
			}
			return $groups;
		}

		/**
		 * Whether a crawler may read a path.
		 *
		 * @param array  $groups parse_robots().
		 * @param string $agent  Crawler token.
		 * @param string $path   Path.
		 * @return array allowed, line.
		 */
		public static function robots_allows( $groups, $agent, $path ) {
			$agent    = strtolower( $agent );
			$specific = array();
			$star     = array();
			foreach ( $groups as $group ) {
				if ( in_array( $agent, $group['agents'], true ) ) {
					$specific = array_merge( $specific, $group['rules'] );
				} elseif ( in_array( '*', $group['agents'], true ) ) {
					$star = array_merge( $star, $group['rules'] );
				}
			}
			$rules = $specific ? $specific : $star;
			$best  = null;
			$size  = -1;
			foreach ( $rules as $rule ) {
				if ( '' === $rule['path'] ) {
					continue; /* "Disallow:" with nothing allows everything */
				}
				$pattern = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $rule['path'], '#' ) ) . '#';
				if ( preg_match( $pattern, $path ) ) {
					$length = strlen( $rule['path'] );
					if ( $length > $size || ( $length === $size && $rule['allow'] ) ) {
						$best = $rule;
						$size = $length;
					}
				}
			}
			return array(
				'allowed' => ( null === $best || $best['allow'] ),
				'line'    => $best ? (int) $best['line'] : 0,
			);
		}

		/**
		 * The store page path ( what crawlers need to read ).
		 *
		 * @return string
		 */
		private static function store_path() {
			$page = (int) get_option( 'ec_option_storepage' );
			$path = $page ? (string) wp_parse_url( (string) get_permalink( $page ), PHP_URL_PATH ) : '';
			return '' !== $path ? $path : '/';
		}

		/**
		 * Who can read the store ( cached 12 hours ).
		 *
		 * @param bool $force Check again.
		 * @return array public, source, path, bots ( key => name, agent, what, allowed, line ), training ( blocked names ).
		 */
		public static function crawlers( $force = false ) {
			$cached = $force ? false : get_transient( self::CRAWL_TRANSIENT );
			if ( is_array( $cached ) && isset( $cached['bots'] ) ) {
				return $cached;
			}
			$robots = self::robots_txt();
			$groups = self::parse_robots( $robots['text'] );
			$path   = self::store_path();
			$public = '0' !== (string) get_option( 'blog_public', '1' );
			$out    = array(
				'public'   => $public,
				'source'   => $robots['source'],
				'path'     => $path,
				'bots'     => array(),
				'training' => array(),
				'checked'  => time(),
			);
			foreach ( self::bots() as $key => $bot ) {
				$rule                = self::robots_allows( $groups, $bot['agent'], $path );
				$out['bots'][ $key ] = array_merge(
					$bot,
					array(
						'allowed' => $public && $rule['allowed'],
						'line'    => $rule['line'],
					)
				);
			}
			foreach ( array( 'GPTBot', 'ClaudeBot', 'CCBot' ) as $agent ) {
				$rule = self::robots_allows( $groups, $agent, $path );
				if ( ! $rule['allowed'] ) {
					$out['training'][] = $agent;
				}
			}
			set_transient( self::CRAWL_TRANSIENT, $out, 12 * HOUR_IN_SECONDS );
			return $out;
		}

		/**
		 * Section Who can read your store.
		 */
		public static function render_crawlers() {
			$crawlers = self::crawlers();
			$sources  = array(
				'live'    => __( 'your live robots.txt', 'wp-easycart' ),
				'none'    => __( 'your site ( it has no robots.txt )', 'wp-easycart' ),
				'file'    => __( 'the robots.txt file on your server', 'wp-easycart' ),
				'virtual' => __( 'WordPress\'s own robots.txt', 'wp-easycart' ),
			);
			/* translators: 1: where the rules came from, 2: time. */
			echo '<p class="ecsa-sub">' . esc_html( sprintf( __( 'Checked against %1$s, %2$s.', 'wp-easycart' ), isset( $sources[ $crawlers['source'] ] ) ? $sources[ $crawlers['source'] ] : '', wp_date( get_option( 'time_format' ) . ', ' . get_option( 'date_format' ), (int) $crawlers['checked'] ) ) ) . '</p>';
			echo '<div class="ecsa-bots">';
			$blocked = false;
			foreach ( $crawlers['bots'] as $bot ) {
				$detail = $bot['what'];
				if ( ! $bot['allowed'] ) {
					$blocked = true;
					if ( ! $crawlers['public'] ) {
						$detail .= ' ' . __( 'Blocked because search engines are discouraged ( Settings › Reading ).', 'wp-easycart' );
					} elseif ( $bot['line'] ) {
						/* translators: %d: line number. */
						$detail .= ' ' . sprintf( __( 'Blocked by line %d of robots.txt.', 'wp-easycart' ), (int) $bot['line'] );
					}
				}
				echo '<div class="ecsa-bot"><div><b>' . esc_html( $bot['name'] ) . '</b><code>' . esc_html( $bot['agent'] ) . '</code></div><span>' . esc_html( $detail ) . '</span><span class="ecsa-state ' . ( $bot['allowed'] ? 'is-ok' : 'is-bad' ) . '">' . esc_html( $bot['allowed'] ? __( 'Can read', 'wp-easycart' ) : __( 'Blocked', 'wp-easycart' ) ) . '</span></div>';
			}
			echo '</div>';
			if ( $blocked && $crawlers['public'] ) {
				echo '<div class="ecsa-note is-warn"><b>' . esc_html__( 'How to fix it', 'wp-easycart' ) . '</b> ' . esc_html__( 'EasyCart doesn\'t change robots.txt: a block there comes from a file on your server, another plugin or your host, and it has to be fixed there. Remove the rule for the blocked assistant, or add "User-agent: OAI-SearchBot" followed by "Allow: /" ( with that assistant\'s name ).', 'wp-easycart' ) . '</div>';
			}
			if ( $crawlers['training'] ) {
				/* translators: %s: crawler names. */
				echo '<div class="ecsa-note">' . esc_html( sprintf( __( 'You block AI training crawlers ( %s ). That\'s fine: they are separate from the search crawlers above and don\'t decide whether your products are shown.', 'wp-easycart' ), implode( ', ', $crawlers['training'] ) ) ) . '</div>';
			}
			echo '<div class="ecsa-note">' . esc_html__( 'If your site is behind Cloudflare, check its AI crawler setting as well. WordPress can\'t see it.', 'wp-easycart' ) . '</div>';
		}

		/**
		 * Action: check the crawlers again.
		 *
		 * @return array
		 */
		public static function action_recheck() {
			delete_transient( self::CRAWL_TRANSIENT );
			self::crawlers( true );
			return array(
				'message' => __( 'Checked again.', 'wp-easycart' ),
				'reload'  => true,
			);
		}

		// ------------------------------------------------------------------
		// SEO plugin.
		// ------------------------------------------------------------------

		/**
		 * Section SEO plugin: which plugin is active and what it prints.
		 */
		public static function render_seo() {
			if ( ! self::engine() ) {
				return;
			}
			$plugin = wp_easycart_product_schema::seo_plugin();
			if ( '' === $plugin ) {
				echo '<div class="ecsa-note">' . esc_html__( 'No SEO plugin is active, so EasyCart prints each product\'s description and social preview itself. The switches below apply when you use Yoast SEO, Rank Math, All in One SEO or SEOPress.', 'wp-easycart' ) . '</div>';
				return;
			}
			$name = wp_easycart_product_schema::seo_plugin_name( $plugin );
			/* translators: %s: SEO plugin name. */
			$text = sprintf( __( '%s is active. EasyCart keeps printing the product data that Google and AI assistants read; the switches below decide who prints descriptions and social previews, and where your policies go.', 'wp-easycart' ), $name );
			if ( 'tsf' === $plugin ) {
				/* 6.0.2: wp_easycart_product_schema::seo_handoff() leaves The SEO Framework out: it can't be handed product data. */
				/* translators: %s: SEO plugin name. */
				$text = sprintf( __( '%s is active. It can\'t take product descriptions and images from EasyCart, so EasyCart keeps printing its own on product pages, whatever the first switch below says.', 'wp-easycart' ), $name );
			}
			if ( ! in_array( $plugin, array( 'yoast', 'rankmath' ), true ) ) {
				/* translators: %s: SEO plugin name. */
				$text .= ' ' . sprintf( __( '%s has no store details to add policies to, so EasyCart prints them on your home page.', 'wp-easycart' ), $name );
			} elseif ( '' === wp_easycart_store_schema::policy_plugin() && '0' !== (string) get_option( 'ec_option_seo_plugin_policies', '1' ) ) {
				/* translators: %s: SEO plugin name. */
				$text .= ' ' . sprintf( __( '%s describes this site as a person, not an organization, so EasyCart prints your policies on your home page.', 'wp-easycart' ), $name );
			}
			echo '<div class="ecsa-note">' . esc_html( $text ) . '</div>';
		}

		// ------------------------------------------------------------------
		// Store Status.
		// ------------------------------------------------------------------

		/**
		 * One Store Status row ( that screen counts these class names ).
		 *
		 * @param bool   $ok   Passed.
		 * @param string $html Escaped label.
		 */
		private static function status_row( $ok, $html ) {
			echo '<div class="' . ( $ok ? 'ec_status_success' : 'ec_status_error' ) . '"><div class="dashicons-before ' . ( $ok ? 'dashicons-yes' : 'dashicons-no' ) . '"></div><span class="ec_status_label">' . $html . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass escaped text and links built with esc_url().
		}

		/**
		 * Store Status rows: product data, crawlers, template copies.
		 */
		public static function store_status_rows() {
			if ( ! self::engine() ) {
				return;
			}
			$link = ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open Search & AI', 'wp-easycart' ) . '</a>';
			if ( wp_easycart_product_schema::enabled() ) {
				self::status_row( true, esc_html__( 'Product pages share their price, stock, variants and reviews with search engines and AI assistants.', 'wp-easycart' ) );
			} else {
				self::status_row( false, esc_html__( 'Product details for search engines and AI assistants are switched off.', 'wp-easycart' ) . $link );
			}
			$crawlers = self::crawlers();
			$blocked  = array();
			foreach ( $crawlers['bots'] as $bot ) {
				if ( ! $bot['allowed'] ) {
					$blocked[] = $bot['name'];
				}
			}
			if ( ! $crawlers['public'] ) {
				self::status_row( false, esc_html__( 'Search engines are discouraged from this site ( Settings › Reading ), so your products aren\'t shown in search or AI answers.', 'wp-easycart' ) );
			} elseif ( $blocked ) {
				/* translators: %s: list of assistants. */
				self::status_row( false, esc_html( sprintf( __( 'robots.txt blocks %s from reading your store.', 'wp-easycart' ), implode( ', ', $blocked ) ) ) . $link );
			} else {
				self::status_row( true, esc_html__( 'Google, Bing, ChatGPT, Perplexity, Claude and Gemini can read your store.', 'wp-easycart' ) );
			}
			$override = self::template_override();
			if ( '' !== $override ) {
				self::status_row( false, esc_html( self::override_fix_text( $override ) ) );
			}
		}

		// ------------------------------------------------------------------
		// Product editor card.
		// ------------------------------------------------------------------

		/**
		 * A product's Google attributes.
		 *
		 * @param int $product_id Product.
		 * @return array
		 */
		private static function attributes( $product_id ) {
			global $wpdb;
			$raw  = $wpdb->get_var( $wpdb->prepare( 'SELECT attribute_value FROM ec_product_google_attributes WHERE product_id = %d', (int) $product_id ) );
			$data = $raw ? json_decode( (string) $raw, true ) : array();
			return is_array( $data ) ? $data : array();
		}

		/**
		 * Check a GTIN's digit count and check digit.
		 *
		 * @param string $digits Digits only.
		 * @return string '' when valid, else the problem.
		 */
		public static function gtin_problem( $digits ) {
			if ( ! in_array( strlen( $digits ), array( 8, 12, 13, 14 ), true ) ) {
				return __( 'A barcode has 8, 12, 13 or 14 digits. For a 10-digit ISBN, use the 13-digit one that starts with 978.', 'wp-easycart' );
			}
			$sum  = 0;
			$body = substr( $digits, 0, -1 );
			$len  = strlen( $body );
			for ( $i = 0; $i < $len; $i++ ) {
				$digit = (int) $body[ $len - 1 - $i ];
				$sum  += ( 0 === $i % 2 ) ? $digit * 3 : $digit;
			}
			$check = ( 10 - ( $sum % 10 ) ) % 10;
			if ( (int) substr( $digits, -1 ) !== $check ) {
				return __( 'That barcode\'s last digit doesn\'t match the others. Check it for a typo.', 'wp-easycart' );
			}
			return '';
		}

		/**
		 * The Search & AI card on the product editor's SEO tab.
		 *
		 * @param object $editor wp_easycart_admin_details_products_v2.
		 */
		public static function print_product_card( $editor ) {
			global $wpdb;
			$product = isset( $editor->product ) ? $editor->product : null;
			if ( ! $product || empty( $product->product_id ) ) {
				return;
			}
			$product_id = (int) $product->product_id;
			$attributes = self::attributes( $product_id );
			$gtin       = isset( $attributes['gtin'] ) ? (string) $attributes['gtin'] : '';
			$mpn        = isset( $attributes['mpn'] ) ? (string) $attributes['mpn'] : '';
			$condition  = isset( $attributes['condition'] ) ? strtolower( (string) $attributes['condition'] ) : '';
			$no_barcode = ( isset( $attributes['identifier_exists'] ) && 'no' === $attributes['identifier_exists'] && '' === $gtin && '' === $mpn );
			$sec        = ' data-ecdv2-sec="search_ai"';
			$default    = class_exists( 'wp_easycart_product_schema' ) ? wp_easycart_product_schema::default_condition() : 'new';
			$conditions = array(
				'new'         => __( 'New', 'wp-easycart' ),
				'used'        => __( 'Used', 'wp-easycart' ),
				'refurbished' => __( 'Refurbished', 'wp-easycart' ),
			);

			$editor->section_open( 'search_ai', __( 'Search & AI', 'wp-easycart' ), __( 'What Google and AI assistants read about this product', 'wp-easycart' ) );
			echo '<div class="ecdv2-sai" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" data-product-id="' . esc_attr( $product_id ) . '">';

			echo '<div class="ecdv2-sai-fields">';
			echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecdv2_sai_gtin">' . esc_html__( 'Barcode ( GTIN, UPC, EAN or ISBN )', 'wp-easycart' ) . '</label>';
			echo '<input type="text" inputmode="numeric" id="ecdv2_sai_gtin" name="ecdv2_sai_gtin" value="' . esc_attr( $gtin ) . '" autocomplete="off"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
			echo '<span class="ecdv2-sai-error" id="ecdv2_sai_gtin_error" role="alert" hidden></span>';
			echo '<span class="ecdv2-field-desc">' . esc_html__( 'The number under the barcode on the packaging. Google uses it to match your product to searches. Variants keep their own barcodes on the Options tab.', 'wp-easycart' ) . '</span></div>';

			echo '<div class="ecdv2-field"><label class="ecdv2-label" for="ecdv2_sai_mpn">' . esc_html__( 'Manufacturer part number', 'wp-easycart' ) . '</label>';
			echo '<input type="text" id="ecdv2_sai_mpn" name="ecdv2_sai_mpn" value="' . esc_attr( $mpn ) . '" autocomplete="off" placeholder="' . esc_attr__( 'Only if there\'s no barcode', 'wp-easycart' ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
			echo '</div>';

			echo '<div class="ecdv2-field"><label class="ecdv2-label" for="ecdv2_sai_condition">' . esc_html__( 'Condition', 'wp-easycart' ) . '</label>';
			echo '<select id="ecdv2_sai_condition" name="ecdv2_sai_condition"' . $sec . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
			/* translators: %s: the store's default condition. */
			echo '<option value=""' . selected( '', isset( $conditions[ $condition ] ) ? $condition : '', false ) . '>' . esc_html( sprintf( __( 'Store default ( %s )', 'wp-easycart' ), $conditions[ $default ] ) ) . '</option>';
			foreach ( $conditions as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $condition, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></div>';

			echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-sai-check"><input type="checkbox" id="ecdv2_sai_no_barcode" name="ecdv2_sai_no_barcode" value="1"' . checked( $no_barcode, true, false ) . $sec . ' /> <span>' . esc_html__( 'This product has no barcode: it\'s handmade, custom or one of a kind. Google stops asking for one.', 'wp-easycart' ) . '</span></label></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
			echo '</div>';

			echo '<div class="ecdv2-sai-side">';
			echo '<span class="ecdv2-sai-head">' . esc_html__( 'Checklist', 'wp-easycart' ) . '</span>';
			echo '<ul class="ecdv2-sai-list">';
			foreach ( self::checklist( $product, $attributes ) as $item ) {
				echo '<li class="' . ( $item[0] ? 'is-ok' : 'is-warn' ) . '">' . esc_html( $item[1] ) . '</li>';
			}
			echo '</ul>';
			$permalink = ! empty( $product->post_id ) ? (string) get_permalink( (int) $product->post_id ) : '';
			echo '<div class="ecdv2-sai-actions">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecdv2_sai_preview_btn">' . esc_html__( 'Preview what Google reads', 'wp-easycart' ) . '</button>';
			if ( '' !== $permalink ) {
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" target="_blank" rel="noopener noreferrer" href="' . esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( $permalink ) ) . '">' . esc_html__( 'Test in Google', 'wp-easycart' ) . ' ↗</a>';
			}
			echo '</div>';
			echo '</div>';

			echo '<pre class="ecdv2-sai-preview" id="ecdv2_sai_preview" hidden></pre>';
			echo '</div>';
			$editor->section_close();
		}

		/**
		 * The card's checklist, from the saved product.
		 *
		 * @param object $product    Product row.
		 * @param array  $attributes Google attributes.
		 * @return array[] ( ok, text ).
		 */
		private static function checklist( $product, $attributes ) {
			global $wpdb;
			$out = array();
			$has = function ( $key ) use ( $product ) {
				return isset( $product->$key ) && '' !== trim( wp_strip_all_tags( (string) $product->$key ) );
			};
			$out[] = array( $has( 'short_description' ) || $has( 'description' ), ( $has( 'short_description' ) || $has( 'description' ) ) ? __( 'Name and description', 'wp-easycart' ) : __( 'Add a description: assistants quote it', 'wp-easycart' ) );

			$images = 0;
			$tokens = ( isset( $product->product_images ) && '' !== (string) $product->product_images ) ? explode( ',', (string) $product->product_images ) : array();
			foreach ( $tokens as $token ) {
				if ( '' !== trim( $token ) && ! preg_match( '/^(video|youtube|vimeo):/', trim( $token ) ) ) {
					++$images;
				}
			}
			if ( ! $tokens ) {
				for ( $i = 1; $i <= 5; $i++ ) {
					$images += ( isset( $product->{ 'image' . $i } ) && '' !== (string) $product->{ 'image' . $i } ) ? 1 : 0;
				}
			}
			/* translators: %d: number of images. */
			$out[] = array( $images > 0, $images > 0 ? sprintf( _n( '%d image', '%d images', $images, 'wp-easycart' ), $images ) : __( 'Add an image: Google won\'t list a product without one', 'wp-easycart' ) );

			$hidden = ! empty( $product->login_for_pricing ) || ! empty( $product->is_donation ) || ( ! empty( $product->catalog_mode ) && get_option( 'ec_option_hide_price_seasonal' ) ) || ( ! empty( $product->inquiry_mode ) && get_option( 'ec_option_hide_price_inquiry' ) ) || ( ! empty( $product->replace_price_label ) && in_array( (int) $product->enable_price_label, array( 2, 4, 6, 7 ), true ) );
			$price  = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( (float) $product->price ) : number_format_i18n( (float) $product->price, 2 );
			/* translators: %s: price. */
			$out[] = array( ! $hidden, $hidden ? __( 'Price hidden: shared as information, not for sale', 'wp-easycart' ) : sprintf( __( 'Price shown: %s', 'wp-easycart' ), $price ) );

			$brand = ! empty( $product->manufacturer_id ) ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ec_manufacturer WHERE manufacturer_id = %d', (int) $product->manufacturer_id ) ) : '';
			/* translators: %s: brand. */
			$out[] = array( '' !== trim( $brand ), '' !== trim( $brand ) ? sprintf( __( 'Brand: %s', 'wp-easycart' ), wp_strip_all_tags( $brand ) ) : __( 'No brand: pick a manufacturer on the General tab', 'wp-easycart' ) );

			$gtin = isset( $attributes['gtin'] ) ? (string) $attributes['gtin'] : '';
			$mpn  = isset( $attributes['mpn'] ) ? (string) $attributes['mpn'] : '';
			if ( '' !== $gtin ) {
				$out[] = array( true, __( 'Barcode', 'wp-easycart' ) );
			} elseif ( '' !== $mpn ) {
				$out[] = array( true, __( 'Part number', 'wp-easycart' ) );
			} elseif ( isset( $attributes['identifier_exists'] ) && 'no' === $attributes['identifier_exists'] ) {
				$out[] = array( true, __( 'No barcode needed ( handmade or custom )', 'wp-easycart' ) );
			} else {
				$out[] = array( false, __( 'No barcode or part number', 'wp-easycart' ) );
			}

			if ( ! empty( $product->use_optionitem_quantity_tracking ) ) {
				$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT google_merchant FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1', (int) $product->product_id ) );
				$count   = count( (array) $rows );
				$missing = 0;
				foreach ( (array) $rows as $row ) {
					$merchant = json_decode( (string) $row->google_merchant, true );
					if ( ! is_array( $merchant ) || ( empty( $merchant['gtin'] ) && empty( $merchant['mpn'] ) ) ) {
						++$missing;
					}
				}
				/* translators: %d: number of variants. */
				$out[] = array( $count > 0, sprintf( _n( '%d variant with its own price and stock', '%d variants with their own price and stock', $count, 'wp-easycart' ), $count ) );
				if ( $count && $missing && '' === $gtin && '' === $mpn ) {
					/* translators: %d: number of variants. */
					$out[] = array( false, sprintf( _n( '%d variant has no barcode', '%d variants have no barcode', $missing, 'wp-easycart' ), $missing ) );
				}
			}

			if ( ! empty( $product->use_customer_reviews ) ) {
				$reviews = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS n, AVG( rating ) AS rating FROM ec_review WHERE product_id = %d AND approved = 1', (int) $product->product_id ) );
				if ( $reviews && (int) $reviews->n > 0 ) {
					/* translators: 1: reviews, 2: average rating. */
					$out[] = array( true, sprintf( _n( '%1$d review, %2$s average', '%1$d reviews, %2$s average', (int) $reviews->n, 'wp-easycart' ), (int) $reviews->n, number_format_i18n( round( (float) $reviews->rating, 1 ), 1 ) ) );
				} else {
					$out[] = array( true, __( 'No reviews yet', 'wp-easycart' ) );
				}
			}
			return $out;
		}

		/**
		 * AJAX ( product editor ): save the card.
		 */
		public static function ajax_save_product() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ), 403 );
			}
			if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), 'wp-easycart-product-details' ) ) {
				wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ), 403 );
			}
			global $wpdb;
			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			if ( ! $product_id || ! $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE product_id = %d', $product_id ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Save the product first.', 'wp-easycart' ) ), 400 );
			}
			$gtin       = isset( $_POST['gtin'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['gtin'] ) ) ) : '';
			$mpn        = isset( $_POST['mpn'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['mpn'] ) ), 0, 70 ) : '';
			$condition  = isset( $_POST['condition'] ) ? sanitize_key( wp_unslash( $_POST['condition'] ) ) : '';
			$no_barcode = isset( $_POST['no_barcode'] ) && ! in_array( sanitize_text_field( wp_unslash( $_POST['no_barcode'] ) ), array( '', '0' ), true );
			if ( '' !== $gtin ) {
				$problem = self::gtin_problem( $gtin );
				if ( '' !== $problem ) {
					wp_send_json_error( array( 'message' => $problem ), 400 );
				}
			}
			if ( ! in_array( $condition, array( '', 'new', 'used', 'refurbished' ), true ) ) {
				$condition = '';
			}
			$attributes              = self::attributes( $product_id );
			$attributes['gtin']      = $no_barcode ? '' : $gtin;
			$attributes['mpn']       = $no_barcode ? '' : $mpn;
			$attributes['condition'] = $condition;
			if ( $no_barcode ) {
				$attributes['identifier_exists'] = 'no';
			} elseif ( '' !== $gtin || '' !== $mpn ) {
				$attributes['identifier_exists'] = 'yes';
			} elseif ( isset( $attributes['identifier_exists'] ) && 'no' === $attributes['identifier_exists'] ) {
				$attributes['identifier_exists'] = '';
			}
			$json = (string) wp_json_encode( $attributes );
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product_google_attributes WHERE product_id = %d', $product_id ) ) ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_product_google_attributes SET attribute_value = %s WHERE product_id = %d', $json, $product_id ) );
			} else {
				$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_product_google_attributes( product_id, attribute_value ) VALUES( %d, %s )', $product_id, $json ) );
			}
			wp_cache_flush();
			do_action( 'wp_easycart_product_search_ai_saved', $product_id, $attributes );
			if ( class_exists( 'wp_easycart_product_writer' ) ) {
				wp_easycart_product_writer::updated( $product_id ); /* 6.0.2: wpeasycart_product_updated, once per request */
			}
			wp_send_json_success( array( 'saved' => true ) );
		}

		/**
		 * AJAX ( product editor ): the markup Google reads for this product.
		 */
		public static function ajax_preview() {
			ecv2_search_ai_guard();
			global $wpdb;
			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
			if ( ! $product_id || ! self::engine() || ! class_exists( 'ec_db' ) || ! class_exists( 'ec_product' ) ) {
				wp_send_json_error( array( 'message' => __( 'This product can\'t be previewed yet.', 'wp-easycart' ) ) );
			}
			$db   = new ec_db();
			$rows = $db->get_product_list( $wpdb->prepare( ' WHERE product.product_id = %d', $product_id ), '', '', '', 'wpeasycart-search-ai-preview-' . $product_id, '', '' );
			if ( ! is_array( $rows ) || ! isset( $rows[0] ) ) {
				wp_send_json_error( array( 'message' => __( 'This product can\'t be previewed yet.', 'wp-easycart' ) ) );
			}
			$product = new ec_product( $rows[0], 0, 1, 0 );
			$data    = wp_easycart_product_schema::build( $product );
			if ( ! $data ) {
				wp_send_json_success( array( 'text' => __( 'Nothing is shared for this product: its price is hidden and it has no reviews yet.', 'wp-easycart' ) ) );
			}
			wp_send_json_success( array( 'text' => wp_easycart_product_schema::json( $data ) ) );
		}

		/**
		 * PRO's Google Merchant field list: drop the fields the Search & AI card owns.
		 *
		 * @param array $fields Field definitions.
		 * @return array
		 */
		public static function google_fields( $fields ) {
			if ( ! is_array( $fields ) ) {
				return $fields;
			}
			$owned = array( 'gm_gtin', 'gm_mpn', 'gm_condition', 'gm_identifier_exists' );
			return array_values(
				array_filter(
					$fields,
					function ( $field ) use ( $owned ) {
						return ! ( is_array( $field ) && isset( $field['name'] ) && in_array( $field['name'], $owned, true ) );
					}
				)
			);
		}
	}

	wp_easycart_admin_search_ai::init();

endif;
