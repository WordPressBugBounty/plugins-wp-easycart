<?php
/**
 * Settings › Search & AI ( V2 declaration ).
 *
 * 6.0.2: what search engines and AI assistants can read about the store. The product markup
 * ( inc/classes/core/class-wp-easycart-product-schema.php ) and the store's return and shipping policy
 * ( class-wp-easycart-store-schema.php ) read these options; the status card, shipping summary and crawler check are drawn
 * by admin/inc/wp_easycart_admin_search_ai.php through render callables. Everything here is FREE except the Google
 * product feed sections ( google-feed, google-feed-options ): WP EasyCart PRO 6.0.2 builds and serves the feed and gives
 * google-feed its body and its New address action ( wp-easycart-pro/admin/template/settings/search-ai.php ); without it
 * they show locked, with a preview ( wp_easycart_admin_search_ai::render_feed_locked() ).
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecst_search_ai_sanitize_days' ) ) {
	/**
	 * A day range: "2-5", "3", or empty.
	 *
	 * @param string $raw Typed value.
	 * @return string
	 */
	function ecst_search_ai_sanitize_days( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || ! preg_match( '/^\s*(\d{1,3})\s*(?:(?:-|–|to)\s*(\d{1,3}))?\s*$/u', $raw, $match ) ) {
			return '';
		}
		$min = (int) $match[1];
		$max = ( isset( $match[2] ) && '' !== $match[2] ) ? (int) $match[2] : $min;
		if ( $max < $min ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}
		return ( $min === $max ) ? (string) $min : $min . '-' . $max;
	}
}

if ( ! function_exists( 'ecst_search_ai_countries' ) ) {
	/**
	 * Every country, for the "countries you sell to" picker.
	 *
	 * @return array ISO2 => name.
	 */
	function ecst_search_ai_countries() {
		$out = array();
		if ( ! isset( $GLOBALS['wpdb'] ) ) {
			return $out;
		}
		foreach ( (array) $GLOBALS['wpdb']->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY name_cnt' ) as $row ) {
			$code = strtoupper( trim( (string) $row->iso2_cnt ) );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
				$out[ $code ] = (string) $row->name_cnt;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'ecst_search_ai_pages' ) ) {
	/**
	 * Published pages, for the returns page choice.
	 *
	 * @return array page id => title.
	 */
	function ecst_search_ai_pages() {
		$out = array( '0' => __( 'None', 'wp-easycart' ) );
		if ( ! function_exists( 'get_pages' ) ) {
			return $out;
		}
		foreach ( (array) get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
			$out[ (string) $page->ID ] = '' !== $page->post_title ? $page->post_title : '#' . $page->ID;
		}
		return $out;
	}
}

if ( ! function_exists( 'ecst_search_ai_hours' ) ) {
	/**
	 * The hours of the day, in the site's time format, for the feed's nightly build.
	 *
	 * @return array 0-23 => label.
	 */
	function ecst_search_ai_hours() {
		$out    = array();
		$format = function_exists( 'get_option' ) ? (string) get_option( 'time_format', 'g:i a' ) : 'g:i a';
		for ( $hour = 0; $hour < 24; $hour++ ) {
			$out[ (string) $hour ] = gmdate( '' !== $format ? $format : 'g:i a', $hour * 3600 );
		}
		return $out;
	}
}

if ( ! function_exists( 'ecst_search_ai_saved' ) ) {
	/**
	 * A setting changed: forget the cached crawler check ( it names the store page ).
	 */
	function ecst_search_ai_saved() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'wpec_search_ai_crawlers' );
		}
	}
}

$ecst_search_ai_legacy = array(
	'page'    => 'search-ai',
	'section' => 'Search & AI',
	'label'   => 'New in 6.0.2',
);

