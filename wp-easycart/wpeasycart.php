<?php
/**
 * Plugin Name: WP EasyCart
 * Plugin URI: http://www.wpeasycart.com
 * Description: The WordPress Shopping Cart by WP EasyCart is a simple eCommerce solution that installs into new or existing WordPress blogs. Customers purchase directly from your store! Get a full ecommerce platform in WordPress! Sell products, downloadable goods, gift cards, clothing and more! Now with WordPress, the powerful features are still very easy to administrate! If you have any questions, please view our website at <a href="http://www.wpeasycart.com" target="_blank">WP EasyCart</a>.

 * Version: 6.0.3
 * Requires at least: 6.5
 * Requires PHP: 7.3
 * Author: WP EasyCart
 * Author URI: http://www.wpeasycart.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-easycart
 * Domain Path: /languages
 * Elementor tested up to: 4.3.2
 * Elementor Pro tested up to: 4.3.1
 *
 * This program is free to download and install and sell with PayPal. Although we offer a ton of FREE features, some of the more advanced features and payment options requires the purchase of our professional shopping cart admin plugin. Professional features include alternate third party gateways, live payment gateways, coupons, promotions, advanced product features, and much more!
 *
 * @package wpeasycart
 * @version 6.0.3
 * @author WP EasyCart <sales@wpeasycart.com>
 * @copyright Copyright (c) 2012, WP EasyCart
 * @link http://www.wpeasycart.com
 */

define( 'EC_PUGIN_NAME', 'WP EasyCart' );
define( 'EC_PLUGIN_DIRECTORY', __DIR__ );
define( 'EC_PLUGIN_DATA_DIRECTORY', __DIR__ . '-data' );
define( 'EC_CURRENT_VERSION', '6_0_3' );
define( 'EC_CURRENT_DB', '1_30' );/* Backwards Compatibility */
define( 'EC_UPGRADE_DB', '123' );

/*
 * Gateway notifications ( webhooks ): Square ( 6.0.1 ), PayPal ( 6.0.2 ).
 *
 * Square's notifications come from connect.wpeasycart.com, which verifies Square's own signature and forwards the
 * body on. Square signs the notification URL it holds — the proxy's — so the only thing this store can
 * check is the key it gave the proxy when webhooks were registered. PayPal's come the same way for a store connected
 * through WP EasyCart Connect ( and straight from PayPal, signed by PayPal, for a store on its own PayPal app ). These
 * helpers hold that key check, the rolling log each gateway's panel reads ( ec_option_{gateway}_webhook_log and
 * ec_option_{gateway}_webhook_last ), and the event-id list that stops a replay being applied twice.
 *
 * 6.0.2: written once for every gateway; the Square names below call them with 'square' and behave as before.
 */
if ( ! function_exists( 'wp_easycart_gateway_webhook_key_ok' ) ) {
	/**
	 * Does this request carry the key this store registered?
	 *
	 * Accepted as the X-EasyCart-Notification-Key header ( preferred ) or a `key` query argument, so the
	 * forwarder can use whichever suits it.
	 *
	 * @since 6.0.2 ( was wp_easycart_square_webhook_key_ok() )
	 * @param string $expected The stored key.
	 * @return bool
	 */
	function wp_easycart_gateway_webhook_key_ok( $expected ) {
		$sent = '';
		if ( isset( $_SERVER['HTTP_X_EASYCART_NOTIFICATION_KEY'] ) ) {
			$sent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_EASYCART_NOTIFICATION_KEY'] ) );
		} elseif ( isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key is the credential being checked.
			$sent = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		}
		return ( '' !== $sent && hash_equals( (string) $expected, $sent ) );
	}
}

if ( ! function_exists( 'wp_easycart_gateway_webhook_entries' ) ) {
	/**
	 * The notification log of one gateway, newest first.
	 *
	 * @since 6.0.2
	 * @param string $gateway Gateway key ( square, paypal ).
	 * @param bool   $fresh   Read the stored row itself rather than this request's copy ( another request may have
	 *                        written it since this one started ).
	 * @return array[] Entries: id, type, note, time.
	 */
	function wp_easycart_gateway_webhook_entries( $gateway, $fresh = false ) {
		global $wpdb;
		$name = 'ec_option_' . sanitize_key( $gateway ) . '_webhook_log';
		if ( $fresh && isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->options ) ) {
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) );
			$log = ( null === $raw ) ? array() : maybe_unserialize( $raw );
		} else {
			$log = get_option( $name );
		}
		return is_array( $log ) ? array_values( $log ) : array();
	}
}

if ( ! function_exists( 'wp_easycart_gateway_webhook_log' ) ) {
	/**
	 * Record one notification. Kept in an option rather than ec_response, because that table only records
	 * anything when the gateway log is switched on and this counter has to be readable either way.
	 *
	 * 6.0.2: at most 10 'rejected' entries are kept, so a stream of refused requests cannot push the ids of handled
	 * events out of the list that stops a replay.
	 *
	 * @since 6.0.2 ( was wp_easycart_square_webhook_log() )
	 * @param string $gateway  Gateway key ( square, paypal ).
	 * @param string $event_id The gateway's event id, when it sent one ( never the id a rejected request claims ).
	 * @param string $type     Event type, or 'rejected'.
	 * @param string $note     Optional sentence for the panel.
	 * @param bool   $fresh    Build on the stored row itself ( see wp_easycart_gateway_webhook_entries() ).
	 * @return void
	 */
	function wp_easycart_gateway_webhook_log( $gateway, $event_id, $type, $note = '', $fresh = false ) {
		$gateway = sanitize_key( $gateway );
		$log     = wp_easycart_gateway_webhook_entries( $gateway, $fresh );
		array_unshift( $log, array(
			'id'   => substr( (string) $event_id, 0, 64 ),
			'type' => substr( (string) $type, 0, 64 ),
			'note' => substr( (string) $note, 0, 200 ),
			'time' => time(),
		) );
		$size = (int) apply_filters( 'wp_easycart_gateway_webhook_log_size', 50, $gateway );
		if ( 'square' === $gateway ) {
			$size = (int) apply_filters( 'wp_easycart_square_webhook_log_size', $size );
		}
		$kept     = array();
		$rejected = 0;
		foreach ( $log as $entry ) {
			if ( is_array( $entry ) && isset( $entry['type'] ) && 'rejected' === $entry['type'] && ++$rejected > 10 ) {
				continue;
			}
			$kept[] = $entry;
		}
		update_option( 'ec_option_' . $gateway . '_webhook_log', array_slice( $kept, 0, max( 1, $size ) ), false );
		if ( 'rejected' !== $type ) {
			update_option( 'ec_option_' . $gateway . '_webhook_last', time(), false );
		}
	}
}

if ( ! function_exists( 'wp_easycart_gateway_webhook_seen' ) ) {
	/**
	 * Has this event id already been handled? Recording happens in wp_easycart_gateway_webhook_log(), so this
	 * only reads the list.
	 *
	 * @since 6.0.2 ( was wp_easycart_square_webhook_seen() )
	 * @param string $gateway  Gateway key ( square, paypal ).
	 * @param string $event_id The gateway's event id.
	 * @param bool   $fresh    Read the stored row itself.
	 * @return bool
	 */
	function wp_easycart_gateway_webhook_seen( $gateway, $event_id, $fresh = false ) {
		if ( '' === (string) $event_id ) {
			return false;
		}
		foreach ( wp_easycart_gateway_webhook_entries( $gateway, $fresh ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) && (string) $entry['id'] === (string) $event_id ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_easycart_gateway_webhook_claim' ) ) {
	/**
	 * Record a verified notification and say whether it is new, in one step, so two deliveries of the same event at
	 * the same moment cannot both be acted on. A repeat is logged as one ( "Already handled; ignored." ).
	 *
	 * @since 6.0.2
	 * @param string $gateway  Gateway key ( square, paypal ).
	 * @param string $event_id The gateway's event id ( '' = none sent: recorded, never a repeat ).
	 * @param string $type     Event type.
	 * @return bool True when the event has not been handled before.
	 */
	function wp_easycart_gateway_webhook_claim( $gateway, $event_id, $type ) {
		global $wpdb;
		$lock   = 'wpec_webhook_' . sanitize_key( $gateway );
		$locked = false;
		if ( '' !== (string) $event_id && isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) ) {
			$locked = ( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $lock, 5 ) ) );
		}
		$new = ! wp_easycart_gateway_webhook_seen( $gateway, $event_id, true );
		wp_easycart_gateway_webhook_log( $gateway, $event_id, $type, $new ? '' : __( 'Already handled; ignored.', 'wp-easycart' ), true );
		if ( $locked ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}
		return $new;
	}
}

if ( ! function_exists( 'wp_easycart_square_webhook_key_ok' ) ) {
	/**
	 * Square's name for wp_easycart_gateway_webhook_key_ok() ( 6.0.1; WP EasyCart PRO may call it ).
	 *
	 * @param string $expected The stored key.
	 * @return bool
	 */
	function wp_easycart_square_webhook_key_ok( $expected ) {
		return wp_easycart_gateway_webhook_key_ok( $expected );
	}
}

if ( ! function_exists( 'wp_easycart_square_webhook_log' ) ) {
	/**
	 * Square's name for wp_easycart_gateway_webhook_log() ( 6.0.1 ).
	 *
	 * @param string $event_id Square's event id, when it sent one.
	 * @param string $type     Event type, or 'rejected'.
	 * @param string $note     Optional sentence for the panel.
	 * @return void
	 */
	function wp_easycart_square_webhook_log( $event_id, $type, $note = '' ) {
		wp_easycart_gateway_webhook_log( 'square', $event_id, $type, $note );
	}
}

if ( ! function_exists( 'wp_easycart_square_webhook_seen' ) ) {
	/**
	 * Square's name for wp_easycart_gateway_webhook_seen() ( 6.0.1 ).
	 *
	 * @param string $event_id Square's event id.
	 * @return bool
	 */
	function wp_easycart_square_webhook_seen( $event_id ) {
		return wp_easycart_gateway_webhook_seen( 'square', $event_id );
	}
}

/*
 * SagePay Pay Now South Africa ( Netcash ) notifications ( 6.0.2 ).
 *
 * Netcash does not sign the notification it posts, so the handler in wp_easycart_webhook_catch() takes only the
 * RequestTrace from it and reads the transaction back from Netcash's status service, acting on that answer alone.
 */
if ( ! function_exists( 'wp_easycart_paynow_za_lookup' ) ) {
	/**
	 * Read one Pay Now transaction back from Netcash.
	 *
	 * @since 6.0.2
	 *
	 * @param string $request_trace The RequestTrace Netcash posted.
	 * @return array|WP_Error Netcash's record ( RequestTrace, Amount, TransactionAccepted, Reference, Reason ), or
	 *                        WP_Error: 'paynow_unreachable' when Netcash could not be asked, 'paynow_unknown' when
	 *                        it has no transaction with that trace.
	 */
	function wp_easycart_paynow_za_lookup( $request_trace ) {
		$response = wp_remote_get(
			'https://ws.netcash.co.za/PayNow/TransactionStatus/Check?RequestTrace=' . rawurlencode( $request_trace ),
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'paynow_unreachable', 'Netcash status lookup failed: ' . $response->get_error_message() );
		}
		$code        = (int) wp_remote_retrieve_response_code( $response );
		$transaction = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $transaction ) ) {
			return new WP_Error( 'paynow_unreachable', 'Netcash status lookup returned HTTP ' . $code . '.' );
		}
		// An unknown trace still answers 200, with a placeholder trace ( "0.0" ) and TransactionAccepted false.
		if ( ! isset( $transaction['RequestTrace'] ) || (string) $transaction['RequestTrace'] !== (string) $request_trace ) {
			return new WP_Error( 'paynow_unknown', 'Netcash has no transaction with this RequestTrace.' );
		}
		return $transaction;
	}
}

if ( ! function_exists( 'wp_easycart_onepage_active' ) ) {
	/**
	 * Is the one-page checkout in use? The setting ( Settings › Checkout ) only counts while WP EasyCart PRO unlocks it
	 * ( filter wp_easycart_onepage_checkout, licence-checked ): every one-page branch asks here, never the raw option,
	 * so a lapsed licence switches the whole checkout back to the classic one at once.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	function wp_easycart_onepage_active() {
		return (bool) apply_filters( 'wp_easycart_onepage_checkout', false ) && ! wp_easycart_onepage_incompatible();
	}
}
if ( ! function_exists( 'wp_easycart_cart_is_dynamic' ) ) {
	/**
	 * Does the cart page render through ec_ajax_get_dynamic_cart_page ( a placeholder filled after load )? With cache
	 * prevention on, and always for the one-page checkout ( its steps, returns and notices live on that route ).
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	function wp_easycart_cart_is_dynamic() {
		return (bool) get_option( 'ec_option_cache_prevent' ) || wp_easycart_onepage_active();
	}
}
if ( ! function_exists( 'wp_easycart_onepage_incompatible' ) ) {
	/**
	 * Payment methods the one-page checkout cannot take yet: a store using one keeps the classic checkout, and
	 * Settings › Checkout says why.
	 *
	 * @since 6.0.2
	 * @return string[] Their names.
	 */
	function wp_easycart_onepage_incompatible() {
		$names       = array();
		$third_party = (string) get_option( 'ec_option_payment_third_party' );
		if ( 'paypal_advanced' == $third_party ) {
			$names[] = 'PayPal Advanced';
		}
		if ( 'realex_thirdparty' == $third_party && 'hpp' == get_option( 'ec_option_realex_thirdparty_type' ) ) {
			$names[] = 'Realex HPP';
		}
		/**
		 * Payment methods that keep a store on the classic checkout.
		 *
		 * @since 6.0.2
		 * @param string[] $names Names.
		 */
		return (array) apply_filters( 'wp_easycart_onepage_incompatible', $names );
	}
}
if ( ! function_exists( 'wp_easycart_offers_active' ) ) {
	function wp_easycart_offers_active( $min_version = '' ) {
		return function_exists( 'wp_easycart_offers_available' ) && wp_easycart_offers_available( $min_version );
	}
}

if ( ! function_exists( 'wp_easycart_offer_line_savings' ) ) {
	/**
	 * What Offers took off one cart or order line, split by how each offer applied: `code` ( a code the shopper entered )
	 * and `automatic`. `shown` is the part the store shows on the line: codes with "Show coupon savings on each line"
	 * ( ec_option_show_coupon_discount_total ), automatic offers with "Show promotion savings on each line"
	 * ( ec_option_show_promotion_discount_total ), as those settings did for the older coupons and promotions.
	 *
	 * @since 6.0.2
	 * @param array|string $offer_discounts The line's offer discounts: the Offers engine's list ( offer_id, label, amount, code ),
	 *                                      or the JSON an order line saved in ec_orderdetail.applied_offers.
	 * @return array { code, automatic, shown } for the whole line, in the store's base currency.
	 */
	function wp_easycart_offer_line_savings( $offer_discounts ) {
		if ( is_string( $offer_discounts ) ) {
			$offer_discounts = ( '' !== trim( $offer_discounts ) ) ? json_decode( $offer_discounts, true ) : array();
		}
		$savings = array( 'code' => 0.0, 'automatic' => 0.0, 'shown' => 0.0 );
		if ( is_array( $offer_discounts ) ) {
			foreach ( $offer_discounts as $offer_discount ) {
				$offer_discount = (array) $offer_discount;
				$amount = ( isset( $offer_discount['amount'] ) && is_numeric( $offer_discount['amount'] ) ) ? (float) $offer_discount['amount'] : 0;
				if ( $amount <= 0 ) {
					continue;
				}
				if ( isset( $offer_discount['code'] ) && '' !== trim( (string) $offer_discount['code'] ) ) {
					$savings['code'] += $amount;
				} else {
					$savings['automatic'] += $amount;
				}
			}
		}
		if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
			$savings['shown'] += $savings['code'];
		}
		if ( get_option( 'ec_option_show_promotion_discount_total' ) ) {
			$savings['shown'] += $savings['automatic'];
		}
		/**
		 * What a cart or order line shows as its Offers savings.
		 *
		 * @since 6.0.2
		 * @param array        $savings         { code, automatic, shown } for the whole line, base currency.
		 * @param array|string $offer_discounts The line's offer discounts.
		 */
		$savings = apply_filters( 'wp_easycart_offer_line_savings', $savings, $offer_discounts );
		return array(
			'code'      => ( isset( $savings['code'] ) ) ? (float) $savings['code'] : 0.0,
			'automatic' => ( isset( $savings['automatic'] ) ) ? (float) $savings['automatic'] : 0.0,
			'shown'     => ( isset( $savings['shown'] ) ) ? max( 0.0, (float) $savings['shown'] ) : 0.0,
		);
	}
}

if ( ! function_exists( 'wp_easycart_canada_tax_label' ) ) {
	/**
	 * Shopper-facing name of a Canadian sales tax line ( cart, checkout, emails, receipts, wallets ).
	 * Quebec's provincial tax is the QST ( TVQ in French ); stores enter its rate in the PST column of
	 * Settings › Taxes › Canada, so for Quebec addresses the PST line is named QST. Names come from the
	 * language editor ( Cart › Cart Totals: GST / PST / QST / HST ), falling back to the English abbreviations.
	 *
	 * @since 6.0.0
	 * @param string $type  gst | pst | hst.
	 * @param string $state Province code of the address the tax was calculated for.
	 * @return string Plain text.
	 */
	function wp_easycart_canada_tax_label( $type, $state = '' ) {
		$type = strtolower( (string) $type );
		if ( 'pst' === $type && 'QC' === strtoupper( trim( (string) $state ) ) ) {
			$type = 'qst';
		}
		$defaults = array( 'gst' => 'GST', 'pst' => 'PST', 'qst' => 'QST', 'hst' => 'HST' );
		if ( ! isset( $defaults[ $type ] ) ) {
			return '';
		}
		$label = function_exists( 'wp_easycart_language' ) ? trim( (string) wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_' . $type ) ) : '';
		if ( '' === $label ) {
			$label = $defaults[ $type ];
		}
		return (string) apply_filters( 'wp_easycart_canada_tax_label', html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ), $type, $state );
	}
}

if ( ! function_exists( 'wp_easycart_offers_template' ) ) {
	function wp_easycart_offers_template( $file, $args = array() ) {
		if ( wp_easycart_offers_active() ) {
			ec_offer_display::load_partial( $file, $args );
		}
	}
}

if ( ! function_exists( 'wp_easycart_offers_your_price' ) ) {
	/**
	 * "Your price" on a product page with options: what the shopper pays, a running offer's price preview applied ( offers
	 * that leave sale items alone skip a product on sale ). With WP EasyCart PRO 6.0.2 the offer's rules go on the price's
	 * wrapper ( data-wpec-offer-rules ), so ec-store.js ( wpeasycart_final_price_text() ) and the Elementor Add to Cart widget
	 * work the offer out again when the options change the price; an older PRO gets the offer price until then.
	 *
	 * @since 6.0.2
	 * @param ec_product $product Product.
	 * @param float      $price   The price before offers ( options, a quantity grid ).
	 * @return array { price: float to show, attrs: escaped attributes for the wrapper ( '' without an offer ) }.
	 */
	function wp_easycart_offers_your_price( $product, $price ) {
		$price  = (float) $price;
		$answer = array(
			'price' => $price,
			'attrs' => '',
		);
		if ( $price <= 0 || ! is_object( $product ) || ! wp_easycart_offers_active() || ! class_exists( 'ec_offer_display' ) || ! method_exists( 'ec_offer_display', 'get_product_price_preview' ) ) {
			return $answer;
		}
		$list    = isset( $product->list_price ) ? (float) $product->list_price : 0;
		$offered = ec_offer_display::get_product_price_preview( $product->product_id, $product->manufacturer_id, $price, $list );
		if ( false === $offered || (float) $offered >= $price ) {
			return $answer;
		}
		$answer['price'] = (float) $offered;
		$answer['attrs'] = ' data-wpec-offer="1" data-wpec-offer-base="' . esc_attr( $price ) . '"';
		if ( method_exists( 'ec_offer_display', 'get_product_price_rules' ) ) {
			$answer['attrs'] .= ' data-wpec-offer-rules="' . esc_attr( wp_json_encode( array_values( (array) ec_offer_display::get_product_price_rules( $product->product_id, $product->manufacturer_id, $price, $list ) ) ) ) . '"';
		}
		return $answer;
	}
}

if ( ! function_exists( 'wp_easycart_offers_default_options' ) ) {
	function wp_easycart_offers_default_options() {
		add_option( 'ec_option_offer_stacking_enabled', '1' );
		add_option( 'ec_option_offer_max_codes_per_cart', '3' );
		add_option( 'ec_option_offer_reverse_on_refund', '1' );
		add_option( 'ec_option_offer_gift_default_stock_behavior', 'skip_message' );
		add_option( 'ec_option_offer_show_progress_messages', '1' );
	}
	add_action( 'init', 'wp_easycart_offers_default_options', 5 );
}

if ( ! function_exists( 'wp_easycart_offers_status_listener' ) ) {
	function wp_easycart_offers_status_listener( $order_id, $orderstatus_id ) {
		if ( wp_easycart_offers_active() ) {
			$reversal_statuses = apply_filters( 'wp_easycart_offer_reversal_statuses', array( 16, 19 ) );
			if ( in_array( (int) $orderstatus_id, array_map( 'intval', $reversal_statuses ), true ) ) {
				ec_offer_integration::order_reversed( $order_id );
			}
		}
	}
	add_action( 'wpeasycart_order_status_update', 'wp_easycart_offers_status_listener', 10, 2 );
}

if ( ! function_exists( 'wp_easycart_hash_password' ) ) {
	function wp_easycart_hash_password( $raw_password ) {
		return password_hash( (string) $raw_password, PASSWORD_DEFAULT );
	}
}

if ( ! function_exists( 'wp_easycart_password_is_legacy_md5' ) ) {
	function wp_easycart_password_is_legacy_md5( $stored_hash ) {
		return is_string( $stored_hash ) && strlen( $stored_hash ) === 32 && ctype_xdigit( $stored_hash );
	}
}

if ( ! function_exists( 'wp_easycart_verify_password' ) ) {
	function wp_easycart_verify_password( $raw_password, $stored_hash, $precomputed_hash = '', $user = null ) {
		$verified = false;
		if ( is_string( $stored_hash ) && '' !== $stored_hash ) {
			if ( wp_easycart_password_is_legacy_md5( $stored_hash ) ) {
				$verified = hash_equals( $stored_hash, md5( (string) $raw_password ) );
			} elseif ( '$' === substr( $stored_hash, 0, 1 ) ) {
				$verified = password_verify( (string) $raw_password, $stored_hash );
			}
			if ( ! $verified && '' !== (string) $precomputed_hash ) {
				$verified = hash_equals( (string) $stored_hash, (string) $precomputed_hash );
			}
		}
		return (bool) apply_filters( 'wpeasycart_password_verify', $verified, $raw_password, $stored_hash, $user );
	}
}

if ( ! function_exists( 'wp_easycart_password_needs_rehash' ) ) {
	function wp_easycart_password_needs_rehash( $stored_hash ) {
		$needs = false;
		if ( wp_easycart_password_is_legacy_md5( $stored_hash ) ) {
			$needs = true;
		} elseif ( is_string( $stored_hash ) && '$' === substr( $stored_hash, 0, 1 ) ) {
			$needs = password_needs_rehash( $stored_hash, PASSWORD_DEFAULT );
		}
		return (bool) apply_filters( 'wpeasycart_password_needs_rehash', $needs, $stored_hash );
	}
}

if ( ! function_exists( 'wp_easycart_generate_password_reset_token' ) ) {
	function wp_easycart_generate_password_reset_token( $user ) {
		$lifetime = (int) apply_filters( 'wp_easycart_password_reset_token_lifetime', HOUR_IN_SECONDS );
		$expires = time() + $lifetime;
		$data = $user->user_id . '|' . $expires;
		$signature = hash_hmac( 'sha256', $data . '|' . strtolower( $user->email ) . '|' . $user->password, wpeasycart_session()->get_secret_key() );
		return $user->user_id . '-' . $expires . '-' . $signature;
	}
}

if ( ! function_exists( 'wp_easycart_validate_password_reset_token' ) ) {
	function wp_easycart_validate_password_reset_token( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}
		$parts = explode( '-', $token );
		if ( count( $parts ) !== 3 ) {
			return false;
		}
		list( $user_id, $expires, $signature ) = $parts;
		$user_id = (int) $user_id;
		$expires = (int) $expires;
		if ( $user_id <= 0 || $expires < time() || ! ctype_xdigit( $signature ) ) {
			return false;
		}

		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, email, password, first_name, last_name FROM ec_user WHERE user_id = %d', $user_id ) );
		if ( ! $user ) {
			return false;
		}

		$data = $user->user_id . '|' . $expires;
		$expected = hash_hmac( 'sha256', $data . '|' . strtolower( $user->email ) . '|' . $user->password, wpeasycart_session()->get_secret_key() );
		if ( ! hash_equals( $expected, (string) $signature ) ) {
			return false;
		}

		return $user;
	}
}

if ( ! function_exists( 'wp_easycart_generate_activation_key' ) ) {
	function wp_easycart_generate_activation_key( $email ) {
		return hash_hmac( 'sha256', 'wpec-activate|' . strtolower( trim( $email ) ), wpeasycart_session()->get_secret_key() );
	}
}

if ( ! function_exists( 'wp_easycart_send_activation_email' ) ) {
	function wp_easycart_send_activation_email( $email ) {
		$key = wp_easycart_generate_activation_key( $email );

		$activation_url = wpeasycart_links()->get_account_page( 'activate_account', array( 'email' => $email, 'key' => $key ) );
		$message  = wp_easycart_language()->get_text( "account_validation_email", "account_validation_email_message" ) . "\r\n";
		$message .= "<a href=\"" . esc_url( $activation_url ) . "\" target=\"_blank\">" . wp_easycart_language()->get_text( "account_validation_email", "account_validation_email_link" ) . "</a>";

		/* 6.0.0: shared email design ( heading, message, button, plain link fallback ). */
		if ( class_exists( 'wp_easycart_email_design' ) ) {
			$ed            = 'wp_easycart_email_design';
			$verify_intro  = wp_easycart_language()->get_text( 'account_validation_email', 'account_validation_email_message' );
			$verify_body   = $ed::get_paragraph( wp_kses_post( $verify_intro ) );
			$verify_body  .= $ed::get_button( $activation_url, wp_easycart_language()->get_text( 'account_validation_email', 'account_validation_email_link' ), array( 'margin' => '4px 0 20px 0' ) );
			$verify_body  .= $ed::get_paragraph( esc_html__( 'If the button does not work, copy this link into your browser:', 'wp-easycart' ) . '<br /><a href="' . esc_url( $activation_url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . 'word-break:break-all;">' . esc_html( $activation_url ) . '</a>', array( 'tone' => 'small', 'margin' => '0' ) );
			$message       = $ed::wrap(
				$verify_body,
				array(
					'title'     => wp_easycart_language()->get_text( 'account_validation_email', 'account_validation_email_title' ),
					'heading'   => wp_easycart_language()->get_text( 'account_validation_email', 'account_validation_email_title' ),
					'preheader' => $verify_intro,
				)
			);
		}

		$headers   = array();
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=utf-8";
		$headers[] = "From: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_password_from_email' ) );
		$headers[] = "X-Mailer: PHP/" . phpversion();

		$email_send_method = get_option( 'ec_option_use_wp_mail' );
		$email_send_method = apply_filters( 'wpeasycart_email_method', $email_send_method );

		if ( $email_send_method == "1" ) {
			wp_mail( $email, wp_easycart_language()->get_text( "account_validation_email", "account_validation_email_title" ), $message, implode("\r\n", $headers));

		} else if ( $email_send_method == "0" ) {
			$mailer = new wpeasycart_mailer();
			$mailer->send_customer_email( $email, wp_easycart_language()->get_text( "account_validation_email", "account_validation_email_title" ), $message );

		} else {
			do_action( 'wpeasycart_custom_register_verification_email', stripslashes( get_option( 'ec_option_password_from_email' ) ), $email, "", wp_easycart_language()->get_text( "account_validation_email", "account_validation_email_title" ), $message );

		}
	}
}

if ( ! function_exists( 'wp_easycart_account_register_admin_email_html' ) ) {
	/**
	 * Body of the "new account" admin notice ( Settings › Email › send signup email ), on the shared email design.
	 *
	 * @since 6.0.0
	 *
	 * @param string $email New customer's email address.
	 * @return string HTML email ( the legacy one-line message when the design class is missing ).
	 */
	function wp_easycart_account_register_admin_email_html( $email ) {
		$email = sanitize_email( (string) $email );
		$text  = wp_easycart_language()->get_text( 'account_register', 'account_register_email_message' );
		if ( ! class_exists( 'wp_easycart_email_design' ) ) {
			return wp_kses_post( $text ) . ' ' . esc_html( $email );
		}
		$ed   = 'wp_easycart_email_design';
		$body = $ed::get_paragraph( wp_kses_post( $text ) );
		$body .= $ed::get_card_start();
		$body .= $ed::get_key_values(
			array(
				array(
					'label' => esc_html__( 'Email', 'wp-easycart' ),
					'value' => '<a href="mailto:' . esc_attr( $email ) . '" style="color:#111827;text-decoration:none;word-break:break-all;">' . esc_html( $email ) . '</a>',
				),
			)
		);
		$body .= $ed::get_card_end();
		return $ed::wrap(
			$body,
			array(
				'title'       => wp_easycart_language()->get_text( 'account_register', 'account_register_email_title' ),
				'heading'     => wp_easycart_language()->get_text( 'account_register', 'account_register_email_title' ),
				'preheader'   => wp_strip_all_tags( $text ) . ' ' . $email,
				'eyebrow'     => __( 'Store notification', 'wp-easycart' ),
				'button_url'  => admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts' ),
				'button_text' => __( 'View customers', 'wp-easycart' ),
			)
		);
	}
}

if ( ! function_exists( 'wp_easycart_legacy_app_auth_enabled' ) ) {
	function wp_easycart_legacy_app_auth_enabled() {
		return ( '0' !== (string) get_option( 'ec_option_enable_legacy_app_auth', '1' ) );
	}
}

if ( ! function_exists( 'wp_easycart_maintain_admin_password_backup' ) ) {
	function wp_easycart_maintain_admin_password_backup( $user_id, $raw_password ) {
		if ( ! wp_easycart_legacy_app_auth_enabled() ) {
			return;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		global $wpdb;
		$is_admin = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM ec_user LEFT JOIN ec_role ON ( ec_user.user_level = ec_role.role_label ) WHERE ec_user.user_id = %d AND ( ec_user.user_level = 'admin' OR ec_role.admin_access = 1 )",
			$user_id
		) );
		if ( $is_admin ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE ec_user SET password_admin_v1 = %s WHERE user_id = %d",
				md5( (string) $raw_password ),
				$user_id
			) );
		}
	}
}

if ( ! function_exists( 'wp_easycart_maybe_purge_admin_password_backup' ) ) {
	function wp_easycart_maybe_purge_admin_password_backup( $unused, $new_value ) {
		if ( '0' === (string) $new_value || '' === (string) $new_value || false === $new_value ) {
			global $wpdb;
			$wpdb->query( "UPDATE ec_user SET password_admin_v1 = '' WHERE password_admin_v1 != ''" );
		}
	}
}
add_action( 'update_option_ec_option_enable_legacy_app_auth', 'wp_easycart_maybe_purge_admin_password_backup', 10, 2 );
add_action( 'add_option_ec_option_enable_legacy_app_auth', 'wp_easycart_maybe_purge_admin_password_backup', 10, 2 );

require_once( EC_PLUGIN_DIRECTORY . '/inc/ec_config.php' );

add_action( 'init', 'wpeasycart_load_startup', 1 );
add_action( 'plugins_loaded', 'wpeasycart_load_translation', 1 );
add_action( 'widgets_init', 'wpeasycart_register_widgets' );
add_filter( 'upload_mimes', 'wp_easycart_add_allow_uploads_admin', 1, 1 );

function wp_easycart_add_allow_uploads_admin( $mimes ) {
	$mimes['csv'] = 'text/csv';
	$mimes['pdf'] = 'application/pdf';
	$mimes['zip'] = 'application/zip';
	$mimes['gzip'] = 'application/x-gzip';
	return $mimes;
}

function wpeasycart_load_translation() {
	load_plugin_textdomain( 'wp-easycart', '', basename( dirname( __FILE__ ) ) . '/languages' );
}

function wpeasycart_load_startup() {

	ec_setup_hooks();

	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/ec_hooks.php" ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . "/ec_hooks.php" );
	}

	if ( ! is_admin() && get_option( 'ec_option_load_ssl' ) && ! is_ssl() && ( ! defined( 'WP_CLI' ) || ! WP_CLI ) ) {
		$redirect_url = 'https://' . sanitize_text_field( $_SERVER['HTTP_HOST'] ) . sanitize_text_field( $_SERVER['REQUEST_URI'] );
		wp_redirect( $redirect_url, 301 );
		exit;
	}

	if ( version_compare( str_replace( '_', '.', EC_CURRENT_VERSION ), get_option( 'ec_option_db_version_updated' ), '>' ) ) {
		$db_manager = new ec_db_manager();
		$db_manager->try_db_update();
	}

	do_action( 'wp_easycart_startup' );
}

function wpeasycart_register_widgets() {
	register_widget( 'ec_categorywidget' );
	register_widget( 'ec_cartwidget' );
	register_widget( 'ec_colorwidget' );
	register_widget( 'ec_currencywidget' );
	register_widget( 'ec_donationwidget' );
	register_widget( 'ec_groupwidget' );
	register_widget( 'ec_languagewidget' );
	register_widget( 'ec_loginwidget' );
	register_widget( 'ec_manufacturerwidget' );
	register_widget( 'ec_menuwidget' );
	register_widget( 'ec_newsletterwidget' );
	register_widget( 'ec_pricepointwidget' );
	register_widget( 'ec_productwidget' );
	register_widget( 'ec_searchwidget' );
	register_widget( 'ec_specialswidget' );
}

function ec_activate() {

	global $wpdb;

	$wpoptions = new ec_wpoptionset();
	$wpoptions->add_options();
	update_option( 'ec_option_wpoptions_version', EC_CURRENT_VERSION );

	if ( ! get_option( 'ec_option_db_new_version' ) || EC_UPGRADE_DB != get_option( 'ec_option_db_new_version' ) ) {
		$db_manager = new ec_db_manager();
		if ( $db_manager->install_db( true ) ) {
			update_option( 'ec_option_is_installed', '1' );
		}
	}

	$mysqli = new ec_db();

	$site = explode( "://", ec_get_url() );
	$site = $site[1];
	$mysqli->update_url( $site );
	
	$GLOBALS['ec_cart_data'] = new ec_cart_data( ( ( isset( $GLOBALS['ec_cart_id'] ) ) ? $GLOBALS['ec_cart_id'] : 'not-set' ) );
	$GLOBALS['ec_cart_data']->restore_session_from_db();
	wp_easycart_language()->update_language_data(); //Do this to update the database if a new language is added

	update_option( 'ec_option_is_installed', '1' );

	if ( '&#36;' == get_option( 'ec_option_currency' ) ) {
		update_option( 'ec_option_currency', '$' );
	}
	
	if ( ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/' ) ) {

		$to = EC_PLUGIN_DATA_DIRECTORY . '/';
		$from = EC_PLUGIN_DIRECTORY . '/';

		if ( ! is_writable( plugin_dir_path( __FILE__ ) ) ) {
			// We really can't do anything now about the data folder. Lets try and get people to do this in the install page.

		} else {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . "/", 0755 );
			mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/", 0755 );

			wpeasycart_copyr( $from . "products", $to . "products" );
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/banners/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/banners/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/categories/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/categories/", 0751 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/", 0751 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/", 0755 );
			}
			if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/" ) ) {
				mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/", 0751 );
			}

		}
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/custom-theme/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/design/layout/custom-layout/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/banners/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/banners/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/banners/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/categories/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/categories/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/categories/", 0751 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/downloads/", 0751 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics2/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics3/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics4/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics5/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/swatches/", 0755 );
	}

	if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/" ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/" ) ) {
		mkdir( EC_PLUGIN_DATA_DIRECTORY . "/products/uploads/", 0751 );
	}

	if ( class_exists( 'wp_easycart_customer_uploads' ) ) {
		wp_easycart_customer_uploads::protect_all();
	}

	/* 6.0.3: when WP EasyCart was first activated ( usage data's store age and getting-started steps, kept locally ). A store that
	   does not share yet remembers this activation, so it can be counted once the store chooses to share ( access_granted() ). */
	add_option( 'wp_easycart_installed_at', time(), '', false );
	if ( '1' !== (string) get_option( 'ec_option_allow_tracking' ) ) {
		update_option( 'wp_easycart_tracking_activated', time(), false );
	}

	if ( get_option( 'ec_option_allow_tracking' ) && '1' == get_option( 'ec_option_allow_tracking' ) && ! function_exists( 'wp_easycart_admin_tracking' ) ) {
		include( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php' );
	}
	do_action( 'wpeasycart_activated' );
}

function ec_uninstall() {

	$db_manager = new ec_db_manager();
	$db_manager->uninstall_db();

	$wpoptions = new ec_wpoptionset();
	$wpoptions->delete_options();

	$data_dir = EC_PLUGIN_DATA_DIRECTORY . "/";
	if ( is_dir( $data_dir ) && ! is_writable( $data_dir ) ) {
		$ftp_server = sanitize_text_field( $_POST['hostname'] );
		$ftp_user_name = sanitize_text_field( $_POST['username'] );
		$ftp_user_pass = $_POST['password']; // XSS OK. Do not sanitize password.

		$conn_id = ftp_connect( $ftp_server ) or die( esc_attr( 'Couldn\'t connect to ' . $ftp_server ) );

		$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

		if ( !$login_result ) {
			die( "Could not connect to your server via FTP to uninstall your wp-easycart. Please remove the files manually." );

		} else {
			ec_delete_directory_ftp( $conn_id, $data_dir );
		}
	} else {
		ec_recursive_remove_directory( $data_dir );
	}

	$store_posts = get_posts( array( 'post_type' => 'ec_store', 'posts_per_page' => 10000 ) );
	foreach ( $store_posts as $store_post ) {
		wp_delete_post( $store_post->ID, true);
	}

	wp_clear_scheduled_hook( 'wp_easycart_square_renew_token' );

	/* 6.0.3: a store that shares usage data says it was deleted ( never sent before ), without waiting for the answer. */
	if ( '1' === (string) get_option( 'ec_option_allow_tracking' ) && wp_easycart_tracking_load() ) {
		wp_easycart_admin_tracking::instance()->deleted();
	}

	/* 6.0.2: usage data's waiting events, install id and WP-Cron event ( wp_easycart_admin_tracking ). 6.0.3: the check-in, the
	   steps sent and the activation times too. */
	delete_option( 'wp_easycart_tracking_queue' );
	delete_option( 'wp_easycart_tracking_install_id' );
	delete_option( 'wp_easycart_tracking_milestones' );
	delete_option( 'wp_easycart_tracking_activated' );
	delete_option( 'wp_easycart_installed_at' );
	wp_clear_scheduled_hook( 'wp_easycart_tracking_flush' );
	wp_clear_scheduled_hook( 'wp_easycart_tracking_checkin' );
}

function wpeasycart_update_check() {
	if ( ! get_option( 'ec_option_wpoptions_version' ) || get_option( 'ec_option_wpoptions_version' ) != EC_CURRENT_VERSION ) {
		$ec_tracking_from = (string) get_option( 'ec_option_wpoptions_version' );
		$wpoptions = new ec_wpoptionset();
		$wpoptions->add_options();
		wp_easycart_language()->update_language_data();
		update_option( 'ec_option_wpoptions_version', EC_CURRENT_VERSION );
		update_option( 'ec_option_language_sections_rev', '6.0.3-translations' );
		/* 6.0.2: a store that chose to share usage data before sending came back queues its setup once ( catch_up() ). 6.0.3: an
		   update event with a fresh check-in ( not on a first install, which has no version before ). */
		add_option( 'wp_easycart_installed_at', 0, '', false ); /* 0: installed before 6.0.3 recorded it */
		if ( '1' === (string) get_option( 'ec_option_allow_tracking' ) && wp_easycart_tracking_load() ) {
			wp_easycart_admin_tracking::instance()->catch_up();
			if ( '' !== $ec_tracking_from ) {
				wp_easycart_admin_tracking::instance()->updated( $ec_tracking_from, EC_CURRENT_VERSION );
			}
		}
	} else if ( '6.0.3-translations' != get_option( 'ec_option_language_sections_rev' ) ) {
		/* 6.0.2: new phrases reach stores that already merged a 6.0.2 build: the one-page checkout's ( cart_onepage ), then
		   the order payments ones ( documents: pay_paid_label, pay_balance_label, account_pay_balance ), then the Elementor
		   upgrade's: the sections elementor_templates, elementor_product, elementor_product_info, elementor_shop,
		   elementor_checkout and elementor_account, the Connect Order email ( account_connect_order_email ) and its messages
		   ( ec_errors order_claim_invalid / _sign_in / _limit, ec_success order_claim_sent ), the review error
		   ( customer_review: customer_review_save_error ), then the subscription page's message when subscriptions can't be
		   sold ( cart_login: cart_subscription_unavailable ), then fulfillment partners' ( the sections fulfillment,
		   shipping_groups and product_managed ), then the Side Cart's free_shipping_available ( elementor_checkout ), then
		   Place order's busy card ( cart_onepage: placing_title, placing_checking, placing_payment, placing_finishing,
		   placing_slow, placing_hint ), then bug round 11's Elementor phrases ( elementor_product: gallery_thumbs_previous, gallery_thumbs_next,
		   gallery_pause, gallery_resume, price_you_save; elementor_product_info: the share, meta, reviews and read more phrases ), then
		   bug round 14's ( product_page: product_page_restricted_signed_out, product_page_restricted_no_access; account_subscriptions:
		   subscription_details_plan_notice, subscription_details_card_notice; product_managed: product_unavailable; ec_errors:
		   download_unavailable ), then 6.0.3's subscription phrases ( product_details: product_details_subscription_days, _weeks, _years,
		   _month_one; account_subscriptions: subscription_details_interval_notice ), then the subscription reminders' ( subscription_upcoming:
		   renewal_reminder_*; subscription_trial: trial_reminder_* ), then the plan changes' ( account_subscriptions: subscription_change_*;
		   cart_login: subscription_change_existing, subscription_change_existing_link ), then the price moves' ( account_subscriptions:
		   subscription_change_price; subscription_upcoming: price_change_* ), then the free trial's ( account_subscriptions:
		   subscription_details_trial_ends, subscription_details_trial_notice ), then buying more than one ( cart_login:
		   subscription_owned_one, _many, _link_one, _link_many ), then the trial change notes ( account_subscriptions:
		   subscription_change_note_trial, subscription_change_note_trial_end ), then the subscription page's renewal note
		   ( cart_coupons: subscription_coupon_first_payment, subscription_coupon_months, subscription_renewal_plus_tax ), then the
		   subscription page's options notice ( cart_login: subscription_choose_options, subscription_choose_options_link ), then
		   the translations of the phrases 6.0.2 added in English to every language file ( ec_language::refresh_phrase() ).
		   Missing sections and keys are added; an existing phrase changes only while it still reads as the English copy ( or a
		   translation that lost a [token] ), so a store's own wording stays. */
		wp_easycart_language()->update_language_data();
		update_option( 'ec_option_language_sections_rev', '6.0.3-translations' );
	}

	if ( is_admin() && ( ! get_option( 'ec_option_db_new_version' ) || EC_UPGRADE_DB != get_option( 'ec_option_db_new_version' ) ) ) {
		$db_manager = new ec_db_manager();
		if ( $db_manager->install_db() ) {
			update_option( 'ec_option_is_installed', '1' );
		}
	}

	/* 6.0.2: the example values the option set once seeded are cleared once ( the Universal Analytics ID, queued emails with no address ). */
	if ( is_admin() && ! get_option( 'ec_option_placeholders_retired' ) && method_exists( 'ec_wpoptionset', 'retire_placeholders' ) ) {
		ec_wpoptionset::retire_placeholders();
	}

	if ( !get_option( 'ec_option_data_folders_installed' ) || EC_CURRENT_VERSION != get_option( 'ec_option_data_folders_installed' ) ) {

		if ( !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/" ) ) {

			$to = EC_PLUGIN_DATA_DIRECTORY . '/';
			$from = EC_PLUGIN_DIRECTORY . '/';

			if ( ! is_writable( plugin_dir_path( __FILE__ ) ) ) {
				// We really can't do anything now about the data folder. Lets try and get people to do this in the install page.

			} else {
				mkdir( $to, 0755 );
				wpeasycart_copyr( $from . 'products', $to . 'products' );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/custom-theme/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/custom-layout/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/banners/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/categories/', 0751 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/', 0751 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics2/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics3/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics4/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics5/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/swatches/', 0755 );
				mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads/', 0751 );
			}
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/design/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/custom-theme/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/custom-theme/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/custom-theme/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/custom-layout/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/custom-layout/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/custom-layout/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/banners/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/banners/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/banners/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/categories/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/categories/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/categories/', 0751 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/downloads/', 0751 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics2/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics2/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics2/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics3/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics3/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics3/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics4/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics4/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics4/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics5/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics5/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics5/', 0755 );
		}

		if ( ! file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/swatches/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/swatches/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/swatches/', 0755 );
		}

		if ( !file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads/' ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads/' ) ) {
			mkdir( EC_PLUGIN_DATA_DIRECTORY . '/products/uploads/', 0751 );
		}

		update_option( 'ec_option_data_folders_installed', EC_CURRENT_VERSION );
	}

	/* 6.0.0: deny rules in products/uploads ( customer files are served only through the gated download handler ). Runs once per protection version. */
	if ( class_exists( 'wp_easycart_customer_uploads' ) ) {
		wp_easycart_customer_uploads::maybe_protect();
	}

}
add_action( 'plugins_loaded', 'wpeasycart_update_check' );
register_activation_hook( __FILE__, 'ec_activate' );
register_uninstall_hook( __FILE__, 'ec_uninstall' );

function load_ec_pre() {
	$storepageid = get_option('ec_option_storepage');
	$accountpageid = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
	if ( function_exists( 'icl_object_id' ) ) {
		$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		$accountpageid = icl_object_id( $accountpageid, 'page', true, ICL_LANGUAGE_CODE );
	}
	$storepage = get_permalink( $storepageid );
	$accountpage = get_permalink( $accountpageid );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$storepage = $https_class->makeUrlHttps( $storepage );
		$accountpage = $https_class->makeUrlHttps( $accountpage );
	}
	if ( substr_count( $storepage, '?' ) ) {
		$permalinkdivider = "&";
	} else {
		$permalinkdivider = "?";
	}
	if ( isset( $_SERVER['HTTPS'] ) ) {
		$currentpageid = url_to_postid( "https://" . sanitize_text_field( $_SERVER['SERVER_NAME'] ) . sanitize_text_field( $_SERVER['REQUEST_URI'] ) );
	} else {
		$currentpageid = url_to_postid( "http://" . sanitize_text_field( $_SERVER['SERVER_NAME'] ) . sanitize_text_field( $_SERVER['REQUEST_URI'] ) );
	}
	$cartpage = wpeasycart_links()->get_cart_page();
	$cartpage = apply_filters( 'wp_easycart_cart_page_url', $cartpage );

	if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" && isset( $_GET['error_description'] ) && get_option( 'ec_option_payment_third_party' ) == "dwolla_thirdparty" ) {
		$db = new ec_db();
		$db->insert_response( (int) $_GET['order_id'], 1, "Dwolla Third Party", print_r( $_GET, true ) );
		header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $_GET['order_id'], 'ec_error' => 'dwolla_error' ) ) ) );
		die();
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" && get_option( 'ec_option_payment_third_party' ) == "dwolla_thirdparty" && isset( $_GET['signature'] ) && isset( $_GET['checkoutId'] ) && isset( $_GET['amount'] ) ) {

		$dwolla_verification = ec_dwolla_verify_signature( sanitize_text_field( $_GET['signature'] ), sanitize_text_field( $_GET['checkoutId'] ), sanitize_text_field( $_GET['amount'] ) );
		if ( $dwolla_verification ) {
			global $wpdb;
			$db = new ec_db_admin();
			$db->update_order_status( (int) $_GET['order_id'], "10" );

			// send email
			$order_row = $db->get_order_row_admin( (int) $_GET['order_id'] );
			$orderdetails = $db->get_order_details_admin( (int) $_GET['order_id'] );

			/* Update Stock Quantity */
			foreach ( $orderdetails as $orderdetail ) {
				$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
				if ( $product ) {
					if ( $product->use_optionitem_quantity_tracking ) {
						$db->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
					}
					$db->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', (int) $_GET['order_id'] ) );
					$order_log_id = $wpdb->insert_id;
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, (int) $_GET['order_id'], $orderdetail->product_id ) );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, (int) $_GET['order_id'], '-' . $orderdetail->quantity ) );
				}
			}

			$order_display = new ec_orderdisplay( $order_row, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();

			do_action( 'wpeasycart_order_paid', (int) $_GET['order_id'] );

			header( "location: " . esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $_GET['order_id'] ) ) ) );
			die();
		} else {
			$db = new ec_db();
			$db->insert_response( (int) $_GET['order_id'], 1, "Dwolla Third Party", print_r( $_GET, true ) );
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $_GET['order_id'], 'ec_error' => 'dwolla_error' ) ) ) );
			die();
		}
	}

	/* Update the Menu and Product Statistics */
	if ( isset( $_GET['model_number'] ) ) {
		$db = new ec_db();
		$db->update_product_views( sanitize_text_field( $_GET['model_number'] ) );
	} else if ( isset( $_GET['menuid'] ) ) {
		$db = new ec_db();
		$db->update_menu_views( (int) $_GET['menuid'] );	
	} else if ( isset( $_GET['submenuid'] ) ) {
		$db = new ec_db();
		$db->update_submenu_views( (int) $_GET['submenuid'] );	
	} else if ( isset( $_GET['subsubmenuid'] ) ) {
		$db = new ec_db();
		$db->update_subsubmenu_views( (int) $_GET['subsubmenuid'] );	
	}

	/* Cart Form Actions, Process Prior to WP Loading */
	if ( isset( $_POST['ec_cart_form_action'] ) ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( sanitize_key( $_POST['ec_cart_form_action'] ) );
	} else if ( isset( $_GET['ec_cart_action'] ) ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( sanitize_key( $_GET['ec_cart_action'] ) );	
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "3dsecure" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "3dsecure" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "3ds" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "3ds" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "3dsprocess" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "3dsprocess" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "third_party" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "third_party_forward" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "realex_redirect" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "realex_redirect" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "realex_response" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "realex_response" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "process_affirm" ) {
		$ec_cartpage = new ec_cartpage( true );
		$ec_cartpage->process_form_action( "submit_order" );
	} else if ( isset( $_GET['ec_action'] ) && $_GET['ec_action'] == "deconetwork_add_to_cart" ) {
		$ec_cartpage = new ec_cartpage( true );
		$ec_cartpage->process_form_action( "deconetwork_add_to_cart" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" && isset( $_GET['ec_action'] ) && $_GET['ec_action'] == "paymentexpress" ) {
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( "paymentexpress_thirdparty_response" );
	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "nets_return" && isset( $_GET['transactionId'] ) ) {
		global $wpdb;
		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT ec_order.order_id FROM ec_order WHERE ec_order.nets_transaction_id = %s", sanitize_text_field( $_GET['transactionId'] ) ) );

		$nets = new ec_nets();
		$nets->process_payment_final( 
			$order_id, 
			htmlspecialchars( sanitize_text_field( $_GET['transactionId'] ), ENT_QUOTES ), 
			htmlspecialchars( sanitize_text_field( $_GET['responseCode'] ), ENT_QUOTES ) 
		);
	} else if ( isset( $_GET['stripe'] ) && $_GET['stripe'] == 'returning' && isset( $_GET['payment_intent_client_secret'] ) && isset( $_GET['payment_intent'] ) ) {
		$ec_cartpage = new ec_cartpage( true );
		$ec_cartpage->process_form_action( "stripe_redirect_action" );
	}

	/* Account Form Actions, Process Prior to WP Loading */
	if ( isset( $_POST['ec_account_form_action'] ) ) {
		$ec_accountpage = new ec_accountpage();
		$ec_accountpage->process_form_action( sanitize_key( $_POST['ec_account_form_action'] ) );

	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "logout" ) {
		$ec_accountpage = new ec_accountpage();
		$ec_accountpage->process_form_action( "logout" );

	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "print_receipt" ) {
		include( EC_PLUGIN_DIRECTORY . "/inc/scripts/print_receipt.php" );
		die();

	} else if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "activate_account" && isset( $_GET['email'] ) && isset( $_GET['key'] ) ) {
		$db = new ec_db();
		$is_activated = $db->activate_user( sanitize_email( $_GET['email'] ), sanitize_text_field( $_GET['key'] ) );
		if ( $is_activated ) {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_success' => 'activation_success' ) ) ) );
			die();
		} else {
			header( "location: " . esc_url_raw( wpeasycart_links()->get_account_page( 'login', array( 'account_error' => 'activation_error' ) ) ) );
			die();
		}
	}

	if ( isset( $_GET['ec_cart_apply'] ) ) {
		wp_easycart_cart_link::resolve( sanitize_text_field( wp_unslash( $_GET['ec_cart_apply'] ) ), $cartpage, $storepage );
	} else if ( isset( $_GET['ec_add_to_cart'] ) ) {
		global $wpdb;
		wpeasycart_session()->handle_session();
		wp_easycart_apply_query_coupon();

		$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE model_number = %s', sanitize_text_field( $_GET['ec_add_to_cart'] ) ) );
		if ( ! $product || empty( $product->activate_in_store ) ) {
			header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $cartpage ) ) );
			die();
		}
		/* 6.0.2: a store shown as a catalog ( vacation mode ) sells nothing: the link opens the product instead. So does a product
		 * the add to cart rules refuse ( catalog or inquiry mode, login for pricing, another customer role, a store closed to this
		 * shopper; a subscription link follows them too, although it skips the cart ), and a donation, whose amount is chosen on
		 * its page. */
		if ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) || ! empty( $product->is_donation ) || ( function_exists( 'wp_easycart_product_can_add_to_cart' ) && true !== wp_easycart_product_can_add_to_cart( $product ) ) ) {
			header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $storepage . "?model_number=" . rawurlencode( (string) $product->model_number ) ) ) );
			die();
		}

		$db = new ec_db();
		$advanced_options = $GLOBALS['ec_advanced_optionsets']->get_advanced_optionsets( $product->product_id );
		$option_id_1 = $option_id_2 = $option_id_3 = $option_id_4 = $option_id_5 = 0;
		if ( ( ! $product->use_advanced_optionset || $product->use_both_option_types ) && ( $product->option_id_1 != 0 || $product->option_id_2 != 0 || $product->option_id_3 != 0 || $product->option_id_4 != 0 || $product->option_id_5 != 0 ) ) {
			$is_basic_valid = true;
			if ( $product->option_id_1 != 0 ) {
				$option_1 = $GLOBALS['ec_options']->get_option( $product->option_id_1 );
				if ( ! $option_1 ) {
					$is_basic_valid = false;
				}
				if ( '' == $option_1->option_meta['url_var'] || ! isset( $_GET[ $option_1->option_meta['url_var'] ] ) ) {
					$is_basic_valid = false;
				} else {
					$option_id_1 = (int) $_GET[ $option_1->option_meta['url_var'] ];
					$option_item_1_test = $GLOBALS['ec_options']->get_optionitem( $option_id_1 );
					if ( ! $option_item_1_test || $product->option_id_1 != $option_item_1_test->option_id ) {
						$is_basic_valid = false;
					}
				}
			}
			if ( $product->option_id_2 != 0 ) {
				$option_2 = $GLOBALS['ec_options']->get_option( $product->option_id_2 );
				if ( ! $option_2 ) {
					$is_basic_valid = false;
				}
				if ( '' == $option_2->option_meta['url_var'] || ! isset( $_GET[ $option_2->option_meta['url_var'] ] ) ) {
					$is_basic_valid = false;
				} else {
					$option_id_2 = (int) $_GET[ $option_2->option_meta['url_var'] ];
					$option_item_2_test = $GLOBALS['ec_options']->get_optionitem( $option_id_2 );
					if ( ! $option_item_2_test || $product->option_id_2 != $option_item_2_test->option_id ) {
						$is_basic_valid = false;
					}
				}
			}
			if ( $product->option_id_3 != 0 ) {
				$option_3 = $GLOBALS['ec_options']->get_option( $product->option_id_3 );
				if ( ! $option_3 ) {
					$is_basic_valid = false;
				}
				if ( '' == $option_3->option_meta['url_var'] || ! isset( $_GET[ $option_3->option_meta['url_var'] ] ) ) {
					$is_basic_valid = false;
				} else {
					$option_id_3 = (int) $_GET[ $option_3->option_meta['url_var'] ];
					$option_item_3_test = $GLOBALS['ec_options']->get_optionitem( $option_id_3 );
					if ( ! $option_item_3_test || $product->option_id_3 != $option_item_3_test->option_id ) {
						$is_basic_valid = false;
					}
				}
			}
			if ( $product->option_id_4 != 0 ) {
				$option_4 = $GLOBALS['ec_options']->get_option( $product->option_id_4 );
				if ( ! $option_4 ) {
					$is_basic_valid = false;
				}
				if ( '' == $option_4->option_meta['url_var'] || ! isset( $_GET[ $option_4->option_meta['url_var'] ] ) ) {
					$is_basic_valid = false;
				} else {
					$option_id_4 = (int) $_GET[ $option_4->option_meta['url_var'] ];
					$option_item_4_test = $GLOBALS['ec_options']->get_optionitem( $option_id_4 );
					if ( ! $option_item_4_test || $product->option_id_4 != $option_item_4_test->option_id ) {
						$is_basic_valid = false;
					}
				}
			}
			if ( $product->option_id_5 != 0 ) {
				$option_5 = $GLOBALS['ec_options']->get_option( $product->option_id_5 );
				if ( ! $option_5 ) {
					$is_basic_valid = false;
				}
				if ( '' == $option_5->option_meta['url_var'] || ! isset( $_GET[ $option_5->option_meta['url_var'] ] ) ) {
					$is_basic_valid = false;
				} else {
					$option_id_5 = (int) $_GET[ $option_5->option_meta['url_var'] ];
					$option_item_5_test = $GLOBALS['ec_options']->get_optionitem( $option_id_5 );
					if ( ! $option_item_5_test || $product->option_id_5 != $option_item_5_test->option_id ) {
						$is_basic_valid = false;
					}
				}
			}
			if ( ! $is_basic_valid ) {
				header( "location: " . esc_url_raw( $storepage . "?model_number=" . htmlspecialchars( sanitize_text_field( $_GET['ec_add_to_cart'] ), ENT_QUOTES ) ) );
				die();
			}
		}

		if ( $product->use_advanced_optionset || $product->use_both_option_types ) {
			$is_valid = true;
			$valid_types = array( 'text', 'number', 'checkbox', 'combo', 'swatch', 'radio' ); // Limit allowed types
			foreach ( $advanced_options as $advanced_option ) {
				// Required data check for this product/options
				if ( '' == $advanced_option->option_meta['url_var'] || ! isset( $_GET[ $advanced_option->option_meta['url_var'] ] ) ) {
					$is_valid = false;
					break;
				}
				// Limit types that may be used in this format, redirect if product not allowed in this method
				if ( ! in_array( $advanced_option->option_type, $valid_types ) ) {
					$is_valid = false;
					break;
				}
				// 6.0.0 text input rules: a required text option whose value is emptied by its rules sends the shopper to the product.
				if ( 'text' == $advanced_option->option_type && class_exists( 'wp_easycart_text_input_rules' ) && ! is_array( $_GET[ $advanced_option->option_meta['url_var'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public add-to-cart link.
					$text_rule_check = wp_easycart_text_input_rules::validate( sanitize_text_field( wp_unslash( $_GET[ $advanced_option->option_meta['url_var'] ] ) ), $advanced_option ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public add-to-cart link; value is sanitised.
					if ( $text_rule_check['rejected'] ) {
						$is_valid = false;
						break;
					}
				}
				// Validate values are valid, otherwise redirect to product
				if ( 'checkbox' == $advanced_option->option_type ) {
					$selected_optionitems = array();
					if ( is_array( $_GET[ $advanced_option->option_meta['url_var'] ] ) ) {
						foreach ( $_GET[ $advanced_option->option_meta['url_var'] ] as $selected_optionitem ) { // XSS OK. Forced array and each item sanitized.
							$selected_optionitems[] = sanitize_text_field( $selected_optionitem );
						}
					} else {
						$selected_optionitems[] = sanitize_text_field( $_GET[ $advanced_option->option_meta['url_var'] ] );
					}
					$optionitems = $db->get_advanced_optionitems( $advanced_option->option_id );
					foreach ( $selected_optionitems as $selected_optionitem ) {
						$item_found = false;
						for ( $i = 0; $i < count( $optionitems ); $i++ ) {
							if ( $optionitems[ $i ]->optionitem_name == $selected_optionitem ) {
								$item_found = true;
								break;
							}
						}
						if ( ! $item_found ) {
							$is_valid = false;
						}
					}
				} else if ( 'combo' == $advanced_option->option_type || 'swatch' == $advanced_option->option_type || 'radio' == $advanced_option->option_type ) {
					$optionitems = $db->get_advanced_optionitems( $advanced_option->option_id );
					$item_found = false;
					foreach ( $optionitems as $optionitem ) {
						if ( $optionitem->optionitem_name == $_GET[ $advanced_option->option_meta['url_var'] ] ) {
							$item_found = true;
							break;
						}
					}
					if ( ! $item_found ) {
						$is_valid = false;
					}
				}
			}
			if ( ! $is_valid ) {
				header( "location: " . esc_url_raw( $storepage . "?model_number=" . htmlspecialchars( sanitize_text_field( $_GET['ec_add_to_cart'] ), ENT_QUOTES ) ) );
				die();
			}
		}

		// Build the advanced option values BEFORE adding so identical requests
		// can update quantity on the existing cart row instead of inserting a
		// duplicate row.
		$option_vals = array();
		if ( $product->use_advanced_optionset || $product->use_both_option_types ) {
			$grid_quantity = 0;
			foreach ( $advanced_options as $optionset ) {
				if ( 'checkbox' == $optionset->option_type ) {
					$selected_optionitems = array();
					if ( is_array( $_GET[$optionset->option_meta['url_var']] ) ) {
						foreach ( (array) $_GET[ $optionset->option_meta['url_var'] ] as $selected_optionitem ) { // XSS OK. Forced array and each item sanitized.
							$selected_optionitems[] = sanitize_text_field( $selected_optionitem );
						}
					} else {
						$selected_optionitems[] = sanitize_text_field( $_GET[ $optionset->option_meta['url_var'] ] );
					}
					$optionitems = $db->get_advanced_optionitems( $optionset->option_id );
					foreach ( $optionitems as $optionitem ) {
						if ( in_array( $optionitem->optionitem_name, $selected_optionitems ) ) {
							$option_vals[] = array( 
								"option_id" => (int) $optionset->option_id, 
								"option_label" => wp_easycart_escape_html( $optionset->option_label ), 
								"option_name" => sanitize_text_field( $optionset->option_name ), 
								"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
								"option_type" => sanitize_text_field( $optionset->option_type ), 
								"optionitem_id" => (int) $optionitem->optionitem_id, 
								"optionitem_value" => esc_attr( $optionitem->optionitem_name ), 
								"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
							);
						}
					}
				} else if ( 'combo' == $optionset->option_type || 'swatch' == $optionset->option_type || 'radio' == $optionset->option_type ) {
					$optionitems = $db->get_advanced_optionitems( $optionset->option_id );
					foreach ( $optionitems as $optionitem ) {
						if ( $optionitem->optionitem_name == $_GET[$optionset->option_meta['url_var']] ) {
							$option_vals[] = array(
								"option_id" => (int) $optionset->option_id,
								"option_label" => wp_easycart_escape_html( $optionset->option_label ),
								"option_name" => sanitize_text_field( $optionset->option_name ),
								"optionitem_name" => wp_easycart_escape_html( $optionitem->optionitem_name ),
								"option_type" => sanitize_text_field( $optionset->option_type ),
								"optionitem_id" => (int) $optionitem->optionitem_id,
								"optionitem_value" => sanitize_text_field( $optionitem->optionitem_name ),
								"optionitem_model_number" => sanitize_text_field( $optionitem->optionitem_model_number )
							);
						}
					}
				} else {
					$optionitems = $db->get_advanced_optionitems( $optionset->option_id );
					foreach ( $optionitems as $optionitem ) {
						$option_vals[] = array(
							"option_id" => $optionset->option_id,
							"option_label" => wp_easycart_escape_html( $optionset->option_label ),
							"option_name" => $optionitem->option_name,
							"optionitem_name" => $optionitem->optionitem_name,
							"option_type" => $optionitem->option_type,
							"optionitem_id" => $optionitem->optionitem_id,
							"optionitem_value" => ( 'number' == $optionset->option_type ) ? (int) sanitize_text_field( wp_unslash( $_GET[ $optionset->option_meta['url_var'] ] ) ) : esc_attr( class_exists( 'wp_easycart_text_input_rules' ) ? wp_easycart_text_input_rules::validate( sanitize_text_field( wp_unslash( $_GET[ $optionset->option_meta['url_var'] ] ) ), $optionset )['value'] : sanitize_text_field( wp_unslash( $_GET[ $optionset->option_meta['url_var'] ] ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- public add-to-cart link; presence checked in the validation loop above, value sanitised and passed through the text input rules ( 6.0.0 ).
							"optionitem_model_number" => $optionitem->optionitem_model_number,
						);
					}
				}
			}
		}

		$was_merged = false;
		$wpec_before_add = 0;
		if ( $product->is_subscription_item ) {
			$tempcart_id = true;
		} else {
			$wpec_before_add = function_exists( 'wp_easycart_cart_add_snapshot' ) ? wp_easycart_cart_add_snapshot( $product->product_id ) : 0;
			$tempcart_id = $db->quick_add_to_cart( sanitize_text_field( $_GET['ec_add_to_cart'] ), array( $option_id_1, $option_id_2, $option_id_3, $option_id_4, $option_id_5 ), $option_vals, $was_merged );
		}

		if ( $tempcart_id ) {
			/* 6.0.3: a subscription link's choices are made for this product ( wp_easycart_subscription_options ): its basic
			 * choices are kept too ( they were dropped ), and nothing chosen for another subscription stays. */
			if ( $product->is_subscription_item && class_exists( 'wp_easycart_subscription_options' ) ) {
				wp_easycart_subscription_options::claim( $product->product_id );
				$wpec_link_slots = array( 1 => $option_id_1, 2 => $option_id_2, 3 => $option_id_3, 4 => $option_id_4, 5 => $option_id_5 );
				foreach ( $wpec_link_slots as $wpec_slot => $wpec_item ) {
					$GLOBALS['ec_cart_data']->cart_data->{ 'subscription_option' . $wpec_slot } = ( (int) $wpec_item > 0 ) ? (int) $wpec_item : '';
				}
				$GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option = '';
				$GLOBALS['ec_cart_data']->save_session_to_db();
			}
			if ( $product->use_advanced_optionset || $product->use_both_option_types ) {
				if ( $product->is_subscription_item ) {
					$GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option = maybe_serialize( $option_vals );
					$GLOBALS['ec_cart_data']->save_session_to_db();
				} else if ( ! $was_merged ) {
					for ( $i=0; $i<count( $option_vals ); $i++ ) {
						$db->add_option_to_cart( $tempcart_id, $GLOBALS['ec_cart_data']->ec_cart_id, $option_vals[$i] );
					}
				}
			}
			if ( ! $product->is_subscription_item && function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
				wp_easycart_announce_cart_item_added( $tempcart_id, $product->product_id, $wpec_before_add, 'link' ); /* 6.0.2 */
			}

			do_action( 'wpeasycart_cart_updated' ); /* 6.0.2: tax services see the cart change ( the AJAX add-to-cart paths already said so ) */
			if ( $product->is_subscription_item ) {
				/* 6.0.2: a shared subscription buy link keeps its campaign tags too, like the cart redirect below. */
				header( "location: " . esc_url_raw( wp_easycart_with_source_tags( wpeasycart_links()->get_cart_page( 'subscription_info', array( 'subscription' => esc_attr( $product->model_number ) ) ) ) ) );
				die();
			} else {
				/* 6.0.2: a shared buy link keeps its campaign tags on the way to the cart, so the order's source is recorded
				   ( the cart page is skipped as a referrer, so without them it read as Direct ). */
				header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $cartpage ) ) );
				die();
			}
			die();
		} else {
			header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $storepage . "?model_number=" . htmlspecialchars( sanitize_text_field( $_GET['ec_add_to_cart'] ), ENT_QUOTES ) ) ) );
			die();
		}

	} else if ( isset( $_GET['ec_action'] ) && $_GET['ec_action'] == "addtocart" && isset( $_GET['model_number'] ) ) {
		wpeasycart_session()->handle_session();

		$db = new ec_db();
		global $wpdb;
		/* 6.0.2: the product list's Add to cart link ( redirect mode ); its script adds ec_meta_eid to the link. */
		$wpec_add_product_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number = %s AND activate_in_store = 1', sanitize_text_field( wp_unslash( $_GET['model_number'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public add-to-cart link.
		/* 6.0.2: only an active product, and nothing while the store is shown as a catalog ( vacation mode ): the product page opens instead. */
		if ( ! $wpec_add_product_id || apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ) {
			header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $storepage . "?model_number=" . htmlspecialchars( sanitize_text_field( $_GET['model_number'] ), ENT_QUOTES ) ) ) );
			die();
		}
		$wpec_before_add = ( $wpec_add_product_id && function_exists( 'wp_easycart_cart_add_snapshot' ) ) ? wp_easycart_cart_add_snapshot( $wpec_add_product_id ) : 0;
		$tempcart_id = $db->quick_add_to_cart( sanitize_text_field( $_GET['model_number'] ) );
		if ( $tempcart_id ) {
			if ( $wpec_add_product_id && function_exists( 'wp_easycart_announce_cart_item_added' ) ) {
				wp_easycart_announce_cart_item_added( $tempcart_id, $wpec_add_product_id, $wpec_before_add, 'list' );
			}
			$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT ec_tempcart.product_id FROM ec_tempcart WHERE ec_tempcart.tempcart_id = %d", $tempcart_id ) );
			header( "location: " . esc_url_raw( apply_filters( 'wp_easycart_add_to_cart_return_url_cart', wp_easycart_with_source_tags( $cartpage ), $tempcart_id, $product_id ) ) );
			die();
		} else {
			header( "location: " . esc_url_raw( wp_easycart_with_source_tags( $storepage . "?model_number=" . htmlspecialchars( sanitize_text_field( $_GET['model_number'] ), ENT_QUOTES ) ) ) );
			die();
		}
	}

	/* Load abandoned cart (signed link only) */
	if ( isset( $_GET['ec_load_tempcart'] ) && isset( $_GET['ec_load_email'] ) && isset( $_GET['ec_load_key'] ) ) {
		global $wpdb;

		$req_session = sanitize_text_field( wp_unslash( $_GET['ec_load_tempcart'] ) );
		$req_email = sanitize_email( wp_unslash( $_GET['ec_load_email'] ) );
		$req_key = sanitize_text_field( wp_unslash( $_GET['ec_load_key'] ) );

		$expected_key = wpeasycart_session()->get_abandoned_cart_key( $req_session, $req_email );

		if ( hash_equals( $expected_key, $req_key ) ) {
			$tempcart_row = $wpdb->get_row( $wpdb->prepare( "SELECT ec_tempcart.session_id FROM ec_tempcart, ec_tempcart_data WHERE ec_tempcart.session_id = %s AND ec_tempcart_data.session_id = ec_tempcart.session_id AND ec_tempcart_data.email = %s", $req_session, $req_email ) );

			if ( $tempcart_row ) {
				wpeasycart_session()->handle_session( $tempcart_row->session_id );
				wpeasycart_session()->rotate_session_id();

				$cart_page_id = get_option( 'ec_option_cartpage' );
				if ( function_exists( 'icl_object_id' ) ) {
					$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
				}
				$cart_page = get_permalink( $cart_page_id );
				if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
					$https_class = new WordPressHTTPS();
					$cart_page = $https_class->makeUrlHttps( $cart_page );
				}
				wp_redirect( $cart_page );
				die();
			}
		}
	}

	/* Newsletter Form Actions */
	if ( isset( $_POST['ec_newsletter_email'] ) ) {

		if ( isset( $_POST['ec_newsletter_name'] ) )
			$newsletter_name = sanitize_text_field( $_POST['ec_newsletter_name'] );
		else
			$newsletter_name = "";

		if ( filter_var( $_POST['ec_newsletter_email'], FILTER_VALIDATE_EMAIL ) ) {
			$ec_db = new ec_db();
			$ec_db->insert_subscriber( sanitize_email( $_POST['ec_newsletter_email'] ), $newsletter_name, "" );

			// MyMail Hook
			if ( function_exists( 'mailster' ) ) {
				$subscriber_id = mailster('subscribers')->add( array(
					'email' => sanitize_email( $_POST['ec_newsletter_email'] ),
					'name' => $newsletter_name,
					'status' => 1,
				), false );
			}

			do_action( 'wpeasycart_subscriber_added', sanitize_email( $_POST['ec_newsletter_email'] ), $newsletter_name );
		}
		setcookie( 'ec_newsletter_popup', 'hide', time() + ( 10 * 365 * 24 * 60 * 60 ), defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
	}

	/* Manual Hide Video */
	if ( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['ec_admin_action'] ) && $_GET['ec_admin_action'] == "hide-video" ) {
		update_option( 'ec_option_hide_design_help_video', '1' );
	}

	// END STATS AND FORM PROCESSING

} // CLOSE PRE FUNCTION

function ec_custom_headers() {
	if ( isset( $_GET['order_id'] ) && isset( $_GET['orderdetail_id'] ) && isset( $_GET['download_id'] ) && $GLOBALS['ec_cart_data']->cart_data->user_id != "" ) {
		$mysqli = new ec_db();
		$orderdetail_row = $mysqli->get_orderdetail_row( (int) $_GET['order_id'], (int) $_GET['orderdetail_id'], $GLOBALS['ec_cart_data']->cart_data->user_id );
		/*
		 * 6.0.2: a download only from a line of the customer's own order ( no line built an empty one ), from a paid order ( a
		 * refunded or cancelled order has none, a partial refund keeps them ) and not for a line whose options switch it off:
		 * the rule the account pages show the link by. A refused download goes back to the order with a note, not a blank page.
		 */
		if ( ! $orderdetail_row || ( class_exists( 'wp_easycart_storefront_access' ) && ! wp_easycart_storefront_access::download_allowed( $orderdetail_row ) ) ) {
			$wpec_download_back = ( $orderdetail_row ) ? wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $orderdetail_row->order_id, 'account_error' => 'download_unavailable' ) ) : wpeasycart_links()->get_account_page( 'dashboard', array( 'account_error' => 'download_unavailable' ) );
			wp_safe_redirect( $wpec_download_back );
			exit;
		}
		if ( $orderdetail_row && function_exists( 'wp_easycart_log_user_activity' ) ) {
			wp_easycart_log_user_activity( (int) $GLOBALS['ec_cart_data']->cart_data->user_id, 'download', array(
				'object_type' => 'download',
				'object_id'   => (int) $_GET['download_id'],
				'meta'        => array(
					'order_id' => (int) $_GET['order_id'],
					'title'    => isset( $orderdetail_row->title ) ? $orderdetail_row->title : '',
				),
				'actor_type'  => 'customer',
			) );
		}
		$ec_orderdetail = new ec_orderdetail( $orderdetail_row, 1 );
	}

	if ( !get_option( 'ec_option_cache_prevent' ) && (
			( 
				isset( $_GET['ec_page'] ) && 
				( 
					$_GET['ec_page'] == "checkout_payment" || $_GET['ec_page'] == "checkout_shipping" || $_GET['ec_page'] == "checkout_info"
				)
			) || (
				get_option( 'ec_option_cartpage' ) == get_the_ID()
			) || (
				apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) ) == get_the_ID()
			)
		)
	) {
		header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
		header('Pragma: no-cache'); // HTTP 1.0.
		header('Expires: 0'); // Proxies.
	}
}

function wpeasycart_prevent_iframe() {
	global $is_wpec_cart, $is_wpec_account;
	if ( $is_wpec_cart || $is_wpec_account ) {
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
}
add_action( 'wp', 'wpeasycart_prevent_iframe' );

function ec_css_loader_v3() {
	if ( apply_filters( 'wp_easycart_load_css_scripts', true ) ) {
		$pageURL = 'http';
		if ( isset( $_SERVER["HTTPS"] ) )
			$pageURL .= "s";

		if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/live-editor.css' ) ) {
				wp_register_style( 'wpeasycart_admin_css', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/live-editor.css', EC_PLUGIN_DATA_DIRECTORY ) );
			} else {
				wp_register_style( 'wpeasycart_admin_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/live-editor.css', EC_PLUGIN_DIRECTORY ) );
			}
			wp_enqueue_style( 'wpeasycart_admin_css' );
		}

		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.css' ) ) {
			wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.css', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
		} else if( get_option( 'ec_option_enabled_minified_scripts' ) ) {
			wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/ec-store.min.css', EC_PLUGIN_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
		} else {
			wp_register_style( 'wpeasycart_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/ec-store.css', EC_PLUGIN_DIRECTORY ), array( 'jquery-ui' ), EC_CURRENT_VERSION );
		}
		wp_enqueue_style( 'wpeasycart_css' );
		/* 6.0.0: native reviews ( rating summary, distribution bars, verified badge, store replies ). Ships in the plugin theme only, so theme copies of ec-store.css keep it. */
		wp_register_style( 'wpeasycart_reviews_css', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec_reviews_native.css', EC_PLUGIN_DIRECTORY ), array( 'wpeasycart_css' ), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wpeasycart_reviews_css' );

		$gfont_string = "://fonts.googleapis.com/css?family=Lato|Monda|Open+Sans|Droid+Serif";
		if ( get_option( 'ec_option_font_main' ) ) {
			$gfont_string .= "|" . str_replace( " ", "+", get_option( 'ec_option_font_main' ) );
		}
		wp_register_style( "wpeasycart_gfont", $pageURL . $gfont_string );
		wp_enqueue_style( 'wpeasycart_gfont' );

		if ( get_option( 'ec_option_use_rtl' ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/rtl_support.css' ) ) {
				wp_register_style( 'wpeasycart_rtl_css', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/rtl_support.css', EC_PLUGIN_DATA_DIRECTORY ) );
			} else {
				wp_register_style( 'wpeasycart_rtl_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/rtl_support.css', EC_PLUGIN_DIRECTORY ) );
			}
			wp_enqueue_style( 'wpeasycart_rtl_css' );
		}

		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/smoothness-jquery-ui.min.css' ) ) {
			wp_register_style( 'jquery-ui', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/smoothness-jquery-ui.min.css', EC_PLUGIN_DATA_DIRECTORY ) );
		} else {
			wp_register_style( 'jquery-ui', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/smoothness-jquery-ui.min.css', EC_PLUGIN_DIRECTORY ) );
		}
	}
}

function ec_js_loader_v3() {
	if ( apply_filters( 'wp_easycart_load_js_scripts', true ) ) {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/live-editor.js' ) ) {
				wp_enqueue_script( 'wpeasycart_admin_js', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/live-editor.js', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery', 'jquery-ui-core' ), EC_CURRENT_VERSION );
			} else {
				wp_enqueue_script( 'wpeasycart_admin_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/live-editor.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'jquery-ui-core' ), EC_CURRENT_VERSION );
			}
		}

		$dependency_list = array( 'jquery', 'jquery-ui-core' );
		if ( ! get_option( 'ec_option_exclude_accordion' ) ) {
			$dependency_list[] = 'jquery-ui-accordion';
		}
		if ( ! get_option( 'ec_option_exclude_datepicker' ) ) {
			$dependency_list[] = 'jquery-ui-datepicker';
		}
		/*
		 * Default option selections ( 6.0.0 ). Separate file so theme copies of ec-store.js and the
		 * minified build both get it, and loaded ahead of ec-store.js so it can read what the
		 * template rendered before the swatch init routine rewrites it.
		 */
		wp_enqueue_script( 'wpeasycart_option_defaults_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-option-defaults.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.js' ) ) {
			wp_enqueue_script( 'wpeasycart_js', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/ec-store.js', EC_PLUGIN_DATA_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
		} else if( get_option( 'ec_option_enabled_minified_scripts' ) ) {
			wp_enqueue_script( 'wpeasycart_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/ec-store.min.js', EC_PLUGIN_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
		} else {
			wp_enqueue_script( 'wpeasycart_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/ec-store.js', EC_PLUGIN_DIRECTORY ), $dependency_list, EC_CURRENT_VERSION, false );
		}
		/*
		 * One-page checkout Place order ( 6.0.2 ). Separate file so theme copies of ec-store.js and the minified build
		 * both get it; it wraps an older ec_validate_submit_order() when it finds one.
		 */
		if ( wp_easycart_onepage_active() ) {
			wp_enqueue_script( 'wpeasycart_onepage_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-checkout-onepage.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'wpeasycart_js' ), EC_CURRENT_VERSION, false );
		}

		/*
		 * Text / textarea modifier input rules ( 6.0.0 ). Separate file so theme copies of ec-store.js don't need updating.
		 * Footer: it only delegates document events and boots on DOMContentLoaded; ec-store.js reads window.wpeasycart_text_input_rules at add-to-cart time.
		 */
		wp_enqueue_script( 'wpeasycart_text_input_rules_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-text-input-rules.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );

		/*
		 * Account subscription panels ( 6.0.0 ). Separate file, loaded after ec-store.js, so theme copies of ec-store.js still get it.
		 * Footer: the templates reach its globals from onclick handlers only, with ec-store.js's own versions as the fallback. Not gated to the
		 * account page because the Elementor account dashboard widget renders subscriptions without the [ec_account] shortcode that sets $is_wpec_account.
		 */
		wp_enqueue_script( 'wpeasycart_account_subscriptions_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-account-subscriptions.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'wpeasycart_js' ), EC_CURRENT_VERSION, true );
		/* Native reviews ( 6.0.0 ): rating-bar filter and the review-request landing. Footer; plain DOM, no jQuery dependency. */
		wp_enqueue_script( 'wpeasycart_reviews_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec_reviews_native.js', EC_PLUGIN_DIRECTORY ), array( 'wpeasycart_js' ), EC_CURRENT_VERSION, true );

		wp_enqueue_script( 'wpeasycart_owl_carousel_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/owl.carousel.min.js', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
		wp_register_style( 'wpeasycart_owl_carousel_css', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/owl.carousel.css', EC_PLUGIN_DIRECTORY ) );
		wp_enqueue_style( 'wpeasycart_owl_carousel_css' );
	}
}

function wp_easycart_load_cart_js() {
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/jquery.payment.min.js' ) ) {
		wp_enqueue_script( 'payment_jquery_js', plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/jquery.payment.min.js', EC_PLUGIN_DATA_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
	} else {
		wp_enqueue_script( 'payment_jquery_js', plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/jquery.payment.min.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, false );
	}

	if ( get_option( 'ec_option_payment_process_method' ) == "square" ) {
		wp_enqueue_script( 'wpeasycart_square_js', ( ( get_option( 'ec_option_square_is_sandbox' ) ) ? 'https://sandbox.web.squarecdn.com/v1/square.js' : 'https://web.squarecdn.com/v1/square.js' ), array(), EC_CURRENT_VERSION, false );
		add_filter( 'sgo_js_async_exclude', 'wp_easycart_exclude_from_siteground', 10, 1 );
	}

	if ( get_option( 'ec_option_payment_process_method' ) == "stripe" || get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) {
		wp_enqueue_script( 'wpeasycart_stripe_js', 'https://js.stripe.com/v3/', array(), EC_CURRENT_VERSION, false );
		add_filter( 'sgo_js_async_exclude', 'wp_easycart_exclude_from_siteground', 10, 1 );
	}

	if ( get_option( 'ec_option_payment_process_method' ) == "eway" && get_option( 'ec_option_eway_use_rapid_pay' ) ) {
		wp_enqueue_script( 'wpeasycart_eway_js', 'https://secure.ewaypayments.com/scripts/eCrypt.min.js', array(), EC_CURRENT_VERSION, false );
		add_filter( 'sgo_js_async_exclude', 'wp_easycart_exclude_from_siteground', 10, 1 );
	}

	if ( get_option( 'ec_option_payment_third_party' ) == "paypal" && ( get_option( 'ec_option_paypal_enable_credit' ) == '1' || get_option( 'ec_option_paypal_enable_pay_now' ) == '1' ) ) {
		if ( get_option( 'ec_option_paypal_use_sandbox' ) == '1' ) {
			if ( get_option( 'ec_option_paypal_sandbox_merchant_id' ) != '' ) {
				// APP ID NOT PUBLIC OR SECRET KEY! THIS TELLS PAYPAL THE PARTER THE MERCHANT IS PROCESSING WITH. MERCHANT DESCRIBED BELOW, WHICH IS SPECIFIC TO THE MERCHANT. THEY HAVE CONNECTED WITH THE WP EasyCart PAYPAL APP. CANNOT USE ONE WITHOUT THE OTHER. THIS WAS CREATED WITH PAYPAL IN ORDER TO ALLOW FOR QUICK ONBOARDING, WITHOUT PROGRAMMING EXPERIENCE AND PAYPAL
				// For more information: https://developer.paypal.com/docs/platforms/seller-onboarding/
				$client_id = 'Acet2ZT0h9IALSY-n76aGnnjCYp3E3myqcmrJ7tfqJiLUvLzXKQMabHN9uLr2W_N03txVHuvkpsQDwhw';
			} else {
				// THIS IS FOR THOSE THAT TAKE THE TIME TO CREATE THEIR OWN PAYPAL APP, NOT THE PUBLIC WP EASYCART APP
				$client_id = get_option( 'ec_option_paypal_sandbox_app_id' );
			}
		}
		if ( get_option( 'ec_option_paypal_use_sandbox' ) == '0' ) {
			if ( get_option( 'ec_option_paypal_production_merchant_id' ) != '' ) {
				// APP ID NOT PUBLIC OR SECRET KEY! THIS TELLS PAYPAL THE PARTER THE MERCHANT IS PROCESSING WITH. MERCHANT DESCRIBED BELOW, WHICH IS SPECIFIC TO THE MERCHANT. THEY HAVE CONNECTED WITH THE WP EasyCart PAYPAL APP. CANNOT USE ONE WITHOUT THE OTHER. THIS WAS CREATED WITH PAYPAL IN ORDER TO ALLOW FOR QUICK ONBOARDING, WITHOUT PROGRAMMING EXPERIENCE AND PAYPAL
				// For more information: https://developer.paypal.com/docs/platforms/seller-onboarding/
				$client_id = 'AXLwqGbEI4j2xLhSOPgUhJYNQkkooPmPUWH9NDIVUZ7PxY6yKPYGrBCELYlSdTSepUaVb_r_M0IdPSJa';
			} else {
				// THIS IS FOR THOSE THAT TAKE THE TIME TO CREATE THEIR OWN PAYPAL APP, NOT THE PUBLIC WP EASYCART APP
				$client_id = get_option( 'ec_option_paypal_production_app_id' );
			}
		}
		$merchant_id = '';
		if( ( get_option( 'ec_option_paypal_use_sandbox' ) == '1' && get_option( 'ec_option_paypal_sandbox_merchant_id' ) != '' ) ) {
			$merchant_id = get_option( 'ec_option_paypal_sandbox_merchant_id' );
		} else if ( get_option( 'ec_option_paypal_use_sandbox' ) == '0' && get_option( 'ec_option_paypal_production_merchant_id' ) != '' ) {
			$merchant_id = get_option( 'ec_option_paypal_production_merchant_id' );
		}
		$disable_funding = '';
		$disable_funding_options = array();
		$enable_funding = '';
		$enable_funding_options = array();
		if( ! apply_filters( 'wp_easycart_allow_paypal_express', false ) || ! get_option( 'ec_option_paypal_use_venmo' ) ){
			$disable_funding_options[] = 'venmo';
		} else {
			$enable_funding_options[] = 'venmo';
		}
		if ( ! apply_filters( 'wp_easycart_allow_paypal_express', false ) || ! get_option( 'ec_option_paypal_use_paylater' ) ) { 
			$disable_funding_options[] = 'paylater'; 
		}
		if ( ! apply_filters( 'wp_easycart_allow_paypal_express', false ) || ! get_option( 'ec_option_paypal_use_card' ) ) { 
			$disable_funding_options[] = 'card'; 
		}
		if ( count( $enable_funding_options ) > 0 ) {
			$enable_funding = '&enable-funding=' . implode( ',', $enable_funding_options );
		}
		if ( count ( $disable_funding_options ) > 0 ) {
			$disable_funding = '&disable-funding=' . implode( ',', $disable_funding_options );
		}
		$paypal_currency = ( get_option( 'ec_option_paypal_use_selected_currency' ) && isset( $_COOKIE['ec_convert_to'] ) ) ? substr( preg_replace( '/[^A-Z]/', '', strtoupper( sanitize_text_field( $_COOKIE['ec_convert_to'] ) ) ), 0, 3 ) : get_option( 'ec_option_paypal_currency_code' );
		wp_enqueue_script( 'wpeasycart_paypal_js', 'https://www.paypal.com/sdk/js?client-id=' . esc_attr( $client_id ) . ( ( '' != $merchant_id ) ? '&merchant-id=' . $merchant_id : '' ) . $disable_funding . $enable_funding . '&currency=' . esc_attr( $paypal_currency ), array(), null, false );
		add_filter( 'sgo_js_async_exclude', 'wp_easycart_exclude_from_siteground', 10, 1 );
	}

	if ( get_option( 'ec_option_payment_process_method' ) == "braintree" && ( wp_easycart_onepage_active() || ( isset( $_GET['ec_page'] ) && ( $_GET['ec_page'] == 'checkout_payment' || $_GET['ec_page'] == 'subscription_info' ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which page this is; nothing is changed.
		wp_enqueue_script( 'wpeasycart_braintree_js', 'https://js.braintreegateway.com/web/dropin/1.13.0/js/dropin.min.js', array(), EC_CURRENT_VERSION, false );
	}

	if ( wp_easycart_recaptcha_ready() ) {
		wp_enqueue_script( 'wpeasycart_google_recaptcha_js', 'https://www.google.com/recaptcha/api.js?onload=wpeasycart_recaptcha_onload&render=explicit', array(), EC_CURRENT_VERSION, false );
	}

	/* 6.0.2 checkout protection: pauses and the human check on the payment step ( the check provider's own script
	   loads only when a check is needed ). */
	if ( class_exists( 'wp_easycart_checkout_guard' ) ) {
		wp_easycart_checkout_guard::enqueue();
	}

	// 6.0.2: Place order's busy state, both checkouts ( ec-checkout-busy.js ): the checkout locked under a status card from the
	// click until the page moves on. A file of its own, so theme copies of ec-store.js and the minified build get it too.
	if ( ! wp_script_is( 'wpeasycart_checkout_busy_js', 'enqueued' ) ) {
		wp_enqueue_script( 'wpeasycart_checkout_busy_js', plugins_url( 'wp-easycart/design/theme/base-responsive-v3/ec-checkout-busy.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );
		$wpec_busy_text = array();
		foreach ( array( 'title', 'checking', 'payment', 'finishing', 'slow', 'hint' ) as $wpec_busy_key ) {
			$wpec_busy_text[ $wpec_busy_key ] = wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'cart_onepage', 'placing_' . $wpec_busy_key ) );
		}
		wp_localize_script( 'wpeasycart_checkout_busy_js', 'wpeasycart_checkout_busy_text', $wpec_busy_text );
	}
}

if ( ! function_exists( 'wp_easycart_recaptcha_fingerprint' ) ) {
	/**
	 * Which reCAPTCHA key pair is saved ( Settings › Accounts ), so a passed test is tied to the keys it tested.
	 *
	 * @since 6.0.3
	 * @param string|null $site_key   Site key, or null for the saved one.
	 * @param string|null $secret_key Secret key, or null for the saved one.
	 * @return string '' when either key is empty.
	 */
	function wp_easycart_recaptcha_fingerprint( $site_key = null, $secret_key = null ) {
		$site   = trim( (string) ( null === $site_key ? get_option( 'ec_option_recaptcha_site_key' ) : $site_key ) );
		$secret = trim( (string) ( null === $secret_key ? get_option( 'ec_option_recaptcha_secret_key' ) : $secret_key ) );
		return ( '' === $site || '' === $secret ) ? '' : md5( $site . '|' . $secret );
	}
}

if ( ! function_exists( 'wp_easycart_recaptcha_test_record' ) ) {
	/**
	 * The last reCAPTCHA key test ( option ec_option_recaptcha_verified: fingerprint, at, host, source test | existing ).
	 * A store that already had reCAPTCHA switched on with both keys before 6.0.3 counts as tested ( source existing ), once,
	 * so updating never turns its protection off; new or changed keys need a test ( the key saves clear the record ).
	 *
	 * @since 6.0.3
	 * @return array Empty when no test has passed.
	 */
	function wp_easycart_recaptcha_test_record() {
		$record = get_option( 'ec_option_recaptcha_verified', false );
		if ( false === $record ) {
			$fingerprint = wp_easycart_recaptcha_fingerprint();
			$record      = ( '' !== $fingerprint && get_option( 'ec_option_enable_recaptcha' ) ) ? array(
				'fingerprint' => $fingerprint,
				'at'          => 0,
				'host'        => '',
				'source'      => 'existing',
			) : array();
			update_option( 'ec_option_recaptcha_verified', $record, false );
		}
		return is_array( $record ) ? $record : array();
	}
}

if ( ! function_exists( 'wp_easycart_recaptcha_verified' ) ) {
	/**
	 * Have the saved reCAPTCHA keys passed a test ( Settings › Accounts › Test keys )?
	 *
	 * @since 6.0.3
	 * @return bool
	 */
	function wp_easycart_recaptcha_verified() {
		$fingerprint = wp_easycart_recaptcha_fingerprint();
		if ( '' === $fingerprint ) {
			return false;
		}
		$record = wp_easycart_recaptcha_test_record();
		return isset( $record['fingerprint'] ) && hash_equals( (string) $record['fingerprint'], $fingerprint );
	}
}

if ( ! function_exists( 'wp_easycart_recaptcha_ready' ) ) {
	/**
	 * Is reCAPTCHA on and able to work: switched on, with both its site key and its secret key saved ( Settings ›
	 * Accounts )? Every form that shows the widget and every server check asks this, so a switch turned on before
	 * the keys were entered no longer refuses every sign-in and sign-up with a widget nobody could see.
	 *
	 * @since 6.0.2
	 * @since 6.0.3 the saved keys must also have passed a test ( wp_easycart_recaptcha_verified() ): keys that don't work
	 *              never reach a form, where they would lock shoppers out of signing in.
	 * @param string $place '' ( account forms, stock alerts, subscriptions ) or 'cart' ( also needs the checkout switch ).
	 * @return bool
	 */
	function wp_easycart_recaptcha_ready( $place = '' ) {
		$ready = get_option( 'ec_option_enable_recaptcha' )
			&& '' !== trim( (string) get_option( 'ec_option_recaptcha_site_key' ) )
			&& '' !== trim( (string) get_option( 'ec_option_recaptcha_secret_key' ) )
			&& ( 'cart' !== $place || get_option( 'ec_option_enable_recaptcha_cart' ) )
			&& wp_easycart_recaptcha_verified();
		/**
		 * Filters whether reCAPTCHA is shown and checked.
		 *
		 * @since 6.0.2
		 * @param bool   $ready Switched on with both keys saved ( 6.0.3: and tested ).
		 * @param string $place '' or 'cart'.
		 */
		return (bool) apply_filters( 'wp_easycart_recaptcha_ready', (bool) $ready, (string) $place );
	}
}

function wp_easycart_load_grecaptcha_js() {
	if ( wp_easycart_recaptcha_ready() ) {
		wp_enqueue_script( 'wpeasycart_google_recaptcha_js', 'https://www.google.com/recaptcha/api.js?onload=wpeasycart_recaptcha_onload&render=explicit', array(), EC_CURRENT_VERSION, false );
	}
}

/**
 * Registers Google's reCAPTCHA script ( 6.0.2 ), so the Elementor login and register forms can enqueue it wherever
 * Elementor draws them ( a late enqueue prints in the footer ). It was only enqueued on pages whose content holds
 * [ec_account], so with reCAPTCHA on nobody could sign in or register through those forms. Enqueued as before by the
 * functions above.
 */
function wp_easycart_register_grecaptcha_js() {
	if ( wp_easycart_recaptcha_ready() ) {
		wp_register_script( 'wpeasycart_google_recaptcha_js', 'https://www.google.com/recaptcha/api.js?onload=wpeasycart_recaptcha_onload&render=explicit', array(), EC_CURRENT_VERSION, false );
	}
}
add_action( 'wp_enqueue_scripts', 'wp_easycart_register_grecaptcha_js', 1 );

/**
 * Pages built with the Elementor account widgets ( 6.0.2 ).
 *
 * Those widgets print the signed-in customer's details, orders and nonces on the page ( they never render through AJAX
 * like the [ec_account] shortcode on cache-prevent stores ), so the page is never cached ( DONOTCACHEPAGE / DONOTCDN and
 * no-cache headers, whatever the cache-prevent setting ) and never framed ( X-Frame-Options ), like the account page. The
 * widgets also set the constants when they render, for theme builder templates this check cannot see; their login and
 * register forms enqueue the reCAPTCHA script themselves.
 */
function wp_easycart_elementor_account_page_headers() {
	if ( ! apply_filters( 'wp_easycart_enable_elementor_account_elements', false ) || ! is_singular() ) {
		return;
	}
	$post_id = (int) get_queried_object_id();
	if ( $post_id <= 0 || 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		return;
	}
	$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! is_string( $elementor_data ) || false === strpos( $elementor_data, '"widgetType":"wp_easycart_account_' ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
	}
	if ( ! defined( 'DONOTCDN' ) ) {
		define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
	}
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
}
add_action( 'template_redirect', 'wp_easycart_elementor_account_page_headers' );

/**
 * Opens a Connect Order link on whatever page it points to, before the page draws ( 6.0.2,
 * ec_accountpage::process_order_claim(), which checks the link's signature, expiry and the signed-in account ).
 */
function wp_easycart_account_claim_link() {
	if ( isset( $_GET['ec_claim_sig'] ) && class_exists( 'ec_accountpage' ) && method_exists( 'ec_accountpage', 'process_order_claim' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the link is HMAC-signed and checked by ec_accountpage::process_order_claim().
		ec_accountpage::process_order_claim();
	}
}
add_action( 'template_redirect', 'wp_easycart_account_claim_link', 1 );

function wp_easycart_exclude_from_siteground( $list ) {
	$list[] = 'wpeasycart_stripe_js';
	$list[] = 'wpeasycart_square_js';
	$list[] = 'wpeasycart_paypal_js';
	$list[] = 'wpeasycart_eway_js';
	$list[] = 'wpeasycart_amazonpay_js';
	return $list;
}

function ec_load_css() {

	ec_css_loader_v3();

}	

function ec_load_js() {

	ec_js_loader_v3();

	$https_link = "";
	if ( class_exists( "WordPressHTTPS" ) ) {
		$https_class = new WordPressHTTPS();
		$https_link = $https_class->makeUrlHttps( admin_url( 'admin-ajax.php' ) );
	} else {
		$https_link = str_replace( "http://", "https://", admin_url( 'admin-ajax.php' ) );
	}

	if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		$current_language = ICL_LANGUAGE_CODE;
	} else {
		$current_language = wp_easycart_language()->get_language_code();
	}

	$ajax_array = array(
		'ga4_id' => esc_attr( get_option( 'ec_option_google_ga4_property_id' ) ),
		'ga4_conv_id' => esc_attr( get_option( 'ec_option_google_adwords_tag_id' ) ),
		'ga4_currency' => wp_easycart_base_currency_code(), /* 6.0.2: GA4 amounts are in the base currency, never the shopper's display currency */
		'ajax_url' => ( ( isset( $_SERVER['HTTPS'] ) && 'on' == $_SERVER["HTTPS"] ) ? $https_link : admin_url( 'admin-ajax.php' ) ),
		'current_language' => $current_language,
		'location_id' => (int) $GLOBALS['ec_cart_data']->cart_data->pickup_location,
		/* 6.0.2: Google consent mode ( Settings › Integrations › Cookie consent ); ec-store.js asks the WP Consent API itself when it can. */
		'consent_mode' => class_exists( 'wp_easycart_consent' ) ? wp_easycart_consent::mode() : ( ( 'wp_consent_api' === get_option( 'ec_option_marketing_consent', 'off' ) ) ? 'wp_consent_api' : 'off' ),
		'consent_marketing' => ( ! function_exists( 'wp_easycart_has_marketing_consent' ) || wp_easycart_has_marketing_consent( 'marketing' ) ) ? '1' : '0',
		'consent_statistics' => ( ! function_exists( 'wp_easycart_has_marketing_consent' ) || wp_easycart_has_marketing_consent( 'statistics' ) ) ? '1' : '0',
		/* 6.0.3: My Account's Change plan previews a change and confirms it ( wp_easycart_subscription_changes, ec-account-subscriptions.js ). */
		'subscription_changes' => ( class_exists( 'wp_easycart_subscription_changes' ) && wp_easycart_subscription_changes::active() ) ? '1' : '0',
	);
	wp_localize_script( 'wpeasycart_js', 'wpeasycart_ajax_object', $ajax_array );
}

if ( ! function_exists( 'wp_easycart_hook_url' ) ) {
	/**
	 * The address a payment gateway notifies ( ?wpeasycarthook=paypal-webhook, stripe-webhook … ). A site installed in a folder
	 * gets a slash before the query ( https://example.com/shop/?wpeasycarthook=… ): the web server answers the folder's
	 * address without it with a redirect, and gateways and WP EasyCart Connect never follow one. A site at the root keeps the
	 * address it always had ( https://example.com?wpeasycarthook=… ), which its gateway and Connect registrations carry.
	 *
	 * @since 6.0.2
	 * @param string $hook The hook ( paypal-webhook, stripe-webhook, redsys-webhook, sagepay-webhook ).
	 * @return string
	 */
	function wp_easycart_hook_url( $hook ) {
		$base = get_site_url();
		if ( '' !== trim( (string) wp_parse_url( $base, PHP_URL_PATH ), '/' ) ) {
			$base = trailingslashit( $base );
		}
		return apply_filters( 'wp_easycart_hook_url', $base . '?wpeasycarthook=' . rawurlencode( (string) $hook ), $hook );
	}
}

if ( ! function_exists( 'wp_easycart_with_source_tags' ) ) {
	/**
	 * A redirect address with this request's campaign tags and ad click IDs ( wp_easycart_order_source::request_tags() ), so a
	 * shared buy link or add to cart link keeps the order's source on the way to the cart ( the cart page is skipped as a
	 * referrer, so without them it read as Direct ).
	 *
	 * @since 6.0.2
	 * @param string $url Where the request goes next.
	 * @return string
	 */
	function wp_easycart_with_source_tags( $url ) {
		if ( class_exists( 'wp_easycart_order_source' ) && method_exists( 'wp_easycart_order_source', 'request_tags' ) ) {
			$tags = wp_easycart_order_source::request_tags();
			if ( $tags ) {
				$url = add_query_arg( $tags, $url );
			}
		}
		return $url;
	}
}

function wpeasycart_seo_tags() {

	global $wp_query;
	global $wpdb;

	/* 6.0.2: with an SEO plugin active and the hand-off on ( Settings › Search & AI ), the plugin prints the description and
	   social tags, and wp_easycart_product_schema hands it the product's own. Otherwise EasyCart prints them. */
	$print_meta   = ! ( class_exists( 'wp_easycart_product_schema' ) && wp_easycart_product_schema::seo_handoff() );
	$model_number = '';
	$menu_level   = 0;
	$menu_id      = 0;
	$own_desc     = false;

	/* Check for Post Content Shortcodes */
	$post_obj = $wp_query->get_queried_object();
	if ( $post_obj && isset( $post_obj->post_content ) && false !== strpos( (string) $post_obj->post_content, '[ec_store' ) ) {
		$matches = array();
		if ( preg_match( '/\[ec_store\s+modelnumber="([^"]+)"/', $post_obj->post_content, $matches ) ) {
			$model_number = $matches[1];
		} else if ( preg_match( '/\[ec_store\s+(subsubmenuid|submenuid|menuid)="(\d+)"/', $post_obj->post_content, $matches ) ) {
			$levels     = array( 'menuid' => 1, 'submenuid' => 2, 'subsubmenuid' => 3 );
			$menu_level = $levels[ $matches[1] ];
			$menu_id    = (int) $matches[2];
		}
		/* A Yoast description written for this page wins over EasyCart's. */
		$own_desc = class_exists( 'WPSEO_Options' ) && isset( $post_obj->ID ) && '' !== (string) get_post_meta( $post_obj->ID, '_yoast_wpseo_metadesc', true );
	}

	/* Check for GET VARS. 6.0.2: also on the store page itself ( a bare [ec_store] with ?model_number= / ?menuid= links, plain
	   permalinks or the old link style ): the shortcode check above used to shut this out, losing every meta and Open Graph tag. */
	if ( '' === $model_number && 0 === $menu_level ) {
		if ( isset( $_GET['model_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which product page this is.
			$model_number = sanitize_text_field( wp_unslash( $_GET['model_number'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which product page this is.
		} else if ( isset( $_GET['subsubmenuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
			$menu_level = 3;
			$menu_id    = (int) $_GET['subsubmenuid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
		} else if ( isset( $_GET['submenuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
			$menu_level = 2;
			$menu_id    = (int) $_GET['submenuid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
		} else if ( isset( $_GET['menuid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
			$menu_level = 1;
			$menu_id    = (int) $_GET['menuid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
		}
	}

	if ( $print_meta && ! $own_desc ) {
		$seo = null;
		if ( '' !== $model_number ) {
			$seo = wp_cache_get( 'wpeasycart-product-seo-' . $model_number, 'wpeasycart-product-seo' );
			if ( ! $seo ) {
				$seo = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_product.seo_keywords, ec_product.seo_description FROM ec_product WHERE ec_product.model_number = %s', $model_number ) );
				wp_cache_set( 'wpeasycart-product-seo-' . $model_number, $seo, 'wpeasycart-product-seo' );
			}
		} else if ( 1 === $menu_level ) {
			$seo = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_menulevel1.seo_keywords, ec_menulevel1.seo_description FROM ec_menulevel1 WHERE ec_menulevel1.menulevel1_id = %d', $menu_id ) );
		} else if ( 2 === $menu_level ) {
			$seo = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_menulevel2.seo_keywords, ec_menulevel2.seo_description FROM ec_menulevel2 WHERE ec_menulevel2.menulevel2_id = %d', $menu_id ) );
		} else if ( 3 === $menu_level ) {
			$seo = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_menulevel3.seo_keywords, ec_menulevel3.seo_description FROM ec_menulevel3 WHERE ec_menulevel3.menulevel3_id = %d', $menu_id ) );
		}
		if ( $seo && isset( $seo->seo_description ) && '' != $seo->seo_description ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( stripslashes( $seo->seo_description ) ) ) . '">' . "\n";
		}
		if ( $seo && isset( $seo->seo_keywords ) && '' != $seo->seo_keywords ) {
			echo '<meta name="keywords" content="' . esc_attr( wp_strip_all_tags( stripslashes( $seo->seo_keywords ) ) ) . '">' . "\n";
		}
	}
	if ( $print_meta && '' !== $model_number ) {
		ec_show_facebook_meta( $model_number );
	}

	if ( get_option( 'ec_option_use_affirm' ) && get_option( 'ec_option_affirm_public_key' ) != "" ) {

		if ( get_option( 'ec_option_affirm_sandbox_account' ) ) {
			echo '<script>
			 var _affirm_config = {
				public_api_key: "' . esc_js( get_option( 'ec_option_affirm_public_key' ) ) . '",
				script:     "https://cdn1-sandbox.affirm.com/js/v2/affirm.js"
			 };
			 (function(l,g,m,e,a,f,b) {var d,c=l[m]||{},h=document.createElement(f),n=document.getElementsByTagName(f)[0],k=function(a,b,c) {return function() {a[b]._.push([c,arguments])}};c[e]=k(c,e,"set");d=c[e];c[a]={};c[a]._=[];d._=[];c[a][b]=k(c,a,b);a=0;for (b="set add save post open empty reset on off trigger ready setProduct".split(" ");a<b.length;a++)d[b[a]]=k(c,e,b[a]);a=0;for (b=["get","token","url","items"];a<b.length;a++)d[b[a]]=function() {};h.async=!0;h.src=g[f];n.parentNode.insertBefore(h,n);delete g[f];d(g);l[m]=c})(window,_affirm_config,"affirm","checkout","ui","script","ready");
			</script>';
		} else {
			echo '<script>
			 var _affirm_config = {
				public_api_key: "' . esc_js( get_option( 'ec_option_affirm_public_key' ) ) . '",
				script:     "https://cdn1.affirm.com/js/v2/affirm.js"
			 };
			 (function(l,g,m,e,a,f,b) {var d,c=l[m]||{},h=document.createElement(f),n=document.getElementsByTagName(f)[0],k=function(a,b,c) {return function() {a[b]._.push([c,arguments])}};c[e]=k(c,e,"set");d=c[e];c[a]={};c[a]._=[];d._=[];c[a][b]=k(c,a,b);a=0;for (b="set add save post open empty reset on off trigger ready setProduct".split(" ");a<b.length;a++)d[b[a]]=k(c,e,b[a]);a=0;for (b=["get","token","url","items"];a<b.length;a++)d[b[a]]=function() {};h.async=!0;h.src=g[f];n.parentNode.insertBefore(h,n);delete g[f];d(g);l[m]=c})(window,_affirm_config,"affirm","checkout","ui","script","ready");
			</script>';
		}
	}

}

function ec_show_facebook_meta( $model_number ) {
	global $wpdb;
	$ec_db = new ec_db();
	/* 6.0.2: get_product_list() caches it for this viewer ( ec_db::product_cache_key() ), never under the plain key. */
	$active  = ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) ? ' AND product.activate_in_store = 1' : ''; // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
	$product = $ec_db->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s' . $active, $model_number ), '', '', '', 'wpeasycart-product-only-' . $model_number, '', '' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $active is one of two fixed strings.
	if ( ! is_array( $product ) || ! isset( $product[0] ) || ! is_array( $product[0] ) ) {
		return;
	}
	$product = $product[0];

	/* 6.0.2: plain text, the product's own address, and the price shown to a signed-out shopper. */
	$prod_title       = wp_strip_all_tags( stripslashes( (string) $product['title'] ) );
	$prod_description = class_exists( 'wp_easycart_product_schema' ) ? wp_easycart_product_schema::row_description( $product, 300 ) : wp_strip_all_tags( (string) $product['seo_description'] );
	$prod_image       = class_exists( 'wp_easycart_product_schema' ) ? wp_easycart_product_schema::row_image() : '';
	$prod_url         = ( ! get_option( 'ec_option_use_old_linking_style' ) && ! empty( $product['post_id'] ) && get_permalink( (int) $product['post_id'] ) ) ? get_permalink( (int) $product['post_id'] ) : ec_curPageURL();

	echo "\n";
	echo '<meta property="og:title" content="' . esc_attr( $prod_title ) . '" />' . "\n";
	echo '<meta property="og:type" content="product" />' . "\n";
	if ( '' !== $prod_description ) {
		echo '<meta property="og:description" content="' . esc_attr( $prod_description ) . '" />' . "\n";
	}
	if ( '' !== $prod_image ) {
		echo '<meta property="og:image" content="' . esc_url( $prod_image ) . '" />' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $prod_url ) . '" />' . "\n";
	if ( class_exists( 'wp_easycart_product_schema' ) && class_exists( 'ec_product' ) ) {
		$ec_product = new ec_product( $product, 0, 0, 0 );
		$pricing    = wp_easycart_product_schema::pricing( $ec_product );
		if ( ! $pricing['hidden'] && $pricing['price'] > 0 ) {
			echo '<meta property="product:price:amount" content="' . esc_attr( number_format( $pricing['price'], 2, '.', '' ) ) . '" />' . "\n";
			echo '<meta property="product:price:currency" content="' . esc_attr( wp_easycart_product_schema::currency() ) . '" />' . "\n";
		}
	}
}

function ec_theme_head_data() {
	$GLOBALS['ec_page_options'] = new ec_page_options();

	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/head_content.php" ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/head_content.php" );

	} else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/head_content.php' ) ) {
		include( EC_PLUGIN_DIRECTORY . '/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/head_content.php' );

	}
}

function ec_curPageURL() {
	$pageURL = 'http';
	if ( isset( $_SERVER["HTTPS"] ) )
		$pageURL .= "s";

	$pageURL .= "://";
	if ( (int) $_SERVER["SERVER_PORT"] != 80 )
		$pageURL .= sanitize_text_field( $_SERVER["SERVER_NAME"] ) . ":" . (int) $_SERVER["SERVER_PORT"] . htmlspecialchars( sanitize_text_field( $_SERVER["REQUEST_URI"] ), ENT_QUOTES );
	else
		$pageURL .= sanitize_text_field( $_SERVER["SERVER_NAME"] ) . htmlspecialchars ( sanitize_text_field( $_SERVER["REQUEST_URI"] ), ENT_QUOTES );

	return $pageURL;
}

function ec_short_string($text, $length) {
	$text = strip_tags( $text );
	if ( strlen( $text ) > $length )
		$text = substr($text, 0, strpos($text, ' ', $length));

	return $text;
}

//[ec_store]
function load_ec_store( $atts ) {

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( "DONOTCACHEPAGE", true );
	}

	if ( ! defined( 'DONOTCDN' ) ) {
		define('DONOTCDN', true);
	}

	$args = shortcode_atts( array(
		'menuid' => 'NOMENU',
		'submenuid' => 'NOSUBMENU',
		'subsubmenuid' => 'NOSUBSUBMENU',
		'manufacturerid' => 'NOMANUFACTURER',
		'groupid' => 'NOGROUP',
		'modelnumber' => 'NOMODELNUMBER',
		'language' => 'NONE',
		'background_add' => false,
		'columns' => false,
		'cols_desktop' => false,
		'cols_tablet' => false,
		'cols_mobile' => false,
		'cols_mobile_small' => 1,
		'spacing' => 20,
		'use_dynamic' => false,
		'productid' => false,
		'category' => false,
		'manufacturer' => false,
		'elementor' => false,
		'show_breadcrumbs' => get_option( 'ec_option_show_breadcrumbs' ),
		'show_image_hover' => get_option( 'ec_option_show_magnification' ),
		'show_lightbox' => get_option( 'ec_option_show_large_popup' ),
		'show_thumbnails' => true,
		'show_title' => true,
		'title_font' => null,
		'title_color' => null,
		'title_divider_color' => null,
		'price_font' => null,
		'price_color' => null,
		'list_price_font' => null,
		'list_price_color' => null,
		'add_to_cart_color' => null,
		'show_customer_reviews' => null,
		'show_price' => true,
		'show_short_description' => null,
		'show_model_number' => get_option( 'ec_option_show_model_number' ),
		'show_categories' => get_option( 'ec_option_show_categories' ),
		'show_manufacturer' => get_option( 'ec_option_show_manufacturer' ),
		'show_stock' => get_option( 'ec_option_show_stock_quantity' ),
		'show_social' => true,
		'show_description' => null,
		'show_specifications' => null,
		'show_related_products' => null,
		'background_add' => null,
		'details_sizing' => (int) get_option( 'ec_option_product_details_sizing' ),
		'paging' => get_option( 'ec_option_enable_product_paging' ),
		'per_page' => get_option( 'ec_option_enable_product_paging_per_page' ),
		'sorting' => get_option( 'ec_option_show_sort_box' ),
		'sorting_default' => get_option( 'ec_option_default_store_filter' ),
		'status' => 'featured',
		'product_style' => get_option( 'ec_option_default_product_type' ),
		'product_align' => get_option( 'ec_option_default_product_align' ),
		'product_visible_options' => get_option( 'ec_option_default_product_visible_options' ),
		'product_rounded_corners' => get_option( 'ec_option_default_product_rounded_corners' ),
		'product_rounded_corners_tl' => get_option( 'ec_option_default_product_rounded_corners_tl' ),
		'product_rounded_corners_tr' => get_option( 'ec_option_default_product_rounded_corners_tr' ),
		'product_rounded_corners_bl' => get_option( 'ec_option_default_product_rounded_corners_bl' ),
		'product_rounded_corners_br' => get_option( 'ec_option_default_product_rounded_corners_br' ),
		'product_border' => get_option( 'ec_option_default_product_border' ),
		'sidebar' => get_option( 'ec_option_show_store_sidebar' ),
		'sidebar_position' => get_option( 'ec_option_store_sidebar_position' ),
		'sidebar_filter_clear' => get_option( 'ec_option_store_sidebar_filter_clear' ),
		'sidebar_include_location' => get_option( 'ec_option_store_sidebar_include_location' ),
		'sidebar_include_search' => get_option( 'ec_option_store_sidebar_include_search' ),
		'sidebar_include_categories' => get_option( 'ec_option_store_sidebar_include_categories' ),
		'sidebar_include_categories_first' => get_option( 'ec_option_sidebar_include_categories_first' ),
		'sidebar_categories' => get_option( 'ec_option_store_sidebar_categories' ),
		'sidebar_include_category_filters' => get_option( 'ec_option_sidebar_include_category_filters' ),
		'sidebar_category_filter_id' => get_option( 'ec_option_sidebar_category_filter_id' ),
		'sidebar_category_filter_method' => get_option( 'ec_option_sidebar_category_filter_method' ),
		'sidebar_category_filter_open' => get_option( 'ec_option_sidebar_category_filter_open' ),
		'sidebar_include_option_filters' => get_option( 'ec_option_sidebar_include_option_filters' ),
		'sidebar_option_filters' => get_option( 'ec_option_store_sidebar_option_filters' ),
		'sidebar_include_manufacturers' => get_option( 'ec_option_store_sidebar_include_manufacturers' ),
		'sidebar_manufacturers' => get_option( 'ec_option_store_sidebar_manufacturers' ),
		'sidebar_include_pricepoints' => get_option( 'ec_option_store_sidebar_include_pricepoints' ),
		'image_display_mode' => '',
		'image_height' => '',
		'image_object_fit' => 'cover',
		'image_object_position' => 'center center',
		'image_contain_bg' => '',
		'image_hover_effect' => '',
	), $atts );
	$args['language'] = strtoupper( esc_attr( sanitize_text_field( $args['language'] ) ) );
	$args['modelnumber'] = sanitize_text_field( $args['modelnumber'] );
	if ( 'NOMANUFACTURER' !== $args['manufacturerid'] ) {
		$clean = implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $args['manufacturerid'] ) ) ) );
		$args['manufacturerid'] = ( '' !== $clean ) ? $clean : 'NOMANUFACTURER';
	}
	if ( 'NOGROUP' !== $args['groupid'] ) {
		$clean = implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $args['groupid'] ) ) ) );
		$args['groupid'] = ( '' !== $clean ) ? $clean : 'NOGROUP';
	}
	if ( false !== $args['productid'] && '' !== $args['productid'] ) {
		$clean = implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $args['productid'] ) ) ) );
		$args['productid'] = ( '' !== $clean ) ? $clean : false;
	}
	$args['image_display_mode'] = in_array( $args['image_display_mode'], array( '', 'fixed', 'dynamic' ), true ) ? $args['image_display_mode'] : '';
	$args['image_height'] = ( '' !== $args['image_height'] ) ? (int) $args['image_height'] : '';
	$args['image_object_fit'] = in_array( $args['image_object_fit'], array( 'cover', 'contain', 'fill' ), true ) ? $args['image_object_fit'] : 'cover';
	$args['image_object_position'] = in_array( $args['image_object_position'], array( 'center center', 'center top', 'center bottom', 'left center', 'right center' ), true ) ? $args['image_object_position'] : 'center center';
	$args['image_contain_bg'] = ( '' !== $args['image_contain_bg'] ) ? sanitize_hex_color( $args['image_contain_bg'] ) : '';
	$args['image_hover_effect'] = in_array( $args['image_hover_effect'], array( '', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ), true ) ? $args['image_hover_effect'] : '';

	if ( $args['language'] != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $args['language'] );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $args['language'];
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	$GLOBALS['ec_store_shortcode_options'] = array( $args['menuid'], $args['submenuid'], $args['subsubmenuid'], $args['manufacturerid'], $args['groupid'], $args['modelnumber'], $args );

	ob_start();
	$store_page = new ec_storepage( $args['menuid'], $args['submenuid'], $args['subsubmenuid'], $args['manufacturerid'], $args['groupid'], $args['modelnumber'], $args );
	$store_page->display_store_page();
	return ob_get_clean();

}

//[ec_cart]
function load_ec_cart( $atts ) {

	if ( !get_option( 'ec_option_cache_prevent' ) ) {
		if ( !defined( 'DONOTCACHEPAGE' ) )
			define( "DONOTCACHEPAGE", true );

		if ( !defined( 'DONOTCDN' ) )
			define('DONOTCDN', true);
	}

	extract( shortcode_atts( array(
		'language' => 'NONE'
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db();
	}

	ob_start();
	/* 6.0.2: a pay link ( ec_page=invoice ) is a page of its own with no cart in it, so it renders here whether or not the
	   cart is drawn by AJAX. The dynamic cart ( one-page checkout, cache prevention ) had no route for it: the link showed the
	   shopper's cart on PHP 7 and nothing on PHP 8. */
	if ( isset( $_GET['ec_page'] ) && 'invoice' === $_GET['ec_page'] && class_exists( 'wp_easycart_order_pay' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing; the link's key is checked by render_page().
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		wp_easycart_order_pay::render_page();
	} else if ( wp_easycart_cart_is_dynamic() ) { /* 6.0.2: the one-page checkout always */
		wp_easycart_dynamic_cart_display( $language );
	} else {
	  $cart_page = new ec_cartpage();
	  $cart_page->display_cart_page();
	}
	return ob_get_clean();
}

function wp_easycart_dynamic_cart_display( $language = 'NONE' ) {
	$ec_db = new ec_db();
	if ( wp_easycart_onepage_active() && get_option( 'ec_option_onepage_checkout_cart_first' ) ) {
		$cart_page = 1;
	} else if ( wp_easycart_onepage_active() ) {
		$cart_page = 2;
	} else {
		$cart_page = 1;
	}
	if ( isset( $_GET['ec_page'] ) ) {
		if ( $_GET['ec_page'] == 'checkout_success' ) {
			$cart_page = 6;
		} else if ( $_GET['ec_page'] == 'checkout_info' ) {
			$cart_page = 2;
		} else if ( $_GET['ec_page'] == 'checkout_shipping' ) {
			$cart_page = 3;
		} else if ( $_GET['ec_page'] == 'checkout_payment' ) {
			$cart_page = 4;
			if ( isset( $_GET['ideal'] ) && $_GET['ideal'] == 'returning' && isset( $_GET['client_secret'] ) && isset( $_GET['source'] ) ) {
				$source = htmlspecialchars( sanitize_text_field( $_GET['source'] ), ENT_QUOTES );
				$client_secret = htmlspecialchars( sanitize_text_field( $_GET['client_secret'] ), ENT_QUOTES );
				$cart_page .= '-ideal' . '-' . $source . '-' . $client_secret;
			}
		} else if ( $_GET['ec_page'] == 'subscription_info' ) {
			$cart_page = 1;
			global $wpdb;
			$model_number = preg_replace( "/[^A-Za-z0-9\-\_]/", '', sanitize_text_field( $_GET['subscription'] ) );
			$products = $ec_db->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s' . ( ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) ? ' AND product.activate_in_store = 1' : '' ), $model_number ), "", "", "" );
			if ( count( $products ) > 0 ) {
				$cart_page = 5;
				$product_id = $products[0]['product_id'];
			}
		}
	} else if ( wp_easycart_onepage_active() && isset( $_GET['eccheckout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which step to show; nothing is changed.
		if ( $_GET['eccheckout'] == 'success' ) {
			$cart_page = 6;
		} else if ( $_GET['eccheckout'] == 'cart' ) {
			$cart_page = 1;
		} else if ( $_GET['eccheckout'] == 'information' ) {
			$cart_page = 2;
		} else if ( $_GET['eccheckout'] == 'shipping' ) {
			$cart_page = 3;
		} else if ( $_GET['eccheckout'] == 'payment' ) {
			$cart_page = 4;
			if ( isset( $_GET['ideal'] ) && $_GET['ideal'] == 'returning' && isset( $_GET['client_secret'] ) && isset( $_GET['source'] ) ) {
				$source = htmlspecialchars( sanitize_text_field( $_GET['source'] ), ENT_QUOTES );
				$client_secret = htmlspecialchars( sanitize_text_field( $_GET['client_secret'] ), ENT_QUOTES );
				$cart_page .= '-ideal' . '-' . $source . '-' . $client_secret;
			}
		}
	}
	$cart_page .= ( ( isset( $_GET['order_id'] ) ) ? '-' . (int) $_GET['order_id'] : '' );
	$cart_page .= ( ( isset( $_GET['PID'] ) && sanitize_text_field( $_GET['PID'] ) != '' ) ? '-paypal-' . preg_replace( "/[^A-Za-z0-9\-]/", '', sanitize_text_field( $_GET['PID'] ) ) . '-' . preg_replace( "/[^A-Za-z0-9\-]/", '', sanitize_text_field( $_GET['PYID'] ) ) : '' );
	$cart_page .= ( ( isset( $_GET['OID'] ) && sanitize_text_field( $_GET['OID'] ) != '' ) ? '-paypal-' . preg_replace( "/[^A-Z0-9]/", '', sanitize_text_field( $_GET['OID'] ) ) . '-' . preg_replace( "/[^A-Z0-9]/", '', sanitize_text_field( $_GET['PYID'] ) ) : '' );
	$cart_page .= ( ( $cart_page == 5 ) ? '-sub-' . esc_attr( $product_id ) : '' );
	$error_codes = apply_filters( 'wpeasycart_valid_cart_errors', array( "email_exists", "login_failed", "3dsecure_failed", "manualbill_failed", "thirdparty_failed", "payment_failed", "card_error", "already_subscribed", "not_activated", "subscription_not_found", "user_insert_error", "subscription_added_failed", "subscription_failed", "invalid_address", "session_expired", "invalid_vat_number", "stock_invalid", "ideal-pending", "shipping_method", "invalid_cart_shipping", /* 6.0.2: the checkout's newer checks, or a failed Place order came back with no reason */ "invalid_checkout", "minimum_order", "preorder_pickup", "restaurant_closed", "protection_blocked", "protection_paused", "protection_check", "protection_page", "checkout_fields", "po_number", "invalid_nonce" ) );
	/* 6.0.2: the notice to show; the forms sent back with an invalid security token name it as cart_error. */
	$wpec_dyn_error = '';
	if ( isset( $_GET['ec_cart_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a notice to show; nothing is changed.
		$wpec_dyn_error = sanitize_text_field( wp_unslash( $_GET['ec_cart_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	} else if ( isset( $_GET['cart_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpec_dyn_error = sanitize_text_field( wp_unslash( $_GET['cart_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( ! in_array( $wpec_dyn_error, $error_codes, true ) ) {
		$wpec_dyn_error = '';
	}
	echo '<div id="wpeasycart_cart_holder" style="position:relative; width:100%; min-height:350px;" data-cart-page="' . esc_js( $cart_page ) . '" data-success-code="' . ( ( isset( $_GET['ec_cart_success'] ) && sanitize_text_field( $_GET['ec_cart_success'] ) == 'account_created' ) ? 'account_created' : '' ) . '" data-error-code="' . esc_js( $wpec_dyn_error ) . '" data-language="' . esc_attr( sanitize_text_field( $language ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-get-dynamic-cart-page' ) ) . '"><style>
	@keyframes rotation{
		0% { transform:rotate(0deg); }
		100%{ transform:rotate(359deg); }
	}
	</style>
	<div style=\'font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;\'>
		<div>
			<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
		</div>
	</div></div>';
}

//[ec_account]
function load_ec_account( $atts ) {

	if ( !get_option( 'ec_option_cache_prevent' ) ) {
		if ( !defined( 'DONOTCACHEPAGE' ) )
			define( "DONOTCACHEPAGE", true );

		if ( !defined( 'DONOTCDN' ) )
			define('DONOTCDN', true);
	}

	extract( shortcode_atts( array(
		'language' => 'NONE',
		'redirect' => false
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );
	$redirect = ( is_string( $redirect ) && strlen( trim( $redirect ) ) > 0 ) ? esc_url_raw( $redirect ) : false;

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	ob_start();
	if ( isset( $_POST['ec_form_action'] ) ) {
		$account_page = new ec_accountpage( $redirect );
		$account_page->process_form_action( sanitize_key( $_POST['ec_form_action'] ) );	

	} else if ( get_option( 'ec_option_cache_prevent' ) ) {
		wp_easycart_dynamic_account_display( $language );

	} else {
		$account_page = new ec_accountpage( $redirect );
		$account_page->display_account_page();
	}
	return ob_get_clean();
}

//[ec_account_forgot]
function load_ec_account_forgot( $atts ) {
	if ( ! get_option( 'ec_option_cache_prevent' ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( "DONOTCACHEPAGE", true );
		}

		if ( ! defined( 'DONOTCDN' ) ) {
			define('DONOTCDN', true);
		}
	}

	extract( shortcode_atts( array(
		'language' => 'NONE',
		'redirect' => false
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	ob_start();
	if ( isset( $_POST['ec_form_action'] ) ) {
		$account_page = new ec_accountpage( $redirect );
		$account_page->process_form_action( sanitize_key( $_POST['ec_form_action'] ) );	

	} else if ( get_option( 'ec_option_cache_prevent' ) ) {
		wp_easycart_dynamic_account_display( $language, 'forgot_password' );

	} else {
		$account_page = new ec_accountpage( $redirect );
		$account_page->display_account_page( 'forgot_password' );
	}
	return ob_get_clean();
}

//[ec_account_login]
function load_ec_account_login( $atts ) {
	if ( ! get_option( 'ec_option_cache_prevent' ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( "DONOTCACHEPAGE", true );
		}

		if ( ! defined( 'DONOTCDN' ) ) {
			define('DONOTCDN', true);
		}
	}

	extract( shortcode_atts( array(
		'elementor' => false,
		'language' => 'NONE',
		'redirect' => false,
		'form_only' => false,
		'dynamic_cache' => '',
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	$shortcode_atts = array(
		'elementor' => (bool) $elementor,
		'form_only' => (bool) $form_only,
	);

	ob_start();
	if ( isset( $_POST['ec_form_action'] ) ) {
		$account_page = new ec_accountpage( $redirect );
		$account_page->process_form_action( sanitize_key( $_POST['ec_form_action'] ) );	

	} else if ( get_option( 'ec_option_cache_prevent' ) && 'disabled' != $dynamic_cache ) {
		wp_easycart_dynamic_account_display( $language, 'login', $shortcode_atts );

	} else {
		$account_page = new ec_accountpage( $redirect );
		$account_page->display_account_page( 'login', $shortcode_atts );
	}
	return ob_get_clean();
}

//[ec_account_register]
function load_ec_account_register( $atts ) {
	if ( ! get_option( 'ec_option_cache_prevent' ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( "DONOTCACHEPAGE", true );
		}

		if ( ! defined( 'DONOTCDN' ) ) {
			define('DONOTCDN', true);
		}
	}

	extract( shortcode_atts( array(
		'language' => 'NONE',
		'redirect' => false
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	ob_start();
	if ( isset( $_POST['ec_form_action'] ) ) {
		$account_page = new ec_accountpage( $redirect );
		$account_page->process_form_action( sanitize_key( $_POST['ec_form_action'] ) );	

	} else if ( get_option( 'ec_option_cache_prevent' ) ) {
		wp_easycart_dynamic_account_display( $language, 'register' );

	} else {
		$account_page = new ec_accountpage( $redirect );
		$account_page->display_account_page( 'register' );
	}
	return ob_get_clean();
}

function wp_easycart_dynamic_account_display( $language = 'NONE', $force_page = false, $shortcode_atts = array() ) {
	$account_page = '';
	$pages = array( 'forgot_password', 'reset_password', 'register', 'billing_information', 'shipping_information', 'personal_information', 'password', 'orders', 'order_details', 'subscription', 'subscriptions', 'subscription_details' );
	if ( $force_page ) {
		$account_page = sanitize_key( $force_page );
	} else if ( isset( $_GET['ec_page'] ) && in_array( $_GET['ec_page'], $pages ) ) {
		$account_page = sanitize_key( $_GET['ec_page'] );
	}
	if ( $account_page == 'order_details' && isset( $_GET['order_id'] ) && isset( $_GET['ec_guest_key'] ) ) {
		$account_page .= '-' . (int) $_GET['order_id'] . '-' . substr( preg_replace( '/[^A-Z]/', '', sanitize_text_field( $_GET['ec_guest_key'] ) ), 0, 30 );
	} else if ( $account_page == 'order_details' && isset( $_GET['order_id'] ) ) {
		$account_page .= '-' . (int) $_GET['order_id'];
	} else if ( $account_page == 'subscription_details' && isset( $_GET['subscription_id'] ) ) {
		$account_page .= '-' . (int) $_GET['subscription_id'];
	} else if ( $account_page == 'reset_password' && isset( $_GET['ec_reset_key'] ) ) {
		$account_page .= '-' . preg_replace( '/[^a-zA-Z0-9\-]/', '', sanitize_text_field( wp_unslash( $_GET['ec_reset_key'] ) ) );
	}
	/* 6.0.2: plus the Connect Order answers ( ec_accountpage::process_connect_order() / process_order_claim() ). */
	$valid_success_codes = array( 'login_success', 'validation_required', 'reset_email_sent', 'password_reset_success', 'resend_activation_sent', 'personal_information_updated', 'billing_information_updated', 'billing_information_updated', 'shipping_information_updated', 'shipping_information_updated', 'subscription_updated', 'subscription_updated', 'subscription_canceled', 'cart_account_created', 'activation_success', 'password_updated', 'order_connected', 'order_claim_sent' );
	$valid_error_codes = array( 'register_email_error', 'not_activated', 'login_failed', 'register_email_error', 'register_invalid', 'no_reset_email_found', 'reset_link_invalid', 'password_too_short', 'password_invalid', 'personal_information_update_error', 'password_no_match', 'password_wrong_current', 'billing_information_error', 'shipping_information_error', 'subscription_update_failed', 'subscription_cancel_failed', 'invalid_order_id', 'order_claim_invalid', 'order_claim_sign_in', 'order_claim_limit' );
	$success_code = ( isset( $_GET['account_success'] ) && in_array( $_GET['account_success'], $valid_success_codes ) ) ? sanitize_text_field( $_GET['account_success'] ) : '';
	$error_code = ( isset( $_GET['account_error'] ) && in_array( $_GET['account_error'], $valid_error_codes ) ) ? sanitize_text_field( $_GET['account_error'] ) : '';
	echo '<div id="wpeasycart_account_holder" style="position:relative; width:100%; min-height:350px;" data-account-page="' . esc_js( $account_page ) . '" data-page-id="' . esc_js( get_queried_object_id() ) . '" data-success-code="' . esc_js( $success_code ) . '" data-error-code="' . esc_js( $error_code ) . '" data-language="' . esc_attr( sanitize_text_field( $language ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-get-dynamic-account-page' ) ) . '"';
	foreach ( $shortcode_atts as $shortcode_att => $shortcode_att_value ) {
		if ( 'elementor' == $shortcode_att ) {
			echo ' data-elementor="' . ( ( (bool) $shortcode_att_value ) ? '1' : '0' ) . '"';
		} else if ( 'form_only' == $shortcode_att ) {
			echo ' data-form-only="' . ( ( (bool) $shortcode_att_value ) ? '1' : '0' ) . '"';
		}
	}
	echo '><style>
	@keyframes rotation{
		0% { transform:rotate(0deg); }
		100%{ transform:rotate(359deg); }
	}
	</style>
	<div style=\'font-family: "HelveticaNeue", "HelveticaNeue-Light", "Helvetica Neue Light", helvetica, arial, sans-serif; font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center;\'>
		<div>
			<div style="height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;"></div>
		</div>
	</div></div>';
}

//[ec_product]
function load_ec_product( $atts ) {
	/* 6.0.2: Who can view the store ( Settings › Products ) covers product grids and sliders too: a visitor outside the chosen
	 * customer roles gets nothing ( managers see the products, an editor a note ). What shows depends on who is signed in, so
	 * on such a store the page is never cached, also for a customer allowed in ( their copy must not reach visitors ). */
	if ( get_option( 'ec_option_restrict_store' ) && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
		wp_easycart_product_details_no_cache();
	}
	/* 6.0.2: a store with customer role prices or products for one role never lets a page cache keep one shopper's grid for others. */
	if ( class_exists( 'WP_EasyCart_Elementor_Shop_Query' ) && method_exists( 'WP_EasyCart_Elementor_Shop_Query', 'role_no_cache' ) ) {
		WP_EasyCart_Elementor_Shop_Query::role_no_cache();
	}
	if ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() ) {
		if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
			wp_easycart_product_details_no_cache();
		}
		ob_start();
		wp_easycart_print_product_not_found();
		return ob_get_clean();
	}
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'model_number' => 'NOPRODUCT',
		'productid' => 'NOPRODUCTID',
		'category' => '',
		'manufacturer' => '',
		'orderby' => '',
		'order' => 'ASC',
		'status' => '',
		'columns' => false,
		'cols_desktop' => false,
		'cols_tablet' => false,
		'cols_mobile' => false,
		'cols_mobile_small' => 1,
		'margin' => '45px',
		'width' => '175px',
		'minheight' => '375px',
		'imagew' => '140px',
		'imageh' => '140px',
		'style' => '1',
		'layout_mode' => 'grid',
		'product_border' => true,
		'per_page' => false,
		'product_slider_nav_pos' => '',
		'product_slider_nav_type' => 'owl-simple',
		'slider_nav' => 0,
		'slider_nav_show' => 0,
		'slider_nav_tablet' => 0,
		'slider_nav_mobile' => 0,
		'slider_dot' => 0,
		'slider_dot_tablet' => 0,
		'slider_dot_mobile' => 0,
		'slider_loop' => 0,
		'slider_auto_play' => 0,
		'slider_auto_play_time' => 10000,
		'slider_center' => 0,
		'spacing' => 20,
		'product_style' => 'default',
		'product_align' => 'default',
		'product_visible_options' => 'title,category,price,rating,cart,quickview,desc',
		'product_rounded_corners' => false,
		'product_rounded_corners_tl' => 10,
		'product_rounded_corners_tr' => 10,
		'product_rounded_corners_bl' => 10,
		'product_rounded_corners_br' => 10
	), $atts ) );
	$model_number = sanitize_text_field( $model_number );

	if( !$style ) {
		$style = '1';
	}
	if( $is_elementor && !$columns ) {
		$columns = 4;
	} else if( !$columns ) {
		if ( get_option( 'ec_option_default_desktop_columns' ) ) {
			$columns = get_option( 'ec_option_default_desktop_columns' );
		} else {
			$columns = 1;
		}
	}
	if( $is_elementor && !$cols_desktop ) {
		$cols_desktop = 4;
	} else if( !$cols_desktop ) {
		if ( get_option( 'ec_option_default_laptop_columns' ) ) {
			$cols_desktop = get_option( 'ec_option_default_laptop_columns' );
		} else {
			$cols_desktop = 1;
		}
	}
	if( $is_elementor && !$cols_tablet ) {
		$cols_tablet = 3;
	} else if( !$cols_tablet ) {
		if ( get_option( 'ec_option_default_tablet_columns' ) ) {
			$cols_tablet = get_option( 'ec_option_default_tablet_columns' );
		} else {
			$cols_tablet = 1;
		}
	}
	if( $is_elementor && !$cols_mobile ) {
		$cols_mobile = 2;
	} else if( !$cols_mobile ) {
		$cols_mobile = 1;
		if ( get_option( 'ec_option_default_smartphone_columns' ) ) {
			$cols_mobile = get_option( 'ec_option_default_smartphone_columns' );
		} else {
			$cols_mobile = 1;
		}
	}
	$simp_product_id = $model_number;
	ob_start();
	global $wpdb;
	$mysqli = new ec_db();
	if ( $model_number != "NOPRODUCT" ) {
		$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s' . ( ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) ? ' AND product.activate_in_store = 1' : '' ), $model_number ), '', '', '', '', '', '' );
	} else {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			$product_where = " WHERE product.activate_in_store = 1";
		} else {
			$product_where = " WHERE ( product.activate_in_store = 1 OR product.activate_in_store = 0 )";
		}
		$product_order_default = ' ORDER BY ';
		if ( $status == 'featured' ) {
			$product_where .= ' AND product.show_on_startup = 1';
		} else if ( $status == 'on_sale' ) {
			$product_where .= ' AND product.list_price > product.price';
		} else if ( $status == 'in_stock' ) {
			$product_where .= ' AND ( product.stock_quantity > 0 OR ( product.show_stock_quantity = 0 AND product.use_optionitem_quantity_tracking = 0 ) OR product.allow_backorders = 1 )';
		}
		if ( ( $productid != '' && $productid != 'NOPRODUCTID' ) || $category != '' || $manufacturer != '' ) {
			$product_where .= ' AND (';
		}
		$ids = 0;
		if ( ( $productid != '' && $productid != 'NOPRODUCTID' ) || $category != '' ) {
			$product_ids = array();
			$cat_prod_ids = array();

			if ( $productid != '' && $productid != 'NOPRODUCTID' ) {
				$product_ids = explode( ',', $productid );
			}

			if ( $category != '' ) {
				$category_ids = explode( ',', $category );
				$cat_id_string = '';
				foreach ( $category_ids as $category_id ) {
					if ( $cat_id_string != '' ) {
						$cat_id_string .= ',';
					}
					$cat_id_string .= (int) $category_id;
				}
				$cat_products = $wpdb->get_results( "SELECT DISTINCT product_id FROM ec_categoryitem WHERE category_id IN(" . $cat_id_string . ")" );
				foreach ( $cat_products as $cat_product ) {
					if ( ! in_array( $cat_product->product_id, $product_ids ) ) {
						$product_ids[] = (int) $cat_product->product_id;
					}
				}
			}

			if ( count( $product_ids ) > 0 ) {
				foreach ( $product_ids as $product_id ) {
					if ( $ids > 0 ) {
						$product_where .= ' OR ';
						$product_order_default .= ', ';
					}
					$product_where .= $wpdb->prepare( 'product.product_id = %d', (int) $product_id );
					$product_order_default .= $wpdb->prepare( 'product.product_id = %d DESC', (int) $product_id );
					$ids++;
				}

			}
		} else {
			$product_order_default = ' ORDER BY product.product_id DESC';
		}

		if ( $manufacturer != '' ) {
			$manufacturer_ids = explode( ',', $manufacturer );
			foreach ( $manufacturer_ids as $manufacturer_id ) {
				if ( $ids > 0 ) {
					$product_where .= " OR ";
				}
				$product_where .= $wpdb->prepare( 'product.manufacturer_id = %d', (int) $manufacturer_id );
				$ids++;
			}
		}

		if ( ( $productid != '' && $productid != 'NOPRODUCTID' ) || $category != '' || $manufacturer != '' ) {
			$product_where .= ')';
		}

		/* 6.0.2: a picked category with no products ( or picked products that are gone ) left `AND ()` and a bare ORDER BY,
		 * a database error in place of the list. With nothing to match the widget draws nothing, as the error did; with a
		 * brand as well, the brand's products show, newest first. */
		$wpec_nothing_selected = ( ( ( '' !== (string) $productid && 'NOPRODUCTID' !== (string) $productid ) || '' !== (string) $category || '' !== (string) $manufacturer ) && 0 === $ids );
		if ( ' ORDER BY ' === $product_order_default ) {
			$product_order_default = ' ORDER BY product.product_id DESC';
		}

		$orderdir = ( $order == 'DESC' ) ? 'DESC' : 'ASC';
		if ( $orderby == 'title' ) {
			$product_order = " ORDER BY product.title " . $orderdir;
		} else if ( $orderby == 'price' ) {
			$product_order = " ORDER BY product.price " . $orderdir;
		} else if ( $orderby == 'product_id' ) {
			$product_order = " ORDER BY product.product_id " . $orderdir;
		} else if ( $orderby == 'added_to_db_date' ) {
			$product_order = " ORDER BY product.added_to_db_date " . $orderdir;
		} else if ( $orderby == 'rand' ) {
			$product_order = " ORDER BY RAND()";
		} else if ( $orderby == 'views' ) {
			$product_order = " ORDER BY product.views " . $orderdir;
		} else if ( $orderby == 'rating' ) {
			$product_order = " ORDER BY review_average " . $orderdir;
		} else {
			$product_order = $product_order_default;
		}

		$limit_query = "";
		if ( $per_page ) {
			$limit_query = " LIMIT " . ( (int) $per_page );
		}

		$products = ( $wpec_nothing_selected ) ? array() : $mysqli->get_product_list( $product_where, $product_order, $limit_query, "" );
	}
	if ( count( $products ) > 0 ) {

		if( ! $is_elementor && 1 == count( $products ) ){
			$columns = 1;
			$cols_desktop = 1;
			$cols_tablet = 1;
			$cols_mobile = 1;
		}

		$cart_page_id = get_option('ec_option_cartpage');
		if ( function_exists( 'icl_object_id' ) ) {
			$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
		}
		$cart_page = get_permalink( $cart_page_id );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$cart_page = $https_class->makeUrlHttps( $cart_page );
		}

		echo "<div class=\"ec_product_shortcode" . ( ( $product_border ) ? '' : ' ec_product_shortcode_no_borders' ) . "\"><div class=\"ec_product_added_to_cart\"><div class=\"ec_product_added_icon\"></div>" . wp_easycart_language()->get_text( "product_page", "product_product_added_note" ) . "<a href=\"" . esc_attr( $cart_page ) . "\" title=\"" . wp_easycart_language()->get_text( "product_page", "product_view_cart" ) . "\">" . wp_easycart_language()->get_text( "product_page", "product_view_cart" ) . "</a></div><div id=\"ec_current_media_size\"></div>";
		if ( $layout_mode == 'slider' ) {
			$owl_options = (object) array(
				'margin'      => (int) $spacing,
				'loop'       => (bool) $slider_loop,
				'autoplay'     => (bool) $slider_auto_play,
				'autoplayTimeout'  => (int) $slider_auto_play_time,
				'center'      => (bool) $slider_center,
				'responsive'    => (object) array(
					'0'       => (object) array(
						'items'   => (int) $cols_mobile_small,
						'nav'    => (bool) $slider_nav_mobile,
						'dots'   => (bool) $slider_dot_mobile
					),
					'576'      => (object) array(
						'items'   => (int) $cols_mobile,
						'nav'    => (bool) $slider_nav_mobile,
						'dots'   => (bool) $slider_dot_mobile
					),
					'768'      => (object) array(
						'items'   => (int) $cols_tablet,
						'nav'    => (bool) $slider_nav_tablet,
						'dots'   => (bool) $slider_dot_tablet
					),
					'992'      => (object) array(
						'items'   => (int) $columns,
						'nav'    => (bool) $slider_nav_tablet,
						'dots'   => (bool) $slider_dot_tablet
					),
					'1200'     => (object) array(
						'items'   => (int) $cols_desktop,
						'nav'    => (bool) $slider_nav,
						'dots'   => (bool) $slider_dot
					),
					'1600'     => (object) array(
						'items'   => (int) $cols_desktop,
						'nav'    => (bool) $slider_nav,
						'dots'   => (bool) $slider_dot
					)
				)
			);
			echo "<div id=\"wpeasycart-owl-slider-" . esc_attr( rand( 10000, 999999 ) ) . "\" class=\"colsdesktop" . esc_attr( $cols_desktop ) . " columns" . esc_attr( $columns ) . " colstablet" . esc_attr( $cols_tablet ) . " colsmobile" . esc_attr( $cols_mobile ) . " colssmall" . esc_attr( $cols_mobile_small ) . " owl-wpeasycart owl-carousel" . ( ( $product_slider_nav_type == 'owl-simple' || $product_slider_nav_type == '' ) ? ' owl-simple' : '' ) . ( ( $product_slider_nav_type == 'owl-full' ) ? ' owl-full' : '' ) . ( ( $product_slider_nav_type == 'owl-nav-rounded' ) ? ' owl-simple owl-nav-rounded' : '' ) . " carousel-with-shadow" . ( ( $slider_nav_show ) ? '' : ' owl-nav-show' ) . ( ( $product_slider_nav_pos == 'owl-nav-inside' ) ? ' owl-nav-inside' : '' ) . ( ( $product_slider_nav_pos == 'owl-nav-top' ) ? ' owl-nav-top' : '' ) . "\" data-owl-options=\"" . htmlspecialchars( json_encode( $owl_options ) ) . "\" style=\"float:left; width:100%;\">"; // XSS OK. Output owl options, which are properly typed above.

		} else {
			echo "<ul class=\"ec_productlist_ul " . esc_attr( ( isset( $spacing ) ) ? 'sp-' . ((int)$spacing) : '' ) . " colsdesktop" . esc_attr( $cols_desktop ) . " columns" . esc_attr( $columns ) . " colstablet" . esc_attr( $cols_tablet ) . " colsmobile" . esc_attr( $cols_mobile ) . " colssmall" . esc_attr( $cols_mobile_small ) . " \" style=\"min-height:" . esc_attr( $minheight ) . ";\">";
		}

		for ( $prod_index=0; $prod_index<count( $products ); $prod_index++ ) {
			$product = new ec_product( $products[$prod_index], 0, 0, 1 );
			if ( $style == '1' ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product.php' );
				}
			} else if ( $style == '2' ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_widget.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_widget.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_widget.php' );
				}
			} else {
				echo "<a href=\"" . esc_url( $product->get_product_link() ) . "\">";
				echo "<img src=\"" . esc_url( $product->get_product_single_image() ) . "\" alt=\"" . esc_attr( $product->title ) . "\" width=\"" . esc_attr( $imagew ) . "\" height=\"" . esc_attr( $imageh ) . "\">";
				echo "</a>";
				echo "<h3><a href=\"" . esc_url( $product->get_product_link() ) . "\">" . esc_attr( $product->title ) . "</a></h3>";
				echo "<span class=\"ec_price_button\" style=\"width:" . esc_attr( $width ) . "\">";
				if ( $product->has_sale_price() ) {
					echo "<span class=\"ec_price_before\"><del>" . esc_attr( $product->get_formatted_before_price() ) . "</del></span>";
					echo "<span class=\"ec_price_sale\">" . esc_attr( $product->get_formatted_price() ) . "</span>";
				} else {
					echo "<span class=\"ec_price\">" . esc_attr( $product->get_formatted_price() ) . "</span>";
				}
				echo "</span>";
			}
		}
		if ( $layout_mode == 'slider' ) {
			echo "</div>
			<style>
			@keyframes rotation{
				0% { transform:rotate(0deg); }
				100%{ transform:rotate(359deg); }
			}
			</style>
			<div class=\"wpec-product-slider-loader\" style=\"font-size: 14px; text-align: center; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; -ms-box-sizing: border-box; box-sizing: border-box; width: 350px; top: 50%; left: 50%; position: absolute; margin-left: -165px; margin-top: -80px; cursor: pointer; text-align: center; z-index:99;\">
				<div>
					<div style=\"height: 30px; width: 30px; display: inline-block; box-sizing: content-box; opacity: 1; filter: alpha(opacity=100); -webkit-animation: rotation .7s infinite linear; -moz-animation: rotation .7s infinite linear; -o-animation: rotation .7s infinite linear; animation: rotation .7s infinite linear; border-left: 8px solid rgba(0, 0, 0, .2); border-right: 8px solid rgba(0, 0, 0, .2); border-bottom: 8px solid rgba(0, 0, 0, .2); border-top: 8px solid #fff; border-radius: 100%;\"></div>
				</div>
			</div>";
		} else {
			echo "</ul>";
		}
		echo "<div style=\"clear:both;\"></div></div>";
		if ( $layout_mode == 'slider' && ( wp_doing_ajax() || ( isset( $_GET['action'] ) && $_GET['action'] == 'elementor' ) ) ) {
			echo "<script>
			jQuery( '.ec_product_shortcode .owl-carousel' ).each( function() {
				jQuery( this ).on({
					'initialized.owl.carousel': function() {
						jQuery( this ).find( '.wp-easycart-carousel-item' ).show();
						jQuery( this ).parent().find( '.wpec-product-slider-loader' ).hide();
					}

				}).owlCarousel( JSON.parse( jQuery( this ).attr( 'data-owl-options' ) ) );
			} );
			</script>";
		}
	}
	return ob_get_clean();
}

function wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id ) {
	global $wpdb;
	/* 6.0.2: Settings › Products › Who can view the store applies to the product widgets and shortcodes too, as it does to
	 * the store page: a visitor outside the chosen customer roles gets no product ( store managers always do ). Such a store's
	 * pages are never cached, also for a customer allowed in. */
	if ( get_option( 'ec_option_restrict_store' ) && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
		wp_easycart_product_details_no_cache();
	}
	if ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() ) {
		if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
			wp_easycart_product_details_no_cache(); /* what shows depends on the customer role: never cache the empty page */
		}
		return array();
	}
	/* 6.0.2: a product template draws the same product in a dozen widgets; look each one up once per request. */
	static $wpec_found = array();
	$is_manager = ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) );
	$active_sql = ( ! $is_manager ) ? ' AND product.activate_in_store = 1' : '';
	$is_editor = ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() );
	if ( $use_post_id ) {
		global $post;
		$lookup_key = 'post:' . ( ( isset( $post ) && isset( $post->ID ) ) ? (int) $post->ID : 0 );
	} else if ( 'NOPRODUCT' != $model_number ) {
		$lookup_key = 'model:' . $model_number;
	} else {
		$lookup_key = 'id:' . $product_id;
	}
	$lookup_key .= '|' . ( $is_manager ? 1 : 0 ) . '|' . ( $is_editor ? 1 : 0 ) . '|' . ( isset( $GLOBALS['ec_user']->user_level ) ? $GLOBALS['ec_user']->user_level : '' );
	if ( isset( $wpec_found[ $lookup_key ] ) ) {
		return $wpec_found[ $lookup_key ];
	}
	$products = array();
	$mysqli = new ec_db();
	if ( $use_post_id ) {
		if ( isset( $post ) && isset( $post->ID ) ) {
			$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.post_id = %d' . $active_sql, $post->ID ), '', '', '', '', '', '' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $active_sql is one of two fixed strings.
		}
		/* 6.0.2: only the editor and its preview fall back to a sample product ( a template being designed ). A visitor on a
		 * page that is not a product used to get the first product in the store ( with its structured data and analytics ),
		 * after the whole catalog was loaded to find it. */
		if ( 0 == count( $products ) && $is_editor ) {
			$products = $mysqli->get_product_list( ' WHERE product.activate_in_store = 1', ' ORDER BY product.product_id ASC', ' LIMIT 1', '' );
		}
	} else if ( 'NOPRODUCT' != $model_number ) {
		$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s' . $active_sql, $model_number ), '', '', '', '', '', '' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $active_sql is one of two fixed strings.
	} else if ( '' != $product_id ) {
		$products = $mysqli->get_product_list( $wpdb->prepare( ' WHERE product.product_id = %d' . $active_sql, $product_id ), '', '', '', '', '', '' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $active_sql is one of two fixed strings.
	}
	if ( ! is_array( $products ) ) {
		$products = array();
	}
	$wpec_found[ $lookup_key ] = $products;
	return $products;
}

/**
 * What a product shortcode or widget prints when it has no product to show ( 6.0.2 ).
 *
 * Visitors see nothing ( a deactivated product, or a template widget on a page that is not a product ); store managers and
 * the Elementor editor still see why the space is empty. Filter `wp_easycart_show_product_not_found` to change who sees it.
 */
function wp_easycart_print_product_not_found() {
	$show = ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) || ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) );
	if ( apply_filters( 'wp_easycart_show_product_not_found', $show ) ) {
		if ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() ) {
			/* 6.0.2: the store is limited to some customer roles and this person is outside them ( an editor who is not a store manager ). */
			echo esc_html__( 'Only some customer roles can view your store ( Settings › Products › Who can view the store ), so no product shows here for you.', 'wp-easycart' );
		} else {
			echo esc_attr__( 'Product not found.', 'wp-easycart' );
		}
	}
}

if ( ! function_exists( 'wp_easycart_store_is_restricted' ) ) {
	/**
	 * Whether Settings › Products › Who can view the store keeps the current visitor out of the store ( 6.0.2 ).
	 *
	 * The same rule as ec_storepage::display_store_page(): no restriction, an EasyCart admin account or one of the chosen
	 * customer roles may view it. Store managers ( manage_options, wpec_manager ) always may, so they can build and check the
	 * store's pages. Product widgets, product shortcodes and the Elementor context return no product while this is true.
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_store_is_restricted() {
		$roles = get_option( 'ec_option_restrict_store' );
		if ( ! $roles ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
			return false;
		}
		$level      = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) && isset( $GLOBALS['ec_user']->user_level ) ) ? (string) $GLOBALS['ec_user']->user_level : '';
		$restricted = ( 'admin' !== $level && ! in_array( $level, explode( '***', (string) $roles ), true ) );
		/**
		 * Whether the store is closed to this visitor.
		 *
		 * @since 6.0.2
		 *
		 * @param bool   $restricted True when the visitor's customer role is not among the chosen ones.
		 * @param string $level      The visitor's EasyCart user level ( '' when signed out ).
		 */
		return (bool) apply_filters( 'wp_easycart_store_is_restricted', $restricted, $level );
	}
}

if ( ! function_exists( 'wp_easycart_cart_state_text' ) ) {
	/**
	 * A value for the cart state as plain text ( tags removed, entities decoded ): the storefront inserts it as text.
	 *
	 * @since 6.0.2
	 *
	 * @param string $value Markup or text.
	 * @return string
	 */
	function wp_easycart_cart_state_text( $value ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
	}
}

if ( ! function_exists( 'wp_easycart_cart_state' ) ) {
	/**
	 * The current visitor's cart as the storefront's cart widgets show it ( 6.0.2 ): the same cart the mini cart reads.
	 *
	 * Answered, uncached, by the ec_ajax_cart_state request that ec-store.js makes for cart badges and mini carts on cached
	 * pages ( window.wpeasycart_refresh_cart_widgets() ); cart widgets can also call it while they render.
	 *
	 * @since 6.0.2
	 *
	 * @return array {
	 *     @type int    $count            Items in the cart ( quantities added up ).
	 *     @type string $subtotal_display The subtotal as the store shows it ( plain text ).
	 *     @type array  $items            Each line: title, quantity, price_display, link, image, cartitem_id, product_id.
	 * }
	 */
	function wp_easycart_cart_state() {
		$state = array(
			'count'            => 0,
			'subtotal_display' => '',
			'items'            => array(),
		);
		if ( ! function_exists( 'wpeasycart_session' ) || ! class_exists( 'ec_cart' ) || ! isset( $GLOBALS['currency'] ) ) {
			return $state;
		}
		if ( ! wpeasycart_session()->handle_session( false, false ) || ! isset( $GLOBALS['ec_cart_data'] ) ) {
			$state['subtotal_display'] = wp_easycart_cart_state_text( $GLOBALS['currency']->get_currency_display( 0 ) );
			return apply_filters( 'wp_easycart_cart_state', $state, null );
		}
		$cart                      = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$state['count']            = (int) $cart->total_items;
		$state['subtotal_display'] = wp_easycart_cart_state_text( $GLOBALS['currency']->get_currency_display( $cart->subtotal ) );
		if ( isset( $cart->cart ) && is_array( $cart->cart ) ) {
			foreach ( $cart->cart as $item ) {
				$is_deconetwork   = ! empty( $item->is_deconetwork );
				$title            = ( $is_deconetwork && isset( $item->deconetwork_name ) ) ? $item->deconetwork_name : $item->title;
				$unit_price       = ( $is_deconetwork && (int) $item->quantity > 0 ) ? $item->deconetwork_total / $item->quantity : $item->unit_price;
				$state['items'][] = array(
					'title'         => wp_easycart_cart_state_text( $title ),
					'quantity'      => (int) $item->quantity,
					'price_display' => wp_easycart_cart_state_text( $GLOBALS['currency']->get_currency_display( $unit_price ) ),
					'link'          => esc_url_raw( $item->get_title_link() ),
					'image'         => esc_url_raw( $item->get_image_url() ),
					'cartitem_id'   => (int) $item->cartitem_id,
					'product_id'    => (int) $item->product_id,
				);
			}
		}
		/**
		 * The cart state sent to the storefront's cart widgets.
		 *
		 * @since 6.0.2
		 *
		 * @param array        $state Count, subtotal_display and items ( plain text and URLs only ).
		 * @param ec_cart|null $cart  The cart, when the visitor has one.
		 */
		return apply_filters( 'wp_easycart_cart_state', $state, $cart );
	}
}

if ( ! function_exists( 'ec_ajax_cart_state' ) ) {
	/**
	 * The visitor's cart for cart badges, mini carts and the Elementor cart widgets ( 6.0.2 ).
	 *
	 * Read only and never cached. It needs no nonce: it only reads the cart of whoever asks ( their own session cookie ), so
	 * it keeps working on pages a cache served long after their nonces expired.
	 */
	function ec_ajax_cart_state() {
		nocache_headers();
		if ( isset( $_POST['language'] ) && function_exists( 'wp_easycart_language' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only: the language the answer's text is in.
			wp_easycart_language()->set_language( sanitize_text_field( wp_unslash( $_POST['language'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only: the language the answer's text is in.
		}
		wp_send_json( wp_easycart_cart_state() );
	}
}
add_action( 'wp_ajax_ec_ajax_cart_state', 'ec_ajax_cart_state' );
add_action( 'wp_ajax_nopriv_ec_ajax_cart_state', 'ec_ajax_cart_state' );

if ( ! function_exists( 'wp_easycart_product_details_no_cache' ) ) {
	/**
	 * Product detail shortcodes and Elementor widgets keep pages out of the page cache, as [ec_store] does ( 6.0.2 ).
	 *
	 * A cached copy served stale stock, prices and nonces: after a day every review sent from a cached page was refused.
	 */
	function wp_easycart_product_details_no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page cache plugins' own constant.
		}
		if ( ! defined( 'DONOTCDN' ) ) {
			define( 'DONOTCDN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the CDN plugins' own constant.
		}
	}
}

if ( ! function_exists( 'wp_easycart_send_nocache_headers' ) ) {
	/**
	 * Headers that keep browsers and proxies from storing the page ( 6.0.2, template_redirect on pages with members-only
	 * content, see wp_easycart_check_for_shortcode() ).
	 *
	 * @since 6.0.2
	 */
	function wp_easycart_send_nocache_headers() {
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}
}

if ( ! function_exists( 'wp_easycart_product_details_schema' ) ) {
	/**
	 * Product data for search engines from any product detail shortcode or widget ( 6.0.2 ). Printed once per product per
	 * page, so a template built from separate widgets gets it without the Product Meta widget.
	 *
	 * @param ec_product $product The product shown.
	 */
	function wp_easycart_product_details_schema( $product ) {
		if ( class_exists( 'wp_easycart_product_schema' ) ) {
			wp_easycart_product_schema::print_for( $product );
		}
	}
}

if ( ! function_exists( 'wp_easycart_product_details_image_size' ) ) {
	/**
	 * The size the product images shortcode asks WordPress for ( 6.0.2 ).
	 *
	 * 'small' ( the old default ) is not a WordPress size, so WordPress answered with the original upload and every
	 * thumbnail loaded the full-size file: it now means 'medium' ( not cropped ) unless the site registers 'small'.
	 * 'custom' uses its width x height ( WordPress picks the closest size it made ); without either it stays the full image.
	 *
	 * @param string $size   Size name.
	 * @param string $custom Width x height for 'custom', e.g. 600x400 ( 0 = any ).
	 * @return string|array
	 */
	function wp_easycart_product_details_image_size( $size, $custom = '' ) {
		if ( 'small' === $size && ! in_array( 'small', get_intermediate_image_sizes(), true ) ) {
			return 'medium';
		}
		if ( 'custom' === $size ) {
			if ( preg_match( '/^(\d+)x(\d+)$/', (string) $custom, $dimensions ) && ( (int) $dimensions[1] > 0 || (int) $dimensions[2] > 0 ) ) {
				return array( (int) $dimensions[1], (int) $dimensions[2] );
			}
			return 'full';
		}
		return $size;
	}
}

//[ec_product_details_images]
function load_ec_product_details_images( $atts ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'show_lightbox' => get_option( 'ec_option_show_large_popup' ),
		'show_thumbnails' => true,
		'show_image_hover' => get_option( 'ec_option_show_magnification' ),
		'image_size' => 'medium_large',
		'thumb_size' => 'small',
		'image_custom_size' => '',
		'thumb_custom_size' => '',
		'thumbnails_position' => 'column',
		'thumbnails_stack' => 'row',
	), $atts ) );
	$model_number = sanitize_text_field( $model_number );
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	$wpec_user_agent = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; /* 6.0.2: requests without one ( uptime checks ) warned */
	$ipad = (bool) strpos( $wpec_user_agent, 'iPad' );
	$iphone = (bool) strpos( $wpec_user_agent, 'iPhone' );
	$image_default_size = wp_easycart_product_details_image_size( $image_size, $image_custom_size );
	$thumb_default_size = wp_easycart_product_details_image_size( $thumb_size, $thumb_custom_size );
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_images.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_images.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_images.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_price]
function load_ec_product_details_price( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'show_price' => true,
		'show_list_price' => true,
		'price_font' => null,
		'price_color' => null,
		'list_price_font' => null,
		'list_price_color' => null,
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'show_price' => $show_price,
		'show_list_price' => $show_list_price,
		'price_font' => $price_font,
		'price_color' => $price_color,
		'list_price_font' => $list_price_font,
		'list_price_color' => $list_price_color,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	$wpec_user_agent = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; /* 6.0.2: requests without one ( uptime checks ) warned */
	$ipad = (bool) strpos( $wpec_user_agent, 'iPad' );
	$iphone = (bool) strpos( $wpec_user_agent, 'iPhone' );
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_price.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_price.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_price.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_title]
function load_ec_product_details_title( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'title_element' => 'h1',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'title_element' => $title_element,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_title.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_title.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_title.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_breadcrumbs]
function load_ec_product_details_breadcrumbs( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'breadcrumb_element' => 'div',
		'divider_character' => '/',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'breadcrumb_element' => $breadcrumb_element,
		'divider_character' => $divider_character,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	$storepageid = get_option( 'ec_option_storepage' );
	if ( function_exists( 'icl_object_id' ) ) {
		$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
	}
	$store_page = get_permalink( $storepageid );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS( );
		$store_page = $https_class->makeUrlHttps( $store_page );
	}
	if ( substr_count( $store_page, '?' ) ) {
		$permalink_divider = '&';
	} else {
		$permalink_divider = '?';
	}
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_breadcrumbs.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_breadcrumbs.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_breadcrumbs.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_rating]
function load_ec_product_details_rating( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_rating.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_rating.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_rating.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_stock]
function load_ec_product_details_stock( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_stock.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_stock.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_stock.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_description]
function load_ec_product_details_description( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_description.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_description.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_description.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_specifications]
function load_ec_product_details_specifications( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_specifications.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_specifications.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_specifications.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_customer_reviews]
function load_ec_product_details_customer_reviews( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'enable_review_list' => true,
		'enable_review_list_title' => true,
		'enable_review_item_title' => true,
		'enable_review_item_date' => true,
		'enable_review_item_user_name' => false,
		'enable_review_item_rating' => true,
		'enable_review_item_review' => true,
		'enable_review_form' => true,
		'enable_review_form_title' => true,
		'form_button_text' => wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_submit' ),
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'enable_review_list' => $enable_review_list,
		'enable_review_list_title' => $enable_review_list_title,
		'enable_review_item_title' => $enable_review_item_title,
		'enable_review_item_date' => $enable_review_item_date,
		'enable_review_item_user_name' => $enable_review_item_user_name,
		'enable_review_item_rating' => $enable_review_item_rating,
		'enable_review_item_review' => $enable_review_item_review,
		'enable_review_form' => $enable_review_form,
		'enable_review_form_title' => $enable_review_form_title,
		'form_button_text' => $form_button_text,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_customer_reviews.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_customer_reviews.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_customer_reviews.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_short_description]
function load_ec_product_details_short_description( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_short_description.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_short_description.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_short_description.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

if ( ! function_exists( 'wp_easycart_product_tab_panel_attributes' ) ) {
	/**
	 * The Description panel's extra attribute, from filter wpeasycart_description_content_initally_active. An extension
	 * answers with an inline style that hides the panel when another tab opens first. The templates used to print the
	 * answer through esc_attr(), which turned a quoted style ( style='display:none;' ) into one browsers ignore, so the
	 * Description panel stayed open beside the other tab. Hiding is the only thing the filter can ask for.
	 *
	 * @since 6.0.2
	 * @param string $value The filter's answer: '' keeps the panel open.
	 * @return string '' or ' style="display:none;"'.
	 */
	function wp_easycart_product_tab_panel_attributes( $value ) {
		$value = strtolower( preg_replace( '/\s+/', '', (string) $value ) );
		return ( false !== strpos( $value, 'display:none' ) ) ? ' style="display:none;"' : '';
	}
}

if ( ! function_exists( 'wp_easycart_product_tabs_takeover' ) ) {
	/**
	 * Whether an extension draws a product's tab area itself ( WP EasyCart Tabs 3: tab styles, tabs beside the photos ). It
	 * answers filter wpeasycart_product_details_tabs_takeover and listens to action wpeasycart_product_details_tabs_area. The
	 * product templates then leave out their tab list, build Description, Specifications and Customer Reviews exactly as
	 * before, and hand their HTML to that action instead of printing it ( wp_easycart_product_tabs_area() ). Nothing changes
	 * without such an extension, and theme copies of the templates never ask, so they keep the classic tabs.
	 *
	 * @since 6.0.2
	 * @param ec_product $product Product.
	 * @param int|string $rand    The tab list's id on this page.
	 * @param array|null $atts    Shortcode attributes ( show_description, show_specifications, show_customer_reviews ).
	 * @return bool
	 */
	function wp_easycart_product_tabs_takeover( $product, $rand, $atts = array() ) {
		if ( ! has_action( 'wpeasycart_product_details_tabs_area' ) ) {
			return false;
		}
		/**
		 * Draw this product's tab area in an extension.
		 *
		 * @since 6.0.2
		 * @param bool       $takeover False: the classic tab list.
		 * @param ec_product $product  Product.
		 * @param int|string $rand     The tab list's id on this page.
		 * @param array      $atts     Shortcode attributes.
		 */
		return (bool) apply_filters( 'wpeasycart_product_details_tabs_takeover', false, $product, $rand, is_array( $atts ) ? $atts : array() );
	}
}

if ( ! function_exists( 'wp_easycart_product_tabs_capture_gaps' ) ) {
	/**
	 * While an extension draws the tab area: whatever other code prints on the classic tab list hooks ( list items ), kept
	 * for the extension to place.
	 *
	 * @since 6.0.2
	 * @param int        $product_id Product.
	 * @param int|string $rand       The tab list's id on this page.
	 * @return string
	 */
	function wp_easycart_product_tabs_capture_gaps( $product_id, $rand ) {
		ob_start();
		do_action( 'wpeasycart_pre_description_tab', $product_id, $rand );
		do_action( 'wpeasycart_pre_specifications_tab', $product_id, $rand );
		do_action( 'wpeasycart_pre_customer_reviews_tab', $product_id, $rand );
		do_action( 'wpeasycart_addon_product_details_tab', $product_id, $rand );
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'wp_easycart_product_tabs_panel' ) ) {
	/**
	 * One built-in panel for an extension that draws the tab area: its tab name as the classic list shows it and the panel's
	 * HTML, unchanged ( the same classes and ids, so the review form, pagination and front-end editing keep working ).
	 *
	 * @since 6.0.2
	 * @param string     $key     description | specifications | reviews.
	 * @param string     $html    The panel.
	 * @param ec_product $product Product.
	 * @return array key, title, count ( reviews only, else null ), html.
	 */
	function wp_easycart_product_tabs_panel( $key, $html, $product ) {
		$phrases = array(
			'description'    => 'product_details_description',
			'specifications' => 'product_details_specifications',
			'reviews'        => 'product_details_customer_reviews',
		);
		return array(
			'key'   => $key,
			'title' => isset( $phrases[ $key ] ) ? wp_easycart_language()->get_text( 'product_details', $phrases[ $key ] ) : '',
			'count' => ( 'reviews' === $key && isset( $product->reviews ) && is_array( $product->reviews ) ) ? count( $product->reviews ) : null,
			'html'  => (string) $html,
		);
	}
}

if ( ! function_exists( 'wp_easycart_product_tabs_area' ) ) {
	/**
	 * Hand the tab area to the extension drawing it ( see wp_easycart_product_tabs_takeover() ).
	 *
	 * @since 6.0.2
	 * @param ec_product $product Product.
	 * @param int|string $rand    The tab list's id on this page.
	 * @param array      $panels  description / specifications / reviews ( wp_easycart_product_tabs_panel() ) for the built-in
	 *                            tabs this product shows, and addon_tabs ( wp_easycart_product_tabs_capture_gaps() ).
	 * @param array|null $atts    Shortcode attributes.
	 */
	function wp_easycart_product_tabs_area( $product, $rand, $panels, $atts = array() ) {
		ob_start();
		do_action( 'wpeasycart_addon_product_details_tab_content', $product->product_id, $rand );
		$panels['addon_panels'] = (string) ob_get_clean();
		if ( ! isset( $panels['addon_tabs'] ) ) {
			$panels['addon_tabs'] = '';
		}
		/**
		 * Draw the tab area.
		 *
		 * @since 6.0.2
		 * @param ec_product $product Product.
		 * @param int|string $rand    The tab list's id on this page.
		 * @param array      $panels  Built-in panels by key ( key, title, count, html ), plus addon_tabs / addon_panels: what
		 *                            other code printed on the classic hooks.
		 * @param array      $atts    Shortcode attributes.
		 */
		ob_start();
		do_action( 'wpeasycart_product_details_tabs_area', $product, $rand, $panels, is_array( $atts ) ? $atts : array() );
		$area = (string) ob_get_clean();
		if ( '' !== trim( $area ) ) {
			echo $area; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the taker's own markup.
			return;
		}
		// Nothing drew the area ( the taker declined late ): the classic tab list, so the built-in panels never vanish.
		echo wp_easycart_product_tabs_classic( $product, $rand, $panels ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built in wp_easycart_product_tabs_classic().
	}
}

if ( ! function_exists( 'wp_easycart_product_tabs_classic' ) ) {
	/**
	 * The classic tab list and panels from a takeover's panels ( the fallback when no extension drew the area ). FREE's
	 * ec-store.js switches them as it does the template's own list.
	 *
	 * @since 6.0.2
	 * @param ec_product $product Product.
	 * @param int|string $rand    The tab list's id on this page.
	 * @param array      $panels  See wp_easycart_product_tabs_area().
	 * @return string
	 */
	function wp_easycart_product_tabs_classic( $product, $rand, $panels ) {
		$classes = array(
			'description'    => 'ec_description',
			'specifications' => 'ec_specifications',
			'reviews'        => 'ec_customer_reviews',
		);
		$list    = '';
		$html    = '';
		$first   = true;
		foreach ( $classes as $key => $class ) {
			if ( empty( $panels[ $key ] ) || ! is_array( $panels[ $key ] ) ) {
				continue;
			}
			$title = (string) $panels[ $key ]['title'] . ( null !== $panels[ $key ]['count'] ? ' (' . (int) $panels[ $key ]['count'] . ')' : '' );
			$list .= '<li class="ec_details_tab ec_details_tab_' . esc_attr( $product->product_id ) . '_' . esc_attr( $rand ) . ' ' . $class . ( $first ? ' ec_active' : '' ) . '">' . esc_html( $title ) . '</li>';
			$html .= (string) $panels[ $key ]['html'];
			$first = false;
		}
		$list .= isset( $panels['addon_tabs'] ) ? (string) $panels['addon_tabs'] : '';
		$html .= isset( $panels['addon_panels'] ) ? (string) $panels['addon_panels'] : '';
		if ( '' === $list ) {
			return $html;
		}
		return '<ul class="ec_details_tabs" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $rand ) . '">' . $list . '</ul>' . $html;
	}
}

//[ec_product_details_tabs]
function load_ec_product_details_tabs( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'show_customer_reviews' => null,
		'show_description' => null,
		'show_specifications' => null,
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'show_customer_reviews' => $show_customer_reviews,
		'show_description' => $show_description,
		'show_specifications' => $show_specifications,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_tabs.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_tabs.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_tabs.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_social]
function load_ec_product_details_social( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_social.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_social.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_social.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_manufacturer]
function load_ec_product_details_manufacturer( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'label_text' => wp_easycart_language( )->get_text( 'product_details', 'product_details_manufacturer' ),
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'label_text' => $label_text,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		/* 6.0.2: only for a product with a manufacturer ( a "Manufacturer:" label with nothing after it showed otherwise ). */
		$has_manufacturer = ( ! empty( $product->manufacturer_id ) && '' !== trim( (string) $product->manufacturer_name ) );
		if ( $has_manufacturer && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_manufacturer.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_manufacturer.php' );
		} elseif ( $has_manufacturer ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_manufacturer.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_sku]
function load_ec_product_details_sku( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_sku.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_sku.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_sku.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_category]
function load_ec_product_details_category( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'categories_element' => 'div',
		'categories_label' => wp_easycart_language( )->get_text( 'product_details', 'product_details_categories' ),
		'categories_divider' => ',',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'categories_element' => $categories_element,
		'categories_label' => $categories_label,
		'categories_divider' => $categories_divider,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_category.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_category.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_category.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_meta]
function load_ec_product_details_meta( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_meta.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_meta.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_meta.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_featured_products]
function load_ec_product_details_featured_products( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'enable_product1' => true,
		'enable_product2' => true,
		'enable_product3' => true,
		'enable_product4' => true,
		'product_visible_options' => get_option( 'ec_option_default_product_visible_options' ),
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$atts = array(
		'enable_product1' => $enable_product1,
		'enable_product2' => $enable_product2,
		'enable_product3' => $enable_product3,
		'enable_product4' => $enable_product4,
		'product_visible_options' => $product_visible_options,
	);
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$this_product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $this_product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_products.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_products.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_featured_products.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_product_details_addtocart]
function load_ec_product_details_addtocart( $attributes ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'product_id' => 'NOPRODUCTID',
		'enable_your_price' => true,
		'enable_quantity' => true,
		'minus_icon' => '',
		'plus_icon' => '',
	), $attributes ) );
	$model_number = sanitize_text_field( $model_number );
	$minus_icon = trim( $minus_icon );
	$plus_icon = trim( $plus_icon );
	/* 6.0.2: an uploaded SVG icon chosen in the Elementor widget arrives as markup Elementor drew ( set around this call only ). */
	$minus_icon_html = '';
	$plus_icon_html = '';
	if ( isset( $GLOBALS['wp_easycart_addtocart_icon_html'] ) && is_array( $GLOBALS['wp_easycart_addtocart_icon_html'] ) ) {
		$minus_icon_html = ( isset( $GLOBALS['wp_easycart_addtocart_icon_html']['minus'] ) ) ? (string) $GLOBALS['wp_easycart_addtocart_icon_html']['minus'] : '';
		$plus_icon_html = ( isset( $GLOBALS['wp_easycart_addtocart_icon_html']['plus'] ) ) ? (string) $GLOBALS['wp_easycart_addtocart_icon_html']['plus'] : '';
		unset( $GLOBALS['wp_easycart_addtocart_icon_html'] );
	}
	$atts = array();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $product_id );
	ob_start();
	$GLOBALS['wpeasycart_prod_details_count'] = ( isset( $GLOBALS['wpeasycart_prod_details_count'] ) ) ? (int) $GLOBALS['wpeasycart_prod_details_count'] + 1 : 1;
	$wpeasycart_addtocart_shortcode_rand = (int) $GLOBALS['wpeasycart_prod_details_count'];
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_add_to_cart.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_add_to_cart.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_add_to_cart.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_addtocart]
function load_ec_addtocart( $atts ) {
	wp_easycart_product_details_no_cache();
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'use_post_id' => false,
		'model_number' => 'NOPRODUCT',
		'productid' => 'NOPRODUCTID',
		'enable_quantity' => 1,
		'button_width' => false,
		'button_font' => false,
		'button_bg_color' => false,
		'button_text_color' => false,
		'background_add' => false,
	), $atts ) );
	$productid = (int) $productid;
	ob_start();
	$products = wp_easycart_get_shortcode_product_list( $use_post_id, $model_number, $productid );
	if ( count( $products ) > 0 ) {
		$product = new ec_product( $products[0], 0, 1, 1 );
		wp_easycart_product_details_schema( $product );
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_add_to_cart_shortcode.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_add_to_cart_shortcode.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_add_to_cart_shortcode.php' );
		}
	} else {
		wp_easycart_print_product_not_found();
	}
	return ob_get_clean();
}

//[ec_cartdisplay]
function load_ec_cartdisplay( $atts ) {
	ob_start();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cartdisplay_shortcode.php' ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cartdisplay_shortcode.php' );
	} else {
		include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cartdisplay_shortcode.php' );
	}
	return ob_get_clean();
}

// [ec_cart_count]
function load_ec_cart_count( $atts ) {
	ob_start();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	echo esc_attr( $cart->total_items );
	return ob_get_clean();
}

// [ec_cart_icon]
function load_ec_cart_icon( $atts ) {
	extract( shortcode_atts( array(
		'is_elementor' => false,
		'cart_icon' => 'fas fa-shopping-cart',
		'show_quantity' => 1,
		'cart_link_url' => '#',
		'cart_link_external' => 0,
		'cart_link_nofollow' => 0,
	), $atts ) );
	/**
	 * The cart icon's markup ( 6.0.2 ). The Elementor Cart Icon widget uses it for an uploaded SVG icon, or when Elementor
	 * draws font icons as inline SVG ( Font Awesome's stylesheet is then not loaded, so an `<i>` would be blank ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $icon_html Default `<i class="…"></i>`.
	 * @param string $cart_icon The icon class from the shortcode.
	 * @param array  $atts      Shortcode attributes.
	 */
	$cart_icon_html = apply_filters( 'wp_easycart_cart_icon_html', '<i class="' . esc_attr( $cart_icon ) . '"></i>', $cart_icon, $atts );
	ob_start();
	echo '<a href="' . esc_url( $cart_link_url ) . '"';
	if ( $cart_link_external ) {
		echo ' target="_blank"';
	}
	if ( $cart_link_nofollow ) {
		echo ' rel="nofollow"';
	}
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the icon markup is escaped above ( default ) or comes from Elementor's Icons_Manager through the filter.
	echo '>
			<div class="wp-easycart-widget-cart-icon-area">
				<div class="wp-easycart-widget-cart-icon">
					' . $cart_icon_html . '
				</div>';
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( $show_quantity ) {
		echo '
				<div class="wp-easycart-widget-cart-quantity" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-cart-icon-quantity' ) ) . '">0</div>';
	}
	echo '
			</div>';
	echo '</a>';
	return ob_get_clean();
}

if ( ! function_exists( 'wp_easycart_shortcode_id_list' ) ) {
	/**
	 * A shortcode's comma separated ID list as positive integers ( "3,7" ), or '' when it names none. Blocks saved before
	 * 6.0.2 wrote "undefined" for some lists ( Membership productid, Store Table categoryid ).
	 *
	 * @since 6.0.2
	 * @param mixed $value The attribute: comma list, array or "undefined".
	 * @return string
	 */
	function wp_easycart_shortcode_id_list( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ',', $value );
		}
		$ids = array();
		foreach ( explode( ',', (string) $value ) as $part ) {
			$part = trim( $part );
			if ( '' !== $part && preg_match( '/^[0-9]+$/', $part ) && (int) $part > 0 ) {
				$ids[] = (int) $part;
			}
		}
		return implode( ',', array_unique( $ids ) );
	}
}

if ( ! function_exists( 'wp_easycart_shortcode_block_render' ) ) {
	/**
	 * The WP EasyCart block ( wp-easycart/shortcode ) saved its shortcode with two attribute names that never existed until
	 * 6.0.2: Membership blocks said productid="undefined" ( the chosen products are in membership_products ) and Store Table
	 * blocks categoryid="undefined". Published pages get the chosen values at render time, before the page is saved again
	 * ( the editor then writes the fixed shortcode ).
	 *
	 * @since 6.0.2
	 * @param string $block_content The block's saved HTML.
	 * @param array  $block         The parsed block.
	 * @return string
	 */
	function wp_easycart_shortcode_block_render( $block_content, $block ) {
		if ( ! is_array( $block ) || empty( $block['blockName'] ) || 'wp-easycart/shortcode' !== $block['blockName'] || false === strpos( (string) $block_content, '="undefined"' ) ) {
			return $block_content;
		}
		$attrs = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();
		$type  = isset( $attrs['shortcode_type'] ) ? (string) $attrs['shortcode_type'] : 'ec_store';
		if ( 'ec_membership' === $type ) {
			$products      = wp_easycart_shortcode_id_list( isset( $attrs['membership_products'] ) ? $attrs['membership_products'] : '' );
			$block_content = str_replace( 'productid="undefined"', 'productid="' . $products . '"', $block_content );
		} elseif ( 'ec_store_table' === $type ) {
			$categories    = wp_easycart_shortcode_id_list( isset( $attrs['store_table_category'] ) ? $attrs['store_table_category'] : '' );
			$block_content = str_replace( 'categoryid="undefined"', 'categoryid="' . $categories . '"', $block_content );
		}
		return $block_content;
	}
	add_filter( 'render_block', 'wp_easycart_shortcode_block_render', 10, 2 );
}

/**
 * [ec_plan_table id="4"]: the pricing table of a plan group ( WP EasyCart PRO, 6.0.3 ). WP EasyCart PRO draws it through
 * filter wp_easycart_plan_table_html; without it visitors see nothing and store managers a note saying what is missing.
 *
 * @since 6.0.3
 * @param array $atts id ( plan group ), interval ( m | y, the one the table opens on ), layout ( cards | table, else the group's ),
 *                    head ( 0 hides the group's heading and intro ).
 * @return string
 */
function load_ec_plan_table( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'       => 0,
			'interval' => '',
			'layout'   => '',
			'head'     => '',
		),
		$atts,
		'ec_plan_table'
	);
	$atts['id']       = (int) $atts['id'];
	$atts['interval'] = in_array( $atts['interval'], array( 'm', 'y' ), true ) ? $atts['interval'] : '';
	$atts['layout']   = in_array( $atts['layout'], array( 'cards', 'table' ), true ) ? $atts['layout'] : '';
	$atts['head']     = ( '0' === (string) $atts['head'] ) ? '0' : '';
	/**
	 * The pricing table of a plan group.
	 *
	 * @since 6.0.3
	 * @param string $html '' until WP EasyCart PRO draws it.
	 * @param array  $atts id, interval, layout, head.
	 */
	$html = (string) apply_filters( 'wp_easycart_plan_table_html', '', $atts );
	if ( '' === $html && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) ) {
		$html = '<p class="wpec-plans-note">' . esc_html__( 'This pricing table shows once WP EasyCart PRO is active and licensed, and the plan group has tiers. Only store managers see this note.', 'wp-easycart' ) . '</p>';
	}
	return $html;
}

//[ec_membership productid=''][/ec_membership]
function load_ec_membership( $atts, $content = NULL ) {
	extract( shortcode_atts( array(
		'productid' => '',
		'userroles' => ''
	), $atts ) );
	/* 6.0.2: product IDs only ( blocks saved before 6.0.2 said productid="undefined" ), roles without stray spaces. */
	$productid = wp_easycart_shortcode_id_list( $productid );
	$userroles = implode( ',', array_filter( array_map( 'trim', explode( ',', (string) $userroles ) ), 'strlen' ) );

	/* 6.0.2: what shows depends on who is looking, so no page cache or CDN may keep the page. */
	if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
		wp_easycart_product_details_no_cache();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		return "<h4>MEMBER AND NON MEMBER CONTENT SHOWN TO ADMIN USER</h4><hr />" . do_shortcode( $content ) . "<hr />";

	} else if ( $GLOBALS['ec_user']->user_id ) {
		$db = new ec_db();
		$is_member = false;

		if ( $productid != '' ) {
			$is_member = function_exists( 'wp_easycart_user_has_membership' ) ? wp_easycart_user_has_membership( $GLOBALS['ec_user']->user_id, $productid ) : $db->has_membership_product_ids( $productid ); /* 6.0.2: one rule ( D5 ), as the page lock */
		}

		if ( $userroles != '' ) {
			$user_role_array = explode( ',', $userroles );

			if ( in_array( $GLOBALS['ec_user']->user_level, $user_role_array ) ) {
				$is_member = true;
			}
		}

		if ( $is_member ) {
			return do_shortcode( $content );
		} else {
			return "";
		}
	}
}

//[ec_membership_alt productid=''][/ec_membership_alt]
function load_ec_membership_alt( $atts, $content = NULL ) {
	extract( shortcode_atts( array(
		'productid' => '',
		'userroles' => ''
	), $atts ) );
	/* 6.0.2: product IDs only ( blocks saved before 6.0.2 said productid="undefined" ), roles without stray spaces. */
	$productid = wp_easycart_shortcode_id_list( $productid );
	$userroles = implode( ',', array_filter( array_map( 'trim', explode( ',', (string) $userroles ) ), 'strlen' ) );

	/* 6.0.2: what shows depends on who is looking, so no page cache or CDN may keep the page. */
	if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
		wp_easycart_product_details_no_cache();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		return "<h4>NON-MEMBER CONTENT (WORDPRESS ADMIN DISPLAY ONLY)</h4><hr />" . do_shortcode( $content ) . "<hr />";

	} else if ( $GLOBALS['ec_user']->user_id ) {
		$db = new ec_db();
		$is_member = false;

		if ( $productid != '' ) {
			$is_member = function_exists( 'wp_easycart_user_has_membership' ) ? wp_easycart_user_has_membership( $GLOBALS['ec_user']->user_id, $productid ) : $db->has_membership_product_ids( $productid ); /* 6.0.2: one rule ( D5 ), as the page lock */
		}

		if ( $userroles != '' ) {
			$user_role_array = explode( ',', $userroles );

			if ( in_array( $GLOBALS['ec_user']->user_level, $user_role_array ) ) {
				$is_member = true;
			}
		}


		if ( ! $is_member ) {
			return do_shortcode( $content );
		} else {
			return "";
		}

	} else {
		return do_shortcode( "[ec_account redirect='" . get_the_ID() . "']" ) . do_shortcode( $content );
	}
}

//[ec_store_table]
function load_ec_store_table_display( $atts ) {
	global $wpdb;
	extract( shortcode_atts( array(
		'productid' => '',
		'menuid' => '',
		'submenuid' => '',
		'subsubmenuid' => '',
		'categoryid' => '',
		'labels' => 'Model Number,Product Name,Price,',
		'columns' => 'model_number,title,price,details_link',
		'view_details' => 'VIEW DETAILS'
	), $atts ) );
	/* 6.0.2: ID lists hold IDs only ( blocks saved before 6.0.2 said categoryid="undefined", which added category 0 to the query ). */
	$productid    = wp_easycart_shortcode_id_list( $productid );
	$menuid       = wp_easycart_shortcode_id_list( $menuid );
	$submenuid    = wp_easycart_shortcode_id_list( $submenuid );
	$subsubmenuid = wp_easycart_shortcode_id_list( $subsubmenuid );
	$categoryid   = wp_easycart_shortcode_id_list( $categoryid );

	$label_start = explode( ",", $labels );
	$columns_start = explode( ",", $columns );

	$columns = array();
	$labels = array();

	for ( $k = 0; $k < count( $columns_start ); $k++ ) {
		if ( $columns_start[$k] != '0' ) {
			$columns[] = $columns_start[$k];
			$labels[] = $label_start[$k];
		}
	}

	$storepageid = get_option('ec_option_storepage');

	if ( function_exists( 'icl_object_id' ) ) {
		$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
	}

	$storepage = get_permalink( $storepageid );

	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$storepage = $https_class->makeUrlHttps( $storepage );
	}

	if ( substr_count( $storepage, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}

	$product_ids = array();
	$menu_ids = array();
	$submenu_ids = array();
	$subsubmenu_ids = array();
	$category_ids = array();

	if ( $productid != '' ) {
		$product_ids = explode( ",", $productid );
	}

	if ( $menuid != '' ) {
		$menu_ids = explode( ",", $menuid );
	}

	if ( $submenuid != '' ) {
		$submenu_ids = explode( ",", $submenuid );
	}

	if ( $subsubmenuid != '' ) {
		$subsubmenu_ids = explode( ",", $subsubmenuid );
	}

	if ( $categoryid != '' ) {
		$category_ids = explode( ",", $categoryid );
	}

	$has_added_to_where = false;
	$where_query = "";
	if ( count( $product_ids ) > 0 || count( $menu_ids ) > 0 || count( $submenu_ids ) > 0 || count( $subsubmenu_ids ) > 0 || count( $category_ids ) > 0 ) {
		$where_query = " WHERE";
	}

	if ( count( $product_ids ) > 0 ) {
		if ( ! $has_added_to_where ) {
			$where_query .= " (";
		} else {
			$where_query .= " OR (";
		}
		for ( $i = 0; $i < count( $product_ids ); $i++ ) {
			if ( $i > 0 ) {
				$where_query .= " OR";
			}
			$where_query .= $wpdb->prepare( " product.product_id = %d", (int) $product_ids[$i] );
		}
		$where_query .= ")";
		$has_added_to_where = true;
	}

	if ( count( $menu_ids ) > 0 ) {
		if ( ! $has_added_to_where ) {
			$where_query .= " (";
		} else {
			$where_query .= " OR (";
		}
		for ( $i=0; $i<count( $menu_ids ); $i++ ) {
			if ( $i > 0 ) {
				$where_query .= " OR";
			}
			$where_query .= $wpdb->prepare( " ( product.menulevel1_id_1 = %d OR product.menulevel2_id_1 = %d OR product.menulevel3_id_1 = %d )", $menu_ids[$i], $menu_ids[$i], $menu_ids[$i] );
		}
		$where_query .= ")";
		$has_added_to_where = true;
	}

	if ( count( $submenu_ids ) > 0 ) {
		if ( ! $has_added_to_where ) {
			$where_query .= " (";
		} else {
			$where_query .= " OR (";
		}
		for ( $i=0; $i<count( $submenu_ids ); $i++ ) {
			if ( $i > 0 ) {
				$where_query .= " OR";
			}
			$where_query .= $wpdb->prepare( " ( product.menulevel1_id_2 = %d OR product.menulevel2_id_2 = %d OR product.menulevel3_id_2 = %d )", $submenu_ids[$i], $submenu_ids[$i], $submenu_ids[$i] );
		}
		$where_query .= ")";
		$has_added_to_where = true;
	}

	if ( count( $subsubmenu_ids ) > 0 ) {
		if ( !$has_added_to_where )
			$where_query .= " (";
		else
			$where_query .= " OR (";

		for ( $i=0; $i<count( $subsubmenu_ids ); $i++ ) {
			if ( $i > 0 ) {
				$where_query .= " OR";
			}
			$where_query .= $wpdb->prepare( " ( product.menulevel1_id_3 = %d OR product.menulevel2_id_3 = %d OR product.menulevel3_id_3 = %d )", $subsubmenu_ids[$i], $subsubmenu_ids[$i], $subsubmenu_ids[$i] );
		}
		$where_query .= ")";
		$has_added_to_where = true;
	}

	if ( count( $category_ids ) > 0 ) {
		if ( ! $has_added_to_where ) {
			$where_query .= " (";
		} else {
			$where_query .= " OR (";
		}
		for ( $i=0; $i<count( $category_ids ); $i++ ) {
			if ( $i > 0 ) {
				$where_query .= " OR";
			}
			$where_query .= $wpdb->prepare( " ec_categoryitem.category_id = %d", $category_ids[$i] );
		}
		$where_query .= ")";
		$has_added_to_where = true;
	}
	$order_query = " ORDER BY product.title ASC";
	$limit_query = "";
	$session_id = $GLOBALS['ec_cart_id'];

	$db = new ec_db();
	$products = $db->get_product_list( $where_query, $order_query, $limit_query, $session_id );

	ob_start();
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_store_table_display.php' ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_store_table_display.php' );
	} else {
		include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_store_table_display.php' );
	}
	return ob_get_clean();
}

//[ec_category_view]
function load_ec_category_view( $atts ) {
	extract( shortcode_atts( array(
		'parentid' => '0',
		'groupid'  => '',
		'columns'  => 2
	), $atts ) );
	/* 6.0.2: the Category Grid block saves groupid ( the same choices as parentid: 0 featured, -1 top level, a category's
	 * children ); it was ignored, so every grid showed the featured categories. parentid still wins when both are given. */
	if ( ( ! is_array( $atts ) || ! isset( $atts['parentid'] ) ) && '' !== trim( (string) $groupid ) ) {
		$parentid = $groupid;
	}
	$parentid = (int) $parentid;
	if ( $parentid < -1 ) {
		$parentid = 0;
	}
	$columns = max( 1, (int) $columns );

	ob_start();
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_category_view.php' ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_category_view.php' );
	} else {
		include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_category_view.php' );
	}
	return ob_get_clean();
}

//[ec_categories]
function load_ec_categories( $atts ) {
	if ( !defined( 'DONOTCACHEPAGE' ) ) {
		define( "DONOTCACHEPAGE", true );
	}
	if ( ! defined( 'DONOTCDN' ) ) {
		define('DONOTCDN', true);
	}
	extract( shortcode_atts( array(
		'menuid' => 'NOMENU',
		'submenuid' => 'NOSUBMENU',
		'subsubmenuid' => 'NOSUBSUBMENU',
		'manufacturerid' => 'NOMANUFACTURER',
		'groupid' => 'NOGROUP',
		'modelnumber' => 'NOMODELNUMBER',
		'language' => 'NONE'
	), $atts ) );
	$language = strtoupper( esc_attr( sanitize_text_field( $language ) ) );
	$modelnumber = sanitize_text_field( $modelnumber );

	if ( 'NOMANUFACTURER' !== $manufacturerid ) {
		$clean = implode( ',', array_filter( array_map( 'absint', explode( ',', (string) $manufacturerid ) ) ) );
		$manufacturerid = ( '' !== $clean ) ? $clean : 'NOMANUFACTURER';
	}
	if ( 'NOGROUP' !== $groupid ) {
		/* 6.0.2: -1 is "top level categories" ( ec_categorylist ); absint() made it category 1. */
		if ( '-1' === trim( (string) $groupid ) ) {
			$groupid = -1;
		} else {
			$clean = wp_easycart_shortcode_id_list( $groupid );
			$groupid = ( '' !== $clean ) ? $clean : 'NOGROUP';
		}
	}

	if ( $language != 'NONE' ) {
		wp_easycart_language()->update_selected_language( $language );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = $language;
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	$GLOBALS['ec_store_shortcode_options'] = array( $menuid, $submenuid, $subsubmenuid, $manufacturerid, $groupid, $modelnumber );

	ob_start();
	$store_page = new ec_storepage( $menuid, $submenuid, $subsubmenuid, $manufacturerid, $groupid, $modelnumber );
	$store_page->display_category_page();
	return ob_get_clean();
}

if ( ! function_exists( 'wp_easycart_print_form_query_inputs' ) ) {
	/**
	 * Prints a hidden input for each query-string argument of a GET form's action ( 6.0.2 ).
	 *
	 * A browser drops the query string of a GET form's action and sends only the form's fields, so a search posted to a
	 * store page at `?page_id=12` ( plain permalinks ) or `?lang=de` ( WPML language as a parameter ) landed on the front
	 * page, or in the default language. The form's action itself stays as it is.
	 *
	 * @since 6.0.2
	 *
	 * @param string $url     The form's action URL ( raw, not HTML-escaped ).
	 * @param array  $exclude Arguments the form sends itself.
	 */
	function wp_easycart_print_form_query_inputs( $url, $exclude = array( 'ec_search' ) ) {
		$query = wp_parse_url( (string) $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return;
		}
		$args = array();
		wp_parse_str( $query, $args );
		foreach ( $args as $name => $value ) {
			if ( in_array( (string) $name, (array) $exclude, true ) || ! is_scalar( $value ) ) {
				continue;
			}
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		}
	}
}

//[ec_search]
function load_ec_search( $atts ) {
	extract( shortcode_atts( array(
		'label' => 'Search',
		'postid' => false
	), $atts ) );

	// Translate if needed
	$label = wp_easycart_language()->convert_text( $label );

	if ( $postid ) {
		$storepageid = $postid;
	} else {
		$storepageid = get_option( 'ec_option_storepage' );
	}

	if ( function_exists( 'icl_object_id' ) ) {
		$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
	}
	$store_page = get_permalink( $storepageid );

	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$store_page = $https_class->makeUrlHttps( $store_page );
	}

	if ( substr_count( $store_page, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}

	ob_start();
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_search_widget.php' ) ) {
		include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_search_widget.php' );
	} else {
		include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_search_widget.php' );
	}
	return ob_get_clean();
}

function ec_plugins_loaded() {
	/* Admin Form Actions */
	if ( ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && isset( $_GET['ec_action'] ) && isset( $_GET['ec_language'] ) && $_GET['ec_action'] == "export-language" ) {
		wp_easycart_language()->export_language( sanitize_key( $_GET['ec_language'] ) );
		die();
	}
}

function ec_footer_load() {
	if ( get_option( 'ec_option_enable_newsletter_popup' ) && !isset( $_COOKIE['ec_newsletter_popup'] ) ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_newsletter_popup.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_newsletter_popup.php' );
		} else {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_newsletter_popup.php' );
		}
	}
}

add_action( 'wp', 'load_ec_pre' );
add_action( 'wp_enqueue_scripts', 'ec_load_css' );
add_action( 'wp_enqueue_scripts', 'ec_load_js' );
add_action( 'send_headers', 'ec_custom_headers' );
add_action( 'plugins_loaded', 'ec_plugins_loaded' );
add_action( 'wp_footer', 'ec_footer_load' );

if ( !is_admin() || wp_doing_ajax() || ( isset( $_GET['action'] ) && $_GET['action'] == 'elementor' ) ) {
	add_shortcode( 'ec_store', 'load_ec_store' );
	add_shortcode( 'ec_cart', 'load_ec_cart' );
	add_shortcode( 'ec_account', 'load_ec_account' );
	add_shortcode( 'ec_account_login', 'load_ec_account_login' );
	add_shortcode( 'ec_account_forgot', 'load_ec_account_forgot' );
	add_shortcode( 'ec_account_register', 'load_ec_account_register' );
	add_shortcode( 'ec_product', 'load_ec_product' );
	add_shortcode( 'ec_product_details_images', 'load_ec_product_details_images' );
	add_shortcode( 'ec_product_details_price', 'load_ec_product_details_price' );
	add_shortcode( 'ec_product_details_title', 'load_ec_product_details_title' );
	add_shortcode( 'ec_product_details_breadcrumbs', 'load_ec_product_details_breadcrumbs' );
	add_shortcode( 'ec_product_details_rating', 'load_ec_product_details_rating' );
	add_shortcode( 'ec_product_details_stock', 'load_ec_product_details_stock' );
	add_shortcode( 'ec_product_details_description', 'load_ec_product_details_description' );
	add_shortcode( 'ec_product_details_specifications', 'load_ec_product_details_specifications' );
	add_shortcode( 'ec_product_details_customer_reviews', 'load_ec_product_details_customer_reviews' );
	add_shortcode( 'ec_product_details_short_description', 'load_ec_product_details_short_description' );
	add_shortcode( 'ec_product_details_social', 'load_ec_product_details_social' );
	add_shortcode( 'ec_product_details_manufacturer', 'load_ec_product_details_manufacturer' );
	add_shortcode( 'ec_product_details_category', 'load_ec_product_details_category' );
	add_shortcode( 'ec_product_details_meta', 'load_ec_product_details_meta' );
	add_shortcode( 'ec_product_details_featured_products', 'load_ec_product_details_featured_products' );
	add_shortcode( 'ec_product_details_addtocart', 'load_ec_product_details_addtocart' );
	add_shortcode( 'ec_product_details_tabs', 'load_ec_product_details_tabs' );
	add_shortcode( 'ec_product_details_sku', 'load_ec_product_details_sku' );
	add_shortcode( 'ec_addtocart', 'load_ec_addtocart' );
	add_shortcode( 'ec_cartdisplay', 'load_ec_cartdisplay' );
	add_shortcode( 'ec_cart_count', 'load_ec_cart_count' );
	add_shortcode( 'ec_cart_icon', 'load_ec_cart_icon' );
	add_shortcode( 'ec_membership', 'load_ec_membership' );
	add_shortcode( 'ec_membership_alt', 'load_ec_membership_alt' );
	add_shortcode( 'ec_store_table', 'load_ec_store_table_display' );
	add_shortcode( 'ec_category_view', 'load_ec_category_view' );
	add_shortcode( 'ec_categories', 'load_ec_categories' );
	add_shortcode( 'ec_search', 'load_ec_search' );
	add_shortcode( 'ec_plan_table', 'load_ec_plan_table' ); /* 6.0.3: a WP EasyCart PRO plan group's pricing table */
}

add_filter( 'widget_text', 'do_shortcode');

add_action( 'wp_head', 'wpeasycart_seo_tags' );
add_action('wp_head', 'ec_theme_head_data');
add_action( 'wp_head', 'wpeasycart_order_completed' );
function wpeasycart_order_completed() {
	// Checkout Success Check.
	if ( isset( $_GET['ec_page'] ) && $_GET['ec_page'] == "checkout_success" && isset( $_GET['order_id'] ) ) {
		// Try and get order and run action
		$ec_db = new ec_db_admin();
		$order_id = (int) $_GET['order_id'];
		if ( $GLOBALS['ec_cart_data']->cart_data->is_guest ) {
			$order_row = $ec_db->get_guest_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->guest_key );
		} else {
			$order_row = $ec_db->get_order_row( $order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
		}
		if ( $order_row ) { // order found and valid for user
			$order = new ec_orderdisplay( $order_row, true );
			do_action( 'wpeasycart_order_success_pre', $order_id, $order_row, $order->orderdetails );
		}
	}
}

add_action( 'wp_enqueue_scripts', 'ec_load_dashicons' );
function ec_load_dashicons() {
	if ( apply_filters( 'wp_easycart_load_css_scripts', true ) ) {
		wp_enqueue_style( 'dashicons' );
	}
}

//////////////////////////////////////////////
//UPDATE FUNCTIONS
//////////////////////////////////////////////

function wpeasycart_copyr( $source, $dest ) {

	// Check for symlinks
	if ( is_link( $source ) ) {
		return symlink( readlink( $source ), $dest );
	}

	// Simple copy for a file
	if ( is_file( $source ) ) {
		$success = copy( $source, $dest );
		if ( $success ) {
		  return true;
		} else {
			$err_message = "wpeasycart - error backing up " . $source . ". Updated halted.";
			exit( esc_attr( $err_message ) );
		}
	}

	// Make destination directory
	if ( !is_dir( $dest ) ) {
		$success = mkdir( $dest, 0755 );
		if ( !$success ) {
			$err_message = "wpeasycart - error creating backup directory: " . $dest . ". Updated halted.";
			exit( esc_attr( $err_message ) );
		}
	}

	// Loop through the folder
	$dir = dir( $source );
	while ( false !== $entry = $dir->read() ) {
		// Skip pointers
		if ($entry == '.' || $entry == '..') {
			continue;
		}

		// Deep copy directories
		wpeasycart_copyr( "$source/$entry", "$dest/$entry" ); // <------- defines wpeasycart copy action
	}

	// Clean up
	$dir->close();
	return true;
}

function wpeasycart_backup() {
	// Test for data folder
	if ( !file_exists( EC_PLUGIN_DATA_DIRECTORY . "/" ) ) {
		echo "YOU DO NOT HAVE A WP EASYCART DATA FOLDER, PLEASE <a href=\"http://www.wpeasycart.com/plugin-update-help/\" target=\"_blank\">CLICK HERE TO READ HOW TO PREVENT DATA LOSS DURING THE UPDATE</a>";
		die();
	}
}

function ec_recursive_remove_directory( $directory, $empty=FALSE ) {
	 // if the path has a slash at the end we remove it here
	 if ( substr( $directory, -1 ) == '/' )
		 $directory = substr( $directory, 0, -1);

	 // if the path is not valid or is not a directory ...
	 if ( !file_exists( $directory ) || !is_dir( $directory ) )
		 return FALSE;

	 // ... if the path is not readable
	 elseif (!is_readable($directory))
		 return FALSE;

	 // ... else if the path is readable
	 else {

		 // we open the directory
		 $handle = opendir( $directory );

		 // and scan through the items inside
		 while ( FALSE !== ( $item = readdir( $handle ) ) ) {
			 // if the filepointer is not the current directory
			 // or the parent directory
			 if ( $item != '.' && $item != '..' ) {
				 // we build the new path to delete
				 $path = $directory . '/' . $item;

				 // if the new path is a directory
				 if ( is_dir( $path ) ) {
					 // we call this function with the new path
					ec_recursive_remove_directory( $path );

				 // if the new path is a file
				 } else {
					 // we remove the file
					 unlink( $path );
				 }
			 }
		 }
		 // close the directory
		 closedir( $handle );

		 // if the option to empty is not set to true
		 if ( $empty == FALSE ) {
			 // try to delete the now empty directory
			 if ( ! rmdir( $directory ) ) {
				 // return false if not possible
				 return FALSE;
			 }
		 }
		 // return success
		 return TRUE;
	}
}

function ec_delete_directory_ftp( $resource, $path ) {
	$result_message = "";
	$list = ftp_nlist( $resource, $path );

	if ( empty($list) ) {
		$list = ec_ran_list_n( ftp_rawlist($resource, $path), $path . ( substr($path, strlen($path) - 1, 1) == "/" ? "" : "/" ) );
	}
	if ($list[0] != $path) {
		$path .= ( substr($path, strlen($path)-1, 1) == "/" ? "" : "/" );
		foreach ($list as $item) {
			if ($item != $path.".." && $item != $path.".") {
				$result_message .= ec_delete_directory_ftp($resource, $item);
			}
		}
		if (ftp_rmdir ($resource, $path)) {
			$result_message .= "Successfully deleted $path <br />\n";
		} else {
			$result_message .= "There was a problem while deleting $path <br />\n";
		}
	}
	else {
		$res = ftp_site( $resource, 'CHMOD 0777 ' . $path );
		if (ftp_delete ($resource, $path)) {
			$result_message .= "Successfully deleted $path <br />\n";
		} else {
			$result_message .= "There was a problem while deleting $path <br />\n";
		}
	}
	return $result_message;
}

function ec_ran_list_n($rawlist, $path) {
	$array = array();
	foreach ($rawlist as $item) {
		$filename = trim(substr($item, 55, strlen($item) - 55));
		if ($filename != "." || $filename != "..") {
		$array[] = $path . $filename;
		}
	}
	return $array;
}

add_filter( 'upgrader_pre_install', 'wpeasycart_backup', 10, 2 );

//////////////////////////////////////////////
//END UPDATE FUNCTIONS
//////////////////////////////////////////////

/////////////////////////////////////////////////////////////////////
//AJAX SETUP FUNCTIONS
/////////////////////////////////////////////////////////////////////
add_action( 'wp_ajax_ec_ajax_try_wpec_session', 'ec_ajax_try_wpec_session' );
add_action( 'wp_ajax_nopriv_ec_ajax_try_wpec_session', 'ec_ajax_try_wpec_session' );
function ec_ajax_try_wpec_session() {
	if ( ! isset( $_POST['product_id'] ) ) {
		die();
	}
	$product_id = (int) $_POST['product_id'];
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-add-to-cart-' . $product_id ) ) {
		die();
	}
	wpeasycart_session()->handle_session();
}
add_action( 'wp_ajax_ec_ajax_get_optionitem_quantities', 'ec_ajax_get_optionitem_quantities' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_optionitem_quantities', 'ec_ajax_get_optionitem_quantities' );
function ec_ajax_get_optionitem_quantities() {
	$product_id = (int) $_POST['product_id'];
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-product-details-' . $product_id ) ) {
		die();
	}

	$db = new ec_db();
	$optionitem_id_1 = (int) $_POST['optionitem_id_1'];

	if ( isset( $_POST['optionitem_id_2'] ) ) {
		$optionitem_id_2 = (int) $_POST['optionitem_id_2'];
	} else {
		$quantity_values = $db->get_option2_quantity_values( $product_id, $optionitem_id_1 );
		echo json_encode( $quantity_values );

		die();
	}

	if ( isset( $_POST['optionitem_id_3'] ) ) {
		$optionitem_id_3 = (int) $_POST['optionitem_id_3'];
	} else {
		$quantity_values = $db->get_option3_quantity_values( $product_id, $optionitem_id_1, $optionitem_id_2 );
		echo json_encode( $quantity_values );

		die();
	}

	if ( isset( $_POST['optionitem_id_4'] ) ) {
		$optionitem_id_4 = (int) $_POST['optionitem_id_4'];
	} else {
		$quantity_values = $db->get_option4_quantity_values( $product_id, $optionitem_id_1, $optionitem_id_2, $optionitem_id_3 );
		echo json_encode( $quantity_values );

		die();
	}


	$quantity_values = $db->get_option5_quantity_values( $product_id, $optionitem_id_1, $optionitem_id_2, $optionitem_id_3, $optionitem_id_4 );
	echo json_encode( $quantity_values );

	die();

}

add_action( 'wp_ajax_ec_ajax_add_to_cart_complete', 'ec_ajax_add_to_cart_complete' );
add_action( 'wp_ajax_nopriv_ec_ajax_add_to_cart_complete', 'ec_ajax_add_to_cart_complete' );
function ec_ajax_add_to_cart_complete() {
	/* 6.0.2: anything printed while adding ( a PHP notice, a hook that echoes ) is dropped, so the reply is only the JSON. */
	$wpec_ob_level = ob_get_level();
	ob_start();
	$product_id = ( isset( $_POST['product_id'] ) ) ? (int) $_POST['product_id'] : 0;
	if ( ! isset( $_POST['ec_cart_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ec_cart_form_nonce'] ) ), 'wp-easycart-add-to-cart-' . $product_id ) ) {
		die();
	}

	if ( isset( $_POST['ec_cart_form_action'] ) && 'add_to_cart_v3' == $_POST['ec_cart_form_action'] ) {
		wpeasycart_session()->handle_session();
		$ec_cartpage = new ec_cartpage();
		$ec_cartpage->process_form_action( sanitize_key( $_POST['ec_cart_form_action'] ) );
		wp_cache_flush();
		do_action( 'wpeasycart_cart_updated' );
	}
	$db = new ec_db();
	$tempcart = $db->get_temp_cart( $GLOBALS['ec_cart_data']->ec_cart_id );

	$cart_arr = array();
	$total_items = 0;
	$total_cost = 0;

	/* 6.0.2: each row also carries cartitem_id and link ( as ec_ajax_add_to_cart does ), so the mini cart can be redrawn. */
	$store_page_id = get_option( 'ec_option_storepage' );
	if ( function_exists( 'icl_object_id' ) ) {
		$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$store_page = get_permalink( $store_page_id );
	if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$store_page = $https_class->makeUrlHttps( $store_page );
	}
	$permalink_divider = ( substr_count( $store_page, '?' ) ) ? '&' : '?';

	wp_easycart_prime_store_posts( wp_list_pluck( (array) $tempcart, 'post_id' ) );
	foreach ( $tempcart as $item ) {
		$link = $store_page . $permalink_divider . 'model_number=' . $item->model_number;
		if ( ! get_option( 'ec_option_use_old_linking_style' ) && '0' != $item->post_id ) {
			$link = wp_easycart_store_post_link( $item->post_id, $link ); /* 6.0.2: live permalink, not the stored guid */
		}
		$cart_arr[] = array( 'title' => $item->title, 'price' => $GLOBALS['currency']->get_currency_display( $item->unit_price ), 'quantity' => $item->quantity, 'cartitem_id' => ( isset( $item->cartitem_id ) ? (int) $item->cartitem_id : 0 ), 'link' => $link );
		$total_items = $total_items + $item->quantity;
		$total_cost = $total_cost + ( $item->quantity * $item->unit_price );
	}
	$cart_arr[0]['total_items'] = $total_items;
	$cart_arr[0]['total_price'] = $GLOBALS['currency']->get_currency_display( $total_cost );
	/* 6.0.2: what the add put in ( content ID, value, Meta event ID ) for the storefront's wpeasycart_item_added event. */
	$item_added = function_exists( 'wp_easycart_cart_item_added_last' ) ? wp_easycart_cart_item_added_last() : null;
	if ( $item_added ) {
		$cart_arr[0]['item_added'] = $item_added;
		$cart_arr[0]['event_id'] = $item_added['event_id'];
	}
	/* 6.0.2: whether this request put anything in the cart. False when the add was refused ( an option or file rule, the
	 * stock limit ) or never ran: the storefront then posts the form as usual, so the shopper sees the store's own message. */
	$cart_arr[0]['added'] = ( function_exists( 'wp_easycart_cart_item_added_last' ) ) ? is_array( $item_added ) : true;
	while ( ob_get_level() > $wpec_ob_level ) {
		ob_end_clean();
	}
	echo json_encode( $cart_arr );
	die();
}

add_action( 'wp_ajax_ec_ajax_add_to_cart', 'ec_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_ec_ajax_add_to_cart', 'ec_ajax_add_to_cart' );
function ec_ajax_add_to_cart() {
	/* 6.0.2: anything printed while adding ( a PHP notice, a hook that echoes ) is dropped, so the reply is only the JSON. */
	$wpec_ob_level = ob_get_level();
	ob_start();
	$product_id = ( isset( $_POST['product_id'] ) ) ? (int) $_POST['product_id'] : 0;
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-add-to-cart-' . $product_id ) ) {
		die();
	}

	wpeasycart_session()->handle_session();

	$model_number = ( isset( $_POST['model_number'] ) ) ? sanitize_text_field( wp_unslash( $_POST['model_number'] ) ) : '';
	$quantity = ( isset( $_POST['quantity'] ) ) ? (int) $_POST['quantity'] : 0;
	$db = new ec_db();

	/* 6.0.2: add, then announce what went in ( wpeasycart_cart_item_added ) before reading the cart back. */
	$wpec_before_add = function_exists( 'wp_easycart_cart_add_snapshot' ) ? wp_easycart_cart_add_snapshot( $product_id ) : 0;
	$wpec_tempcart_id = $db->add_to_cart( $product_id, $GLOBALS['ec_cart_data']->ec_cart_id, $quantity, 0, 0, 0, 0, 0, "", "", "", 0.00, false, 0 );
	/* 6.0.2: refused by the add to cart rules ( catalog or inquiry mode, login for pricing, customer role, a closed store ) or a
	 * donation, whose amount is chosen on its page: the storefront opens the product page instead of saying it was added. */
	$wpec_refused = ( ! $wpec_tempcart_id && isset( ec_db::$add_to_cart_refused ) && '' !== ec_db::$add_to_cart_refused );
	$tempcart = $db->get_temp_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$item_added = ( $wpec_tempcart_id && function_exists( 'wp_easycart_announce_cart_item_added' ) ) ? wp_easycart_announce_cart_item_added( $wpec_tempcart_id, $product_id, $wpec_before_add, 'list', '', $tempcart ) : false;
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	$cart_arr = array();
	$total_items = 0;
	$total_cost = 0;

	$store_page_id = get_option( 'ec_option_storepage' );
	if ( function_exists( 'icl_object_id' ) ) {
		$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$store_page = get_permalink( $store_page_id );
	if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$store_page = $https_class->makeUrlHttps( $store_page );
	}
	if ( substr_count( $store_page, '?' ) ) {
		$permalink_divider = '&';
	} else {
		$permalink_divider = '?';
	}

	wp_easycart_prime_store_posts( wp_list_pluck( (array) $tempcart, 'post_id' ) );
	foreach ( $tempcart as $item ) {
		$link = $store_page . $permalink_divider . 'model_number=' . $item->model_number;
		if ( !get_option( 'ec_option_use_old_linking_style' ) && $item->post_id != '0' ) {
			$link = wp_easycart_store_post_link( $item->post_id, $link ); /* 6.0.2: live permalink, not the stored guid */
		}
		/* 6.0.2: cartitem_id too, which the storefront's mini cart rows use for their ids. */
		$cart_arr[] = array( 'title' => $item->title, 'price' => $GLOBALS['currency']->get_currency_display( $item->unit_price ), 'quantity' => $item->quantity, 'link' => $link, 'cartitem_id' => ( isset( $item->cartitem_id ) ? (int) $item->cartitem_id : 0 ) );
		$total_items = $total_items + $item->quantity;
		$total_cost = $total_cost + ( $item->quantity * $item->unit_price );
	}
	$cart_arr[0]['total_items'] = $total_items;
	$cart_arr[0]['total_price'] = $GLOBALS['currency']->get_currency_display( $total_cost );
	if ( $item_added ) {
		$cart_arr[0]['item_added'] = $item_added; /* 6.0.2: for the storefront's wpeasycart_item_added event */
		$cart_arr[0]['event_id'] = $item_added['event_id'];
	}
	if ( $wpec_refused ) {
		/* 6.0.2: ec_add_to_cart() ( ec-store.js ) and the Elementor shop's quick add open this page instead. */
		$cart_arr[0]['added']    = false;
		$cart_arr[0]['redirect'] = class_exists( 'wp_easycart_storefront_access' ) ? wp_easycart_storefront_access::product_url( $product_id ) : $store_page;
	}
	while ( ob_get_level() > $wpec_ob_level ) {
		ob_end_clean();
	}
	echo json_encode( $cart_arr );

	die();
}

add_action( 'wp_ajax_ec_ajax_cartitem_update', 'ec_ajax_cartitem_update' );
add_action( 'wp_ajax_nopriv_ec_ajax_cartitem_update', 'ec_ajax_cartitem_update' );
function ec_ajax_cartitem_update() {
	$tempcart_id = (int) sanitize_text_field( $_POST['cartitem_id'] );
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-cart-item-' . $tempcart_id ) ) {
		die();
	}

	wpeasycart_session()->handle_session();

	// UPDATE CART ITEM
	$session_id = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
	$quantity = (int) $_POST['quantity'];

	if ( is_numeric( $quantity ) ) {
		$db = new ec_db();
		$db->update_cartitem( $tempcart_id, $session_id, $quantity );
		wp_cache_flush();
		do_action( 'wpeasycart_cart_updated' );
	}
	// UPDATE CART ITEM

	// GET NEW CART ITEM INFO
	if ( isset( $_POST['ec_v3_24'] ) ) {
		$return_array = ec_get_cart_data();

		echo json_encode( $return_array );
	} else {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );

		$unit_price = 0;
		$total_price = 0;
		$new_quantity = 0;
		for ( $i=0; $i<count( $cart->cart ); $i++ ) {
			if ( $cart->cart[$i]->cartitem_id == $tempcart_id ) {
				$unit_price = $cart->cart[$i]->unit_price;
				$total_price = $cart->cart[$i]->total_price;
				$new_quantity = $cart->cart[$i]->quantity;
			}
		}
		// GET NEW CART ITEM INFO
		$order_totals = ec_get_order_totals( $cart );

		echo esc_attr( $GLOBALS['currency']->get_currency_display( $unit_price ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $total_price ) ) . '***' . 
				esc_attr( $new_quantity ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->sub_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->tax_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->duty_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( (-1) * $order_totals->discount_total ) ) . '***' .
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) );

		if ( $cart->total_items > 0 ) {

			if ( $cart->total_items != 1 ) {
				$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label_plural' );
			} else {
				$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label' );
			}

			echo '***' . esc_attr( $cart->total_items ) . ' ' . esc_attr( $items_label ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $cart->subtotal ) );
		} else {
			echo '***' . esc_attr( $cart->total_items ) . ' ' . esc_attr( $items_label );
		}
		echo '***' . esc_attr( $cart->total_items );
	}

	die();
}

add_action( 'wp_ajax_ec_ajax_cartitem_delete', 'ec_ajax_cartitem_delete' );
add_action( 'wp_ajax_nopriv_ec_ajax_cartitem_delete', 'ec_ajax_cartitem_delete' );
function ec_ajax_cartitem_delete() {
	$tempcart_id = sanitize_text_field( $_POST['cartitem_id'] );
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-delete-cart-item-' . $tempcart_id ) ) {
		die();
	}

	wpeasycart_session()->handle_session();

	//Get the variables from the AJAX call
	$session_id = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );

	// DELTE CART ITEM
	$wpec_delete_is_gift = false;
	if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
		global $wpdb;
		$wpec_delete_is_gift = ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT free_gift_offer_id FROM ec_tempcart WHERE tempcart_id = %d AND session_id = %s', (int) $tempcart_id, $session_id ) ) > 0 );
	}
	/* 6.0.0: one delete, one offer evaluation and one cart update event. The handler used to delete twice and fire
	   wpeasycart_cart_updated twice, so every listener ( live carrier rates, tax services, Stripe ) ran twice. A free
	   gift row is still not deleted here; the offer evaluation decides whether the gift stays. */
	if ( ! $wpec_delete_is_gift ) {
		$db = new ec_db();
		$ret_data = $db->delete_cartitem( $tempcart_id, $session_id );
		wp_cache_flush();
	}

	if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
		ec_offer_integration::reset();
		ec_offer_integration::evaluate_cart( new ec_cart( $session_id ) );
		ec_offer_integration::reset();
		wp_cache_flush();
	}

	do_action( 'wpeasycart_cart_updated' );

	// GET NEW CART ITEM INFO
	if ( isset( $_POST['ec_v3_24'] ) ) {
		$return_array = ec_get_cart_data();

		echo json_encode( $return_array );
	} else {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$order_totals = ec_get_order_totals( $cart );

		echo esc_attr( $cart->total_items ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->sub_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->tax_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->duty_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( (-1) * $order_totals->discount_total ) ) . '***' .
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) );

		if ( $cart->total_items != 1 ) {
			$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label_plural' );
		} else {
			$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label' );
		}

		if ( $cart->total_items > 0 ) {
			echo '***' . esc_attr( $cart->total_items ) . ' ' . esc_attr( $items_label ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $cart->subtotal ) );

		} else {
			echo '***' . esc_attr( $cart->total_items ) . ' ' . esc_attr( $items_label );

		}
		echo '***' . esc_attr( $cart->total_items );
	}

	die();

}

add_action( 'wp_ajax_ec_ajax_update_tip_amount', 'ec_ajax_update_tip_amount' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_tip_amount', 'ec_ajax_update_tip_amount' );
function ec_ajax_update_tip_amount() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-tip-' . $session_id ) ) {
		die();
	}

	$GLOBALS['ec_cart_data']->cart_data->tip_amount = ( $_POST['tip_rate'] == 'custom' && (float) $_POST['tip_amount'] > 0 ) ? (float) $_POST['tip_amount'] : 0;
	$GLOBALS['ec_cart_data']->cart_data->tip_rate = ( $_POST['tip_rate'] == 'custom' ) ? 'custom' : (float) $_POST['tip_rate'];
	$GLOBALS['ec_cart_data']->save_session_to_db();

	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	// GET NEW CART ITEM INFO
	$return_array = ec_get_cart_data();
	echo json_encode( $return_array );
	die();
}

add_action( 'wp_ajax_ec_ajax_subscription_create_account', 'ec_ajax_subscription_create_account' );
add_action( 'wp_ajax_nopriv_ec_ajax_subscription_create_account', 'ec_ajax_subscription_create_account' );
function ec_ajax_subscription_create_account() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-subscription-create-account-' . $session_id ) ) {
		die();
	}
	global $wpdb;
	$ec_db = new ec_db();

	$recaptcha_valid = true;
	if ( wp_easycart_recaptcha_ready() ) {
		if ( ! isset( $_POST['recaptcha_response'] ) || '' == $_POST['recaptcha_response'] ) {
			die();
		}

		$recaptcha_response = sanitize_text_field( $_POST['recaptcha_response'] );

		$data = array(
			"secret"	=> get_option( 'ec_option_recaptcha_secret_key' ),
			"response"	=> $recaptcha_response
		);

		$request = new WP_Http;
		$response = $request->request( 
			"https://www.google.com/recaptcha/api/siteverify", 
			array( 
				'method' => 'POST', 
				'body' => http_build_query( $data ),
				'timeout' => 30
			)
		);
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$ec_db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
			$response = (object) array(
				'error' => $error_message
			);
		} else {
			$response = json_decode( $response['body'] );
			$ec_db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
		}

		$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
	}

	if ( $recaptcha_valid ) {
		if ( wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_contact_email'] ) ) ) {
			$response = array( 'error' => array( 
				'id'		=> 'user_create_error',
				'message'	=> wp_easycart_language()->get_text( "ec_errors", "email_exists_error" )
			) );

		} else {
			$password = wp_easycart_hash_password( $_POST['ec_contact_password'] ); // XSS OK. Password Hashed Immediately
			$password = apply_filters( 'wpeasycart_password_hash', $password, $_POST['ec_contact_password'] ); // XSS OK. Password should not be hashed.

			$billing_id = $ec_db->insert_address( sanitize_text_field( $_POST['ec_contact_first_name'] ), sanitize_text_field( $_POST['ec_contact_last_name'] ), '', '', '', '', '', '', '', '' );
			$shipping_id = $ec_db->insert_address( sanitize_text_field( $_POST['ec_contact_first_name'] ), sanitize_text_field( $_POST['ec_contact_last_name'] ), '', '', '', '', '', '', '', '' );

			$user_id = $ec_db->insert_user( sanitize_email( $_POST['ec_contact_email'] ), $password, sanitize_text_field( $_POST['ec_contact_first_name'] ), sanitize_text_field( $_POST['ec_contact_last_name'] ), $billing_id, $shipping_id, 'shopper', 0, '', '' );

			$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;

			$ec_db->update_address_user_id( $billing_id, $user_id );
			$ec_db->update_address_user_id( $shipping_id, $user_id );

			do_action( 'wpeasycart_account_added', $user_id, sanitize_email( $_POST['ec_contact_email'] ), $_POST['ec_contact_password'], 'subscription' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

			// WordPress User Sync 1.x: the WordPress user ( 2.0 makes it on wpeasycart_account_added ).
			wp_easycart_wordpress_users::legacy_account_created( $user_id, sanitize_email( $_POST['ec_contact_email'] ), $_POST['ec_contact_password'], sanitize_text_field( $_POST['ec_contact_first_name'] ), sanitize_text_field( $_POST['ec_contact_last_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

			// Send registration email if needed
			if ( get_option( 'ec_option_send_signup_email' ) ) {
				$headers   = array();
				$headers[] = "MIME-Version: 1.0";
				$headers[] = "Content-Type: text/html; charset=utf-8";
				$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
				$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
				$headers[] = "X-Mailer: PHP/" . phpversion();

				$message = wp_easycart_account_register_admin_email_html( isset( $_POST['ec_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['ec_contact_email'] ) ) : '' ); // 6.0.0: shared email design.

				if ( get_option( 'ec_option_use_wp_mail' ) ) {
					wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( "account_register", "account_register_email_title" ), $message, implode("\r\n", $headers ) );
				} else {
					$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
					$subject = wp_easycart_language()->get_text( "account_register", "account_register_email_title" );
					$mailer = new wpeasycart_mailer();
					$mailer->send_order_email( $admin_email, $subject, $message );
				}

			}

			$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
			$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $_POST['ec_contact_email'] );
			$GLOBALS['ec_cart_data']->cart_data->username = sanitize_text_field( $_POST['ec_contact_first_name'] . ' ' . $_POST['ec_contact_last_name'] );
			$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $_POST['ec_contact_first_name'] );
			$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $_POST['ec_contact_last_name'] );
			$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $_POST['ec_contact_first_name'] );
			$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $_POST['ec_contact_last_name'] );
			$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['ec_contact_first_name'] );
			$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $_POST['ec_contact_last_name'] );

			$GLOBALS['ec_user'] = new ec_user( '' );
			$GLOBALS['ec_cart_data']->save_session_to_db();
			wp_cache_flush();
			do_action( 'wpeasycart_cart_updated' );

			$response = array(
				'first_name' => sanitize_text_field( $_POST['ec_contact_first_name'] ),
				'last_name' => sanitize_text_field( $_POST['ec_contact_last_name'] ),
				'name' => sanitize_text_field( $_POST['ec_contact_first_name'] . ' ' . $_POST['ec_contact_last_name'] )
			);
		}
		echo json_encode( $response );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_update_subscription_tax', 'ec_ajax_update_subscription_tax' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_subscription_tax', 'ec_ajax_update_subscription_tax' );
function ec_ajax_update_subscription_tax() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-subscription-tax-' . $session_id ) ) {
		die();
	}
	
	global $wpdb;
	$ec_db = new ec_db();

	$GLOBALS['ec_cart_data']->cart_data->shipping_selector = ( $_POST['shipping_selector'] ) ? 'true' : '';

	$GLOBALS['ec_cart_data']->cart_data->vat_registration_number = preg_replace( '/[^a-zA-Z0-9\s]/', '', sanitize_text_field( $_POST['vat_registration_number'] ) );
	$GLOBALS['ec_user']->vat_registration_number = preg_replace( '/[^a-zA-Z0-9\s]/', '', sanitize_text_field( $_POST['vat_registration_number'] ) );
	$ec_db->update_user( $GLOBALS['ec_user']->user_id, preg_replace( '/[^a-zA-Z0-9\s]/', '', sanitize_text_field( $_POST['vat_registration_number'] ) ) );

	$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $_POST['billing_first_name'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $_POST['billing_last_name'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_company_name = sanitize_text_field( $_POST['billing_company_name'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $_POST['billing_address'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $_POST['billing_address2'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $_POST['billing_city'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_state'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['billing_zip'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $_POST['billing_country'] );
	$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $_POST['billing_phone'] );

	$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shipping_first_name'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $_POST['shipping_last_name'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = sanitize_text_field( $_POST['shipping_company_name'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $_POST['shipping_address'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $_POST['shipping_address2'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $_POST['shipping_city'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_state'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shipping_zip'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shipping_country'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shipping_phone'] );

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	wp_easycart_subscription_output_ajax_totals();

	die();
}

/**
 * Prints the subscription page totals ( JSON ) after a change on that page.
 *
 * @param array|null $coupon_notice 6.0.2: message + status for a code the shopper just typed and was refused ( the
 *                                  session no longer holds it, so the code box would otherwise say nothing useful ).
 */
function wp_easycart_subscription_output_ajax_totals( $coupon_notice = null ) {
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	global $wpdb;
	$ec_db = new ec_db();
	$products = $ec_db->get_product_list( $wpdb->prepare( " WHERE product.product_id = %d", (int) $_POST['product_id'] ), "", "", "" );
	$product = new ec_product( $products[0], 0, 1, 0 );
	if ( class_exists( 'wp_easycart_subscription_options' ) ) {
		wp_easycart_subscription_options::guard( $products[0] ); /* 6.0.3: totals from this product's own choices only */
	}
	$subscription_cart = array();

	if ( !get_option( 'ec_option_subscription_one_only' ) && $GLOBALS['ec_cart_data']->cart_data->subscription_quantity != "" ) { 
		$subscription_quantity = $GLOBALS['ec_cart_data']->cart_data->subscription_quantity;
	} else { 
		$subscription_quantity = 1; 
	}

	// Create Promotion Multiplier for Options
	$option_promotion_multiplier = 1;
	$option_promotion_discount = 0;
	$promotions = $GLOBALS['ec_promotions']->promotions;
	for ( $i=0; $i<count( $promotions ); $i++ ) {
		if ( $product->promotion_text == $promotions[$i]->promotion_name ) {
			if ( $promotions[$i]->price1 == 0 ) {
				$option_promotion_multiplier = round( $promotions[$i]->percentage1 / 100, 2 );
			} else if ( $promotions[$i]->price1 != 0 ) {
				$option_promotion_discount = $promotions[$i]->price1;
			}
		}
	}

	// Get option item price adjustments
	$option_total = 0;
	$option_total_onetime = 0;
	$option_weight = 0;
	$option_weight_onetime = 0;
	$optionitem_list = $GLOBALS['ec_options']->get_all_optionitems();
	$subscription_option1 = $subscription_option2 = $subscription_option3 = $subscription_option4 = $subscription_option5 = 0;
	if ( ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option1 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option1 != "" ) || 
		( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option2 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option2 != "" ) || 
		( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option3 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option3 != "" ) || 
		( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option4 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option4 != "" ) || 
		( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option5 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option5 != "" ) ) {


		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option1 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option1 != "" ) {
			$subscription_option1 = $GLOBALS['ec_cart_data']->cart_data->subscription_option1;
		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option2 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option2 != "" ) {
			$subscription_option2 = $GLOBALS['ec_cart_data']->cart_data->subscription_option2;
		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option3 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option3 != "" ) {
			$subscription_option3 = $GLOBALS['ec_cart_data']->cart_data->subscription_option3;
		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option4 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option4 != "" ) {
			$subscription_option4 = $GLOBALS['ec_cart_data']->cart_data->subscription_option4;
		}

		if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_option5 ) && $GLOBALS['ec_cart_data']->cart_data->subscription_option5 != "" ) {
			$subscription_option5 = $GLOBALS['ec_cart_data']->cart_data->subscription_option5;
		}

		if ( $subscription_option1 != 0 ) {
			$subscription_option1 = $GLOBALS['ec_options']->get_optionitem( $subscription_option1 );
			if ( $subscription_option1->optionitem_price > 0 ) {
				$option_total += $subscription_option1->optionitem_price;
				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $subscription_option1->optionitem_price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);
			}
			if ( $subscription_option1->optionitem_weight > 0 ) {
				$option_weight += $subscription_option1->optionitem_weight;
			}
		}
		if ( $subscription_option2 != 0 ) {
			$subscription_option2 = $GLOBALS['ec_options']->get_optionitem( $subscription_option2 );
			if ( $subscription_option2->optionitem_price > 0 ) {
				$option_total += $subscription_option2->optionitem_price;
				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $subscription_option2->optionitem_price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);
			}
			if ( $subscription_option2->optionitem_weight > 0 ) {
				$option_weight += $subscription_option2->optionitem_weight;
			}
		}
		if ( $subscription_option3 != 0 ) {
			$subscription_option3 = $GLOBALS['ec_options']->get_optionitem( $subscription_option3 );
			if ( $subscription_option3->optionitem_price > 0 ) {
				$option_total += $subscription_option3->optionitem_price;
				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $subscription_option3->optionitem_price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);
			}
			if ( $subscription_option3->optionitem_weight > 0 ) {
				$option_weight += $subscription_option3->optionitem_weight;
			}
		}
		if ( $subscription_option4 != 0 ) {
			$subscription_option4 = $GLOBALS['ec_options']->get_optionitem( $subscription_option4 );
			if ( $subscription_option4->optionitem_price > 0 ) {
				$option_total += $subscription_option4->optionitem_price;
				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $subscription_option4->optionitem_price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);
			}
			if ( $subscription_option4->optionitem_weight > 0 ) {
				$option_weight += $subscription_option4->optionitem_weight;
			}
		}
		if ( $subscription_option5 != 0 ) {
			$subscription_option5 = $GLOBALS['ec_options']->get_optionitem( $subscription_option5 );
			if ( $subscription_option5->optionitem_price > 0 ) {
				$option_total += $subscription_option5->optionitem_price;
				$subscription_cart[] = (object) array(
					'vat_enabled' => ( $product->vat_rate != 0 ),
					'is_taxable' => $product->is_taxable,
					'item_total' => round( $subscription_option5->optionitem_price * $subscription_quantity, 2 ),
					'item_discount' => 0,
				);
			}
			if ( $subscription_option5->optionitem_weight > 0 ) {
				$option_weight += $subscription_option5->optionitem_weight;
			}
		}
	}
	
	// Subscription Advanced Options
	if ( isset( $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option ) && $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option != "" ) {
		$subscription_advanced_options = maybe_unserialize( $GLOBALS['ec_cart_data']->cart_data->subscription_advanced_option );
		if ( $subscription_advanced_options ) {
			foreach( $subscription_advanced_options as $option ) {
				$optionitem = $GLOBALS['ec_options']->get_optionitem( $option['optionitem_id'] );
				if ( $optionitem->optionitem_disallow_shipping ) {
					$product->is_shippable = false;
				}
				if ( $optionitem && $optionitem->optionitem_price > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_total += ( $optionitem->optionitem_price * (int) $option['optionitem_value'] );
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( ( $optionitem->optionitem_price * (int) $option['optionitem_value'] ) * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					} else {
						$option_total += $optionitem->optionitem_price;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $optionitem->optionitem_price * $subscription_quantity, 2 ),
							'item_discount' => 0,
						);
					}
				} else if ( $optionitem && $optionitem->optionitem_price_onetime > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_total_onetime += ( $optionitem->optionitem_price_onetime * (int) $option['optionitem_value'] );
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( ( $optionitem->optionitem_price_onetime * (int) $option['optionitem_value'] ), 2 ),
							'item_discount' => 0,
						);
					} else {
						$option_total_onetime += $optionitem->optionitem_price_onetime;
						$subscription_cart[] = (object) array(
							'vat_enabled' => ( $product->vat_rate != 0 ),
							'is_taxable' => $product->is_taxable,
							'item_total' => round( $optionitem->optionitem_price_onetime, 2 ),
							'item_discount' => 0,
						);
					}
				} else if ( $optionitem && $optionitem->optionitem_price_override > -1 ) {
					$product->price = $optionitem->optionitem_price_override;
				}
				if ( $optionitem && $optionitem->optionitem_weight > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_weight += ( $optionitem->optionitem_weight * (int) $option['optionitem_value'] );
					} else {
						$option_weight += $optionitem->optionitem_weight;
					}
				} else if ( $optionitem && $optionitem->optionitem_weight_onetime > 0 ) {
					if ( 'number' == $option['option_type'] ) {
						$option_weight_onetime += ( $optionitem->optionitem_weight_onetime * (int) $option['optionitem_value'] );
					} else {
						$option_weight_onetime += $optionitem->optionitem_weight_onetime;
					}
				} else if ( $optionitem && $optionitem->optionitem_weight_override > -1 ) {
					$product->weight = $optionitem->optionitem_weight_override;
				}
			}
		}
	}

	$subscription_cart[] = (object) array(
		'vat_enabled' => ( $product->vat_rate != 0 ),
		'is_taxable' => $product->is_taxable,
		'item_total' => round( $product->price * $subscription_quantity, 2 ),
		'item_discount' => 0,
	);

	if ( $product->is_shippable ) {
		$ship_price_total = ( ( $product->price + $option_total ) * $subscription_quantity ) + $option_total_onetime;
		$ship_weight_total = ( ( $product->weight + $option_weight ) * $subscription_quantity ) + $option_weight_onetime;
		$ship_quantity = $subscription_quantity;
	} else {
		$ship_price_total = 0;
		$ship_weight_total = 0;
		$ship_quantity = 0;
	}

	$product->weight = $ship_weight_total;
	$product->quantity = $ship_quantity;
	do_action( 'wpeasycart_cart_subscription_updated', $product, $subscription_quantity, $ship_weight_total ); /* 6.0.2: the total weight, so live rates stop multiplying it by the quantity again */

	$cartpage= new ec_cartpage();
	$cartpage->cart->cart = array( $product );
	$cartpage->shipping = new ec_shipping( $ship_price_total, $ship_weight_total, $ship_quantity, 'RADIO', $GLOBALS['ec_user']->freeshipping, $product->length, $product->width, $product->height * $subscription_quantity, array( $product ) );
	$cartpage->shipping->change_shipping_js_func = 'ec_cart_subscription_shipping_method_change';

	$handling_total = $product->handling_price + ( $product->handling_price_each * $subscription_quantity );
	$shipping_total = floatval( $cartpage->shipping->get_shipping_price( $handling_total ) );
	$subscription_cart[] = (object) array(
		'vat_enabled' => ! get_option( 'ec_option_no_vat_on_shipping' ),
		'is_taxable' => get_option( 'ec_option_collect_tax_on_shipping' ),
		'item_total' => round( $shipping_total, 2 ),
		'item_discount' => 0,
	);

	if ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && $product->is_shippable ) {
		$cartpage->cart->shippable_total_items = $subscription_quantity;
	}

	$coupon = $GLOBALS['ec_coupons']->subscription_coupon( $GLOBALS['ec_cart_data']->cart_data->coupon_code, $product->product_id ); // 6.0.2: Offers codes too
	$coupon_code_invalid = true;
	$coupon_applicable = true;
	$coupon_exceeded_redemptions = false;
	$coupon_expired = false;

	if ( !$coupon ) { // Invalid Coupon
		$coupon_code_invalid = false;
	} else if ( $coupon->by_product_id && $coupon->product_id != $product->product_id ) { // Product does not match
		$coupon_applicable = false;
	} else if ( $coupon->by_manufacturer_id && $coupon->manufacturer_id != $product->manufacturer_id ) { // Manufacturer Does not Match
		$coupon_applicable = false;
	} else if ( $coupon->by_category_id && ! $wpdb->get_results( $wpdb->prepare( "SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d", $coupon->category_id, $product->product_id ) ) ) { // Category does not match ( 6.0.2: a matching category coupon is checked for use and expiry below too )
		$coupon_applicable = false;
	} else if ( $coupon->max_redemptions != 999 && $coupon->times_redeemed >= $coupon->max_redemptions ) {
		$coupon_exceeded_redemptions = true;
	} else if ( $coupon->coupon_expired ) {
		$coupon_expired = true;
	} else if ( ! ( ( ! empty( $coupon->is_percentage_based ) && (float) $coupon->promo_percentage > 0 ) || ( ! empty( $coupon->is_dollar_based ) && (float) $coupon->promo_dollar > 0 ) ) ) {
		$coupon_applicable = false; // 6.0.2: a subscription takes only a percent or an amount off ( subscription_checkout_coupon() ), never shipping or a free item.
	}

	$discount_amount = 0;
	$is_dollar_discount = false;
	if ( $coupon && $coupon_applicable && ! $coupon_exceeded_redemptions && ! $coupon_expired ) {
		if ( $coupon->is_percentage_based ) {
			$coupon_percentage = round( $coupon->promo_percentage / 100, 2 );
			for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
				$subscription_cart[ $i ]->item_discount = round( $subscription_cart[ $i ]->item_total * $coupon_percentage, 2 );
				$discount_amount += $subscription_cart[ $i ]->item_discount;
			}
		} else if ( $coupon->is_dollar_based ) {
			$is_dollar_discount = true;
			$discount_amount = $coupon->promo_dollar;
		}
		if ( $discount_amount > ( ( $product->price + $option_total ) * $subscription_quantity ) + $option_total_onetime ) {
			$discount_amount = ( ( $product->price + $option_total ) * $subscription_quantity ) + $option_total_onetime;
		}
	} else if ( $option_promotion_multiplier < 1 ) {
		for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
			$subscription_cart[ $i ]->item_discount = round( $subscription_cart[ $i ]->item_total * $option_promotion_multiplier, 2 );
			$discount_amount += $subscription_cart[ $i ]->item_discount;
		}
	} else if ( $option_promotion_discount > 0 ) {
		$is_dollar_discount = true;
		$discount_amount = round( $option_promotion_discount, 2 );
	}
	$discount_amount = round( $discount_amount, 2 );

	// Get and Print Order Totals
	do_action( 'wpeasycart_cart_subscription_pre_tax', $product, $subscription_quantity, $shipping_total, $handling_total, $discount_amount );
	wpeasycart_taxcloud()->setup_subscription_for_tax( $product, $subscription_quantity, $discount_amount );
	if ( function_exists( 'wpeasycart_taxjar' ) ) {
		wpeasycart_taxjar()->setup_subscription_for_tax( $product, $subscription_quantity, $discount_amount );
	}
	$sub_total = ( ( $product->price + $option_total ) * $subscription_quantity ) + $option_total_onetime;
	if ( $is_dollar_discount ) {
		for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
			$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - round( ( $subscription_cart[$i]->item_total / ( $sub_total + $shipping_total ) ) * $discount_amount, 2 );
		}
	} else {
		for ( $i = 0; $i < count( $subscription_cart ); $i++ ) {
			$subscription_cart[$i]->item_total = $subscription_cart[$i]->item_total - $subscription_cart[$i]->item_discount;
		}
	}
	$sub_total -= $discount_amount;
	$tax_subtotal = ( $product->is_taxable ) ? $sub_total - ( $product->subscription_signup_fee * $subscription_quantity ) : 0;
	$vat_subtotal = ( $product->vat_rate > 0 ) ? $sub_total - ( $product->subscription_signup_fee * $subscription_quantity ) : 0;
	$ec_tax = new ec_tax( $sub_total, $tax_subtotal, $vat_subtotal, $GLOBALS['ec_cart_data']->cart_data->shipping_state, $GLOBALS['ec_cart_data']->cart_data->shipping_country, $GLOBALS['ec_user']->taxfree, 0, $subscription_cart, true );

	$tax_total = $ec_tax->tax_total;
	$vat_rate = $ec_tax->vat_rate;
	$vat_total = $ec_tax->vat_total;

	$coupon_message = '';
	$coupon_status = '';

	if ( !$coupon_code_invalid ) {
		$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
		$coupon_status = "invalid";

	} else if ( !$coupon_applicable ) {
		$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_not_applicable_coupon' );
		$coupon_status = "invalid";

	} else if ( $coupon_exceeded_redemptions ) {
		$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_max_exceeded_coupon' );
		$coupon_status = "invalid";

	} else if ( $coupon_expired ) {
		$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_coupon_expired' );
		$coupon_status = "invalid";

	} else if ( ! empty( $coupon->is_offer ) ) {
		$coupon_message = $coupon->message; // 6.0.2: already checked against this subscription
		$coupon_status = "valid";

	} else {
		/* 6.0.2: the coupon was checked against this subscription above. The regular cart never holds the subscription, so
		 * its match count said "does not apply" for a coupon made for this product; only its first-order check is asked.
		 * Its own variable: $cartpage prints this subscription's shipping options below. */
		$wpec_regular_cartpage = new ec_cartpage();
		if ( $wpec_regular_cartpage->discount->coupon_first_failed ) {
			$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'coupon_first_only' );
		} else {
			$coupon_message = $coupon->message;
		}
		$coupon_status = "valid";

	}
	if ( is_array( $coupon_notice ) ) {
		$coupon_message = $coupon_notice['message'];
		$coupon_status  = $coupon_notice['status'];
	}

	if ( $product->trial_period_days > 0 ) {
		$grand_total = ( $product->subscription_signup_fee * $subscription_quantity );
	} else if ( $ec_tax->vat_included ) {
		$grand_total = ( ( $product->price + $option_total + $product->subscription_signup_fee ) * $subscription_quantity ) + $option_total_onetime - $discount_amount + $tax_total + $ec_tax->hst + $ec_tax->gst + $ec_tax->pst + $shipping_total;
	} else {
		$grand_total = ( ( $product->price + $option_total + $product->subscription_signup_fee ) * $subscription_quantity ) + $option_total_onetime - $discount_amount + $tax_total + $vat_total + $ec_tax->hst + $ec_tax->gst + $ec_tax->pst + $shipping_total;
	}

	/* 6.0.3: what the subscription renews at when the code comes off the first payment only, and the free trial's line with
	 * the first payment ( the page's note and trial line, redrawn ). */
	$wpec_sub_plan = array(
		'code'           => ( 'valid' === $coupon_status ) ? (string) $GLOBALS['ec_cart_data']->cart_data->coupon_code : '',
		'quantity'       => $subscription_quantity,
		'option_total'   => $option_total,
		'shipping_total' => $shipping_total,
		'taxed'          => ( $tax_total + $ec_tax->hst + $ec_tax->gst + $ec_tax->pst > 0 ) || ( $vat_total > 0 && ! $ec_tax->vat_included ),
	);
	$renewal_note  = function_exists( 'wp_easycart_subscription_renewal_note' ) ? wp_easycart_subscription_renewal_note( $product, $wpec_sub_plan ) : '';
	$trial_text    = function_exists( 'wp_easycart_subscription_trial_text' ) ? wp_easycart_subscription_trial_text( $product, $wpec_sub_plan ) : '';

	ob_start();
	$cartpage->shipping->print_shipping_options( wp_easycart_language( )->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language( )->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) );
	$shipping_method_content = ob_get_clean();

	$billing_address_display = esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_first_name . ' ' . $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) . ', ';
	$billing_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_company_name . ', ' : '' ) );
	$billing_address_display .= esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) . ', ';
	$billing_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 . ', ' : '' ) );
	$billing_address_display .= esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_city ) . ' ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_state ) . ' ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_zip ) . ', ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->billing_country );
	$billing_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_phone ) ? ', ' . $GLOBALS['ec_cart_data']->cart_data->billing_phone : '' ) );
	$shipping_address_display = $billing_address_display;
	if ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_selector && 'true' == $GLOBALS['ec_cart_data']->cart_data->shipping_selector ) {
		$shipping_address_display = esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name . ' ' . $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ) . ', ';
		$shipping_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_company_name . ', ' : '' ) );
		$shipping_address_display .= esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) . ', ';
		$shipping_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 . ', ' : '' ) );
		$shipping_address_display .= esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_city ) . ' ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_state ) . ' ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) . ', ' . esc_attr( $GLOBALS['ec_cart_data']->cart_data->shipping_country );
		$shipping_address_display .= esc_attr( ( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_phone ) ? ', ' . $GLOBALS['ec_cart_data']->cart_data->shipping_phone : '' ) );
	}

	echo json_encode( array(
		'quantity'			=> $subscription_quantity, 
		'subtotal'			=> $GLOBALS['currency']->get_currency_display( $sub_total ),
		'has_tax'			=> ( $tax_total > 0 ) ? 1 : 0,
		'tax_total'			=> $product->get_option_price_formatted( $tax_total, 1 ), 
		'hst_total'			=> $product->get_option_price_formatted( $ec_tax->hst, 1 ),
		'hst_rate'			=> (string) $ec_tax->hst_rate,
		'pst_total'			=> $product->get_option_price_formatted( $ec_tax->pst, 1 ),
		'pst_rate'			=> (string) $ec_tax->pst_rate,
		'pst_label'			=> wp_easycart_canada_tax_label( 'pst', $ec_tax->shipping_state ),
		'gst_total'			=> $product->get_option_price_formatted( $ec_tax->gst, 1 ),
		'gst_rate'			=> (string) $ec_tax->gst_rate,
		'is_shippable'		=> ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && $product->is_shippable ),
		'shipping_total'	=> ( ( $product->subscription_shipping_recurring ) ? $product->get_option_price_formatted( $shipping_total, 1 ): $GLOBALS['currency']->get_currency_display( $shipping_total ) ),
		'shipping_methods'	=> $shipping_method_content,
		'discount_total'	=> $GLOBALS['currency']->get_currency_display( (-1) * $discount_amount ), 
		'has_vat'			=> ( $vat_total > 0 ) ? 1 : 0,
		'vat_total'			=> $product->get_option_price_formatted( $vat_total, 1 ),
		'vat_rate'			=> $vat_rate,
		'vat_rate_formatted'=> $cartpage->get_vat_rate_formatted( $vat_rate ),
		'grand_total'		=> $GLOBALS['currency']->get_currency_display( $grand_total ),
		'renewal_note'		=> $renewal_note, /* 6.0.3, plain text */
		'trial_text'		=> $trial_text, /* 6.0.3, plain text ( '' without a free trial ) */
		'coupon_message'	=> $coupon_message,
		'coupon_status'		=> $coupon_status,
		'has_discount'		=> ( $discount_amount == 0 ) ? 0 : 1,
		'price_formatted'	=> $product->get_price_formatted( $subscription_quantity ),
		'billing_address_display' => $billing_address_display,
		'shipping_address_display' => $shipping_address_display,
	) );
}

add_action( 'wp_ajax_ec_ajax_save_checkout_info', 'ec_ajax_save_checkout_info' );
add_action( 'wp_ajax_nopriv_ec_ajax_save_checkout_info', 'ec_ajax_save_checkout_info' );
function ec_ajax_save_checkout_info() {
	// wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-save-checkout-info-' . $session_id ) ) {
		die();
	}

	$ec_db = new ec_db();
	$errors = false;

	// Add recaptcha check
	
	// Manage Subscriber
	$is_subscriber = ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) ? 1 : 0;
	if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
		$first_name = sanitize_text_field( $_POST['ec_shipping_name'] );
		$last_name = sanitize_text_field( $_POST['ec_shipping_last_name'] );
		$email = sanitize_text_field( $_POST['ec_contact_email'] );

		/* 6.0.2: the one-page checkout saves this step several times per checkout ( each address change, then Place order ), so
		   subscribe only an address not on the list yet: every save used to fire "subscribed" to the email services again. */
		if ( ! ( method_exists( 'ec_db', 'is_subscribed' ) && ec_db::is_subscribed( $email ) ) ) {
			$ec_db->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).
		}

		if ( $GLOBALS['ec_user']->user_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
		}

		// MyMail Hook
		if ( function_exists( 'mailster' ) ) {
			$subscriber_id = mailster('subscribers')->add(array(
				'firstname' => $first_name,
				'lastname' => $last_name,
				'email' => $email,
				'status' => 1,
			), false );
		}
	}
	
	if ( isset( $_POST['ec_vat_registration_number'] ) ) { /* 6.0.2: only when the page sent it ( it was always wiped before ) */
		$GLOBALS['ec_cart_data']->cart_data->vat_registration_number = sanitize_text_field( wp_unslash( $_POST['ec_vat_registration_number'] ) );
	}
	
	$address_type = 'shipping';
	if ( ! isset( $_POST['ec_shipping_country'] ) ) {
		$address_type = 'billing';
	}

	$name = explode( ' ', sanitize_text_field( $_POST['ec_' . $address_type . '_name'] ) );
	$first_name = $last_name = '';
	if ( is_array( $name ) ) {
		$first_name = ( isset( $name[0] ) ) ? $name[0] : $_POST['ec_' . $address_type . '_name'];
		for ( $i = 1; $i < count( $name ); $i++ ) {
			if ( $i > 1 ) {
				$last_name .= ' ';
			}
			$last_name .= $name[ $i ];
		}
	}
	if ( '' == $last_name && isset( $_POST['ec_' . $address_type . '_last_name'] ) ) {
		$last_name = sanitize_text_field( $_POST['ec_' . $address_type . '_last_name'] );
	}

	$wpec_billing_choice = isset( $_POST['ec_shipping_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['ec_shipping_selector'] ) ) : '';
	if ( 'shipping' == $address_type && '' !== $wpec_billing_choice ) { /* 6.0.2: no choice on the page ( '' ) keeps the saved one */
		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = ( 1 == (int) $wpec_billing_choice ) ? 'true' : '';
	}
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_first_name' } = $first_name;
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_last_name' } = $last_name;
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_company_name' } = sanitize_text_field( $_POST['ec_' . $address_type . '_company_name'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_address_line_1' } = sanitize_text_field( $_POST['ec_' . $address_type . '_address_line_1'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_address_line_2' } = ( isset( $_POST['ec_' . $address_type . '_address_line_2'] ) ) ? sanitize_text_field( $_POST['ec_' . $address_type . '_address_line_2'] ) : '';
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_city' } = sanitize_text_field( $_POST['ec_' . $address_type . '_city'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_state' } = sanitize_text_field( $_POST['ec_' . $address_type . '_state'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_zip' } = sanitize_text_field( $_POST['ec_' . $address_type . '_zip'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_country' } = sanitize_text_field( $_POST['ec_' . $address_type . '_country'] );
	$GLOBALS['ec_cart_data']->cart_data->{ $address_type . '_phone' } = ( isset( $_POST['ec_' . $address_type . '_phone'] ) ) ? sanitize_text_field( $_POST['ec_' . $address_type . '_phone'] ) : '';
	if ( isset( $_POST['ec_order_notes'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->order_notes = sanitize_textarea_field( wp_unslash( $_POST['ec_order_notes'] ) ); /* 6.0.2: keeps line breaks */
	}
	if ( 'billing' === $address_type ) {
		// 6.0.2: no shipping address on this checkout ( nothing ships, or it ships to the billing address ): tax, fees, rates
		// and the order's shipping address use the billing address, as the classic checkout does, never one left in the
		// session by an earlier estimate or cart.
		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = '';
		foreach ( array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ) as $wpec_address_key ) {
			$GLOBALS['ec_cart_data']->cart_data->{ 'shipping_' . $wpec_address_key } = $GLOBALS['ec_cart_data']->cart_data->{ 'billing_' . $wpec_address_key };
		}
	} else if ( '' == $GLOBALS['ec_cart_data']->cart_data->shipping_selector ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $GLOBALS['ec_cart_data']->cart_data->shipping_first_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $GLOBALS['ec_cart_data']->cart_data->shipping_last_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = $GLOBALS['ec_cart_data']->cart_data->shipping_company_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2;
		$GLOBALS['ec_cart_data']->cart_data->billing_city = $GLOBALS['ec_cart_data']->cart_data->shipping_city;
		$GLOBALS['ec_cart_data']->cart_data->billing_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = $GLOBALS['ec_cart_data']->cart_data->shipping_zip;
		$GLOBALS['ec_cart_data']->cart_data->billing_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = $GLOBALS['ec_cart_data']->cart_data->shipping_phone;
	}
	if ( $GLOBALS['ec_user']->user_id ) {
		$GLOBALS['ec_cart_data']->cart_data->user_id = $GLOBALS['ec_user']->user_id;
		$GLOBALS['ec_cart_data']->cart_data->email = $GLOBALS['ec_user']->email;
		$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
		$GLOBALS['ec_cart_data']->cart_data->guest_key = '';
		$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
		$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;
	} else {
		$GLOBALS['ec_cart_data']->cart_data->email = sanitize_text_field( $_POST['ec_contact_email'] );
		if ( isset( $_POST['ec_create_account'] ) && '1' == $_POST['ec_create_account'] ) {
			if ( wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_contact_email'] ) ) ) {
				$errors = 'user_create_error';
			} else {
				$password = wp_easycart_hash_password( $_POST['ec_contact_password'] ); // XSS OK. Password Hashed Immediately
				$password = apply_filters( 'wpeasycart_password_hash', $password, $_POST['ec_contact_password'] ); // XSS OK. Password should not be hashed.
				$billing_id = $ec_db->insert_address(
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_first_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_first_name : $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_last_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_last_name : $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 : $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 : $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_city ) ? $GLOBALS['ec_cart_data']->cart_data->billing_city : $GLOBALS['ec_cart_data']->cart_data->shipping_city ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_state ) ? $GLOBALS['ec_cart_data']->cart_data->billing_state : $GLOBALS['ec_cart_data']->cart_data->shipping_state ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_zip ) ? $GLOBALS['ec_cart_data']->cart_data->billing_zip : $GLOBALS['ec_cart_data']->cart_data->shipping_zip ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_country ) ? $GLOBALS['ec_cart_data']->cart_data->billing_country : $GLOBALS['ec_cart_data']->cart_data->shipping_country ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_phone ) ? $GLOBALS['ec_cart_data']->cart_data->billing_phone : $GLOBALS['ec_cart_data']->cart_data->shipping_phone ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->billing_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->billing_company_name : $GLOBALS['ec_cart_data']->cart_data->shipping_company_name )
				);
				$shipping_id = $ec_db->insert_address(
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_first_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_first_name : $GLOBALS['ec_cart_data']->cart_data->billing_first_name ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_last_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_last_name : $GLOBALS['ec_cart_data']->cart_data->billing_last_name ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 : $GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 : $GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_city ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_city : $GLOBALS['ec_cart_data']->cart_data->billing_city ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_state : $GLOBALS['ec_cart_data']->cart_data->billing_state ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_zip ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_zip : $GLOBALS['ec_cart_data']->cart_data->billing_zip ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_country : $GLOBALS['ec_cart_data']->cart_data->billing_country ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_phone ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_phone : $GLOBALS['ec_cart_data']->cart_data->billing_phone ),
					( ( '' != $GLOBALS['ec_cart_data']->cart_data->shipping_company_name ) ? $GLOBALS['ec_cart_data']->cart_data->shipping_company_name : $GLOBALS['ec_cart_data']->cart_data->billing_company_name )
				);
				$user_id = $ec_db->insert_user(
					sanitize_email( $_POST['ec_contact_email'] ),
					$password,
					( ( isset( $_POST['ec_contact_first_name'] ) ) ? sanitize_text_field( $_POST['ec_contact_first_name'] ) : $first_name ),
					( ( isset( $_POST['ec_contact_last_name'] ) ) ? sanitize_text_field( $_POST['ec_contact_last_name'] ) : $last_name ),
					$billing_id,
					$shipping_id,
					'shopper',
					0,
					'',
					''
				);

				$GLOBALS['ec_cart_data']->cart_data->user_id = $user_id;

				$ec_db->update_address_user_id( $billing_id, $user_id );
				$ec_db->update_address_user_id( $shipping_id, $user_id );

				do_action( 'wpeasycart_account_added', $user_id, sanitize_email( $_POST['ec_contact_email'] ), $_POST['ec_contact_password'], 'checkout' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// WordPress User Sync 1.x: the WordPress user ( 2.0 makes it on wpeasycart_account_added ).
				wp_easycart_wordpress_users::legacy_account_created( $user_id, sanitize_email( $_POST['ec_contact_email'] ), $_POST['ec_contact_password'], sanitize_text_field( $_POST['ec_contact_first_name'] ), sanitize_text_field( $_POST['ec_contact_last_name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are used as typed, never sanitized ( 6.0.2 ).

				// Send registration email if needed
				if ( get_option( 'ec_option_send_signup_email' ) ) {
					$headers   = array();
					$headers[] = "MIME-Version: 1.0";
					$headers[] = "Content-Type: text/html; charset=utf-8";
					$headers[] = "From: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
					$headers[] = "Reply-To: " . stripslashes( get_option( 'ec_option_order_from_email' ) );
					$headers[] = "X-Mailer: PHP/" . phpversion();

					$message = wp_easycart_account_register_admin_email_html( isset( $_POST['ec_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['ec_contact_email'] ) ) : '' ); // 6.0.0: shared email design.

					if ( get_option( 'ec_option_use_wp_mail' ) ) {
						wp_mail( stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ), wp_easycart_language()->get_text( "account_register", "account_register_email_title" ), $message, implode("\r\n", $headers ) );
					} else {
						$admin_email = stripslashes( get_option( 'ec_option_bcc_email_addresses' ) );
						$subject = wp_easycart_language()->get_text( "account_register", "account_register_email_title" );
						$mailer = new wpeasycart_mailer();
						$mailer->send_order_email( $admin_email, $subject, $message );
					}
				}

				$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
				$GLOBALS['ec_cart_data']->cart_data->guest_key = '';
				$GLOBALS['ec_cart_data']->cart_data->username = sanitize_text_field( $_POST['ec_contact_first_name'] . ' ' . $_POST['ec_contact_last_name'] );
				$GLOBALS['ec_cart_data']->cart_data->first_name = ( isset( $_POST['ec_contact_first_name'] ) ) ? sanitize_text_field( $_POST['ec_contact_first_name'] ) : $first_name;
				$GLOBALS['ec_cart_data']->cart_data->last_name = ( isset( $_POST['ec_contact_last_name'] ) ) ? sanitize_text_field( $_POST['ec_contact_last_name'] ) : $last_name;
				$GLOBALS['ec_user'] = new ec_user( '' );
			}
		} else {
			$GLOBALS['ec_cart_data']->cart_data->user_id = '';
			$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
			$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
			$GLOBALS['ec_cart_data']->cart_data->first_name = $first_name;
			$GLOBALS['ec_cart_data']->cart_data->last_name = $last_name;
		}
	}
	if ( isset( $_POST['ec_email_other'] ) ) { /* 6.0.2: only when the page sent it ( the one-page checkout never did, so every save wiped it ) */
		$wpec_email_other = sanitize_email( wp_unslash( $_POST['ec_email_other'] ) );
		if ( ! filter_var( $wpec_email_other, FILTER_VALIDATE_EMAIL ) ) {
			$wpec_email_other = '';
		}
		$GLOBALS['ec_cart_data']->cart_data->email_other = $wpec_email_other;
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	// Get cart and totals
	$cartpage = new ec_cartpage();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );
	do_action( 'wpeasycart_save_checkout_info_complete', $cart, $order_totals, $cartpage->shipping->get_selected_shipping_method() );

	/* 6.0.2: the one-page Place order saves the page only to check it next ( ec_ajax_onepage_check, which also moves the
	   Stripe payment to the order total ) and reads just the error: the step templates, with their live rate and gateway
	   calls, are not drawn again for it. */
	if ( isset( $_POST['ec_render'] ) && '0' === sanitize_text_field( wp_unslash( $_POST['ec_render'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked at the top of this handler.
		echo wp_json_encode( (object) array( 'error' => $errors ) );
		die();
	}

	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
		if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );
	}

	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );

	$return_cart_data = ec_get_cart_data();

	if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) {
		ob_start();
		/* 6.0.2: "Skip the shipping method step": the cheapest rate is chosen and the next step is payment. */
		$wpec_skip_shipping = get_option( 'ec_option_skip_shipping_page' ) && $cartpage->page_allowed( 'payment' );
		if ( $wpec_skip_shipping ) {
			$cartpage->shipping->skip_shipping_selection_page();
			$GLOBALS['ec_cart_data']->save_session_to_db();
		}
		if ( ! $wpec_skip_shipping && get_option( 'ec_option_use_shipping' ) && $cartpage->shipping_address_allowed && ( $cartpage->cart->shippable_total_items > 0 || $cartpage->order_totals->handling_total > 0 || $cartpage->cart->excluded_shippable_total_items > 0 ) ) {
			if( $cartpage->page_allowed( 'shipping' ) ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' ) ) {
					include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php';
				} else {
					include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_v2.php';
				}
			} else {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' ) ) {
					include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php';
				} else {
					include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_information_v2.php';
				}
			}
		} else {
			if( $cartpage->page_allowed( 'payment' ) ) {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' ) ) {
					include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php';
				} else {
					include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_v2.php';
				}
			} else {
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' ) ) {
					include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php';
				} else {
					include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_information_v2.php';
				}
			}
		}
		$html_content = ob_get_clean();

		// Output new info
		$result = (object) array(
			'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
			'display_items'		=> $displayItems,
			'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
			'cart_data'			=> $return_cart_data,
			'shipping_allowed'	=> ( ( $cartpage->page_allowed( 'shipping' ) ) ? 1 : 0 ),
			'payment_allowed'	=> ( ( $cartpage->page_allowed( 'payment' ) ) ? 1 : 0 ),
			'error'				=> $errors,
			'html_content'		=> $html_content,
		);
		echo json_encode( $result );
	} else {
		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_v2.php';
		}
		$shipping_html_content = ob_get_clean();

		ob_start();
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_v2.php';
		}
		$payment_html_content = ob_get_clean();

		// Output new info
		$result = (object) array(
			'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ), wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
			'display_items'		=> $displayItems,
			'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
			'cart_data'			=> $return_cart_data,
			'shipping_allowed'	=> ( ( $cartpage->page_allowed( 'shipping' ) ) ? 1 : 0 ),
			'payment_allowed'	=> ( ( $cartpage->page_allowed( 'payment' ) ) ? 1 : 0 ),
			'error'				=> $errors,
			'shipping_content'	=> $shipping_html_content,
			'payment_content'	=> $payment_html_content,
		);
		echo json_encode( $result );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_update_order_notes', 'ec_ajax_update_order_notes' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_order_notes', 'ec_ajax_update_order_notes' );
function ec_ajax_update_order_notes() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-save-checkout-info-' . $session_id ) ) {
		die();
	}

	$ec_db = new ec_db();
	$GLOBALS['ec_cart_data']->cart_data->order_notes = sanitize_textarea_field( $_POST['ec_order_notes'] );
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_email_other', 'ec_ajax_update_email_other' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_email_other', 'ec_ajax_update_email_other' );
function ec_ajax_update_email_other() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-save-checkout-info-' . $session_id ) ) {
		die();
	}

	$ec_db = new ec_db();
	if ( ! filter_var( $_POST['ec_email_other'], FILTER_VALIDATE_EMAIL ) ) {
		$GLOBALS['ec_cart_data']->cart_data->email_other = '';
		echo json_encode( array( 'error' => 'email_format_error' ) );
	} else {
		$GLOBALS['ec_cart_data']->cart_data->email_other = sanitize_text_field( $_POST['ec_email_other'] );
		echo json_encode( array( 'success' => 1 ) );
	}
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_contact_email', 'ec_ajax_update_contact_email' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_contact_email', 'ec_ajax_update_contact_email' );
function ec_ajax_update_contact_email() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-save-checkout-info-' . $session_id ) ) {
		die();
	}

	$ec_db = new ec_db();
	$is_success = 0;
	$error_message = '';
	$coupon_message = '';
	if ( ! filter_var( $_POST['ec_contact_email'], FILTER_VALIDATE_EMAIL ) ) {
		$GLOBALS['ec_cart_data']->cart_data->email = '';
		$error_message = 'email_format_error';
	} else if ( isset( $_POST['ec_create_account'] ) && '1' == $_POST['ec_create_account']  && wp_easycart_wordpress_users::email_taken( sanitize_email( $_POST['ec_contact_email'] ) ) ) {
		$GLOBALS['ec_cart_data']->cart_data->email = '';
		$error_message = 'user_create_error';
	} else {
		$GLOBALS['ec_cart_data']->cart_data->email = sanitize_text_field( $_POST['ec_contact_email'] );
		$is_success = 1;
	}
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	$cartpage = new ec_cartpage();
	if ( $cartpage->discount->coupon_first_failed ) {
		$coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'coupon_first_only' );
	}
	$return_cart_data = ec_get_cart_data();
	$order_totals     = $cartpage->order_totals; /* 6.0.2: was undefined here */

	$result = (object) array(
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data,
		'shipping_allowed'	=> ( ( $cartpage->page_allowed( 'shipping' ) ) ? 1 : 0 ),
		'payment_allowed'	=> ( ( $cartpage->page_allowed( 'payment' ) ) ? 1 : 0 ),
		'error'				=> null,
		'is_valid_coupon'	=> null,
		'coupon_message'	=> null,
		'success'			=> null,
	);
	if ( '' != $error_message ) {
		$result->error = $error_message;
	} else {
		$result->success = 1;
	}
	if ( '' != $coupon_message ) {
		$result->is_valid_coupon = false;
		$result->coupon_message = $coupon_message;
	}
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_cart_login_v2', 'ec_ajax_cart_login_v2' );
add_action( 'wp_ajax_nopriv_ec_ajax_cart_login_v2', 'ec_ajax_cart_login_v2' );
function ec_ajax_cart_login_v2() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_nonce'] ), 'wp-easycart-cart-login-' . $session_id ) ) {
		die();
	}
	
	$cartpage = new ec_cartpage();
	$result = $cartpage->process_login_user( false );
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_goto_page_v2', 'ec_ajax_goto_page_v2' );
add_action( 'wp_ajax_nopriv_ec_ajax_goto_page_v2', 'ec_ajax_goto_page_v2' );
function ec_ajax_goto_page_v2() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-goto-cart-page-' . $session_id ) ) {
		die();
	}
	
	// Get cart and totals
	$cartpage = new ec_cartpage();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
		if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );
	}

	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );

	$return_cart_data = ec_get_cart_data();

	ob_start();
	if ( function_exists( 'wp_easycart_meta_initiate_checkout' ) && ( ! isset( $_POST['page'] ) || 'cart' !== $_POST['page'] ) && $cartpage->cart->total_items > 0 ) {
		wp_easycart_meta_initiate_checkout( $cartpage ); /* 6.0.2: leaving the cart for a checkout step starts checkout ( once per checkout session ) */
	}
	if ( get_option( 'ec_option_onepage_checkout_tabbed' ) ) {
		if ( ( isset( $_POST['page'] ) && 'information' == $_POST['page'] ) || ( isset( $_POST['page'] ) && 'shipping' == $_POST['page'] && ! $cartpage->page_allowed( 'shipping' )  ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_information_v2.php';
			}
		} else if ( ( isset( $_POST['page'] ) && 'shipping' == $_POST['page'] && $cartpage->page_allowed( 'shipping' ) ) || ( isset( $_POST['page'] ) && 'payment' == $_POST['page'] && ! $cartpage->page_allowed( 'payment' ) ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_v2.php';
			}
		} else if ( isset( $_POST['page'] ) && 'payment' == $_POST['page'] && $cartpage->page_allowed( 'payment' ) ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_v2.php';
			}
		} else {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_v2.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_v2.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_v2.php';
			}
		}
	} else {
		if ( isset( $_POST['page'] ) && 'cart' == $_POST['page'] ) {
			if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_v2.php' ) ) {
				include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_v2.php';
			} else {
				include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_v2.php';
			}
		} else {
			echo '<div class="ec_cart_onepage" id="ec_cart_onepage_cart"></div>';
			echo '<div class="ec_cart_information" id="ec_cart_onepage_info">';
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_information_v2.php' );
				}
			echo '</div>';
			$wpec_checkout_allowed = ( ! $cartpage->has_downloads && get_option( 'ec_option_allow_guest' ) ) || '' != $GLOBALS['ec_cart_data']->cart_data->user_id; /* 6.0.2: as ec_checkout_v2.php */
			if ( $wpec_checkout_allowed && get_option( 'ec_option_use_shipping' ) && $cartpage->shipping_address_allowed && ( $cartpage->cart->shippable_total_items > 0 || $cartpage->order_totals->handling_total > 0 || $cartpage->cart->excluded_shippable_total_items > 0 ) ) {
				echo '<div class="ec_cart_shipping" id="ec_cart_onepage_shipping">';
					if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' ) ) {
						include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' );
					} else {
						include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_v2.php' );
					}
				echo '</div>';
			}
			if ( $wpec_checkout_allowed ) {
				echo '<div class="ec_cart_payment" id="ec_cart_onepage_payment">';
				if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' ) ) {
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' );
				} else {
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_v2.php' );
				}
				echo '</div>';
			}
		}
	}
	$html_content = ob_get_clean();

	// Output new info
	$result = (object) array(
		'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'display_items'		=> $displayItems,
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data,
		'shipping_allowed'	=> ( ( $cartpage->page_allowed( 'shipping' ) ) ? 1 : 0 ),
		'payment_allowed'	=> ( ( $cartpage->page_allowed( 'payment' ) ) ? 1 : 0 ),
		'html_content'		=> $html_content,
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_save_shipping_method', 'ec_ajax_save_shipping_method' );
add_action( 'wp_ajax_nopriv_ec_ajax_save_shipping_method', 'ec_ajax_save_shipping_method' );
function ec_ajax_save_shipping_method() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['wpeasycart_checkout_nonce'] ), 'wp-easycart-save-shipping-method-' . $session_id ) ) {
		die();
	}
	$cartpage = new ec_cartpage();
	if ( $cartpage->shipping->is_valid_shipping_method( sanitize_text_field( $_POST['shipping_method'] ) ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_method = sanitize_text_field( $_POST['shipping_method'] );
	}
	$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = (int) sanitize_text_field( $_POST['ship_express'] );
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );
	
	// Get cart and totals
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
		if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );
	}

	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );

	$return_cart_data = ec_get_cart_data();
	
	ob_start();
	if ( $cartpage->page_allowed( 'payment' ) ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_payment_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_payment_v2.php';
		}
	} else if ( $cartpage->page_allowed( 'shipping' ) ) {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_shipping_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_shipping_v2.php';
		}
	} else {
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php' ) ) {
			include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_cart_information_v2.php';
		} else {
			include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_cart_information_v2.php';
		}
	}
	$html_content = ob_get_clean();

	// Output new info
	$result = (object) array(
		'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'display_items'		=> $displayItems,
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data,
		'shipping_allowed'	=> ( ( $cartpage->page_allowed( 'shipping' ) ) ? 1 : 0 ),
		'payment_allowed'	=> ( ( $cartpage->page_allowed( 'payment' ) ) ? 1 : 0 ),
		'html_content'		=> $html_content,
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_billing_address_type', 'ec_ajax_update_billing_address_type' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_billing_address_type', 'ec_ajax_update_billing_address_type' );
function ec_ajax_update_billing_address_type() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) ) {
		die();
	}

	if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-billing-address-type-' . $session_id ) ) {
		die();
	}

	$GLOBALS['ec_cart_data']->cart_data->shipping_selector = ( isset( $_POST['billing_address_type'] ) && '1' == $_POST['billing_address_type'] ) ? 'true' : '';
	if ( 'true' == $GLOBALS['ec_cart_data']->cart_data->shipping_selector ) {
		$name = explode( ' ', sanitize_text_field( $_POST['ec_billing_name'] ) );
		$first_name = $last_name = '';
		if ( is_array( $name ) ) {
			$first_name = ( isset( $name[0] ) ) ? $name[0] : $_POST['ec_billing_name'];
			for ( $i = 1; $i < count( $name ); $i++ ) {
				if ( $i > 1 ) {
					$last_name .= ' ';
				}
				$last_name .= $name[ $i ];
			}
		}
		if ( '' == $last_name && isset( $_POST['ec_billing_last_name'] ) ) {
			$last_name = sanitize_text_field( $_POST['ec_billing_last_name'] );
		}
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $first_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $last_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = sanitize_text_field( $_POST['ec_billing_company_name'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $_POST['ec_billing_address_line_1'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = ( isset( $_POST['ec_billing_address_line_2'] ) ) ? sanitize_text_field( $_POST['ec_billing_address_line_2'] ) : '';
		$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $_POST['ec_billing_city'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['ec_billing_state'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['ec_billing_zip'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $_POST['ec_billing_country'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = ( isset( $_POST['ec_billing_phone'] ) ) ? sanitize_text_field( $_POST['ec_billing_phone'] ) : '';
	} else {
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = $GLOBALS['ec_cart_data']->cart_data->shipping_first_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = $GLOBALS['ec_cart_data']->cart_data->shipping_last_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = $GLOBALS['ec_cart_data']->cart_data->shipping_company_name;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1;
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2;
		$GLOBALS['ec_cart_data']->cart_data->billing_city = $GLOBALS['ec_cart_data']->cart_data->shipping_city;
		$GLOBALS['ec_cart_data']->cart_data->billing_state = $GLOBALS['ec_cart_data']->cart_data->shipping_state;
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = $GLOBALS['ec_cart_data']->cart_data->shipping_zip;
		$GLOBALS['ec_cart_data']->cart_data->billing_country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = $GLOBALS['ec_cart_data']->cart_data->shipping_phone;
	}
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	die();
}

if ( ! function_exists( 'wp_easycart_wallet_shipping_method' ) ) {
	/**
	 * Saves the shipping rate an Apple Pay / Google Pay sheet ends on, as its shipping option change does, when it is one
	 * of the cart's rates for the wallet's address and not the one already saved. The caller saves the session.
	 *
	 * @since 6.0.2
	 * @param ec_cartpage $cartpage Checkout built with the wallet's shipping address ( live rates depend on it ).
	 * @param string      $method   Rate id from the wallet.
	 * @return bool Whether the saved rate changed.
	 */
	function wp_easycart_wallet_shipping_method( $cartpage, $method ) {
		$method = (string) $method;
		if ( '' === $method || 'undefined' === $method || (string) $GLOBALS['ec_cart_data']->cart_data->shipping_method === $method || ! $cartpage->shipping->is_valid_shipping_method( $method ) ) {
			return false;
		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_method    = $method;
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = ( 'shipexpress' === $method ) ? 'shipexpress' : '';
		return true;
	}
}

add_action( 'wp_ajax_ec_ajax_get_stripe_express_shipping_dynamic', 'ec_ajax_get_stripe_express_shipping_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_express_shipping_dynamic', 'ec_ajax_get_stripe_express_shipping_dynamic' );
function ec_ajax_get_stripe_express_shipping_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-shipping-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total and the intent are a card payment's ( Card flex fees ) */
	
	// Update Shipping
	$GLOBALS['ec_cart_data']->cart_data->shipping_selector = 'true';
	$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shippingAddress']['recipient'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = '';
	$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = '';

	if ( isset( $_POST['shippingAddress']['addressLine'] ) && count( $_POST['shippingAddress']['addressLine'] ) > 0 ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $GLOBALS['ec_user']->shipping->address_line_1 = sanitize_text_field( $_POST['shippingAddress']['addressLine'][0] );
	}
	if ( isset( $_POST['shippingAddress']['addressLine'] ) && count( $_POST['shippingAddress']['addressLine'] ) > 1 ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $GLOBALS['ec_user']->shipping->address_line_2 = sanitize_text_field( $_POST['shippingAddress']['addressLine'][1] );
	}
	$GLOBALS['ec_cart_data']->cart_data->shipping_city = $GLOBALS['ec_user']->shipping->city = sanitize_text_field( $_POST['shippingAddress']['city'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_state = $GLOBALS['ec_user']->shipping->state = sanitize_text_field( $_POST['shippingAddress']['region'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $GLOBALS['ec_user']->shipping->zip = sanitize_text_field( $_POST['shippingAddress']['postalCode'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_country = $GLOBALS['ec_user']->shipping->country = sanitize_text_field( $_POST['shippingAddress']['country'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shippingAddress']['phone'] );
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	$cartpage = new ec_cartpage();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );

	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	$result = (object) array(
		'shipping_rates' 	=> $cartpage->get_stripe_express_shipping_items( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'line_items'		=> $cartpage->get_stripe_express_cart_items(),
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_express_shipping_rate_dynamic', 'ec_ajax_get_stripe_express_shipping_rate_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_express_shipping_rate_dynamic', 'ec_ajax_get_stripe_express_shipping_rate_dynamic' );
function ec_ajax_get_stripe_express_shipping_rate_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-shipping-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total and the intent are a card payment's ( Card flex fees ) */
	
	// Update Shipping
	$cartpage = new ec_cartpage();
	/* 6.0.2: the rate the element sends ( shippingRate ); it looked for shipping_method, so a rate change was never saved. */
	wp_easycart_wallet_shipping_method( $cartpage, isset( $_POST['shippingRate'] ) ? sanitize_text_field( wp_unslash( $_POST['shippingRate'] ) ) : '' );
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );
	$cartpage = new ec_cartpage(); /* the rates and lines below show the new rate */

	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );

	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	$result = (object) array(
		'shipping_rates' 	=> $cartpage->get_stripe_express_shipping_items( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'line_items'		=> $cartpage->get_stripe_express_cart_items(),
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_shipping_dynamic', 'ec_ajax_get_stripe_shipping_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_shipping_dynamic', 'ec_ajax_get_stripe_shipping_dynamic' );
function ec_ajax_get_stripe_shipping_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-shipping-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total and the intent it confirms are a card payment's ( Card flex fees ) */

	if ( isset( $_POST['shippingAddress'] ) && is_array( $_POST['shippingAddress'] ) ) {
		// Update Shipping
		$GLOBALS['ec_cart_data']->cart_data->shipping_selector = 'true';
		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shippingAddress']['recipient'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = '';
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = '';

		if ( isset( $_POST['shippingAddress']['addressLine'] ) && count( $_POST['shippingAddress']['addressLine'] ) > 0 ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = $GLOBALS['ec_user']->shipping->address_line_1 = sanitize_text_field( $_POST['shippingAddress']['addressLine'][0] );
		}
		if ( isset( $_POST['shippingAddress']['addressLine'] ) && count( $_POST['shippingAddress']['addressLine'] ) > 1 ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = $GLOBALS['ec_user']->shipping->address_line_2 = sanitize_text_field( $_POST['shippingAddress']['addressLine'][1] );
		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = $GLOBALS['ec_user']->shipping->city = sanitize_text_field( $_POST['shippingAddress']['city'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = $GLOBALS['ec_user']->shipping->state = sanitize_text_field( $_POST['shippingAddress']['region'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = $GLOBALS['ec_user']->shipping->zip = sanitize_text_field( $_POST['shippingAddress']['postalCode'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = $GLOBALS['ec_user']->shipping->country = sanitize_text_field( $_POST['shippingAddress']['country'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shippingAddress']['phone'] );
	}

	$cartpage = new ec_cartpage();
	$shipping_options = $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) );
	if ( isset( $_POST['shipping_method'] ) ) { /* 6.0.2: the sheet's final rate, sent before the payment is confirmed, so the intent charges it */
		wp_easycart_wallet_shipping_method( $cartpage, sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) );
	}
	if ( '' == $GLOBALS['ec_cart_data']->cart_data->shipping_method ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_method = ( is_array( $shipping_options ) && count( $shipping_options ) > 0 ) ? $shipping_options[0]->id : '';
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	if ( ! $cartpage->order->verify_stock() ) {
		$json_response = (object) array(
			'is_valid' => false,
			'redirect' => $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=stock_invalid',
		);
		echo json_encode( $json_response );
		die();
	}
	// 6.0.2: a fulfillment partner could not price its items for this address ( or there is no shipping country ): no payment
	// starts from the sheet ( it never asks order_errors() ), and the cart says why.
	if ( class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::blocks_checkout( $cartpage, true ) ) {
		echo wp_json_encode(
			array(
				'is_valid' => false,
				'redirect' => $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=shipping_group_unavailable',
			)
		);
		die();
	}

	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );

	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );

	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	$result = (object) array(
		'is_valid' => true,
		'shipping_options' => $shipping_options,
		'display_items' => $displayItems,
		'total' => (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data' => $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_shipping_option_dynamic', 'ec_ajax_get_stripe_shipping_option_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_shipping_option_dynamic', 'ec_ajax_get_stripe_shipping_option_dynamic' );
function ec_ajax_get_stripe_shipping_option_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-shipping-option-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total and the intent are a card payment's ( Card flex fees ) */

	// Save Selected Method
	$cartpage = new ec_cartpage();
	// 6.0.2: the id as the sheet sends it, checked against the cart's rates ( a cast to a number never saved a fulfillment
	// partner's service, an extension's <slug>_<code> rate or standard / shipexpress ).
	wp_easycart_wallet_shipping_method( $cartpage, ( isset( $_POST['shippingOption']['id'] ) && is_scalar( $_POST['shippingOption']['id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['shippingOption']['id'] ) ) : '' );
	if ( $GLOBALS['ec_cart_data']->cart_data->shipping_method == 'shipexpress' ) {
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = 'shipexpress';
	} else {
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = '';
	}
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	// Get cart and totals
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );

	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );

	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	// Output new info
	$result = (object) array(
		'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_stripe_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'display_items'		=> $displayItems,
		'total'				=> (int) ( $order_totals->grand_total * 100 ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_square_shipping_address_dynamic', 'ec_ajax_update_square_shipping_address_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_square_shipping_address_dynamic', 'ec_ajax_update_square_shipping_address_dynamic' );
function ec_ajax_update_square_shipping_address_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-square-shipping-address-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total is a card payment's ( Card flex fees ) */

	// Update Shipping
	$GLOBALS['ec_cart_data']->cart_data->shipping_selector = 'true';
	if ( isset( $_POST['shippingAddress']['givenName'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shippingAddress']['givenName'] );
	}
	if ( isset( $_POST['shippingAddress']['familyName'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $_POST['shippingAddress']['familyName'] );
	}
	$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = '';

	if ( isset( $_POST['shippingAddress']['addressLines'] ) && count( $_POST['shippingAddress']['addressLines'] ) > 0 ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $_POST['shippingAddress']['addressLines'][0] );
	}
	if ( isset( $_POST['shippingAddress']['addressLines'] ) && count( $_POST['shippingAddress']['addressLines'] ) > 1 ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $_POST['shippingAddress']['addressLines'][1] );
	}
	if ( isset( $_POST['shippingAddress']['city'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $_POST['shippingAddress']['city'] );
	}
	if ( isset( $_POST['shippingAddress']['region'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shippingAddress']['region'] );
	} else if ( isset( $_POST['shippingAddress']['state'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shippingAddress']['state'] );
	}
	if ( isset( $_POST['shippingAddress']['postalCode'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shippingAddress']['postalCode'] );
	}
	if ( isset( $_POST['shippingAddress']['country'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shippingAddress']['country'] );
	} else if ( isset( $_POST['shippingAddress']['countryCode'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shippingAddress']['countryCode'] );
	}
	if ( isset( $_POST['shippingAddress']['phone'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shippingAddress']['phone'] );
	}
	$GLOBALS['ec_cart_data']->cart_data->payment_method = 'credit_card';
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	$cartpage = new ec_cartpage();
	if ( ! $cartpage->order->verify_stock() ) {
		$json_response = (object) array(
			'is_valid' => false,
			'redirect' => $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=stock_invalid',
		);
		echo json_encode( $json_response );
		die();
	}

	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );
	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );
	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	// Output new info
	$result = (object) array(
		'is_valid' => true,
		'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_square_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'display_items'		=> $cartpage->get_dynamic_square_line_items(),
		'total'				=> number_format( $order_totals->grand_total, 2, '.', '' ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_square_shipping_option_dynamic', 'ec_ajax_update_square_shipping_option_dynamic' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_square_shipping_option_dynamic', 'ec_ajax_update_square_shipping_option_dynamic' );
function ec_ajax_update_square_shipping_option_dynamic() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-square-shipping-option-dynamic-' . $session_id ) ) {
		die();
	}
	wp_easycart_wallet_pays_by_card(); /* 6.0.2: the sheet's total is a card payment's ( Card flex fees ) */

	// Save Selected Method
	$cartpage = new ec_cartpage();
	if ( $cartpage->shipping->is_valid_shipping_method( sanitize_text_field( $_POST['shippingAddress'] ) ) ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_method = sanitize_text_field( $_POST['shippingAddress'] );
	}
	if ( $GLOBALS['ec_cart_data']->cart_data->shipping_method == 'shipexpress' ) {
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = 'shipexpress';
	} else {
		$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = '';
	}
	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	if ( ! $cartpage->order->verify_stock() ) {
		$json_response = (object) array(
			'is_valid' => false,
			'redirect' => $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=stock_invalid',
		);
		echo json_encode( $json_response );
		die();
	}

	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );
	$return_cart_data = wp_easycart_wallet_page_cart_data(); /* 6.0.2: the page keeps its own payment method's totals */

	// Output new info
	$result = (object) array(
		'is_valid' => true,
		'shipping_options' 	=> $cartpage->ec_cart_display_shipping_methods_square_dynamic( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'display_items'		=> $cartpage->get_dynamic_square_line_items(),
		'total'				=> number_format( $order_totals->grand_total, 2, '.', '' ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_square_complete_payment', 'ec_ajax_square_complete_payment' );
add_action( 'wp_ajax_nopriv_ec_ajax_square_complete_payment', 'ec_ajax_square_complete_payment' );
function ec_ajax_square_complete_payment() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['easycartnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['easycartnonce'] ), 'wp-easycart-get-square-complete-payment-' . $session_id ) ) {
		/* 6.0.2: say so ( an empty answer left the page with "correct the errors" and nothing in the log ). */
		wp_easycart_square_checkout_log(
			'Square Checkout: stopped before payment',
			array(
				'Reason' => __( 'The payment page no longer matches the shopper’s cart session ( the page was open for a long time, or the cart changed in another tab ). Square was not asked to charge.', 'wp-easycart' ),
			)
		);
		echo wp_json_encode(
			array(
				'error' => wp_strip_all_tags( (string) wp_easycart_language()->get_text( 'ec_errors', 'session_expired' ) ),
				'code'  => 'session_expired',
			)
		);
		die();
	}
	if ( isset( $_POST['wallet'] ) && '1' === sanitize_key( wp_unslash( $_POST['wallet'] ) ) ) {
		wp_easycart_wallet_pays_by_card( true ); /* 6.0.2: Apple Pay / Google Pay pay as a card: Square charges, and the order keeps, the Card flex fees */
	}
	$ec_db = new ec_db();
	if ( isset( $_POST['shipping_address_first_name'] ) && '' != $_POST['shipping_address_first_name'] && 'undefined' != $_POST['shipping_address_first_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = sanitize_text_field( $_POST['shipping_address_first_name'] );
	}
	if ( isset( $_POST['shipping_address_last_name'] ) && '' != $_POST['shipping_address_last_name'] && 'undefined' != $_POST['shipping_address_last_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = sanitize_text_field( $_POST['shipping_address_last_name'] );
	}
	if ( isset( $_POST['shipping_address_company_name'] ) && '' != $_POST['shipping_address_company_name'] && 'undefined' != $_POST['shipping_address_company_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = sanitize_text_field( $_POST['shipping_address_company_name'] );
	}
	if ( isset( $_POST['shipping_address_line_1'] ) && '' != $_POST['shipping_address_line_1'] && 'undefined' != $_POST['shipping_address_line_1'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $_POST['shipping_address_line_1'] );
	}
	if ( isset( $_POST['shipping_address_line_2'] ) && '' != $_POST['shipping_address_line_2'] && 'undefined' != $_POST['shipping_address_line_2'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $_POST['shipping_address_line_2'] );
	}
	if ( isset( $_POST['shipping_address_city'] ) && '' != $_POST['shipping_address_city'] && 'undefined' != $_POST['shipping_address_city'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $_POST['shipping_address_city'] );
	}
	if ( isset( $_POST['shipping_address_region'] ) && '' != $_POST['shipping_address_region'] && 'undefined' != $_POST['shipping_address_region'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address_region'] );
	} else if ( isset( $_POST['shipping_address_dependentLocality'] ) && '' != $_POST['shipping_address_dependentLocality'] && 'undefined' != $_POST['shipping_address_dependentLocality'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address_dependentLocality'] );
	} else if ( isset( $_POST['shipping_address_state'] ) && '' != $_POST['shipping_address_state'] && 'undefined' != $_POST['shipping_address_state'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address_state'] );
	}
	if ( isset( $_POST['shipping_address_zip'] ) && '' != $_POST['shipping_address_zip'] && 'undefined' != $_POST['shipping_address_zip'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shipping_address_zip'] );
	}
	if ( isset( $_POST['shipping_address_country'] ) && '' != $_POST['shipping_address_country'] && 'undefined' != $_POST['shipping_address_country'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shipping_address_country'] );
	}
	if ( isset( $_POST['shipping_address_phone'] ) && '' != $_POST['shipping_address_phone'] && 'undefined' != $_POST['shipping_address_phone'] ) {
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['shipping_address_phone'] );
	}
	if ( ! $GLOBALS['ec_user']->user_id && isset( $_POST['shipping_address_email'] ) ) {
		$GLOBALS['ec_user']->email = sanitize_email( $_POST['shipping_address_email'] );
		$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $_POST['shipping_address_email'] );
	} else if ( !$GLOBALS['ec_user']->user_id && isset( $_POST['billing_address_email'] ) ) {
		$GLOBALS['ec_user']->email = sanitize_email( $_POST['billing_address_email'] );
		$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $_POST['billing_address_email'] );
	}
	if ( isset( $_POST['shipping_method'] ) ) { /* 6.0.2: checked against the rates for the wallet's address */
		wp_easycart_wallet_shipping_method( new ec_cartpage(), sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) );
	}
	if ( isset( $_POST['billing_address_first_name'] ) && '' != $_POST['billing_address_first_name'] && 'undefined' != $_POST['billing_address_first_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $_POST['billing_address_first_name'] );
	}
	if ( isset( $_POST['billing_address_last_name'] ) && '' != $_POST['billing_address_last_name'] && 'undefined' != $_POST['billing_address_last_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $_POST['billing_address_last_name'] );
	}
	if ( isset( $_POST['billing_address_company_name'] ) && '' != $_POST['billing_address_company_name'] && 'undefined' != $_POST['billing_address_company_name'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_company_name = sanitize_text_field( $_POST['billing_address_company_name'] );
	}
	if ( isset( $_POST['billing_address_line_1'] ) && '' != $_POST['billing_address_line_1'] && 'undefined' != $_POST['billing_address_line_1'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $_POST['billing_address_line_1'] );
	}
	if ( isset( $_POST['billing_address_line_2'] ) && '' != $_POST['billing_address_line_2'] && 'undefined' != $_POST['billing_address_line_2'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $_POST['billing_address_line_2'] );
	}
	if ( isset( $_POST['billing_address_city'] ) && '' != $_POST['billing_address_city'] && 'undefined' != $_POST['billing_address_city'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $_POST['billing_address_city'] );
	}
	if ( isset( $_POST['billing_address_region'] ) && '' != $_POST['billing_address_region'] && 'undefined' != $_POST['billing_address_region'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address_region'] );
	} else if ( isset( $_POST['billing_address_dependentLocality'] ) && '' != $_POST['billing_address_dependentLocality'] && 'undefined' != $_POST['billing_address_dependentLocality'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address_dependentLocality'] );
	} else if ( isset( $_POST['billing_address_state'] ) && '' != $_POST['billing_address_state'] && 'undefined' != $_POST['billing_address_state'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address_state'] );
	}
	if ( isset( $_POST['billing_address_zip'] ) && '' != $_POST['billing_address_zip'] && 'undefined' != $_POST['billing_address_zip'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['billing_address_zip'] );
	}
	if ( isset( $_POST['billing_address_country'] ) && '' != $_POST['billing_address_country'] && 'undefined' != $_POST['billing_address_country'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $_POST['billing_address_country'] );
	}
	if ( isset( $_POST['billing_address_phone'] ) && '' != $_POST['billing_address_phone'] && 'undefined' != $_POST['billing_address_phone'] ) {
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $_POST['billing_address_phone'] );
	}

	$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_first_name );
	$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_last_name );

	if ( !$GLOBALS['ec_cart_data']->cart_data->user_id ) {
		$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
		$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
	} else {
		$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
		$GLOBALS['ec_cart_data']->cart_data->guest_key = "";	
	}
	
	// Manage Subscriber
	$is_subscriber = ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) ? 1 : 0;
	if ( isset( $_POST['ec_cart_is_subscriber'] ) && '1' == $_POST['ec_cart_is_subscriber'] ) {
		$first_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name );
		$last_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name );
		$email = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->email );

		$ec_db->insert_subscriber( $email, $first_name, $last_name ); // Fires wpeasycart_insert_subscriber ( 6.0.2: not fired a second time below ).

		if ( $GLOBALS['ec_user']->user_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE ec_user SET is_subscriber = 1 WHERE ec_user.user_id = %d", $GLOBALS['ec_user']->user_id ) );
		}

		// MyMail Hook
		if ( function_exists( 'mailster' ) ) {
			$subscriber_id = mailster('subscribers')->add(array(
				'firstname' => $first_name,
				'lastname' => $last_name,
				'email' => $email,
				'status' => 1,
			), false );
		}
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();

	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	/* 6.0.2: Square charges this checkout's total, so it is built from the wallet's final address and shipping choice, after the tax and rate services updated. */
	$cartpage = new ec_cartpage();
	if ( ! $cartpage->order->verify_stock() ) {
		wp_easycart_square_checkout_log( 'Square Checkout: stopped before payment', array( 'Reason' => __( 'An item in the cart is no longer in stock in the quantity ordered ( stock_invalid ). Square was not asked to charge.', 'wp-easycart' ) ) );
		$json_response = (object) array(
			'error' => 'stock_invalid',
		);
		echo json_encode( $json_response );
		die();
	}

	$wpec_minimum = (float) apply_filters( 'wpeasycart_minimum_order_total', get_option( 'ec_option_minimum_order_total' ) );
	if ( $wpec_minimum > 0 && $wpec_minimum > $cartpage->cart->subtotal ) { /* 6.0.2 */
		wp_easycart_square_checkout_log( 'Square Checkout: stopped before payment', array( 'Reason' => __( 'The cart is below the store’s minimum order total ( minimum_order ). Square was not asked to charge.', 'wp-easycart' ) ) );
		echo json_encode( (object) array(
			'error' => 'minimum_order',
		) );
	} else if ( ! $cartpage->validate_cart_shipping() ) {
		wp_easycart_square_checkout_log( 'Square Checkout: stopped before payment', array( 'Reason' => __( 'The store does not ship to the address on this order ( invalid_cart_shipping ). Square was not asked to charge.', 'wp-easycart' ) ) );
		echo json_encode( (object) array(
			'error' => 'invalid_cart_shipping',
		) );
	} elseif ( class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::blocks_checkout( $cartpage, true ) ) {
		/* 6.0.2: a fulfillment partner could not price its items for this address ( or there is no shipping country ): the wallet never asks order_errors(), so it is asked here. */
		wp_easycart_square_checkout_log( 'Square Checkout: stopped before payment', array( 'Reason' => __( 'A fulfillment partner could not price shipping for its items to the address on this order, or the order has no shipping country ( shipping_group_unavailable ). Square was not asked to charge.', 'wp-easycart' ) ) );
		echo wp_json_encode(
			array(
				'error' => esc_attr( wp_easycart_shipping_groups::error_message() ),
				'code'  => 'shipping_group_unavailable',
			)
		);
	} else {
		/* 6.0.2 checkout protection: the gate before Square is asked to charge. */
		$wpec_gate = class_exists( 'wp_easycart_checkout_guard' ) ? wp_easycart_checkout_guard::check( 'checkout', array( 'gateway' => 'square', 'amount' => (float) $cartpage->order_totals->grand_total ) ) : true;
		if ( is_wp_error( $wpec_gate ) ) {
			wp_easycart_square_checkout_log(
				'Square Checkout: stopped before payment',
				array(
					/* translators: %s: checkout protection's reason code, such as protection_paused. */
					'Reason' => sprintf( __( 'Checkout protection held this payment ( %s ). Square was not asked to charge; Settings › Checkout protection shows the activity.', 'wp-easycart' ), $wpec_gate->get_error_code() ),
				)
			);
			echo json_encode( (object) array(
				'error' => esc_attr( $wpec_gate->get_error_message() ), /* no code: a blocked shopper only ever sees a decline */
			) );
		} else {
			echo $cartpage->submit_square_quick_payment_v2();
		}
	}
	die();
}

if ( ! function_exists( 'wp_easycart_stripe_refund_log' ) ) {
	/**
	 * The order's activity entry for a refund made in Stripe ( the dashboard or another app ): the same keys a refund made on
	 * the order screen writes ( order-refund-full / -partial ), with the reason and any note Stripe recorded, where it came
	 * from, and the Stripe refund id.
	 *
	 * @since 6.0.2
	 * @param int    $order_id Order.
	 * @param bool   $full     The order is now fully refunded.
	 * @param float  $amount   What this refund added.
	 * @param object $charge   The charge from the charge.refunded event.
	 */
	function wp_easycart_stripe_refund_log( $order_id, $full, $amount, $charge, $payment_id = 0 ) {
		global $wpdb;
		$refund = null;
		if ( isset( $charge->refunds->data ) && is_array( $charge->refunds->data ) && ! empty( $charge->refunds->data ) ) {
			$refund = $charge->refunds->data[0];
		} elseif ( isset( $charge->id ) ) {
			$api = ( 'stripe' === get_option( 'ec_option_payment_process_method' ) && class_exists( 'ec_stripe' ) ) ? new ec_stripe() : ( class_exists( 'ec_stripe_connect' ) ? new ec_stripe_connect() : null );
			if ( $api && method_exists( $api, 'get_charge_refunds' ) ) {
				$list   = $api->get_charge_refunds( (string) $charge->id, 1 );
				$refund = ( $list && isset( $list->data[0] ) ) ? $list->data[0] : null;
			}
		}
		$reasons = array(
			'duplicate'                 => __( 'Duplicate charge', 'wp-easycart' ),
			'fraudulent'                => __( 'Fraudulent', 'wp-easycart' ),
			'requested_by_customer'     => __( 'Requested by the customer', 'wp-easycart' ),
			'expired_uncaptured_charge' => __( 'The charge was never captured', 'wp-easycart' ),
		);
		$reason = ( $refund && isset( $refund->reason ) && isset( $reasons[ (string) $refund->reason ] ) ) ? $reasons[ (string) $refund->reason ] : '';
		$notes  = array();
		if ( $refund && isset( $refund->metadata ) && ( is_object( $refund->metadata ) || is_array( $refund->metadata ) ) ) {
			foreach ( (array) $refund->metadata as $key => $value ) {
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					$notes[] = ( in_array( strtolower( (string) $key ), array( 'note', 'reason', 'description', 'comment' ), true ) ? '' : ucfirst( str_replace( '_', ' ', (string) $key ) ) . ': ' ) . sanitize_text_field( (string) $value );
				}
			}
		}
		$wpdb->insert(
			'ec_order_log',
			array(
				'order_id'      => (int) $order_id,
				'order_log_key' => $full ? 'order-refund-full' : 'order-refund-partial',
			)
		);
		$log_id = (int) $wpdb->insert_id;
		if ( ! $log_id ) {
			return;
		}
		$meta = array(
			'refunded_amount'  => round( (float) $amount, 2 ),
			'refund_reason'    => $reason,
			'refund_note'      => implode( ' · ', $notes ),
			'refund_by'        => __( 'Stripe, outside WP EasyCart', 'wp-easycart' ),
			'refund_source'    => 'stripe',
			'refund_reference' => ( $refund && isset( $refund->id ) ) ? sanitize_text_field( (string) $refund->id ) : '',
			/* 6.0.2: the payment it gave money back from ( wp_easycart_order_refunds ). */
			'refund_payments'  => ( $payment_id > 0 ) ? wp_json_encode(
				array(
					array(
						'payment'   => (int) $payment_id,
						'amount'    => round( (float) $amount, 2 ),
						'reference' => ( $refund && isset( $refund->id ) ) ? sanitize_text_field( (string) $refund->id ) : '',
						'route'     => 'stripe',
					),
				)
			) : '',
		);
		foreach ( $meta as $meta_key => $meta_value ) {
			if ( '' === (string) $meta_value ) {
				continue;
			}
			$wpdb->insert(
				'ec_order_log_meta',
				array(
					'order_log_id'         => $log_id,
					'order_id'             => (int) $order_id,
					'order_log_meta_key'   => $meta_key,
					'order_log_meta_value' => (string) $meta_value,
				)
			);
		}
	}
}

if ( ! function_exists( 'wp_easycart_stripe_charge_refunded' ) ) {
	/**
	 * Stripe webhook charge.refunded for a charge the order's payment register knows ( wp_easycart_order_refunds ): what the
	 * charge now reports refunded, less what its payment has refunded ( and a refund WP EasyCart is making on it right now ),
	 * is a refund made in the Stripe dashboard. The order's refund total and status follow, the refund is logged against its
	 * payment, and the refund hooks and email follow as for any refund.
	 *
	 * @since 6.0.2
	 * @param array  $found  wp_easycart_order_refunds::for_stripe_charge().
	 * @param object $charge The charge from the event.
	 */
	function wp_easycart_stripe_charge_refunded( $found, $charge ) {
		global $wpdb;
		$order    = $found['order'];
		$payment  = $found['payment'];
		$order_id = (int) $order->order_id;
		$cents    = 0;
		if ( isset( $charge->amount_refunded ) ) {
			$cents = (int) $charge->amount_refunded;
		} elseif ( isset( $charge->refunds->data ) && is_array( $charge->refunds->data ) ) {
			foreach ( $charge->refunds->data as $refund ) {
				$cents += (int) $refund->amount;
			}
		}
		$known  = (float) $payment['refunded'] + wp_easycart_order_refunds::pending( $found['charges'] );
		$amount = round( $cents / 100 - $known, 2 );
		if ( $amount < 0.01 ) {
			return; /* made from the order screen ( already counted ), or nothing new */
		}
		$previous  = (int) $order->orderstatus_id;
		$new_total = round( (float) $order->refund_total + $amount, 2 );
		$basis     = class_exists( 'wp_easycart_order_payments' ) ? (float) wp_easycart_order_payments::refund_basis( $order ) : (float) $order->grand_total;
		$status    = ( $new_total >= $basis - 0.004 ) ? 16 : 17;
		$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, refund_total = %s WHERE order_id = %d', $status, $new_total, $order_id ) );
		/* Written before the status hook: the Reports ledger records the refund on that hook, against this payment. */
		wp_easycart_stripe_refund_log( $order_id, 16 === $status, $amount, $charge, (int) $payment['id'] );
		if ( $previous !== $status ) {
			do_action( 'wpeasycart_order_status_update', $order_id, $status, $previous );
		}
		if ( 16 === $status ) {
			do_action( 'wpeasycart_full_order_refund', $order_id );
		} else {
			do_action( 'wpeasycart_partial_order_refund', $order_id, $amount, $new_total );
		}
		if ( get_option( 'ec_option_auto_send_refund_email' ) && class_exists( 'ec_db_admin' ) ) {
			$db_admin  = new ec_db_admin();
			$order_row = $db_admin->get_order_row_admin( $order_id );
			if ( $order_row ) {
				$order_display = new ec_orderdisplay( $order_row, true, true );
				$order_display->send_refund_email();
			}
		}
		do_action( 'wpeasycart_stripe_webhook_order_refunded', $order_id );
	}
}

if ( ! function_exists( 'wp_easycart_square_checkout_log' ) ) {
	/**
	 * A Square checkout problem in the store's gateway log ( Settings › Log entries ), for a payment that stopped before
	 * Square was asked to charge ( the gateway logs the charge itself ). Follows the "Log gateway and webhook responses"
	 * switch, and writes at most 60 of these entries an hour so a flood of bad requests cannot fill the table.
	 *
	 * @since 6.0.2
	 *
	 * @param string $title Short title ( the log's processor column ).
	 * @param array  $lines Label => value lines.
	 * @return bool Whether an entry was written.
	 */
	function wp_easycart_square_checkout_log( $title, $lines ) {
		if ( ! get_option( 'ec_option_enable_gateway_log' ) ) {
			return false;
		}
		$window = get_transient( 'wpec_square_checkout_log' );
		if ( ! is_array( $window ) || ! isset( $window['start'], $window['count'] ) ) {
			$window = array(
				'start' => time(),
				'count' => 0,
			);
		}
		if ( $window['count'] >= 60 ) {
			return false;
		}
		++$window['count'];
		set_transient( 'wpec_square_checkout_log', $window, max( 60, $window['start'] + HOUR_IN_SECONDS - time() ) );
		$text = '';
		foreach ( (array) $lines as $label => $value ) {
			$value = wp_easycart_square_log_scrub( $value );
			if ( '' !== $value ) {
				$text .= $label . ': ' . $value . "\n";
			}
		}
		$db = new ec_db();
		$db->insert_response( 0, 1, substr( (string) $title, 0, 100 ), $text );
		return true;
	}
}

if ( ! function_exists( 'wp_easycart_square_log_scrub' ) ) {
	/**
	 * One log value as plain text, with anything that looks like a payment token or a card number removed.
	 *
	 * @since 6.0.2
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function wp_easycart_square_log_scrub( $value ) {
		$value = sanitize_textarea_field( (string) $value );
		$value = preg_replace( '/\b(cnon|ccof|verf|gftc|wnon|bnon|bauth|cash)([:_-])[A-Za-z0-9:_\-]{4,}/i', '$1$2[removed]', $value );
		$value = preg_replace( '/\b(?:\d[ -]?){13,19}\b/', '[number removed]', $value );
		return trim( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 600 ) : substr( $value, 0, 600 ) );
	}
}

if ( ! function_exists( 'wp_easycart_square_log_guard' ) ) {
	/**
	 * Nonce check for the Square checkout's browser report ( not tied to the cart session, so a page whose session
	 * changed can still report it ).
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_square_log_guard() {
		return isset( $_POST['nonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-square-checkout-log' );
	}
}

add_action( 'wp_ajax_ec_ajax_square_checkout_log', 'wp_easycart_square_checkout_log_ajax' );
add_action( 'wp_ajax_nopriv_ec_ajax_square_checkout_log', 'wp_easycart_square_checkout_log_ajax' );
/**
 * AJAX ec_ajax_square_checkout_log: the Square checkout script reports a payment that stopped in the browser ( Square's
 * card form did not load, the card check failed, the store's answer could not be read ): one line in the gateway log.
 * Never the card or a token.
 *
 * @since 6.0.2
 */
function wp_easycart_square_checkout_log_ajax() {
	if ( ! wp_easycart_square_log_guard() || 'square' !== get_option( 'ec_option_payment_process_method' ) ) {
		wp_send_json_error();
	}
	$stages = array(
		'init'     => __( 'Square’s card form did not load, so no payment could start.', 'wp-easycart' ),
		'tokenize' => __( 'Square could not read the payment details.', 'wp-easycart' ),
		'verify'   => __( 'Square’s card check ( buyer verification / 3-D Secure ) did not pass, so the store was not asked to charge.', 'wp-easycart' ),
		'request'  => __( 'The payment request did not reach the store ( a network problem or a blocked request ).', 'wp-easycart' ),
		'response' => __( 'The store’s answer to the payment request could not be read, or had other text printed around it.', 'wp-easycart' ),
		'script'   => __( 'A script error stopped the payment before the store was asked to charge.', 'wp-easycart' ),
	);
	$stage  = isset( $_POST['stage'] ) ? sanitize_key( wp_unslash( $_POST['stage'] ) ) : '';
	if ( ! isset( $stages[ $stage ] ) ) {
		wp_send_json_error();
	}
	$amount = isset( $_POST['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0;
	wp_easycart_square_checkout_log(
		'Square Checkout: payment stopped in the browser',
		array(
			'What happened'  => $stages[ $stage ],
			'Details'        => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			'Error type'     => isset( $_POST['error_name'] ) ? sanitize_text_field( wp_unslash( $_POST['error_name'] ) ) : '',
			'Payment method' => isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '',
			'Checkout'       => isset( $_POST['checkout'] ) ? sanitize_text_field( wp_unslash( $_POST['checkout'] ) ) : '',
			'Amount'         => ( $amount > 0 ) ? number_format( $amount, 2, '.', '' ) . ' ' . get_option( 'ec_option_square_currency' ) : '',
			'Sandbox'        => get_option( 'ec_option_square_is_sandbox' ) ? 'yes' : 'no',
			'Browser'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 ) : '',
		)
	);
	wp_send_json_success();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_complete_payment', 'ec_ajax_get_stripe_complete_payment' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_complete_payment', 'ec_ajax_get_stripe_complete_payment' );
function ec_ajax_get_stripe_complete_payment() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-complete-payment-' . $session_id ) ) {
		wp_easycart_checkout_ajax_session_expired(); /* 6.0.2: a URL with the notice, never an empty answer */
	}

	/* 6.0.2: only a payment Stripe confirms for this checkout's PaymentIntent makes an order ( as the checkout's own completion does ). */
	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$payment_intent = $stripe->get_payment_intent( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );
	if ( ! $payment_intent || empty( $payment_intent->id ) || ! in_array( $payment_intent->status, array( 'succeeded', 'processing', 'requires_capture' ), true ) ) {
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) );
		die();
	}
	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s OR gateway_transaction_id = %s', $payment_intent->id, $payment_intent->id . ':' . $payment_intent->client_secret ) );
	if ( $order ) {
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order->order_id ) ) );
		die();
	}
	/* 6.0.2: an Apple Pay / Google Pay payment is a card payment: the order carries the Card flex fees its intent was charged with. */
	wp_easycart_wallet_pays_by_card( true );

	$ec_db = new ec_db();
	if ( isset( $_POST['shipping_address'] ) ) {
		$shipping_name = sanitize_text_field( $_POST['shipping_address']['recipient'] );
		$shipping_names = explode( " ", $shipping_name );
		$GLOBALS['ec_cart_data']->cart_data->shipping_first_name = "";
		for ( $i=0; $i<count( $shipping_names ) - 1; $i++ ) {
			if ( $i > 0 )
				$GLOBALS['ec_cart_data']->cart_data->shipping_first_name .= ' ';
			$GLOBALS['ec_cart_data']->cart_data->shipping_first_name .= $shipping_names[$i];
		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_last_name = ( count( $shipping_names ) > 1 ) ? $shipping_names[count( $shipping_names ) - 1] : '';
		$GLOBALS['ec_cart_data']->cart_data->shipping_company_name = sanitize_text_field( $_POST['shipping_address']['organization'] );
		if ( isset( $_POST['shipping_address']['addressLine'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = ( count( $_POST['shipping_address']['addressLine'] ) > 0 ) ? sanitize_text_field( $_POST['shipping_address']['addressLine'][0] ) : '';
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = ( count( $_POST['shipping_address']['addressLine'] ) > 1 ) ? sanitize_text_field( $_POST['shipping_address']['addressLine'][1] ) : '';
		} else if ( isset( $_POST['shipping_address']['addressLines'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = ( count( $_POST['shipping_address']['addressLines'] ) > 0 ) ? sanitize_text_field( $_POST['shipping_address']['addressLines'][0] ) : '';
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = ( count( $_POST['shipping_address']['addressLines'] ) > 1 ) ? sanitize_text_field( $_POST['shipping_address']['addressLines'][1] ) : '';
		} else if ( isset( $_POST['shipping_address']['line1'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = sanitize_text_field( $_POST['shipping_address']['line1'] );
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = sanitize_text_field( $_POST['shipping_address']['line2'] );
		} else {
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 = '';
			$GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 = '';
		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_city = sanitize_text_field( $_POST['shipping_address']['city'] );
		if ( isset( $_POST['shipping_address']['region'] ) && $_POST['shipping_address']['region'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address']['region'] );

		} else if ( isset( $_POST['shipping_address']['dependentLocality'] ) && $_POST['shipping_address']['dependentLocality'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address']['dependentLocality'] );

		} else if ( isset( $_POST['shipping_address']['state'] ) && $_POST['shipping_address']['stat'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = sanitize_text_field( $_POST['shipping_address']['state'] );

		} else {
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = '';
		}
		if ( isset( $_POST['shipping_address']['postalCode'] ) && $_POST['shipping_address']['postalCode'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shipping_address']['postalCode'] );

		} else if ( isset( $_POST['shipping_address']['postal_code'] ) && $_POST['shipping_address']['postal_code'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['shipping_address']['postal_code'] );

		} else {
			$GLOBALS['ec_cart_data']->cart_data->shipping_zip = '';

		}
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['shipping_address']['country'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_phone = sanitize_text_field( $_POST['phone'] );
	}

	if ( isset( $_POST['shipping_method'] ) ) { /* 6.0.2: checked against the rates for the wallet's address */
		wp_easycart_wallet_shipping_method( new ec_cartpage(), sanitize_text_field( wp_unslash( $_POST['shipping_method'] ) ) );
	}

	if ( isset( $_POST['billing_name'] ) && $_POST['billing_name'] != '' ) {
		$billing_name = sanitize_text_field( $_POST['billing_name'] );
		$billing_names = explode( " ", $billing_name );
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = "";
		for ( $i=0; $i<count( $billing_names ) - 1; $i++ ) {
			if ( $i > 0 )
				$GLOBALS['ec_cart_data']->cart_data->billing_first_name .= ' ';
			$GLOBALS['ec_cart_data']->cart_data->billing_first_name .= $billing_names[$i];
		}
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = ( count( $billing_names ) > 1 ) ? $billing_names[count( $billing_names ) - 1] : '';
		if ( isset( $_POST['billing_address']['addressLine'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = ( count( $_POST['billing_address']['addressLine'] ) > 0 ) ? sanitize_text_field( $_POST['billing_address']['addressLine'][0] ) : '';
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = ( count( $_POST['billing_address']['addressLine'] ) > 1 ) ? sanitize_text_field( $_POST['billing_address']['addressLine'][1] ) : '';
		} else if ( isset( $_POST['billing_address']['addressLines'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = ( count( $_POST['billing_address']['addressLines'] ) > 0 ) ? sanitize_text_field( $_POST['billing_address']['addressLines'][0] ) : '';
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = ( count( $_POST['billing_address']['addressLines'] ) > 1 ) ? sanitize_text_field( $_POST['billing_address']['addressLines'][1] ) : '';
		} else if ( isset( $_POST['billing_address']['line1'] ) ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $_POST['billing_address']['line1'] );
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $_POST['billing_address']['line2'] );
		} else {
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1;
			$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2;
		}
		$GLOBALS['ec_cart_data']->cart_data->billing_city = ( isset( $_POST['billing_address']['city'] ) ) ? sanitize_text_field( $_POST['billing_address']['city'] ) : sanitize_text_field( $_POST['shipping_address']['city'] );
		if ( isset( $_POST['billing_address']['region'] ) && $_POST['billing_address']['region'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address']['region'] );

		} else if ( isset( $_POST['billing_address']['dependentLocality'] ) && $_POST['billing_address']['dependentLocality'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address']['dependentLocality'] );

		} else if ( isset( $_POST['billing_address']['state'] ) && $_POST['billing_address']['stat'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $_POST['billing_address']['state'] );

		} else {
			$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_state );
		}
		if ( isset( $_POST['billing_address']['postalCode'] ) && $_POST['billing_address']['postalCode'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['billing_address']['postalCode'] );

		} else if ( isset( $_POST['billing_address']['postal_code'] ) && $_POST['billing_address']['postal_code'] != '' ) {
			$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $_POST['billing_address']['postal_code'] );

		} else {
			$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_zip );

		}
		$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $_POST['billing_address']['country'] );
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $_POST['billing_phone'] );

	} else {
		$GLOBALS['ec_cart_data']->cart_data->billing_first_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_first_name );
		$GLOBALS['ec_cart_data']->cart_data->billing_last_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_last_name );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_1 = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_1 );
		$GLOBALS['ec_cart_data']->cart_data->billing_address_line_2 = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_address_line_2 );
		$GLOBALS['ec_cart_data']->cart_data->billing_city = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_city );
		$GLOBALS['ec_cart_data']->cart_data->billing_state = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_state );
		$GLOBALS['ec_cart_data']->cart_data->billing_zip = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_zip );
		$GLOBALS['ec_cart_data']->cart_data->billing_country = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_country );
		$GLOBALS['ec_cart_data']->cart_data->billing_phone = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->shipping_phone );

	}

	$GLOBALS['ec_cart_data']->cart_data->first_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_first_name );
	$GLOBALS['ec_cart_data']->cart_data->last_name = sanitize_text_field( $GLOBALS['ec_cart_data']->cart_data->billing_last_name );

	if ( !$GLOBALS['ec_user']->user_id && isset( $_POST['email'] ) ) {
		$GLOBALS['ec_user']->email = sanitize_email( $_POST['email'] );
		$GLOBALS['ec_cart_data']->cart_data->email = sanitize_email( $_POST['email'] );
	}

	if ( !$GLOBALS['ec_cart_data']->cart_data->user_id ) {
		$GLOBALS['ec_cart_data']->cart_data->is_guest = true;
		$GLOBALS['ec_cart_data']->cart_data->guest_key = sanitize_text_field( $GLOBALS['ec_cart_data']->ec_cart_id );
	} else {
		$GLOBALS['ec_cart_data']->cart_data->is_guest = false;
		$GLOBALS['ec_cart_data']->cart_data->guest_key = "";	
	}
	$payment_intent_id = $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id;
	$GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id = ''; /* before wpeasycart_cart_updated, so nothing moves the paid intent's amount */
	$GLOBALS['ec_cart_data']->save_session_to_db();

	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	/* 6.0.2: the order's totals come from the wallet's final address and shipping choice, after the tax and rate services updated. */
	$cartpage = new ec_cartpage();
	if ( ! $cartpage->validate_cart_shipping() ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . "ec_cart_error=invalid_cart_shipping" ) );
	} else {
		/* 6.0.2: the payment was checked above; one that is not this total puts the order on hold with a note, as the card completion does. */
		$goto_url = $cartpage->submit_stripe_quick_payment(
			$payment_intent_id,
			isset( $_POST['card_type'] ) ? sanitize_text_field( wp_unslash( $_POST['card_type'] ) ) : '',
			isset( $_POST['last_4'] ) ? sanitize_text_field( wp_unslash( $_POST['last_4'] ) ) : '',
			isset( $_POST['exp_month'] ) ? sanitize_text_field( wp_unslash( $_POST['exp_month'] ) ) : '',
			isset( $_POST['exp_year'] ) ? sanitize_text_field( wp_unslash( $_POST['exp_year'] ) ) : '',
			! wp_easycart_stripe_intent_matches_cart( $payment_intent, $cartpage )
		);
		echo esc_url_raw( $goto_url );
	}
	die();
}

/**
 * A checkout completion call whose nonce does not match the cart session ( the page was open for a long time, the cart
 * changed in another tab, a cached copy of the page ): answer with the payment page and its "session expired" notice.
 * These calls answer with the page to go to, and an empty answer sent the shopper to the same page again with no message.
 *
 * @since 6.0.2
 * @param string $page Cart page key ( wpeasycart_links()->get_cart_page() ).
 */
function wp_easycart_checkout_ajax_session_expired( $page = 'checkout_payment' ) {
	echo esc_url_raw( wpeasycart_links()->get_cart_page( $page, array( 'ec_cart_error' => 'session_expired' ) ) );
	die();
}

add_action( 'wp_ajax_ec_ajax_complete_payment_manual', 'ec_ajax_complete_payment_manual' );
add_action( 'wp_ajax_nopriv_ec_ajax_complete_payment_manual', 'ec_ajax_complete_payment_manual' );
function ec_ajax_complete_payment_manual() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-complete-payment-manual-' . $session_id ) ) {
		wp_easycart_checkout_ajax_session_expired(); /* 6.0.2: a URL with the notice, never an empty answer */
	}
	
	$ec_db = new ec_db();
	$cartpage = new ec_cartpage();
	if ( ! $cartpage->use_manual_payment() ) { /* 6.0.2: only when the store takes manual payment */
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) );
		die();
	}
	$order_errors = $cartpage->order_errors(); /* 6.0.2 */
	if ( $order_errors ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=' . rawurlencode( $order_errors[0] ) ) );
	} else if ( ! $cartpage->validate_cart_shipping() ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . "ec_cart_error=invalid_cart_shipping" ) );
	} else {
		$goto_url = $cartpage->submit_manual_order_v2();
		echo esc_url_raw( $goto_url );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_cart_validate_stock', 'ec_ajax_cart_validate_stock' );
add_action( 'wp_ajax_nopriv_ec_ajax_cart_validate_stock', 'ec_ajax_cart_validate_stock' );
function ec_ajax_cart_validate_stock() {
	global $wpdb;
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-easycart-validate-stock-' . $session_id ) ) {
		die();
	}
	
	$cartpage = new ec_cartpage();
	$json_response = (object) array(
		'is_valid' => $cartpage->order->verify_stock(),
		'redirect' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'stock_invalid' ) ) ),
	);
	/* 6.0.2: the classic payment step's Stripe finishes without posting the form, so the page sends the form along: what the
	   step asked ( checkout fields, a PO number ) is saved as a Place order post saves it, and the step's own checks
	   ( wpeasycart_checkout_step_error ) are asked before the charge. */
	if ( $json_response->is_valid && isset( $_POST['wpec_payment_form'] ) && is_string( $_POST['wpec_payment_form'] ) ) {
		$wpec_payment_form = array();
		wp_parse_str( wp_unslash( $_POST['wpec_payment_form'] ), $wpec_payment_form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each wpeasycart_submit_order_process listener sanitizes the fields it reads.
		foreach ( $wpec_payment_form as $wpec_key => $wpec_value ) {
			if ( ! isset( $_POST[ $wpec_key ] ) ) {
				$_POST[ $wpec_key ] = wp_slash( $wpec_value ); /* as WordPress hands $_POST to the listeners */
			}
		}
		/** This action is documented in inc/classes/cart/ec_cartpage.php ( process_submit_order() ). */
		do_action( 'wpeasycart_submit_order_process' );
		$GLOBALS['ec_cart_data']->save_session_to_db();
	}
	if ( $json_response->is_valid ) {
		$wpec_step_error = $cartpage->checkout_step_error( 'payment' );
		if ( null !== $wpec_step_error ) {
			$json_response->is_valid = false;
			$json_response->redirect = esc_url_raw( wpeasycart_links()->get_cart_page( $wpec_step_error['page'], array( 'ec_cart_error' => $wpec_step_error['code'] ) ) );
		}
	}
	echo json_encode( $json_response );
	die();
}

add_action( 'wp_ajax_ec_ajax_onepage_check', 'ec_ajax_onepage_check' );
add_action( 'wp_ajax_nopriv_ec_ajax_onepage_check', 'ec_ajax_onepage_check' );
/**
 * The one-page checkout's Place order, after the page saved its fields: the order's problems ( shown on the page, nothing
 * is charged ), the total to charge, and the saved addresses the payment scripts send to the gateway ( never the values
 * the page was printed with ). Also keeps the Stripe PaymentIntent on the order's total.
 *
 * @since 6.0.2
 */
function ec_ajax_onepage_check() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
	if ( ! isset( $_POST['wpeasycart_checkout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpeasycart_checkout_nonce'] ) ), 'wp-easycart-save-checkout-info-' . $session_id ) ) {
		wp_send_json_error(
			array(
				'errors' => array(
					array(
						'code'    => 'session_expired',
						'message' => wp_strip_all_tags( wp_easycart_language()->get_text( 'ec_errors', 'session_expired' ) ),
					),
				),
			)
		);
	}
	$cartpage = new ec_cartpage();
	$errors   = $cartpage->order_error_messages();
	/* 6.0.2: a refusal answers success false ( it answered success true with the problems inside, so the network log and the
	   page seemed to disagree ). Each problem carries its code; the page prints it on the message line. */
	if ( $errors ) {
		wp_send_json_error( array( 'errors' => $errors ) );
	}
	$cd       = $GLOBALS['ec_cart_data']->cart_data;
	$method   = (string) get_option( 'ec_option_payment_process_method' );
	if ( ! $errors && $cartpage->order_totals->grand_total > 0 && '' != $cd->stripe_paymentintent_id && ( ( 'stripe' == $method && class_exists( 'ec_stripe' ) ) || ( 'stripe_connect' == $method && class_exists( 'ec_stripe_connect' ) ) ) ) {
		$stripe = ( 'stripe' == $method ) ? new ec_stripe() : new ec_stripe_connect();
		$stripe->update_payment_intent_total( $cd->stripe_paymentintent_id, $cartpage->order_totals );
	}
	$session = array( 'email' => (string) $cd->email );
	foreach ( array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ) as $key ) {
		$session[ 'billing_' . $key ]  = isset( $cd->{ 'billing_' . $key } ) ? (string) $cd->{ 'billing_' . $key } : '';
		$session[ 'shipping_' . $key ] = isset( $cd->{ 'shipping_' . $key } ) ? (string) $cd->{ 'shipping_' . $key } : '';
	}
	$items = array(); /* Affirm's checkout lists the items */
	if ( get_option( 'ec_option_use_affirm' ) ) {
		foreach ( $cartpage->cart->cart as $cart_item ) {
			$items[] = array(
				'display_name'   => wp_strip_all_tags( (string) $cart_item->title ),
				'sku'            => (string) $cart_item->model_number,
				'unit_price'     => (int) number_format( 100 * (float) $cart_item->unit_price, 0, '', '' ),
				'qty'            => (int) $cart_item->quantity,
				'item_image_url' => (string) $cart_item->get_image_url(),
				'item_url'       => (string) $cart_item->get_product_url(),
			);
		}
	}
	wp_send_json_success(
		array(
			'errors'              => $errors,
			'grand_total'         => number_format( (float) $cartpage->order_totals->grand_total, 2, '.', '' ),
			'grand_total_display' => $GLOBALS['currency']->get_currency_display( $cartpage->order_totals->get_converted_grand_total(), false ), /* as the page shows it */
			'totals'              => array(
				'tax'      => (int) number_format( 100 * (float) $cartpage->order_totals->tax_total, 0, '', '' ),
				'shipping' => (int) number_format( 100 * (float) $cartpage->order_totals->shipping_total, 0, '', '' ),
			),
			'items'               => $items,
			'session'             => $session,
			'cart_data'           => $errors ? false : ec_get_cart_data(), /* refreshes the summary when the total changed */
		)
	);
}

add_action( 'wp_ajax_ec_ajax_v2_complete_free_payment', 'ec_ajax_v2_complete_free_payment' );
add_action( 'wp_ajax_nopriv_ec_ajax_v2_complete_free_payment', 'ec_ajax_v2_complete_free_payment' );
function ec_ajax_v2_complete_free_payment() {
	global $wpdb;
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-v2-complete-payment-main-' . $session_id ) ) {
		wp_easycart_checkout_ajax_session_expired(); /* 6.0.2: a URL with the notice, never an empty answer */
	}

	$cartpage = new ec_cartpage();
	if ( ! isset( $cartpage->order_totals->grand_total ) || round( (float) $cartpage->order_totals->grand_total, 2 ) > 0 ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . "ec_cart_error=invalid_checkout" ) );
	} else if ( $order_errors = $cartpage->order_errors() ) { /* 6.0.2: once ( verify_stock() changes the cart ) */
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . 'ec_cart_error=' . rawurlencode( $order_errors[0] ) ) );
	} else if ( ! $cartpage->validate_cart_shipping() ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . "ec_cart_error=invalid_cart_shipping" ) );
	} else {
		$goto_url = $cartpage->submit_v2_free_quick_payment();
		echo esc_url_raw( $goto_url );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_complete_payment_main', 'ec_ajax_get_stripe_complete_payment_main' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_complete_payment_main', 'ec_ajax_get_stripe_complete_payment_main' );
function ec_ajax_get_stripe_complete_payment_main() {
	global $wpdb;
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-complete-payment-main-' . $session_id ) ) {
		wp_easycart_checkout_ajax_session_expired(); /* 6.0.2: a URL with the notice, never an empty answer */
	}

	// Get Payment Intent Info
	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$payment_intent = $stripe->get_payment_intent( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );
	if ( ! $payment_intent || empty( $payment_intent->id ) ) {
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) );
		die();
	}
	$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $payment_intent->id . ':' . $payment_intent->client_secret ) );
	if ( $order ) { /* Verify Order Doesn't Already Exist! */
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order->order_id ) ) );
		die();
	}
	/* 6.0.2: nothing was paid ( a failed or abandoned intent ): no order. */
	if ( ! in_array( $payment_intent->status, array( 'succeeded', 'processing', 'requires_capture' ), true ) ) {
		echo esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_payment', array( 'ec_cart_error' => 'payment_failed' ) ) );
		die();
	}
	/* 6.0.2: paid, but not this cart's total ( the cart changed after the payment ): the order is created on hold for the merchant to check. */
	$wpec_amount_ok = wp_easycart_stripe_intent_matches_cart( $payment_intent );

	$charge = $stripe->get_charge( $payment_intent->latest_charge );
	$payment_method = $last_4 = $exp_month = $exp_year = '';
	if ( $charge && isset( $charge->payment_method_details ) && isset( $charge->payment_method_details->type ) ) {
		if ( 'ach_debit' == $charge->payment_method_details->type ) { 
			$payment_method = $charge->payment_method_details->ach_debit->bank_name;
			$last_4 = $charge->payment_method_details->ach_debit->last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'acss_debit' == $charge->payment_method_details->type ) { 
			$payment_method = $charge->payment_method_details->acss_debit->bank_name;
			$last_4 = $charge->payment_method_details->acss_debit->last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'affirm' == $charge->payment_method_details->type ) { 
			$payment_method = 'Affirm';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'afterpay_clearpay' == $charge->payment_method_details->type ) { 
			$payment_method = 'Afterpay / Clearpay';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'alipay' == $charge->payment_method_details->type ) { 
			$payment_method = 'Alipay';
			$last_4 = $charge->payment_method_details->alipay->transaction_id;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'au_becs_debit' == $charge->payment_method_details->type ) { 
			$payment_method = 'BECS';
			$last_4 = $charge->payment_method_details->au_becs_debit->last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'bancontact' == $charge->payment_method_details->type ) { 
			$payment_method = 'Bancontact';
			$last_4 = $charge->payment_method_details->bancontact->bacs_debit;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'bacs_debit' == $charge->payment_method_details->type ) { 
			$payment_method = 'BACS';
			$last_4 = $charge->payment_method_details->bacs_debit->last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'blik' == $charge->payment_method_details->type ) { 
			$payment_method = 'BLIK';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'boleto' == $charge->payment_method_details->type ) { 
			$payment_method = 'Boleto';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'eps' == $charge->payment_method_details->type ) { 
			$payment_method = 'EPS';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'fpx' == $charge->payment_method_details->type ) { 
			$payment_method = 'FPX';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'giropay' == $charge->payment_method_details->type ) { 
			$payment_method = 'Giropay';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'grabpay' == $charge->payment_method_details->type ) { 
			$payment_method = 'Grabpay';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'ideal' == $charge->payment_method_details->type ) { 
			$payment_method = 'iDeal';
			$last_4 = $charge->payment_method_details->ideal->iban_last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'klarna' == $charge->payment_method_details->type ) { 
			$payment_method = 'Klarna';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'kobini' == $charge->payment_method_details->type ) { 
			$payment_method = 'Kobini';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'link' == $charge->payment_method_details->type ) { 
			$payment_method = 'Link';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'multibanco' == $charge->payment_method_details->type ) { 
			$payment_method = 'Multibanco';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'oxxo' == $charge->payment_method_details->type ) { 
			$payment_method = 'OXXO';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'p24' == $charge->payment_method_details->type ) { 
			$payment_method = 'P24';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'paynow' == $charge->payment_method_details->type ) { 
			$payment_method = 'Paynow';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'pix' == $charge->payment_method_details->type ) { 
			$payment_method = 'Pix';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'promptpay' == $charge->payment_method_details->type ) { 
			$payment_method = 'Promptpay';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'sepa_debit' == $charge->payment_method_details->type ) { 
			$payment_method = 'SEPA';
			$last_4 = $charge->payment_method_details->sepa_debit->last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'sofort' == $charge->payment_method_details->type ) { 
			$payment_method = 'Sofort';
			$last_4 = $charge->payment_method_details->sofort->iban_last4;
			$exp_month = '';
			$exp_year = '';

		} else if ( 'wechat' == $charge->payment_method_details->type ) { 
			$payment_method = 'WeChat';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else if ( 'wechat_pay' == $charge->payment_method_details->type ) { 
			$payment_method = 'WeChat';
			$last_4 = '';
			$exp_month = '';
			$exp_year = '';

		} else {
			$payment_method = $charge->payment_method_details->card->brand;
			$last_4 = $charge->payment_method_details->card->last4;
			$exp_month = $charge->payment_method_details->card->exp_month;
			$exp_year = $charge->payment_method_details->card->exp_year;
		}
	}

	// Create the Stripe Order Dynamically
	$cartpage = new ec_cartpage();
	if ( ! $cartpage->validate_cart_shipping() ) {
		echo esc_url_raw( apply_filters( 'wp_easycart_invalid_checkout_details_url', $cartpage->cart_page . $cartpage->permalink_divider . "ec_cart_error=invalid_cart_shipping" ) );
	} else {
		$goto_url = $cartpage->submit_stripe_quick_payment( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $payment_method, $last_4, $exp_month, $exp_year, ! $wpec_amount_ok );
		echo esc_url_raw( $goto_url );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_complete_payment_invoice', 'ec_ajax_get_stripe_complete_payment_invoice' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_complete_payment_invoice', 'ec_ajax_get_stripe_complete_payment_invoice' );
function ec_ajax_get_stripe_complete_payment_invoice() {
	/* 6.0.2: the pay link page is wp_easycart_order_pay, which completes its own payments; this old completion is retired. */
	if ( class_exists( 'wp_easycart_order_pay' ) ) {
		die();
	}
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-complete-payment-invoice-' . $session_id ) ) {
		die();
	}

	// Get Payment Intent Info
	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}

	// 6.0.2: the order and PaymentIntent come from the binding the pay link made when it created the intent.
	$cartpage           = new ec_cartpage();
	$requested_order_id = ( isset( $_POST['invoice_id'] ) ) ? (int) $_POST['invoice_id'] : 0;
	$payment            = $cartpage->get_verified_stripe_invoice_payment( $stripe, $requested_order_id );
	if ( ! $payment ) {
		echo esc_url_raw( $cartpage->get_invoice_payment_failed_url( $requested_order_id ) );
		die();
	}
	$payment_intent = $payment->payment_intent;

	// Get data we want to keep. API versions from 2022-11-15 no longer list charges on the intent: read latest_charge.
	$charge = false;
	if ( isset( $payment_intent->charges->data[0] ) ) {
		$charge = $payment_intent->charges->data[0];
	} elseif ( ! empty( $payment_intent->latest_charge ) ) {
		$charge = ( is_object( $payment_intent->latest_charge ) ) ? $payment_intent->latest_charge : $stripe->get_charge( $payment_intent->latest_charge );
	}
	$card_type = '';
	$last_4    = '';
	$exp_month = '';
	$exp_year  = '';
	if ( $charge && isset( $charge->payment_method_details->card ) ) {
		$card_type = $charge->payment_method_details->card->brand;
		$last_4    = $charge->payment_method_details->card->last4;
		$exp_month = $charge->payment_method_details->card->exp_month;
		$exp_year  = $charge->payment_method_details->card->exp_year;
	} elseif ( $charge && isset( $charge->payment_method_details->type ) ) {
		$card_type = $charge->payment_method_details->type;
	}

	// Record the payment on the bound order.
	$goto_url = $cartpage->submit_stripe_invoice_payment( $payment_intent->id, $card_type, $last_4, $exp_month, $exp_year, $payment );

	echo esc_url_raw( $goto_url );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_complete_payment_subscription', 'ec_ajax_get_stripe_complete_payment_subscription' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_complete_payment_subscription', 'ec_ajax_get_stripe_complete_payment_subscription' );
function ec_ajax_get_stripe_complete_payment_subscription() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-complete-payment-subscription-' . $session_id ) ) {
		die();
	}

	// Get Payment Intent Info
	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}

	// Create the Order Dynamically
	$cartpage = new ec_cartpage();
	$goto_url = $cartpage->submit_stripe_quick_subscription_payment( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );

	echo esc_url_raw( $goto_url );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_stripe_create_subscription', 'ec_ajax_get_stripe_create_subscription' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_create_subscription', 'ec_ajax_get_stripe_create_subscription' );
function ec_ajax_get_stripe_create_subscription() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-create-subscription-' . $session_id ) ) {
		die();
	}

	/* 6.0.2: subscriptions are billed through Stripe only ( a page opened before Stripe was disconnected ). */
	if ( class_exists( 'wp_easycart_subscription_gateway' ) && ! wp_easycart_subscription_gateway::ready() ) {
		echo wp_json_encode( array( 'error' => array( 'code' => 'subscriptions_unavailable', 'message' => wp_easycart_subscription_gateway::shopper_text() ) ) ); /* the page script shows error.message */
		die();
	}

	/* 6.0.2 checkout protection: the gate before the card is attached and charged. */
	$wpec_gate = class_exists( 'wp_easycart_checkout_guard' ) ? wp_easycart_checkout_guard::check( 'subscription', array( 'gateway' => (string) get_option( 'ec_option_payment_process_method' ) ) ) : true;
	if ( is_wp_error( $wpec_gate ) ) {
		echo json_encode( array( 'error' => array( 'code' => $wpec_gate->get_error_code(), 'message' => esc_html( $wpec_gate->get_error_message() ) ) ) ); /* the page script shows error.message */
		die();
	}

	$cartpage = new ec_cartpage();
	$response = $cartpage->submit_stripe_quick_subscription( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id );//, $card_type, $last_4, $exp_month, $exp_year );
	if ( is_array( $response ) && isset( $response['error'] ) && 'payment_fail' === $response['error'] && class_exists( 'wp_easycart_checkout_guard' ) ) {
		wp_easycart_checkout_guard::record_decline( 'subscription', array( 'gateway' => (string) get_option( 'ec_option_payment_process_method' ), 'reason' => 'declined' ) );
	}
	if ( ! $response ) {
		echo json_encode( array( 'status' => 'error' ) );
	} else {
		echo json_encode( $response );
	}
	die();
}

if ( ! function_exists( 'wp_easycart_customer_subscription_allows' ) ) {
	/**
	 * Server-side check for account-page subscription changes: the row belongs to the signed-in
	 * customer and ec_subscription::customer_can() allows the action for its status and gateway.
	 *
	 * @since 6.0.0
	 * @param object|null $subscription_row ec_subscription row ( ec_db::get_subscription_row ).
	 * @param string      $action           update_payment|change_plan|cancel.
	 * @param int         $product_id       change_plan only: the selected product.
	 * @return bool
	 */
	function wp_easycart_customer_subscription_allows( $subscription_row, $action, $product_id = 0 ) {
		$user_id = ( isset( $GLOBALS['ec_cart_data']->cart_data->user_id ) ) ? (int) $GLOBALS['ec_cart_data']->cart_data->user_id : 0;
		if ( ! $subscription_row || $user_id <= 0 || (int) $subscription_row->user_id != $user_id ) {
			return false;
		}
		$subscription = new ec_subscription( $subscription_row, true );
		if ( ! method_exists( $subscription, 'customer_can' ) || ! $subscription->customer_can( $action ) ) {
			return false;
		}
		if ( 'change_plan' == $action && ! $subscription->is_allowed_plan( $product_id ) ) {
			return false;
		}
		return true;
	}
}

add_action( 'wp_ajax_ec_ajax_get_stripe_update_customer_card', 'ec_ajax_get_stripe_update_customer_card' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_stripe_update_customer_card', 'ec_ajax_get_stripe_update_customer_card' );
function ec_ajax_get_stripe_update_customer_card() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-update-customer-card-' . $session_id ) ) {
		die();
	}

	global $wpdb;
	$ec_db = new ec_db();

	$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
	if ( function_exists( 'icl_object_id' ) ) {
		$account_page_id = icl_object_id( $account_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$account_page = get_permalink( $account_page_id );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$account_page = $https_class->makeUrlHttps( $account_page );
	}
	if ( substr_count( $account_page, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}
	$payment_method = get_option( 'ec_option_payment_process_method' );
	if ( 'stripe' != $payment_method && 'stripe_connect' != $payment_method ) {
		echo json_encode( 
			array( 
				'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'], 'error' => 'stripe-setup' ) ) ),
			)
		);
		die();
	}

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}

	$subscription = $ec_db->get_subscription_row( (int) $_POST['subscription_id'] );
	/* 6.0.0: owner only, and not once the subscription is canceled / ended. */
	if ( ! wp_easycart_customer_subscription_allows( $subscription, 'update_payment' ) ) {
		echo json_encode( array( 'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => ( isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0 ), 'account_error' => 'subscription_update_failed' ) ) ) ) );
		die();
	}
	$subscription_info = $stripe->get_subscription( $GLOBALS['ec_user']->stripe_customer_id, $subscription->stripe_subscription_id );
	$subscription_item_id = false;
	$quantity = (int) $subscription->quantity;
	if ( $subscription_info ) {
		$subscription_item_id = ( isset( $subscription_info->items ) && isset( $subscription_info->items->data ) && count( $subscription_info->items->data ) > 0 ) ? $subscription_info->items->data[0]->id : false;
		$card_info = $stripe->attach_payment_method( sanitize_text_field( $_POST['payment_id'] ), $GLOBALS['ec_user'] );
		$update_response = $stripe->set_subscription_payment_method( sanitize_text_field( $_POST['payment_id'] ), $subscription_info, $subscription, $quantity );
		if ( $update_response && class_exists( 'wp_easycart_subscription_changes' ) ) {
			wp_easycart_subscription_changes::payment_method_changed( (int) $subscription->subscription_id, isset( $_POST['payment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_id'] ) ) : '' ); /* 6.0.3: a change waiting for renewal bills the new card */
		}
		if ( $update_response ) {
			$card = new ec_credit_card( $card_info->card->brand, ( ( isset( $card_info->card->billing_details ) && isset( $card_info->card->billing_details->name ) ) ? $card_info->card->billing_details->name : '' ), $card_info->card->last4, $card_info->card->exp_month, $card_info->card->exp_year, '' );
			$ec_db->update_user_default_card( $GLOBALS['ec_user'], $card );
		}
	}

	echo json_encode( 
		array( 
			'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'] ) ) ),
		)
	);
	die();
}

add_action( 'wp_ajax_ec_ajax_stripe_update_customer_subscription_plan', 'ec_ajax_stripe_update_customer_subscription_plan' );
add_action( 'wp_ajax_nopriv_ec_ajax_stripe_update_customer_subscription_plan', 'ec_ajax_stripe_update_customer_subscription_plan' );
function ec_ajax_stripe_update_customer_subscription_plan() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-stripe-update-customer-card-' . $session_id ) ) {
		die();
	}

	global $wpdb;
	$ec_db = new ec_db();

	$account_page_id = apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
	if ( function_exists( 'icl_object_id' ) ) {
		$account_page_id = icl_object_id( $account_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$account_page = get_permalink( $account_page_id );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$account_page = $https_class->makeUrlHttps( $account_page );
	}
	if ( substr_count( $account_page, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}
	$payment_method = get_option( 'ec_option_payment_process_method' );
	if ( 'stripe' != $payment_method && 'stripe_connect' != $payment_method ) {
		echo json_encode( 
			array( 
				'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $_POST['subscription_id'], 'error' => 'stripe-setup' ) ) ),
			)
		);
		die();
	}

	if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}

	$subscription = $ec_db->get_subscription_row( (int) $_POST['subscription_id'] );
	/* 6.0.0: owner only, only while the plan can change ( active / trial ), only to a plan the details page offers. */
	if ( ! wp_easycart_customer_subscription_allows( $subscription, 'change_plan', ( isset( $_POST['ec_selected_plan'] ) ? (int) $_POST['ec_selected_plan'] : 0 ) ) ) {
		echo json_encode( array( 'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => ( isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0 ), 'account_error' => 'subscription_update_failed' ) ) ) ) );
		die();
	}
	$quantity = ( isset( $_POST['quantity'] ) && (int) $_POST['quantity'] > 0 && ! get_option( 'ec_option_subscription_one_only' ) ) ? (int) $_POST['quantity'] : max( 1, (int) $subscription->quantity );

	/* 6.0.3: one way to change plan ( wp_easycart_change_subscription_plan(): the Stripe price checked before use, the subscription's
	 * own item, the model number kept, wp_easycart_subscription_plan_changed ), shared with the account form and WP EasyCart PRO.
	 * Through wp_easycart_subscription_changes, so a change the plan makes wait for renewal waits here too ( this Save comes from
	 * template copies before 6.0.3; the current panel previews and confirms through ec_ajax_subscription_change_* ). */
	if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
		$changed = wp_easycart_subscription_changes::customer_change( (int) $subscription->subscription_id, (int) $_POST['ec_selected_plan'], $quantity, $GLOBALS['ec_user'] ) ? true : new WP_Error( 'change', 'refused' );
	} else {
		$changed = wp_easycart_change_subscription_plan(
			(int) $subscription->subscription_id,
			(int) $_POST['ec_selected_plan'],
			$quantity,
			array(
				'source' => 'customer',
				'user'   => $GLOBALS['ec_user'],
			)
		);
	}
	$changed_args = array( 'subscription_id' => (int) $subscription->subscription_id );
	if ( is_wp_error( $changed ) ) {
		$changed_args['account_error'] = 'subscription_update_failed';
	}

	echo json_encode(
		array(
			'url' => esc_url_raw( wpeasycart_links()->get_account_page( 'subscription_details', $changed_args ) ),
		)
	);
	die();
}

function wpeasycart_get_cart_display_items( $cart, $order_totals, $tax ) {
	$displayItems = array(
		(object) array(
			"pending" 	=> (bool) 1,
			"label"		=> 'Subtotal',
			"amount"	=> (int) round( ( $order_totals->get_converted_sub_total() * 100 ), 2 )
		)
	);
	if ( $order_totals->tax_total > 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_tax' ),
			"amount" 	=> (int) round( ( $order_totals->tax_total * 100 ), 2 )
		);
	}
	if ( $order_totals->shipping_total > 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_shipping' ),
			"amount" 	=> (int) round( ( $order_totals->shipping_total * 100 ), 2 )
		);
	}
	if ( $order_totals->discount_total != 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_discounts' ),
			"amount" 	=> (int) round( ( $order_totals->discount_total * 100 ), 2 )
		);
	}
	if ( $tax->is_duty_enabled() ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_duty' ),
			"amount" 	=> (int) round( ( $order_totals->duty_total * 100 ), 2 )
		);
	}
	if ( $tax->is_vat_enabled() && $tax->vat_total > 0 ) { 
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_language()->get_text( 'cart_totals', 'cart_totals_vat' ),
			"amount" 	=> (int) round( ( $tax->vat_total * 100 ), 2 )
		);
	}
	if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->gst_total > 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_canada_tax_label( 'gst', $tax->shipping_state ) . ' (' . $tax->gst_rate . '%)',
			"amount" 	=> (int) round( ( $order_totals->gst_total * 100 ), 2 )
		);
	}
	if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->pst_total > 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_canada_tax_label( 'pst', $tax->shipping_state ) . ' (' . $tax->pst_rate . '%)',
			"amount" 	=> (int) round( ( $order_totals->pst_total * 100 ), 2 )
		);
	}
	if ( get_option( 'ec_option_enable_easy_canada_tax' ) && $order_totals->hst_total > 0 ) {
		$displayItems[] = (object) array(
			"pending" 	=> (bool) 1,
			"label" 	=> wp_easycart_canada_tax_label( 'hst', $tax->shipping_state ) . ' (' . $tax->hst_rate . '%)',
			"amount" 	=> (int) round( ( $order_totals->hst_total * 100 ), 2 )
		);
	}
	/* 6.0.2: flex fees are part of the total, so the Apple Pay / Google Pay sheet lists them too ( a card fee reads as its own line ). */
	foreach ( wp_easycart_wallet_fee_lines( $order_totals ) as $fee_line ) {
		$displayItems[] = (object) array(
			'pending' => true,
			'label'   => $fee_line['label'],
			'amount'  => (int) round( $fee_line['amount'] * 100 ),
		);
	}
	return $displayItems;
}

if ( ! function_exists( 'wp_easycart_wallet_fee_lines' ) ) {
	/**
	 * The flex fee lines in a set of totals, for an Apple Pay / Google Pay sheet ( label and amount ).
	 *
	 * @since 6.0.2
	 * @param ec_order_totals $order_totals Totals ( their tax holds the fees ).
	 * @return array Lines, each array( 'label' => string, 'amount' => float ).
	 */
	function wp_easycart_wallet_fee_lines( $order_totals ) {
		$lines = array();
		if ( ! is_object( $order_totals ) || ! isset( $order_totals->tax ) || ! is_object( $order_totals->tax ) || empty( $order_totals->tax->fees ) || ! is_array( $order_totals->tax->fees ) ) {
			return $lines;
		}
		foreach ( $order_totals->tax->fees as $fee ) {
			if ( ! isset( $fee->amount ) || 0.0 === round( (float) $fee->amount, 2 ) ) {
				continue;
			}
			$lines[] = array(
				'label'  => wp_strip_all_tags( wp_unslash( isset( $fee->label ) ? (string) $fee->label : '' ) ),
				'amount' => round( (float) $fee->amount, 2 ),
			);
		}
		return $lines;
	}
}

if ( ! function_exists( 'wp_easycart_wallet_pays_by_card' ) ) {
	/**
	 * Apple Pay, Google Pay and the other card wallets are card payments: from here to the end of this request the flex
	 * fee rules see a card ( ec_tax::use_fee_payment_type() ), whatever payment method the checkout page has selected, so
	 * the wallet's sheet, the charge and the order carry the Card fees. Called by every request an Apple Pay / Google Pay
	 * sheet makes. With $paying ( the wallet pays in this request ) the cart session records the card as well ( payment
	 * method credit_card, type card; the caller saves the session ), for the order's own hooks and a webhook that finishes it.
	 *
	 * @since 6.0.2
	 * @param bool $paying The wallet pays in this request.
	 */
	function wp_easycart_wallet_pays_by_card( $paying = false ) {
		ec_tax::use_fee_payment_type( 'card' );
		if ( $paying && isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) ) {
			$GLOBALS['ec_cart_data']->cart_data->payment_method = 'credit_card';
			$GLOBALS['ec_cart_data']->cart_data->payment_type   = 'card';
		}
	}
}

if ( ! function_exists( 'wp_easycart_wallet_page_cart_data' ) ) {
	/**
	 * The cart data ( ec_get_cart_data() ) for the checkout page behind an Apple Pay / Google Pay sheet: the page keeps the
	 * totals of the payment method it has selected ( the sheet's own totals are a card payment's, wp_easycart_wallet_pays_by_card() ).
	 *
	 * @since 6.0.2
	 * @return array
	 */
	function wp_easycart_wallet_page_cart_data() {
		$previous  = ec_tax::use_fee_payment_type( null );
		$cart_data = ec_get_cart_data();
		ec_tax::use_fee_payment_type( $previous );
		return $cart_data;
	}
}

add_action( 'wp_ajax_ec_ajax_redeem_coupon_code', 'ec_ajax_redeem_coupon_code' );
add_action( 'wp_ajax_nopriv_ec_ajax_redeem_coupon_code', 'ec_ajax_redeem_coupon_code' );
function ec_ajax_redeem_coupon_code() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-redeem-coupon-code-' . $session_id ) ) {
		die();
	}

	//UPDATE COUPON CODE
	$coupon_code = "";
	if ( isset( $_POST['couponcode'] ) ) {
		$coupon_code = trim( sanitize_text_field( $_POST['couponcode'] ) );
		$GLOBALS['ec_cart_data']->cart_data->coupon_code = preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $coupon_code ) );
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	/* 6.0.2 checkout protection: unknown codes are counted, so bots can't guess codes without limit. */
	$wpec_code_gate = ( '' !== $coupon_code && class_exists( 'wp_easycart_checkout_guard' ) ) ? wp_easycart_checkout_guard::code_check( 'coupon' ) : true;
	$coupon = is_wp_error( $wpec_code_gate ) ? false : $GLOBALS['ec_coupons']->redeem_coupon_code( $coupon_code );
	if ( ! $coupon && '' !== $coupon_code && ! is_wp_error( $wpec_code_gate ) && class_exists( 'wp_easycart_checkout_guard' ) ) {
		wp_easycart_checkout_guard::code_failed( 'coupon' );
	}
	$wpec_invalid_coupon = is_wp_error( $wpec_code_gate ) ? esc_html( $wpec_code_gate->get_error_message() ) : wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
	if ( isset( $_POST['ec_v3_24'] ) ) {
		$return_array = ec_get_cart_data();
		if ( $coupon ) {
			if ( $coupon && !$coupon->coupon_expired && ( $coupon->max_redemptions == 999 || $coupon->times_redeemed < $coupon->max_redemptions ) ) {
				$return_array['coupon_message'] = $coupon->message;
				$return_array['is_coupon_valid'] = true;
				$GLOBALS['ec_cart_data']->cart_data->coupon_code = preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $coupon_code ) );
				$cartpage = new ec_cartpage();
				if ( $cartpage->discount->coupon_matches <= 0 ) {
					$return_array['coupon_message'] = wp_easycart_language()->get_text( 'cart_coupons', 'coupon_not_applicable' );
					$return_array['is_coupon_valid'] = false;
					$GLOBALS['ec_cart_data']->cart_data->coupon_code = '';
				} else if ( $cartpage->discount->coupon_first_failed ) {
					$return_array['coupon_message'] = wp_easycart_language()->get_text( 'cart_coupons', 'coupon_first_only' );
					$return_array['is_coupon_valid'] = false;
					$GLOBALS['ec_cart_data']->cart_data->coupon_code = '';
				}

			} else if ( $coupon && $coupon->times_redeemed >= $coupon->max_redemptions ) {
				$return_array['coupon_message'] = wp_easycart_language()->get_text( 'cart_coupons', 'cart_max_exceeded_coupon' );
				$return_array['is_coupon_valid'] = false;
				$GLOBALS['ec_cart_data']->cart_data->coupon_code = '';

			} else if ( $coupon->coupon_expired ) {
				$return_array['coupon_message'] = wp_easycart_language()->get_text( 'cart_coupons', 'cart_coupon_expired' );
				$return_array['is_coupon_valid'] = false;
				$GLOBALS['ec_cart_data']->cart_data->coupon_code;

			} else {
				$return_array['coupon_message'] = wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
				$return_array['is_coupon_valid'] = false;
				$GLOBALS['ec_cart_data']->cart_data->coupon_code;
			}
		} else {
			$return_array['coupon_message'] = $wpec_invalid_coupon;
			$return_array['is_coupon_valid'] = false;
			$GLOBALS['ec_cart_data']->cart_data->coupon_code;
		}

		echo json_encode( $return_array );
	} else {
		// UPDATE COUPON CODE
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$order_totals = ec_get_order_totals( $cart );

		echo esc_attr( $cart->total_items ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->sub_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->tax_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( (-1) * $order_totals->discount_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->duty_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) );

		if ( $coupon ) {
			if ( $coupon && !$coupon->coupon_expired && ( $coupon->max_redemptions == 999 || $coupon->times_redeemed < $coupon->max_redemptions ) ) {
				$GLOBALS['ec_cart_data']->cart_data->coupon_code = preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $coupon_code ) );
				$cartpage = new ec_cartpage();
				if ( $cartpage->discount->coupon_matches <= 0 ) {
					echo '***' . wp_easycart_language()->get_text( 'cart_coupons', 'coupon_not_applicable' ) . '***' . "valid";
				} else {
					echo '***' . esc_attr( $coupon->message ) . '***' . "valid";
				}

			} else if ( $coupon && $coupon->times_redeemed >= $coupon->max_redemptions ) {
				echo '***' . wp_easycart_language()->get_text( 'cart_coupons', 'cart_max_exceeded_coupon' ) . '***' . "invalid";
				$GLOBALS['ec_cart_data']->cart_data->coupon_code = "";

			} else if ( $coupon->coupon_expired ) {
				echo '***' . wp_easycart_language()->get_text( 'cart_coupons', 'cart_coupon_expired' ) . '***' . "invalid";
				esc_attr( $GLOBALS['ec_cart_data']->cart_data->coupon_code );

			} else {
				echo '***' . wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' ) . '***' . "invalid";
				esc_attr( $GLOBALS['ec_cart_data']->cart_data->coupon_code );
			}
		} else {
			echo '***' . $wpec_invalid_coupon . '***' . "invalid";
			esc_attr( $GLOBALS['ec_cart_data']->cart_data->coupon_code );
		}

		if ( $order_totals->discount_total == 0 )
			echo "***0";
		else
			echo "***1";
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_redeem_subscription_coupon_code', 'ec_ajax_redeem_subscription_coupon_code' );
add_action( 'wp_ajax_nopriv_ec_ajax_redeem_subscription_coupon_code', 'ec_ajax_redeem_subscription_coupon_code' );
function ec_ajax_redeem_subscription_coupon_code() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-redeem-subscription-coupon-code-' . $session_id ) ) {
		die();
	}

	global $wpdb;

	// Get Coupon Code Info
	$product_id = "";
	$manufacturer_id = "";
	$coupon_code = "";
	if ( isset( $_POST['couponcode'] ) ) {
		$coupon_code = trim( sanitize_text_field( $_POST['couponcode'] ) );
	}
	if ( isset( $_POST['product_id'] ) ) {
		$product_id = (int) $_POST['product_id'];
	}
	if ( isset( $_POST['manufacturer_id'] ) ) {
		$manufacturer_id = (int) $_POST['manufacturer_id'];
	}

	// Get the Coupon and Check Validity
	$GLOBALS['ec_cart_data']->cart_data->coupon_code = preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $coupon_code ) );
	/* 6.0.2 checkout protection: unknown codes count here too, as they do in the cart's code box. */
	$wpec_code_gate = ( '' !== $coupon_code && class_exists( 'wp_easycart_checkout_guard' ) ) ? wp_easycart_checkout_guard::code_check( 'coupon' ) : true;
	$coupon = is_wp_error( $wpec_code_gate ) ? false : $GLOBALS['ec_coupons']->subscription_coupon( $coupon_code, $product_id ); // 6.0.2: Offers codes too
	if ( ! $coupon && '' !== $coupon_code && ! is_wp_error( $wpec_code_gate ) && class_exists( 'wp_easycart_checkout_guard' ) ) {
		wp_easycart_checkout_guard::code_failed( 'coupon' );
	}
	$coupon_code_invalid = true;
	$coupon_applicable = true;
	$coupon_exceeded_redemptions = false;
	$coupon_expired = false;

	if ( !$coupon ) { // Invalid Coupon
		$coupon_code_invalid = false;
	} else if ( $coupon->by_product_id && $coupon->product_id != $product_id ) { // Product does not match
		$coupon_applicable = false;
	} else if ( $coupon->by_manufacturer_id && $coupon->manufacturer_id != $manufacturer_id ) { // Manufacturer Does not Match
		$coupon_applicable = false;
	} else if ( $coupon->by_category_id && ! $wpdb->get_results( $wpdb->prepare( "SELECT categoryitem_id FROM ec_categoryitem WHERE category_id = %d AND product_id = %d", $coupon->category_id, $product_id ) ) ) { // Category does not match ( 6.0.2: a matching category coupon is checked for use and expiry below too )
		$coupon_applicable = false;
	} else if ( $coupon->max_redemptions != 999 && $coupon->times_redeemed >= $coupon->max_redemptions ) {
		$coupon_exceeded_redemptions = true;
	} else if ( $coupon->coupon_expired ) {
		$coupon_expired = true;
	} else if ( ! ( ( ! empty( $coupon->is_percentage_based ) && (float) $coupon->promo_percentage > 0 ) || ( ! empty( $coupon->is_dollar_based ) && (float) $coupon->promo_dollar > 0 ) ) ) {
		$coupon_applicable = false; // 6.0.2: a subscription takes only a percent or an amount off ( subscription_checkout_coupon() ), never shipping or a free item.
	}

	// If valid and applicable, set to cache ( 6.0.2: an unknown code no longer stays in the session ).
	$wpec_coupon_notice = null;
	if ( '' != $coupon_code && $coupon && $coupon_applicable && !$coupon_exceeded_redemptions && !$coupon_expired ) {
		$GLOBALS['ec_cart_data']->cart_data->coupon_code = preg_replace( "/[^A-Za-z0-9_\-\$\%]/", '', stripslashes_deep( $coupon_code ) );
	} else {
		$GLOBALS['ec_cart_data']->cart_data->coupon_code ='';
		/* 6.0.2: say why the code was refused. The totals below read the session, which no longer holds it, so every
		 * refusal used to read "Not a valid coupon code". */
		if ( '' != $coupon_code ) {
			if ( is_wp_error( $wpec_code_gate ) ) {
				$wpec_coupon_message = esc_html( $wpec_code_gate->get_error_message() );
			} else if ( ! $coupon ) {
				$wpec_coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_coupon' );
			} else if ( ! $coupon_applicable ) {
				$wpec_coupon_message = ( ! empty( $coupon->blocked ) && ! empty( $coupon->message ) ) ? $coupon->message : wp_easycart_language()->get_text( 'cart_coupons', 'cart_not_applicable_coupon' );
			} else if ( $coupon_exceeded_redemptions ) {
				$wpec_coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_max_exceeded_coupon' );
			} else {
				$wpec_coupon_message = wp_easycart_language()->get_text( 'cart_coupons', 'cart_coupon_expired' );
			}
			$wpec_coupon_notice = array(
				'message' => $wpec_coupon_message,
				'status'  => 'invalid',
			);
		}
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	wp_easycart_subscription_output_ajax_totals( $wpec_coupon_notice );
	die();
}

add_action( 'wp_ajax_ec_ajax_redeem_gift_card', 'ec_ajax_redeem_gift_card' );
add_action( 'wp_ajax_nopriv_ec_ajax_redeem_gift_card', 'ec_ajax_redeem_gift_card' );
function ec_ajax_redeem_gift_card() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-redeem-gift-card-' . $session_id ) ) {
		die();
	}

	// UPDATE GIFT CARD
	$gift_card = "";
	if ( isset( $_POST['giftcard'] ) )
		$gift_card = trim( sanitize_text_field( $_POST['giftcard'] ) );

	$GLOBALS['ec_cart_data']->cart_data->giftcard = $gift_card;

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	$db = new ec_db();
	/* 6.0.2 checkout protection: gift card numbers can be guessed like card numbers; wrong ones are counted. */
	$wpec_code_gate = ( '' !== $gift_card && class_exists( 'wp_easycart_checkout_guard' ) ) ? wp_easycart_checkout_guard::code_check( 'giftcard' ) : true;
	$giftcard = is_wp_error( $wpec_code_gate ) ? false : $db->redeem_gift_card( $gift_card );
	if ( ! $giftcard && '' !== $gift_card && ! is_wp_error( $wpec_code_gate ) && class_exists( 'wp_easycart_checkout_guard' ) ) {
		wp_easycart_checkout_guard::code_failed( 'giftcard' );
	}
	$wpec_invalid_giftcard = is_wp_error( $wpec_code_gate ) ? esc_html( $wpec_code_gate->get_error_message() ) : wp_easycart_language()->get_text( 'cart_coupons', 'cart_invalid_giftcard' );

	if ( isset( $_POST['ec_v3_24'] ) ) {
		$return_array = ec_get_cart_data();
		if ( $giftcard ) {
			$return_array['giftcard_message'] = $giftcard->message;
			$return_array['is_giftcard_valid'] = true;

		} else {
			$GLOBALS['ec_cart_data']->cart_data->giftcard = "";
			$return_array['giftcard_message'] = $wpec_invalid_giftcard;
			$return_array['is_giftcard_valid'] = false;
		}	
		echo json_encode( $return_array );
	} else {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$order_totals = ec_get_order_totals( $cart );
		echo esc_attr( $cart->total_items ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->sub_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->tax_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( (-1) * $order_totals->discount_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->duty_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ) ) . '***' . 
				esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) );

		if ( $giftcard )
			echo '***' . esc_attr( $giftcard->message ) . '***' . "valid";
		else {
			$GLOBALS['ec_cart_data']->cart_data->giftcard = "";
			echo '***' . $wpec_invalid_giftcard . '***' . "invalid";
		}

		if ( $order_totals->discount_total == 0 )
			echo "***0";
		else
			echo "***1";
	}

	die();

}

add_action( 'wp_ajax_ec_ajax_estimate_shipping', 'ec_ajax_estimate_shipping' );
add_action( 'wp_ajax_nopriv_ec_ajax_estimate_shipping', 'ec_ajax_estimate_shipping' );
function ec_ajax_estimate_shipping() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-estimate-shipping-' . $session_id ) ) {
		die();
	}

	//Get the variables from the AJAX call
	if ( isset( $_POST['zipcode'] ) ) {
		$GLOBALS['ec_cart_data']->cart_data->estimate_shipping_zip = sanitize_text_field( $_POST['zipcode'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_zip = sanitize_text_field( $_POST['zipcode'] );
	}

	if ( isset( $_POST['country'] ) && $_POST['country'] != "0" ) {
		$GLOBALS['ec_cart_data']->cart_data->estimate_shipping_country = sanitize_text_field( $_POST['country'] );
		$GLOBALS['ec_cart_data']->cart_data->shipping_country = sanitize_text_field( $_POST['country'] );
	}

	if ( isset( $_POST['zipcode'] ) && isset( $_POST['country'] ) && '0' != $_POST['country'] ) {
		$estimate_state = $GLOBALS['ec_countries']->get_state_from_zip( $_POST['country'], $_POST['zipcode'] );
		if ( $estimate_state ) {
			$GLOBALS['ec_cart_data']->cart_data->shipping_state = $estimate_state;
		}
	}

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	if ( isset( $_POST['ec_v3_24'] ) ) {
		$return_array = ec_get_cart_data();
		echo json_encode( $return_array );

	} else {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$order_totals = ec_get_order_totals( $cart );
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$shipping = new ec_shipping( $cart->subtotal, $cart->weight, $cart->store_shippable_items /* 6.0.2: the units the store ships */, 'RADIO', $GLOBALS['ec_user']->freeshipping );

		if ( $GLOBALS['ec_setting']->get_shipping_method() == "live" ) {
			echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) ) . '***';
			$shipping->print_shipping_options( 
				wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),
				wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ),
				'RADIO'
			);
			echo '***' . esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ) );
		} else {
			echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ) ) . '***' . esc_attr( $GLOBALS['currency']->get_currency_display( $order_totals->grand_total ) ) . '***';
			$shipping->print_shipping_options( 
				wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),
				wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ),
				'RADIO'
			);
		}
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_update_subscription_shipping_method', 'ec_ajax_update_subscription_shipping_method' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_subscription_shipping_method', 'ec_ajax_update_subscription_shipping_method' );
function ec_ajax_update_subscription_shipping_method() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
	$shipping_method = ( isset( $_POST['shipping_method'] ) ) ? sanitize_text_field( $_POST['shipping_method'] ) : '';

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-shipping-method-' . $session_id . '-' . $shipping_method ) ) {
		die();
	}

	$cartpage = new ec_cartpage();

	//Get the variables from the AJAX call
	$ship_express = (int) sanitize_text_field( $_POST['ship_express'] );

	//Create a new db and submit review
	$GLOBALS['ec_cart_data']->cart_data->shipping_method = ( $cartpage->shipping->is_valid_shipping_method( $shipping_method ) ) ? $shipping_method : '';
	$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = $ship_express;

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );

	wp_easycart_subscription_output_ajax_totals();
	die();
}

add_action( 'wp_ajax_ec_ajax_update_shipping_method', 'ec_ajax_update_shipping_method' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_shipping_method', 'ec_ajax_update_shipping_method' );
function ec_ajax_update_shipping_method() {
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;
	if ( ! isset( $_POST['shipping_method'] ) ) {
		die();
	}

	$shipping_method = sanitize_text_field( $_POST['shipping_method'] );
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-shipping-method-' . $session_id . '-' . esc_attr( $shipping_method ) ) ) {
		die();
	}

	$cartpage = new ec_cartpage();
	$ship_express = (int) sanitize_text_field( $_POST['ship_express'] );
	$GLOBALS['ec_cart_data']->cart_data->shipping_method = ( $cartpage->shipping->is_valid_shipping_method( $shipping_method ) ) ? $shipping_method : '';
	$GLOBALS['ec_cart_data']->cart_data->expedited_shipping = $ship_express;

	$GLOBALS['ec_cart_data']->save_session_to_db();
	wp_cache_flush();
	do_action( 'wpeasycart_cart_updated' );
	$return_array = ec_get_cart_data();
	echo json_encode( $return_array );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_payment_method', 'ec_ajax_update_payment_method' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_payment_method', 'ec_ajax_update_payment_method' );
function ec_ajax_update_payment_method() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-payment-method-' . $session_id ) ) {
		die();
	}

	$payment_method = sanitize_text_field( $_POST['payment_method'] );
	$GLOBALS['ec_cart_data']->cart_data->payment_method = $payment_method;
	$GLOBALS['ec_cart_data']->save_session_to_db();

	$cartpage = new ec_cartpage();
	$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	$order_totals = ec_get_order_totals( $cart );
	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
		if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );
	}
	$displayItems = wpeasycart_get_cart_display_items( $cart, $order_totals, $order_totals->tax );
	$return_cart_data = ec_get_cart_data();
	$result = (object) array(
		'shipping_rates' 	=> $cartpage->get_stripe_express_shipping_items( wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_standard' ),wp_easycart_language()->get_text( 'cart_estimate_shipping', 'cart_estimate_shipping_express' ) ),
		'line_items'		=> $cartpage->get_stripe_express_cart_items(),
		'total'				=> (int) round( ( $order_totals->grand_total * 100 ), 2 ),
		'cart_data'			=> $return_cart_data
	);
	echo json_encode( $result );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_payment_type', 'ec_ajax_update_payment_type' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_payment_type', 'ec_ajax_update_payment_type' );
function ec_ajax_update_payment_type() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-payment-type-' . $session_id ) ) {
		die();
	}

	//Get the variables from the AJAX call
	$payment_type = sanitize_text_field( $_POST['payment_type'] );
	$GLOBALS['ec_cart_data']->cart_data->payment_type = $payment_type;
	$GLOBALS['ec_cart_data']->save_session_to_db();

	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) || 'stripe_connect' == get_option( 'ec_option_payment_process_method' ) ) {
		if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
			$stripe = new ec_stripe();
		} else {
			$stripe = new ec_stripe_connect();
		}
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$order_totals = ec_get_order_totals( $cart );
		$stripe->update_payment_intent_total( $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id, $order_totals );
	}

	$return_array = ec_get_cart_data();
	echo json_encode( $return_array );
	die();
}

add_action( 'wp_ajax_ec_ajax_update_payment_complete', 'ec_ajax_update_payment_complete' );
add_action( 'wp_ajax_nopriv_ec_ajax_update_payment_complete', 'ec_ajax_update_payment_complete' );
function ec_ajax_update_payment_complete() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-payment-complete-' . $session_id ) ) {
		die();
	}

	$cartpage = new ec_cartpage();
	do_action( 'wp_easycart_stripe_payment_method_complete', $cartpage->cart, $cartpage->order_totals );
	die();
}

add_action( 'wp_ajax_ec_ajax_insert_customer_review', 'ec_ajax_insert_customer_review' );
add_action( 'wp_ajax_nopriv_ec_ajax_insert_customer_review', 'ec_ajax_insert_customer_review' );
function ec_ajax_insert_customer_review() {
	wpeasycart_session()->handle_session();
	$product_id = (int) $_POST['product_id'];
	
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-insert-customer-review-' . $product_id ) ) {
		die();
	}

	//Get the variables from the AJAX call
	$rating = isset( $_POST['review_score'] ) ? (int) $_POST['review_score'] : 0;
	$title = isset( $_POST['review_title'] ) ? sanitize_text_field( wp_unslash( $_POST['review_title'] ) ) : '';
	$description = isset( $_POST['review_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_message'] ) ) : '';

	/* 6.0.2: the form's own rules, on the server too: 1 to 5 stars, a title and a message, and a signed-in customer when the
	 * store asks reviewers to sign in. */
	$signed_in = ( isset( $GLOBALS['ec_user']->user_id ) && (int) $GLOBALS['ec_user']->user_id > 0 );
	if ( $rating < 1 || $rating > 5 || '' === trim( $title ) || '' === trim( $description ) || ( get_option( 'ec_option_customer_review_require_login' ) && ! $signed_in ) ) {
		echo '0';
		die();
	}

	/* 6.0.2: the answer is exactly 1 or 0 ( the form reads nothing else ): output from the review hooks or a notice would
	 * otherwise make a saved review read as failed, and a second click save it twice. */
	$wpec_ob_level = ob_get_level();
	ob_start();
	$db = new ec_db();
	$saved = $db->submit_customer_review( $product_id, $rating, $title, $description, $GLOBALS['ec_user']->user_id );
	while ( ob_get_level() > $wpec_ob_level ) {
		ob_end_clean();
	}
	echo ( $saved ) ? '1' : '0';

	die();

}

add_action( 'wp_ajax_ec_ajax_live_search', 'ec_ajax_live_search' );
add_action( 'wp_ajax_nopriv_ec_ajax_live_search', 'ec_ajax_live_search' );
function ec_ajax_live_search() {

	/* 6.0.2: public and read-only ( the product, manufacturer and category names shoppers already see ), so it no longer
	 * asks for the nonce printed into the page: a search box on a page served from a cache after that nonce expired got an
	 * empty answer and never suggested anything ( review A13 ). The search forms still send it; it is ignored. */
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, read-only suggestions ( see above ).
	$search_val = isset( $_POST['search_val'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['search_val'] ) ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( '' === $search_val || strlen( $search_val ) > 100 ) {
		echo json_encode( array() );
		die();
	}

	/* 6.0.2: Settings › Products › Who can view the store covers suggestions too: a visitor it keeps out gets none. */
	if ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() ) {
		echo wp_json_encode( array() );
		die();
	}

	global $wpdb;
	//Create a new db and submit review
	$db = new ec_db();
	/* 6.0.2: a % or _ the shopper types is a letter, not a LIKE wildcard ( every lookup below builds '%term%' ). */
	$results = $db->get_live_search_options( $wpdb->esc_like( $search_val ) );

	// 6.0.2: products kept for other customer roles ( role_id ) are left out by the query itself ( get_live_search_options() ).
	echo json_encode( $results );

	die();

}

add_action( 'wp_ajax_ec_ajax_close_newsletter', 'ec_ajax_close_newsletter' );
add_action( 'wp_ajax_nopriv_ec_ajax_close_newsletter', 'ec_ajax_close_newsletter' );
function ec_ajax_close_newsletter() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-close-newsletter' ) ) {
		die();
	}

	setcookie( 'ec_newsletter_popup', 'hide', time() + ( 10 * 365 * 24 * 60 * 60 ), defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );

	die();

}

add_action( 'wp_ajax_ec_ajax_submit_newsletter_signup', 'ec_ajax_submit_newsletter_signup' );
add_action( 'wp_ajax_nopriv_ec_ajax_submit_newsletter_signup', 'ec_ajax_submit_newsletter_signup' );
function ec_ajax_submit_newsletter_signup() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-submit-newsletter' ) ) {
		die();
	}

	$newsletter_name = "";
	if ( isset( $_POST['newsletter_name'] ) ) {
		$newsletter_name = sanitize_text_field( $_POST['newsletter_name'] );
	}

	if ( filter_var( $_POST['email_address'], FILTER_VALIDATE_EMAIL ) ) {
		$ec_db = new ec_db();
		$ec_db->insert_subscriber( sanitize_email( $_POST['email_address'] ), $newsletter_name, "" );

		// MyMail Hook
		if ( function_exists( 'mailster' ) ) {
			$subscriber_id = mailster('subscribers')->add(array(
				'email' => sanitize_email( $_POST['email_address'] ),
				'name' => $newsletter_name,
				'status' => 1,
			), false );
		}

		do_action( 'wpeasycart_subscriber_added', sanitize_email( $_POST['email_address'] ), sanitize_text_field( $_POST['newsletter_name'] ) );
	}
	setcookie( 'ec_newsletter_popup', 'hide', time() + ( 10 * 365 * 24 * 60 * 60 ), defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );

	die();

}

add_action( 'wp_ajax_ec_ajax_create_stripe_ideal_order', 'ec_ajax_create_stripe_ideal_order' );
add_action( 'wp_ajax_nopriv_ec_ajax_create_stripe_ideal_order', 'ec_ajax_create_stripe_ideal_order' );
function ec_ajax_create_stripe_ideal_order() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-create-stripe-ideal-order-' . $session_id ) ) {
		die();
	}

	/* 6.0.2: only for this checkout's own PaymentIntent, recorded with the id and secret Stripe returns ( never the posted ones ). */
	$payment_intent_id = (string) $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id;
	$posted_id = '';
	if ( isset( $_POST['source'] ) && is_array( $_POST['source'] ) && isset( $_POST['source']['id'] ) && is_string( $_POST['source']['id'] ) ) {
		$posted_id = sanitize_text_field( wp_unslash( $_POST['source']['id'] ) );
	} else if ( isset( $_POST['source'] ) && is_string( $_POST['source'] ) ) {
		$posted_id = sanitize_text_field( wp_unslash( $_POST['source'] ) );
	}
	if ( '' === $payment_intent_id || ! hash_equals( $payment_intent_id, $posted_id ) ) {
		die();
	}
	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$payment_intent = $stripe->get_payment_intent( $payment_intent_id );
	if ( ! $payment_intent || empty( $payment_intent->id ) || empty( $payment_intent->client_secret ) ) {
		die();
	}
	global $wpdb;
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s', $payment_intent->id . ':' . $payment_intent->client_secret ) ) ) {
		die();
	}
	$source = array(
		'id' => $payment_intent->id,
		'client_secret' => $payment_intent->client_secret,
	);
	$cartpage = new ec_cartpage();
	$order_id = $cartpage->insert_ideal_order( $source );
	die();
}

add_action( 'wp_ajax_ec_ajax_stripe_check_order_status', 'ec_ajax_stripe_check_order_status' );
add_action( 'wp_ajax_nopriv_ec_ajax_stripe_check_order_status', 'ec_ajax_stripe_check_order_status' );
function ec_ajax_stripe_check_order_status() {
	global $wpdb;

	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-create-stripe-ideal-order-' . $session_id ) ) {
		die();
	}

	if ( ! isset( $_POST['source'] ) ) {
		die();
	}

	$cart_page_id = get_option( 'ec_option_cartpage' );
	if ( function_exists( 'icl_object_id' ) ) {
		$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$cart_page = get_permalink( $cart_page_id );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$cart_page = $https_class->makeUrlHttps( $cart_page );
	}
	if ( substr_count( $cart_page, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}

	/* 6.0.2: only this checkout's own PaymentIntent. */
	$payment_intent_id = (string) $GLOBALS['ec_cart_data']->cart_data->stripe_paymentintent_id;
	$posted_id = is_string( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
	if ( '' === $payment_intent_id || ! hash_equals( $payment_intent_id, $posted_id ) ) {
		echo json_encode( (object) array( 'status' => '', 'redirect' => esc_url_raw( $cart_page ) ) );
		die();
	}

	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	$stripe_pi_response = $stripe->get_payment_intent( $payment_intent_id );

	if ( ! $stripe_pi_response ) {
		$response_obj = (object) array(
			'status' => '',
			'redirect' => esc_url_raw( $cart_page ),
		);
		echo json_encode( $response_obj );
		die();
	}

	if ( 'requires_confirmation' == $stripe_pi_response->status ) {
		$response_obj = (object) array(
			'status' => $stripe_pi_response->status,
			'redirect' => '',
		);
		echo json_encode( $response_obj );
		die();
	}

	$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $stripe_pi_response->id . ':' . $stripe_pi_response->client_secret ) );
	if ( $order ) {
		$order_id = $order->order_id;
		$response_obj = (object) array(
			'status' => $stripe_pi_response->status,
			'redirect' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ),
		);
		echo json_encode( $response_obj );
		die();
	}

	if ( in_array( $stripe_pi_response->status, array( 'succeeded', 'processing', 'requires_capture', 'canceled' ), true ) ) { /* 6.0.2: never for an intent still waiting to be paid */
		$cartpage = new ec_cartpage();
		$source = array(
			'id' => $stripe_pi_response->id,
			'client_secret' => $stripe_pi_response->client_secret,
		);
		$order_id = $cartpage->insert_ideal_order( $source );
		$response_obj = (object) array(
			'status' => '',
			'redirect' => esc_url_raw( wpeasycart_links()->get_cart_page( 'checkout_success', array( 'order_id' => (int) $order_id ) ) ),
		);
		echo json_encode( $response_obj );
		die();
	}
	
	$response_obj = (object) array(
		'status' => $stripe_pi_response->status,
		'redirect' => esc_url_raw( $cart_page ),
	);
	echo json_encode( $response_obj );
	die();
}

add_action( 'wp_ajax_ec_ajax_subscribe_to_stock_notification', 'ec_ajax_subscribe_to_stock_notification' );
add_action( 'wp_ajax_nopriv_ec_ajax_subscribe_to_stock_notification', 'ec_ajax_subscribe_to_stock_notification' );
function ec_ajax_subscribe_to_stock_notification() {
	$product_id = (int) $_POST['product_id'];
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-subscribe-to-stock-notification-' . $product_id ) ) {
		die();
	}

	$email = sanitize_email( $_POST['email'] );
	$cartpage = new ec_cartpage();

	$recaptcha_valid = true;
	if ( wp_easycart_recaptcha_ready() ) { // 6.0.2: only once both keys are saved, as the form shows it
		if ( !isset( $_POST['recaptcha_response'] ) || $_POST['recaptcha_response'] == '' ) {
			die();
		}

		$db = new ec_db_admin();
		$recaptcha_response = sanitize_text_field( $_POST['recaptcha_response'] );

		$data = array(
			"secret"	=> get_option( 'ec_option_recaptcha_secret_key' ),
			"response"	=> $recaptcha_response
		);

		$request = new WP_Http;
		$response = $request->request( 
			"https://www.google.com/recaptcha/api/siteverify", 
			array( 
				'method' => 'POST', 
				'body' => http_build_query( $data ),
				'timeout' => 30
			)
		);
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$db->insert_response( 0, 1, "GOOGLE RECAPTCHA CURL ERROR", $error_message );
			$response = (object) array( "error" => $error_message );
		} else {
			$response = json_decode( $response['body'] );
			$db->insert_response( 0, 0, "Google Recaptcha Response", print_r( $response, true ) );
		}

		$recaptcha_valid = ( isset( $response->success ) && $response->success ) ? true : false;
	}

	if ( $recaptcha_valid ) {
		global $wpdb;

		$found = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_product_subscriber WHERE email = %s AND product_id = %d", $email, $product_id ) );
		if ( !$found ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO ec_product_subscriber( email, product_id ) VALUES( %s, %d )", $email, $product_id ) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_product_subscriber SET status = 'subscribed' WHERE email = %s AND product_id = %d", $email, $product_id ) );
		}

	}

	die();
}

add_action( 'wp_ajax_ec_ajax_check_stripe_3ds_order', 'ec_ajax_check_stripe_3ds_order' );
add_action( 'wp_ajax_nopriv_ec_ajax_check_stripe_3ds_order', 'ec_ajax_check_stripe_3ds_order' );
function ec_ajax_check_stripe_3ds_order() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-check-stripe-3ds-order-' . $session_id ) ) {
		die();
	}

	if ( ! isset( $_POST['source'] ) ) {
		die();
	}

	if ( ! isset( $_POST['client_secret'] ) ) {
		die();
	}
	
	if ( 'stripe' == get_option( 'ec_option_payment_process_method' ) ) {
		$stripe = new ec_stripe();
	} else {
		$stripe = new ec_stripe_connect();
	}
	/* 6.0.2: only for the page holding the intent's client secret, and only its status. */
	$result = $stripe->get_payment_intent( is_string( $_POST['source'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', $_POST['source'] ) : '' );
	$client_secret = is_string( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
	if ( ! $result || empty( $result->client_secret ) || '' === $client_secret || ! hash_equals( (string) $result->client_secret, $client_secret ) ) {
		$response = array(
			'status'  => 'failed'
		);
		echo json_encode( $response );
	} else {
		echo json_encode( array( 'status' => $result->status ) );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_check_stripe_ideal_order', 'ec_ajax_check_stripe_ideal_order' );
add_action( 'wp_ajax_nopriv_ec_ajax_check_stripe_ideal_order', 'ec_ajax_check_stripe_ideal_order' );
function ec_ajax_check_stripe_ideal_order() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-check-stripe-ideal-order-' . $session_id ) ) {
		die();
	}

	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.order_id FROM ec_order, ec_orderstatus WHERE ec_order.gateway_transaction_id = %s AND ec_order.orderstatus_id = ec_orderstatus.status_id AND is_approved = 1", sanitize_text_field( $_POST['source'] . ':' . $_POST['client_secret'] ) ) );
	$failed_order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.order_id FROM ec_order WHERE ec_order.gateway_transaction_id = %s", sanitize_text_field( $_POST['source'] . ':' . $_POST['client_secret'] ) ) );
	if ( $order ) {
		// Clear tempcart
		$ec_db_admin = new ec_db_admin();
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$GLOBALS['ec_cart_data']->checkout_session_complete();
		$GLOBALS['ec_cart_data']->save_session_to_db();
		echo esc_attr( $order->order_id );

	} else if ( !$failed_order ) {
		echo 'failed';

	} else {
		echo '0';
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_check_stripe_ideal_order_skip', 'ec_ajax_check_stripe_ideal_order_skip' );
add_action( 'wp_ajax_nopriv_ec_ajax_check_stripe_ideal_order_skip', 'ec_ajax_check_stripe_ideal_order_skip' );
function ec_ajax_check_stripe_ideal_order_skip() {
	wpeasycart_session()->handle_session();
	$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-check-stripe-ideal-order-skip-' . $session_id ) ) {
		die();
	}

	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.order_id, ec_orderstatus.is_approved FROM ec_order, ec_orderstatus WHERE ec_order.gateway_transaction_id = %s AND ec_order.orderstatus_id = ec_orderstatus.status_id", sanitize_text_field( $_POST['source'] . ':' . $_POST['client_secret'] ) ) );
	if ( $order ) {
		// Clear tempcart
		$ec_db_admin = new ec_db_admin();
		$ec_db_admin->clear_tempcart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$GLOBALS['ec_cart_data']->checkout_session_complete();
		$GLOBALS['ec_cart_data']->save_session_to_db();
		$response = array(
			'order_id'  => $order->order_id,
			'is_approved' => $order->is_approved,
			'status'   => 'skip'
		);
		echo json_encode( $response );

	} else {
		$response = array(
			'status'  => 'failed'
		);
		echo json_encode( $response );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_save_page_options', 'ec_ajax_save_page_options' );
function ec_ajax_save_page_options() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-page-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		update_option( 'ec_option_design_saved', 1 );
		$db = new ec_db();
		$post_id = (int) $_POST['post_id'];

		$ec_validate_toggle = function( $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );
			return in_array( $value, array( '0', '1' ), true ) ? $value : null;
		};
		$ec_validate_columns = function( $value ) {
			$value = (int) $value;
			return ( $value >= 1 && $value <= 12 ) ? (string) $value : null;
		};
		$ec_validate_px_height = function( $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );
			return preg_match( '/^[0-9]{1,4}px$/', $value ) ? $value : null;
		};
		$ec_validate_product_type = function( $value ) {
			$value = (int) $value;
			return ( $value >= 1 && $value <= 99 ) ? (string) $value : null;
		};

		$allowed_page_options = array(
			'product_type'             => $ec_validate_product_type,
			'use_quickview'            => $ec_validate_toggle,
			'dynamic_image_sizing'     => $ec_validate_toggle,
			'columns_smartphone'       => $ec_validate_columns,
			'image_height_smartphone'  => $ec_validate_px_height,
			'columns_tablet'           => $ec_validate_columns,
			'image_height_tablet'      => $ec_validate_px_height,
			'columns_tablet_wide'      => $ec_validate_columns,
			'image_height_tablet_wide' => $ec_validate_px_height,
			'columns_laptop'           => $ec_validate_columns,
			'image_height_laptop'      => $ec_validate_px_height,
			'columns_desktop'          => $ec_validate_columns,
			'image_height_desktop'     => $ec_validate_px_height,
		);

		foreach ( $allowed_page_options as $key => $validator ) {
			if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
				continue;
			}
			$value = call_user_func( $validator, $_POST[ $key ] );
			if ( null !== $value ) {
				$db->update_page_option( $post_id, $key, $value );
			}
		}

		if ( isset( $_POST['ec_option_details_main_color'] ) ) {
			update_option( 'ec_option_details_main_color', preg_replace( '/[^\#0-9A-Z]/', '', strtoupper( sanitize_text_field( $_POST['ec_option_details_main_color'] ) ) ) );
		}
		if ( isset( $_POST['ec_option_details_second_color'] ) ) {
			update_option( 'ec_option_details_second_color', preg_replace( '/[^\#0-9A-Z]/', '', strtoupper( sanitize_text_field( $_POST['ec_option_details_second_color'] ) ) ) );
		}

		do_action( 'wpeasycart_page_options_updated' );
	}	
	die();

}

add_action( 'wp_ajax_ec_ajax_save_page_default_options', 'ec_ajax_save_page_default_options' );
function ec_ajax_save_page_default_options() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-page-default-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$ec_validate_color = function( $value ) {
			$value = preg_replace( '/[^\#0-9A-F]/', '', strtoupper( sanitize_text_field( wp_unslash( $value ) ) ) );
			return preg_match( '/^\#([0-9A-F]{3}|[0-9A-F]{6}|[0-9A-F]{8})$/', $value ) ? $value : null;
		};
		$ec_validate_toggle = function( $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );
			return in_array( $value, array( '0', '1' ), true ) ? (int) $value : null;
		};
		$ec_validate_columns = function( $value ) {
			$value = (int) $value;
			return ( $value >= 1 && $value <= 12 ) ? $value : null;
		};
		$ec_validate_px_height = function( $value ) {
			$value = sanitize_text_field( wp_unslash( $value ) );
			return preg_match( '/^[0-9]{1,4}px$/', $value ) ? $value : null;
		};
		$ec_validate_product_type = function( $value ) {
			$value = sanitize_key( wp_unslash( $value ) );
			return ( '' !== $value ) ? $value : null;
		};

		$allowed_default_options = array(
			'ec_option_details_main_color'               => $ec_validate_color,
			'ec_option_details_second_color'             => $ec_validate_color,
			'ec_option_default_dynamic_sizing'           => $ec_validate_toggle,
			'ec_option_default_quick_view'               => $ec_validate_toggle,
			'ec_option_default_product_type'             => $ec_validate_product_type,
			'ec_option_default_desktop_columns'          => $ec_validate_columns,
			'ec_option_default_desktop_image_height'     => $ec_validate_px_height,
			'ec_option_default_laptop_columns'           => $ec_validate_columns,
			'ec_option_default_laptop_image_height'      => $ec_validate_px_height,
			'ec_option_default_tablet_wide_columns'      => $ec_validate_columns,
			'ec_option_default_tablet_wide_image_height' => $ec_validate_px_height,
			'ec_option_default_tablet_columns'           => $ec_validate_columns,
			'ec_option_default_tablet_image_height'      => $ec_validate_px_height,
			'ec_option_default_smartphone_columns'       => $ec_validate_columns,
			'ec_option_default_smartphone_image_height'  => $ec_validate_px_height,
		);

		update_option( 'ec_option_design_saved', 1 );

		foreach ( $allowed_default_options as $key => $validator ) {
			if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
				continue;
			}
			$value = call_user_func( $validator, $_POST[ $key ] );
			if ( null !== $value ) {
				update_option( $key, $value );
			}
		}

		do_action( 'wpeasycart_page_options_updated' );
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_save_product_options', 'ec_ajax_save_product_options' );
function ec_ajax_save_product_options() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-product-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$model_number = sanitize_text_field( $_POST['model_number'] );

		$product_options = new stdClass();
		$product_options->image_hover_type = (int) $_POST['image_hover_type'];
		$product_options->image_effect_type = preg_replace( '/[^0-9a-z]/', '', sanitize_text_field( $_POST['image_effect_type'] ) );
		$product_options->tag_type = (int) $_POST['tag_type'];
		$product_options->tag_text = sanitize_text_field( $_POST['tag_text'] );
		$product_options->tag_bg_color = preg_replace( '/[^0-9A-Z\#]/', '', strtoupper( sanitize_text_field( $_POST['tag_bg_color'] ) ) );
		$product_options->tag_text_color = preg_replace( '/[^0-9A-Z\#]/', '', strtoupper( sanitize_text_field( $_POST['tag_text_color'] ) ) );

		$db = new ec_db();
		$db->update_product_options( $model_number, $product_options );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_mass_save_product_options', 'ec_ajax_mass_save_product_options' );
function ec_ajax_mass_save_product_options() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-mass-save-product-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$product_list = (array) $_POST['products']; // XSS OK. Forced array and each item sanitized.

		$product_options = new stdClass();
		$product_options->image_hover_type = (int) $_POST['image_hover_type'];
		$product_options->image_effect_type = preg_replace( '/[^0-9a-z]/', '', sanitize_text_field( $_POST['image_effect_type'] ) );

		$db = new ec_db();
		foreach ( $product_list as $model_number ) {
			$db->update_product_options( sanitize_text_field( $model_number ), $product_options );
		}
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_save_product_order', 'ec_ajax_save_product_order' );
function ec_ajax_save_product_order() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-product-order' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$post_id = (int) $_POST['post_id'];
		$products_sanitized = array();
		$products = json_decode( wp_unslash( $_POST['product_order'] ) );// XSS OK. Each Item Sanitized and Validated.
		if ( is_array( $products ) ) {
			foreach ( $products as $model_number ) {
				$products_sanitized[] = preg_replace( '/[^A-Za-z0-9\-\_]/', '', sanitize_text_field( $model_number ) );
			}
		}
		$db = new ec_db();
		$db->update_page_option( $post_id, 'product_order', json_encode( $products_sanitized ) );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_ec_update_product_description', 'ec_ajax_ec_update_product_description' );
function ec_ajax_ec_update_product_description() {
	$product_id = (int) $_POST['product_id'];
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-product-description-' . $product_id ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$description = wp_easycart_language()->convert_text( $_POST['description'] ); // XSS OK, Handled within conversion function.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET ec_product.description = %s WHERE ec_product.product_id = %d", $description, $product_id ) );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_ec_update_product_specifications', 'ec_ajax_ec_update_product_specifications' );
function ec_ajax_ec_update_product_specifications() {
	$product_id = (int) $_POST['product_id'];
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-update-product-specifications-' . $product_id ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		$specifications = wp_easycart_language()->convert_text( $_POST['specifications'] ); // XSS OK, Handled within conversion function.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE ec_product SET ec_product.specifications = %s WHERE ec_product.product_id = %d", $specifications, $product_id ) );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_save_product_details_options', 'ec_ajax_save_product_details_options' );
function ec_ajax_save_product_details_options() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-product-details-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		update_option( 'ec_option_details_main_color', preg_replace( '/[^\#0-9A-Z]/', '', strtoupper( sanitize_text_field( $_POST['ec_option_details_main_color'] ) ) ) );
		update_option( 'ec_option_details_second_color', preg_replace( '/[^\#0-9A-Z]/', '', strtoupper( sanitize_text_field( $_POST['ec_option_details_second_color'] ) ) ) );
		update_option( 'ec_option_details_columns_desktop', (int) $_POST['ec_option_details_columns_desktop'] );
		update_option( 'ec_option_details_columns_laptop', (int) $_POST['ec_option_details_columns_laptop'] );
		update_option( 'ec_option_details_columns_tablet_wide', (int) $_POST['ec_option_details_columns_tablet_wide'] );
		update_option( 'ec_option_details_columns_tablet', (int) $_POST['ec_option_details_columns_tablet'] );
		update_option( 'ec_option_details_columns_smartphone', (int) $_POST['ec_option_details_columns_smartphone'] );
		update_option( 'ec_option_use_dark_bg', (int) $_POST['ec_option_use_dark_bg'] );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_save_cart_options', 'ec_ajax_save_cart_options' );
function ec_ajax_save_cart_options() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-save-cart-options' ) ) {
		die();
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
		update_option( 'ec_option_cart_columns_desktop', (int) $_POST['ec_option_cart_columns_desktop'] );
		update_option( 'ec_option_cart_columns_laptop', (int) $_POST['ec_option_cart_columns_laptop'] );
		update_option( 'ec_option_cart_columns_tablet_wide', (int) $_POST['ec_option_cart_columns_tablet_wide'] );
		update_option( 'ec_option_cart_columns_tablet', (int) $_POST['ec_option_cart_columns_tablet'] );
		update_option( 'ec_option_cart_columns_smartphone', (int) $_POST['ec_option_cart_columns_smartphone'] );
		update_option( 'ec_option_use_dark_bg', (int) $_POST['ec_option_use_dark_bg'] );
		do_action( 'wpeasycart_page_options_updated' );
	}
	die();

}

add_action( 'wp_ajax_ec_ajax_get_dynamic_cart_menu', 'ec_ajax_get_dynamic_cart_menu' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_dynamic_cart_menu', 'ec_ajax_get_dynamic_cart_menu' );
function ec_ajax_get_dynamic_cart_menu() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-mini-cart' ) ) {
		die();
	}
	$is_cart_created = wpeasycart_session()->handle_session( false, false );
	if ( isset( $_POST['language'] ) ) {
		wp_easycart_language()->set_language( sanitize_text_field( $_POST['language'] ) );
	}
	if ( $is_cart_created ) {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		$total_items = $cart->total_items;
	} else {
		$total_items = 0;
	}
	if ( ! get_option( 'ec_option_hide_cart_icon_on_empty' ) || $total_items > 0 ) {
		$cartpage = wpeasycart_links()->get_cart_page();
		$cartpage = apply_filters( 'wpml_permalink', $cartpage, sanitize_text_field( $_POST['language'] ) );
		if ( $total_items != 1 ) {
			$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label_plural' );
		} else {
			$items_label = wp_easycart_language()->get_text( 'cart', 'cart_menu_icon_label' );
		}
		if ( $total_items > 0 ) {
			echo '<a href="' . esc_url( $cartpage ) . '"><span class="dashicons dashicons-cart" style="vertical-align:middle; margin-top:-5px; margin-right:5px; font-family:dashicons;"></span> ' . ' ( <span class="ec_menu_cart_text"><span class="ec_cart_items_total">' . esc_attr( $total_items ) . '</span> ' . esc_attr( $items_label ) . ' <span class="ec_cart_price_total">' . esc_attr( $GLOBALS['currency']->get_currency_display( $cart->subtotal ) ) . '</span></span> )</a>';

		} else {
			echo '<a href="' . esc_url( $cartpage ) . '"><span class="dashicons dashicons-cart" style="vertical-align:middle; margin-top:-5px; margin-right:5px; font-family:dashicons;"></span> ' . ' ( <span class="ec_menu_cart_text"><span class="ec_cart_items_total">' . esc_attr( $total_items ) . '</span> ' . esc_attr( $items_label ) . ' <span class="ec_cart_price_total"></span></span> )</a>';
		}
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_get_dynamic_cart_total', 'ec_ajax_get_dynamic_cart_total' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_dynamic_cart_total', 'ec_ajax_get_dynamic_cart_total' );
function ec_ajax_get_dynamic_cart_total() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-cart-icon-quantity' ) ) {
		echo '0';
		die();
	}

	if ( wpeasycart_session()->handle_session( false, false ) ) {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
		echo esc_attr( $cart->total_items );
	} else {
		echo 0;
	}
	die();
}

add_action( 'wp_ajax_ec_ajax_save_pickup_info', 'ec_ajax_save_pickup_info' );
add_action( 'wp_ajax_nopriv_ec_ajax_save_pickup_info', 'ec_ajax_save_pickup_info' );
function ec_ajax_save_pickup_info() {
	wpeasycart_session()->handle_session();
	$wpec_pickup_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	/* 6.0.2: the one-page checkout's single page sends its details form nonce ( checkout-info ). */
	if ( ! wp_verify_nonce( $wpec_pickup_nonce, 'wp-easycart-cart-submit-order-' . $GLOBALS['ec_cart_data']->ec_cart_id ) && ! wp_verify_nonce( $wpec_pickup_nonce, 'wp-easycart-cart-checkout-info-' . $GLOBALS['ec_cart_data']->ec_cart_id ) ) {
		die();
	}
	if ( ! isset( $_POST['pickup_date'] ) ) {
		die();
	}
	if ( ! isset( $_POST['pickup_date_time'] ) ) {
		die();
	}
	if ( ! isset( $_POST['pickup_asap'] ) ) {
		die();
	}
	if ( ! isset( $_POST['pickup_time'] ) ) {
		die();
	}
	$pickup_date = sanitize_text_field( $_POST['pickup_date'] );
	$pickup_date_time = sanitize_text_field( $_POST['pickup_date_time'] );
	$pickup_date_split = ( is_string( $pickup_date ) ) ? explode( ' 00:00:00 GMT', $pickup_date ) : array( $pickup_date, '' );
	$pickup_date_only = ( is_array( $pickup_date_split ) && count( $pickup_date_split ) > 0 ) ? $pickup_date_split[0] : $pickup_date;
	$wp_timezone_string = get_option( 'timezone_string' );
	$wp_gmt_offset = get_option( 'gmt_offset' );
	if ( $wp_timezone_string ) {
		date_default_timezone_set( $wp_timezone_string );
	} else {
		if ( $wp_gmt_offset !== false ) {
			$wp_timezone_offset = $wp_gmt_offset * 3600;
			@date_default_timezone_set( 'Etc/GMT' . ( $wp_gmt_offset < 0 ? '+' : '-' ) . abs( $wp_gmt_offset ) );
		}
	}
	$timestamp = strtotime( $pickup_date_only . ' ' . $pickup_date_time );
	if ( isset( $wp_timezone_offset ) && strpos( $pickup_date, 'GMT' ) !== false ) {
		preg_match( '/GMT([+-]\d{4})/', $pickup_date, $matches );
		$jquery_timezone_offset = $matches[1][0] === '+' ? 1 : -1;
		$jquery_timezone_offset *= ( (int) substr( $matches[1], 1, 2 ) * 3600 ) + ( (int) substr( $matches[1], 3, 2 ) * 60 );
		$timestamp += ( $wp_timezone_offset - $jquery_timezone_offset );
	}
	$formatted_pickup_date = date( 'Y-m-d H:i', $timestamp );
	$pickup_asap = ( isset( $_POST['pickup_asap'] ) && '1' == $_POST['pickup_asap'] ) ? 1 : 0;
	$pickup_time = ( ! $pickup_asap && $_POST['pickup_time'] ) ? date( 'H:i', strtotime( sanitize_text_field( $_POST['pickup_time'] ) ) ) : '';
	$GLOBALS['ec_cart_data']->cart_data->pickup_date = $formatted_pickup_date;
	$GLOBALS['ec_cart_data']->cart_data->pickup_asap = $pickup_asap;
	$GLOBALS['ec_cart_data']->cart_data->pickup_time = $pickup_time;
	$GLOBALS['ec_cart_data']->save_session_to_db();
	die();
}

// Helper function for AJAX calls in cart.
function ec_get_order_totals( $cart = false ) {
	if ( ! $cart ) {
		$cart = new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id );
	}
	$user =& $GLOBALS['ec_user'];

	$coupon_code = "";
	if ( $GLOBALS['ec_cart_data']->cart_data->coupon_code != "" )
		$coupon_code = $GLOBALS['ec_cart_data']->cart_data->coupon_code;

	$gift_card = "";
	if ( $GLOBALS['ec_cart_data']->cart_data->giftcard != "" )
		$gift_card = $GLOBALS['ec_cart_data']->cart_data->giftcard;

	// Shipping
	if ( wp_easycart_offers_active() ) {
		$wpeasycart_offer_result_local = ec_offer_integration::evaluate_cart( $cart );
		$GLOBALS['wpeasycart_offer_result'] = $wpeasycart_offer_result_local;
	} else {
		$wpeasycart_offer_result_local = null;
	}
	$sales_tax_discount = new ec_discount( $cart, $cart->discountable_subtotal, 0.00, $coupon_code, "", 0 );
	if ( null !== $wpeasycart_offer_result_local ) {
		$sales_tax_discount->add_discount( $wpeasycart_offer_result_local->discount_total );
	}
	$GLOBALS['wpeasycart_current_coupon_discount'] = $sales_tax_discount->coupon_discount;
	$shipping = new ec_shipping( $cart->shipping_subtotal, $cart->weight, $cart->store_shippable_items /* 6.0.2: the units the store ships */, 'RADIO', $GLOBALS['ec_user']->freeshipping, $cart->length, $cart->width, $cart->height, $cart->cart );
	$shipping_price = $shipping->get_shipping_price( $cart->get_handling_total() );
	// Tax (no VAT here)
	$sales_tax_discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, "", 0 );
	if ( null !== $wpeasycart_offer_result_local ) {
		$sales_tax_discount->add_discount( $wpeasycart_offer_result_local->discount_total );
	}
	if ( $sales_tax_discount->shipping_discount > 0 ) {
		$shipping_price_tax = ( $shipping_price > $sales_tax_discount->shipping_discount ) ? $shipping_price - $sales_tax_discount->shipping_discount : 0;
	} else {
		$shipping_price_tax = $shipping_price;
	}
	$tax = new ec_tax( $cart->subtotal, $cart->taxable_subtotal - $sales_tax_discount->coupon_discount, 0, $GLOBALS['ec_cart_data']->cart_data->shipping_state, $GLOBALS['ec_cart_data']->cart_data->shipping_country, $GLOBALS['ec_user']->taxfree, $shipping_price_tax, $cart );
	// Duty (Based on Product Price) - already calculated in tax
	// Get Total Without VAT, used only breifly
	if ( get_option( 'ec_option_no_vat_on_shipping' ) ) {
		$total_without_vat_or_discount = $cart->vat_subtotal + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $tax->duty_total;
	} else {
		$total_without_vat_or_discount = $cart->vat_subtotal + $shipping_price + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $tax->duty_total;
	}
	//If a discount used, and no vatable subtotal, we need to set to 0
	if ( $total_without_vat_or_discount < 0 )
		$total_without_vat_or_discount = 0;
	// Discount for Coupon
	$discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, $gift_card, $total_without_vat_or_discount );
	if ( null !== $wpeasycart_offer_result_local ) {
		$discount->add_discount( $wpeasycart_offer_result_local->discount_total ); /* 6.0.3: before the gift card takes its share */
	}
	// Amount to Apply VAT on
	$promotion = new ec_promotion();
	$vatable_subtotal = $total_without_vat_or_discount - $tax->tax_total - $discount->coupon_discount - $promotion->get_discount_total( $cart->subtotal );
	// If for some reason this is less than zero, we should correct
	if ( $vatable_subtotal < 0 )
		$vatable_subtotal = 0;
	// Get Tax Again For VAT
	$tax = new ec_tax( $cart->subtotal, $cart->taxable_subtotal - $sales_tax_discount->coupon_discount, $vatable_subtotal, $GLOBALS['ec_cart_data']->cart_data->shipping_state, $GLOBALS['ec_cart_data']->cart_data->shipping_country, $GLOBALS['ec_user']->taxfree, $shipping_price_tax, $cart );
	// Discount for Gift Card
	$grand_total = ( $cart->subtotal + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $shipping_price + $tax->duty_total );
	$discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, $gift_card, $grand_total );
	if ( null !== $wpeasycart_offer_result_local ) {
		$discount->add_discount( $wpeasycart_offer_result_local->discount_total ); /* 6.0.3: before the gift card takes its share */
	}
	// Order Totals
	$order_totals = new ec_order_totals( $cart, $GLOBALS['ec_user'], $shipping, $tax, $discount );
	return $order_totals;
}

/**
 * The key a 3-D Secure return link carries: only the link made for an order may remove that order when the check fails.
 *
 * @since 6.0.2
 * @param int $order_id Order.
 * @return string
 */
function wp_easycart_3ds_return_key( $order_id ) {
	return wp_hash( 'wp-easycart-3ds-return|' . (int) $order_id, 'nonce' );
}

/**
 * Whether a Stripe PaymentIntent is for this cart's total, in the store's Stripe currency.
 *
 * @since 6.0.2
 * @param object           $payment_intent PaymentIntent read from Stripe.
 * @param ec_cartpage|null $cartpage       The cart, when the caller already built it.
 * @return bool
 */
function wp_easycart_stripe_intent_matches_cart( $payment_intent, $cartpage = null ) {
	if ( ! is_object( $payment_intent ) || ! isset( $payment_intent->amount ) || ! isset( $payment_intent->currency ) ) {
		return false;
	}
	if ( strtolower( (string) $payment_intent->currency ) !== strtolower( (string) get_option( 'ec_option_stripe_currency' ) ) ) {
		return false;
	}
	if ( ! ( $cartpage instanceof ec_cartpage ) ) {
		$cartpage = new ec_cartpage();
	}
	$cents = (int) $payment_intent->amount;
	if ( (int) number_format( (float) $cartpage->order_totals->grand_total * 100, 0, '', '' ) === $cents ) {
		return true;
	}
	$totals = ec_get_order_totals( new ec_cart( $GLOBALS['ec_cart_data']->ec_cart_id ) ); /* how the classic checkout keeps the intent's amount */
	return isset( $totals->grand_total ) && (int) number_format( (float) $totals->grand_total * 100, 0, '', '' ) === $cents;
}

/**
 * Whether a Stripe PaymentIntent is for an order's total, in the store's Stripe currency.
 *
 * @since 6.0.2
 * @param object $payment_intent PaymentIntent read from Stripe.
 * @param object $order          ec_order row.
 * @return bool
 */
function wp_easycart_stripe_intent_matches_order( $payment_intent, $order ) {
	if ( ! is_object( $payment_intent ) || ! is_object( $order ) || ! isset( $payment_intent->amount ) || ! isset( $payment_intent->currency ) || ! isset( $order->grand_total ) ) {
		return false;
	}
	return strtolower( (string) $payment_intent->currency ) === strtolower( (string) get_option( 'ec_option_stripe_currency' ) )
		&& (int) number_format( (float) $order->grand_total * 100, 0, '', '' ) === (int) $payment_intent->amount;
}

/**
 * Keep an order whose Stripe payment does not match it on hold: a staff comment for the merchant, and the "order-payment-hold"
 * log the Stripe webhook checks before it marks anything paid.
 *
 * @since 6.0.2
 * @param int    $order_id Order.
 * @param string $comment  Staff comment.
 */
function wp_easycart_stripe_hold_order( $order_id, $comment ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "staff-comment" )', $order_id ) );
	$hold_log_id = $wpdb->insert_id;
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "comment", %s )', $hold_log_id, $order_id, $comment ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "author", %s )', $hold_log_id, $order_id, 'WP EasyCart' ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-payment-hold" )', $order_id ) );
}

function ec_get_cart_data() {
	$ec_db = new ec_db();
	$cartpage = new ec_cartpage();

	// GET NEW CART ITEM INFO
	$user_zones = $ec_db->get_zone_ids( $GLOBALS['ec_cart_data']->cart_data->shipping_country, $GLOBALS['ec_cart_data']->cart_data->shipping_state );
	$cart_array = array();

	for ( $i=0; $i<count( $cartpage->cart->cart ); $i++ ) {
		$shipping_restricted = 0;
		if ( get_option( 'ec_option_use_shipping' ) && $cartpage->cart->cart[$i]->is_shippable && '0' != $cartpage->cart->cart[$i]->shipping_restriction && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_state ) {
			$zone_found = false;
			for( $j = 0; $j < count( $user_zones ); $j++ ) {
				if ( $cartpage->cart->cart[$i]->shipping_restriction == $user_zones[$j]->zone_id ) {
					$zone_found = 1;
				}
			}
			if ( ! $zone_found ) {
				$shipping_restricted = 1;
			}
		}
		$cart_item = array( 
			'id' => $cartpage->cart->cart[$i]->cartitem_id,
			'unit_price' => wp_easycart_escape_html( $cartpage->cart->cart[$i]->get_unit_price() ),
			'total_price' => wp_easycart_escape_html( $cartpage->cart->cart[$i]->get_total() ),
			'promo_message' => wp_easycart_escape_html( $cartpage->cart->cart[$i]->get_promo_message() ),
			'quantity' => $cartpage->cart->cart[$i]->quantity,
			'stock_quantity' => $cartpage->cart->cart[$i]->stock_quantity,
			'allow_backorders' => $cartpage->cart->cart[$i]->allow_backorders,
			'use_optionitem_quantity_tracking' => $cartpage->cart->cart[$i]->use_optionitem_quantity_tracking,
			'optionitem_stock_quantity' => $cartpage->cart->cart[$i]->optionitem_stock_quantity,
			'shipping_restricted' => $shipping_restricted,
		);
		$cart_array[] = $cart_item;
	}
	// GET NEW CART ITEM INFO
	/* 6.0.2: the checkout's own totals ( what the one-page check compares and the payment charges ). A second calculation here left
	   out free-shipping promotions and coupon-before-VAT, so Place order could stop with "your total has changed" when it had not. */
	$order_totals = ( isset( $cartpage->order_totals ) && is_object( $cartpage->order_totals ) ) ? $cartpage->order_totals : ec_get_order_totals( $cartpage->cart );

	if ( $order_totals->discount_total != 0 ) {
		$has_discount = 1;
	} else {
		$has_discount = 0;
	}
	$cart_promo_html = '';
	if ( get_option( 'ec_option_show_promotion_discount_total' ) ) {
		$cart_promotion_only = $cartpage->get_cart_promotion();
		if ( false !== $cart_promotion_only ) {
			$cart_promo_html .= '<div class="ec_cart_promotions_list ec_cart_promotions_discount"><div class="ec_details_price_promo_discount"><span class="dashicons dashicons-tag"></span><span class="ec_details_price_promo_discount_label"> ' . esc_attr( $GLOBALS['language']->convert_text( $cart_promotion_only->promotion_name ) ) . '</span></div></div>';
		}
	}
	$cart_shipping_promo_html = '';
	if ( get_option( 'ec_option_show_promotion_discount_total' ) ) {
		$cart_shipping_promotion_only = $cartpage->get_cart_shipping_promotion();
		if ( false !== $cart_shipping_promotion_only ) {
			$cart_shipping_promo_html .= '<div class="ec_cart_promotions_list ec_cart_shipping_discount"><div class="ec_details_price_promo_discount"><span class="dashicons dashicons-tag"></span><span class="ec_details_price_promo_discount_label"> ' . esc_attr( $GLOBALS['language']->convert_text( $cart_shipping_promotion_only->promotion_name ) ) . '</span>';
			if ( $cart_shipping_promotion_only->discount > 0 ) {
				$cart_shipping_promo_html .= '<span class="ec_details_price_promo_discount_minus"> -</span><span class="ec_details_price_promo_discount_total">' . esc_attr( $GLOBALS['currency']->get_currency_display( $cart_shipping_promotion_only->discount ) ) . '</span>';
			}
			$cart_shipping_promo_html .= '</div></div>';
		}
	}

	$order_totals_array = array( 
		"sub_total_amt" => round( $order_totals->get_converted_sub_total(), 2 ),
		"sub_total" => $cartpage->get_subtotal(),
		"tax_total" => $GLOBALS['currency']->get_currency_display( $order_totals->tax_total ),
		"has_tax" => ( ( $order_totals->tax_total > 0 ) ? 1 : 0 ),
		"shipping_total" => $GLOBALS['currency']->get_currency_display( $order_totals->shipping_total ),
		"duty_total" => $GLOBALS['currency']->get_currency_display( $order_totals->duty_total ),
		"has_duty" => ( ( $order_totals->duty_total > 0 ) ? 1 : 0 ),
		"vat_total" => $GLOBALS['currency']->get_currency_display( $order_totals->vat_total ),
		"has_vat" => ( ( $order_totals->vat_total > 0 ) ? 1 : 0 ),
		"vat_rate_formatted" => $cartpage->get_vat_rate_formatted(),
		"gst_total" => $GLOBALS['currency']->get_currency_display( $order_totals->gst_total ),
		"has_gst" => ( ( $order_totals->gst_total > 0 ) ? 1 : 0 ),
		"hst_total" => $GLOBALS['currency']->get_currency_display( $order_totals->hst_total ),
		"has_hst" => ( ( $order_totals->hst_total > 0 ) ? 1 : 0 ),
		"pst_total" => $GLOBALS['currency']->get_currency_display( $order_totals->pst_total ),
		"has_pst" => ( ( $order_totals->pst_total > 0 ) ? 1 : 0 ),
		"tip_total" => $GLOBALS['currency']->get_currency_display( $order_totals->tip_total ),
		"discount_total" => $GLOBALS['currency']->get_currency_display( (-1) * $order_totals->discount_total ),
		"discount_message" => $cart_promo_html,
		"shipping_discount_message" => $cart_shipping_promo_html,
		"grand_total" => $GLOBALS['currency']->get_currency_display( $order_totals->get_converted_grand_total(), false ),
		"grand_total_amt" => round( $order_totals->get_converted_grand_total(), 2 ),
		'fees' => array(),
	);

	if ( count( $cartpage->tax->fees ) > 0 ) {
		foreach ( $cartpage->tax->fees as $fee ) {
			$order_totals_array['fees'][] = (object) array(
				'fee_id' => esc_attr( $fee->fee_id ),
				'fee_label' => esc_attr( $fee->label ),
				'fee_total' => esc_attr( $GLOBALS['currency']->get_currency_display( $fee->amount, false ) ),
			);
		}
	}

	ob_start();
	$cartpage->print_stripe_payment_button( false );
	$stripe_button = ob_get_clean();

	$square_wallet = false;
	if ( 'square' === get_option( 'ec_option_payment_process_method' ) && get_option( 'ec_option_square_digital_wallet' ) ) {
		$wallet_totals = $cartpage->get_wallet_order_totals(); /* 6.0.2: the wallets pay as a card ( Card flex fees ) */
		$square_wallet = array(
			'items' => $cartpage->get_dynamic_square_line_items( $wallet_totals ),
			'total' => number_format( (float) $wallet_totals->grand_total, 2, '.', '' ),
		);
	}

	$final_array = apply_filters( 'wp_easycart_cart_update_response', array( 	
		"cart" 										=> $cart_array,
		"order_totals"								=> $order_totals_array,
		"items_total"								=> $cartpage->cart->total_items,
		"weight_total"								=> $cartpage->cart->weight,
		"has_discount"								=> $has_discount,
		"has_backorder"								=> $cartpage->cart->has_backordered_item(),
		"stripe_wallet"               => $stripe_button,
		'square_wallet'               => $square_wallet, /* 6.0.2: the Square wallets' sheet follows the cart */
	) );

	return $final_array;
}

add_action( 'wp_ajax_ec_ajax_get_dynamic_cart_page', 'ec_ajax_get_dynamic_cart_page' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_dynamic_cart_page', 'ec_ajax_get_dynamic_cart_page' );
function ec_ajax_get_dynamic_cart_page() {
	wpeasycart_session()->handle_session();
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-dynamic-cart-page' ) ) {
		die();
	}

	if ( !preg_match( '/[0-9]+/', sanitize_text_field( $_POST['cart_page'] ) ) && !preg_match( '/[0-9]+\-[0-9]+/', sanitize_text_field( $_POST['cart_page'] ) ) ) {
		die();
	}

	if ( isset( $_POST['language'] ) && $_POST['language'] != 'NONE' ) {
		wp_easycart_language()->update_selected_language( sanitize_text_field( $_POST['language'] ) );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = sanitize_text_field( $_POST['language'] );
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	//Get the variables from the AJAX call
	$cartpage = new ec_cartpage();
	$cartpage->display_cart_dynamic( sanitize_text_field( $_POST['cart_page'] ), sanitize_key( $_POST['success_code'] ), sanitize_key( $_POST['error_code'] ) );
	die();
}

add_action( 'wp_ajax_ec_ajax_get_dynamic_account_page', 'ec_ajax_get_dynamic_account_page' );
add_action( 'wp_ajax_nopriv_ec_ajax_get_dynamic_account_page', 'ec_ajax_get_dynamic_account_page' );
function ec_ajax_get_dynamic_account_page() {

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-get-dynamic-account-page' ) ) {
		die();
	}

	$pages = array( 'forgot_password', 'reset_password', 'register', 'billing_information', 'shipping_information', 'personal_information', 'password', 'orders', 'order_details', 'subscription', 'subscriptions', 'subscription_details' );
	if ( sanitize_text_field( $_POST['account_page'] ) != '' && !in_array( sanitize_text_field( $_POST['account_page'] ), $pages ) && substr( sanitize_text_field( $_POST['account_page'] ), 0, 13 ) != 'order_details' && substr( sanitize_text_field( $_POST['account_page'] ), 0, 20 ) != 'subscription_details' && substr( sanitize_text_field( $_POST['account_page'] ), 0, 14 ) != 'reset_password' ) {
		$account_page = '';
	} else {
		$account_page = sanitize_text_field( $_POST['account_page'] );
	}
	
	if ( isset( $_POST['language'] ) ) {
		wp_easycart_language()->update_selected_language( sanitize_text_field( $_POST['language'] ) );
		$GLOBALS['ec_cart_data']->cart_data->translate_to = sanitize_text_field( $_POST['language'] );
		$GLOBALS['ec_cart_data']->save_session_to_db( );
	}

	//Get the variables from the AJAX call
	$accountpage = new ec_accountpage();
	$accountpage->display_account_dynamic( $account_page, (int) $_POST['page_id'], sanitize_key( $_POST['success_code'] ), sanitize_key( $_POST['error_code'] ) );
	die();
}

add_action( 'wp_ajax_ec_ajax_location_find_by_geo', 'ec_ajax_location_find_by_geo' );
add_action( 'wp_ajax_nopriv_ec_ajax_location_find_by_geo', 'ec_ajax_location_find_by_geo' );
function ec_ajax_location_find_by_geo() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-location' ) ) {
		die();
	}
	$ecdb = new ec_db();
	$product_id = ( isset( $_POST['product_id'] ) ) ? (int) $_POST['product_id'] : false;
	$type = ( isset( $_POST['type'] ) && 'cart' == $_POST['type'] ) ? 'cart' : false;
	if ( ! isset( $_POST['lat'] ) || ! isset( $_POST['long'] ) ) {
		$locations = $ecdb->get_locations_by_geo( false, false, $product_id, $type );
	} else {
		$target_lat = filter_var( $_POST['lat'], FILTER_VALIDATE_FLOAT );
		$target_long = filter_var( $_POST['long'], FILTER_VALIDATE_FLOAT );
		if ( $target_lat === false || $target_long === false || ! is_numeric( $_POST['lat'] ) || ! is_numeric( $_POST['long'] ) || $target_lat < -90.0 || $target_lat > 90.0 || $target_long < -180.0 || $target_long > 180.0 ) {
			$locations = $ecdb->get_locations_by_geo( false, false, $product_id, $type );
		} else {
			$locations = $ecdb->get_locations_by_geo( $target_lat, $target_long, $product_id, $type );
		}
	}
	$return_locations = wp_easycart_get_locations_response( $locations );
	wp_send_json_success( array( 'locations' => $return_locations ) );
}

add_action( 'wp_ajax_ec_ajax_location_search', 'ec_ajax_location_search' );
add_action( 'wp_ajax_nopriv_ec_ajax_location_search', 'ec_ajax_location_search' );
function ec_ajax_location_search() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-location' ) ) {
		die();
	}
	if ( ! isset( $_POST['search'] ) ) {
		wp_send_json_error( array( 'message' => 'invalid-request' ) );
	}
	if ( ! get_option( 'ec_option_pickup_location_google_site_key' ) || '' == get_option( 'ec_option_pickup_location_google_site_key' ) ) {
		wp_send_json_error( array( 'message' => 'invalid-request' ) );
	}
	$ecdb = new ec_db();
	$search_term = sanitize_text_field( $_POST['search'] );
	$lat_long = wp_easycart_get_location_geocode( $search_term );
	if ( false === $lat_long || ! is_array( $lat_long ) || ! isset( $lat_long['lat'] ) || ! isset( $lat_long['long'] ) ) {
		$locations = $ecdb->get_locations_by_geo( false, false );
	} else {
		$locations = $ecdb->get_locations_by_geo( $lat_long['lat'], $lat_long['long'] );
	}
	$return_locations = wp_easycart_get_locations_response( $locations );
	wp_send_json_success( array( 'locations' => $return_locations ) );
}

add_action( 'wp_ajax_ec_ajax_location_set_selected', 'ec_ajax_location_set_selected' );
add_action( 'wp_ajax_nopriv_ec_ajax_location_set_selected', 'ec_ajax_location_set_selected' );
function ec_ajax_location_set_selected() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'wp-easycart-location' ) ) {
		die();
	}
	if ( ! isset( $_POST['location_id'] ) ) {
		wp_send_json_error( array( 'message' => 'invalid-request' ) );
	}
	global $wpdb;
	$ecdb = new ec_db();
	wpeasycart_session()->handle_session();
	$location = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_location WHERE location_id = %d', (int) $_POST['location_id'] ) );
	if ( is_object( $location ) && isset( $location->location_id ) ) {
		$GLOBALS['ec_cart_data']->cart_data->pickup_location = (int) $location->location_id;
		$GLOBALS['ec_cart_data']->save_session_to_db();
		$total_removed = $ecdb->remove_invalid_cart_items_by_location( $GLOBALS['ec_cart_data']->ec_cart_id, $location->location_id );
		do_action( 'wpeasycart_cart_updated' ); /* 6.0.2: the pickup store and the items in the cart changed */
		wp_send_json_success( array( 'success' => 'saved', 'location' => (int) $location->location_id, 'total_removed' => (int) $total_removed ) );
	} else {
		wp_send_json_error( array( 'message' => 'invalid-request' ) );
	}
}
// End AJAX helper function for cart.
function wp_easycart_output_location_popup() {
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_location_selector.php' ) ) {
		include_once( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_location_selector.php' );
	} else {
		include_once( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_location_selector.php' );
	}
}
function wp_easycart_get_locations_response( $locations ) {
	$return_locations = array();
	foreach ( $locations as $location ) {
		$location_address_format = ( ( '' != $location->address_line_1 ) ? $location->address_line_1 : '' ) . ( ( '' != $location->address_line_2 ) ? ' ' . $location->address_line_2 : '' ) . ', ' . $location->city . ( ( '' != $location->state ) ? ' ' . $location->state : '' ) . ( ( '' != $location->zip ) ? ', ' . $location->zip : '' ) . ( ( '' != $location->country ) ? ', ' . $location->country : '' );
		$location_address_format =apply_filters( 'wp_easycart_location_address', $location_address_format, $location );
		$location_html = '<div class="wpeasycart-location-popup-store-item">
			<div class="wpeasycart-location-popup-store-details">
				<strong class="wpeasycart-location-popup-item-label">' . esc_attr( $location->location_label ) . '</strong>
				<p class="">' . esc_attr( $location_address_format ) . '</p>';
		if ( isset( $location->hours_note ) && is_string( $location->hours_note ) && '' != trim( $location->hours_note ) ) {
			$location_html .= '<p class="wpeasycart-location-popup-item-note">' . esc_attr( $location->hours_note ) . '</p>';
		}
		if ( isset( $location->distance_miles ) ) {
			if ( get_option( 'ec_option_pickup_location_show_km' ) ) {
				$location_distance_format = apply_filters( 'wp_easycart_location_distance', sprintf( wp_easycart_language( )->get_text( 'product_details', 'store_select_distance_km' ), $location->distance_km ), $location );
			} else {
				$location_distance_format = apply_filters( 'wp_easycart_location_distance', sprintf( wp_easycart_language( )->get_text( 'product_details', 'store_select_distance_m' ), $location->distance_miles ), $location );
			}
			$location_html .= '
				<span class="wpeasycart-location-popup-item-distance">' . esc_attr( $location_distance_format ) . '</span>';
		}
		if ( ( isset( $location->phone ) && '' != $location->phone ) || ( isset( $location->email ) && '' != $location->email ) ) {
			$location_html .= '
			<div class="wpeasycart-location-popup-button-row">';
			if ( isset( $location->phone ) && '' != $location->phone ) {
				$location_html .= '<a href="tel:' . esc_attr( $location->phone ) . '" title="' . esc_attr( $location->phone ) . '" class="wpeasycart-location-popup-item-phone"><span class="dashicons dashicons-phone"></span></a>';
			}
			if ( isset( $location->email ) && '' != $location->email ) {
				$location_html .= '<a href="mailto:' . esc_attr( $location->email ) . '" title="' . esc_attr( $location->email ) . '" class="wpeasycart-location-popup-item-email"><span class="dashicons dashicons-email-alt"></span></a>';
			}
			$location_html .= '</div>';
		}
		$location_html .= '
			</div>
			<button class="wpeasycart-location-popup-select-store-btn" data-location-id="' . esc_attr( $location->location_id ) . '" data-store-name="' . esc_attr( $location->location_label ) . '">' . wp_easycart_language( )->get_text( 'product_details', 'store_select_button' ) . '</button>
		</div>';
		$return_locations[] = (object) array(
			'location_info' => $location,
			'location_html' => $location_html,
		);
	}
	return $return_locations;
}
function wp_easycart_get_location_geocode( $search_term ) {
	$api_key = get_option( 'ec_option_pickup_location_google_site_key' );
	$base_url = 'https://maps.googleapis.com/maps/api/geocode/json';
	$query_params = array(
		'address' => $search_term,
		'key' => $api_key
	);
	$url = add_query_arg( $query_params, $base_url );
	$args = array(
		'timeout' => 10,
		'redirection' => 5,
		'httpversion' => '1.0',
		'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . ' (WPEasyCart/1.0)',
		'sslverify'   => true,
	);
	$response = wp_remote_get( $url, $args );
	if ( is_wp_error( $response ) ) {
		return false;
	}
	$http_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		return false;
	}
	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return false;
	}
	$data = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return false;
	}
	if ( isset( $data['status'] ) && $data['status'] === 'OK' && ! empty( $data['results'] ) ) {
		if ( isset( $data['results'][0]['geometry']['location']['lat'], $data['results'][0]['geometry']['location']['lng'] ) ) {
			return array(
				'lat' => (float) $data['results'][0]['geometry']['location']['lat'],
				'long' => (float) $data['results'][0]['geometry']['location']['lng'],
			 );
		} else {
			return false;
		}
	} else {
		return false; 
	}
}

add_filter( 'wp_title', 'ec_custom_title', 20 );

function ec_custom_title( $title ) {
	global $wpdb;
	$page_id = get_the_ID();
	$store_id = get_option( 'ec_option_storepage' );

	if ( $page_id == $store_id && isset( $_GET['model_number'] ) ) {
		$db = new ec_db();
		$products = $db->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s', sanitize_text_field( $_GET['model_number'] ) ), "", "", "" );
		if ( count( $products ) > 0 ) {
			$custom_title = $products[0]['title'] . " |" . $title;
			return $custom_title;
		} else {
			return $title;
		}
	} else if ( $page_id == $store_id ) {

		$additional_title = "";

		if ( isset( $_GET['manufacturer'] ) ) {
			$db = new ec_db();
			$manufacturer = $db->get_manufacturer_row( (int) $_GET['manufacturer'] );

			$additional_title .= $manufacturer->name . " |";
		}

		if ( isset( $_GET['menu'] ) ) {
			$custom_title = sanitize_text_field( $_GET['menu'] ) . " |" . $additional_title . $title;
			return $custom_title;
		} else if ( isset( $_GET['submenu'] ) ) {
			$custom_title = sanitize_text_field( $_GET['submenu'] ) . " |" . $additional_title . $title;
			return $custom_title;
		} else if ( isset( $_GET['subsubmenu'] ) ) {
			$custom_title = sanitize_text_field( $_GET['subsubmenu'] ) . " |" . $additional_title . $title;
			return $custom_title;
		} else {
			return $additional_title . $title;
		}	
	} else {
		return $title;
	}

}

function ec_theme_options_page_callback() {
	if ( is_dir( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option('ec_option_base_theme') . "/" ) )
		include( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option('ec_option_base_theme') . "/admin_panel.php");
	else
		include( EC_PLUGIN_DIRECTORY . "/design/theme/" . get_option('ec_option_latest_theme') . "/admin_panel.php");
}

/////////////////////////////////////////////////////////////////////
//CUSTOM POST TYPES
/////////////////////////////////////////////////////////////////////
add_action( 'init', 'wp_easycart_add_rewrite_webhooks' );
function wp_easycart_add_rewrite_webhooks() {
	add_rewrite_rule( '(.*)/wp-easycart/inc/amfphp/(.*)', '$1/wp-easycart-pro/inc/amfphp/$2', 'top' );
	add_rewrite_rule( '(.*)/paypal_webhook.php', '?wpeasycarthook=paypal-webhook', 'top' );
	add_rewrite_rule( '(.*)/print_giftcard.php?(.*)', '?wpeasycarthook=print-giftcard&$2', 'top' );
	add_rewrite_rule( '(.*)/redsys_success.php', '?wpeasycarthook=redsys-webhook', 'top' );
	add_rewrite_rule( '(.*)/sagepay_paynow_za_payment_complete.php', '?wpeasycarthook=sagepay-webhook', 'top' );
	add_rewrite_rule( '(.*)/stripe_webhook.php', '?wpeasycarthook=stripe-webhook', 'top' );
}

add_action( 'wp_loaded', 'wp_easycart_verify_rewrite_rules' );
function wp_easycart_verify_rewrite_rules() {
	if ( get_option( 'ec_option_store_rules_checked_version' ) === EC_CURRENT_VERSION ) {
		return;
	}

	$store_id = get_option( 'ec_option_storepage' );
	if ( ! $store_id ) {
		return;
	}

	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'ec_option_store_rules_checked_version', EC_CURRENT_VERSION, true );
		return;
	}

	$store_slug = ec_get_the_slug( $store_id );
	if ( '' === $store_slug ) {
		return;
	}

	$rules = get_option( 'rewrite_rules' );
	$has_rule = false;
	if ( is_array( $rules ) ) {
		if ( isset( $rules[ '^' . $store_slug . '/([^/]*)/?$' ] ) ) {
			$has_rule = true;
		} else {
			foreach ( $rules as $rewrite ) {
				if ( false !== strpos( $rewrite, 'ec_store=' ) ) {
					$has_rule = true;
					break;
				}
			}
		}
	}

	if ( ! $has_rule ) {
		flush_rewrite_rules( false );
	}

	update_option( 'ec_option_store_rules_checked_version', EC_CURRENT_VERSION, true );
}

add_action( 'update_option_ec_option_storepage', 'wp_easycart_reset_rewrite_check' );
function wp_easycart_reset_rewrite_check() {
	delete_option( 'ec_option_store_rules_checked_version' );
}

/**
 * A plan change that charged nothing ( 6.0.3 ): Stripe's invoice for a subscription update with nothing paid, such as a change
 * during a free trial ( the trial goes on ) or one covered by credit. It is not a payment, so the webhook records no order and
 * leaves the subscription's dates, price and payment count as the change left them.
 *
 * @since 6.0.3
 * @param object $invoice Stripe invoice.
 * @return bool
 */
function wp_easycart_stripe_invoice_is_free_change( $invoice ) {
	if ( ! is_object( $invoice ) || ! isset( $invoice->billing_reason ) || 'subscription_update' !== $invoice->billing_reason ) {
		return false;
	}
	$paid = isset( $invoice->amount_paid ) ? $invoice->amount_paid : ( isset( $invoice->total ) ? max( 0, $invoice->total ) : 1 );
	return 0 === (int) $paid;
}

/**
 * Subscription id carried by a Stripe invoice object. API versions before
 * 2025-03-31 expose invoice.subscription; newer versions moved it to
 * invoice.parent.subscription_details.subscription. Either may be an id or
 * an expanded object.
 */
function wp_easycart_stripe_invoice_subscription_id( $invoice ) {
	$candidates = array();
	if ( isset( $invoice->subscription ) ) {
		$candidates[] = $invoice->subscription;
	}
	if ( isset( $invoice->parent->subscription_details->subscription ) ) {
		$candidates[] = $invoice->parent->subscription_details->subscription;
	}
	if ( isset( $invoice->lines->data[0]->parent->subscription_item_details->subscription ) ) {
		$candidates[] = $invoice->lines->data[0]->parent->subscription_item_details->subscription;
	}
	foreach ( $candidates as $candidate ) {
		$id = is_object( $candidate ) && isset( $candidate->id ) ? $candidate->id : $candidate;
		if ( is_string( $id ) && '' != $id ) {
			return $id;
		}
	}
	return '';
}

/**
 * Charge id for a Stripe invoice. Older API versions expose invoice.charge;
 * newer versions list payments under invoice.payments. Falls back to the
 * payment intent id so a recurring order still records a transaction id.
 */
function wp_easycart_stripe_invoice_charge_id( $invoice ) {
	if ( isset( $invoice->charge ) ) {
		$id = is_object( $invoice->charge ) && isset( $invoice->charge->id ) ? $invoice->charge->id : $invoice->charge;
		if ( is_string( $id ) && '' != $id ) {
			return $id;
		}
	}
	if ( isset( $invoice->payments->data ) && is_array( $invoice->payments->data ) ) {
		foreach ( $invoice->payments->data as $invoice_payment ) {
			if ( isset( $invoice_payment->payment->charge ) ) {
				$id = is_object( $invoice_payment->payment->charge ) && isset( $invoice_payment->payment->charge->id ) ? $invoice_payment->payment->charge->id : $invoice_payment->payment->charge;
				if ( is_string( $id ) && '' != $id ) {
					return $id;
				}
			}
		}
		foreach ( $invoice->payments->data as $invoice_payment ) {
			if ( isset( $invoice_payment->payment->payment_intent ) ) {
				$id = is_object( $invoice_payment->payment->payment_intent ) && isset( $invoice_payment->payment->payment_intent->id ) ? $invoice_payment->payment->payment_intent->id : $invoice_payment->payment->payment_intent;
				if ( is_string( $id ) && '' != $id ) {
					return $id;
				}
			}
		}
	}
	if ( isset( $invoice->payment_intent ) ) {
		$id = is_object( $invoice->payment_intent ) && isset( $invoice->payment_intent->id ) ? $invoice->payment_intent->id : $invoice->payment_intent;
		if ( is_string( $id ) && '' != $id ) {
			return $id;
		}
	}
	return '';
}

function wp_easycart_verify_stripe_webhook( $payload ) {
	if ( ! get_option( 'ec_option_stripe_connect_webhook_secret' ) || '' == get_option( 'ec_option_stripe_connect_webhook_secret' ) ){
		return true;
	}
	if ( ! isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) {
		return false;
	}
	$endpoint_secret = get_option( 'ec_option_stripe_connect_webhook_secret' );
	$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
	$parts = explode( ',', $sig_header );
	if ( ! is_array( $parts ) ) {
		return false;
	}
	$timestamp = false;
	$signature = '';
	foreach ( $parts as $part ) {
		$item = explode('=', $part);
		if ( is_array( $item ) && count( $item ) == 2 ) {
			if ( 't' === trim( $item[0] ) ) {
				$timestamp = trim( $item[1] );
			} else if ( 'v1' === trim( $item[0] ) ) {
				$signature = trim( $item[1] );
			}
		}
	}
	if ( false === $timestamp || '' === $signature ) {
		return false;
	}
	$signed_payload = $timestamp . '.' . $payload;
	$expected_signature = hash_hmac( 'sha256', $signed_payload, $endpoint_secret );
	if ( ! hash_equals( $expected_signature, $signature ) ) {
		return false;
	}
	return true;
}

/**
 * The Stripe event the webhook acts on. With a signing secret the posted event must carry Stripe's signature. Without one
 * only the posted event id is used: the event itself is read back from Stripe with the store's own keys.
 *
 * @since 6.0.2
 * @param string $payload Request body.
 * @return object|false The event, or false when it is not an event Stripe sent this store.
 */
function wp_easycart_stripe_webhook_event( $payload ) {
	$posted = json_decode( $payload );
	if ( ! is_object( $posted ) || ! isset( $posted->id ) || ! is_string( $posted->id ) ) {
		return false;
	}
	if ( '' != get_option( 'ec_option_stripe_connect_webhook_secret' ) ) {
		return ( wp_easycart_verify_stripe_webhook( $payload ) ) ? $posted : false;
	}
	if ( ! preg_match( '/^evt_[A-Za-z0-9_]+$/', $posted->id ) ) {
		return false;
	}
	$method = get_option( 'ec_option_payment_process_method' );
	if ( 'stripe_connect' == $method && class_exists( 'ec_stripe_connect' ) ) {
		$event = ( new ec_stripe_connect() )->get_event( $posted->id );
	} else if ( 'stripe' == $method && class_exists( 'ec_stripe' ) && method_exists( 'ec_stripe', 'get_event' ) ) {
		$event = ( new ec_stripe() )->get_event( $posted->id );
	} else if ( 'stripe' == $method && class_exists( 'ec_stripe' ) ) { /* WP EasyCart PRO before 6.0.2: the key its Stripe gateway uses */
		$response = wp_remote_get(
			'https://api.stripe.com/v1/events/' . $posted->id,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'  => 'Bearer ' . get_option( 'ec_option_stripe_api_key' ),
					'Stripe-Version' => '2022-11-15',
				),
			)
		);
		$event = ( is_wp_error( $response ) ) ? false : json_decode( wp_remote_retrieve_body( $response ) );
	} else {
		return false;
	}
	return ( is_object( $event ) && isset( $event->id ) && $event->id === $posted->id && isset( $event->type ) && isset( $event->data->object ) ) ? $event : false;
}

/**
 * Whether a PayPal webhook event may be acted on.
 *
 * Stores connected through WP EasyCart Connect get their events from Connect, which checks PayPal's signature and forwards
 * the event with the key this store registered ( as for Square ). A store on its own PayPal app gets them straight from
 * PayPal, signed for the webhook this store created. A store that has neither ( registered before 6.0.2, or a webhook set up
 * by hand ) is accepted as before.
 *
 * While a new key is being registered ( wp_easycart_paypal_webhooks::register() ), the key sent with it is accepted too:
 * Connect may have stored it even when its answer never reached this store.
 *
 * @since 6.0.2
 * @param string $payload Request body.
 * @return bool
 */
function wp_easycart_paypal_webhook_verified( $payload ) {
	$sandbox  = (bool) get_option( 'ec_option_paypal_use_sandbox' );
	$merchant = (string) get_option( ( $sandbox ) ? 'ec_option_paypal_sandbox_merchant_id' : 'ec_option_paypal_production_merchant_id' );
	if ( '' !== $merchant ) {
		$key = (string) get_option( ( $sandbox ) ? 'ec_option_paypal_sandbox_webhook_key' : 'ec_option_paypal_production_webhook_key' );
		if ( '' === $key || wp_easycart_gateway_webhook_key_ok( $key ) ) { /* the same X-EasyCart-Notification-Key check as Square */
			return true;
		}
		$pending = class_exists( 'wp_easycart_paypal_webhooks' ) ? wp_easycart_paypal_webhooks::pending_key( $sandbox ? 'sandbox' : 'production' ) : '';
		return '' !== $pending && wp_easycart_gateway_webhook_key_ok( $pending );
	}
	$webhook_id = (string) get_option( ( $sandbox ) ? 'ec_option_paypal_sandbox_webhook_id' : 'ec_option_paypal_production_webhook_id' );
	return '' === $webhook_id || wp_easycart_paypal_signature_ok( $payload, $webhook_id );
}

/**
 * PayPal's own signature on a webhook event ( PAYPAL-TRANSMISSION-* headers ), checked against PayPal's certificate.
 *
 * @since 6.0.2
 * @param string $payload    Request body, exactly as received.
 * @param string $webhook_id The webhook the event was sent for.
 * @return bool
 */
function wp_easycart_paypal_signature_ok( $payload, $webhook_id ) {
	$names = array(
		'id'   => 'HTTP_PAYPAL_TRANSMISSION_ID',
		'time' => 'HTTP_PAYPAL_TRANSMISSION_TIME',
		'sig'  => 'HTTP_PAYPAL_TRANSMISSION_SIG',
		'cert' => 'HTTP_PAYPAL_CERT_URL',
		'algo' => 'HTTP_PAYPAL_AUTH_ALGO',
	);
	$sent = array();
	foreach ( $names as $key => $name ) {
		$sent[ $key ] = ( isset( $_SERVER[ $name ] ) ) ? trim( sanitize_text_field( wp_unslash( $_SERVER[ $name ] ) ) ) : '';
		if ( '' === $sent[ $key ] ) {
			return false;
		}
	}
	if ( 'SHA256WITHRSA' !== strtoupper( $sent['algo'] ) || ! function_exists( 'openssl_verify' ) ) {
		return false;
	}
	/* Only a certificate PayPal serves over HTTPS. */
	$cert_url = esc_url_raw( $sent['cert'] );
	$host     = strtolower( (string) wp_parse_url( $cert_url, PHP_URL_HOST ) );
	if ( 'https' !== wp_parse_url( $cert_url, PHP_URL_SCHEME ) || ! in_array( $host, array( 'api.paypal.com', 'api-m.paypal.com', 'api.sandbox.paypal.com', 'api-m.sandbox.paypal.com' ), true ) ) {
		return false;
	}
	$cache = 'wpec_paypal_cert_' . md5( $cert_url );
	$cert  = get_transient( $cache );
	if ( ! is_string( $cert ) || false === strpos( $cert, 'BEGIN CERTIFICATE' ) ) {
		$response = wp_remote_get( $cert_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$cert = (string) wp_remote_retrieve_body( $response );
		if ( false === strpos( $cert, 'BEGIN CERTIFICATE' ) ) {
			return false;
		}
		set_transient( $cache, $cert, DAY_IN_SECONDS );
	}
	$public_key = openssl_pkey_get_public( $cert );
	if ( ! $public_key ) {
		return false;
	}
	$message = $sent['id'] . '|' . $sent['time'] . '|' . $webhook_id . '|' . sprintf( '%u', crc32( $payload ) );
	return 1 === openssl_verify( $message, (string) base64_decode( $sent['sig'] ), $public_key, OPENSSL_ALGO_SHA256 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- PayPal sends the signature base64 encoded.
}

add_action( 'wp', 'wp_easycart_webhook_catch' );
function wp_easycart_webhook_catch() {
	if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'stripe-webhook' ) {
		$body = @file_get_contents('php://input');
		$json = wp_easycart_stripe_webhook_event( $body ); /* 6.0.2: a signed event, or one read back from Stripe */
		if ( ! $json ) {
			wp_send_json_error( array( 'message' => 'Invalid Signature' ), 400 );
		}

		global $wpdb;
		$mysqli = new ec_db();

		if ( isset( $json->type ) && isset( $json->data ) && isset( $json->id ) && isset( $json->type ) && isset( $json->data ) && isset( $json->data->object ) ) {
			$webhook_id = $json->id;
			$webhook_type = $json->type;
			$webhook_data = $json->data->object;
			$webhook = $mysqli->get_webhook( $webhook_id );
			if ( ! $webhook || 'evt_00000000000000' == $webhook_id ) {
				$mysqli->insert_webhook( $webhook_id, $webhook_type, $webhook_data );
				if ( $webhook_type == "charge.refunded" && isset( $webhook_data->id ) && '' != $webhook_data->id ) {
					global $wpdb;
					/* 6.0.2: an order paid more than once ( a pay link payment toward its balance ) has a Stripe charge per
					   payment: the refund is matched to its payment and counted against what that payment has refunded. */
					$wpec_refund_found = class_exists( 'wp_easycart_order_refunds' ) ? wp_easycart_order_refunds::for_stripe_charge( $webhook_data ) : null;
					$order             = $wpec_refund_found ? null : $wpdb->get_row( $wpdb->prepare( "SELECT ec_order.* FROM ec_order WHERE ec_order.stripe_charge_id = %s", $webhook_data->id ) );
					if ( $wpec_refund_found ) {
						wp_easycart_stripe_charge_refunded( $wpec_refund_found, $webhook_data );
					} else if ( is_object( $order ) ) {
						$previous_status = (int) $order->orderstatus_id;
						$stripe_charge_id = $webhook_data->id;
						$original_amount = $webhook_data->amount;
						$refund_total = 0;
						// amount_refunded is on the charge in every API version; the refunds list is not included from API 2022-11-15 on.
						if ( isset( $webhook_data->amount_refunded ) ) {
							$refund_total = (int) $webhook_data->amount_refunded;
						} else if ( isset( $webhook_data->refunds->data ) && is_array( $webhook_data->refunds->data ) ) {
							foreach ( $webhook_data->refunds->data as $refund ) {
								$refund_total = $refund_total + $refund->amount;
							}
						}
						$new_refund_total = $refund_total / 100;
						$refund_amount = round( $new_refund_total - (float) $order->refund_total, 2 );
						// 6.0.2: the charge reports everything refunded so far, so act on what it adds to the order's refund total.
						// A refund made from the EasyCart admin is already counted ( nothing added ); a second refund made in the Stripe
						// dashboard after an earlier one is no longer ignored because the order was already marked refunded.
						if ( $refund_amount >= 0.01 ) {
							$order_status = 16;
							if ( empty( $webhook_data->refunded ) && $refund_total < $original_amount ) {
								$order_status = 17;
							}
							$mysqli->update_stripe_order_status( $stripe_charge_id, $order_status, $new_refund_total );
							/* 6.0.2: the refund was made outside WP EasyCart ( one made here is already counted ), so the order's activity
							   says where it came from and the reason recorded in Stripe ( owner bug round 4, item 13 ). Written before the
							   status hook: the Reports ledger records the refund on that hook and takes its reason and reference from
							   this entry. */
							wp_easycart_stripe_refund_log( (int) $order->order_id, 16 === $order_status, $refund_amount, $webhook_data );
							if ( $previous_status !== $order_status ) {
								do_action( 'wpeasycart_order_status_update', (int) $order->order_id, $order_status, $previous_status );
							}
							if ( 16 === $order_status ) {
								do_action( 'wpeasycart_full_order_refund', $order->order_id );
							} else if ( 17 === $order_status ) {
								do_action( 'wpeasycart_partial_order_refund', $order->order_id, $refund_amount, $new_refund_total );
							}
							if ( get_option( 'ec_option_auto_send_refund_email' ) ) {
								$db_admin = new ec_db_admin();
								$order_row = $db_admin->get_order_row_admin( $order->order_id );
								if ( $order_row ) {
									$order_display = new ec_orderdisplay( $order_row, true, true );
									$order_display->send_refund_email();
								}
							}
							do_action( 'wpeasycart_stripe_webhook_order_refunded', $order->order_id );
						}
					}

				// Subscription Cancelled (manaually, by customer, or by failed payments)	
				} else if ( $webhook_type == "customer.subscription.deleted" && isset( $webhook_data->id ) && '' != $webhook_data->id ) {
					$stripe_subscription_id = $webhook_data->id;
					$subscription_row = $mysqli->get_stripe_subscription( $stripe_subscription_id );
					if ( $subscription_row ) {
						$subscription = new ec_subscription( $subscription_row );
						$mysqli->cancel_stripe_subscription( $stripe_subscription_id );
						if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
							wp_easycart_subscription_changes::closed( (int) $subscription_row->subscription_id ); /* 6.0.3: its open plan change will not happen */
						}
						$user = $mysqli->get_stripe_user( $webhook_data->customer );
						$subscription->send_subscription_ended_email( $user );
						do_action( 'wp_easycart_subscription_ended', $subscription, $user, $webhook_data );
					}

				// Subscription Trial is Ending in 3 Days	
				// Subscription status changed on Stripe's side ( past_due after failed retries, active again after a
				// card update paid the open invoice, unpaid, etc. ). Keep ec_subscription in sync instead of leaving
				// it on whatever status the last invoice webhook set.
				} else if ( $webhook_type == "customer.subscription.updated" && isset( $webhook_data->id ) && '' != $webhook_data->id && isset( $webhook_data->status ) ) {
					$subscription_row = $mysqli->get_stripe_subscription( $webhook_data->id );
					if ( $subscription_row && 0 != $subscription_row->subscription_id ) {
						$status_map = array(
							'active'             => 'Active',
							'trialing'           => 'Active',
							'past_due'           => 'Failed',
							'unpaid'             => 'Failed',
							'canceled'           => 'Canceled',
							'incomplete_expired' => 'Canceled',
						);
						$status_map = apply_filters( 'wp_easycart_stripe_subscription_status_map', $status_map );
						if ( isset( $status_map[ $webhook_data->status ] ) && $status_map[ $webhook_data->status ] != $subscription_row->subscription_status ) {
							$wpdb->query( $wpdb->prepare( "UPDATE ec_subscription SET subscription_status = %s WHERE stripe_subscription_id = %s", $status_map[ $webhook_data->status ], $webhook_data->id ) );
							$mysqli->insert_response( 0, 0, "STRIPE Subscription", 'Subscription ' . $webhook_data->id . ' status synced: ' . $subscription_row->subscription_status . ' -> ' . $status_map[ $webhook_data->status ] . ' ( Stripe status ' . $webhook_data->status . ' )' );
							do_action( 'wp_easycart_subscription_status_synced', $subscription_row, $status_map[ $webhook_data->status ], $webhook_data );
						}
						/* 6.0.3: a plan change that started at renewal, or was paid after 3-D Secure, applies here ( read again from Stripe ). */
						if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
							wp_easycart_subscription_changes::webhook( (string) $webhook_data->id );
						}
					}

				// Subscription Trial is Ending in 3 Days
				} else if ( $webhook_type == "customer.subscription.trial_will_end" && isset( $webhook_data->id ) && '' != $webhook_data->id ) {
					$stripe_subscription_id = $webhook_data->id;
					$subscription_row = $mysqli->get_stripe_subscription( $stripe_subscription_id );
					/* 6.0.3: not when the trial reminder ( N days before, wp_easycart_subscription_reminders ) already went out for this trial. */
					if ( $subscription_row && ( ! class_exists( 'wp_easycart_subscription_reminders' ) || wp_easycart_subscription_reminders::webhook_trial_notice( $subscription_row, isset( $webhook_data->trial_end ) ? (int) $webhook_data->trial_end : 0 ) ) ) {
						$subscription = new ec_subscription( $subscription_row );
						/* 6.0.3: the email needs the subscriber ( it was called without one, which stopped the webhook on PHP 7.1+ ). */
						$user = $mysqli->get_stripe_user( isset( $webhook_data->customer ) ? $webhook_data->customer : '' );
						if ( ! $user || empty( $user->email ) ) {
							$user = (object) array(
								'user_id'    => (int) $subscription_row->user_id,
								'email'      => (string) $subscription_row->email,
								'first_name' => (string) $subscription_row->first_name,
								'last_name'  => (string) $subscription_row->last_name,
							);
						}
						$subscription->send_subscription_trial_ending_email( $user );
					}

				// 6.0.3: a plan change that charged nothing ( during a trial ) is not a payment: no order, nothing counted.
				} else if ( $webhook_type == "invoice.payment_succeeded" && '' != wp_easycart_stripe_invoice_subscription_id( $webhook_data ) && wp_easycart_stripe_invoice_is_free_change( $webhook_data ) ) {
					$mysqli->insert_response( 0, 0, "STRIPE Subscription", 'Invoice ' . ( isset( $webhook_data->id ) ? $webhook_data->id : '' ) . ' for subscription ' . wp_easycart_stripe_invoice_subscription_id( $webhook_data ) . ' charged nothing ( a plan change ): no order recorded.' );
					/* The next billing date follows the change ( a change that keeps the trial keeps its end; one to another schedule moves it ). */
					$wpec_period_end = 0;
					if ( isset( $webhook_data->lines->data ) && is_array( $webhook_data->lines->data ) ) {
						foreach ( $webhook_data->lines->data as $wpec_line ) {
							if ( isset( $wpec_line->period->end ) ) {
								$wpec_period_end = max( $wpec_period_end, (int) $wpec_line->period->end );
							}
						}
					}
					if ( $wpec_period_end > time() ) {
						$wpdb->query( $wpdb->prepare( 'UPDATE ec_subscription SET next_payment_date = %s WHERE stripe_subscription_id = %s', $wpec_period_end, wp_easycart_stripe_invoice_subscription_id( $webhook_data ) ) );
					}

				// Subscription Recurring Billing Succeeded	
				} else if ( $webhook_type == "invoice.payment_succeeded" && '' != wp_easycart_stripe_invoice_subscription_id( $webhook_data ) ) {
					$payment_timestamp = $webhook_data->created;
					$stripe_subscription_id = wp_easycart_stripe_invoice_subscription_id( $webhook_data );
					$stripe_charge_id = wp_easycart_stripe_invoice_charge_id( $webhook_data );
					if ( ! isset( $webhook_data->charge ) || ! is_string( $webhook_data->charge ) || '' == $webhook_data->charge ) {
						$webhook_data->charge = $stripe_charge_id; // insert_stripe_order() reads ->charge directly.
					}
					$subscription = $mysqli->get_stripe_subscription( $stripe_subscription_id );
					if ( $subscription ) {
						$mysqli->insert_response( 0, 1, "STRIPE Subscription", print_r( $webhook_data, true ) );

						if ( $subscription && ( $subscription->last_payment_date + 10 ) >= $payment_timestamp ) {
							$mysqli->update_stripe_order( $subscription->subscription_id, $stripe_charge_id );
						} else if ( $subscription ) {
							$user = $mysqli->get_stripe_user( $webhook_data->customer );
							$order_id = $mysqli->insert_stripe_order( $subscription, $webhook_data, $user );

							// A successful payment after failed retries means Stripe kept the subscription alive
							// ( dunning set to "leave past due" / "mark unpaid" ); bring our status back in line.
							if ( 0 != $subscription->subscription_id && in_array( $subscription->subscription_status, array( 'Failed', 'Canceled', 'Cancelled' ), true ) ) {
								$wpdb->query( $wpdb->prepare( "UPDATE ec_subscription SET subscription_status = 'Active' WHERE subscription_id = %d", $subscription->subscription_id ) );
								do_action( 'wp_easycart_subscription_reactivated', $subscription, $webhook_data );
							}

							do_action( 'wpeasycart_subscription_paid', $order_id );
							do_action( 'wpeasycart_order_paid', $order_id );

							$db_admin = new ec_db_admin();
							$order_row = $db_admin->get_order_row_admin( $order_id );
							$order = new ec_orderdisplay( $order_row, true, true );
							$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $subscription->product_id ) );
							if ( ! is_object( $product ) || $product->subscription_recurring_email ) {
								$order->send_email_receipt();
							}

							if ( $subscription->payment_duration > 0 && $subscription->payment_duration <= $subscription->number_payments_completed + 1 ) {
								if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
									$stripe = new ec_stripe();
								} else {
									$stripe = new ec_stripe_connect();
								}
								if ( class_exists( 'wp_easycart_subscription_changes' ) ) {
									wp_easycart_subscription_changes::before_cancel( (int) $subscription->subscription_id );
								}
								$stripe->cancel_subscription( $user, $stripe_subscription_id );
								$mysqli->cancel_stripe_subscription( $stripe_subscription_id );
							} else {
								$mysqli->update_stripe_subscription( $stripe_subscription_id, $webhook_data );
							}
						}
					}

				// Subscription Failed Payment	
				} else if ( $webhook_type == "invoice.payment_failed" && '' != wp_easycart_stripe_invoice_subscription_id( $webhook_data ) ) {
					if ( ! isset( $webhook_data->billing_reason ) || $webhook_data->billing_reason != 'subscription_create' ) {
						$payment_timestamp = isset( $webhook_data->created ) ? $webhook_data->created : time();
						$stripe_subscription_id = wp_easycart_stripe_invoice_subscription_id( $webhook_data );
						$stripe_charge_id = wp_easycart_stripe_invoice_charge_id( $webhook_data );
						if ( ! isset( $webhook_data->charge ) || ! is_string( $webhook_data->charge ) || '' == $webhook_data->charge ) {
							$webhook_data->charge = $stripe_charge_id; // insert_stripe_failed_order() reads ->charge directly.
						}
						$subscription = $mysqli->get_stripe_subscription( $stripe_subscription_id );
						if ( $subscription ) {
							$mysqli->insert_response( 0, 1, "STRIPE Subscription Failed", print_r( $subscription, true ) );

							if ( $subscription ) {

								$order_id = $mysqli->insert_stripe_failed_order( $subscription, $webhook_data );
								$mysqli->update_stripe_subscription_failed( $subscription->subscription_id, $webhook_data );

								$db_admin = new ec_db_admin();
								$order_row = $db_admin->get_order_row_admin( $order_id );
								$order = new ec_orderdisplay( $order_row, true, true );

								$order->send_failed_payment();
							}
						}
					}

				// iDEAL now chargeable	
				} else if ( $webhook_type == "source.chargeable" && isset( $webhook_data->id ) && isset( $webhook_data->client_secret ) && '' != $webhook_data->id && '' != $webhook_data->client_secret ) {
					global $wpdb;
					$order = $wpdb->get_row( $wpdb->prepare( "SELECT order_id, grand_total FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id . ':' . $webhook_data->client_secret ) );
					if ( $order ) {
						if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
							$stripe = new ec_stripe();
						} else {
							$stripe = new ec_stripe_connect();
						}

						$order_totals = (object) array(
							'grand_total' => $order->grand_total,
						);

						$response = $stripe->insert_charge( $order_totals, false, $webhook_data->id, $order->order_id, false );

						if ( ! isset( $response->error ) ) {
							/* Update Stock Quantity */
							$ec_db_admin = new ec_db_admin();
							$order_row = $ec_db_admin->get_order_row_admin( $order->order_id );
							$orderdetails = $ec_db_admin->get_order_details_admin( $order->order_id );

							foreach ( $orderdetails as $orderdetail ) {
								$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
								if ( $product ) {
									if ( $product->use_optionitem_quantity_tracking ) {
										$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
									}
									$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order->order_id ) );
									$order_log_id = $wpdb->insert_id;
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order->order_id, $orderdetail->product_id ) );
									$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order->order_id, '-' . $orderdetail->quantity ) );
								}
							}

							// Update Order Status/Send Alerts
							$ec_db_admin->update_order_status( $order->order_id, "3" );
							do_action( 'wpeasycart_order_paid', $order->order_id );

							// send email
							$order_display = new ec_orderdisplay( $order_row, true, true );
							$order_display->send_email_receipt();
							$order_display->send_gift_cards();
						}
					}

				// iDEAL failed	
				} else if ( ( $webhook_type == "source.failed" || $webhook_type == "source.canceled" ) && isset( $webhook_data->id ) && isset( $webhook_data->client_secret ) && '' != $webhook_data->id && '' != $webhook_data->client_secret ) {
					global $wpdb;
					$order = $wpdb->get_row( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id . ':' . $webhook_data->client_secret ) );
					if ( $order ) {
						$wpdb->query( $wpdb->prepare( "DELETE FROM ec_order WHERE order_id = %d AND gateway_transaction_id = %s", $order->order_id, $webhook_data->id . ':' . $webhook_data->client_secret ) );
					}

				// Payment Intent Succeeded	
				} else if ( $webhook_type == "invoice.upcoming" && get_option( 'ec_option_stripe_subscription_notices' ) ) {
					$mysqli->insert_response( 0, 0, "STRIPE Upcoming Payment", print_r( $webhook_data, true ) );
					$stripe_subscription_id = $webhook_data->subscription;
					$subscription_row = $mysqli->get_stripe_subscription( $stripe_subscription_id );
					if ( $subscription_row ) {
						$mysqli->insert_response( 0, 0, "STRIPE Upcoming Payment Sub Row", print_r( $subscription_row, true ) );
						$subscription = new ec_subscription( $subscription_row );
						$user = $mysqli->get_stripe_user( $webhook_data->customer );
						$subscription->send_subscription_upcoming_payment_email( $subscription, $user, $webhook_data );
						do_action( 'wp_easycart_subscription_upcoming_payment', $subscription, $user, $webhook_data );
					}

				// Payment Intent Succeeded	
				} else if ( $webhook_type == "payment_intent.succeeded" && isset( $webhook_data->id ) && isset( $webhook_data->client_secret ) && '' != $webhook_data->id && '' != $webhook_data->client_secret ) {
					global $wpdb;
					$ec_db_admin = new ec_db_admin();

					$stripe = false;
					if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
						$stripe = new ec_stripe();
					} else if ( get_option( 'ec_option_payment_process_method' ) == 'stripe_connect' ) {
						$stripe = new ec_stripe_connect();
					}

					$mysqli->insert_response( 0, 0, "STRIPE Payment Complete", print_r( $webhook_data, true ) );
					/* 6.0.2: a payment made on an order's pay link is finished by wp_easycart_order_pay ( once, with its own receipt ). */
					$wpec_pay_link = class_exists( 'wp_easycart_order_pay' ) && wp_easycart_order_pay::webhook_intent( $webhook_data );
					$order = $wpec_pay_link ? false : $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id ) );
					if ( ! $order && ! $wpec_pay_link ) {
						$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id . ':' . $webhook_data->client_secret ) );
					}
					$wpec_held = $order && $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_id FROM ec_order_log WHERE order_id = %d AND order_log_key = %s LIMIT 1', $order->order_id, 'order-payment-hold' ) );
					if ( $wpec_held ) {
						/* 6.0.2: held ( the payment did not match the cart ): the merchant decides, nothing is marked paid here. */
						$ec_db_admin->insert_response( $order->order_id, 0, 'STRIPE Webhook: order on hold, not marked paid', $webhook_data->id );
					} else if ( $order ) {
						/* 6.0.2: paid only when Stripe says the intent succeeded and, for an order not yet paid, that it was for the order's total. */
						$payment_intent = ( $stripe ) ? $stripe->get_payment_intent( $webhook_data->id ) : $webhook_data;
						$wpec_approved = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT is_approved FROM ec_orderstatus WHERE status_id = %d', $order->orderstatus_id ) );
						if ( ! is_object( $payment_intent ) || ! isset( $payment_intent->status ) || 'succeeded' != $payment_intent->status ) {
							$ec_db_admin->insert_response( $order->order_id, 1, 'STRIPE Webhook: payment not confirmed, order not marked paid', $webhook_data->id );
						} else if ( ! $wpec_approved && ! wp_easycart_stripe_intent_matches_order( $payment_intent, $order ) ) {
							/* translators: %s: Stripe payment id. */
							wp_easycart_stripe_hold_order( $order->order_id, sprintf( __( 'The Stripe payment %s does not match this order’s total, so the order was not marked paid. Check the payment in Stripe before you ship it.', 'wp-easycart' ), $payment_intent->id ) );
							$ec_db_admin->insert_response( $order->order_id, 1, 'STRIPE Webhook: payment does not match the order, order on hold', $webhook_data->id );
						} else {
							$charge_id = ( isset( $payment_intent->charges->data[0]->id ) ) ? $payment_intent->charges->data[0]->id : '';
							if ( '' == $charge_id && isset( $payment_intent->latest_charge ) && is_string( $payment_intent->latest_charge ) && '' != $payment_intent->latest_charge ) {
								$charge_id = $payment_intent->latest_charge;
							}
							if ( '' != $charge_id ) {
								$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET stripe_charge_id = %s WHERE order_id = %d", $charge_id, $order->order_id ) );
							}
							if ( $wpec_approved ) {
								/* 6.0.2: the checkout page ( or an earlier webhook ) already completed this order: only the charge is
								   recorded. Marking it paid again fired the status and paid hooks twice ( invoices, accounting, the Meta
								   purchase ) and sent the receipt and gift cards again. */
								$ec_db_admin->insert_response( $order->order_id, 0, 'STRIPE Webhook: order already paid, nothing changed', $webhook_data->id );
							} else {
								$order_row = $ec_db_admin->get_order_row_admin( $order->order_id );
								$orderdetails = $ec_db_admin->get_order_details_admin( $order->order_id );
								// Update Order Status/Send Alerts
								$ec_db_admin->update_order_status( $order->order_id, "6" );
								do_action( 'wpeasycart_order_paid', $order->order_id );

								// send email
								if ( apply_filters( 'wp_easycart_stripe_webhook_payment_intent_succeeded_send_email', true ) ) {
									$order_display = new ec_orderdisplay( $order_row, true, true );
									$order_display->send_email_receipt();
									$order_display->send_gift_cards();
								}
							}

							if ( $stripe ) {
								$stripe->update_payment_intent_description( $webhook_data->id, $order->order_id );
							}
						}

					} else if ( ! $wpec_pay_link ) {
						if ( $stripe ) {
							$payment_intent = $stripe->get_payment_intent( $webhook_data->id );
							$mysqli->insert_response( 0, 0, "STRIPE Payment Intent", print_r( $payment_intent, true ) );
							if ( $payment_intent ) {
								sleep(20);
								global $wpdb;
								$tempcart_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_tempcart_data WHERE stripe_paymentintent_id = %s AND stripe_pi_client_secret = %s', $webhook_data->id, $webhook_data->client_secret ) );
								$mysqli->insert_response( 0, 0, "STRIPE tempcart data", print_r( $tempcart_data, true ) );
								if ( $tempcart_data ) {
									$GLOBALS['ec_cart_data'] = new ec_cart_data( $tempcart_data->session_id );
									$charge = $stripe->get_charge( $payment_intent->latest_charge );
									$mysqli->insert_response( 0, 0, "STRIPE Charge", print_r( $tempcart_data, true ) );
									if ( $charge ) {
										$redirect_types = array( 'affirm', 'afterpay_clearpay', 'alipay', 'bancontact', 'eps', 'giropay', 'ideal', 'klarna', 'multibanco', 'p24', 'sofort' );
										if ( wp_easycart_onepage_active() ) {
											$redirect_types[] = 'card';
										}
										if ( isset( $charge->payment_method_details ) && isset( $charge->payment_method_details->type ) && in_array( $charge->payment_method_details->type, $redirect_types ) ) {
											$mysqli->insert_response( 0, 0, "STRIPE Redirect Types", print_r( $redirect_types, true ) );
											/* 6.0.2: a card charge ( Apple Pay and Google Pay included ) is a card payment for flex fees, as the checkout priced it. */
											$wpec_fee_type = ( 'card' === $charge->payment_method_details->type ) ? ec_tax::use_fee_payment_type( 'card' ) : null;
											$ec_db = new ec_db();
											$cart = new ec_cart( $tempcart_data->session_id );
											$user = new ec_user( $tempcart_data->user_id );
											$shipping = new ec_shipping( $cart->shipping_subtotal, $cart->weight, $cart->store_shippable_items /* 6.0.2: the units the store ships */, 'RADIO', $user->freeshipping, $cart->length, $cart->width, $cart->height, $cart->cart );

											if ( isset( $tempcart_data->coupon_code ) && '' != $tempcart_data->coupon_code ) {
												$coupon_code = $tempcart_data->coupon_code;
												$coupon_result = $GLOBALS['ec_coupons']->redeem_coupon_code( $coupon_code );
												if ( $coupon_result ) {
													$coupon = $coupon_result;
												}
											} else {
												$coupon_code = '';
											}

											if ( isset( $tempcart_data->giftcard ) && '' != $tempcart_data->giftcard ) {
												$gift_card = $tempcart_data->giftcard;
												$giftcard = $ec_db->redeem_gift_card( $gift_card );
												if ( ! $giftcard ) {
													$gift_card = '';
												}
											} else {
												$gift_card = '';
											}

											$promotion = new ec_promotion();
											$promotion->apply_free_shipping( $cart );

											$shipping_price = $shipping->get_shipping_price( $cart->get_handling_total() );
											if ( wp_easycart_offers_active() ) {
												$wpeasycart_offer_result_local = ec_offer_integration::evaluate_cart( $cart );
												$GLOBALS['wpeasycart_offer_result'] = $wpeasycart_offer_result_local;
											} else {
												$wpeasycart_offer_result_local = null;
											}
											$sales_tax_discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, "", 0 );
											if ( null !== $wpeasycart_offer_result_local ) {
												$sales_tax_discount->add_discount( $wpeasycart_offer_result_local->discount_total );
											}
											$GLOBALS['wpeasycart_current_coupon_discount'] = $sales_tax_discount->coupon_discount;

											if ( $sales_tax_discount->shipping_discount > 0 ) {
												$shipping_price_tax = ( $shipping_price > $sales_tax_discount->shipping_discount ) ? $shipping_price - $sales_tax_discount->shipping_discount : 0;
											} else {
												$shipping_price_tax = $shipping_price;
											}
											$tax = new ec_tax( $cart->subtotal, $cart->taxable_subtotal - $sales_tax_discount->coupon_discount, 0, $tempcart_data->shipping_state, $tempcart_data->shipping_country, $user->taxfree, $shipping_price_tax, $cart, false, array( 'context' => 'cart', 'session' => $tempcart_data, 'user' => $user ) ); /* 6.0.2: the shopper's session, for a tax service */

											if ( get_option( 'ec_option_no_vat_on_shipping' ) ) {
												$total_without_vat_or_discount = $cart->vat_subtotal + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $tax->duty_total;
											} else {
												$total_without_vat_or_discount = $cart->vat_subtotal + $shipping_price + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $tax->duty_total;
											}

											if ( $total_without_vat_or_discount < 0 ) {
												$total_without_vat_or_discount = 0;
											}

											$discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, $gift_card, $total_without_vat_or_discount );
											if ( null !== $wpeasycart_offer_result_local ) {
												$discount->add_discount( $wpeasycart_offer_result_local->discount_total ); /* 6.0.3: before the gift card takes its share */
											}
											$promotion = new ec_promotion();

											$vatable_subtotal = $total_without_vat_or_discount - $tax->tax_total - $discount->coupon_discount - $promotion->get_discount_total( $cart->subtotal );
											if ( $vatable_subtotal < 0 ) {
												$vatable_subtotal = 0;
											}

											$tax = new ec_tax( $cart->subtotal, $cart->taxable_subtotal - $sales_tax_discount->coupon_discount, $vatable_subtotal, $tempcart_data->shipping_state, $tempcart_data->shipping_country, $user->taxfree, $shipping_price_tax, $cart, false, array( 'context' => 'cart', 'session' => $tempcart_data, 'user' => $user ) );

											$grand_total = ( $cart->subtotal + $tax->tax_total + $tax->gst + $tax->hst + $tax->pst + $shipping_price + $tax->duty_total );
											$discount = new ec_discount( $cart, $cart->discountable_subtotal, $shipping_price, $coupon_code, $gift_card, $grand_total );
											if ( null !== $wpeasycart_offer_result_local ) {
												$discount->add_discount( $wpeasycart_offer_result_local->discount_total ); /* 6.0.3: before the gift card takes its share */
											}

											$order_totals = new ec_order_totals( $cart, $user, $shipping, $tax, $discount );
											$GLOBALS['ec_order_grand_total' ] = $order_totals->grand_total;
											ec_tax::use_fee_payment_type( $wpec_fee_type );

											$credit_card = new ec_credit_card( '', '', '', '', '', '' );
											$payment = new ec_payment( $credit_card, '' );
											
											/* Do final check for existing order before adding! */
											$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id . ':' . $webhook_data->client_secret ) );
											if ( ! $order ) {
												$order = new ec_order( $cart, $user, $shipping, $tax, $discount, $order_totals, $payment );
												$mysqli->insert_response( 0, 0, "STRIPE New Webhook Order", print_r( $order, true ) );
												$order->submit_order( 'ideal' );
												$order_id = $order->order_id;
												$stripe->update_payment_intent_description( $webhook_data->id, $order_id );

												$order_gateway = 'stripe_connect';
												if ( get_option( 'ec_option_payment_process_method' ) == 'stripe' ) {
													$order_gateway = 'stripe';
												}

												$order_status = 6;
												if ( $webhook_data->status == 'succeeded' ) {
													$order_status = 3;
												} else if ( $webhook_data->status == 'requires_capture' ) {
													$order_status = 12;
												} else if ( $webhook_data->status == 'processing' ) {
													$order_status = 12;
												} else if ( $webhook_data->status == 'canceled' ) {
													$order_status = 19;
												}
												/* 6.0.2: paid, but a fulfillment partner's shipping could not be confirmed for this address: on hold with a note. */
												if ( 19 !== (int) $order_status && class_exists( 'wp_easycart_shipping_groups' ) && wp_easycart_shipping_groups::hold_order( $order_id, (object) array( 'shipping' => $shipping ) ) ) {
													$order_status = 12;
												}

												// Maybe send email receipts
												if ( $order_status == 3 ) {
													$ec_db_admin = new ec_db_admin();
													$orderdetails = $ec_db_admin->get_order_details_admin( $order_id );

													/* Update Stock Quantity */
													foreach ( $orderdetails as $orderdetail ) {
														$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
														if ( $product ) {
															if ( $product->use_optionitem_quantity_tracking ) {
																$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
															}
															$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
															$mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
															$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
															$order_log_id = $wpdb->insert_id;
															$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
															$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
														}
													}

													// Update Order Status/Send Alerts
													do_action( 'wpeasycart_order_paid', $order_id );

													// send email
													$order_row = $ec_db_admin->get_order_row_admin( $order_id );
													$order_display = new ec_orderdisplay( $order_row, true, true );
													$order_display->send_email_receipt();
													$order_display->send_gift_cards();
												}

												$ec_db_admin->clear_tempcart( $tempcart_data->session_id );
												$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, order_gateway = %s, gateway_transaction_id = %s, payment_method = %s, stripe_charge_id = %s WHERE order_id = %d', $order_status, $order_gateway, $webhook_data->id . ':' . $webhook_data->client_secret, $charge->payment_method_details->type, $payment_intent->latest_charge, $order_id ) );
												/* 6.0.2: the final status is written directly, so tell listeners ( offers, reviews, invoices, accounting ). */
												do_action( 'wpeasycart_order_status_update', (int) $order_id, (int) $order_status );
											}
										}
									}
								}
							}
						}
					}

				} else if ( $webhook_type == "payment_intent.payment_failed" && isset( $webhook_data->id ) && isset( $webhook_data->client_secret ) && '' != $webhook_data->id && '' != $webhook_data->client_secret ) {
					global $wpdb;
					$ec_db_admin = new ec_db_admin();

					$ec_db_admin->insert_response( 0, 0, "STRIPE Payment Failed", print_r( $webhook_data, true ) );
					$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id ) );
					if ( ! $order ) {
						$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ec_order WHERE gateway_transaction_id = %s", $webhook_data->id . ':' . $webhook_data->client_secret ) );
					}
					if ( $order ) {
						$ec_db_admin->update_order_status( $order->order_id, "19" );
					}

				}

				do_action( 'wpeasycart_stripe_webhook', $webhook_id, $webhook_type, $webhook_data );
			}

		}
		wp_send_json_success( array( 'success' => true ), 200 );

	} else if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'paypal-webhook' ) {
		// Init DB References
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		$body = @file_get_contents('php://input');
		$json = json_decode( $body );

		/* 6.0.2: anything that is not a PayPal event ( no event_type: a PayPal Standard IPN post, a stray request ) is
		   answered as before and acts on nothing; it is not an event, so it stays out of the notification log. */
		if ( ! is_object( $json ) || ! isset( $json->event_type ) || ! is_scalar( $json->event_type ) ) {
			$ec_db_admin->insert_response( 0, 0, 'PayPal Webhook', 'No event type match! ---- ' . print_r( $json, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log entry, as before.
			wp_send_json_success( array( 'success' => true ), 200 );
		}
		$ecwh_event = ( isset( $json->id ) && is_scalar( $json->id ) ) ? (string) $json->id : '';
		$ecwh_type  = (string) $json->event_type;

		/* 6.0.2: WP EasyCart Connect confirms a key before it keeps it ( wp_easycart_paypal_webhooks::key_check() ); the check
		   acts on nothing and stays out of the notification log. */
		if ( class_exists( 'wp_easycart_paypal_webhooks' ) && defined( 'wp_easycart_paypal_webhooks::KEY_CHECK' ) && wp_easycart_paypal_webhooks::KEY_CHECK === $ecwh_type ) {
			$ecwh_challenge = wp_easycart_paypal_webhooks::key_check( $json );
			if ( '' === $ecwh_challenge ) {
				status_header( 403 );
				wp_send_json_error( array( 'message' => 'invalid key' ), 403 );
			}
			wp_send_json_success( array( 'key_check' => $ecwh_challenge ), 200 );
		}

		/* 6.0.2: a webhook event is acted on only once it is verified ( wp_easycart_paypal_webhook_verified() ). A refused
		   one is recorded without its id, so it can never mark a real event as already handled. */
		if ( ! wp_easycart_paypal_webhook_verified( $body ) ) {
			$ec_db_admin->insert_response( 0, 1, 'PayPal Webhook not verified, ignored', $ecwh_event );
			$ecwh_connect = '' !== (string) get_option( get_option( 'ec_option_paypal_use_sandbox' ) ? 'ec_option_paypal_sandbox_merchant_id' : 'ec_option_paypal_production_merchant_id' );
			wp_easycart_gateway_webhook_log( 'paypal', '', 'rejected', $ecwh_connect ? __( 'A notification arrived without the right key and was ignored.', 'wp-easycart' ) : __( 'A notification arrived without a valid PayPal signature and was ignored.', 'wp-easycart' ) );
			status_header( 403 );
			wp_send_json_error( array( 'message' => 'Invalid Signature' ), 403 );
		}

		/* 6.0.2: every verified event is recorded ( id and type ) before anything is done with it. A repeat of an event
		   already handled is acknowledged and dropped, so a replay cannot refund, restock or change a status twice. */
		if ( ! wp_easycart_gateway_webhook_claim( 'paypal', $ecwh_event, $ecwh_type ) ) {
			wp_send_json_success( array( 'success' => true, 'duplicate' => true ), 200 );
		}

		// Payment was voided
		if ( $json->event_type == 'PAYMENT.AUTHORIZATION.VOIDED' && isset( $json->resource->parent_payment ) && '' != $json->resource->parent_payment ) {
			$paypal_payment_id = $json->resource->parent_payment;
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s", $paypal_payment_id ) ); /* 6.0.2: was an unset variable, which matched an order with no transaction id */
			if ( !$order_id ) {
				die();
			}
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook VOIDED Response", print_r( $json, true ) );

			$ec_db_admin->update_order_status( $order_id, "19" );

		// Order Processed
		} else if ( ( $json->event_type == 'CHECKOUT.ORDER.PROCESSED' || ( $json->event_type == 'PAYMENT.SALE.COMPLETED' && $json->resource->payment_mode == 'ECHECK' ) ) && isset( $json->resource->id ) && '' != $json->resource->id ) {
			$paypal_order_id = $json->resource->id;
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s", $paypal_order_id ) );
			if ( ! $order_id ) {
				die();
			}

			$order_row = $ec_db_admin->get_order_row_admin( $order_id );
			$orderdetails = $ec_db_admin->get_order_details_admin( $order_id );
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook Complete Response", print_r( $json, true ) . " --- " . print_r( $order_row, true ) );
			if ( $order_row ) {
				// Update Order Gateway ID From Order to Payment (used on refunds)
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET gateway_transaction_id = %s WHERE order_id = %d", $json->resource->payment_details->payment_id, $order_id ) );

				/* Update Stock Quantity */
				foreach ( $orderdetails as $orderdetail ) {
					$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
					if ( $product ) {
						if ( $product->use_optionitem_quantity_tracking ) {
							$ec_db_admin->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
						}
						$ec_db_admin->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
						$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
						$order_log_id = $wpdb->insert_id;
						$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
						$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
					}
				}

				// Update Order Status to Paid
				$ec_db_admin->update_order_status( $order_id, "10" );
				do_action( 'wpeasycart_order_paid', $order_id );

				// send email
				$order_display = new ec_orderdisplay( $order_row, true, true );
				$order_display->send_email_receipt();
				$order_display->send_gift_cards();
			}

		// Payment was Refunded
		} else if ( ( $json->event_type == 'PAYMENT.CAPTURE.REFUNDED' || $json->event_type == 'PAYMENT.SALE.REFUNDED' ) && isset( $json->resource ) ) {
			// Payments v1 refunds name the sale and its payment. Orders v2 refunds link "up" to the refunded capture, whose id checkout stores on the order.
			$paypal_ids = array();
			$refund_amount = 0;
			if ( isset( $json->resource->sale_id ) && isset( $json->resource->parent_payment ) && '' != $json->resource->sale_id && '' != $json->resource->parent_payment ) {
				$paypal_ids = array( $json->resource->parent_payment, $json->resource->sale_id );
				$refund_amount = abs( (float) $json->resource->amount->total );
			} else if ( isset( $json->resource->links ) && is_array( $json->resource->links ) && isset( $json->resource->amount->value ) ) {
				foreach ( $json->resource->links as $link ) {
					if ( isset( $link->rel ) && isset( $link->href ) && 'up' === $link->rel && preg_match( '#/v2/payments/captures/([A-Za-z0-9-]+)#', $link->href, $capture_match ) ) {
						$paypal_ids = array( $capture_match[1], $capture_match[1] );
					}
				}
				$refund_amount = abs( (float) $json->resource->amount->value );
			}
			$order_id = ( $paypal_ids ) ? $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s OR gateway_transaction_id = %s', $paypal_ids[0], $paypal_ids[1] ) ) : 0;
			if ( ! $order_id ) {
				$ec_db_admin->insert_response( 0, 0, 'PayPal Webhook REFUNDED Response', 'No order match! ---- ' . print_r( $json, true ) );
				die();
			}
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook REFUNDED Response", print_r( $json, true ) );

			$order = $wpdb->get_row( $wpdb->prepare( "SELECT orderstatus_id, refund_total, grand_total FROM ec_order WHERE order_id = %d", $order_id ) );
			$order_status = $order->orderstatus_id;
			$refund_failed = isset( $json->resource->status ) && in_array( strtoupper( $json->resource->status ), array( 'CANCELLED', 'FAILED' ), true );

			// Refunds made from the EasyCart admin have already set status 16/17 and the refund total.
			if ( ! $refund_failed && 16 != $order_status && 17 != $order_status ) {
				/* 6.0.2: what was paid, when it is recorded ( an order changed after it was paid is refunded in full when all of it went back ). */
				$original_amount = class_exists( 'wp_easycart_order_payments' ) ? wp_easycart_order_payments::refund_basis( (int) $order_id ) : (float) $order->grand_total;
				$refund_total = round( (float) $order->refund_total + $refund_amount, 2 );
				$order_status = ( $refund_total < $original_amount ) ? 17 : 16;
				$wpdb->query( $wpdb->prepare( "UPDATE ec_order SET orderstatus_id = %d, refund_total = %s WHERE order_id = %d", $order_status, $refund_total, $order_id ) );
				/* 6.0.2: written directly, so tell status listeners too ( the guard above means the status did change ). */
				do_action( 'wpeasycart_order_status_update', (int) $order_id, (int) $order_status );

				if ( 16 === $order_status ) {
					do_action( 'wpeasycart_full_order_refund', $order_id );
				} else {
					do_action( 'wpeasycart_partial_order_refund', $order_id, $refund_amount, $refund_total );
				}
			}

		// Payment was Denied
		} else if ( ( $json->event_type == 'PAYMENT.CAPTURE.DENIED' || $json->event_type == 'PAYMENT.SALE.DENIED' ) && isset( $json->resource->sale_id ) && isset( $json->resource->parent_payment ) && '' != $json->resource->sale_id && '' != $json->resource->parent_payment ) {
			$paypal_sale_id = $json->resource->sale_id;
			$paypal_payment_id = $json->resource->parent_payment;
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s OR gateway_transaction_id = %s", $paypal_payment_id, $paypal_sale_id ) );
			if ( !$order_id ) {
				die();
			}
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook DENIED Response", print_r( $json, true ) );
			$ec_db_admin->update_order_status( $order_id, "7" );

		// Payment Pending
		} else if ( ( $json->event_type == 'PAYMENT.CAPTURE.PENDING' || $json->event_type == 'PAYMENT.SALE.PENDING' ) && isset( $json->resource->id ) && isset( $json->resource->parent_payment ) && '' != $json->resource->id && '' != $json->resource->parent_payment ) {
			$paypal_sale_id = $json->resource->id;
			$paypal_payment_id = $json->resource->parent_payment;
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s OR gateway_transaction_id = %s", $paypal_payment_id, $paypal_sale_id ) );
			if ( !$order_id ) {
				die();
			}
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook PENDING Response", print_r( $json, true ) );
			$ec_db_admin->update_order_status( $order_id, "8" );

		// Payment Reversed
		} else if ( ( $json->event_type == 'PAYMENT.CAPTURE.REVERSED' || $json->event_type == 'PAYMENT.SALE.REVERSED' ) && isset( $json->resource->sale_id ) && isset( $json->resource->parent_payment ) && '' != $json->resource->sale_id && '' != $json->resource->parent_payment ) {
			$paypal_sale_id = $json->resource->sale_id;
			$paypal_payment_id = $json->resource->parent_payment;
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM ec_order WHERE gateway_transaction_id = %s OR gateway_transaction_id = %s", $paypal_payment_id, $paypal_sale_id ) );
			if ( !$order_id ) {
				die();
			}
			$ec_db_admin->insert_response( $order_id, 0, "PayPal Webhook REVERSED Response", print_r( $json, true ) );
			$ec_db_admin->update_order_status( $order_id, "9" );

		// 6.0.2: Orders v2 captures ( completed, pending, denied, reversed ): the branches above read the older Payments API.
		} else if ( class_exists( 'wp_easycart_paypal_webhooks' ) && method_exists( 'wp_easycart_paypal_webhooks', 'capture_event' ) && wp_easycart_paypal_webhooks::capture_event( $ecwh_type, $json ) ) {
			// Handled ( or logged with no order to match ) by wp_easycart_paypal_webhooks::capture_event().

		} else {
			$ec_db_admin->insert_response( 0, 0, "PayPal Webhook", 'No event type match! ---- ' . print_r( $json, true ) );
		}
		wp_send_json_success( array( 'success' => true ), 200 );

	} else if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'redsys-webhook' ) {
		global $wpdb;
		$mysqli = new ec_db_admin();

		try{
			$redsys = new Tpv();
			$key = get_option( 'ec_option_redsys_key' );

			$parameters = $redsys->getMerchantParameters( sanitize_text_field( $_POST["Ds_MerchantParameters"] ) );
			$DsResponse = (int) $parameters["Ds_Response"];
			$DsResponse += 0;
			if ( $redsys->check( $key, $_POST ) && $DsResponse <= 99 ) {
				$order_id = intval( substr( $parameters['Ds_Order'], 0, -3 ) );
				$response_code = intval( $parameters['Ds_Response'] );
				$mysqli->insert_response( $order_id, 0, "Redsys Success", $response_code . ", " . print_r( $parameters, true ) );


				if ( $response_code <= 99 ) {
					$mysqli->update_order_transaction_id( $order_id, $parameters['Ds_AuthorisationCode'] );
					$order_row = $mysqli->get_order_row_admin( $order_id );
					$orderdetails = $mysqli->get_order_details_admin( $order_id );

					if ( $order_row ) {
						$mysqli->update_order_status( $order_id, "10" );
						do_action( 'wpeasycart_order_paid', $order_id );

						/* Update Stock Quantity */
						foreach ( $orderdetails as $orderdetail ) {
							$product = $wpdb->get_row( $wpdb->prepare( "SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d", $orderdetail->product_id ) );
							if ( $product ) {
								if ( $product->use_optionitem_quantity_tracking ) {
									$mysqli->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
								}
								$mysqli->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
								$order_log_id = $wpdb->insert_id;
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
								$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
							}
						}

						// send email
						$order_display = new ec_orderdisplay( $order_row, true, true );
						$order_display->send_email_receipt();
						$order_display->send_gift_cards();
					}
				}
			} else {
				$mysqli->insert_response( 0, 1, "Redsys Failed", "response was invalid." );
			}
		}
		catch( Exception $e ) {
			$mysqli->insert_response( 0, 1, "Redsys Try Failed", $e->getMessage() );
		}
		wp_send_json_success( array( 'success' => true ), 200 );

	} else if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'sagepay-webhook' ) {
		global $wpdb;
		$mysqli = new ec_db_admin();

		/*
		 * 6.0.2: the post only supplies the RequestTrace. The order, the amount and whether the payment was accepted
		 * all come from Netcash's own record of that transaction ( wp_easycart_paynow_za_lookup() ). The order is
		 * named by the reference checkout sent as p2, "<random>-<order id>".
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- server-to-server notification from Netcash; its content is confirmed with Netcash below.
		$mysqli->insert_response( 0, 0, 'SagePay PayNow South Africa', print_r( map_deep( wp_unslash( $_POST ), 'sanitize_text_field' ), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- gateway log entry.
		$request_trace = ( isset( $_POST['RequestTrace'] ) ) ? sanitize_text_field( wp_unslash( $_POST['RequestTrace'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $request_trace ) {
			wp_send_json_error( array( 'message' => 'missing trace' ), 400 );
		}

		$transaction = wp_easycart_paynow_za_lookup( $request_trace );
		if ( is_wp_error( $transaction ) ) {
			$mysqli->insert_response( 0, 1, 'SagePay PayNow South Africa', 'Error: ' . $transaction->get_error_message() );
			// Not answered with a 200, so a resent notification can still complete the order.
			wp_send_json_error( array( 'message' => 'transaction not confirmed' ), ( 'paynow_unreachable' === $transaction->get_error_code() ) ? 503 : 400 );
		}

		$order_id = 0;
		if ( isset( $transaction['Reference'] ) && preg_match( '/-(\d+)$/', (string) $transaction['Reference'], $reference_match ) ) {
			$order_id = (int) $reference_match[1];
		}
		$order_row = ( $order_id ) ? $mysqli->get_order_row_admin( $order_id ) : false;
		$accepted  = ( isset( $transaction['TransactionAccepted'] ) && ( true === $transaction['TransactionAccepted'] || 'true' === strtolower( (string) $transaction['TransactionAccepted'] ) ) );
		$amount    = ( isset( $transaction['Amount'] ) ) ? (float) str_replace( array( ',', ' ' ), '', (string) $transaction['Amount'] ) : -1;
		$reason    = ( isset( $transaction['Reason'] ) ) ? (string) $transaction['Reason'] : '';

		if ( ! $order_row || ( 'sagepay_paynow_za' !== $order_row->payment_method && 'sagepay' !== $order_row->order_gateway ) ) {
			$mysqli->insert_response( $order_id, 1, 'SagePay PayNow South Africa', 'Error: No Pay Now order matches this transaction reference.' );

		} elseif ( ! in_array( (int) $order_row->orderstatus_id, array( 5, 7, 8, 9 ), true ) ) {
			// Already paid, or moved on by the store ( refunded, cancelled ): a repeat of the notification changes nothing.
			$mysqli->insert_response( $order_id, 0, 'SagePay PayNow South Africa', 'Notice: The order is no longer awaiting payment, so this notification was not applied.' );

		} elseif ( $accepted && abs( round( $amount, 2 ) - round( (float) $order_row->grand_total, 2 ) ) < 0.005 ) {
			$mysqli->update_order_status( $order_id, '10' );
			do_action( 'wpeasycart_order_paid', $order_id );

			/* Update Stock Quantity: Pay Now orders are not taken out of stock until paid. */
			$orderdetails = $mysqli->get_order_details_admin( $order_id );
			foreach ( $orderdetails as $orderdetail ) {
				if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT stock_adjusted FROM ec_orderdetail WHERE orderdetail_id = %d', $orderdetail->orderdetail_id ) ) ) {
					continue;
				}
				$product = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_product.* FROM ec_product WHERE ec_product.product_id = %d', $orderdetail->product_id ) );
				if ( $product ) {
					if ( $product->use_optionitem_quantity_tracking ) {
						$mysqli->update_quantity_value( $orderdetail->quantity, $orderdetail->product_id, $orderdetail->optionitem_id_1, $orderdetail->optionitem_id_2, $orderdetail->optionitem_id_3, $orderdetail->optionitem_id_4, $orderdetail->optionitem_id_5 );
					}
					$mysqli->update_product_stock( $orderdetail->product_id, $orderdetail->quantity );
					$mysqli->update_details_stock_adjusted( $orderdetail->orderdetail_id );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log( order_id, order_log_key ) VALUES( %d, "order-stock-update" )', $order_id ) );
					$order_log_id = $wpdb->insert_id;
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "product_id", %s )', $order_log_id, $order_id, $orderdetail->product_id ) );
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_order_log_meta( order_log_id, order_id, order_log_meta_key, order_log_meta_value ) VALUES( %d, %d, "quantity", %s )', $order_log_id, $order_id, '-' . $orderdetail->quantity ) );
				}
			}

			// Send the receipt and any gift cards.
			$order_display = new ec_orderdisplay( $mysqli->get_order_row_admin( $order_id ), true, true );
			$order_display->send_email_receipt();
			$order_display->send_gift_cards();

		} elseif ( $accepted ) {
			$mysqli->insert_response( $order_id, 1, 'SagePay PayNow South Africa', 'Error: Transaction total does not match that in the order table.' );

		} elseif ( 'Denied' === $reason ) {
			$mysqli->update_order_status( $order_id, '7' );

		} else {
			$mysqli->insert_response( $order_id, 0, 'SagePay PayNow South Africa', 'Warning: Transaction not accepted, but also not denied. ' . $reason );
		}
		wp_send_json_success( array( 'success' => true ), 200 );

	} else if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'square-webhook' && get_option( 'ec_option_square_webhooks' ) ) {
		global $wpdb;
		$ec_db_admin = new ec_db_admin();

		$body = @file_get_contents('php://input'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- raw request body.
		$json = json_decode( $body );

		/*
		 * 6.0.1: notifications reach this store through connect.wpeasycart.com, which is where Square's own
		 * signature is checked — Square signs the notification URL it was given, and that URL is the proxy's,
		 * so the signature could never verify here. What is checked here is the shared key this store handed
		 * the proxy when webhooks were switched on, sent back with every forwarded request. Until this store
		 * has registered a key the request is accepted as before, because existing stores registered without
		 * one; the Square panel asks them to reconnect and close the gap.
		 */
		$ecwh_key = (string) get_option( 'ec_option_square_webhook_key' );
		if ( '' !== $ecwh_key && ! wp_easycart_square_webhook_key_ok( $ecwh_key ) ) {
			wp_easycart_square_webhook_log( '', 'rejected', __( 'A notification arrived without the right key and was ignored.', 'wp-easycart' ) );
			status_header( 403 );
			wp_send_json_error( array( 'message' => 'invalid key' ), 403 );
		}

		/* Every event is recorded, whatever its type, so the log means something. A repeat of an event already
		   seen is acknowledged and dropped, so a replay cannot move stock a second time. */
		$ecwh_event = ( isset( $json->event_id ) ) ? (string) $json->event_id : '';
		$ecwh_type  = ( isset( $json->type ) ) ? (string) $json->type : 'unknown';
		if ( '' !== $ecwh_event && wp_easycart_square_webhook_seen( $ecwh_event ) ) {
			wp_easycart_square_webhook_log( $ecwh_event, $ecwh_type, __( 'Already handled; ignored.', 'wp-easycart' ) );
			wp_send_json_success( array( 'success' => true, 'square' => true, 'duplicate' => true ), 200 );
		}
		wp_easycart_square_webhook_log( $ecwh_event, $ecwh_type, '' );

		/* Update Inventory Hook */		if ( isset( $json ) && is_object( $json ) && isset( $json->type ) && 'inventory.count.updated' == $json->type ) {
			/* Only sync if it is enabled*/
			if ( get_option( 'ec_option_square_auto_sync' ) ) {
				if ( isset( $json->data->object ) && isset( $json->data->object->inventory_counts ) && is_array( $json->data->object->inventory_counts ) ) {
					foreach ( $json->data->object->inventory_counts as $inventory_count ) {
						if ( isset( $inventory_count ) && is_object( $inventory_count ) && isset( $inventory_count->catalog_object_id ) ) {
							$location_id = ( isset( $inventory_count->location_id ) ) ? $inventory_count->location_id : '';
							$selected_location_id = ( get_option( 'ec_option_square_is_sandbox' ) ) ? get_option( 'ec_option_square_sandbox_location_id' ) : get_option( 'ec_option_square_location_id' );

							/* Only sync if correct location */
							if ( $selected_location_id == $location_id ) {
								if ( 'ITEM_VARIATION' == $inventory_count->catalog_object_type && 'IN_STOCK' == $inventory_count->state ) {
									/* Get Object to Fix Changes to Tracking */
									$item_found = false;
									$is_enabled = $use_optionitem_quantity_tracking = 0;
									if ( class_exists( 'ec_square' ) ) {
										$square = new ec_square();
										$catalog_object = $square->get_catalog_object( $inventory_count->catalog_object_id );
										if ( $catalog_object && is_object( $catalog_object ) ) {
											$item_found = true;
											$ec_db_admin->insert_response( 0, 0, "Square Webhook (Item Verify)", print_r( $catalog_object, true ) );
											if ( $square->allowed_at_location( $catalog_object ) && empty( $catalog_object->is_deleted ) ) {
												$is_enabled = 1;
												/* 6.0.3: the location's own setting, else the variation's ( a variation that said "don't track" counted as tracked ). */
												$use_optionitem_quantity_tracking = $square->variation_tracks_inventory( $catalog_object, $location_id ) ? 1 : 0;
											}
										}
									}

									/* Update Quantity for a Variation or Product */
									if ( $item_found ) {
										/* 6.0.3: the values in the columns' order ( they were swapped: a variation Square stopped counting was switched off ). */
										$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET is_stock_tracking_enabled = %d, is_enabled = %d, quantity = %d WHERE square_id = %s', $use_optionitem_quantity_tracking, $is_enabled, $inventory_count->quantity, $inventory_count->catalog_object_id ) );
									} else {
										$wpdb->query( $wpdb->prepare( 'UPDATE ec_optionitemquantity SET quantity = %d WHERE square_id = %s', $inventory_count->quantity, $inventory_count->catalog_object_id ) );
									}
									$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = %d WHERE square_variation_id = %s', $inventory_count->quantity, $inventory_count->catalog_object_id ) );

									/* Get Row and Product*/
									$optionitemquantity_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE square_id = %s', $inventory_count->catalog_object_id ) );
									if ( $optionitemquantity_row && is_object( $optionitemquantity_row ) && isset( $optionitemquantity_row->product_id ) ) {
										$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $optionitemquantity_row->product_id ) );
									} else {
										$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE square_variation_id = %s', $inventory_count->catalog_object_id ) );
									}

									/* Recalculate stock total for variant products */
									if ( $product && is_object( $product ) && $product->use_optionitem_quantity_tracking ) {
										$stock_count = 0;
										$optionitems = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d', $product->product_id ) );
										foreach ( $optionitems as $optionitem ) {
											if ( $optionitem->is_enabled && $optionitem->is_stock_tracking_enabled ) {
												$stock_count += $optionitem->quantity;
											} else if ( $optionitem->is_enabled ) {
												$stock_count += 1;
											}
										}
										$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET stock_quantity = %d WHERE product_id = %d', $stock_count, $product->product_id ) );
									}

									/* Clear the Cache if Possible */
									if ( $product && is_object( $product ) && isset( $product->model_number ) ) {
										ec_db::product_cache_changed();
									}
								}
							}
						}
					}
				}
			}
			$ec_db_admin->insert_response( 0, 0, "Square Webhook (Inventory Update)", print_r( $json, true ) );

		}
		wp_send_json_success( array( 'success' => true, 'square' => true ), 200 );

	} else if ( isset( $_GET['wpeasycarthook'] ) && $_GET['wpeasycarthook'] == 'print-giftcard' ) {
		if ( isset( $_GET['order_id'] ) && isset( $_GET['orderdetail_id'] ) && isset( $_GET['giftcard_id'] ) ) { 
			//Get the variables from the AJAX call
			$order_id = (int) $_GET['order_id'];
			$orderdetail_id = (int) $_GET['orderdetail_id'];
			$giftcard_id = isset( $_GET['giftcard_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( $_GET['giftcard_id'] ) ) : '';
			$mysqli = new ec_db_admin();

			if ( isset( $_GET['ec_guest_key'] ) ) {
				$guest_key = isset( $_GET['ec_guest_key'] ) ? substr( preg_replace( '/[^A-Z]/', '', sanitize_text_field( $_GET['ec_guest_key'] ) ), 0, 30 ) : '';
				$order_row = $mysqli->get_guest_order_row( $order_id, $guest_key );
				$orderdetail_row = $mysqli->get_orderdetail_row_guest( $order_id, $orderdetail_id );
				if ( $orderdetail_row ) {
					$giftcard_id = $orderdetail_row->giftcard_id;
				}
			} else {
				$order_row = $mysqli->get_order_row_admin( $order_id );
				$orderdetail_row = $mysqli->get_orderdetail_row_guest( $order_id, $orderdetail_id );
			}

			if ( $orderdetail_row && $orderdetail_row->giftcard_id == $giftcard_id ) {

				if ( $order_row && $order_row->is_approved ) {

					global $wpdb;
					$giftcard_total = $orderdetail_row->unit_price;
					$giftcard_total = $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM ec_giftcard WHERE giftcard_id = %s", $giftcard_id ) );

					$storepageid = get_option('ec_option_storepage');
					if ( function_exists( 'icl_object_id' ) ) {
						$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
					}
					$store_page = get_permalink( $storepageid );
					if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
						$https_class = new WordPressHTTPS();
						$store_page = $https_class->makeUrlHttps( $store_page );
					}

					if ( substr_count( $store_page, '?' ) ) {
						$permalink_divider = "&";
					} else {
						$permalink_divider = "?";
					}

					$ec_orderdetail = new ec_orderdetail( $orderdetail_row );
					$email_logo_url = get_option( 'ec_option_email_logo' );

					// Get receipt
					if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_print_gift_card.php' ) ) {
						include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_print_gift_card.php';
					} else {
						include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_print_gift_card.php';
					}

				} else {

					echo wp_easycart_language()->get_text( "cart_success", "cart_giftcards_unavailable" );

				}
			} else {
				echo "No Order Found";	
			}
		}
		die();

	}
}

/**
 * Once per plugin version, make sure every product that is inactive in the store has a private
 * ec_store post. post_sync()->update() verifies ownership and relinks or recreates the post, so
 * it cannot be replaced by one set-based UPDATE on wp_posts.
 *
 * Used to load every inactive product and write ec_option_published_check only after the loop,
 * so a timeout restarted the whole loop on every request ( storefront included ). Now: only an
 * admin or cron request runs it, a short lock transient stops parallel requests doubling up, rows
 * are processed 200 at a time from a product_id cursor, and the request stops after ~10 seconds
 * and continues on the next one. The version marker is written only when the last batch is done.
 *
 * @since 6.0.0
 */
function wp_easycart_sync_inactive_product_posts() {
	global $wpdb;
	if ( ! function_exists( 'wp_easycart_post_sync' ) || ! function_exists( 'wp_easycart_language' ) ) {
		return;
	}
	if ( get_transient( 'ec_published_check_lock' ) ) {
		return;
	}
	set_transient( 'ec_published_check_lock', 1, 2 * MINUTE_IN_SECONDS );

	$batch_size = 200;
	$time_budget = 10;
	$started = microtime( true );
	$progress = get_option( 'ec_option_published_check_progress' );
	$cursor = ( is_array( $progress ) && isset( $progress['version'], $progress['cursor'] ) && EC_CURRENT_VERSION === $progress['version'] ) ? (int) $progress['cursor'] : 0;

	while ( true ) {
		$inactive_products = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, post_id, model_number, title FROM ec_product WHERE activate_in_store = 0 AND product_id > %d ORDER BY product_id ASC LIMIT %d', $cursor, $batch_size ) );
		if ( ! is_array( $inactive_products ) ) {
			$inactive_products = array();
		}
		foreach ( $inactive_products as $product ) {
			$cursor = (int) $product->product_id;
			$title = wp_easycart_language()->convert_text( $product->title );
			wp_easycart_post_sync()->update(
				'product',
				$product->product_id,
				$product->post_id,
				array(
					'post_content' => '[ec_store modelnumber="' . $product->model_number . '"]',
					'post_status' => 'private',
					'post_title' => $title,
					'post_name' => str_replace( ' ', '-', $title ),
				)
			);
		}
		if ( count( $inactive_products ) < $batch_size ) {
			break;
		}
		/* Persist after every batch so a hard timeout resumes here rather than at the last soft pause. */
		update_option( 'ec_option_published_check_progress', array( 'version' => EC_CURRENT_VERSION, 'cursor' => $cursor ), false );
		if ( ( microtime( true ) - $started ) >= $time_budget ) {
			delete_transient( 'ec_published_check_lock' );
			return;
		}
	}
	delete_option( 'ec_option_published_check_progress' );
	update_option( 'ec_option_published_check', EC_CURRENT_VERSION );
	delete_transient( 'ec_published_check_lock' );
}

/**
 * WP-Cron handler for the post-sync audit that ec_db_manager schedules at the end of the 5.9.4
 * version step ( it walks every product / category row, so it runs off the admin request ).
 *
 * @since 6.0.0
 */
add_action( 'wp_easycart_post_sync_audit', 'wp_easycart_run_post_sync_audit' );
function wp_easycart_run_post_sync_audit() {
	if ( ! function_exists( 'wp_easycart_post_sync' ) ) {
		return;
	}
	try {
		$sync = wp_easycart_post_sync();
		if ( is_object( $sync ) && method_exists( $sync, 'audit' ) ) {
			$sync->audit( true );
		}
	} catch ( \Throwable $e ) {
		//error_log( 'WP EasyCart post sync audit failed: ' . $e->getMessage() );
	}
}

add_action( 'init', 'ec_create_post_type_menu' );
function ec_create_post_type_menu() {

	// Fix, V3 upgrades missed the ec_tempcart_optionitem.session_id upgrade!
	if ( !get_option( 'ec_option_v3_fix' ) ) {
		global $wpdb;
		$wpdb->query( "INSERT INTO ec_tempcart_optionitem( tempcart_id, option_id, optionitem_id, optionitem_value ) VALUES( '999999999', '3', '3', 'test' )" );
		$tempcart_optionitem_row = $wpdb->get_row( "SELECT * FROM ec_tempcart_optionitem WHERE tempcart_id = '999999999'" );
		if ( !isset( $tempcart_optionitem_row->session_id ) ) {
			$wpdb->query( "ALTER TABLE ec_tempcart_optionitem ADD `session_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The ec_cart_id that determines the user who entered this value.'" );
		}
		update_option( 'ec_option_v3_fix', 1 );
	}

	// Update store item posts, set to private if inactive in store ( once per version, admin / cron only, batched )
	if ( ( is_admin() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) && ( ! get_option( 'ec_option_published_check' ) || get_option( 'ec_option_published_check' ) != EC_CURRENT_VERSION ) ) {
		wp_easycart_sync_inactive_product_posts();
	}

	$store_id = get_option( 'ec_option_storepage' );
	if ( $store_id ) {
		$store_slug = ec_get_the_slug( $store_id );

		$labels = array(
			'name'        => _x( 'Store Items', 'post type general name' ),
			'singular_name'   => _x( 'Store Item', 'post type singular name' ),
			'add_new'      => _x( 'Add New', 'ec_store' ),
			'add_new_item'    => __( 'Add New Store Item' ),
			'edit_item'     => __( 'Edit Store Item' ),
			'new_item'      => __( 'New Store Item' ),
			'all_items'     => __( 'All Store Items' ),
			'view_item'     => __( 'View Store Item' ),
			'search_items'    => __( 'Search Store Items' ),
			'not_found'     => __( 'No store items found' ),
			'not_found_in_trash' => __( 'No store items found in the Trash' ), 
			'parent_item_colon' => '',
			'menu_name'     => 'Store Items'
		);
		$args = array(
			'labels'    	=> $labels,
			'description' 		=> 'Used for the EasyCart Store',
			'public' 			=> true,
			'has_archive' 		=> false,
			'show_ui' 			=> true,
			'show_in_nav_menus' => true,
			'show_in_menu' 		=> apply_filters( 'wp_easycart_show_cpt_in_menu', false ),
			'supports'			=> array( 'title', 'page-attributes', 'author', 'editor', 'post-formats', 'excerpt', 'thumbnail' ),
			'taxonomies'		=> array( 'post_tag' ),
			'rewrite'			=> array( 'slug' => $store_slug, 'with_front' => false, 'page' => false ),
		);
		register_post_type( 'ec_store', $args );

		if ( '' !== $store_slug ) {
			global $wp_rewrite;
			$wp_rewrite->add_permastruct( 'ec_store', $store_slug . '/%ec_store%/', true, 1 );
			add_rewrite_rule( '^' . $store_slug . '/([^/]*)/?$', 'index.php?ec_store=$matches[1]', 'top');
		}
	}
}

function ec_get_the_slug( $id = null ) {
	if ( empty( $id ) ) {
		global $post;
		if ( empty( $post ) ) {
			return '';
		}
		$id = $post->ID;
	}
	$path = get_page_uri( $id );

	if ( ! is_string( $path ) || '' === $path ) {
		$home_url = parse_url( site_url() );
		$home_path = isset( $home_url['path'] ) ? $home_url['path'] : '';

		$store_url = parse_url( get_permalink( $id ) );
		$store_path = isset( $store_url['path'] ) ? $store_url['path'] : '';

		$path = ( strlen( $home_path ) == 0 || $home_path == "/" ) ? $store_path : str_replace( $home_path, "", $store_path );
	}
	$path = trim( $path, '/' );
	return $path;
}

if ( ! function_exists( 'wp_easycart_store_post_link' ) ) {
	/**
	 * The live address of a store post ( a product, category, menu level or manufacturer page ).
	 *
	 * The cart, the mini cart, product images, the category widget and the store menu linked to the post's stored guid. That
	 * is written once, when the post is made, so it kept the store page's old slug after the page was renamed ( the cart
	 * linked to /browse-store/<product>/ while the store linked to /store/<product>/ ). The store listing already used
	 * get_permalink(); now every link does.
	 *
	 * @since 6.0.2
	 * @param int    $post_id  The ec_store post.
	 * @param string $fallback The classic link ( ?model_number=, ?menuid= … ), used when there is no post.
	 * @return string
	 */
	function wp_easycart_store_post_link( $post_id, $fallback = '' ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			$link = get_permalink( $post_id );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return (string) $fallback;
	}
}

if ( ! function_exists( 'wp_easycart_show_payment_pending_notice' ) ) {
	/**
	 * Show the "payment is still being processed" notice ( language ec_errors › payment_processing ) for an order that is
	 * not approved: on the receipt email, the order confirmation page and My Account's order details. The other order
	 * notices ( payment failed, refunded, payment received ) always show.
	 *
	 * @since 6.0.3 Settings › Email › Order emails › Payment pending notice ( GitHub #128 ); stores that take bank transfer,
	 *              invoice or pay on pickup found it confusing.
	 * @param object|null $order The order the notice is for.
	 * @return bool
	 */
	function wp_easycart_show_payment_pending_notice( $order = null ) {
		$show = '0' !== (string) get_option( 'ec_option_show_payment_pending_notice', '1' );
		/**
		 * Filter whether an order that is not approved shows the payment pending notice.
		 *
		 * @since 6.0.3
		 * @param bool        $show  The store's setting.
		 * @param object|null $order The order.
		 */
		return (bool) apply_filters( 'wp_easycart_show_payment_pending_notice', $show, $order );
	}
}

if ( ! function_exists( 'wp_easycart_prime_store_posts' ) ) {
	/**
	 * Load several store posts in one query before their links are built ( the menu, the category widget, the cart ).
	 *
	 * @since 6.0.2
	 * @param array $post_ids Post ids; zeros and repeats are skipped.
	 */
	function wp_easycart_prime_store_posts( $post_ids ) {
		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $post_ids ) ) ) );
		if ( $post_ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $post_ids, false, false );
		}
	}
}

add_action( 'wp', 'ec_force_page_type' );
function ec_force_page_type() {
	global $wp_query, $post_type;

	/* 6.0.2: filter wp_easycart_store_item_as_page ( default true ). The Elementor templates module answers false when an
	 * Elementor Pro Theme Builder template with a WP EasyCart condition draws this product, category or manufacturer page,
	 * so it stays a store item for Elementor Pro. Asked only for store items drawn as pages; nothing changes otherwise. */
	if ( $post_type == 'ec_store' && !get_option( 'ec_option_use_custom_post_theme_template' ) && apply_filters( 'wp_easycart_store_item_as_page', true ) ) {
		$wp_query->is_page = true;
		$wp_query->is_single = false;
		$wp_query->query_vars['post_type'] = "page";
		if ( isset( $wp_query->post ) )
			$wp_query->post->post_type = "page";
	}
}

/* 6.0.3: a store item ( product, category, manufacturer page ) is drawn with the store page's theme page template. Until 6.0.2
 * this included that file on template_redirect ( priority 1 ) and exited, so no later template_redirect callback ran on those
 * pages ( canonical redirects, page caches, Elementor's frontend set-up, other plugins ) and the template ran inside a function,
 * without WordPress's globals. It now hands the file to WordPress's own template loader, late enough that it still wins over
 * other template_include filters, as the exit did. */
add_filter( 'template_include', 'ec_fix_store_template', 999 );
function ec_fix_store_template( $template = '' ) {
	global $wp;
	$custom_post_types = array("ec_store");

	/* 6.0.2: the store page's theme template does not take over a store item that an Elementor Pro Theme Builder template
	 * draws ( filter wp_easycart_store_item_as_page, see ec_force_page_type() ). */
	if ( isset( $wp->query_vars["post_type"] ) && in_array( $wp->query_vars["post_type"], $custom_post_types ) && apply_filters( 'wp_easycart_store_item_as_page', true ) ) {
		$store_template = get_post_meta( get_option( 'ec_option_storepage' ), "_wp_page_template", true );
		if ( is_string( $store_template ) && $store_template != "" && $store_template != "default" && 0 === validate_file( $store_template ) ) {
			if ( file_exists( get_template_directory() . "/" . $store_template ) ) {
				return get_template_directory() . "/" . $store_template;
			}
		}
	}
	return $template;
}

add_action( 'wp_easycart_square_renew_token', 'wp_easycart_square_renew_token' );
function wp_easycart_square_renew_token() {
	if ( get_option( 'ec_option_payment_process_method' ) == 'square' ) {
		if ( class_exists( 'ec_square' ) ) {
			$square = new ec_square();
			$square->renew_token();
		}
	} else {
		wp_clear_scheduled_hook( 'wp_easycart_square_renew_token' );
	}
}

/* 6.0.2: usage data, only for stores that chose to share it ( ec_option_allow_tracking '1' ). The events, their queue and the
   sender are wp_easycart_admin_tracking ( admin/inc/wp_easycart_admin_tracking.php, loaded in wp-admin while sharing is on ).
   These hooks reach it from WP-Cron, where the waiting batch is posted ( never on a shopper's request ), and follow the setting
   wherever it is changed ( the Allow notice, Settings, the setup wizard ): turned on, the store's setup snapshot is queued once;
   turned off, the waiting events, the install id and the WP-Cron event go. */
add_action( 'wp_easycart_tracking_flush', 'wp_easycart_tracking_flush' );
add_action( 'wp_easycart_tracking_checkin', 'wp_easycart_tracking_checkin' );
add_action( 'wpeasycart_order_inserted', 'wp_easycart_tracking_first_order', 99 );
add_action( 'add_option_ec_option_allow_tracking', 'wp_easycart_tracking_option_added', 10, 2 );
add_action( 'update_option_ec_option_allow_tracking', 'wp_easycart_tracking_option_changed', 10, 2 );

/** Loads the usage data class and its event hooks; true once they are there. */
function wp_easycart_tracking_load() {
	if ( ! class_exists( 'wp_easycart_admin_tracking' ) ) {
		include_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_tracking.php';
	}
	if ( ! class_exists( 'wp_easycart_admin_tracking' ) ) {
		return false;
	}
	wp_easycart_admin_tracking::instance();
	return true;
}

/** WP-Cron: post the waiting usage events ( or forget them when sharing was turned off ). */
function wp_easycart_tracking_flush() {
	if ( wp_easycart_tracking_load() ) {
		wp_easycart_admin_tracking::instance()->flush();
	}
}

/** WP-Cron, weekly ( 6.0.3 ): the usage check-in ( how the store is set up, as keys, flags and bands ). */
function wp_easycart_tracking_checkin() {
	if ( '1' !== (string) get_option( 'ec_option_allow_tracking' ) ) {
		wp_clear_scheduled_hook( 'wp_easycart_tracking_checkin' );
		return;
	}
	if ( wp_easycart_tracking_load() ) {
		wp_easycart_admin_tracking::instance()->checkin();
	}
}

/** The store's first order, once, for a store that shares usage data ( 6.0.3; a getting-started step ). */
function wp_easycart_tracking_first_order() {
	if ( '1' !== (string) get_option( 'ec_option_allow_tracking' ) ) {
		return;
	}
	$sent = get_option( 'wp_easycart_tracking_milestones', array() );
	if ( is_array( $sent ) && isset( $sent['first_order'] ) ) {
		return;
	}
	if ( wp_easycart_tracking_load() ) {
		wp_easycart_admin_tracking::instance()->milestone( 'first_order' );
	}
}

function wp_easycart_tracking_option_added( $option, $value ) {
	wp_easycart_tracking_option_changed( '', $value );
}

function wp_easycart_tracking_option_changed( $old_value, $value ) {
	$was = is_scalar( $old_value ) && '1' === (string) $old_value;
	$now = is_scalar( $value ) && '1' === (string) $value;
	if ( $was === $now || ! wp_easycart_tracking_load() ) {
		return;
	}
	if ( $now ) {
		do_action( 'wpeasycart_admin_usage_tracking_accepted' );
	} else {
		wp_easycart_admin_tracking::forget();
	}
}

/////////////////////////////////////////////////////////////////////
//HELPER FUNCTIONS
/////////////////////////////////////////////////////////////////////
//Helper Function, Get URL
function ec_get_url() {
 if ( isset( $_SERVER['HTTPS'] ) )
	$protocol = "https";
 else
	$protocol = "http";

 $baseurl = "://" . sanitize_text_field( $_SERVER['HTTP_HOST'] );
 $strip = explode( "/wp-admin", sanitize_text_field( $_SERVER['REQUEST_URI'] ) );
 $folder = $strip[0];
 return $protocol . $baseurl . $folder;
}

function ec_setup_hooks() {
	$GLOBALS['ec_hooks'] = array();
}

function ec_add_hook( $call_location, $function_name, $args = array(), $priority = 1 ) {
	if ( !isset( $GLOBALS['ec_hooks'][$call_location] ) )
		$GLOBALS['ec_hooks'][$call_location] = array();

	$GLOBALS['ec_hooks'][$call_location][] = array( $function_name, $args, $priority );
}

function ec_call_hook( $hook_array, $class_args ) {
	$hook_array[0]( $hook_array[1], $class_args );
}

function ec_dwolla_verify_signature( $proposedSignature, $checkoutId, $amount ) {
	$apiSecret = get_option( 'ec_option_dwolla_thirdparty_secret' );
	$amount = number_format( $amount, 2 );
	$signature = hash_hmac("sha1", "{$checkoutId}&{$amount}", $apiSecret);

	return $signature == $proposedSignature;
}

add_filter( 'wp_nav_menu_items', 'ec_custom_cart_in_menu', 10, 2 );
function ec_custom_cart_in_menu ( $items, $args ) {
	$ids = explode( '***', get_option( 'ec_option_cart_menu_id' ) );
	$menu = wp_get_nav_menu_object( $args->menu );
	if ( get_option( 'ec_option_show_menu_cart_icon' ) && ( in_array( substr( $args->menu_id, 0, -5 ), $ids ) || in_array( $args->theme_location, $ids ) || in_array( 'term_' . $menu->term_id, $ids ) ) ) {
		$items .= '<li class="ec_menu_mini_cart" data-nonce="' . wp_create_nonce( 'wp-easycart-mini-cart' ) . '"></li>';
	}
	return $items;
}

function wpeasycart_activation_redirect( $plugin ) {
	if ( $plugin == plugin_basename( __FILE__ ) ) {
		wp_redirect( admin_url( 'admin.php?page=wp-easycart-settings' ) );
		die();
	}
}
add_action( 'activated_plugin', 'wpeasycart_activation_redirect' );

add_action( 'wpeasycart_abandoned_cart_automation', 'wpeasycart_send_abandoned_cart_emails' );
function wpeasycart_send_abandoned_cart_emails() {
	global $wpdb;
	$abandoned_carts = $wpdb->get_results( $wpdb->prepare( "SELECT ec_tempcart.tempcart_id FROM ec_tempcart, ec_tempcart_data WHERE ec_tempcart.abandoned_cart_email_sent = 0 AND ec_tempcart.session_id = ec_tempcart_data.session_id AND ec_tempcart_data.email != '' AND ec_tempcart.last_changed_date < DATE_SUB( NOW(), INTERVAL %d DAY ) GROUP BY ec_tempcart.session_id", get_option( 'ec_option_abandoned_cart_days' ) ) );
	foreach ( $abandoned_carts as $abandoned_cart ) {
		wpeasycart_send_abandoned_cart_email( $abandoned_cart->tempcart_id );
	}
}

function wpeasycart_send_abandoned_cart_email( $tempcart_id ) {
	global $wpdb;
	$email_logo_url = get_option( 'ec_option_email_logo' );
	$store_page_id = get_option( 'ec_option_storepage' );
	$cart_page_id = get_option( 'ec_option_cartpage' );
	if ( function_exists( 'icl_object_id' ) ) {
		$store_page_id = icl_object_id( $store_page_id, 'page', true, ICL_LANGUAGE_CODE );
		$cart_page_id = icl_object_id( $cart_page_id, 'page', true, ICL_LANGUAGE_CODE );
	}
	$store_page = get_permalink( $store_page_id );
	$cart_page = get_permalink( $cart_page_id );
	if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
		$https_class = new WordPressHTTPS();
		$store_page = $https_class->makeUrlHttps( $store_page );
		$cart_page = $https_class->makeUrlHttps( $cart_page );
	}
	if ( substr_count( $cart_page, '?' ) ) {
		$permalink_divider = "&";
	} else {
		$permalink_divider = "?";
	}

	$tempcart_item = $wpdb->get_row( $wpdb->prepare( "SELECT ec_tempcart.session_id, ec_tempcart.tempcart_id, ec_tempcart.product_id, ec_tempcart.quantity, ec_tempcart_data.translate_to, ec_tempcart_data.billing_first_name, ec_tempcart_data.billing_last_name, ec_tempcart_data.email, ec_product.title FROM ec_tempcart LEFT JOIN ec_tempcart_data ON ec_tempcart_data.session_id = ec_tempcart.session_id LEFT JOIN ec_product ON ec_product.product_id = ec_tempcart.product_id WHERE ec_tempcart.tempcart_id = %d ORDER BY ec_tempcart.session_id, last_changed_date", $tempcart_id ) );
	if ( $tempcart_item->translate_to != '' ) {
		wp_easycart_language()->set_language( $tempcart_item->translate_to );
	}
	$tempcart_rows = $wpdb->get_results( $wpdb->prepare( "SELECT ec_product.*, ec_tempcart.quantity AS tempcart_quantity, ec_tempcart.optionitem_id_1, ec_tempcart.optionitem_id_2, ec_tempcart.optionitem_id_3, ec_tempcart.optionitem_id_4, ec_tempcart.optionitem_id_5 FROM ec_tempcart, ec_product WHERE ec_product.product_id = ec_tempcart.product_id AND ec_tempcart.session_id = %s", $tempcart_item->session_id ) );

	$to = $tempcart_item->email;
	$subject = wp_easycart_language()->get_text( 'ec_abandoned_cart_email', 'email_title' );

	$ec_load_key = wpeasycart_session()->get_abandoned_cart_key( $tempcart_item->session_id, $tempcart_item->email );
	$ec_load_url = $cart_page . $permalink_divider . 'ec_load_tempcart=' . rawurlencode( $tempcart_item->session_id ) . '&ec_load_email=' . rawurlencode( $tempcart_item->email ) . '&ec_load_key=' . rawurlencode( $ec_load_key );

	ob_start();
	if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_abandoned_cart_email.php' ) )	
		include EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_abandoned_cart_email.php';	
	else
		include EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_abandoned_cart_email.php';
	$message = ob_get_clean();

	/*
	 * 6.0.2: sent like the reminder sequence ( ec_email, 'marketing' channel ), so every cart reminder comes from the
	 * Account emails address, which also takes the replies, and shows in the email log. It used the Order emails address.
	 */
	ec_email::send(
		$to,
		$subject,
		$message,
		array(
			'type'     => 'abandoned_cart',
			'order_id' => 0,
			'channel'  => 'marketing',
		)
	);
	$wpdb->query( $wpdb->prepare( "UPDATE ec_tempcart SET abandoned_cart_email_sent = 1 WHERE ec_tempcart.session_id = %s", $tempcart_item->session_id ) );

}

function is_wpeasycart_cart() {
	global $is_wpec_cart;
	return $is_wpec_cart;
}

function wp_easycart_load_amazon_js() {
	if ( get_option( 'ec_option_amazonpay_enable' ) ) {
		if( 'EU' == get_option( 'ec_option_amazonpay_region' ) ) {
			wp_enqueue_script( 'wpeasycart_amazonpay_js', 'https://static-eu.payments-amazon.com/checkout.js', array( 'jquery' ), EC_CURRENT_VERSION, false );
		} else if( 'JP' == get_option( 'ec_option_amazonpay_region' ) ) {
			wp_enqueue_script( 'wpeasycart_amazonpay_js', 'https://static-fe.payments-amazon.com/checkout.js', array( 'jquery' ), EC_CURRENT_VERSION, false );
		} else {
			wp_enqueue_script( 'wpeasycart_amazonpay_js', 'https://static-na.payments-amazon.com/checkout.js', array( 'jquery' ), EC_CURRENT_VERSION, false );
		}
		add_filter( 'sgo_js_async_exclude', 'wp_easycart_exclude_from_siteground', 10, 1 );
	}
}

function wp_easycart_check_for_shortcode( $posts ) {
	global $is_wpec_store, $is_wpec_cart, $is_wpec_account, $is_wpec_product_shortcode;
	$is_wpec_store = false;
	$is_wpec_cart = false;
	$is_wpec_account = false;
	$is_wpec_product_shortcode = false;

	if ( empty( $posts ) )
		return $posts;

	$found = false;

	foreach ( $posts as $post ) {
		if ( $post->ID == get_option( 'ec_option_storepage' ) || $post->post_type == "ec_store" ) {
			$found = true;
			$is_wpec_store = true;
			break;
		}
	}

	foreach ( $posts as $post ) {
		if ( stripos( $post->post_content, '[ec_cart' ) !== false ) {
			$is_wpec_cart = true;
			break;
		}
	}

	foreach ( $posts as $post ) {
		if ( stripos( $post->post_content, '[ec_account' ) !== false ) {
			$is_wpec_account = true;
			break;
		}
	}

	foreach ( $posts as $post ) {
		if ( stripos( $post->post_content, '[ec_product' ) !== false ) {
			$is_wpec_product_shortcode = true;
			break;
		}
	}

	/* 6.0.2: members-only content ( [ec_membership], [ec_membership_alt] ) is drawn for each visitor: known before the page
	 * draws, so the no-cache headers go out with it ( the shortcodes also keep page caches away while they draw ). */
	if ( ! is_admin() && ! did_action( 'template_redirect' ) ) {
		foreach ( $posts as $post ) {
			if ( stripos( $post->post_content, '[ec_membership' ) !== false ) {
				wp_easycart_product_details_no_cache();
				add_action( 'template_redirect', 'wp_easycart_send_nocache_headers' );
				break;
			}
		}
	}

	if ( $is_wpec_cart || $is_wpec_account ) {
		add_action( 'wp_enqueue_scripts', 'wp_easycart_load_cart_js' );

	} else if ( $is_wpec_store || $is_wpec_product_shortcode || get_option( 'ec_option_restrict_store' ) ) {
		add_action( 'wp_enqueue_scripts', 'wp_easycart_load_grecaptcha_js' );
	}

	if ( get_option( 'ec_option_amazonpay_enable' ) ) {
		add_action( 'wp_enqueue_scripts', 'wp_easycart_load_amazon_js' );
	}

	if ( $found ) {
		add_filter( 'jetpack_enable_open_graph', '__return_false' ); 
	}

	if ( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ) {
		$found = false;
		foreach ( $posts as $post ) {
			/* 6.0.2: also pages built from product, add to cart and account shortcodes ( their buttons send Pixel events ). */
			if ( $post->post_type == "ec_store" ||
				stripos( $post->post_content, '[ec_store' ) !== false ||
				stripos( $post->post_content, '[ec_cart' ) !== false ||
				stripos( $post->post_content, '[ec_product' ) !== false ||
				stripos( $post->post_content, '[ec_addtocart' ) !== false ||
				stripos( $post->post_content, '[ec_account' ) !== false
			) {
				$found = true;
				break;
			}
		}

		if ( $found && apply_filters( 'wpeasycart_allow_pixel_code', true ) ) {
			add_action( 'wp_head', 'wp_easycart_init_facebook_pixel' );
		}
	}

	return $posts;
}

/**
 * The Meta Pixel base code ( wp_head on store pages, or every page with "Load the Pixel on every page" ). Since 6.0.2
 * it comes from wp_easycart_meta::print_base_code(): consent, Limited Data Use, advanced matching, event IDs.
 */
function wp_easycart_init_facebook_pixel() {
	if ( class_exists( 'wp_easycart_meta' ) ) {
		wp_easycart_meta::print_base_code();
	}
}
add_action( 'the_posts', 'wp_easycart_check_for_shortcode' );

function wp_easycart_restrict_access() {
	/* 6.0.2: a lock protects its own page; an archive or blog list whose first post is locked is not that page. */
	if ( ! is_singular() ) {
		return;
	}
	$product_restrict = get_post_meta( get_the_ID(), 'wpeasycart_restrict_product_id', true );
	$user_restrict = get_post_meta( get_the_ID(), 'wpeasycart_restrict_user_id', true );
	$role_restrict = get_post_meta( get_the_ID(), 'wpeasycart_restrict_role_id', true );

	$redirect_page = get_post_meta( get_the_ID(), 'wpeasycart_restrict_redirect_url', true );
	$redirect_page_auth = get_post_meta( get_the_ID(), 'wpeasycart_restrict_redirect_url_auth', true );
	$redirect_page_not_auth = get_post_meta( get_the_ID(), 'wpeasycart_restrict_redirect_url_not_auth', true );

	/*
	 * 6.0.0: the meta value is post data, not trusted input ( any user who can edit the post can write it through
	 * XML-RPC / REST ), so the product and user ids are reduced to positive integers ( bound as placeholders ). 6.0.2: the
	 * older comma list and single values count as a lock too ( only arrays did ).
	 */
	$product_restrict_ids = array();
	foreach ( ( is_array( $product_restrict ) ? $product_restrict : explode( ',', (string) $product_restrict ) ) as $product_restrict_id ) {
		if ( is_scalar( $product_restrict_id ) && (int) $product_restrict_id > 0 ) {
			$product_restrict_ids[] = (int) $product_restrict_id;
		}
	}
	$product_restrict_ids = array_values( array_unique( $product_restrict_ids ) );
	$user_restrict_ids = array();
	foreach ( ( is_array( $user_restrict ) ? $user_restrict : explode( ',', (string) $user_restrict ) ) as $user_restrict_id ) {
		if ( is_scalar( $user_restrict_id ) && (int) $user_restrict_id > 0 ) {
			$user_restrict_ids[] = (int) $user_restrict_id;
		}
	}
	$role_restrict_levels = array();
	foreach ( ( is_array( $role_restrict ) ? $role_restrict : array( $role_restrict ) ) as $role_restrict_level ) {
		if ( is_scalar( $role_restrict_level ) && '' !== (string) $role_restrict_level && '0' !== (string) $role_restrict_level ) {
			$role_restrict_levels[] = (string) $role_restrict_level;
		}
	}

	$is_restricted = ( count( $product_restrict_ids ) > 0 || count( $user_restrict_ids ) > 0 || count( $role_restrict_levels ) > 0 );
	if ( ! $is_restricted ) {
		return;
	}

	/* 6.0.2: a locked page is drawn for each visitor, so no page cache or CDN may keep it ( a member's copy was served to
	 * everyone, skipping the lock ). */
	if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
		wp_easycart_product_details_no_cache();
	}
	if ( ! headers_sent() ) {
		nocache_headers();
	}

	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	/*
	 * 6.0.2: the lock applies whether or not its redirects are filled in ( without any the page was open to everyone ), and a
	 * signed-out visitor is never let in ( with only a "Logged In" redirect set they were ). Where each visitor goes:
	 * - signed out: "Not Logged In + Not Authorized", else the account sign-in page;
	 * - signed in and allowed: "Logged In + Authorized" when set, else the page;
	 * - signed in, not allowed: "Logged In + Not Authorized", else "Not Logged In + Not Authorized", else the store page.
	 * The merchant's own addresses may be on another site ( wp_redirect(), as before ); the store's fallbacks stay on this one.
	 */
	$user_id = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ) ? (int) $GLOBALS['ec_user']->user_id : 0;
	if ( $user_id <= 0 ) {
		if ( '' != $redirect_page ) {
			wp_redirect( $redirect_page ); die(); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the merchant's own address from the page's Limit Access box ( protected meta ).
		}
		wp_easycart_restrict_access_fallback( wpeasycart_links()->get_account_page( 'login' ) );
	}

	$is_allowed = true;

	if ( count( $product_restrict_ids ) > 0 ) {
		/* 6.0.2: one membership rule ( D5 ), as the [ec_membership] shortcodes: a subscription while Active or an installment plan
		 * paid in full; any other product once an order holding it is paid. */
		if ( ! wp_easycart_user_has_membership( $user_id, $product_restrict_ids ) ) {
			$is_allowed = false;
		}
	}

	if ( count( $user_restrict_ids ) > 0 && ! in_array( $user_id, $user_restrict_ids, true ) ) {
		$is_allowed = false;
	}

	if ( count( $role_restrict_levels ) > 0 && ! in_array( (string) $GLOBALS['ec_user']->user_level, $role_restrict_levels, true ) ) {
		$is_allowed = false;
	}

	if ( $is_allowed ) {
		if ( '' != $redirect_page_auth ) {
			wp_redirect( $redirect_page_auth ); die(); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the merchant's own address from the page's Limit Access box ( protected meta ).
		}
		return;
	}

	if ( '' != $redirect_page_not_auth ) {
		wp_redirect( $redirect_page_not_auth ); die(); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the merchant's own address from the page's Limit Access box ( protected meta ).
	}
	if ( '' != $redirect_page ) {
		wp_redirect( $redirect_page ); die(); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the merchant's own address from the page's Limit Access box ( protected meta ).
	}
	$store_page = get_permalink( (int) get_option( 'ec_option_storepage' ) );
	wp_easycart_restrict_access_fallback( $store_page ? $store_page : home_url( '/' ) );
}
add_action( 'template_redirect', 'wp_easycart_restrict_access' );

/**
 * Sends a visitor a locked page refused to one of the store's own pages ( 6.0.2 ). Never to the locked page itself ( the
 * account or store page may be the one locked ): then the home page, and when that is the locked page too, a plain 403.
 *
 * @since 6.0.2
 *
 * @param string $url Where to.
 */
function wp_easycart_restrict_access_fallback( $url ) {
	$here = explode( '?', (string) get_permalink( get_the_ID() ) );
	$here = untrailingslashit( $here[0] );
	foreach ( array( (string) $url, home_url( '/' ) ) as $to ) {
		$path = explode( '?', $to );
		if ( '' !== $to && untrailingslashit( $path[0] ) !== $here ) {
			wp_safe_redirect( $to );
			exit;
		}
	}
	wp_die( esc_html__( 'You do not have access to this page.', 'wp-easycart' ), '', array( 'response' => 403 ) );
}

/**
 * The page-lock meta keys are written only by the plugin's own meta box ( update_post_meta, capability and nonce
 * checked there ). Marking them protected keeps every other write path out: the Custom Fields box, XML-RPC
 * ( wp.newPost / wp.editPost custom_fields ) and the REST meta endpoint all refuse protected keys for users without
 * the raw edit_post_meta capability, so an author cannot set a restriction or redirect on their own post.
 *
 * @since 6.0.0
 */
function wp_easycart_protect_restrict_meta( $protected, $meta_key, $meta_type ) {
	if ( 'post' === $meta_type && 0 === strpos( (string) $meta_key, 'wpeasycart_restrict_' ) ) {
		return true;
	}
	return $protected;
}
add_filter( 'is_protected_meta', 'wp_easycart_protect_restrict_meta', 10, 3 );

add_action( 'wp_head', 'wp_easycart_show_404_help' );
function wp_easycart_show_404_help( ) {
	/*
	 * 6.0.2: a 404 no longer publishes a new "Store" page when the store page is also the site's front page: any visitor's 404
	 * did that ( and flushed the rewrite rules ). A store manager now sees a notice on the EasyCart screens and a Store Status
	 * row, each with a Create Store page button ( wp_easycart_admin_store_front_page ).
	 */
	// Many times we see the user hit the store page with a 404 and can usually be fixed with a flush ( store managers only ).
	if ( wp_easycart_404_check() ) {
		echo '<div style="position:relative; top:0; left:0; width:100%; background:red; padding:15px; text-align:center; color:#FFF; font-size:16px; font-weight:bold;">It appears your product is not linking correctly. Refreshing this page may automatically fix the issue, but lots of things can cause this, but we will help you out. Try reading here: <a href="https://docs.wpeasycart.com/docs/how-to-guides/how-to-fix-404-errors-on-cart-pages/" target="_blank" style="color:#CCC !important;">Help on 404 Errors</a> and if none of these options help, contact us here: <a href="https://www.wpeasycart.com/contact-information/" target="_blank" style="color:#CCC !important;">Contact Us</a>.</div>';
		flush_rewrite_rules();
	}
}
function wp_easycart_404_check() {
	if ( is_404() && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) && !is_admin() ) {
		$url = str_replace( "https://", "", str_replace( "http://", "", get_site_url() . strtok( sanitize_text_field( $_SERVER["REQUEST_URI"] ), '?' ) ) );
		$store_page_id = get_option( 'ec_option_storepage' );
		$store_page = get_permalink( $store_page_id );
		$store_url = str_replace( "https://", "", str_replace( "http://", "", $store_page ) ); 
		if ( strpos( $url, $store_url ) !== false ) {
			return true;
		}
	}
	return false;
}

function wp_easycart_maybe_add_toolbar_link( $wp_admin_bar ) {

	global $wpdb, $post;
	if ( !is_admin() && isset( $_GET['model_number'] ) ) {
		$product = $wpdb->get_row( $wpdb->prepare( "SELECT product_id FROM ec_product WHERE model_number = %s", sanitize_text_field( $_GET['model_number'] ) ) );
		if ( $product ) {
			$args = array(
				'id' => 'wpeasycart_product',
				'title' => 'Edit Product',
				'href' => get_admin_url() . "admin.php?page=wp-easycart-products&subpage=products&product_id=" . $product->product_id . "&ec_admin_form_action=edit",
				'meta' => array(
					'target' => '_self',
					'class' => 'wp-easycart-toolbar-edit',
					'title' => 'Edit Product'
				)
			);
			$wp_admin_bar->add_node( $args );
		}
	} else if ( !is_admin() && isset( $post ) && is_object( $post ) && ( $post->post_type == "ec_store" || $post->post_type == "page" ) ) {
		$id = $post->ID;
		$product = $wpdb->get_row( $wpdb->prepare( "SELECT product_id FROM ec_product WHERE post_id = %d", $id ) );
		if ( $product ) {
			$args = array(
				'id' => 'wpeasycart_product',
				'title' => 'Edit Product',
				'href' => get_admin_url() . "admin.php?page=wp-easycart-products&subpage=products&product_id=" . $product->product_id . "&ec_admin_form_action=edit",
				'meta' => array(
					'target' => '_self',
					'class' => 'wp-easycart-toolbar-edit',
					'title' => 'Edit Product'
				)
			);
			$wp_admin_bar->add_node( $args );
		}
	}
}
add_action( 'admin_bar_menu', 'wp_easycart_maybe_add_toolbar_link', 999 );

/* 6.0.2: WordPress → store password and email copies moved to wp_easycart_wordpress_users ( password_reset, wp_set_password,
   profile_update ). The old wp_pre_insert_user_data hook is gone: it saw only WordPress's hash, so the store accounts it made could
   never sign in; WordPress-login mode makes a store account on the first store visit instead. */

function wp_easycart_record_user_login( $email ) {
	if ( is_admin() ) {
		return;
	}
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET last_login = NOW() WHERE email = %s', sanitize_email( $email ) ) );
}
add_action( 'wpeasycart_login_success', 'wp_easycart_record_user_login' );

function wp_easycart_escape_html( $text ) {
	if ( '' == $text ) {
		return $text;
	}
	/* Initial list of tags from https://wp-mix.com/allowed-html-tags-wp_kses/. */
	$allowedposttags = array();
	$allowed_atts = array(
		'align'   => array(),
		'class'   => array(),
		'type'    => array(),
		'id'     => array(),
		'dir'    => array(),
		'lang'    => array(),
		'style'   => array(),
		'xml:lang'  => array(),
		'src'    => array(),
		'alt'    => array(),
		'href'    => array(),
		'rel'    => array(),
		'rev'    => array(),
		'target'   => array(),
		'novalidate' => array(),
		'type'    => array(),
		'value'   => array(),
		'name'    => array(),
		'tabindex'  => array(),
		'action'   => array(),
		'method'   => array(),
		'for'    => array(),
		'width'   => array(),
		'height'   => array(),
		'data'    => array(),
		'title'   => array(),
		'pseudo' => array(),
		'preload' => array(),
		'controls' => array(),
	);
	$allowedposttags['form']   = $allowed_atts;
	$allowedposttags['label']  = $allowed_atts;
	$allowedposttags['input']  = $allowed_atts;
	$allowedposttags['textarea'] = $allowed_atts;
	$allowedposttags['blockquote'] = $allowed_atts;
	$allowedposttags['figure'] = $allowed_atts;
	$allowedposttags['figcaption'] = $allowed_atts;
	$allowedposttags['iframe']  = $allowed_atts;
	$allowedposttags['audio']  = $allowed_atts;
	$allowedposttags['video']  = $allowed_atts;
	$allowedposttags['source']  = $allowed_atts;
	$allowedposttags['style']  = $allowed_atts;
	$allowedposttags['strong']  = $allowed_atts;
	$allowedposttags['small']  = $allowed_atts;
	$allowedposttags['table']  = $allowed_atts;
	$allowedposttags['span']   = $allowed_atts;
	$allowedposttags['abbr']   = $allowed_atts;
	$allowedposttags['code']   = $allowed_atts;
	$allowedposttags['pre']   = $allowed_atts;
	$allowedposttags['div']   = $allowed_atts;
	$allowedposttags['img']   = $allowed_atts;
	$allowedposttags['h1']    = $allowed_atts;
	$allowedposttags['h2']    = $allowed_atts;
	$allowedposttags['h3']    = $allowed_atts;
	$allowedposttags['h4']    = $allowed_atts;
	$allowedposttags['h5']    = $allowed_atts;
	$allowedposttags['h6']    = $allowed_atts;
	$allowedposttags['ol']    = $allowed_atts;
	$allowedposttags['ul']    = $allowed_atts;
	$allowedposttags['li']    = $allowed_atts;
	$allowedposttags['em']    = $allowed_atts;
	$allowedposttags['hr']    = $allowed_atts;
	$allowedposttags['br']    = $allowed_atts;
	$allowedposttags['tr']    = $allowed_atts;
	$allowedposttags['td']    = $allowed_atts;
	$allowedposttags['dl']    = $allowed_atts;
	$allowedposttags['dt']    = $allowed_atts;
	$allowedposttags['p']    = $allowed_atts;
	$allowedposttags['a']    = $allowed_atts;
	$allowedposttags['b']    = $allowed_atts;
	$allowedposttags['i']    = $allowed_atts;
	return wp_kses( $text, $allowedposttags );
}
