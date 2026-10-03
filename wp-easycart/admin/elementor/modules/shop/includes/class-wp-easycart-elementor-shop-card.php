<?php
/**
 * The product card shared by Products, Product Carousel and Shop, and the quick view ( 6.0.2 ).
 *
 * One small block per product: image ( a second image on hover ), badges, category, title, rating, price, colour swatches,
 * short description and the purchase button, each part switchable. Everything comes from the ec_product the list already
 * built; the only extra query is one for the category line of the whole list, when that line is on.
 *
 * The purchase button follows the store's own list rules ( the classic product list, ec_product.php ): catalog display,
 * login for pricing, catalog mode, DecoNetwork, "select options" for products that need choices, subscriptions, add to
 * cart in the background for simple products, backorders and out of stock.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Card' ) ) :

	/**
	 * Product card markup.
	 */
	class WP_EasyCart_Elementor_Shop_Card {

		/**
		 * Card parts and their defaults ( option => default ).
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'image'       => 1,
				'hover_image' => 1,
				'image_size'  => 'medium_large',
				'sizes'       => '',
				'badges'      => 1,
				'sale_badge'  => 'text',
				'category'    => 0,
				'title'       => 1,
				'title_tag'   => 'h3',
				'rating'      => 1,
				'price'       => 1,
				'swatches'    => 1,
				'excerpt'     => 0,
				'button'      => 1,
				'quick_view'  => 1,
				'eager'       => 0,
			);
		}

		/**
		 * Card options from widget settings ( the card controls, see WP_EasyCart_Elementor_Shop_Listing_Widget ).
		 *
		 * @param array $settings Widget settings.
		 * @return array
		 */
		public static function options_from_settings( $settings ) {
			$switch           = function ( $key ) use ( $settings ) {
				return ( isset( $settings[ $key ] ) && 'yes' === $settings[ $key ] ) ? 1 : 0;
			};
			$options          = array(
				'image'       => $switch( 'ec_show_image' ),
				'hover_image' => $switch( 'ec_show_hover_image' ),
				'image_size'  => isset( $settings['ec_image_size'] ) ? (string) $settings['ec_image_size'] : 'medium_large',
				'badges'      => $switch( 'ec_show_badges' ),
				'sale_badge'  => ( isset( $settings['ec_sale_badge'] ) && 'percent' === $settings['ec_sale_badge'] ) ? 'percent' : 'text',
				'category'    => $switch( 'ec_show_category' ),
				'title'       => $switch( 'ec_show_title' ),
				'title_tag'   => isset( $settings['ec_title_tag'] ) ? (string) $settings['ec_title_tag'] : 'h3',
				'rating'      => $switch( 'ec_show_rating' ),
				'price'       => $switch( 'ec_show_price' ),
				'swatches'    => $switch( 'ec_show_swatches' ),
				'excerpt'     => $switch( 'ec_show_excerpt' ),
				'button'      => $switch( 'ec_show_button' ),
				'quick_view'  => $switch( 'ec_show_quick_view' ),
			);
			$options['sizes'] = self::sizes_attribute( $settings );
			return self::sanitize( $options );
		}

		/**
		 * Card options kept to known values ( also for options sent back by "Load more" ).
		 *
		 * @param mixed $raw Options.
		 * @return array
		 */
		public static function sanitize( $raw ) {
			$raw      = is_array( $raw ) ? $raw : array();
			$defaults = self::defaults();
			$options  = array();
			foreach ( $defaults as $key => $default ) {
				$value = array_key_exists( $key, $raw ) ? $raw[ $key ] : $default;
				if ( is_int( $default ) ) {
					$options[ $key ] = empty( $value ) ? 0 : ( 'eager' === $key ? min( 12, absint( $value ) ) : 1 );
				} else {
					$options[ $key ] = is_scalar( $value ) ? (string) $value : $default;
				}
			}
			if ( ! in_array( $options['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ), true ) ) {
				$options['title_tag'] = 'h3';
			}
			if ( ! in_array( $options['sale_badge'], array( 'text', 'percent' ), true ) ) {
				$options['sale_badge'] = 'text';
			}
			if ( 'full' !== $options['image_size'] && ! in_array( $options['image_size'], get_intermediate_image_sizes(), true ) ) {
				$options['image_size'] = 'medium_large';
			}
			/* sizes: widths only ( "(max-width: 767px) 50vw, 25vw" ). */
			$options['sizes'] = preg_replace( '/[^0-9a-z\s\(\)\-:,.\/%]/i', '', $options['sizes'] );
			return $options;
		}

		/**
		 * The sizes attribute for card images from the widget's columns, so the browser picks a file the width of a card.
		 *
		 * @param array $settings Widget settings ( ec_columns, _tablet, _mobile ).
		 * @return string
		 */
		public static function sizes_attribute( $settings ) {
			$desktop = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_columns'] ) ? $settings['ec_columns'] : 4, 1, 8, 4 );
			$tablet  = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_columns_tablet'] ) ? $settings['ec_columns_tablet'] : 3, 1, 8, 3 );
			$mobile  = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_columns_mobile'] ) ? $settings['ec_columns_mobile'] : 2, 1, 8, 2 );
			return '(max-width: 767px) ' . round( 100 / $mobile ) . 'vw, (max-width: 1024px) ' . round( 100 / $tablet ) . 'vw, ' . round( 100 / $desktop ) . 'vw';
		}

		/**
		 * Draws a list of cards.
		 *
		 * @param ec_product[] $products Products.
		 * @param array        $options  Card options.
		 * @param array        $args     'tag' ( li | div ), 'class' ( extra classes ), 'slides' ( carousel slide labels ).
		 */
		public static function render_list( $products, $options, $args = array() ) {
			$args       = wp_parse_args(
				$args,
				array(
					'tag'    => 'li',
					'class'  => '',
					'slides' => false,
				)
			);
			$categories = ! empty( $options['category'] ) ? self::categories_for( $products ) : array();
			$total      = count( $products );
			foreach ( array_values( $products ) as $index => $product ) {
				$attrs = array();
				if ( $args['slides'] ) {
					$attrs['role']                 = 'group';
					$attrs['aria-roledescription'] = 'slide';
					$attrs['aria-label']           = WP_EasyCart_Elementor_Shop::fill(
						WP_EasyCart_Elementor_Shop::text( 'shop_carousel_slide', '[index] of [total]' ),
						array(
							'index' => $index + 1,
							'total' => $total,
						)
					);
				}
				self::render(
					$product,
					$options,
					array(
						'tag'        => $args['tag'],
						'class'      => $args['class'],
						'attrs'      => $attrs,
						'index'      => $index,
						'categories' => isset( $categories[ (int) $product->product_id ] ) ? $categories[ (int) $product->product_id ] : array(),
					)
				);
			}
		}

		/**
		 * Draws one card.
		 *
		 * @param ec_product $product Product.
		 * @param array      $options Card options ( sanitize() ).
		 * @param array      $args    'tag', 'class', 'attrs', 'index', 'categories'.
		 */
		public static function render( $product, $options, $args = array() ) {
			$tag   = ( isset( $args['tag'] ) && 'div' === $args['tag'] ) ? 'div' : 'li';
			$index = isset( $args['index'] ) ? (int) $args['index'] : 0;
			$link  = $product->get_product_link();
			$title = trim( wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ) );
			$class = 'wpec-card' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
			if ( ! $product->in_stock() && ! $product->allow_backorders ) {
				$class .= ' is-out-of-stock';
			}
			if ( 3 === (int) get_option( 'ec_option_pickup_location_unavailable', 3 ) && self::unavailable_here( $product ) ) {
				$class .= ' is-unavailable'; /* "Show but disable purchase": dimmed, as the classic list. */
			}

			echo '<' . $tag . ' class="' . esc_attr( $class ) . '" data-wpec-product="' . esc_attr( (int) $product->product_id ) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is li or div.
			if ( ! empty( $args['attrs'] ) && is_array( $args['attrs'] ) ) {
				foreach ( $args['attrs'] as $name => $value ) {
					echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
				}
			}
			echo '>';

			if ( ! empty( $options['image'] ) ) {
				echo '<div class="wpec-card__media">';
				echo '<a class="wpec-card__image-link" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">';
				$images = self::images( $product, ! empty( $options['hover_image'] ) ? 2 : 1 );
				$eager  = ( $index < (int) $options['eager'] );
				if ( isset( $images[0] ) ) {
					self::print_image( $images[0], $options, $title, 'wpec-card__image', $eager );
				}
				if ( isset( $images[1] ) ) {
					self::print_image( $images[1], $options, '', 'wpec-card__image wpec-card__image--hover', false );
				}
				echo '</a>';
				if ( ! empty( $options['badges'] ) ) {
					self::print_badges( $product, $options );
				}
				if ( ! empty( $options['quick_view'] ) && self::quick_view_allowed() ) {
					echo '<button type="button" class="wpec-card__quick-view" data-wpec-quick-view="' . esc_attr( (int) $product->product_id ) . '" aria-haspopup="dialog">';
					echo esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_quick_view', 'Quick View' ) );
					echo '<span class="wpec-sr-only">: ' . esc_html( $title ) . '</span></button>';
				}
				echo '</div>';
			}

			echo '<div class="wpec-card__body">';
			if ( ! empty( $options['category'] ) && ! empty( $args['categories'] ) ) {
				echo '<div class="wpec-card__category">' . esc_html( implode( ', ', array_slice( $args['categories'], 0, 2 ) ) ) . '</div>';
			}
			if ( ! empty( $options['title'] ) ) {
				$title_tag = $options['title_tag'];
				echo '<' . $title_tag . ' class="wpec-card__title"><a href="' . esc_url( $link ) . '">' . wp_easycart_escape_html( $product->title ) . '</a></' . $title_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $title_tag is one of a fixed list ( sanitize() ).
			}
			if ( ! empty( $options['rating'] ) ) {
				self::print_rating( $product );
			}
			if ( ! empty( $options['price'] ) ) {
				$price = self::price_html( $product );
				if ( '' !== $price ) {
					echo '<div class="wpec-card__price wpec-price">' . $price . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in price_html().
				}
			}
			if ( ! empty( $options['swatches'] ) ) {
				self::print_swatches( $product, $link );
			}
			if ( ! empty( $options['excerpt'] ) && '' !== trim( (string) $product->short_description ) ) {
				echo '<div class="wpec-card__excerpt">' . wp_easycart_escape_html( nl2br( stripslashes( $product->short_description ) ) ) . '</div>';
			}
			if ( ! empty( $options['button'] ) ) {
				$action = self::action_html( $product );
				if ( '' !== $action ) {
					echo '<div class="wpec-card__actions">' . $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in action_html().
				}
			}
			echo '</div>';
			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is li or div.
		}

		/**
		 * Whether quick view may open on this device ( the store's own list turns it off on iPhones and iPads ).
		 *
		 * @return bool
		 */
		public static function quick_view_allowed() {
			return (bool) apply_filters( 'wp_easycart_elementor_quick_view_allowed', true );
		}

		/**
		 * Up to $count images of a product, the list the product page opens with first.
		 *
		 * @param ec_product $product Product.
		 * @param int        $count   How many.
		 * @return array Each array( 'id' => attachment id or 0, 'url' => URL when there is no attachment ).
		 */
		public static function images( $product, $count = 2 ) {
			$list    = array();
			$entries = ( isset( $product->images->product_images ) && is_array( $product->images->product_images ) ) ? $product->images->product_images : array();
			foreach ( $entries as $entry ) {
				$entry = trim( (string) $entry );
				if ( '' === $entry ) {
					continue;
				}
				if ( preg_match( '/^image([1-5])$/', $entry, $match ) ) {
					$url = self::legacy_image_url( $product, (int) $match[1] );
					if ( '' !== $url ) {
						$list[] = array(
							'id'  => 0,
							'url' => $url,
						);
					}
				} elseif ( 0 === strpos( $entry, 'image:' ) ) {
					$list[] = array(
						'id'  => 0,
						'url' => (string) apply_filters( 'wp_easycart_product_details_image_url_type', substr( $entry, 6 ) ),
					);
				} elseif ( preg_match( '/^(video|youtube|vimeo):(.*)$/', $entry, $match ) ) {
					$parts = explode( ':::', $match[2] );
					if ( isset( $parts[1] ) && '' !== trim( $parts[1] ) ) {
						$list[] = array(
							'id'  => 0,
							'url' => trim( $parts[1] ),
						);
					}
				} elseif ( ctype_digit( $entry ) ) {
					$list[] = array(
						'id'  => (int) $entry,
						'url' => '',
					);
				}
				if ( count( $list ) >= $count ) {
					break;
				}
			}
			if ( empty( $list ) ) {
				$list[] = array(
					'id'  => 0,
					'url' => $product->get_first_image_url(),
				);
				if ( $count > 1 && isset( $product->images->image2 ) && '' !== trim( (string) $product->images->image2 ) ) {
					$list[] = array(
						'id'  => 0,
						'url' => $product->get_second_image_url(),
					);
				}
			}
			return array_slice( $list, 0, $count );
		}

		/**
		 * The URL of a product's image1-image5.
		 *
		 * @param ec_product $product Product.
		 * @param int        $number  1-5.
		 * @return string
		 */
		private static function legacy_image_url( $product, $number ) {
			$methods = array(
				1 => 'get_first_image_url',
				2 => 'get_second_image_url',
				3 => 'get_third_image_url',
				4 => 'get_fourth_image_url',
				5 => 'get_fifth_image_url',
			);
			if ( ! isset( $methods[ $number ] ) || ! method_exists( $product, $methods[ $number ] ) ) {
				return '';
			}
			return (string) call_user_func( array( $product, $methods[ $number ] ) );
		}

		/**
		 * Prints one image: a media library image with srcset, else a plain one. Lazy unless $eager.
		 *
		 * @param array  $image   From images().
		 * @param array  $options Card options.
		 * @param string $alt     Alt text.
		 * @param string $classes Classes.
		 * @param bool   $eager   Load at once ( the first row of a store page ).
		 */
		public static function print_image( $image, $options, $alt, $classes, $eager ) {
			$loading = $eager ? 'eager' : 'lazy';
			if ( ! empty( $image['id'] ) ) {
				$attrs = array(
					'class'    => $classes,
					'alt'      => $alt,
					'loading'  => $loading,
					'decoding' => 'async',
				);
				if ( '' !== $options['sizes'] ) {
					$attrs['sizes'] = $options['sizes'];
				}
				$html = wp_get_attachment_image( (int) $image['id'], $options['image_size'], false, $attrs );
				if ( '' !== $html ) {
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escapes its attributes.
					return;
				}
				return;
			}
			if ( '' === $image['url'] ) {
				return;
			}
			echo '<img class="' . esc_attr( $classes ) . '" src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="' . esc_attr( $loading ) . '" decoding="async" />';
		}

		/**
		 * Badges: an offer's badge ( Offers ), the product's own tag, sale ( or its percentage ), sold out.
		 *
		 * @param ec_product $product Product.
		 * @param array      $options Card options.
		 */
		private static function print_badges( $product, $options ) {
			$badges = array();
			/* The badge an offer puts on product tiles ( the classic list draws it from wp_easycart_product_image_holder_pre ). */
			if ( function_exists( 'wp_easycart_offers_active' ) && function_exists( 'wp_easycart_offers_template' ) && wp_easycart_offers_active() ) {
				ob_start();
				wp_easycart_offers_template(
					'ec_offer_product_badge.php',
					array(
						'badge_product_id'      => (int) $product->product_id,
						'badge_manufacturer_id' => isset( $product->manufacturer_id ) ? (int) $product->manufacturer_id : 0,
						'badge_price'           => (float) $product->price,
						'badge_list_price'      => isset( $product->list_price ) ? (float) $product->list_price : 0,
					)
				);
				$offer = trim( (string) ob_get_clean() );
				if ( '' !== $offer ) {
					$badges[] = $offer; /* The Offers template's own markup ( as the Product Badges widget prints it ). */
				}
			}
			if ( ! empty( $product->tag_type ) && '' !== trim( (string) $product->tag_text ) ) {
				$style = '';
				if ( preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $product->tag_bg_color ) ) {
					$style .= '--wpec-tag-bg:' . $product->tag_bg_color . ';';
				}
				if ( preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $product->tag_text_color ) ) {
					$style .= '--wpec-tag-color:' . $product->tag_text_color . ';';
				}
				$badges[] = '<span class="wpec-badge wpec-badge--tag"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>' . esc_html( wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $product->tag_text ), ENT_QUOTES, 'UTF-8' ) ) ) . '</span>';
			}
			if ( self::price_visible( $product ) && self::on_sale( $product ) ) {
				if ( 'percent' === $options['sale_badge'] ) {
					$percent  = (int) round( ( (float) $product->list_price - (float) $product->price ) / (float) $product->list_price * 100 );
					$badges[] = '<span class="wpec-badge wpec-badge--sale">' . esc_html( WP_EasyCart_Elementor_Shop::fill( WP_EasyCart_Elementor_Shop::text( 'shop_percent_off', '-[percent]%' ), array( 'percent' => $percent ) ) ) . '</span>';
				} else {
					$badges[] = '<span class="wpec-badge wpec-badge--sale">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sale', 'Sale' ) ) . '</span>';
				}
			}
			if ( ! $product->in_stock() && ! $product->allow_backorders ) {
				$badges[] = '<span class="wpec-badge wpec-badge--sold-out">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sold_out', 'Sold out' ) ) . '</span>';
			}
			if ( ! empty( $badges ) ) {
				echo '<div class="wpec-card__badges">' . implode( '', $badges ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each badge is escaped above ( the offer badge by its template ).
			}
		}

		/**
		 * Whether a product is on sale ( a regular price above its price ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function on_sale( $product ) {
			return ! $product->show_custom_price_range && (float) $product->list_price > 0 && (float) $product->list_price > (float) $product->price;
		}

		/**
		 * Whether the shopper may see the price ( login for pricing, catalog and inquiry price hiding ).
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function price_visible( $product ) {
			if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
				return false;
			}
			if ( ( $product->is_catalog_mode && get_option( 'ec_option_hide_price_seasonal' ) ) || ( $product->is_inquiry_mode && get_option( 'ec_option_hide_price_inquiry' ) ) ) {
				return false;
			}
			return true;
		}

		/**
		 * The price as the store's product list shows it ( VAT note, price label, price range, sale ), escaped.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function price_html( $product ) {
			if ( ! self::price_visible( $product ) || ! isset( $GLOBALS['currency'] ) ) {
				return '';
			}
			$currency = $GLOBALS['currency'];
			if ( $product->show_custom_price_range ) {
				if ( $product->price_range_high > 0 ) {
					$text = $currency->get_currency_display( $product->price_range_low ) . ' – ' . $currency->get_currency_display( $product->price_range_high );
				} else {
					$from = $product->is_subscription_item ? $product->get_option_price_formatted( $product->price_range_low, 1 ) : $currency->get_currency_display( $product->price_range_low );
					$text = WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_starting_at', 'Starting at' ) . ' ' . $from;
				}
				return '<span class="wpec-price__current">' . esc_html( $text ) . '</span>';
			}

			$labels_on = in_array( (int) $product->enable_price_label, array( 1, 4, 5, 7 ), true );
			if ( $product->replace_price_label && $labels_on ) {
				$current = '<span class="wpec-price__current">' . wp_easycart_escape_html( $product->custom_price_label ) . '</span>';
			} else {
				$display = $product->is_subscription_item ? $product->get_price_formatted( 1 ) : $currency->get_currency_display( $product->price );
				if ( $product->pricing_per_sq_foot ) {
					$display .= get_option( 'ec_option_enable_metric_unit_display' ) ? '/sq m' : '/sq ft';
				}
				$display = apply_filters( 'wp_easycart_product_price_display', $display, $product->price, $product->product_id );
				$current = esc_html( wp_strip_all_tags( html_entity_decode( (string) $display, ENT_QUOTES, 'UTF-8' ) ) );
				if ( self::on_sale( $product ) ) {
					$regular = apply_filters( 'wp_easycart_product_list_price_display', $currency->get_currency_display( $product->list_price ), $product->list_price );
					$current = '<del class="wpec-price__regular"><span class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_regular_price', 'Regular price:' ) ) . ' </span>' . esc_html( wp_strip_all_tags( html_entity_decode( (string) $regular, ENT_QUOTES, 'UTF-8' ) ) ) . '</del> '
						. '<ins class="wpec-price__current wpec-price__current--sale"><span class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_sale_price', 'Sale price:' ) ) . ' </span>' . $current . '</ins>';
				} else {
					$current = '<span class="wpec-price__current">' . $current . '</span>';
				}
				if ( $labels_on && '' !== trim( (string) $product->custom_price_label ) ) {
					$current .= ' <span class="wpec-price__label">' . wp_easycart_escape_html( $product->custom_price_label ) . '</span>';
				}
			}
			if ( 1 === (int) $product->vat_rate && get_option( 'ec_option_show_multiple_vat_pricing' ) ) {
				if ( ! empty( $GLOBALS['ec_vat_included'] ) ) {
					$current .= ' <span class="wpec-price__note">' . esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_inc_vat_text', 'inc VAT' ) ) . '</span>';
				} elseif ( ! empty( $GLOBALS['ec_vat_added'] ) ) {
					$current .= ' <span class="wpec-price__note">' . esc_html( WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_excluding_vat_text', 'excluding VAT' ) ) . '</span>';
				}
			}
			return $current;
		}

		/**
		 * The average rating and number of reviews, or null when the product has none ( or reviews are off for it ).
		 *
		 * @param ec_product $product Product.
		 * @return array|null array( average, count ).
		 */
		public static function rating( $product ) {
			if ( empty( $product->use_customer_reviews ) || empty( $product->reviews ) || ! is_array( $product->reviews ) ) {
				return null;
			}
			$total = 0;
			$count = 0;
			foreach ( $product->reviews as $review ) {
				if ( isset( $review->rating ) ) {
					$total += (float) $review->rating;
					++$count;
				}
			}
			if ( ! $count ) {
				return null;
			}
			return array( round( $total / $count, 1 ), $count );
		}

		/**
		 * Prints the stars ( one element, drawn in CSS ) and the review count.
		 *
		 * @param ec_product $product Product.
		 */
		public static function print_rating( $product ) {
			$rating = self::rating( $product );
			if ( ! $rating ) {
				return;
			}
			$label = WP_EasyCart_Elementor_Shop::fill( WP_EasyCart_Elementor_Shop::text( 'shop_rated', 'Rated [rating] out of 5' ), array( 'rating' => $rating[0] ) );
			$count = WP_EasyCart_Elementor_Shop::fill( ( 1 === $rating[1] ) ? WP_EasyCart_Elementor_Shop::text( 'shop_review_one', '[count] review' ) : WP_EasyCart_Elementor_Shop::text( 'shop_review_many', '[count] reviews' ), array( 'count' => $rating[1] ) );
			echo '<div class="wpec-card__rating">';
			echo '<span class="wpec-stars" role="img" aria-label="' . esc_attr( $label ) . '" style="--wpec-rating:' . esc_attr( round( $rating[0] / 5 * 100 ) ) . '%"></span>';
			echo '<span class="wpec-card__review-count"><span aria-hidden="true">(' . esc_html( $rating[1] ) . ')</span><span class="wpec-sr-only">' . esc_html( $count ) . '</span></span>';
			echo '</div>';
		}

		/**
		 * Colour swatches of the product's first swatch option ( shown only; each links to the product with it chosen ).
		 *
		 * @param ec_product $product Product.
		 * @param string     $link    Product URL.
		 * @param int        $max     Most swatches shown.
		 */
		public static function print_swatches( $product, $link, $max = 5 ) {
			$items = self::swatch_items( $product );
			if ( count( $items ) < 1 ) {
				return;
			}
			echo '<ul class="wpec-card__swatches" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_options', 'Available options' ) ) . '">';
			foreach ( array_slice( $items, 0, $max ) as $item ) {
				$url = add_query_arg( 'o' . (int) $item['option_id'], rawurlencode( $item['name'] ), $link );
				echo '<li><a class="wpec-swatch" href="' . esc_url( $url ) . '" tabindex="-1" title="' . esc_attr( $item['name'] ) . '">';
				echo '<img src="' . esc_url( ec_optionitem::swatch_src( $item['icon'], 40 ), ec_optionitem::swatch_url_protocols() ) . '" alt="' . esc_attr( $item['name'] ) . '" width="20" height="20" loading="lazy" decoding="async" />';
				echo '</a></li>';
			}
			if ( count( $items ) > $max ) {
				echo '<li class="wpec-card__swatches-more">' . esc_html( WP_EasyCart_Elementor_Shop::fill( WP_EasyCart_Elementor_Shop::text( 'shop_more_options', '+[count]' ), array( 'count' => count( $items ) - $max ) ) ) . '</li>';
			}
			echo '</ul>';
		}

		/**
		 * Items of the product's first swatch option set ( basic or advanced ), from the data the product already holds.
		 *
		 * @param ec_product $product Product.
		 * @return array Each array( option_id, name, icon ).
		 */
		public static function swatch_items( $product ) {
			if ( ! class_exists( 'ec_optionitem' ) || ! method_exists( 'ec_optionitem', 'swatch_src' ) ) {
				return array();
			}
			$items = array();
			if ( ( $product->use_advanced_optionset || $product->use_both_option_types ) && is_array( $product->advanced_optionsets ) ) {
				foreach ( $product->advanced_optionsets as $optionset ) {
					if ( isset( $optionset->option_type ) && 'swatch' === $optionset->option_type ) {
						foreach ( (array) $product->get_advanced_optionitems( $optionset->option_id ) as $optionitem ) {
							if ( '' !== trim( (string) $optionitem->optionitem_icon ) ) {
								$items[] = array(
									'option_id' => (int) $optionset->option_id,
									'name'      => wp_strip_all_tags( html_entity_decode( (string) $optionitem->optionitem_name, ENT_QUOTES, 'UTF-8' ) ),
									'icon'      => (string) $optionitem->optionitem_icon,
								);
							}
						}
						return $items;
					}
				}
			}
			if ( ! $product->use_advanced_optionset && ! empty( $product->has_options ) && isset( $product->options ) && is_object( $product->options ) ) {
				for ( $level = 1; $level <= 5; $level++ ) {
					$optionset = isset( $product->options->{ 'optionset' . $level } ) ? $product->options->{ 'optionset' . $level } : null;
					if ( ! $optionset || ! method_exists( $optionset, 'is_swatch' ) || ! $optionset->is_swatch() ) {
						continue;
					}
					foreach ( $optionset->optionset as $optionitem ) {
						if ( '' === trim( (string) $optionitem->optionitem_icon ) || ! $product->options->verify_optionitem( $level, $optionitem->optionitem_id ) ) {
							continue;
						}
						$items[] = array(
							'option_id' => (int) $optionset->option_id,
							'name'      => wp_strip_all_tags( html_entity_decode( (string) $optionitem->optionitem_name, ENT_QUOTES, 'UTF-8' ) ),
							'icon'      => (string) $optionitem->optionitem_icon,
						);
					}
					return $items;
				}
			}
			return $items;
		}

		/**
		 * Category names for every product of a list, in one query.
		 *
		 * @param ec_product[] $products Products.
		 * @return array product id => names.
		 */
		public static function categories_for( $products ) {
			global $wpdb;
			$ids = array();
			foreach ( (array) $products as $product ) {
				$ids[] = (int) $product->product_id;
			}
			$ids = WP_EasyCart_Elementor_Shop_Query::ids( $ids );
			if ( empty( $ids ) ) {
				return array();
			}
			$rows = $wpdb->get_results( 'SELECT wpec_ci.product_id, wpec_c.category_name FROM ec_categoryitem AS wpec_ci INNER JOIN ec_category AS wpec_c ON wpec_c.category_id = wpec_ci.category_id AND wpec_c.is_active = 1 WHERE wpec_ci.product_id IN (' . implode( ',', $ids ) . ') ORDER BY wpec_c.priority DESC, wpec_c.category_name ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the ids are integers ( WP_EasyCart_Elementor_Shop_Query::ids() ).
			$map  = array();
			foreach ( (array) $rows as $row ) {
				$map[ (int) $row->product_id ][] = wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $row->category_name ), ENT_QUOTES, 'UTF-8' ) );
			}
			return $map;
		}

		/**
		 * What the card offers the shopper to do, escaped: add to cart, choose options, sign up, sign in, or a note.
		 *
		 * @param ec_product $product  Product.
		 * @param bool       $quantity Offer a quantity field with the add button ( quick view ).
		 * @return string
		 */
		public static function action_html( $product, $quantity = false ) {
			$action = self::action( $product );
			$title  = trim( wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ) );
			/* The visible label, and the product's name for screen readers ( every card has the same button ). */
			$label = '<span class="wpec-button__label">' . esc_html( $action['label'] ) . '</span><span class="wpec-sr-only">: ' . esc_html( $title ) . '</span>';
			/* A backordered product says so under its button, with the date it is expected ( the classic list's note ). */
			$after = ! empty( $action['backorder'] ) ? self::backorder_note( $product ) : '';
			switch ( $action['type'] ) {
				case 'note':
					return '<p class="wpec-card__note' . ( ! empty( $action['class'] ) ? ' ' . esc_attr( $action['class'] ) : '' ) . '">' . esc_html( $action['label'] ) . '</p>';
				case 'link':
					return '<a class="wpec-button wpec-card__button" href="' . esc_url( $action['url'] ) . '"' . ( ! empty( $action['rel'] ) ? ' rel="' . esc_attr( $action['rel'] ) . '"' : '' ) . self::track_attribute( $product, $action ) . '>' . $label . '</a>' . $after;
				case 'add':
					$html  = '';
					$input = '';
					if ( $quantity ) {
						$input = 'wpec-qty-' . (int) $product->product_id . '-' . wp_rand( 1000, 999999 );
						$min   = max( 1, (int) $product->min_purchase_quantity );
						$max   = ( (int) $product->max_purchase_quantity > 0 ) ? (int) $product->max_purchase_quantity : ( ( $product->show_stock_quantity && ! $product->allow_backorders && (int) $product->stock_quantity > 0 ) ? (int) $product->stock_quantity : 0 );
						$html .= '<div class="wpec-qty"><label for="' . esc_attr( $input ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_quantity', 'Quantity' ) ) . '</label>';
						$html .= '<input type="number" id="' . esc_attr( $input ) . '" class="wpec-qty__input" value="' . esc_attr( $min ) . '" min="' . esc_attr( $min ) . '"' . ( $max ? ' max="' . esc_attr( $max ) . '"' : '' ) . ' step="1" inputmode="numeric" /></div>';
					}
					$html .= '<button type="button" class="wpec-button wpec-card__button" data-wpec-add="' . esc_attr( (int) $product->product_id ) . '" data-wpec-model="' . esc_attr( $product->model_number ) . '" data-wpec-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-add-to-cart-' . (int) $product->product_id ) ) . '" data-wpec-href="' . esc_url( $product->get_add_to_cart_link() ) . '" data-wpec-link="' . esc_url( $product->get_product_link() ) . '" data-wpec-name="' . esc_attr( $title ) . '"' . ( '' !== $input ? ' data-wpec-qty-input="' . esc_attr( $input ) . '"' : '' ) . self::track_attribute( $product, $action ) . '>' . $label . '</button>' . $after;
					return $html;
			}
			return '';
		}

		/**
		 * "Out of stock until <date>" for a backordered product ( the date only when the store set one ), escaped.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		public static function backorder_note( $product ) {
			$text = WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_out_of_stock', 'Out of Stock' );
			$date = isset( $product->backorder_fill_date ) ? trim( wp_strip_all_tags( html_entity_decode( (string) $product->backorder_fill_date, ENT_QUOTES, 'UTF-8' ) ) ) : '';
			if ( '' !== $date ) {
				$text .= ' ' . WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_backorder_until', 'until' ) . ' ' . $date;
			}
			return '<p class="wpec-card__note wpec-card__note--backorder">' . esc_html( $text ) . '</p>';
		}

		/**
		 * Whether the shopper chose a pickup location that does not carry the product ( Settings › Checkout › pickup
		 * locations, with the location menu on ). Such products are left out of lists ( "Hide" ), or shown without a way to
		 * buy them, as the product page does.
		 *
		 * @param ec_product $product Product.
		 * @return bool
		 */
		public static function unavailable_here( $product ) {
			return get_option( 'ec_option_pickup_enable_locations' ) && get_option( 'ec_option_pickup_location_select_enabled' ) && method_exists( $product, 'at_current_location' ) && ! $product->at_current_location();
		}

		/**
		 * The card's purchase action ( the classic product list's rules ).
		 *
		 * @param ec_product $product Product.
		 * @return array array( 'type' => none | note | link | add, 'label', 'url', 'rel', 'track', 'backorder', 'class' ).
		 */
		public static function action( $product ) {
			$none = array(
				'type'  => 'none',
				'label' => '',
				'url'   => '',
			);
			if ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ) {
				return $none;
			}
			$signed_in = ( isset( $GLOBALS['ec_user'] ) && ! empty( $GLOBALS['ec_user']->user_id ) );
			if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
				if ( $signed_in ) {
					return array(
						'type'  => 'note',
						'label' => WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_login_for_price_no_access', 'Your account does not have access to pricing for this product.' ),
						'url'   => '',
					);
				}
				$label = trim( wp_strip_all_tags( (string) $product->login_for_pricing_label ) );
				return array(
					'type'  => 'link',
					'label' => ( '' !== $label ) ? $label : WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_page_login_for_price', 'Login for Pricing' ),
					'url'   => $product->account_page,
				);
			}
			if ( $product->is_catalog_mode ) {
				$phrase = trim( wp_strip_all_tags( (string) $product->catalog_mode_phrase ) );
				return ( '' !== $phrase ) ? array(
					'type'  => 'note',
					'label' => $phrase,
					'url'   => '',
				) : $none;
			}
			if ( self::unavailable_here( $product ) ) {
				/* The product page offers no way to buy it at this location either. */
				return array(
					'type'  => 'note',
					'label' => WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_not_at_location', 'Product Unavailable at Selected Location' ),
					'url'   => '',
					'class' => 'wpec-card__note--location',
				);
			}
			if ( $product->is_deconetwork ) {
				return array(
					'type'  => 'link',
					'label' => WP_EasyCart_Elementor_Shop::store_text( 'product_page', 'product_design_now', 'Design Now' ),
					'url'   => $product->get_deconetwork_link(),
				);
			}
			$in_stock  = $product->in_stock();
			$backorder = ( ! $in_stock && $product->allow_backorders );
			if ( ( $in_stock || $product->allow_backorders ) && ( $product->has_options() || $product->is_giftcard || $product->is_inquiry_mode || $product->is_donation || $product->min_purchase_quantity > 1 || apply_filters( 'wp_easycart_product_force_select_options', false, $product->product_id ) ) ) {
				return array(
					'type'      => 'link',
					'label'     => WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_select_options', 'Select Options' ),
					'url'       => $product->get_product_link(),
					'backorder' => $backorder,
				);
			}
			if ( $in_stock && $product->is_subscription_item ) {
				return array(
					'type'  => 'link',
					'label' => WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_sign_up_now', 'Sign Up Now' ),
					'url'   => $product->get_subscription_link(),
				);
			}
			if ( $in_stock || $product->allow_backorders ) {
				$label = $in_stock
					? apply_filters( 'wp_easycart_product_details_add_to_cart_value', WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_add_to_cart', 'Add to Cart' ), $product->product_id )
					: WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_backorder_button', 'Backorder' );
				$label = trim( wp_strip_all_tags( html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' ) ) );
				if ( ! apply_filters( 'wp_easycart_product_show_add_to_cart_button', true, $product ) ) {
					return array(
						'type'      => 'link',
						'label'     => WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_select_options', 'Select Options' ),
						'url'       => $product->get_product_link(),
						'backorder' => $backorder,
					);
				}
				if ( get_option( 'ec_option_redirect_add_to_cart' ) ) {
					/* The store sends shoppers to the cart after an add: the public add-to-cart link does that. */
					return array(
						'type'      => 'link',
						'label'     => $label,
						'url'       => $product->get_add_to_cart_link(),
						'rel'       => 'nofollow',
						'track'     => 1,
						'backorder' => $backorder,
					);
				}
				return array(
					'type'      => 'add',
					'label'     => $label,
					'url'       => $product->get_add_to_cart_link(),
					'track'     => 1,
					'backorder' => $backorder,
				);
			}
			return array(
				'type'  => 'note',
				'label' => WP_EasyCart_Elementor_Shop::store_text( 'product_details', 'product_details_out_of_stock', 'Out of Stock' ),
				'url'   => '',
			);
		}

		/**
		 * Analytics data for an add ( Meta Pixel, Google Analytics 4 ), only when the store uses them.
		 *
		 * @param ec_product $product Product.
		 * @param array      $action  From action().
		 * @return string A data attribute with a leading space, or ''.
		 */
		private static function track_attribute( $product, $action ) {
			if ( empty( $action['track'] ) ) {
				return '';
			}
			$title = wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) );
			$track = array();
			if ( function_exists( 'wp_easycart_meta_pixel_id' ) && function_exists( 'wp_easycart_meta_content_id' ) && '' !== wp_easycart_meta_pixel_id() ) {
				$track['meta'] = array(
					'id'   => wp_easycart_meta_content_id( $product->product_id ),
					'name' => $title,
				);
				if ( self::price_visible( $product ) ) {
					$track['meta']['price'] = round( (float) $product->price, 2 );
				}
			}
			if ( '' !== (string) get_option( 'ec_option_google_ga4_property_id' ) && isset( $GLOBALS['currency'] ) ) {
				$track['ga4'] = array(
					'model'    => (string) $product->model_number,
					'title'    => $title,
					'price'    => number_format( (float) $product->price, 2, '.', '' ),
					'currency' => wp_easycart_base_currency_code(),
					'brand'    => (string) $product->manufacturer_name,
					'gtm'      => get_option( 'ec_option_google_ga4_tag_manager' ) ? 1 : 0,
				);
			}
			if ( empty( $track ) ) {
				return '';
			}
			return ' data-wpec-track="' . esc_attr( wp_json_encode( $track ) ) . '"';
		}

		/**
		 * The quick view's content ( fetched when the shopper opens it, so its add button carries a fresh nonce ).
		 *
		 * @param ec_product $product Product.
		 * @return string Escaped markup.
		 */
		public static function quick_view_html( $product ) {
			$link    = $product->get_product_link();
			$title   = trim( wp_strip_all_tags( html_entity_decode( (string) $product->title, ENT_QUOTES, 'UTF-8' ) ) );
			$options = self::sanitize(
				array(
					'image_size' => 'large',
					'sizes'      => '(max-width: 767px) 90vw, 480px',
					'eager'      => 1,
				)
			);
			ob_start();
			echo '<div class="wpec-qv">';
			echo '<div class="wpec-qv__media">';
			$images = self::images( $product, 1 );
			if ( isset( $images[0] ) ) {
				self::print_image( $images[0], $options, $title, 'wpec-qv__image', true );
			}
			self::print_badges( $product, $options );
			echo '</div><div class="wpec-qv__summary">';
			echo '<h2 class="wpec-qv__title" id="wpec-qv-title"><a href="' . esc_url( $link ) . '">' . wp_easycart_escape_html( $product->title ) . '</a></h2>';
			self::print_rating( $product );
			$price = self::price_html( $product );
			if ( '' !== $price ) {
				echo '<div class="wpec-qv__price wpec-price">' . $price . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in price_html().
			}
			if ( '' !== trim( (string) $product->short_description ) ) {
				echo '<div class="wpec-qv__excerpt">' . wp_easycart_escape_html( nl2br( stripslashes( $product->short_description ) ) ) . '</div>';
			}
			$action = self::action_html( $product, true );
			if ( '' !== $action ) {
				echo '<div class="wpec-qv__actions">' . $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in action_html().
			}
			echo '<a class="wpec-qv__details" href="' . esc_url( $link ) . '">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_view_details', 'View full details' ) ) . '</a>';
			echo '</div></div>';
			return (string) ob_get_clean();
		}
	}

endif;