/* One delivery time per shipping zone, folded behind "Show advanced". */
$ecst_search_ai_zone_fields = array();
if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_results' ) ) {
	$ecst_search_ai_zones = (array) $GLOBALS['wpdb']->get_results( 'SELECT DISTINCT z.zone_id, z.zone_name FROM ec_zone z INNER JOIN ec_shippingrate r ON r.zone_id = z.zone_id ORDER BY z.zone_name' );
	foreach ( $ecst_search_ai_zones as $ecst_search_ai_zone ) {
		$ecst_search_ai_zone_fields[ 'ec_option_shipping_transit_days_z' . (int) $ecst_search_ai_zone->zone_id ] = array(
			'type'        => 'text',
			/* translators: %s: shipping zone name. */
			'label'       => sprintf( __( 'Delivery time to %s', 'wp-easycart' ), (string) $ecst_search_ai_zone->zone_name ),
			'desc'        => __( 'Days in transit for this zone, if it differs from the delivery time above.', 'wp-easycart' ),
			'default'     => '',
			'placeholder' => __( 'Same as above', 'wp-easycart' ),
			'unit'        => __( 'days', 'wp-easycart' ),
			'advanced'    => true,
			'sanitize'    => 'ecst_search_ai_sanitize_days',
			'legacy'      => $ecst_search_ai_legacy,
		);
	}
}

