<?php
/**
 * Settings › Checkout ( V2 declaration ).
 *
 * Replaces the classic Settings › Checkout page ( cart settings, checkout form,
 * payment page, order statuses, stock control, schedule ) plus the abandoned
 * cart delay that lived under Additional Settings. PRO rows ( secondary email,
 * one-page checkout, abandoned cart, order text notifications ) are declared
 * here and render locked until PRO is licensed; PRO attaches its behavior in
 * wp-easycart-pro/admin/template/settings/checkout.php.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_checkout_link_target_label' ) ) {
	/**
	 * Title and type hint for a page or post a legal link points at.
	 *
	 * @since 6.0.0
	 * @return array( label, hint )
	 */
	function ecv2_checkout_link_target_label( $post ) {
		$title = function_exists( 'get_the_title' ) ? trim( (string) get_the_title( $post ) ) : '';
		if ( '' === $title ) {
			/* translators: %d: post id */
			$title = sprintf( __( '(no title) #%d', 'wp-easycart' ), (int) $post->ID );
		}
		$type = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post->post_type ) : null;
		$hint = ( $type && isset( $type->labels->singular_name ) ) ? (string) $type->labels->singular_name : (string) $post->post_type;
		if ( 'publish' !== $post->post_status ) {
			/* translators: %s: post status such as draft */
			$hint = sprintf( __( 'not published (%s)', 'wp-easycart' ), $post->post_status );
		}
		return array( $title, $hint );
	}
}

if ( ! function_exists( 'ecv2_checkout_search_link_targets' ) ) {
	/**
	 * 'search_callback' for the terms and privacy page pickers: published pages and
	 * posts whose title or content matches, twenty at a time. The row value is the
	 * permalink, because the option stores a URL ( the storefront links straight to it ),
	 * and the picker writes that permalink into the URL field.
	 *
	 * @since 6.0.0
	 * @return array rows of value ( permalink ) / label / hint
	 */
	function ecv2_checkout_search_link_targets( $term, $field, $limit ) {
		if ( ! class_exists( 'WP_Query' ) || ! function_exists( 'get_permalink' ) ) {
			return array();
		}
		$term = trim( (string) $term );
		$args = array(
			'post_type'              => array( 'page', 'post' ),
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
			$post = get_post( $id );
			$url  = $post ? (string) get_permalink( $post ) : '';
			if ( ! $post || '' === $url ) {
				continue;
			}
			$label  = ecv2_checkout_link_target_label( $post );
			$rows[] = array( 'value' => $url, 'label' => $label[0], 'hint' => $label[1] );
		}
		return $rows;
	}
}

if ( ! function_exists( 'ecv2_checkout_lookup_link_targets' ) ) {
	/**
	 * 'validate_callback' for the terms and privacy page pickers: the page or post a
	 * stored URL resolves to on this site ( url_to_postid ), so the chip beside the URL
	 * can name it. An external or unresolvable URL returns nothing and shows no chip.
	 *
	 * @since 6.0.0
	 * @return array rows of value ( the URL as given ) / label / hint
	 */
	function ecv2_checkout_lookup_link_targets( $values, $field ) {
		if ( ! function_exists( 'url_to_postid' ) || ! function_exists( 'get_post' ) ) {
			return array();
		}
		$rows = array();
		foreach ( (array) $values as $value ) {
			$url = trim( (string) $value );
			if ( '' === $url ) {
				continue;
			}
			$id = (int) url_to_postid( $url );
			if ( $id <= 0 ) {
				continue;
			}
			$post = get_post( $id );
			if ( ! $post || 'trash' === $post->post_status ) {
				continue;
			}
			$label  = ecv2_checkout_link_target_label( $post );
			$rows[] = array( 'value' => $url, 'label' => $label[0], 'hint' => $label[1] );
		}
		return $rows;
	}
}

if ( ! function_exists( 'wp_easycart_settings_checkout_country_options' ) ) {
	/**
	 * iso2 => name for the country rows. An 'options' callable, so the table is read
	 * only when the Checkout page renders or one of those rows saves, never when the
	 * declarations are loaded for search or another page. Read straight from the table
	 * so the declaration does not depend on wp_easycart_admin()->init_shipping_data();
	 * empty outside WordPress ( migration map generator ).
	 *
	 * @since 6.0.0
	 */
	function wp_easycart_settings_checkout_country_options() {
		static $countries = null;
		if ( null !== $countries ) {
			return $countries;
		}
		$countries = array();
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_results' ) ) {
			$rows = $GLOBALS['wpdb']->get_results( 'SELECT iso2_cnt, name_cnt FROM ec_country ORDER BY sort_order ASC, name_cnt ASC' );
			foreach ( (array) $rows as $row ) {
				$countries[ (string) $row->iso2_cnt ] = (string) $row->name_cnt;
			}
		}
		return $countries;
	}
}

if ( ! function_exists( 'wp_easycart_settings_checkout_next_order_id' ) ) {
	/**
	 * Live AUTO_INCREMENT of ec_order: the 'current' value of the next-order-number row
	 * ( it is not an option ). Read when that row renders or saves. 0 when unavailable.
	 *
	 * @since 6.0.0
	 */
	function wp_easycart_settings_checkout_next_order_id() {
		static $next = null;
		if ( null !== $next ) {
			return $next;
		}
		$next = 0;
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_var' ) ) {
			$next = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', $GLOBALS['wpdb']->dbname, 'ec_order' ) );
		}
		return $next;
	}
}

