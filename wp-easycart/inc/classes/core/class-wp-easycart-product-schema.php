<?php
/**
 * WP EasyCart product data for search engines and AI assistants ( 6.0.2 ).
 *
 * One builder for the schema.org markup on every product page: the price a signed-out shopper sees for the default
 * choice, real stock, identifiers, images, a plain-text description, the exact rating and recent reviews, and a
 * ProductGroup with one Product per variant when stock is tracked per variant. The details and Product Meta templates
 * call print_for(), which prints each product once per page whatever renders it. Store-wide return and shipping policy
 * live in wp_easycart_store_schema; this class links to it through each offer's seller.
 *
 * Also the bridge to SEO plugins: seo_plugin(), and the description / image hand-off when one of them owns the head tags.
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_schema' ) ) :

	/**
	 * Product markup builder ( static ).
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_product_schema {

		/** Most variants listed on one page ( filter wp_easycart_product_schema_variant_limit ). */
		const VARIANT_LIMIT = 100;

		/** Most images listed for one product. */
		const IMAGE_LIMIT = 10;

		/** Most reviews listed for one product. */
		const REVIEW_LIMIT = 5;

		/**
		 * Products already printed on this page.
		 *
		 * @var array
		 */
		private static $printed = array();

		/**
		 * The product row for this request's head ( false = looked up, none ).
		 *
		 * @var array|false|null
		 */
		private static $head_row = null;

		/**
		 * Register hooks.
		 */
		public static function init() {
			/* SEO plugins own the description and social tags when the hand-off is on: give them the product's. */
			add_filter( 'wpseo_metadesc', array( __CLASS__, 'fill_description' ), 20 );
			add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'fill_social_description' ), 20 );
			add_filter( 'wpseo_twitter_description', array( __CLASS__, 'fill_social_description' ), 20 );
			add_action( 'wpseo_add_opengraph_images', array( __CLASS__, 'yoast_image' ) );
			add_filter( 'rank_math/frontend/description', array( __CLASS__, 'fill_description' ), 20 );
			add_filter( 'rank_math/opengraph/facebook/image', array( __CLASS__, 'fill_image' ), 20 );
			add_filter( 'aioseo_description', array( __CLASS__, 'fill_description' ), 20 );
			add_filter( 'seopress_titles_desc', array( __CLASS__, 'fill_description' ), 20 );
			/* 6.0.2: the product image for All in One SEO and SEOPress too ( 6.0.1 printed og:image on those sites ). */
			add_filter( 'aioseo_facebook_tags', array( __CLASS__, 'aioseo_facebook_tags' ), 20 );
			add_filter( 'aioseo_twitter_tags', array( __CLASS__, 'aioseo_twitter_tags' ), 20 );
			add_filter( 'seopress_social_og_thumb', array( __CLASS__, 'seopress_og_image' ), 20 );
		}

		// ------------------------------------------------------------------
		// Settings.
		// ------------------------------------------------------------------

		/**
		 * Is product markup on?
		 *
		 * @param object|null $product Product, for the per-product filter.
		 * @return bool
		 */
		public static function enabled( $product = null ) {
			$on = '0' !== (string) get_option( 'ec_option_product_schema', '1' );
			return (bool) apply_filters( 'wp_easycart_product_schema_enabled', $on, $product );
		}

		/**
		 * The store's currency code ( markup is always in the store's own currency, whatever a visitor converted to ).
		 *
		 * @return string
		 */
		public static function currency() {
			$code = strtoupper( trim( (string) get_option( 'ec_option_base_currency', 'USD' ) ) );
			return ( 3 === strlen( $code ) ) ? $code : 'USD';
		}

		/**
		 * Condition for products that don't set their own.
		 *
		 * @return string new | used | refurbished
		 */
		public static function default_condition() {
			$condition = (string) get_option( 'ec_option_product_schema_condition', 'new' );
			return in_array( $condition, array( 'new', 'used', 'refurbished' ), true ) ? $condition : 'new';
		}

		// ------------------------------------------------------------------
		// Output.
		// ------------------------------------------------------------------

		/**
		 * Print the product's markup once per page.
		 *
		 * @param object $product ec_product.
		 */
		public static function print_for( $product ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) || ! self::enabled( $product ) ) {
				return;
			}
			$id = (int) $product->product_id;
			if ( isset( self::$printed[ $id ] ) ) {
				return;
			}
			self::$printed[ $id ] = true;
			$data = self::build( $product );
			if ( ! $data ) {
				return;
			}
			echo "<script type=\"application/ld+json\">\n" . self::json( $data ) . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode() with JSON_HEX_TAG, so it can't close the script tag.
		}

		/**
		 * Encode markup for a script tag.
		 *
		 * @param array $data Markup.
		 * @return string
		 */
		public static function json( $data ) {
			return (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT );
		}

		/**
		 * Build the markup for one product.
		 *
		 * @param object $product   ec_product.
		 * @param bool   $keep_rows Give each hasVariant item its ec_optionitemquantity row as '_wpec_row' ( the Google product feed
		 *                          reads the variant's own Google attributes from it; never printed ).
		 * @return array|null Null when there is nothing Google can use ( no price, no reviews ).
		 */
		public static function build( $product, $keep_rows = false ) {
			if ( ! is_object( $product ) || empty( $product->product_id ) ) {
				return null;
			}
			$url  = self::url( $product );
			$data = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Product',
				'@id'      => $url . '#product',
				'name'     => self::plain( $product->title, 150 ),
				'url'      => $url,
			);

			$description = self::description( $product );
			if ( '' !== $description ) {
				$data['description'] = $description;
			}
			$images = self::images( $product );
			if ( $images ) {
				$data['image'] = $images;
			}
			$brand = self::plain( isset( $product->manufacturer_name ) ? $product->manufacturer_name : '', 100 );
			if ( '' !== $brand ) {
				$data['brand'] = array(
					'@type' => 'Brand',
					'name'  => $brand,
				);
			}
			if ( '' !== trim( (string) $product->model_number ) ) {
				$data['sku'] = trim( (string) $product->model_number );
			}
			$attributes = self::google_attributes( $product );
			$data       = array_merge( $data, self::identifiers( $attributes ) );
			$data       = array_merge( $data, self::reviews( $product ) );

			$pricing = self::pricing( $product );
			$offer   = self::offer( $product, $pricing, $url, $attributes );
			if ( $offer ) {
				$data['offers'] = $offer;
			}

			$variants = self::variants( $product, $pricing, $url, $attributes, $keep_rows );
			if ( $variants && ! $keep_rows ) {
				/* 6.0.2: a variant with no offer ( hidden price, a price range, a zero price ) has nothing Google can use, and
				   variants never carry the reviews, so it is left out of the page's markup. With none left, the product is
				   described as a plain Product. The Google product feed ( $keep_rows ) still gets every variant and reports why. */
				$variants['items'] = array_values(
					array_filter(
						$variants['items'],
						function ( $item ) {
							return isset( $item['offers'] );
						}
					)
				);
				if ( ! $variants['items'] ) {
					$variants = null;
				}
			}
			if ( $variants ) {
				$data['@type']          = 'ProductGroup';
				$data['productGroupID'] = self::group_id( $product, $attributes );
				foreach ( array( 'sku', 'gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'mpn', 'offers' ) as $key ) {
					unset( $data[ $key ] );
				}
				if ( $variants['varies_by'] ) {
					$data['variesBy'] = $variants['varies_by'];
				}
				$data['hasVariant'] = $variants['items'];
			}

			if ( ! isset( $data['offers'] ) && ! isset( $data['aggregateRating'] ) && ! isset( $data['review'] ) && empty( $data['hasVariant'] ) ) {
				return null;
			}
			return apply_filters( 'wp_easycart_product_schema', $data, $product );
		}

		// ------------------------------------------------------------------
		// Pieces.
		// ------------------------------------------------------------------

		/**
		 * The product's own address.
		 *
		 * @param object $product ec_product.
		 * @return string
		 */
		public static function url( $product ) {
			$url = method_exists( $product, 'get_product_link' ) ? (string) $product->get_product_link() : '';
			return esc_url_raw( $url );
		}

		/**
		 * Plain text: tags, shortcodes and extra whitespace removed, cut at a word.
		 *
		 * @param string $text  Text.
		 * @param int    $limit Characters.
		 * @return string
		 */
		public static function plain( $text, $limit = 5000 ) {
			$text = (string) $text;
			if ( '' === $text ) {
				return '';
			}
			$text = stripslashes( $text );
			/* 6.0.2: registered shortcodes and EasyCart's own only; "[Limited edition]" in a title is text, not a shortcode. */
			if ( function_exists( 'strip_shortcodes' ) ) {
				$text = strip_shortcodes( $text );
			}
			$text = preg_replace( '/\[\/?ec_[a-zA-Z0-9_]*[^\]]*\]/', ' ', $text );
			$text = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $text );
			$text = str_replace( array( '<br', '</p>', '</li>', '</div>' ), array( ' <br', '</p> ', '</li> ', '</div> ' ), $text );
			$text = wp_strip_all_tags( $text );
			$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
			$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $limit ) {
				$cut  = mb_substr( $text, 0, $limit, 'UTF-8' );
				$last = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
				$text = rtrim( ( false !== $last && $last > $limit * 0.6 ) ? mb_substr( $cut, 0, $last, 'UTF-8' ) : $cut, ' ,.;:-' ) . '…';
			} elseif ( ! function_exists( 'mb_strlen' ) && strlen( $text ) > $limit ) {
				$text = substr( $text, 0, $limit ) . '…';
			}
			return $text;
		}

		/**
		 * Description: the short description, else the full one, as plain text.
		 *
		 * @param object $product ec_product.
		 * @return string
		 */
		public static function description( $product ) {
			$short = isset( $product->short_description ) ? self::plain( $product->short_description ) : '';
			if ( '' !== $short ) {
				return $short;
			}
			return isset( $product->description ) ? self::plain( $product->description ) : '';
		}

		/**
		 * PRO's Google attributes for the product ( barcode, MPN, condition ... ), shared with FREE's editor card.
		 *
		 * @param object $product ec_product.
		 * @return object|null
		 */
		public static function google_attributes( $product ) {
			if ( empty( $product->google_attributes ) ) {
				return null;
			}
			$attributes = is_object( $product->google_attributes ) ? $product->google_attributes : json_decode( (string) $product->google_attributes );
			return is_object( $attributes ) ? $attributes : null;
		}

		/**
		 * Barcode and part number properties.
		 *
		 * @param object|null $attributes Google attributes ( product or variant ).
		 * @return array
		 */
		public static function identifiers( $attributes ) {
			$out = array();
			if ( ! is_object( $attributes ) ) {
				return $out;
			}
			if ( ! empty( $attributes->gtin ) ) {
				$digits = preg_replace( '/\D/', '', (string) $attributes->gtin );
				if ( in_array( strlen( $digits ), array( 8, 12, 13, 14 ), true ) ) {
					$out[ 'gtin' . strlen( $digits ) ] = $digits;
				}
			}
			if ( ! empty( $attributes->mpn ) ) {
				$mpn = self::plain( $attributes->mpn, 70 );
				if ( '' !== $mpn ) {
					$out['mpn'] = $mpn;
				}
			}
			return $out;
		}

		/**
		 * The product group's ID: the Google group ID from the product's Google Merchant card, else the model number, else the
		 * product ID. The Google product feed uses the same value as item_group_id, so page and feed agree.
		 *
		 * @param object      $product    ec_product.
		 * @param object|null $attributes google_attributes().
		 * @return string
		 */
		public static function group_id( $product, $attributes ) {
			if ( is_object( $attributes ) && isset( $attributes->item_group_id ) && '' !== trim( (string) $attributes->item_group_id ) ) {
				return trim( (string) $attributes->item_group_id );
			}
			$model = trim( (string) $product->model_number );
			return '' !== $model ? $model : (string) (int) $product->product_id;
		}

		/**
		 * A variant's ID: its SKU, else the model number followed by the variant row's ID ( the ID the Google product feed has
		 * always given it, so Merchant Center keeps its history ).
		 *
		 * @param object $product ec_product.
		 * @param object $row     ec_optionitemquantity row.
		 * @return string
		 */
		public static function variant_id( $product, $row ) {
			$sku = isset( $row->sku ) ? trim( (string) $row->sku ) : '';
			if ( '' !== $sku ) {
				return $sku;
			}
			$model = trim( (string) $product->model_number );
			return ( '' !== $model ? $model : (int) $product->product_id . '-' ) . (int) $row->optionitemquantity_id;
		}

		/**
		 * The condition as a schema.org URL.
		 *
		 * @param object|null $attributes Google attributes.
		 * @return string
		 */
		public static function condition( $attributes ) {
			$condition = ( is_object( $attributes ) && ! empty( $attributes->condition ) ) ? strtolower( (string) $attributes->condition ) : self::default_condition();
			$map       = array(
				'new'         => 'https://schema.org/NewCondition',
				'used'        => 'https://schema.org/UsedCondition',
				'refurbished' => 'https://schema.org/RefurbishedCondition',
			);
			return isset( $map[ $condition ] ) ? $map[ $condition ] : $map[ self::default_condition() ];
		}

		/**
		 * What the product page shows for price, for a shopper who hasn't picked anything yet.
		 *
		 * @param object $product ec_product.
		 * @return array hidden ( bool ), price, list ( struck-through, 0 = none ), raw ( price before default options ),
		 *               low / high ( a custom price range ), vat ( prices shown with VAT added ).
		 */
		public static function pricing( $product ) {
			$out = array(
				'hidden' => false,
				'price'  => 0.0,
				'list'   => 0.0,
				'raw'    => (float) $product->price,
				'low'    => 0.0,
				'high'   => 0.0,
				'vat'    => false,
			);
			$login_hidden   = ! empty( $product->login_for_pricing ) && ( ! method_exists( $product, 'is_login_for_pricing_valid' ) || ! $product->is_login_for_pricing_valid() );
			$catalog_hidden = ! empty( $product->is_catalog_mode ) && get_option( 'ec_option_hide_price_seasonal' );
			$inquiry_hidden = ! empty( $product->is_inquiry_mode ) && get_option( 'ec_option_hide_price_inquiry' );
			$label_replaces = ! empty( $product->replace_price_label ) && in_array( (int) $product->enable_price_label, array( 2, 4, 6, 7 ), true );
			if ( $login_hidden || $catalog_hidden || $inquiry_hidden || $label_replaces || ! empty( $product->is_donation ) ) {
				$out['hidden'] = true;
				return $out;
			}
			$out['vat'] = ( (float) $product->vat_rate > 0 && get_option( 'ec_option_show_multiple_vat_pricing' ) );
			if ( ! empty( $product->show_custom_price_range ) ) {
				$out['low']  = self::shown( $product, (float) $product->price_range_low, $out['vat'] );
				$out['high'] = ( (float) $product->price_range_high > 0 ) ? self::shown( $product, (float) $product->price_range_high, $out['vat'] ) : 0.0;
				return $out;
			}
			$price = (float) $product->price_options;
			$list  = (float) $product->list_price;

			/*
			 * The Offers price preview ( WP EasyCart PRO ): the page strikes the price and shows the offer price. It starts from
			 * the price shown ( with the default options ) and gets the list price, so an offer that leaves sale items alone
			 * previews nothing on a product on sale, as on the page and in the cart.
			 */
			if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && class_exists( 'ec_offer_display' ) && method_exists( 'ec_offer_display', 'get_product_price_preview' ) && $price > 0 ) {
				$preview = ec_offer_display::get_product_price_preview( $product->product_id, $product->manufacturer_id, $price, $list );
				if ( false !== $preview && (float) $preview < $price ) {
					$list  = max( $list, $price );
					$price = (float) $preview;
				}
			}
			$out['price'] = self::shown( $product, $price, $out['vat'] );
			$out['list']  = ( $list > $price ) ? self::shown( $product, $list, $out['vat'] ) : 0.0;
			return $out;
		}

		/**
		 * A price as the page shows it: with VAT added when the store shows VAT prices, rounded to cents.
		 *
		 * @param object $product ec_product.
		 * @param float  $amount  Price.
		 * @param bool   $vat     Show with VAT.
		 * @return float
		 */
		public static function shown( $product, $amount, $vat ) {
			$amount = (float) $amount;
			if ( $vat && $amount > 0 && class_exists( 'ec_tax' ) ) {
				$country = '';
				$state   = '';
				if ( isset( $GLOBALS['ec_cart_data']->cart_data->shipping_country ) && '' !== (string) $GLOBALS['ec_cart_data']->cart_data->shipping_country ) {
					$country = $GLOBALS['ec_cart_data']->cart_data->shipping_country;
				}
				if ( isset( $GLOBALS['ec_cart_data']->shipping_state ) && '' !== (string) $GLOBALS['ec_cart_data']->shipping_state ) {
					$state = $GLOBALS['ec_cart_data']->shipping_state;
				}
				$tax = new ec_tax(
					$amount,
					$amount,
					$amount,
					$state,
					$country,
					false,
					0,
					(object) array(
						'cart' => array(
							(object) array(
								'product_id'      => $product->product_id,
								'total_price'     => $amount,
								'manufacturer_id' => $product->manufacturer_id,
								'is_taxable'      => $product->is_taxable,
								'vat_enabled'     => $product->vat_rate,
							),
						),
					)
				);
				if ( $tax->vat_added ) {
					$amount += (float) $tax->vat_total;
				}
			}
			return round( $amount, 2 );
		}

		/**
		 * Stock as schema.org availability.
		 *
		 * @param object $product  ec_product.
		 * @param bool   $tracked  Stock is counted.
		 * @param float  $quantity Units left.
		 * @return array availability, and availabilityStarts for a backorder with a fill date.
		 */
		public static function availability( $product, $tracked, $quantity ) {
			if ( ! $tracked || $quantity > 0 ) {
				return array( 'availability' => 'https://schema.org/InStock' );
			}
			if ( ! empty( $product->allow_backorders ) ) {
				$out  = array( 'availability' => 'https://schema.org/BackOrder' );
				$fill = isset( $product->backorder_fill_date ) ? strtotime( (string) $product->backorder_fill_date ) : false;
				if ( $fill && $fill > time() ) {
					$out['availabilityStarts'] = gmdate( 'Y-m-d', $fill );
				}
				return $out;
			}
			return array( 'availability' => 'https://schema.org/OutOfStock' );
		}

		/**
		 * The store as the seller of every offer ( full markup in wp_easycart_store_schema ).
		 *
		 * @return array
		 */
		public static function seller() {
			$seller = array(
				'@type' => 'OnlineStore',
				'name'  => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			);
			if ( class_exists( 'wp_easycart_store_schema' ) ) {
				$seller['@id'] = wp_easycart_store_schema::id();
			}
			return $seller;
		}

		/**
		 * The product's offer ( null when the page shows no price ).
		 *
		 * @param object      $product    ec_product.
		 * @param array       $pricing    pricing().
		 * @param string      $url        Offer address.
		 * @param object|null $attributes Google attributes.
		 * @return array|null
		 */
		public static function offer( $product, $pricing, $url, $attributes ) {
			if ( $pricing['hidden'] ) {
				return null;
			}
			$tracked  = ! empty( $product->show_stock_quantity ) || ! empty( $product->use_optionitem_quantity_tracking );
			$currency = self::currency();
			$common   = array_merge(
				self::availability( $product, $tracked, (float) $product->stock_quantity ),
				array(
					'itemCondition' => self::condition( $attributes ),
					'url'           => $url,
					'seller'        => self::seller(),
				)
			);
			if ( $pricing['low'] > 0 ) {
				$offer = array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => $pricing['low'],
					'priceCurrency' => $currency,
				);
				if ( $pricing['high'] > $pricing['low'] ) {
					$offer['highPrice'] = $pricing['high'];
				}
				return array_merge( $offer, $common );
			}
			if ( $pricing['price'] <= 0 ) {
				return null; /* Google needs a price above zero */
			}
			$offer = array_merge(
				array(
					'@type'         => 'Offer',
					'price'         => $pricing['price'],
					'priceCurrency' => $currency,
				),
				$common
			);
			$specs = array();
			if ( $pricing['list'] > $pricing['price'] ) {
				$specs[] = array(
					'@type'         => 'UnitPriceSpecification',
					'priceType'     => 'https://schema.org/StrikethroughPrice',
					'price'         => $pricing['list'],
					'priceCurrency' => $currency,
				);
			}
			if ( ! empty( $product->is_subscription_item ) ) {
				$units = array(
					'D' => 'DAY',
					'W' => 'WEE',
					'M' => 'MON',
					'Y' => 'ANN',
				);
				$period = isset( $units[ (string) $product->subscription_bill_period ] ) ? $units[ (string) $product->subscription_bill_period ] : 'MON';
				/* 6.0.2: the bill length is how often the price is charged ( "every 3 months" ): the price's reference
				   quantity. billingDuration is how long it is charged in all, only for a plan with a set end ( "for 12 months" ). */
				$spec = array(
					'@type'             => 'UnitPriceSpecification',
					'price'             => $pricing['price'],
					'priceCurrency'     => $currency,
					'referenceQuantity' => array(
						'@type'    => 'QuantitativeValue',
						'value'    => max( 1, (int) $product->subscription_bill_length ),
						'unitCode' => $period,
					),
					'unitCode'          => $period,
				);
				if ( isset( $product->subscription_bill_duration ) && (int) $product->subscription_bill_duration > 0 ) {
					$spec['billingDuration'] = array(
						'@type'    => 'QuantitativeValue',
						'value'    => (int) $product->subscription_bill_duration,
						'unitCode' => $period,
					);
				}
				$specs[] = $spec;
			}
			if ( 1 === count( $specs ) ) {
				$offer['priceSpecification'] = $specs[0];
			} elseif ( $specs ) {
				$offer['priceSpecification'] = $specs;
			}
			return $offer;
		}

		/**
		 * Rating and recent reviews.
		 *
		 * @param object $product ec_product.
		 * @return array aggregateRating and review, or empty.
		 */
		public static function reviews( $product ) {
			if ( empty( $product->use_customer_reviews ) || empty( $product->reviews ) || ! is_array( $product->reviews ) ) {
				return array();
			}
			$total = 0;
			$count = 0;
			foreach ( $product->reviews as $review ) {
				$rating = isset( $review->rating ) ? (int) $review->rating : 0;
				if ( $rating >= 1 && $rating <= 5 ) {
					$total += $rating;
					++$count;
				}
			}
			if ( ! $count ) {
				return array();
			}
			$out = array(
				'aggregateRating' => array(
					'@type'       => 'AggregateRating',
					'ratingValue' => round( $total / $count, 1 ),
					'bestRating'  => 5,
					'worstRating' => 1,
					'reviewCount' => $count,
				),
			);
			/* Individual reviews only when product pages show reviewer names: Google needs an author for each. */
			if ( 'rating' === (string) get_option( 'ec_option_product_schema_reviews', 'reviews' ) || ! get_option( 'ec_option_customer_review_show_user_name' ) ) {
				return $out;
			}
			$picked = array();
			foreach ( $product->reviews as $review ) {
				if ( count( $picked ) >= self::REVIEW_LIMIT ) {
					break;
				}
				$name = isset( $review->reviewer_name ) ? trim( (string) $review->reviewer_name ) : '';
				if ( '' === $name ) {
					$name = trim( ( isset( $review->first_name ) ? (string) $review->first_name : '' ) . ' ' . ( isset( $review->last_name ) ? (string) $review->last_name : '' ) );
				}
				$rating = isset( $review->rating ) ? (int) $review->rating : 0;
				if ( '' === $name || $rating < 1 || $rating > 5 ) {
					continue;
				}
				$picked[] = array(
					'id'     => isset( $review->review_id ) ? (int) $review->review_id : 0,
					'name'   => self::plain( $name, 60 ),
					'rating' => $rating,
					'title'  => isset( $review->title ) ? self::plain( $review->title, 110 ) : '',
					'body'   => isset( $review->description ) ? self::plain( $review->description, 2000 ) : '',
				);
			}
			if ( ! $picked ) {
				return $out;
			}
			$dates = self::review_dates( wp_list_pluck( $picked, 'id' ) );
			$items = array();
			foreach ( $picked as $row ) {
				$item = array(
					'@type'        => 'Review',
					'reviewRating' => array(
						'@type'       => 'Rating',
						'ratingValue' => $row['rating'],
						'bestRating'  => 5,
						'worstRating' => 1,
					),
					'author'       => array(
						'@type' => 'Person',
						'name'  => $row['name'],
					),
				);
				if ( '' !== $row['title'] ) {
					$item['name'] = $row['title'];
				}
				if ( '' !== $row['body'] ) {
					$item['reviewBody'] = $row['body'];
				}
				if ( isset( $dates[ $row['id'] ] ) ) {
					$item['datePublished'] = $dates[ $row['id'] ];
				}
				$items[] = $item;
			}
			$out['review'] = $items;
			return $out;
		}

		/**
		 * Submission dates for reviews ( the loaded rows carry a formatted date only ).
		 *
		 * @param int[] $ids Review ids.
		 * @return array id => Y-m-d.
		 */
		private static function review_dates( $ids ) {
			global $wpdb;
			$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
			if ( ! $ids ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a list of %d markers; the ids are prepare() arguments.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT review_id, date_submitted FROM ec_review WHERE review_id IN ( $placeholders )", $ids ) );
			$out  = array();
			foreach ( (array) $rows as $row ) {
				$time = strtotime( (string) $row->date_submitted );
				if ( $time ) {
					$out[ (int) $row->review_id ] = gmdate( 'Y-m-d', $time );
				}
			}
			return $out;
		}

		// ------------------------------------------------------------------
		// Images.
		// ------------------------------------------------------------------

		/**
		 * Every product image, main first: no videos, posters or the "no image" placeholder.
		 *
		 * @param object $product ec_product.
		 * @return string[]
		 */
		public static function images( $product ) {
			if ( ! isset( $product->images ) || ! is_object( $product->images ) ) {
				return array();
			}
			$tokens = array();
			$source = null;
			if ( ! empty( $product->use_optionitem_images ) && method_exists( $product, 'get_details_initial_imageset_id' ) ) {
				$set_id = $product->get_details_initial_imageset_id();
				foreach ( (array) $product->images->imageset as $set ) {
					if ( false !== $set_id && (int) $set->optionitem_id === (int) $set_id ) {
						$tokens = self::set_tokens( $set );
						$source = $set; /* 6.0.2: its image1-5 are the set's own pictures */
						break;
					}
				}
			}
			if ( ! $tokens ) {
				$tokens = self::set_tokens( $product->images );
				$source = null;
			}
			$urls = array();
			foreach ( $tokens as $token ) {
				$url = self::image_url( $product, $token, $source );
				if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
					$urls[] = $url;
				}
				if ( count( $urls ) >= self::IMAGE_LIMIT ) {
					break;
				}
			}
			return $urls;
		}

		/**
		 * Image tokens of a product or image set: product_images, else the legacy image1-5 fields.
		 *
		 * @param object $set ec_prodimages or ec_prodimageset.
		 * @return string[]
		 */
		private static function set_tokens( $set ) {
			$list = isset( $set->product_images ) ? $set->product_images : array();
			if ( is_string( $list ) ) {
				$list = ( '' !== $list ) ? explode( ',', $list ) : array();
			}
			$list = array_values( array_filter( array_map( 'trim', (array) $list ), 'strlen' ) );
			if ( $list ) {
				return $list;
			}
			$out = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$field = 'image' . $i;
				if ( isset( $set->$field ) && '' !== trim( (string) $set->$field ) ) {
					$out[] = 'image' . $i;
				}
			}
			return $out;
		}

		/**
		 * One image token as an absolute URL ( '' for videos and placeholders ).
		 *
		 * @param object      $product ec_product.
		 * @param string      $token   image1-5, image:URL, an attachment id, or a video.
		 * @param object|null $set     6.0.2: the option item image set the token belongs to ( its image1-5 are its own
		 *                             pictures, not the product's ); null for the product's own images.
		 * @return string
		 */
		private static function image_url( $product, $token, $set = null ) {
			$token = (string) $token;
			if ( '' === $token || preg_match( '/^(video|youtube|vimeo):/', $token ) ) {
				return '';
			}
			$getters = array(
				'image1' => 'get_first_image_url',
				'image2' => 'get_second_image_url',
				'image3' => 'get_third_image_url',
				'image4' => 'get_fourth_image_url',
				'image5' => 'get_fifth_image_url',
			);
			$url = '';
			if ( is_object( $set ) && preg_match( '/^image([1-5])$/', $token, $match ) ) {
				$url = self::stored_image_url( isset( $set->$token ) ? (string) $set->$token : '', (int) $match[1] );
			} elseif ( isset( $getters[ $token ] ) ) {
				$getter = $getters[ $token ];
				$url    = method_exists( $product, $getter ) ? (string) $product->$getter() : '';
			} elseif ( 'image:' === substr( $token, 0, 6 ) ) {
				$url = (string) apply_filters( 'wp_easycart_product_details_image_url_type', substr( $token, 6 ) );
			} elseif ( ctype_digit( $token ) && function_exists( 'wp_get_attachment_image_url' ) ) {
				$url = (string) wp_get_attachment_image_url( (int) $token, 'full' );
			}
			if ( '' === $url || false !== strpos( $url, 'ec_image_not_found' ) ) {
				return '';
			}
			$default = (string) get_option( 'ec_option_product_image_default' );
			if ( '' !== $default && $url === $default ) {
				return '';
			}
			if ( 0 === strpos( $url, '//' ) ) {
				$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
			}
			return preg_match( '#^https?://#i', $url ) ? esc_url_raw( $url ) : '';
		}

		/**
		 * An image1-5 value as a URL: a full address as is, else the uploaded file in products/pics1-5 ( '' when missing ).
		 *
		 * @since 6.0.2
		 * @param string $file  image1-5 value.
		 * @param int    $index 1-5.
		 * @return string
		 */
		private static function stored_image_url( $file, $index ) {
			$file = trim( (string) $file );
			if ( '' === $file ) {
				return '';
			}
			if ( preg_match( '#^https?://#i', $file ) ) {
				return $file;
			}
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics' . (int) $index . '/' . $file ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics' . (int) $index . '/' . $file ) ) {
				return plugins_url( '/wp-easycart-data/products/pics' . (int) $index . '/' . $file, EC_PLUGIN_DATA_DIRECTORY );
			}
			return '';
		}

		/**
		 * A video token's poster image ( video:URL:::POSTER, youtube:ID:::POSTER, vimeo:ID:::POSTER ), '' when it has none.
		 *
		 * @since 6.0.2
		 * @param string $token Image token.
		 * @return string
		 */
		private static function video_poster( $token ) {
			if ( ! preg_match( '/^(video|youtube|vimeo):(.*)$/s', (string) $token, $match ) ) {
				return '';
			}
			$parts  = explode( ':::', $match[2] );
			$poster = isset( $parts[1] ) ? trim( $parts[1] ) : '';
			return preg_match( '#^https?://#i', $poster ) ? esc_url_raw( $poster ) : '';
		}

		// ------------------------------------------------------------------
		// Variants.
		// ------------------------------------------------------------------

		/**
		 * Which schema.org property an option set describes ( color, size, material, pattern ), or ''.
		 *
		 * @param object $set ec_optionset.
		 * @return string
		 */
		public static function variant_property( $set ) {
			$name = strtolower( trim( (string) ( isset( $set->option_label ) && '' !== (string) $set->option_label ? $set->option_label : ( isset( $set->option_name ) ? $set->option_name : '' ) ) ) );
			$map  = array(
				'color'    => '/\b(colou?rs?|shades?)\b/',
				'size'     => '/\bsizes?\b/',
				'material' => '/\b(materials?|fabrics?)\b/',
				'pattern'  => '/\b(patterns?|prints?)\b/',
			);
			$found = '';
			foreach ( $map as $property => $pattern ) {
				if ( preg_match( $pattern, $name ) ) {
					$found = $property;
					break;
				}
			}
			return (string) apply_filters( 'wp_easycart_product_schema_variant_property', $found, $set );
		}

		/**
		 * One Product per enabled variant, when the product tracks stock per variant.
		 *
		 * @param object      $product    ec_product.
		 * @param array       $pricing    pricing().
		 * @param string      $url        Product address.
		 * @param object|null $attributes Product Google attributes.
		 * @param bool        $keep_rows  Add each item's ec_optionitemquantity row as '_wpec_row' ( see build() ).
		 * @return array|null items and varies_by.
		 */
		public static function variants( $product, $pricing, $url, $attributes, $keep_rows = false ) {
			if ( empty( $product->use_optionitem_quantity_tracking ) || empty( $product->has_options ) || ! isset( $product->options ) || ! is_object( $product->options ) ) {
				return null;
			}
			global $wpdb;
			$limit = (int) apply_filters( 'wp_easycart_product_schema_variant_limit', self::VARIANT_LIMIT, $product );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d AND is_enabled = 1 ORDER BY optionitemquantity_id ASC', (int) $product->product_id ) );
			if ( ! $rows ) {
				return null;
			}
			$sets = array();
			for ( $i = 1; $i <= 5; $i++ ) {
				$set = isset( $product->options->{ 'optionset' . $i } ) ? $product->options->{ 'optionset' . $i } : null;
				if ( ! $set || empty( $set->option_id ) ) {
					continue;
				}
				$items = array();
				foreach ( (array) $set->optionset as $item ) {
					$items[ (int) $item->optionitem_id ] = $item;
				}
				$sets[ $i ] = array(
					'set'      => $set,
					'items'    => $items,
					'property' => self::variant_property( $set ),
				);
			}
			if ( ! $sets ) {
				return null;
			}
			$images   = self::variant_images( $product );
			$currency = self::currency();
			$title    = self::plain( $product->title, 120 );
			$group    = self::group_id( $product, $attributes );
			$varies   = array();
			$out      = array();
			foreach ( $rows as $row ) {
				if ( count( $out ) >= $limit ) {
					break;
				}
				$names      = array();
				$query      = array();
				$properties = array();
				$add        = 0.0;
				$valid      = true;
				for ( $i = 1; $i <= 5; $i++ ) {
					$id = (int) $row->{ 'optionitem_id_' . $i };
					if ( ! $id ) {
						continue;
					}
					if ( ! isset( $sets[ $i ]['items'][ $id ] ) ) {
						$valid = false;
						break;
					}
					$item    = $sets[ $i ]['items'][ $id ];
					$names[] = self::plain( $item->optionitem_name, 60 );
					$query[ 'o' . (int) $sets[ $i ]['set']->option_id ] = (string) $item->optionitem_name;
					$add    += (float) $item->optionitem_price;
					if ( '' !== $sets[ $i ]['property'] ) {
						$properties[ $sets[ $i ]['property'] ] = self::plain( $item->optionitem_name, 60 );
					}
				}
				if ( ! $valid || ! $names ) {
					continue;
				}
				$merchant = isset( $row->google_merchant ) ? json_decode( (string) $row->google_merchant ) : null;
				if ( is_object( $merchant ) ) {
					foreach ( array( 'color', 'size', 'material', 'pattern' ) as $property ) {
						if ( ! empty( $merchant->$property ) ) {
							$properties[ $property ] = self::plain( $merchant->$property, 60 );
						}
					}
				}
				foreach ( array_keys( $properties ) as $property ) {
					$varies[ $property ] = 'https://schema.org/' . $property;
				}

				$variant_url = $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
				/* The sku is the variant's ID in the Google product feed too ( variant_id() ), so the two always match. */
				$variant = array(
					'@type'                => 'Product',
					'name'                 => $title . ' - ' . implode( ' / ', $names ),
					'url'                  => $variant_url,
					'sku'                  => self::variant_id( $product, $row ),
					'inProductGroupWithID' => $group,
				);
				$variant = array_merge( $variant, $properties, self::identifiers( $merchant ) );
				$first   = (int) $row->optionitem_id_1;
				if ( isset( $images[ $first ] ) ) {
					$variant['image'] = $images[ $first ];
				}
				if ( ! $pricing['hidden'] && $pricing['low'] <= 0 ) {
					/* The cart's rule ( ec_cartitem ): a variant price other than -1 replaces the price and the option prices. */
					if ( '' !== (string) $row->price && (float) $row->price >= 0 ) {
						$raw = method_exists( $product, 'get_updated_price' ) ? (float) $product->get_updated_price( $row->price ) : (float) $row->price;
					} else {
						$raw = (float) $pricing['raw'] + $add;
					}
					$price = self::shown( $product, $raw, $pricing['vat'] );
					if ( $price > 0 ) {
						$tracked = ! empty( $row->is_stock_tracking_enabled );
						$offer   = array_merge(
							array(
								'@type'         => 'Offer',
								'price'         => $price,
								'priceCurrency' => $currency,
							),
							self::availability( $product, $tracked, (float) $row->quantity ),
							array(
								'itemCondition' => self::condition( ( is_object( $merchant ) && ! empty( $merchant->condition ) ) ? $merchant : $attributes ),
								'url'           => $variant_url,
								'seller'        => self::seller(),
							)
						);
						if ( $pricing['list'] > $price ) {
							$offer['priceSpecification'] = array(
								'@type'         => 'UnitPriceSpecification',
								'priceType'     => 'https://schema.org/StrikethroughPrice',
								'price'         => $pricing['list'],
								'priceCurrency' => $currency,
							);
						}
						$variant['offers'] = $offer;
					}
				}
				if ( $keep_rows ) {
					$variant['_wpec_row'] = $row;
				}
				$out[] = $variant;
			}
			if ( ! $out ) {
				return null;
			}
			return array(
				'items'     => $out,
				'varies_by' => array_values( $varies ),
			);
		}

		/**
		 * First image of each first-option item's image set, when the product shows images per option item.
		 *
		 * @param object $product ec_product.
		 * @return array optionitem_id => URL.
		 */
		private static function variant_images( $product ) {
			$out = array();
			if ( empty( $product->use_optionitem_images ) || ! isset( $product->images->imageset ) ) {
				return $out;
			}
			foreach ( (array) $product->images->imageset as $set ) {
				$id = (int) $set->optionitem_id;
				if ( ! $id || isset( $out[ $id ] ) ) {
					continue;
				}
				foreach ( self::set_tokens( $set ) as $token ) {
					/* 6.0.2: image1-5 resolve against this set, not the product ( every variant showed the first set's picture ). */
					$url = self::image_url( $product, $token, $set );
					if ( '' !== $url ) {
						$out[ $id ] = $url;
						break;
					}
				}
			}
			return $out;
		}

		// ------------------------------------------------------------------
		// SEO plugins.
		// ------------------------------------------------------------------

		/**
		 * The active SEO plugin, if any.
		 *
		 * @return string yoast | rankmath | aioseo | seopress | tsf | ''
		 */
		public static function seo_plugin() {
			if ( defined( 'WPSEO_VERSION' ) ) {
				return 'yoast';
			}
			if ( defined( 'RANK_MATH_VERSION' ) ) {
				return 'rankmath';
			}
			if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
				return 'aioseo';
			}
			if ( defined( 'SEOPRESS_VERSION' ) ) {
				return 'seopress';
			}
			if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
				return 'tsf';
			}
			return '';
		}

		/**
		 * The SEO plugin's name.
		 *
		 * @param string $slug seo_plugin().
		 * @return string
		 */
		public static function seo_plugin_name( $slug ) {
			$names = array(
				'yoast'    => 'Yoast SEO',
				'rankmath' => 'Rank Math',
				'aioseo'   => 'All in One SEO',
				'seopress' => 'SEOPress',
				'tsf'      => 'The SEO Framework',
			);
			return isset( $names[ $slug ] ) ? $names[ $slug ] : '';
		}

		/**
		 * Does the active SEO plugin own the description and social tags ( so EasyCart prints none )?
		 *
		 * @return bool
		 */
		public static function seo_handoff() {
			/* 6.0.2: only plugins EasyCart can hand the product's description and image to. The SEO Framework has no such
			   filter here, so with it EasyCart keeps printing its own tags, as before 6.0.2. */
			$plugin = self::seo_plugin();
			return '' !== $plugin && 'tsf' !== $plugin && '0' !== (string) get_option( 'ec_option_seo_plugin_handoff', '1' );
		}

		/**
		 * The product row behind this request, for head tags ( an ec_store product page or ?model_number= ).
		 *
		 * @return array|false
		 */
		public static function head_row() {
			if ( null !== self::$head_row ) {
				return self::$head_row;
			}
			self::$head_row = false;
			$model_number   = '';
			$post           = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
			if ( $post && isset( $post->post_content ) && preg_match( '/\[ec_store\s+modelnumber="([^"]+)"/', (string) $post->post_content, $match ) ) {
				$model_number = $match[1];
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which product page this is.
			if ( '' === $model_number && isset( $_GET['model_number'] ) ) {
				$model_number = sanitize_text_field( wp_unslash( $_GET['model_number'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which product page this is.
			}
			if ( '' === $model_number || ! class_exists( 'ec_db' ) ) {
				return false;
			}
			global $wpdb;
			/* get_product_list() caches it for this viewer and this query ( ec_db::product_cache_key() ). */
			$db   = new ec_db();
			$rows = $db->get_product_list( $wpdb->prepare( ' WHERE product.model_number = %s AND product.activate_in_store = 1', $model_number ), '', '', '', 'wpeasycart-product-only-' . $model_number, '', '' );
			if ( is_array( $rows ) && isset( $rows[0] ) && is_array( $rows[0] ) ) {
				self::$head_row = $rows[0];
			}
			return self::$head_row;
		}

		/**
		 * The head description for a product row: its SEO description, else its short or full description.
		 *
		 * @param array $row   Product row.
		 * @param int   $limit Characters.
		 * @return string
		 */
		public static function row_description( $row, $limit = 160 ) {
			foreach ( array( 'seo_description', 'short_description', 'description' ) as $key ) {
				$text = isset( $row[ $key ] ) ? self::plain( $row[ $key ], $limit ) : '';
				if ( '' !== $text ) {
					return $text;
				}
			}
			return '';
		}

		/**
		 * Filter for SEO plugin meta descriptions: the product's description when the plugin has none.
		 *
		 * @param string $description The plugin's description.
		 * @return string
		 */
		public static function fill_description( $description ) {
			if ( '' !== trim( (string) $description ) || ! self::seo_handoff() ) {
				return $description;
			}
			$row = self::head_row();
			if ( $row ) {
				return self::row_description( $row, 160 );
			}
			/* 6.0.2: a menu page's SEO description too, as 6.0.1 printed it. */
			$menu = self::menu_description( 160 );
			return '' !== $menu ? $menu : $description;
		}

		/**
		 * 6.0.2: the SEO description of the store menu page behind this request ( [ec_store menuid / submenuid /
		 * subsubmenuid] in the page, or ?menuid= / ?submenuid= / ?subsubmenuid= ), '' when this is no menu page or it has none.
		 *
		 * @since 6.0.2
		 * @param int $limit Characters.
		 * @return string
		 */
		public static function menu_description( $limit = 160 ) {
			global $wpdb;
			static $found = null;
			if ( null === $found ) {
				$found = '';
				$level = 0;
				$id    = 0;
				$post  = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
				if ( $post && isset( $post->post_content ) && preg_match( '/\[ec_store\s+(subsubmenuid|submenuid|menuid)="(\d+)"/', (string) $post->post_content, $match ) ) {
					$levels = array(
						'menuid'       => 1,
						'submenuid'    => 2,
						'subsubmenuid' => 3,
					);
					$level  = $levels[ $match[1] ];
					$id     = (int) $match[2];
				}
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: which menu page this is.
				if ( ! $level ) {
					if ( isset( $_GET['subsubmenuid'] ) ) {
						$level = 3;
						$id    = (int) $_GET['subsubmenuid'];
					} elseif ( isset( $_GET['submenuid'] ) ) {
						$level = 2;
						$id    = (int) $_GET['submenuid'];
					} elseif ( isset( $_GET['menuid'] ) ) {
						$level = 1;
						$id    = (int) $_GET['menuid'];
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				$text = null;
				if ( $id > 0 && 1 === $level ) {
					$text = $wpdb->get_var( $wpdb->prepare( 'SELECT seo_description FROM ec_menulevel1 WHERE menulevel1_id = %d', $id ) );
				} elseif ( $id > 0 && 2 === $level ) {
					$text = $wpdb->get_var( $wpdb->prepare( 'SELECT seo_description FROM ec_menulevel2 WHERE menulevel2_id = %d', $id ) );
				} elseif ( $id > 0 && 3 === $level ) {
					$text = $wpdb->get_var( $wpdb->prepare( 'SELECT seo_description FROM ec_menulevel3 WHERE menulevel3_id = %d', $id ) );
				}
				$found = (string) $text;
			}
			return '' !== $found ? self::plain( $found, $limit ) : '';
		}

		/**
		 * Filter for SEO plugin social descriptions.
		 *
		 * @param string $description The plugin's description.
		 * @return string
		 */
		public static function fill_social_description( $description ) {
			if ( '' !== trim( (string) $description ) || ! self::seo_handoff() ) {
				return $description;
			}
			$row = self::head_row();
			if ( $row ) {
				return self::row_description( $row, 300 );
			}
			$menu = self::menu_description( 300 );
			return '' !== $menu ? $menu : $description;
		}

		/**
		 * The main image of the head product row.
		 *
		 * @return string
		 */
		public static function row_image() {
			global $wpdb;
			$row = self::head_row();
			if ( ! $row ) {
				return '';
			}
			/* 6.0.2: a product that shows images per option item keeps its pictures in ec_optionitemimage ( its own fields are
			   often empty ): the first option item's set, as 6.0.1's og:image used. */
			$source = $row;
			if ( ! empty( $row['use_optionitem_images'] ) && ! empty( $row['product_id'] ) ) {
				$set = $wpdb->get_row( $wpdb->prepare( 'SELECT optionitemimage.image1, optionitemimage.image2, optionitemimage.image3, optionitemimage.image4, optionitemimage.image5, optionitemimage.product_images FROM ec_optionitemimage AS optionitemimage INNER JOIN ec_optionitem AS optionitem ON optionitem.optionitem_id = optionitemimage.optionitem_id WHERE optionitemimage.product_id = %d ORDER BY optionitem.optionitem_order ASC, optionitemimage.optionitemimage_id ASC LIMIT 1', (int) $row['product_id'] ), ARRAY_A );
				if ( $set ) {
					$source = $set;
				}
			}
			$tokens = ( isset( $source['product_images'] ) && '' !== (string) $source['product_images'] ) ? explode( ',', (string) $source['product_images'] ) : array( 'image1', 'image2', 'image3', 'image4', 'image5' );
			foreach ( $tokens as $token ) {
				$token = trim( $token );
				if ( preg_match( '/^(video|youtube|vimeo):/', $token ) ) {
					/* 6.0.2: a video first shows as its poster, as in 6.0.1. */
					$poster = self::video_poster( $token );
					if ( '' !== $poster ) {
						return $poster;
					}
					continue;
				}
				if ( 'image:' === substr( $token, 0, 6 ) ) {
					/* 6.0.2: through the same filter as the product page ( CDN rewrites ). */
					$url = (string) apply_filters( 'wp_easycart_product_details_image_url_type', substr( $token, 6 ) );
					if ( preg_match( '#^https?://#i', $url ) ) {
						return esc_url_raw( $url );
					}
					continue;
				}
				if ( ctype_digit( $token ) && function_exists( 'wp_get_attachment_image_url' ) ) {
					$url = (string) wp_get_attachment_image_url( (int) $token, apply_filters( 'wp_easycart_product_details_full_size', 'large' ) );
					if ( '' !== $url ) {
						return esc_url_raw( $url );
					}
					continue;
				}
				if ( preg_match( '/^image([1-5])$/', $token, $match ) ) {
					$url = self::stored_image_url( isset( $source[ $token ] ) ? (string) $source[ $token ] : '', (int) $match[1] );
					if ( '' !== $url ) {
						return esc_url_raw( $url );
					}
				}
			}
			return '';
		}

		/**
		 * Filter for SEO plugin social images: the product's main image when the plugin has none.
		 *
		 * @param string $image The plugin's image.
		 * @return string
		 */
		public static function fill_image( $image ) {
			if ( '' !== trim( (string) $image ) || ! self::seo_handoff() ) {
				return $image;
			}
			$url = self::row_image();
			return '' !== $url ? $url : $image;
		}

		/**
		 * All in One SEO ( filter aioseo_facebook_tags ): the product's main image when the page has none.
		 *
		 * @since 6.0.2
		 * @param array $tags property => content.
		 * @return array
		 */
		public static function aioseo_facebook_tags( $tags ) {
			if ( ! is_array( $tags ) || ! self::seo_handoff() || ! empty( $tags['og:image'] ) ) {
				return $tags;
			}
			$url = self::row_image();
			if ( '' !== $url ) {
				$tags['og:image'] = $url;
				if ( 0 === strpos( $url, 'https://' ) ) {
					$tags['og:image:secure_url'] = $url;
				}
			}
			return $tags;
		}

		/**
		 * All in One SEO ( filter aioseo_twitter_tags ): the product's main image when the page has none.
		 *
		 * @since 6.0.2
		 * @param array $tags name => content.
		 * @return array
		 */
		public static function aioseo_twitter_tags( $tags ) {
			if ( ! is_array( $tags ) || ! self::seo_handoff() || ! empty( $tags['twitter:image'] ) ) {
				return $tags;
			}
			$url = self::row_image();
			if ( '' !== $url ) {
				$tags['twitter:image'] = $url;
			}
			return $tags;
		}

		/**
		 * SEOPress ( filter seopress_social_og_thumb, the og:image meta tags as HTML ): the product's main image when the
		 * page has none.
		 *
		 * @since 6.0.2
		 * @param string $html Meta tags.
		 * @return string
		 */
		public static function seopress_og_image( $html ) {
			if ( '' !== trim( (string) $html ) || ! self::seo_handoff() ) {
				return $html;
			}
			$url = self::row_image();
			return '' !== $url ? '<meta property="og:image" content="' . esc_url( $url ) . '" />' . "\n" : $html;
		}

		/**
		 * Yoast SEO: add the product's main image when the page has none.
		 *
		 * @param object $images Yoast's image container.
		 */
		public static function yoast_image( $images ) {
			if ( ! self::seo_handoff() || ! is_object( $images ) || ! method_exists( $images, 'add_image_by_url' ) ) {
				return;
			}
			if ( method_exists( $images, 'has_images' ) && $images->has_images() ) {
				return;
			}
			$url = self::row_image();
			if ( '' !== $url ) {
				$images->add_image_by_url( $url );
			}
		}
	}

	wp_easycart_product_schema::init();

endif;
