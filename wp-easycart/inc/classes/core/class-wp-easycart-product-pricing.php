<?php
/**
 * WP EasyCart — what the cart charges, for the product page ( 6.0.5 ).
 *
 * The Classic product page works its price out in the browser as the shopper chooses ( ec-store.js wpeasycart_details_line() ).
 * It used to follow rules of its own, so a page could show a price the cart then did not charge ( a variant's price over a
 * wholesale price, a volume price dropping the variation's price, two overrides, an area price, a quantity grid, a promotion on
 * the product price only … ). The cart is the truth: ec_cartitem::__construct() prices the line, then ec_cart re-prices it for
 * the shopper's VAT rate and takes classic promotions off. This class gives the page what those rules start from, as the cart
 * reads it: the product's own price, the shopper's role price, the volume prices, every option item's price fields and SKU ending,
 * the variants that set a price or SKU ( keyed by the five option item ids with dashes, so 1 + 23 and 12 + 3 never meet ), the
 * classic promotions a line of this product gets and the VAT re-pricing. Printed by the three Classic templates
 * ( ec_product_details_page.php, ec_product_details_page_add_to_cart.php, ec_add_to_cart_shortcode.php ) as
 * wpeasycart_price_data_<product>_<rand>; a template copy without it keeps the page's older reading of its hidden fields.
 *
 * Keep it in step with ec_cartitem::__construct(), ec_cart::make_vat_adjustments() and ec_promotion::apply_promotion().
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_pricing' ) ) :

	/**
	 * The cart's price rules' inputs for one product page.
	 */
	class wp_easycart_product_pricing {

		/**
		 * Whether the page shows this product's price ( the same test the templates make before they print its hidden price
		 * fields ): never print what a store hides.
		 *
		 * @param object $product ec_product.
		 * @return bool
		 */
		public static function prices_shown( $product ) {
			if ( ! empty( $product->login_for_pricing ) && method_exists( $product, 'is_login_for_pricing_valid' ) && ! $product->is_login_for_pricing_valid() ) {
				return false;
			}
			if ( ! empty( $product->is_catalog_mode ) && get_option( 'ec_option_hide_price_seasonal' ) ) {
				return false;
			}
			if ( ! empty( $product->is_inquiry_mode ) && get_option( 'ec_option_hide_price_inquiry' ) ) {
				return false;
			}
			return true;
		}

		/**
		 * The data. Keys ( short: it is printed on every product page ):
		 * price ( the product's own price ), role ( the shopper's role price or null ), tiers ( [ quantity, price ] in the cart's
		 * order ), promo ( ec_promotion::product_line_rules() ), dec ( money decimals ), vat ( [ the rate prices include, the
		 * shopper's rate ] when the cart re-prices the line, else null ), metric, model, ext ( the SKU ending separator ),
		 * items ( option item id => { p price, t one-time, v override, m multiplier, c per character, n characters of its name,
		 * s SKU ending }, defaults left out ), sets ( option_to_product_id => its item ids ), variants ( "a-b-c-d-e" => [ price,
		 * SKU ], only those that set either ).
		 *
		 * @param object $product ec_product.
		 * @return array|null Null when the page hides the price.
		 */
		public static function data( $product ) {
			global $wpdb;
			if ( ! is_object( $product ) || empty( $product->product_id ) || ! self::prices_shown( $product ) ) {
				return null;
			}
			$product_id = (int) $product->product_id;
			$row        = $wpdb->get_row( $wpdb->prepare( 'SELECT price, manufacturer_id, vat_rate, tax_class, option_id_1, option_id_2, option_id_3, option_id_4, option_id_5 FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $row ) {
				return null;
			}

			$role  = isset( $GLOBALS['ec_roleprices'] ) ? $GLOBALS['ec_roleprices']->get_roleprice( $product_id ) : false;
			$tiers = array();
			if ( isset( $GLOBALS['ec_pricetiers'] ) ) {
				foreach ( (array) $GLOBALS['ec_pricetiers']->get_pricetiers( $product_id ) as $tier ) {
					$tiers[] = array( (int) $tier->quantity, (float) $tier->price );
				}
			}
			$promo = array();
			if ( empty( $product->is_subscription_item ) ) { /* a subscription is never a cart line ( and its page never showed a promotion ) */
				$promotion = new ec_promotion();
				if ( method_exists( $promotion, 'product_line_rules' ) ) {
					$promo = $promotion->product_line_rules( $product_id, (int) $row->manufacturer_id );
				}
			}

			$data = array(
				'price'    => (float) $row->price,
				'role'     => ( false === $role || null === $role ) ? null : (float) $role, /* the cart tests the role price as set, so 0.000 is a price */
				'tiers'    => $tiers,
				'promo'    => $promo,
				'dec'      => (int) $GLOBALS['currency']->get_decimal_length(),
				'vat'      => self::vat( $row ),
				'metric'   => get_option( 'ec_option_enable_metric_unit_display' ) ? 1 : 0,
				'model'    => (string) $product->model_number,
				'ext'      => (string) get_option( 'ec_option_model_number_extension' ),
				'items'    => array(),
				'sets'     => array(),
				'variants' => array(),
			);

			/* Variation sets: each choice's own price and SKU ending ( the cart reads nothing else of them ). */
			$basic_sets = array();
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				if ( (int) $row->{ 'option_id_' . $slot } > 0 ) {
					$basic_sets[] = (int) $row->{ 'option_id_' . $slot };
				}
			}
			if ( $basic_sets ) {
				$basic_items = $wpdb->get_results( 'SELECT optionitem_id, optionitem_price, optionitem_model_number FROM ec_optionitem WHERE option_id IN ( ' . implode( ',', array_map( 'intval', $basic_sets ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers.
				foreach ( $basic_items as $item ) {
					$data['items'][ (int) $item->optionitem_id ] = self::item( $item, true );
				}
			}

			/* Modifier sets, as the add to cart reads them ( ec_cartpage::get_advanced_option_vals() ). */
			$sets = ( isset( $product->advanced_optionsets ) && is_array( $product->advanced_optionsets ) ) ? $product->advanced_optionsets : array();
			foreach ( $sets as $set ) {
				if ( ! isset( $set->option_to_product_id, $set->option_id ) ) {
					continue;
				}
				$items = apply_filters( 'wp_easycart_advanced_option_items_add_to_cart', isset( $set->option_items ) ? (array) $set->option_items : array(), $set->option_id );
				$ids   = array();
				foreach ( $items as $item ) {
					if ( isset( $item->optionitem_id ) ) {
						$ids[]                                       = (int) $item->optionitem_id;
						$data['items'][ (int) $item->optionitem_id ] = self::item( $item, false );
					}
				}
				$data['sets'][ (int) $set->option_to_product_id ] = $ids;
			}

			/* Variants: the first row for a combination is the one the cart finds ( wp_easycart_variants::find() ). */
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, price, sku FROM ec_optionitemquantity WHERE product_id = %d ORDER BY optionitemquantity_id ASC', $product_id ) );
			$seen = array();
			foreach ( $rows as $variant ) {
				$key = (int) $variant->optionitem_id_1 . '-' . (int) $variant->optionitem_id_2 . '-' . (int) $variant->optionitem_id_3 . '-' . (int) $variant->optionitem_id_4 . '-' . (int) $variant->optionitem_id_5;
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				if ( -1 != $variant->price || '' != $variant->sku ) {
					$data['variants'][ $key ] = array( (float) $variant->price, (string) $variant->sku );
				}
			}

			/**
			 * What the product page prices its choices from ( wpeasycart_price_data_<product>_<rand> ).
			 *
			 * @since 6.0.5
			 * @param array  $data    See data().
			 * @param object $product ec_product.
			 */
			return apply_filters( 'wp_easycart_product_price_data', $data, $product );
		}

		/**
		 * The data as JavaScript ( null when the page hides the price ), safe inside a script tag.
		 *
		 * @param object $product ec_product.
		 * @return string
		 */
		public static function json( $product ) {
			$data = self::data( $product );
			if ( null === $data ) {
				return 'null';
			}
			if ( empty( $data['items'] ) ) {
				$data['items'] = new stdClass(); /* an object in JavaScript, never [] */
			}
			if ( empty( $data['sets'] ) ) {
				$data['sets'] = new stdClass();
			}
			if ( empty( $data['variants'] ) ) {
				$data['variants'] = new stdClass();
			}
			$json = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			return ( false === $json ) ? 'null' : $json;
		}

		/**
		 * One option item's price fields, defaults left out.
		 *
		 * @param object $item  ec_optionitem row.
		 * @param bool   $basic A variation choice ( the cart reads only its price and SKU ending ).
		 * @return array
		 */
		private static function item( $item, $basic ) {
			$out = array();
			if ( isset( $item->optionitem_price ) && 0 != $item->optionitem_price ) {
				$out['p'] = (float) $item->optionitem_price;
			}
			if ( isset( $item->optionitem_model_number ) && '' != $item->optionitem_model_number ) {
				$out['s'] = (string) $item->optionitem_model_number;
			}
			if ( $basic ) {
				return $out;
			}
			if ( isset( $item->optionitem_price_onetime ) && 0 != $item->optionitem_price_onetime ) {
				$out['t'] = (float) $item->optionitem_price_onetime;
			}
			if ( isset( $item->optionitem_price_override ) && $item->optionitem_price_override >= 0 ) {
				$out['v'] = (float) $item->optionitem_price_override;
			}
			if ( isset( $item->optionitem_price_multiplier ) && 0 != $item->optionitem_price_multiplier ) {
				$out['m'] = (float) $item->optionitem_price_multiplier;
			}
			if ( isset( $item->optionitem_price_per_character ) && $item->optionitem_price_per_character > 0 ) {
				$out['c'] = (float) $item->optionitem_price_per_character;
				/* a choice ( select, radio, swatch, check box ) is sent as its name, and the cart counts that */
				$out['n'] = strlen( preg_replace( '/\s+/', '', sanitize_text_field( isset( $item->optionitem_name ) ? $item->optionitem_name : '' ) ) );
			}
			return $out;
		}

		/**
		 * The VAT re-pricing ec_cart::make_vat_adjustments() gives a line of this product: [ the rate prices include, the
		 * shopper's rate ], or null when it leaves the line alone.
		 *
		 * @param object $row The product's row.
		 * @return array|null
		 */
		private static function vat( $row ) {
			if ( ! ( $row->vat_rate > 0 ) || ! class_exists( 'ec_tax' ) ) {
				return null;
			}
			$state   = '';
			$country = '';
			if ( isset( $GLOBALS['ec_cart_data']->shipping_state ) && '' != $GLOBALS['ec_cart_data']->shipping_state ) {
				$state = $GLOBALS['ec_cart_data']->shipping_state;
			} elseif ( isset( $GLOBALS['ec_user']->shipping->state ) && '' != $GLOBALS['ec_user']->shipping->state ) {
				$state = $GLOBALS['ec_user']->shipping->state;
			}
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) && '' != $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
				$country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
			} elseif ( isset( $GLOBALS['ec_user']->shipping->country ) && '' != $GLOBALS['ec_user']->shipping->country ) {
				$country = $GLOBALS['ec_user']->shipping->country;
			}
			$tax     = new ec_tax( 0, 0, 0, $state, $country );
			$class   = isset( $row->tax_class ) ? (string) $row->tax_class : '';
			$default = method_exists( $tax, 'class_default_rate' ) ? $tax->class_default_rate( $class ) : $tax->vat_rate_default;
			$rate    = method_exists( $tax, 'class_rate' ) ? $tax->class_rate( $class ) : $tax->vat_rate;
			if ( ! $tax->vat_included || $default == $rate ) {
				return null;
			}
			return array( (float) $default, (float) $rate );
		}
	}

endif;