$wp_easycart_checkout_minutes = array();
foreach ( array( 5, 10, 15, 20, 30, 60 ) as $wp_easycart_checkout_minute ) {
	/* translators: %d: number of minutes. */
	$wp_easycart_checkout_minutes[ (string) $wp_easycart_checkout_minute ] = sprintf( __( '%d minutes', 'wp-easycart' ), $wp_easycart_checkout_minute );
}
$wp_easycart_checkout_prep_times = array();
for ( $wp_easycart_checkout_minute = 5; $wp_easycart_checkout_minute <= 90; $wp_easycart_checkout_minute += 5 ) {
	/* translators: %d: number of minutes. */
	$wp_easycart_checkout_prep_times[ (string) $wp_easycart_checkout_minute ] = sprintf( __( '%d minutes', 'wp-easycart' ), $wp_easycart_checkout_minute );
}

return array(
	'slug'        => 'checkout',
	'title'       => __( 'Checkout', 'wp-easycart' ),
	'description' => __( 'What shoppers see in the cart, what the checkout form asks for, order numbers and statuses, stock alerts and pickup scheduling.', 'wp-easycart' ),
	'group'       => 'store-setup',
	'icon'        => 'cart',
	'docs'        => array( 'settings', 'checkout', 'settings' ),
	'legacy'      => array( 'checkout' ),
	'upsell'      => 'default',
	'sections'    => array(

		'cart' => array(
			'title'  => __( 'Cart page', 'wp-easycart' ),
			'hint'   => __( 'What shoppers can do before they start checkout', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_estimate_shipping' => array(
					'type'     => 'toggle',
					'label'    => __( 'Shipping estimator', 'wp-easycart' ),
					'desc'     => __( 'Adds a box to the cart page where shoppers can estimate shipping before they check out.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'estimate', 'shipping calculator', 'postage', 'delivery' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Estimate Shipping' ),
				),
				'ec_option_estimate_shipping_zip' => array(
					'type'     => 'toggle',
					'label'    => __( 'Estimator asks for a postal code', 'wp-easycart' ),
					'desc'     => __( 'The estimate uses the shopper’s ZIP or postal code, which most carriers need for an accurate rate.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_use_estimate_shipping',
					'keywords' => array( 'zip', 'postcode', 'estimate' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Estimate Shipping: Enable Postal Code' ),
				),
				'ec_option_estimate_shipping_country' => array(
					'type'     => 'toggle',
					'label'    => __( 'Estimator asks for a country', 'wp-easycart' ),
					'desc'     => __( 'Shoppers pick their country so international rates can be estimated.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_use_estimate_shipping',
					'keywords' => array( 'country', 'international', 'estimate' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Estimate Shipping: Enable Country' ),
				),
				'ec_option_show_coupons' => array(
					'type'     => 'toggle',
					'label'    => __( 'Coupon code box', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can enter a coupon code on the cart page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'promo code', 'discount', 'voucher' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Coupons' ),
				),
				'ec_option_show_coupon_message' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the coupon’s message instead of its code', 'wp-easycart' ),
					'desc'     => __( 'After checkout, receipts and order details display the message text saved with the coupon rather than the code the shopper typed.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'promo message', 'receipt', 'coupon display' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Coupon Display: Message' ),
				),
				'ec_option_show_giftcards' => array(
					'type'     => 'toggle',
					'label'    => __( 'Gift card box', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can redeem a gift card on the cart page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'gift certificate', 'redeem', 'voucher' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Gift Cards' ),
				),
				'ec_option_gift_card_shipping_allowed' => array(
					'type'     => 'toggle',
					'label'    => __( 'Gift cards cover the grand total', 'wp-easycart' ),
					'desc'     => __( 'The gift card balance can pay for shipping and tax as well as the products. Off limits it to the product subtotal.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_giftcards',
					'keywords' => array( 'gift card shipping', 'gift card tax', 'grand total' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Gift Cards: Apply to Grand Total' ),
				),
				'ec_option_enable_tips' => array(
					'type'     => 'toggle',
					'label'    => __( 'Tips and gratuity', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can add a tip during checkout. The wording is editable in the language editor.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'gratuity', 'tipping', 'restaurant', 'donation' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Enable Tips / Gratuity' ),
				),
				'ec_option_default_tips' => array(
					'type'        => 'text',
					'label'       => __( 'Suggested tip percentages', 'wp-easycart' ),
					'desc'        => __( 'Comma-separated percentages offered as buttons, e.g. 15,20,25. Shoppers can also enter their own amount.', 'wp-easycart' ),
					'default'     => '15,18,20,25',
					'placeholder' => '15,18,20,25',
					'parent'      => 'ec_option_enable_tips',
					'sanitize'    => function ( $raw, $field ) {
						$clean = preg_replace( '/[^0-9\.\,]/', '', (string) $raw );
						$final = array();
						foreach ( explode( ',', $clean ) as $tip_rate ) {
							if ( (float) $tip_rate > 0 ) {
								$final[] = $tip_rate;
							}
						}
						return implode( ',', $final );
					},
					'keywords'    => array( 'tip amounts', 'gratuity', 'percent' ),
					'legacy'      => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Default Tip Values' ),
				),
				'ec_option_minimum_order_total' => array(
					'type'        => 'number',
					'label'       => __( 'Minimum order subtotal', 'wp-easycart' ),
					'desc'        => __( 'Shoppers cannot check out until the cart subtotal reaches this amount. Leave at 0 for no minimum.', 'wp-easycart' ),
					'default'     => '0.00',
					'placeholder' => '0.00',
					'min'         => 0,
					'step'        => 0.01,
					'sanitize'    => function ( $raw, $field ) {
						$clean = preg_replace( '/[^0-9\.\,]/', '', trim( (string) $raw ) );
						if ( '' === $clean ) {
							return '0.00';
						}
						$clean = str_replace( ',', '', $clean );
						if ( ! is_numeric( $clean ) ) {
							return new WP_Error( 'number', __( 'Enter an amount such as 25.00.', 'wp-easycart' ) );
						}
						return number_format( (float) $clean, 2, '.', '' );
					},
					'keywords'    => array( 'minimum purchase', 'min order', 'order minimum' ),
					'legacy'      => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Minimum Order Total' ),
				),
				'ec_option_return_to_store_page_url' => array(
					'type'        => 'url',
					'label'       => __( 'Continue shopping link', 'wp-easycart' ),
					'desc'        => __( 'Where the Continue Shopping buttons send shoppers. Leave blank to use your store page.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'https://www.example.com/shop/',
					'advanced'    => true,
					'keywords'    => array( 'back to store', 'return url', 'keep shopping' ),
					'legacy'      => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Custom Continue Shopping URL' ),
				),
				'ec_option_cache_prevent' => array(
					'type'     => 'toggle',
					'label'    => __( 'Load the cart dynamically', 'wp-easycart' ),
					'desc'     => __( 'Cart, checkout and account pages fetch their contents with JavaScript so page caching plugins never show another shopper’s cart or stale totals.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'caching', 'cache plugin', 'ajax cart', 'stale' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Prevent Cart Caching Problems' ),
				),
				'ec_option_load_ssl' => array(
					'type'     => 'toggle',
					'label'    => __( 'Force HTTPS on every page', 'wp-easycart' ),
					'desc'     => __( 'Redirects the whole site to https://. Only turn this on once your SSL certificate is installed and working, or the site becomes unreachable.', 'wp-easycart' ),
					'default'  => ( function_exists( 'is_ssl' ) && is_ssl() ) ? 1 : 0,
					'advanced' => true,
					'keywords' => array( 'ssl', 'secure', 'https redirect', 'certificate' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Force Website HTTPS Secured' ),
				),
			),
		),

		'checkout-flow' => array(
			'title'  => __( 'Checkout flow', 'wp-easycart' ),
			'hint'   => __( 'The steps a shopper goes through to place an order', 'wp-easycart' ),
			'fields' => array(
				'ec_option_allow_guest' => array(
					'type'     => 'toggle',
					'label'    => __( 'Guest checkout', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can order without creating an account. Downloads, subscriptions and gift cards still require one.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'no account', 'guest', 'register', 'anonymous' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Guest Checkout' ),
				),
				'ec_option_skip_shipping_page' => array(
					'type'     => 'toggle',
					'label'    => __( 'Skip the shipping method step', 'wp-easycart' ),
					'desc'     => __( 'Shoppers go straight from their details to payment and the first available shipping method is used.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'bypass shipping', 'shipping selection', 'fewer steps' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Bypass Shipping Selection Page' ),
				),
				'ec_option_onepage_checkout' => array(
					'type'     => 'toggle',
					'label'    => __( 'One-page checkout (beta)', 'wp-easycart' ),
					'desc'     => __( 'Shows the whole checkout on a single page without reloads. Beta: may not work with every payment or shipping feature.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'pro'      => true,
					'keywords' => array( 'single page', 'one page', 'no reload', 'beta' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'One Page Checkout (Beta)', 'note' => 'hidden in the classic admin ( PRO hook disabled ), saveable by key' ),
				),
				'ec_option_onepage_checkout_tabbed' => array(
					'type'      => 'toggle',
					'label'     => __( 'Show one-page checkout as steps', 'wp-easycart' ),
					'desc'      => __( 'When shipping is required the one-page checkout is split into steps with a breadcrumb trail.', 'wp-easycart' ),
					'default'   => 0,
					'advanced'  => true,
					'pro'       => true,
					'parent'    => 'ec_option_onepage_checkout',
					'keywords'  => array( 'breadcrumbs', 'tabbed', 'steps' ),
					'legacy'    => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'One Page Checkout Format: Enable Breadcrumbs Display', 'note' => 'hidden in the classic admin ( PRO hook disabled ), saveable by key' ),
				),
				'ec_option_onepage_checkout_cart_first' => array(
					'type'      => 'toggle',
					'label'     => __( 'Show the cart before the one-page checkout', 'wp-easycart' ),
					'desc'      => __( 'Shoppers land on the cart first. Off sends them straight into checkout.', 'wp-easycart' ),
					'default'   => 0,
					'advanced'  => true,
					'pro'       => true,
					'parent'    => 'ec_option_onepage_checkout',
					'keywords'  => array( 'cart first', 'entry', 'landing' ),
					'legacy'    => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'One Page Checkout Entry: Cart First', 'note' => 'hidden in the classic admin ( PRO hook disabled ), saveable by key' ),
				),
				'ec_option_onepage_checkout_quantity_adjust_on' => array(
					'type'      => 'toggle',
					'label'     => __( 'Quantities can be changed on the one-page checkout', 'wp-easycart' ),
					'desc'      => __( 'Shoppers can adjust item quantities on the checkout page itself instead of going back to the cart.', 'wp-easycart' ),
					'default'   => 0,
					'advanced'  => true,
					'pro'       => true,
					'parent'    => 'ec_option_onepage_checkout',
					'keywords'  => array( 'quantity', 'adjust', 'edit cart' ),
					'legacy'    => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'One Page Checkout: Quantity Adjust on Checkout', 'note' => 'hidden in the classic admin ( PRO hook disabled ), saveable by key' ),
				),
			),
		),

		'checkout-form' => array(
			'title'  => __( 'Checkout form', 'wp-easycart' ),
			'hint'   => __( 'Which details shoppers are asked for', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_contact_name' => array(
					'type'     => 'toggle',
					'label'    => __( 'Name fields when creating an account', 'wp-easycart' ),
					'desc'     => __( 'Shoppers who create an account during checkout enter a first and last name for the account, separate from the billing name.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'first name', 'last name', 'contact', 'register' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Contact Name for Account Creation' ),
				),
				'ec_option_enable_extra_email' => array(
					'type'     => 'toggle',
					'label'    => __( 'Secondary email address', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can add an optional second email that also receives the order emails.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'additional email', 'cc', 'second email', 'copy' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Additional Email' ),
				),
				'ec_option_collect_user_phone' => array(
					'type'     => 'toggle',
					'label'    => __( 'Phone number', 'wp-easycart' ),
					'desc'     => __( 'Adds a phone field to the address form. Carriers and pickup orders often need it.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'telephone', 'mobile', 'contact number' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Phone Number' ),
				),
				'ec_option_user_phone_required' => array(
					'type'     => 'toggle',
					'label'    => __( 'Phone number is required', 'wp-easycart' ),
					'desc'     => __( 'Shoppers cannot continue without entering a phone number.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_collect_user_phone',
					'keywords' => array( 'mandatory phone', 'require telephone' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Require Phone Number' ),
				),
				'ec_option_enable_company_name' => array(
					'type'     => 'toggle',
					'label'    => __( 'Company name', 'wp-easycart' ),
					'desc'     => __( 'Adds an optional company field to the address form.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'business', 'organisation', 'b2b' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Company Name' ),
				),
				'ec_option_enable_company_name_required' => array(
					'type'     => 'toggle',
					'label'    => __( 'Company name is required', 'wp-easycart' ),
					'desc'     => __( 'Shoppers cannot continue without entering a company name. Useful for trade-only stores.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_enable_company_name',
					'keywords' => array( 'mandatory company', 'trade only', 'b2b' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Require Company Name' ),
				),
				'ec_option_collect_vat_registration_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'VAT registration number', 'wp-easycart' ),
					'desc'     => __( 'Business shoppers can enter their VAT number. Combined with your tax settings it can zero-rate VAT on B2B orders.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'vat', 'tax id', 'b2b', 'eu' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'VAT Registration Number' ),
				),
				'ec_option_use_address2' => array(
					'type'     => 'toggle',
					'label'    => __( 'Address line 2', 'wp-easycart' ),
					'desc'     => __( 'Adds a second address line for apartment, suite or unit numbers.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'apartment', 'suite', 'unit', 'address 2' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Address Line 2' ),
				),
				'ec_option_user_order_notes' => array(
					'type'     => 'toggle',
					'label'    => __( 'Order notes box', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can leave a note with their order. Notes show on the order, the receipt and in the admin.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'customer notes', 'comments', 'special instructions', 'message' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Customer Notes' ),
				),
				'ec_option_require_terms_agreement' => array(
					'type'     => 'toggle',
					'label'    => __( 'Require terms agreement', 'wp-easycart' ),
					'desc'     => __( 'Shoppers must tick a box agreeing to your terms and privacy policy before they can pay. Add the page links below.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'terms and conditions', 'gdpr', 'consent', 'legal' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Terms Agreement' ),
				),
				/* The two legal links stay URLs ( the storefront links straight to them ) with a page picker attached:
				 * picking a page or post writes its permalink into the field, which stays editable for an external URL. */
				'ec_option_terms_link' => array(
					'type'              => 'url',
					'create_page'       => 'terms',
					'label'             => __( 'Terms and conditions page', 'wp-easycart' ),
					'desc'              => __( 'Linked from the agreement checkbox. Pick a page or post on this site, or type any URL.', 'wp-easycart' ),
					'default'           => 'http://yoursite.com/termsandconditions',
					'placeholder'       => 'https://www.yoursite.com/terms/',
					'parent'            => 'ec_option_require_terms_agreement',
					'attach'            => 'page_picker',
					'attach_placeholder' => __( 'Search pages and posts…', 'wp-easycart' ),
					'search_callback'   => 'ecv2_checkout_search_link_targets',
					'validate_callback' => 'ecv2_checkout_lookup_link_targets',
					'keywords'          => array( 'terms url', 'conditions', 'legal page' ),
					'legacy'            => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Terms URL' ),
				),
				'ec_option_privacy_link' => array(
					'type'              => 'url',
					'create_page'       => 'privacy',
					'label'             => __( 'Privacy policy page', 'wp-easycart' ),
					'desc'              => __( 'Linked from the agreement checkbox. Pick a page or post on this site, or type any URL.', 'wp-easycart' ),
					'default'           => 'http://yoursite.com/privacypolicy',
					'placeholder'       => 'https://www.yoursite.com/privacy/',
					'parent'            => 'ec_option_require_terms_agreement',
					'attach'            => 'page_picker',
					'attach_placeholder' => __( 'Search pages and posts…', 'wp-easycart' ),
					'search_callback'   => 'ecv2_checkout_search_link_targets',
					'validate_callback' => 'ecv2_checkout_lookup_link_targets',
					'keywords'          => array( 'privacy url', 'gdpr', 'legal page' ),
					'legacy'            => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Privacy Policy URL' ),
				),
			),
		),

		'address-fields' => array(
			'title'  => __( 'Country and state fields', 'wp-easycart' ),
			'hint'   => __( 'How the address form asks for country and state', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_country_dropdown' => array(
					'type'     => 'toggle',
					'label'    => __( 'Country as a drop-down list', 'wp-easycart' ),
					'desc'     => __( 'Shoppers pick from your active countries instead of typing. Always on with Square, Stripe, Intuit or live shipping rates.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'country select', 'combo box', 'country list' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Country: Combo Box' ),
				),
				'ec_option_default_country' => array(
					'type'     => 'select',
					'label'    => __( 'Preselected country', 'wp-easycart' ),
					'desc'     => __( 'The country chosen when a shopper has not picked one yet.', 'wp-easycart' ),
					'default'  => '0',
					'parent'   => 'ec_option_use_country_dropdown',
					'options'  => function () {
						return array( '0' => __( 'None', 'wp-easycart' ) ) + wp_easycart_settings_checkout_country_options();
					},
					'keywords' => array( 'default country', 'home country', 'preselect' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Country: Default Selection' ),
				),
				'ec_option_display_country_top' => array(
					'type'     => 'toggle',
					'label'    => __( 'Country field first', 'wp-easycart' ),
					'desc'     => __( 'Puts the country at the top of the address form so the address layout and state list can follow the choice.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'country position', 'form order', 'address layout' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'Country: Start of Form' ),
				),
				'ec_option_use_state_dropdown' => array(
					'type'     => 'toggle',
					'label'    => __( 'State as a drop-down list', 'wp-easycart' ),
					'desc'     => __( 'Shoppers pick their state or province from a list instead of typing. Always on with Square payments or live shipping rates.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'state select', 'province', 'combo box' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'State: Combo Box' ),
				),
				'ec_option_use_smart_states' => array(
					'type'     => 'toggle',
					'label'    => __( 'State list follows the country', 'wp-easycart' ),
					'desc'     => __( 'The state or province list updates to match the selected country. Always on with Square payments or live shipping rates.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'smart states', 'province', 'dynamic states' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Form', 'label' => 'State: Change with Country' ),
				),
			),
		),

		'payment-page' => array(
			'title'  => __( 'Payment page', 'wp-easycart' ),
			'hint'   => __( 'The last step before the order is placed', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_card_holder_name' => array(
					'type'     => 'toggle',
					'label'    => __( 'Ask for the cardholder name', 'wp-easycart' ),
					'desc'     => __( 'Adds a name-on-card field to the credit card form, which some gateways and compliance rules expect.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'name on card', 'credit card', 'cardholder', 'compliance' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Payment Page Options', 'label' => 'Collect Card Holder Name' ),
				),
				'ec_option_default_payment_type' => array(
					'type'     => 'pills',
					'label'    => __( 'Preselected payment method', 'wp-easycart' ),
					'desc'     => __( 'Which option is selected when the payment page opens. Most stores choose Credit card.', 'wp-easycart' ),
					'default'  => 'manual_bill',
					'options'  => array(
						'credit_card' => __( 'Credit card', 'wp-easycart' ),
						'third_party' => __( 'Third party', 'wp-easycart' ),
						'manual_bill' => __( 'Manual billing', 'wp-easycart' ),
					),
					'keywords' => array( 'default payment', 'paypal', 'bill later', 'selected method' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Payment Page Options', 'label' => 'Payment: Default Selection' ),
				),
			),
		),

		'orders' => array(
			'title'  => __( 'Order numbers and statuses', 'wp-easycart' ),
			'hint'   => __( 'How orders are numbered and the statuses they can move through', 'wp-easycart' ),
			'fields' => array(
				'ec_option_current_order_id' => array(
					'type'     => 'number',
					'label'    => __( 'Next order number', 'wp-easycart' ),
					'desc'     => __( 'The number the next order will receive. It can only be raised, never lowered.', 'wp-easycart' ),
					'default'  => 0,
					'current'  => 'wp_easycart_settings_checkout_next_order_id',
					'min'      => 1,
					'step'     => 1,
					'advanced' => true,
					'sanitize' => function ( $raw, $field ) {
						$next = (int) preg_replace( '/[^0-9]/', '', trim( (string) $raw ) );
						if ( $next <= 0 ) {
							return new WP_Error( 'number', __( 'Enter a whole number.', 'wp-easycart' ) );
						}
						$floor = wp_easycart_settings_checkout_next_order_id();
						if ( $floor > 0 && $next < $floor ) {
							/* translators: %d: the current next order number. */
							return new WP_Error( 'min', sprintf( __( 'The next order number is already %d and cannot be lowered.', 'wp-easycart' ), $floor ) );
						}
						return $next;
					},
					'on_save'  => function ( $value, $old, $field ) {
						global $wpdb;
						$wpdb->query( $wpdb->prepare( 'ALTER TABLE ec_order AUTO_INCREMENT = %d', (int) $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						/* Not a real option: the live value comes from the table, so drop the stored copy
						 * and let the row fall back to its default ( the current AUTO_INCREMENT ). */
						delete_option( 'ec_option_current_order_id' );
					},
					'keywords' => array( 'order id', 'invoice number', 'starting number', 'sequence' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Cart Settings', 'label' => 'Next Order Number' ),
				),
			),
			/* Order status editor. Saves through the classic AJAX handlers in wp_easycart_admin_order_statuses.php
			 * ( add / save / save_approved / archieve ), which take a 'wp-easycart-settings-checkout' nonce. */
			'enqueue' => function ( $page, $section ) {
				$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
				$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
				wp_enqueue_style( 'wp_easycart_admin_settings_checkout_v2_css', $css . 'settings-checkout-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
				wp_enqueue_script( 'wp_easycart_admin_settings_checkout_v2_js', $js . 'settings-checkout-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
				wp_localize_script( 'wp_easycart_admin_settings_checkout_v2_js', 'ecst_checkout_vars', array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wp-easycart-settings-checkout' ),
					'i18n'  => array(
						'saving'         => __( 'Saving…', 'wp-easycart' ),
						'saved'          => __( 'Saved', 'wp-easycart' ),
						'failed'         => __( 'Could not save', 'wp-easycart' ),
						'name_required'  => __( 'Enter a status name first.', 'wp-easycart' ),
						'confirm_title'  => __( 'Delete this status?', 'wp-easycart' ),
						'confirm_text'   => __( 'Orders already in this status keep it, but it can no longer be chosen.', 'wp-easycart' ),
						'delete'         => __( 'Delete', 'wp-easycart' ),
						'added'          => __( 'Status added.', 'wp-easycart' ),
						'deleted'        => __( 'Status deleted.', 'wp-easycart' ),
						'status_name'    => __( 'Status name', 'wp-easycart' ),
						/* translators: %s: order status name */
						'color_for'     => __( 'Color for %s', 'wp-easycart' ),
						/* translators: %s: order status name */
						'delete_named'   => __( 'Delete %s', 'wp-easycart' ),
					),
				) );
			},
			'render' => function ( $page, $section ) {
				global $wpdb;
				$statuses = $wpdb->get_results( 'SELECT status_id, order_status, color_code, is_approved FROM ec_orderstatus WHERE is_archieved = 0 ORDER BY status_id ASC' );
				$builtin_max = defined( 'wp_easycart_admin_order_statuses::BUILTIN_STATUS_MAX' ) ? wp_easycart_admin_order_statuses::BUILTIN_STATUS_MAX : 19; // Statuses 1–19 ship with the plugin.
				$builtin  = array();
				$custom   = array();
				foreach ( (array) $statuses as $status ) {
					if ( (int) $status->status_id <= $builtin_max ) {
						$builtin[] = $status;
					} else {
						$custom[] = $status;
					}
				}
				/* One status: swatch ( native color input under a round button ), quiet inline name, paid chip, delete. */
				$print_item = function ( $status, $locked ) {
					$id    = (int) $status->status_id;
					$color = sanitize_hex_color( (string) $status->color_code );
					$color = $color ? $color : '#e5e7eb';
					$paid  = (bool) $status->is_approved;
					?>
					<li class="ecos-item<?php echo $locked ? ' is-builtin' : ''; ?>" data-id="<?php echo esc_attr( $id ); ?>">
						<label class="ecos-swatch" style="--ecos-c:<?php echo esc_attr( $color ); ?>" title="<?php esc_attr_e( 'Change color', 'wp-easycart' ); ?>">
							<input type="color" class="ecos-color" value="<?php echo esc_attr( $color ); ?>" aria-label="<?php /* translators: %s: order status name */ echo esc_attr( sprintf( __( 'Color for %s', 'wp-easycart' ), $status->order_status ) ); ?>" />
						</label>
						<span class="ecos-name-wrap">
							<input type="text" class="ecos-name" value="<?php echo esc_attr( $status->order_status ); ?>" aria-label="<?php esc_attr_e( 'Status name', 'wp-easycart' ); ?>" />
							<span class="ecst-state ecos-state" aria-live="polite"></span>
						</span>
						<?php if ( $locked ) : ?>
							<span class="ecos-paid-chip is-fixed<?php echo $paid ? ' is-on' : ''; ?>" title="<?php esc_attr_e( 'Built-in status — whether it counts as paid is fixed.', 'wp-easycart' ); ?>"><?php echo $paid ? esc_html__( 'Paid', 'wp-easycart' ) : esc_html__( 'Unpaid', 'wp-easycart' ); ?></span>
						<?php else : ?>
							<label class="ecos-paid-chip<?php echo $paid ? ' is-on' : ''; ?>" title="<?php esc_attr_e( 'Click to switch between Paid and Unpaid. Paid statuses count as revenue.', 'wp-easycart' ); ?>">
								<input type="checkbox" class="ecos-paid" value="1"<?php checked( $paid ); ?> />
								<span class="ecos-paid-text" data-on="<?php esc_attr_e( 'Paid', 'wp-easycart' ); ?>" data-off="<?php esc_attr_e( 'Unpaid', 'wp-easycart' ); ?>"><?php echo $paid ? esc_html__( 'Paid', 'wp-easycart' ) : esc_html__( 'Unpaid', 'wp-easycart' ); ?></span>
							</label>
							<button type="button" class="ecos-del" aria-label="<?php /* translators: %s: order status name */ echo esc_attr( sprintf( __( 'Delete %s', 'wp-easycart' ), $status->order_status ) ); ?>" title="<?php esc_attr_e( 'Delete', 'wp-easycart' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
						<?php endif; ?>
					</li>
					<?php
				};
				?>
				<div class="ecos" id="ecst_orderstatus">
					<p class="ecos-intro"><?php esc_html_e( 'Click a color or name to change it; changes save as you go. Paid statuses count as revenue in reports and release downloads and gift cards.', 'wp-easycart' ); ?></p>

					<div class="ecos-group">
						<div class="ecos-group-head">
							<span class="ecos-group-title"><?php esc_html_e( 'Built-in', 'wp-easycart' ); ?></span>
							<span class="ecos-group-n"><?php echo (int) count( $builtin ); ?></span>
							<span class="ecos-group-hint"><?php esc_html_e( 'Used by payments, stock and reports, so they cannot be deleted and their paid setting is fixed.', 'wp-easycart' ); ?></span>
						</div>
						<ul class="ecos-grid">
							<?php foreach ( $builtin as $status ) { $print_item( $status, true ); } ?>
						</ul>
					</div>

					<div class="ecos-group">
						<div class="ecos-group-head">
							<span class="ecos-group-title"><?php esc_html_e( 'Your statuses', 'wp-easycart' ); ?></span>
							<span class="ecos-group-n" id="ecst_orderstatus_custom_n"><?php echo (int) count( $custom ); ?></span>
							<span class="ecos-group-hint"><?php esc_html_e( 'Extra steps for your own workflow, such as “Out for delivery”.', 'wp-easycart' ); ?></span>
						</div>
						<ul class="ecos-grid" id="ecst_orderstatus_rows">
							<?php foreach ( $custom as $status ) { $print_item( $status, false ); } ?>
						</ul>
						<p class="ecos-empty" id="ecst_orderstatus_empty"<?php echo $custom ? ' hidden' : ''; ?>><?php esc_html_e( 'No statuses of your own yet.', 'wp-easycart' ); ?></p>

						<div class="ecos-add" id="ecst_orderstatus_add">
							<label class="ecos-swatch" style="--ecos-c:#6b7280" title="<?php esc_attr_e( 'Change color', 'wp-easycart' ); ?>">
								<input type="color" class="ecos-color" id="ecst_orderstatus_add_color" value="#6b7280" aria-label="<?php esc_attr_e( 'New status color', 'wp-easycart' ); ?>" />
							</label>
							<span class="ecos-name-wrap">
								<input type="text" class="ecos-name" id="ecst_orderstatus_add_name" value="" placeholder="<?php esc_attr_e( 'New status name', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'New status name', 'wp-easycart' ); ?>" />
								<span class="ecst-state ecos-state" aria-live="polite"></span>
							</span>
							<label class="ecos-paid-chip" title="<?php esc_attr_e( 'Click to switch between Paid and Unpaid. Paid statuses count as revenue.', 'wp-easycart' ); ?>">
								<input type="checkbox" class="ecos-paid" id="ecst_orderstatus_add_paid" value="1" />
								<span class="ecos-paid-text" data-on="<?php esc_attr_e( 'Paid', 'wp-easycart' ); ?>" data-off="<?php esc_attr_e( 'Unpaid', 'wp-easycart' ); ?>"><?php esc_html_e( 'Unpaid', 'wp-easycart' ); ?></span>
							</label>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" id="ecst_orderstatus_add_btn"><?php esc_html_e( 'Add status', 'wp-easycart' ); ?></button>
						</div>
					</div>
				</div>
				<?php
			},
		),

		'stock-alerts' => array(
			'title'  => __( 'Stock alerts', 'wp-easycart' ),
			'hint'   => __( 'What counts as low stock, and the emails that go with it', 'wp-easycart' ),
			'fields' => array(
				'ec_option_low_stock_trigger_total' => array(
					'type'     => 'number',
					'label'    => __( 'Low stock threshold', 'wp-easycart' ),
					'desc'     => __( 'One number for the whole store: it marks items Low on the Inventory and Products screens and triggers the low stock email below. A product or variant with its own reorder point uses that instead.', 'wp-easycart' ),
					'default'  => '10',
					'min'      => 0,
					'step'     => 1,
					'unit'     => __( 'units', 'wp-easycart' ),
					'keywords' => array( 'trigger quantity', 'reorder point', 'minimum stock', 'low stock', 'inventory threshold' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Stock Control', 'label' => 'Low Stock: Trigger Quantity' ),
				),
				'ec_option_send_low_stock_emails' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email me when stock runs low', 'wp-easycart' ),
					'desc'     => __( 'Sends a low stock email to the store admin address the first time an item drops to the threshold above. It does not repeat until the item is restocked past it.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'low inventory', 'stock warning', 'notify admin' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Stock Control', 'label' => 'Low Stock: Notify Admin' ),
				),
				'ec_option_send_out_of_stock_emails' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email me when a product sells out', 'wp-easycart' ),
					'desc'     => __( 'Sends an out of stock email to the store admin address when an item reaches zero, for a whole product or a single option combination.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'sold out', 'zero stock', 'notify admin' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Stock Control', 'label' => 'Out of Stock: Notify Admin' ),
				),
			),
		),

		'abandoned-cart' => array(
			'title'  => __( 'Abandoned cart', 'wp-easycart' ),
			'hint'   => __( 'The classic daily reminder email', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(
				'ec_option_abandoned_cart_days' => array(
					'type'     => 'number',
					'label'    => __( 'Reminder email delay', 'wp-easycart' ),
					'desc'     => __( 'Days a cart sits untouched before the daily reminder goes out. Multi-step recovery sequences are set up under Marketing › Abandoned carts.', 'wp-easycart' ),
					'default'  => 3,
					'min'      => 1,
					'step'     => 1,
					'unit'     => __( 'days', 'wp-easycart' ),
					'keywords' => array( 'abandoned', 'recovery', 'reminder', 'cart email' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Settings', 'label' => 'Abandoned Cart Email' ),
				),
			),
		),

		'pickup-schedule' => array(
			'title'  => __( 'Pickup scheduling', 'wp-easycart' ),
			'hint'   => __( 'Pickup times for restaurant and pre-order products, and multiple locations', 'wp-easycart' ),
			'fields' => array(
				'ec_option_restaurant_allow_scheduling' => array(
					'type'     => 'toggle',
					'label'    => __( 'Restaurant orders can be scheduled', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can choose a later pickup time for restaurant-style products instead of ASAP only.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'restaurant', 'schedule', 'pickup time', 'food' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Restaurants: Allow Scheduling' ),
				),
				'ec_option_restaurant_schedule_range' => array(
					'type'     => 'select',
					'label'    => __( 'Pickup time slot interval', 'wp-easycart' ),
					'desc'     => __( 'Gap between the pickup times shoppers can choose.', 'wp-easycart' ),
					'default'  => '15',
					'options'  => $wp_easycart_checkout_minutes,
					'keywords' => array( 'interval', 'time slots', 'minutes', 'restaurant' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Restaurant Orders: Scheduling Range' ),
				),
				'ec_option_restaurant_pickup_asap_length' => array(
					'type'     => 'select',
					'label'    => __( 'Expected preparation time', 'wp-easycart' ),
					'desc'     => __( 'Minutes from ordering to pickup for ASAP restaurant orders.', 'wp-easycart' ),
					'default'  => '30',
					'options'  => $wp_easycart_checkout_prep_times,
					'keywords' => array( 'prep time', 'asap', 'ready in', 'restaurant' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Restaurant Orders: Expected Prep Time' ),
				),
				'ec_option_shedule_pickup_preorder' => array(
					'type'        => 'textarea',
					'label'       => __( 'Pre-order pickup instructions', 'wp-easycart' ),
					'desc'        => __( 'Shown in the box where shoppers choose a pickup date for pre-order products.', 'wp-easycart' ),
					'default'     => '',
					'rows'        => 3,
					'placeholder' => __( 'Enter a customer message', 'wp-easycart' ),
					'advanced'    => true,
					'keywords'    => array( 'preorder', 'pickup message', 'instructions', 'date' ),
					'legacy'      => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Cart Schedule: Preorder for Pickup Instructions' ),
				),
				'ec_option_shedule_pickup_restaurant' => array(
					'type'        => 'textarea',
					'label'       => __( 'Restaurant pickup instructions', 'wp-easycart' ),
					'desc'        => __( 'Shown in the box where shoppers choose a pickup time for restaurant-style products.', 'wp-easycart' ),
					'default'     => '',
					'rows'        => 3,
					'placeholder' => __( 'Enter a customer message', 'wp-easycart' ),
					'advanced'    => true,
					'keywords'    => array( 'restaurant message', 'pickup instructions', 'time' ),
					'legacy'      => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Cart Schedule: Restaurant Pickup Instructions' ),
				),
				'ec_option_pickup_enable_locations' => array(
					'type'     => 'toggle',
					'label'    => __( 'Multiple pickup locations', 'wp-easycart' ),
					'desc'     => __( 'Shoppers choose a pickup location and products can be tied to specific locations. Needs the multiple locations add-on.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'locations', 'stores', 'branches', 'pickup point' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Preorder Pickup: Enable Multiple Locations' ),
				),
				'ec_option_multiple_location_schedules_enabled' => array(
					'type'     => 'toggle',
					'label'    => __( 'Separate schedule per location', 'wp-easycart' ),
					'desc'     => __( 'Each location follows its own store schedule. Add the locations to your schedules first.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'parent'   => 'ec_option_pickup_enable_locations',
					'keywords' => array( 'location hours', 'schedules', 'opening times' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Preorder Pickup: Enable Location Based Schedules' ),
				),
				'ec_option_pickup_location_select_enabled' => array(
					'type'     => 'toggle',
					'label'    => __( 'Ask shoppers to choose a location when the store loads', 'wp-easycart' ),
					'desc'     => __( 'Shows a location picker on arrival so products and schedules match the chosen location.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'parent'   => 'ec_option_pickup_enable_locations',
					'keywords' => array( 'location selector', 'choose store', 'picker' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Preorder Pickup: Enable Location Selector' ),
				),
				'ec_option_pickup_location_show_km' => array(
					'type'     => 'pills',
					'label'    => __( 'Distance units', 'wp-easycart' ),
					'desc'     => __( 'How far each location is from the shopper in the location picker.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'parent'   => 'ec_option_pickup_location_select_enabled',
					'options'  => array(
						'0' => __( 'Miles', 'wp-easycart' ),
						'1' => __( 'Kilometres', 'wp-easycart' ),
					),
					'keywords' => array( 'miles', 'kilometres', 'distance' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Preorder Pickup: Distance Format', 'note' => 'classic save handler never accepted this key' ),
				),
				'ec_option_pickup_location_google_site_key' => array(
					'type'     => 'text',
					'label'    => __( 'Google Maps API key', 'wp-easycart' ),
					'desc'     => __( 'Geocodes shopper searches and your locations automatically. Without it you must enter each location’s latitude and longitude by hand.', 'wp-easycart' ),
					'default'  => '',
					'advanced' => true,
					'parent'   => 'ec_option_pickup_location_select_enabled',
					'keywords' => array( 'google api', 'geocoding', 'maps key' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Google API Key' ),
				),
				'ec_option_pickup_location_unavailable' => array(
					'type'     => 'select',
					'label'    => __( 'Products not stocked at the chosen location', 'wp-easycart' ),
					'desc'     => __( 'How products that are not available at the selected pickup location appear in the store.', 'wp-easycart' ),
					'default'  => '3',
					'advanced' => true,
					'parent'   => 'ec_option_pickup_location_select_enabled',
					'options'  => array(
						'1' => __( 'Show as normal', 'wp-easycart' ),
						'2' => __( 'Hide', 'wp-easycart' ),
						'3' => __( 'Show but disable purchase', 'wp-easycart' ),
					),
					'keywords' => array( 'unavailable', 'not at location', 'hide products' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Checkout Schedule Settings', 'label' => 'Preorder Pickup: Product Not At Location' ),
				),
			),
		),

		'text-notifications' => array(
			'title'  => __( 'Order text notifications', 'wp-easycart' ),
			'hint'   => __( 'SMS alerts to shoppers through the WP EasyCart cloud texting service', 'wp-easycart' ),
			'pro'    => true,
			'fields' => array(
				'ec_option_enable_cloud_messages' => array(
					'type'     => 'toggle',
					'label'    => __( 'Send text notifications', 'wp-easycart' ),
					'desc'     => __( 'Turns on the triggers and messages set up below. Leave off while you are still writing them.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'sms', 'text message', 'cloud messaging', 'twilio' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Order Text Notifications', 'label' => 'Enable Cloud Messaging' ),
				),
				'ec_option_cloud_messages_default_country' => array(
					'type'     => 'select',
					'label'    => __( 'Default phone country', 'wp-easycart' ),
					'desc'     => __( 'Used for the phone country code when the shopper’s country cannot be detected from their IP address.', 'wp-easycart' ),
					'default'  => 'US',
					'parent'   => 'ec_option_enable_cloud_messages',
					'options'  => 'wp_easycart_settings_checkout_country_options',
					'keywords' => array( 'country code', 'dial code', 'phone country' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Order Text Notifications', 'label' => 'Country: Default Country' ),
				),
				/* Stored as a PHP array of ISO codes ( the storefront phone widget reads it as an array ). */
				'ec_option_cloud_messages_preferred_countries' => array(
					'type'      => 'multiselect',
					'separator' => 'array',
					'label'     => __( 'Preferred phone countries', 'wp-easycart' ),
					'desc'      => __( 'Countries pinned to the top of the phone country-code list.', 'wp-easycart' ),
					'default'   => array( 'US', 'CA', 'AU' ),
					'pro'       => true,
					'parent'    => 'ec_option_enable_cloud_messages',
					'options'   => 'wp_easycart_settings_checkout_country_options',
					'placeholder' => __( 'Search countries…', 'wp-easycart' ),
					'keywords'  => array( 'preferred countries', 'dial code', 'phone list' ),
					'legacy'   => array( 'page' => 'checkout', 'section' => 'Order Text Notifications', 'label' => 'Cloud Messaging Preferred Countries' ),
				),
			),
		),
	),
);
