<?php
/**
 * WP EasyCart — Cart Links (FREE core).
 *
 * A Cart Link is a saved, shareable URL ( ?ec_cart_apply=TOKEN ) that fills the
 * shopper's cart with pre-selected products / quantities / variants / modifier
 * values, optionally applies coupon codes, and redirects — built by admins
 * under Marketing (capability: wpec_marketing).
 *
 * This file is the storefront resolver + shared CRUD. Resolution lives in the
 * FREE plugin on purpose: links printed on packaging or in campaigns must keep
 * working even if a PRO license lapses. PRO only gates *builder* features
 * (multi-product, modifier preselects, coupons, expiry/limits, QR, stats).
 *
 * Loaded from ec_config.php ( inc/classes/core/class-wp-easycart-cart-link.php ).
 *
 * Codes stored on a link may be legacy coupons ( ec_promocode ) OR Offers v2
 * codes ( ec_offer_code ) — merchants have one or both depending on store age.
 * apply_codes() mirrors wp_easycart_apply_query_coupon(): Offers v2 gets the
 * first attempt at each code, legacy coupons are the fallback ( single slot ).
 *
 * Failure posture: a marketing link never 404s. Anything invalid at visit time
 * (inactive product, deleted option, expired coupon) is skipped; whatever is
 * still valid is added and the shopper lands on the cart.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_cart_link {

	const TOKEN_LENGTH = 10;

	/* ------------------------------------------------------------------ */
	/* CRUD (used by the Marketing manager + product quick-create)         */
	/* ------------------------------------------------------------------ */

	public static function generate_token() {
		global $wpdb;
		do {
			/* No ambiguous chars: token may be read aloud off printed material. */
			$token = strtoupper( substr( str_replace( array( '0', 'O', '1', 'I', 'L' ), '', wp_generate_password( 24, false, false ) ), 0, self::TOKEN_LENGTH ) );
		} while ( strlen( $token ) < self::TOKEN_LENGTH || $wpdb->get_var( $wpdb->prepare( 'SELECT cart_link_id FROM ec_cart_link WHERE link_token = %s', $token ) ) );
		return $token;
	}

	public static function get_url( $token ) {
		$base = get_option( 'ec_option_storepage' ) ? get_permalink( get_option( 'ec_option_storepage' ) ) : home_url( '/' );
		return apply_filters( 'wp_easycart_cart_link_url', add_query_arg( 'ec_cart_apply', rawurlencode( $token ), $base ), $token );
	}

	public static function get_link( $cart_link_id, $with_items = true ) {
		global $wpdb;
		$link = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_cart_link WHERE cart_link_id = %d', $cart_link_id ) );
		return self::hydrate( $link, $with_items );
	}

	public static function get_link_by_token( $token, $with_items = true ) {
		global $wpdb;
		$link = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_cart_link WHERE link_token = %s', $token ) );
		return self::hydrate( $link, $with_items );
	}

	private static function hydrate( $link, $with_items ) {
		global $wpdb;
		if ( ! $link ) {
			return false;
		}
		$link->promo_codes = self::decode_codes( $link->promo_codes );
		$link->items = array();
		if ( $with_items ) {
			$link->items = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_cart_link_item.*, ec_product.model_number, ec_product.title, ec_product.activate_in_store FROM ec_cart_link_item LEFT JOIN ec_product ON ec_product.product_id = ec_cart_link_item.product_id WHERE ec_cart_link_item.cart_link_id = %d ORDER BY ec_cart_link_item.sort_order ASC, ec_cart_link_item.cart_link_item_id ASC', $link->cart_link_id ) );
			foreach ( $link->items as $item ) {
				$item->modifier_values = self::decode_modifiers( $item->modifier_values );
			}
		}
		return $link;
	}

	public static function get_links_for_product( $product_id ) {
		global $wpdb;
		$links = $wpdb->get_results( $wpdb->prepare(
			'SELECT l.*, ( SELECT COUNT(*) FROM ec_cart_link_item i2 WHERE i2.cart_link_id = l.cart_link_id ) AS item_count
			 FROM ec_cart_link l
			 WHERE l.cart_link_id IN ( SELECT i.cart_link_id FROM ec_cart_link_item i WHERE i.product_id = %d )
			 ORDER BY l.created_at DESC',
			$product_id
		) );
		foreach ( $links as $link ) {
			$link->url = self::get_url( $link->link_token );
		}
		return $links;
	}

	/**
	 * Insert or update a link + its items in one call.
	 *
	 * $data keys: link_label, promo_codes (array), destination, clear_cart,
	 * is_active, expires (Y-m-d H:i:s or ''), max_uses.
	 * $items: array of arrays( product_id, quantity, optionitem_id_1..5,
	 * modifier_values (array in ec_tempcart_option "option_val" shape) ).
	 */
	public static function save_link( $cart_link_id, $data, $items ) {
		global $wpdb;
		$row = array(
			'link_label'  => isset( $data['link_label'] ) ? sanitize_text_field( $data['link_label'] ) : '',
			'promo_codes' => wp_json_encode( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $data['promo_codes'] ) ? $data['promo_codes'] : array() ) ) ) ) ),
			'destination' => ( isset( $data['destination'] ) && in_array( $data['destination'], array( 'cart', 'checkout' ), true ) ) ? $data['destination'] : 'cart',
			'clear_cart'  => ! empty( $data['clear_cart'] ) ? 1 : 0,
			'is_active'   => isset( $data['is_active'] ) ? ( $data['is_active'] ? 1 : 0 ) : 1,
			'expires'     => ( ! empty( $data['expires'] ) && strtotime( $data['expires'] ) ) ? date( 'Y-m-d H:i:s', strtotime( $data['expires'] ) ) : null,
			'max_uses'    => isset( $data['max_uses'] ) ? max( 0, (int) $data['max_uses'] ) : 0,
		);

		$cart_link_id = (int) $cart_link_id;
		if ( $cart_link_id ) {
			$wpdb->update( 'ec_cart_link', $row, array( 'cart_link_id' => $cart_link_id ) );
		} else {
			$row['link_token'] = self::generate_token();
			$row['created_by'] = get_current_user_id();
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( 'ec_cart_link', $row );
			$cart_link_id = (int) $wpdb->insert_id;
		}
		if ( ! $cart_link_id ) {
			return false;
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_cart_link_item WHERE cart_link_id = %d', $cart_link_id ) );
		$sort = 0;
		foreach ( (array) $items as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			if ( ! $product_id ) {
				continue;
			}
			$wpdb->insert( 'ec_cart_link_item', array(
				'cart_link_id'    => $cart_link_id,
				'product_id'      => $product_id,
				'quantity'        => isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1,
				'optionitem_id_1' => isset( $item['optionitem_id_1'] ) ? (int) $item['optionitem_id_1'] : 0,
				'optionitem_id_2' => isset( $item['optionitem_id_2'] ) ? (int) $item['optionitem_id_2'] : 0,
				'optionitem_id_3' => isset( $item['optionitem_id_3'] ) ? (int) $item['optionitem_id_3'] : 0,
				'optionitem_id_4' => isset( $item['optionitem_id_4'] ) ? (int) $item['optionitem_id_4'] : 0,
				'optionitem_id_5' => isset( $item['optionitem_id_5'] ) ? (int) $item['optionitem_id_5'] : 0,
				'modifier_values' => wp_json_encode( isset( $item['modifier_values'] ) ? (array) $item['modifier_values'] : array() ),
				'sort_order'      => $sort++,
			) );
		}
		do_action( 'wp_easycart_cart_link_saved', $cart_link_id );
		return $cart_link_id;
	}

	public static function delete_link( $cart_link_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_cart_link_item WHERE cart_link_id = %d', (int) $cart_link_id ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_cart_link WHERE cart_link_id = %d', (int) $cart_link_id ) );
		do_action( 'wp_easycart_cart_link_deleted', (int) $cart_link_id );
	}

	public static function set_active( $cart_link_id, $active ) {
		global $wpdb;
		$wpdb->update( 'ec_cart_link', array( 'is_active' => $active ? 1 : 0 ), array( 'cart_link_id' => (int) $cart_link_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Storefront resolver ( ?ec_cart_apply=TOKEN )                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Called from wpeasycart.php early in the request. Always ends in a
	 * redirect + die, mirroring the ec_add_to_cart handler.
	 */
	public static function resolve( $token, $cartpage, $storepage ) {
		global $wpdb;
		wpeasycart_session()->handle_session();

		$link = self::get_link_by_token( $token );

		/* Invalid / exhausted links degrade to the cart page, never a 404. */
		if ( ! $link
			|| ! $link->is_active
			|| ( $link->expires && strtotime( $link->expires ) < time() )
			|| ( $link->max_uses > 0 && $link->use_count >= $link->max_uses ) ) {
			do_action( 'wp_easycart_cart_link_rejected', $link ? $link : $token );
			header( 'location: ' . esc_url_raw( $cartpage ) );
			die();
		}

		$db = new ec_db();
		$session_id = $GLOBALS['ec_cart_data']->ec_cart_id;

		if ( $link->clear_cart ) {
			global $wpdb;
			$db->clear_tempcart( $session_id );
			/* A fresh cart implies fresh codes: clear_tempcart leaves applied
			 * Offers v2 codes and the legacy coupon slot behind, which would
			 * mis-discount the preloaded cart. */
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_tempcart_offer WHERE session_id = %s', $session_id ) );
			$GLOBALS['ec_cart_data']->cart_data->coupon_code = '';
		}

		$added = 0;
		foreach ( $link->items as $item ) {
			if ( self::add_link_item( $db, $item ) ) {
				$added++;
			}
		}

		$codes_applied = ( $added && ! empty( $link->promo_codes ) ) ? self::apply_codes( $link->promo_codes, $session_id ) : 0;

		if ( $added ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_cart_link SET use_count = use_count + 1, last_used = %s WHERE cart_link_id = %d', current_time( 'mysql' ), $link->cart_link_id ) );

			/* Attribution: stamped into the session; ec_db::insert_order copies
			 * it onto the order so use_count (clicks) can be compared against
			 * orders (conversions). One save covers this, the legacy coupon
			 * slot set in apply_codes(), and the clear-cart reset above. */
			$GLOBALS['ec_cart_data']->cart_data->cart_link_id = (int) $link->cart_link_id;
			$GLOBALS['ec_cart_data']->save_session_to_db();
		}
		if ( $codes_applied || ( $link->clear_cart && $added ) ) {
			wp_cache_flush();
			do_action( 'wpeasycart_cart_updated' );
		}

		do_action( 'wp_easycart_cart_link_resolved', $link, $added );

		$destination = $cartpage;
		if ( 'checkout' === $link->destination && function_exists( 'wpeasycart_links' ) ) {
			$destination = wpeasycart_links()->get_cart_page( 'checkout' );
		}
		header( 'location: ' . esc_url_raw( apply_filters( 'wp_easycart_cart_link_destination', $destination, $link, $added ) ) );
		die();
	}

	/**
	 * Apply stored codes with the same rules as wp_easycart_apply_query_coupon(),
	 * in two passes so the engine's coexistence rule ( a legacy v1 coupon never
	 * combines with v2 offers ) holds no matter how codes are ordered on the link:
	 *
	 *   Pass 1 — every code through ec_offer_engine::apply_code() ( the engine
	 *   enforces its own stacking / pair / limit rules; context is rebuilt per
	 *   code so those checks see prior applications ). Recognized-but-blocked
	 *   codes ( message_key !== cart_invalid_coupon ) are consumed and never
	 *   fall through to legacy.
	 *
	 *   Pass 2 — leftover codes try the legacy single-slot coupon, but only if
	 *   NO v2 offer codes are on the session ( from this visit or earlier ) and
	 *   the slot is empty. First valid legacy code wins.
	 *
	 * Returns how many codes were accepted by either system.
	 */
	private static function apply_codes( $codes, $session_id ) {
		$applied = 0;
		$leftover = array();
		$offers_active = function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active()
			&& class_exists( 'ec_offer_engine' ) && class_exists( 'ec_offer_integration' );

		foreach ( (array) $codes as $code ) {
			$code = sanitize_text_field( $code );
			if ( '' === $code ) {
				continue;
			}
			if ( $offers_active ) {
				$offer_apply = ec_offer_engine::apply_code( $code, $session_id, ec_offer_integration::get_context() );
				if ( ! empty( $offer_apply['success'] ) ) {
					$applied++;
					continue;
				}
				if ( isset( $offer_apply['message_key'] ) && 'cart_invalid_coupon' !== $offer_apply['message_key'] ) {
					continue; /* recognized offer code, blocked — consumed */
				}
			}
			$leftover[] = $code;
		}

		$offers_on_session = ( $offers_active && count( ec_offer_engine::get_applied_codes( $session_id ) ) > 0 );
		$legacy_slot_used  = ( '' !== (string) $GLOBALS['ec_cart_data']->cart_data->coupon_code );
		if ( ! $offers_on_session && ! $legacy_slot_used && isset( $GLOBALS['ec_coupons'] ) ) {
			foreach ( $leftover as $code ) {
				if ( $GLOBALS['ec_coupons']->redeem_coupon_code( $code ) ) {
					$GLOBALS['ec_cart_data']->cart_data->coupon_code = htmlspecialchars( sanitize_text_field( preg_replace( '/[^A-Za-z0-9_\-\$\%]/', '', $code ) ), ENT_QUOTES );
					$applied++;
					break; /* single legacy slot */
				}
			}
		}
		return $applied;
	}

	/**
	 * Add one saved line to the cart, validating everything against the
	 * CURRENT catalog. Returns true when something was added / merged.
	 */
	private static function add_link_item( $db, $item ) {
		global $wpdb;
		$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', (int) $item->product_id ) );
		if ( ! $product || ! $product->activate_in_store ) {
			return false;
		}
		/* Types a preloaded cart cannot represent ( mirrors the builder's exclusions ). */
		if ( $product->is_subscription_item || $product->is_donation || $product->is_deconetwork ) {
			return false;
		}

		/* Basic option sets: every stored optionitem must still belong to the
		 * product's CURRENT option set in that slot, else the whole line is
		 * skipped ( a wrong-variant add is worse than no add ). */
		$optionitem_ids = array( 0, 0, 0, 0, 0 );
		for ( $slot = 1; $slot <= 5; $slot++ ) {
			$option_id  = (int) $product->{ 'option_id_' . $slot };
			$stored_oid = (int) $item->{ 'optionitem_id_' . $slot };
			if ( $option_id && $product->use_advanced_optionset && ! $product->use_both_option_types ) {
				$option_id = 0; /* basic slots ignored for advanced-only products */
			}
			if ( $option_id > 0 ) {
				if ( ! $stored_oid ) {
					return false; /* option now required but link predates it */
				}
				$valid = $wpdb->get_var( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE optionitem_id = %d AND option_id = %d', $stored_oid, $option_id ) );
				if ( ! $valid ) {
					return false;
				}
				$optionitem_ids[ $slot - 1 ] = $stored_oid;
			}
		}

		/* Modifiers: revalidate each stored value against the product's
		 * current advanced option sets; stale entries are dropped ( they are
		 * add-ons, so a partial set is still a correct product ). */
		$option_vals = array();
		if ( ! empty( $item->modifier_values ) && isset( $GLOBALS['ec_advanced_optionsets'] ) ) {
			$current_sets = $GLOBALS['ec_advanced_optionsets']->get_advanced_optionsets( $product->product_id );
			$set_index = array();
			foreach ( (array) $current_sets as $set ) {
				$set_index[ (int) $set->option_id ] = $set;
			}
			foreach ( $item->modifier_values as $val ) {
				$val = (array) $val;
				$oid = isset( $val['option_id'] ) ? (int) $val['option_id'] : 0;
				if ( ! $oid || ! isset( $set_index[ $oid ] ) ) {
					continue;
				}
				$set = $set_index[ $oid ];
				if ( in_array( $set->option_type, array( 'combo', 'swatch', 'radio', 'checkbox' ), true ) ) {
					$optionitem = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE optionitem_id = %d AND option_id = %d', isset( $val['optionitem_id'] ) ? (int) $val['optionitem_id'] : 0, $oid ) );
					if ( ! $optionitem ) {
						continue;
					}
					$option_vals[] = array(
						'option_id'               => $oid,
						'option_label'            => wp_easycart_escape_html( $set->option_label ),
						'option_name'             => sanitize_text_field( $set->option_name ),
						'optionitem_name'         => wp_easycart_escape_html( $optionitem->optionitem_name ),
						'option_type'             => sanitize_text_field( $set->option_type ),
						'optionitem_id'           => (int) $optionitem->optionitem_id,
						'optionitem_value'        => sanitize_text_field( $optionitem->optionitem_name ),
						'optionitem_model_number' => sanitize_text_field( $optionitem->optionitem_model_number ),
					);
				} else if ( in_array( $set->option_type, array( 'text', 'textarea' ), true ) ) {
					$text = isset( $val['optionitem_value'] ) ? sanitize_text_field( $val['optionitem_value'] ) : '';
					if ( '' === $text ) {
						continue;
					}
					$option_vals[] = array(
						'option_id'               => $oid,
						'option_label'            => wp_easycart_escape_html( $set->option_label ),
						'option_name'             => sanitize_text_field( $set->option_name ),
						'optionitem_name'         => $text,
						'option_type'             => sanitize_text_field( $set->option_type ),
						'optionitem_id'           => 0,
						'optionitem_value'        => $text,
						'optionitem_model_number' => '',
					);
				}
				/* file / unsupported types: silently skipped */
			}
		}

		$was_merged  = false;
		$tempcart_id = $db->quick_add_to_cart( $product->model_number, $optionitem_ids, $option_vals, $was_merged, max( 1, (int) $item->quantity ) );
		if ( ! $tempcart_id ) {
			return false;
		}
		if ( ! $was_merged && ! empty( $option_vals ) ) {
			foreach ( $option_vals as $option_val ) {
				$db->add_option_to_cart( $tempcart_id, $GLOBALS['ec_cart_data']->ec_cart_id, $option_val );
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	private static function decode_codes( $raw ) {
		$codes = json_decode( (string) $raw, true );
		return is_array( $codes ) ? array_values( array_filter( array_map( 'strval', $codes ) ) ) : array();
	}

	private static function decode_modifiers( $raw ) {
		$vals = json_decode( (string) $raw, true );
		return is_array( $vals ) ? $vals : array();
	}
}