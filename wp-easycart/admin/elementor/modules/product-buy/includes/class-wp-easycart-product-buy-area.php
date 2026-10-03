<?php
/**
 * The add to cart area of the Elementor Add to Cart widget ( 6.0.2 ).
 *
 * Drawn inside EasyCart's add to cart form ( ec_product_details_page_add_to_cart.php, through templates/add-to-cart-area.php )
 * when the widget passes $wpec_el_buy, in place of the classic area. It covers every state the classic templates do ( the v1
 * shortcode template's list, which is the more complete one, plus the pickup location gate ): store catalog display, log in
 * for pricing, catalog mode, pickup location, inquiry, DecoNetwork, subscription, in stock, backorder, and out of stock with
 * the back-in-stock form. The ids and classes ec-store.js reads are kept: #ec_quantity_*, .ec_details_quantity data-*,
 * .ec_minus / .ec_plus, #ec_final_price_*, #ec_base_price_*, the inquiry and back-in-stock field ids.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Product_Buy_Area' ) ) :

	/**
	 * Add to Cart widget area ( static ).
	 */
	class WP_EasyCart_Product_Buy_Area {

		/**
		 * Language text as the language class returns it ( its own markup allowed ).
		 *
		 * @param string $section Section.
		 * @param string $key     Key.
		 * @return string
		 */
		private static function lang( $section, $key ) {
			return function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( $section, $key ) : '';
		}

		/**
		 * Draws the area.
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    The form's instance number.
		 * @param array      $buy     The widget's choices ( WP_EasyCart_Elementor_Add_To_Cart_Widget::buy_args() ).
		 * @param array      $form    From the form: has_quantity_grid, override_price_grid, add_price_grid, selected_location.
		 */
		public static function render( $product, $rand, $buy, $form ) {
			$pid    = (int) $product->product_id;
			$key    = $pid . '_' . $rand;
			$qstyle = ( ! empty( $form['has_quantity_grid'] ) ) ? 'none' : $buy['quantity'];
			$icons  = array(
				'icon'          => isset( $buy['button_icon'] ) ? (string) $buy['button_icon'] : '',
				'icon_position' => isset( $buy['icon_position'] ) ? $buy['icon_position'] : 'before',
			);
			$qicons = array(
				'minus' => isset( $buy['minus_icon'] ) ? (string) $buy['minus_icon'] : '',
				'plus'  => isset( $buy['plus_icon'] ) ? (string) $buy['plus_icon'] : '',
			);
			$pixel  = ( '' !== trim( (string) get_option( 'ec_option_fb_pixel' ) ) );
			/* The add button's validation, as the classic template does it ( Meta's AddToCart wrapper when the pixel is on ). */
			$validate = ( $pixel ) ? 'return wp_easycart_facebook_add_to_cart_track_' . $key . '( this );' : 'return ec_details_add_to_cart( ' . $pid . ', ' . (int) $rand . ' );';
			$can_add  = ( $product->in_stock() || ( $product->allow_backorders && $product->use_optionitem_quantity_tracking ) || apply_filters( 'wp_easycart_product_details_allow_add_to_cart', false, $product->product_id ) );

			if ( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ) {
				/* The whole store shows as a catalog ( vacation mode ): its message, when the store wrote one. */
				if ( '' !== (string) get_option( 'ec_option_vacation_mode_button_text' ) ) {
					$text = apply_filters( 'wp_easycart_vacation_mode_text', wp_easycart_language()->convert_text( get_option( 'ec_option_vacation_mode_button_text' ) ), $product->product_id );
					echo '<div class="ec_seasonal_mode wpec-atc__note">' . wp_kses_post( wp_easycart_escape_html( $text ) ) . '</div>';
				}
			} elseif ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
				$login = WP_EasyCart_Product_Buy::login_for_price( $product );
				if ( '' === $login['url'] ) {
					echo '<div class="ec_seasonal_mode wpec-atc__note">' . esc_html( wp_strip_all_tags( $login['text'] ) ) . '</div>';
				} else {
					echo '<div class="wpec-atc__actions">';
					WP_EasyCart_Product_Buy::print_button(
						array(
							'label' => $login['text'],
							'href'  => $login['url'],
							'class' => 'wpec-atc__button--login',
						)
					);
					echo '</div>';
				}
			} elseif ( $product->is_catalog_mode ) {
				echo '<div class="ec_details_seasonal_mode wpec-atc__note">' . esc_html( $product->catalog_mode_phrase ) . '</div>';
			} elseif ( get_option( 'ec_option_pickup_enable_locations' ) && get_option( 'ec_option_pickup_location_select_enabled' ) && ! $product->at_current_location() ) {
				self::location( $product, $form );
			} elseif ( $product->is_inquiry_mode ) {
				self::inquiry( $product, $rand );
			} elseif ( $product->is_deconetwork ) {
				echo '<div class="wpec-atc__actions">';
				if ( get_option( 'ec_option_deconetwork_allow_blank_products' ) ) {
					WP_EasyCart_Product_Buy::print_quantity(
						$product,
						$rand,
						array_merge(
							$qicons,
							array(
								'style'    => $qstyle,
								'max'      => ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : ( $product->show_stock_quantity ? $product->stock_quantity : 0 ),
								'data_max' => ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : $product->stock_quantity,
							)
						)
					);
					WP_EasyCart_Product_Buy::print_button(
						array_merge(
							$icons,
							array(
								'label'   => self::add_label( $product, $buy ),
								'onclick' => $validate,
							)
						)
					);
				}
				WP_EasyCart_Product_Buy::print_button(
					array(
						'label' => self::lang( 'product_page', 'product_design_now' ),
						'href'  => $product->get_deconetwork_link(),
						'class' => get_option( 'ec_option_deconetwork_allow_blank_products' ) ? 'wpec-atc__button--secondary' : '',
					)
				);
				echo '</div>';
				self::base_price( $product, $key );
			} elseif ( $product->is_subscription_item ) {
				/* Subscription: the form posts to the subscription checkout ( never in the background ). */
				ob_start();
				do_action( 'wp_easycart_product_details_subscription_button_onclick', $product );
				$subscription_js = trim( (string) ob_get_clean() );
				echo '<div class="wpec-atc__actions">';
				if ( ! get_option( 'ec_option_subscription_one_only' ) ) {
					WP_EasyCart_Product_Buy::print_quantity(
						$product,
						$rand,
						array_merge(
							$qicons,
							array(
								'style'    => $qstyle,
								'max'      => ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : ( $product->show_stock_quantity ? $product->stock_quantity : 0 ),
								'data_max' => ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : ( $product->show_stock_quantity ? $product->stock_quantity : 10000000 ),
							)
						)
					);
				} else {
					echo '<input type="hidden" id="' . esc_attr( 'ec_quantity_' . $key ) . '" value="1" />';
				}
				WP_EasyCart_Product_Buy::print_button(
					array_merge(
						$icons,
						array(
							'label'   => self::lang( 'product_details', 'product_details_sign_up_now' ),
							'onclick' => $subscription_js . $validate,
						)
					)
				);
				echo '</div>';
				self::base_price( $product, $key );
			} elseif ( $can_add || $product->allow_backorders ) {
				self::add( $product, $rand, $buy, $form, $can_add, $validate, $qstyle, $icons, $qicons );
			} else {
				self::out_of_stock( $product, $rand );
			}

			/* The widget's stock note ( the same line as the Product Stock widget, following the chosen option ). */
			if ( ! empty( $buy['stock_note'] ) && ! $product->is_catalog_mode && ! $product->is_inquiry_mode ) {
				WP_EasyCart_Product_Buy::print_stock(
					$product,
					array(
						'rand'  => WP_EasyCart_Product_Buy::next_rand(),
						'class' => 'wpec-atc__stock',
					)
				);
			}
			/* Where the widget says what happened after a background add ( product-buy.js fills it ). */
			echo '<div class="wpec-atc__status" role="status" aria-live="polite" hidden></div>';
		}

		/**
		 * The add button text: the widget's, else the store's.
		 *
		 * @param ec_product $product Product.
		 * @param array      $buy     Widget choices.
		 * @return string
		 */
		private static function add_label( $product, $buy ) {
			if ( '' !== $buy['button_text'] ) {
				return $buy['button_text'];
			}
			return (string) apply_filters( 'wp_easycart_product_details_add_to_cart_value', self::lang( 'product_details', 'product_details_add_to_cart' ), $product->product_id );
		}

		/**
		 * The hidden base price ec-store.js reads.
		 *
		 * @param ec_product $product Product.
		 * @param string     $key     Product id _ instance number.
		 */
		private static function base_price( $product, $key ) {
			echo '<span class="ec_details_hidden_base_price" id="' . esc_attr( 'ec_base_price_' . $key ) . '">' . esc_html( $product->price ) . '</span>';
		}

		/**
		 * Not stocked at the pickup location the shopper chose.
		 *
		 * @param ec_product $product Product.
		 * @param array      $form    Form values ( selected_location ).
		 */
		private static function location( $product, $form ) {
			if ( '' === (string) $product->pickup_locations ) {
				echo '<div class="ec_product_no_locations_notice wpec-atc__note">' . wp_kses_post( self::lang( 'product_details', 'product_unavailable_all_locations' ) ) . '</div>';
				return;
			}
			echo '<div class="ec_product_not_at_location_notice wpec-atc__note">' . wp_kses_post( self::lang( 'product_details', 'product_details_not_at_location' ) ) . '</div>';
			$location = ( ! empty( $GLOBALS['ec_cart_data']->cart_data->pickup_location ) && ! empty( $form['selected_location'] ) ) ? $form['selected_location']->location_label . ', ' : '';
			echo '<button class="ec_product_select_location ec_product_select_location_product wpec-atc__button wpec-atc__button--secondary" type="button" data-product-id="' . esc_attr( $product->product_id ) . '"><span class="wpec-atc__button-text">' . esc_html( $location . wp_strip_all_tags( self::lang( 'product_details', 'find_this_product' ) ) ) . '</span></button>';
		}

		/**
		 * Inquiry: the built-in form posts to the store, which emails it ( ec_cartpage::process_send_inquiry() ). With the
		 * built-in form switched off, the product's inquiry URL is a plain link: nothing the shopper typed is sent there.
		 * The buttons carry no icon: the widget's icon is a cart ( by default ), and an inquiry adds nothing to the cart.
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    Instance number.
		 */
		private static function inquiry( $product, $rand ) {
			$pid     = (int) $product->product_id;
			$key     = $pid . '_' . $rand;
			$form_on = ( get_option( 'ec_option_use_inquiry_form' ) || '' === (string) $product->inquiry_url );
			if ( $form_on ) {
				if ( class_exists( 'wp_easycart_inquiry_guard' ) ) {
					wp_easycart_inquiry_guard::print_notice( $product->product_id );
				}
				echo '<div class="ec_details_option_row_error ec_inquiry_error" id="' . esc_attr( 'ec_details_inquiry_error_' . $key ) . '">' . wp_kses_post( self::lang( 'ec_errors', 'missing_inquiry_options' ) ) . '</div>';
				echo '<div class="wpec-atc__inquiry">';
				$fields = array(
					'name'    => array( 'text', 'product_details_inquiry_name', 'name' ),
					'email'   => array( 'email', 'product_details_inquiry_email', 'email' ),
					'message' => array( 'textarea', 'product_details_inquiry_message', '' ),
				);
				foreach ( $fields as $field => $spec ) {
					$id = 'ec_inquiry_' . $field . '_' . $key;
					echo '<div class="ec_details_option_row wpec-atc__field">';
					echo '<label class="ec_details_option_label" for="' . esc_attr( $id ) . '">' . wp_kses_post( self::lang( 'product_details', $spec[1] ) ) . '</label>';
					echo '<div class="ec_details_option_data">';
					if ( 'textarea' === $spec[0] ) {
						echo '<textarea name="' . esc_attr( 'ec_inquiry_' . $field ) . '" id="' . esc_attr( $id ) . '" rows="4"></textarea>';
					} else {
						echo '<input type="' . esc_attr( $spec[0] ) . '" name="' . esc_attr( 'ec_inquiry_' . $field ) . '" id="' . esc_attr( $id ) . '" value="" autocomplete="' . esc_attr( $spec[2] ) . '" />';
					}
					echo '</div></div>';
				}
				echo '<div class="ec_details_option_row wpec-atc__field wpec-atc__field--check"><label for="' . esc_attr( 'ec_inquiry_send_copy_' . $key ) . '"><input type="checkbox" name="ec_inquiry_send_copy" id="' . esc_attr( 'ec_inquiry_send_copy_' . $key ) . '" /> ' . wp_kses_post( self::lang( 'product_details', 'product_details_inquiry_send_copy' ) ) . '</label></div>';
				$site_key = (string) get_option( 'ec_option_recaptcha_site_key' );
				if ( wp_easycart_recaptcha_ready() && '' !== $site_key ) { // 6.0.2: both keys saved
					echo '<input type="hidden" id="ec_grecaptcha_response_inquiry" name="ec_grecaptcha_response_inquiry" value="" />';
					echo '<input type="hidden" id="ec_grecaptcha_site_key" value="' . esc_attr( $site_key ) . '" />';
					echo '<div class="ec_cart_input_row" data-sitekey="' . esc_attr( $site_key ) . '" id="ec_product_details_inquiry_recaptcha"></div>';
				}
				echo '</div>';
				echo '<div class="wpec-atc__actions">';
				WP_EasyCart_Product_Buy::print_button(
					array(
						'label'   => self::lang( 'product_details', 'product_details_inquire' ),
						'onclick' => 'return ec_details_submit_inquiry( ' . $pid . ', ' . (int) $rand . ' );',
						'class'   => 'wpec-atc__button--inquiry',
					)
				);
				echo '</div>';
			} else {
				echo '<div class="wpec-atc__actions">';
				WP_EasyCart_Product_Buy::print_button(
					array(
						'label' => self::lang( 'product_details', 'product_details_inquire' ),
						'href'  => $product->inquiry_url,
						'class' => 'wpec-atc__button--inquiry',
					)
				);
				echo '</div>';
			}
			echo '<input type="hidden" name="ec_cart_form_action" value="send_inquiry" />';
			if ( ! wp_doing_ajax() ) {
				wp_referer_field( true ); /* Back to this page after sending, whatever Referer the browser sends ( not in AJAX-drawn forms: the address would be admin-ajax.php ). */
			}
			echo '<input type="hidden" name="ec_cart_form_nonce" value="' . esc_attr( wp_create_nonce( 'wp-easycart-send-inquiry' ) ) . '" />';
			echo '<input type="hidden" name="ec_inquiry_model_number" value="' . esc_attr( $product->model_number ) . '" />';
			if ( class_exists( 'wp_easycart_inquiry_guard' ) ) {
				wp_easycart_inquiry_guard::print_fields( $product->product_id );
			}
			self::base_price( $product, $key );
		}

		/**
		 * In stock ( or sold per option with backorders ), else out of stock with backorders allowed.
		 *
		 * @param ec_product $product  Product.
		 * @param int        $rand     Instance number.
		 * @param array      $buy      Widget choices.
		 * @param array      $form     Form values.
		 * @param bool       $can_add  In stock.
		 * @param string     $validate Button validation JavaScript.
		 * @param string     $qstyle   Quantity style.
		 * @param array      $icons    Button icon.
		 * @param array      $qicons   Quantity icons.
		 */
		private static function add( $product, $rand, $buy, $form, $can_add, $validate, $qstyle, $icons, $qicons ) {
			$key = (int) $product->product_id . '_' . $rand;
			if ( ! $can_add ) {
				$label    = self::lang( 'product_details', 'product_details_backorder_button' );
				$max      = ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : 0;
				$data_max = 100000000;
			} else {
				$label    = self::add_label( $product, $buy );
				$max      = ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : ( ( ! $product->allow_backorders && $product->show_stock_quantity ) ? $product->stock_quantity : 0 );
				$data_max = ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : $product->stock_quantity;
			}
			echo '<div class="wpec-atc__actions">';
			WP_EasyCart_Product_Buy::print_quantity(
				$product,
				$rand,
				array_merge(
					$qicons,
					array(
						'style'    => $qstyle,
						'max'      => $max,
						'data_max' => $data_max,
					)
				)
			);
			WP_EasyCart_Product_Buy::print_button(
				array_merge(
					$icons,
					array(
						'label'   => $label,
						'onclick' => $validate,
						'class'   => 'wpec-atc__button--add',
					)
				)
			);
			if ( ! empty( $buy['buy_now'] ) ) {
				WP_EasyCart_Product_Buy::print_button(
					array(
						'label'   => ( '' !== $buy['buy_now_text'] ) ? $buy['buy_now_text'] : WP_EasyCart_Product_Buy::text( 'buy_now', __( 'Buy now', 'wp-easycart' ) ),
						'onclick' => $validate,
						'class'   => 'wpec-atc__button--buy-now',
						'attrs'   => array( 'data-wpec-buy-now' => '1' ),
					)
				);
			}
			echo '</div>';
			if ( $product->has_options || $product->use_advanced_optionset || $product->use_both_option_types ) {
				if ( $form['override_price_grid'] > -1 ) {
					$price = $form['override_price_grid'];
				} elseif ( $form['add_price_grid'] > 0 ) {
					$price = $product->price + $form['add_price_grid'];
				} else {
					$price = $product->price;
				}

				/*
				 * 6.0.2: the price the shopper pays: after a running offer, unless the widget asks for the price before it.
				 * data-wpec-offer-* let product-buy.js work the offer out again when the options change the price.
				 */
				$attrs = '';
				if ( ! isset( $buy['price_offers'] ) || 'before' !== $buy['price_offers'] ) {
					$offer = WP_EasyCart_Product_Buy::offer_preview( $product, (float) $price, (float) $product->list_price );
					if ( false !== $offer ) {
						$attrs = ' data-wpec-offer="1" data-wpec-offer-base="' . esc_attr( (float) $price ) . '"';
						$rules = WP_EasyCart_Product_Buy::offer_rules( $product, (float) $price, (float) $product->list_price );
						if ( is_array( $rules ) ) {
							$attrs .= ' data-wpec-offer-rules="' . esc_attr( wp_json_encode( $rules ) ) . '"';
						}
						$price = $offer;
					}
				}
				$label = ( isset( $buy['price_label'] ) && '' !== $buy['price_label'] ) ? esc_html( $buy['price_label'] ) : wp_kses_post( self::lang( 'product_details', 'product_details_your_price' ) );
				echo '<div class="ec_details_final_price_ele wpec-atc__your-price"' . $attrs . ( empty( $buy['your_price'] ) ? ' hidden' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs escaped where built.
				echo '<span class="wpec-atc__your-price-label">' . $label . '</span> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				echo '<span id="' . esc_attr( 'ec_final_price_' . $key ) . '" class="wpec-atc__your-price-value" aria-live="polite">' . esc_html( $GLOBALS['currency']->get_currency_display( $price ) ) . '</span>';
				echo '</div>';
			}
			self::base_price( $product, $key );
		}

		/**
		 * Out of stock: the message, and the back-in-stock form when the store offers it ( ec_notify_submit() ids ).
		 *
		 * @param ec_product $product Product.
		 * @param int        $rand    Instance number.
		 */
		private static function out_of_stock( $product, $rand ) {
			$pid = (int) $product->product_id;
			$key = $pid . '_' . $rand;
			echo '<div class="ec_out_of_stock wpec-atc__note wpec-atc__note--out">' . wp_kses_post( self::lang( 'product_details', 'product_details_out_of_stock' ) ) . '</div>';
			if ( ! get_option( 'ec_option_enable_inventory_notification' ) ) {
				return;
			}
			$notify   = 'ec_product_details_stock_notify_' . $key;
			$email    = 'ec_email_notify_' . $key;
			$site_key = (string) get_option( 'ec_option_recaptcha_site_key' );
			$nonce    = wp_create_nonce( 'wp-easycart-subscribe-to-stock-notification-' . $pid );
			echo '<div class="ec_cart_success wpec-atc__notify-done" style="display:none;" id="' . esc_attr( 'ec_product_details_stock_notify_complete_' . $key ) . '" role="status"><div>' . wp_kses_post( self::lang( 'product_details', 'product_details_notify_subscribe_success' ) ) . '</div></div>';
			echo '<div class="ec_out_of_stock_notify wpec-atc__notify" id="' . esc_attr( $notify ) . '">';
			echo '<div class="ec_out_of_stock_notify_loader_cover" style="display:none;" id="' . esc_attr( $notify . '_loader_cover' ) . '"></div>';
			echo '<div class="ec_out_of_stock_notify_loader" style="display:none;" id="' . esc_attr( $notify . '_loader' ) . '"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>';
			echo '<label class="ec_out_of_stock_notify_title" for="' . esc_attr( $email ) . '">' . wp_kses_post( self::lang( 'product_details', 'product_details_notify_subscribe_title' ) ) . '</label>';
			echo '<div class="ec_out_of_stock_notify_input">';
			echo '<div class="ec_cart_error_row" id="' . esc_attr( $email . '_error' ) . '">' . wp_kses_post( self::lang( 'cart_form_notices', 'cart_notice_please_enter_valid' ) . ' ' . self::lang( 'cart_contact_information', 'cart_contact_information_email' ) ) . '</div>';
			echo '<input type="email" id="' . esc_attr( $email ) . '" value="" autocomplete="email" placeholder="' . esc_attr( wp_strip_all_tags( self::lang( 'product_details', 'product_details_notify_subscribe_email_placeholder' ) ) ) . '" />';
			echo '</div>';
			if ( wp_easycart_recaptcha_ready() && '' !== $site_key ) { // 6.0.2: both keys saved
				echo '<div class="ec_out_of_stock_notify_grecaptcha">';
				echo '<input type="hidden" id="ec_grecaptcha_response_product_details" name="ec_grecaptcha_response_product_details" value="" />';
				echo '<input type="hidden" id="ec_grecaptcha_site_key" value="' . esc_attr( $site_key ) . '" />';
				echo '<div class="ec_cart_input_row" data-sitekey="' . esc_attr( $site_key ) . '" id="ec_product_details_recaptcha"></div>';
				echo '</div>';
			}
			echo '<div class="ec_out_of_stock_notify_button">';
			echo '<button type="button" class="wpec-atc__button wpec-atc__button--secondary" onclick="' . esc_attr( 'ec_notify_submit( ' . $pid . ', ' . (int) $rand . ', \'' . $nonce . '\' );' ) . '"><span class="wpec-atc__button-text">' . esc_html( wp_strip_all_tags( self::lang( 'product_details', 'product_details_notify_subscribe_button_title' ) ) ) . '</span></button>';
			echo '</div>';
			echo '</div>';
		}
	}

endif;