return array(
	'slug'        => 'search-ai',
	'title'       => __( 'Search & AI', 'wp-easycart' ),
	'description' => __( 'How your products appear in Google, Bing and AI assistants like ChatGPT, Gemini, Perplexity and Copilot.', 'wp-easycart' ),
	'group'       => 'marketing',
	'order'       => 10,
	'icon'        => 'search',
	'docs'        => array( 'settings', 'search-ai', 'search-ai' ),
	'legacy'      => array(),
	'upsell'      => 'google_feed',
	'enqueue'     => array( 'wp_easycart_admin_search_ai', 'enqueue' ),
	'sections'    => array(

		'status'              => array(
			'title'  => __( 'Status', 'wp-easycart' ),
			'hint'   => __( 'What search engines and AI assistants can read about your store right now.', 'wp-easycart' ),
			'icon'   => 'activity',
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_search_ai', 'render_status' ),
		),

		'product'             => array(
			'title'  => __( 'Product data', 'wp-easycart' ),
			'hint'   => __( 'What each product page tells search engines and assistants. Barcodes are set per product, on its SEO tab.', 'wp-easycart' ),
			'icon'   => 'package',
			'fields' => array(
				'ec_option_product_schema'           => array(
					'type'     => 'toggle',
					'label'    => __( 'Share product details with search engines and AI assistants', 'wp-easycart' ),
					'desc'     => __( 'Adds each product\'s price, stock, variants, barcode and reviews to its page in the format Google, Bing and AI assistants read. Turn it off only if another plugin already does this.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'schema', 'structured data', 'json-ld', 'rich results', 'google shopping', 'merchant listings', 'chatgpt', 'ai', 'seo' ),
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_product_schema_condition' => array(
					'type'     => 'pills',
					'label'    => __( 'Condition of your products', 'wp-easycart' ),
					'desc'     => __( 'Used for every product unless its own Search & AI card says otherwise.', 'wp-easycart' ),
					'default'  => 'new',
					'options'  => array(
						'new'         => __( 'New', 'wp-easycart' ),
						'used'        => __( 'Used', 'wp-easycart' ),
						'refurbished' => __( 'Refurbished', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_product_schema',
					'show_when' => '1',
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_product_schema_reviews'   => array(
					'type'      => 'pills',
					'label'     => __( 'Reviews to share', 'wp-easycart' ),
					'desc'      => __( '"Rating and reviews" shares the average and the five newest reviews. Reviews are only shared with the reviewer\'s name when your product pages show names ( Settings › Products › Reviews ); otherwise just the rating is.', 'wp-easycart' ),
					'default'   => 'reviews',
					'options'   => array(
						'reviews' => __( 'Rating and reviews', 'wp-easycart' ),
						'rating'  => __( 'Rating only', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_product_schema',
					'show_when' => '1',
					'legacy'    => $ecst_search_ai_legacy,
				),
			),
		),

		'returns'             => array(
			'title'  => __( 'Returns', 'wp-easycart' ),
			'hint'   => __( 'Google can show your return window next to your products, and assistants answer "can I return it?" from this.', 'wp-easycart' ),
			'icon'   => 'swap',
			'fields' => array(
				'ec_option_returns_policy'     => array(
					'type'     => 'pills',
					'label'    => __( 'Do you accept returns?', 'wp-easycart' ),
					'desc'     => __( 'Shared once for your whole store. "Not shared" says nothing about returns.', 'wp-easycart' ),
					'default'  => '',
					'options'  => array(
						''          => __( 'Not shared', 'wp-easycart' ),
						'finite'    => __( 'Within a set time', 'wp-easycart' ),
						'unlimited' => __( 'Any time', 'wp-easycart' ),
						'none'      => __( 'No returns', 'wp-easycart' ),
					),
					'keywords' => array( 'return policy', 'refunds', 'merchant return policy' ),
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_returns_days'       => array(
					'type'      => 'number',
					'label'     => __( 'Return window', 'wp-easycart' ),
					'desc'      => __( 'Days after delivery.', 'wp-easycart' ),
					'default'   => 30,
					'min'       => 1,
					'max'       => 365,
					'unit'      => __( 'days', 'wp-easycart' ),
					'parent'    => 'ec_option_returns_policy',
					'show_when' => 'finite',
					'legacy'    => $ecst_search_ai_legacy,
				),
				'ec_option_returns_methods'    => array(
					'type'      => 'multiselect',
					'label'     => __( 'How customers return items', 'wp-easycart' ),
					'default'   => 'mail',
					'options'   => array(
						'mail'  => __( 'By mail', 'wp-easycart' ),
						'store' => __( 'In store', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_returns_policy',
					'show_when' => array( 'finite', 'unlimited' ),
					'legacy'    => $ecst_search_ai_legacy,
				),
				'ec_option_returns_fees'       => array(
					'type'      => 'pills',
					'label'     => __( 'Return shipping', 'wp-easycart' ),
					'default'   => 'free',
					'options'   => array(
						'free'     => __( 'Free', 'wp-easycart' ),
						'customer' => __( 'Customer pays', 'wp-easycart' ),
						'flat'     => __( 'Flat fee', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_returns_policy',
					'show_when' => array( 'finite', 'unlimited' ),
					'legacy'    => $ecst_search_ai_legacy,
				),
				'ec_option_returns_fee_amount' => array(
					'type'      => 'number',
					'label'     => __( 'Return fee', 'wp-easycart' ),
					'desc'      => __( 'In your store\'s currency.', 'wp-easycart' ),
					'default'   => 0,
					'min'       => 0,
					'step'      => 0.01,
					'parent'    => 'ec_option_returns_fees',
					'show_when' => 'flat',
					'legacy'    => $ecst_search_ai_legacy,
				),
				'ec_option_returns_refund'     => array(
					'type'      => 'multiselect',
					'label'     => __( 'Refund', 'wp-easycart' ),
					'default'   => 'full',
					'options'   => array(
						'full'     => __( 'Full refund', 'wp-easycart' ),
						'exchange' => __( 'Exchange', 'wp-easycart' ),
						'credit'   => __( 'Store credit', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_returns_policy',
					'show_when' => array( 'finite', 'unlimited' ),
					'legacy'    => $ecst_search_ai_legacy,
				),
				'ec_option_returns_countries'  => array(
					'type'     => 'multiselect',
					'label'    => __( 'Countries you sell to', 'wp-easycart' ),
					'desc'     => __( 'Used for your return policy, and for shipping rates that apply everywhere. Leave it empty to use the countries in your shipping zones.', 'wp-easycart' ),
					'default'  => '',
					'options'  => 'ecst_search_ai_countries',
					'display'  => 'picker',
					'on_save'  => 'ecst_search_ai_saved',
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_returns_page'       => array(
					'type'     => 'select',
					'label'    => __( 'Returns page', 'wp-easycart' ),
					'desc'     => __( 'The page with your full return policy, linked for shoppers and assistants who want the details.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => 'ecst_search_ai_pages',
					'legacy'   => $ecst_search_ai_legacy,
				),
			),
		),

		'shipping'            => array(
			'title'  => __( 'Shipping', 'wp-easycart' ),
			'hint'   => __( 'Built from Settings › Shipping rates: change a rate there and this follows. Add how long orders take.', 'wp-easycart' ),
			'icon'   => 'truck',
			'render' => array( 'wp_easycart_admin_search_ai', 'render_shipping' ),
			'fields' => array_merge(
				array(
					'ec_option_shipping_handling_days' => array(
						'type'        => 'text',
						'label'       => __( 'Handling time', 'wp-easycart' ),
						'desc'        => __( 'Business days from order to hand-over to the carrier, like 0-1.', 'wp-easycart' ),
						'default'     => '',
						'placeholder' => '0-1',
						'unit'        => __( 'days', 'wp-easycart' ),
						'sanitize'    => 'ecst_search_ai_sanitize_days',
						'keywords'    => array( 'shipping policy', 'delivery time', 'handling time' ),
						'legacy'      => $ecst_search_ai_legacy,
					),
					'ec_option_shipping_cutoff'        => array(
						'type'    => 'select',
						'label'   => __( 'Order cut-off time', 'wp-easycart' ),
						'desc'    => __( 'Orders after this time start the next business day. Uses your WordPress time zone.', 'wp-easycart' ),
						'default' => '',
						'options' => array(
							''      => __( 'No cut-off', 'wp-easycart' ),
							'10:00' => '10:00',
							'11:00' => '11:00',
							'12:00' => '12:00',
							'13:00' => '13:00',
							'14:00' => '14:00',
							'15:00' => '15:00',
							'16:00' => '16:00',
							'17:00' => '17:00',
							'18:00' => '18:00',
						),
						'legacy'  => $ecst_search_ai_legacy,
					),
					'ec_option_shipping_transit_days'  => array(
						'type'        => 'text',
						'label'       => __( 'Delivery time', 'wp-easycart' ),
						'desc'        => __( 'Days in transit once shipped, like 2-5.', 'wp-easycart' ),
						'default'     => '',
						'placeholder' => '2-5',
						'unit'        => __( 'days', 'wp-easycart' ),
						'sanitize'    => 'ecst_search_ai_sanitize_days',
						'legacy'      => $ecst_search_ai_legacy,
					),
				),
				$ecst_search_ai_zone_fields
			),
		),

		/* 6.0.2: the self-updating Google product feed ( WP EasyCart PRO 6.0.2 builds, serves and renders it ). */
		'google-feed'         => array(
			'title'           => __( 'Google product feed', 'wp-easycart' ),
			'hint'            => __( 'Your catalog for Google Shopping, Bing and Pinterest, kept up to date by the store', 'wp-easycart' ),
			'icon'            => 'share',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'google merchant center', 'product feed', 'google shopping', 'free listings', 'shopping ads', 'microsoft merchant center', 'bing shopping', 'pinterest catalog', 'xml feed', 'tsv feed', 'data feed' ),
			'fields'          => array(),
			'render'          => array( 'wp_easycart_admin_search_ai', 'render_feed_locked' ),
			'actions'         => array(
				'new-address' => array(
					'id'             => 'new-address',
					'label'          => __( 'New feed address', 'wp-easycart' ),
					'desc'           => __( 'If the address was shared by mistake. The old one stops working at once.', 'wp-easycart' ),
					'button'         => __( 'New address', 'wp-easycart' ),
					'confirm'        => __( 'Make a new feed address?', 'wp-easycart' ),
					'confirm_title'  => __( 'The current address stops working at once. Paste the new one into Google, Microsoft and Pinterest; a connected Merchant Center is moved for you.', 'wp-easycart' ),
					'confirm_button' => __( 'Make a new address', 'wp-easycart' ),
					'danger'         => true,
					'callback'       => null,
				),
			),
		),

		'google-feed-options' => array(
			'title'           => __( 'Feed options', 'wp-easycart' ),
			'hint'            => __( 'When the feed rebuilds and what goes in it', 'wp-easycart' ),
			'icon'            => 'sliders',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(
				'ec_option_google_feed_hour'           => array(
					'type'     => 'select',
					'label'    => __( 'Build the feed every night at', 'wp-easycart' ),
					'desc'     => __( 'Pick an hour before Google fetches it. Product and stock changes are also picked up within the hour.', 'wp-easycart' ),
					'default'  => '2',
					'options'  => 'ecst_search_ai_hours',
					'pro'      => true,
					'keywords' => array( 'feed schedule', 'rebuild', 'cron' ),
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_google_feed_out_of_stock'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Include out-of-stock products', 'wp-easycart' ),
					'desc'    => __( 'Google shows them as out of stock instead of dropping them, which keeps their history.', 'wp-easycart' ),
					'default' => 1,
					'pro'     => true,
					'legacy'  => $ecst_search_ai_legacy,
				),
				'ec_option_google_feed_brand_fallback' => array(
					'type'    => 'toggle',
					'label'   => __( 'Use your store name as the brand for handmade products', 'wp-easycart' ),
					'desc'    => __( 'For products marked "no barcode" that have no manufacturer, as Google asks for custom goods.', 'wp-easycart' ),
					'default' => 1,
					'pro'     => true,
					'legacy'  => $ecst_search_ai_legacy,
				),
				'ec_option_google_feed_category'       => array(
					'type'        => 'text',
					'label'       => __( 'Default Google product category', 'wp-easycart' ),
					'desc'        => __( 'Optional. Used for products that don\'t set their own on their Google Merchant card; Google can also work it out.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => __( 'Apparel & Accessories > Clothing', 'wp-easycart' ),
					'pro'         => true,
					'keywords'    => array( 'google_product_category', 'taxonomy' ),
					'legacy'      => $ecst_search_ai_legacy,
				),
			),
		),

		/* 6.0.2: the Google attribute spreadsheet moved here from Settings › Integrations ( its Google Merchant section, now a
		 * signpost ), so everything for Google Shopping is on one page. WP EasyCart PRO 6.0.2 draws it here; an older PRO keeps
		 * drawing it on Integrations and this section asks for the update. */
		'google-attributes'   => array(
			'title'           => __( 'Google attributes in bulk', 'wp-easycart' ),
			'hint'            => __( 'Edit the Google attributes of many products at once in a spreadsheet', 'wp-easycart' ),
			'icon'            => 'download',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'google merchant', 'google attributes', 'google_product_category', 'gender', 'age group', 'sizes', 'csv', 'spreadsheet', 'bulk edit', 'import', 'export' ),
			'fields'          => array(),
			'render'          => array( 'wp_easycart_admin_search_ai', 'render_attributes_locked' ),
		),

		'crawlers'            => array(
			'title'   => __( 'Who can read your store', 'wp-easycart' ),
			'hint'    => __( 'From your robots.txt and WordPress\'s search engine setting.', 'wp-easycart' ),
			'icon'    => 'eye',
			'fields'  => array(),
			'render'  => array( 'wp_easycart_admin_search_ai', 'render_crawlers' ),
			'actions' => array(
				'recheck' => array(
					'id'       => 'recheck',
					'label'    => __( 'Check again', 'wp-easycart' ),
					'desc'     => __( 'After changing robots.txt or a plugin that writes it.', 'wp-easycart' ),
					'button'   => __( 'Check again', 'wp-easycart' ),
					'callback' => array( 'wp_easycart_admin_search_ai', 'action_recheck' ),
				),
			),
		),

		'seo'                 => array(
			'title'  => __( 'SEO plugin', 'wp-easycart' ),
			'hint'   => __( 'Who prints descriptions and social previews when you use an SEO plugin.', 'wp-easycart' ),
			'icon'   => 'globe',
			'render' => array( 'wp_easycart_admin_search_ai', 'render_seo' ),
			'fields' => array(
				'ec_option_seo_plugin_handoff'  => array(
					'type'     => 'toggle',
					'label'    => __( 'Let the SEO plugin handle descriptions and social previews', 'wp-easycart' ),
					'desc'     => __( 'EasyCart stops printing its own and gives the plugin each product\'s description and main image, so nothing is printed twice.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'yoast', 'rank math', 'aioseo', 'seopress', 'open graph', 'meta description' ),
					'legacy'   => $ecst_search_ai_legacy,
				),
				'ec_option_seo_plugin_policies' => array(
					'type'    => 'toggle',
					'label'   => __( 'Add your return and shipping policy to the SEO plugin\'s store details', 'wp-easycart' ),
					'desc'    => __( 'Yoast SEO and Rank Math: keeps one description of your business for Google instead of two.', 'wp-easycart' ),
					'default' => 1,
					'legacy'  => $ecst_search_ai_legacy,
				),
			),
		),
	),
);
