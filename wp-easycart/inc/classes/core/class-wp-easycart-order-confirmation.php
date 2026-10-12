<?php
/**
 * The order confirmation page's order summary ( 6.0.4 ).
 *
 * Matt asked for a modern order confirmation page for every store: the page said thank you and gave two links, nothing about the
 * order itself. Under the thank-you card it now shows what was bought ( pictures, options, quantities, prices ) with the order's
 * totals, the order's details ( date, payment, shipping method, email, coupon, gift card, VAT number, notes ), the shipping and
 * billing addresses, and how to pay a bank transfer order that is not paid yet ( the store's instructions, and its pay link when the
 * store takes payment online ).
 *
 * ec_cart_success.php prints it ( print_summary(), print_payment(), print_info() ); store copies of that template made before 6.0.4
 * do not, and stay as they were. The rows follow the order's page in My Account ( ec_account_order_details.php ), in the store's own
 * wording: phrases the receipt email and the Elementor Order Confirmation widget already use, so nothing new needs translating. That
 * widget draws its own items, totals and addresses, so it turns this off ( filter wp_easycart_success_page_summary ).
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_confirmation' ) ) :

	/**
	 * What the order confirmation page shows about the order.
	 *
	 * @since 6.0.4
	 */
	final class wp_easycart_order_confirmation {

		/**
		 * Whether the confirmation page shows the order summary for this order.
		 *
		 * @param object $order ec_orderdisplay.
		 * @return bool
		 */
		public static function enabled( $order ) {
			/**
			 * Whether the order confirmation page shows the order's items, totals, details and addresses under the thank-you card.
			 *
			 * @since 6.0.4
			 * @param bool   $show  True.
			 * @param object $order ec_orderdisplay.
			 */
			return is_object( $order ) && (bool) apply_filters( 'wp_easycart_success_page_summary', true, $order );
		}

		/**
		 * A phrase as plain text, else the English.
		 *
		 * @param string $section  Language section.
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string
		 */
		public static function text( $section, $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? trim( wp_strip_all_tags( (string) wp_easycart_language()->get_text( $section, $key ) ) ) : '';
			return ( '' !== $text ) ? $text : $fallback;
		}

		/**
		 * A label phrase without the colon My Account's rows end with.
		 *
		 * @param string $section  Language section.
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string
		 */
		public static function label( $section, $key, $fallback ) {
			return trim( rtrim( self::text( $section, $key, $fallback ), ': ' ) );
		}

		/**
		 * An amount in the shopper's currency, as the rest of the page shows it.
		 *
		 * @param float $amount Base currency.
		 * @return string Plain text.
		 */
		private static function money( $amount ) {
			return isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? trim( html_entity_decode( wp_strip_all_tags( (string) $GLOBALS['currency']->get_currency_display( (float) $amount ) ), ENT_QUOTES, 'UTF-8' ) ) : number_format( (float) $amount, 2 );
		}

		/**
		 * What a display method of the order prints, as plain text.
		 *
		 * @param object $source Order or order line.
		 * @param string $method Method.
		 * @param array  $args   Arguments.
		 * @return string
		 */
		private static function shown( $source, $method, $args = array() ) {
			if ( ! is_object( $source ) || ! method_exists( $source, $method ) ) {
				return '';
			}
			ob_start();
			call_user_func_array( array( $source, $method ), $args );
			return trim( html_entity_decode( wp_strip_all_tags( (string) ob_get_clean() ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The order's lines: picture, title, options, quantity and line total ( as My Account shows the line total ).
		 *
		 * @param object $order ec_orderdisplay.
		 * @return array[] Each: id, title, sku, options ( strings ), quantity, total, image ( address or '' ).
		 */
		public static function lines( $order ) {
			$lines = array();
			if ( ! is_object( $order ) || empty( $order->orderdetails ) || ! is_array( $order->orderdetails ) ) {
				return $lines;
			}
			foreach ( $order->orderdetails as $detail ) {
				if ( ! is_object( $detail ) ) {
					continue;
				}
				$lines[] = array(
					'id'       => isset( $detail->orderdetail_id ) ? (int) $detail->orderdetail_id : 0,
					'title'    => self::shown( $detail, 'display_title' ),
					'sku'      => isset( $detail->model_number ) ? trim( wp_strip_all_tags( stripslashes( (string) $detail->model_number ) ) ) : '',
					'options'  => self::line_options( $detail ),
					'quantity' => isset( $detail->quantity ) ? (int) $detail->quantity : 1,
					'total'    => method_exists( $detail, 'display_item_total' ) ? self::shown( $detail, 'display_item_total' ) : self::money( isset( $detail->total_price ) ? $detail->total_price : 0 ),
					'image'    => self::line_image( $detail ),
				);
			}
			/**
			 * The order confirmation page's lines.
			 *
			 * @since 6.0.4
			 * @param array[] $lines Lines.
			 * @param object  $order ec_orderdisplay.
			 */
			return (array) apply_filters( 'wp_easycart_success_page_lines', $lines, $order );
		}

		/**
		 * A line's details as short text, as My Account lists them under the title: a free gift, the choices with what they add to the
		 * price, the advanced options as "Label: value" with theirs, a gift card's message, sender and recipient, a signup fee.
		 *
		 * @param object $detail ec_orderdetail.
		 * @return string[]
		 */
		private static function line_options( $detail ) {
			$options = array();
			if ( ! empty( $detail->is_free_gift ) ) {
				$options[] = self::text( 'cart_offers', 'gift_line_label', 'Free gift' );
			}
			if ( empty( $detail->use_advanced_optionset ) || ! empty( $detail->use_both_option_types ) ) {
				for ( $i = 1; $i <= 5; $i++ ) {
					$key = 'optionitem_name_' . $i;
					if ( isset( $detail->$key ) && '' !== trim( (string) $detail->$key ) ) {
						$price     = 'optionitem_price_' . $i;
						$add       = ( isset( $detail->$price ) && 0.0 !== (float) $detail->$price ) ? self::shown( $detail, 'display_option' . $i . '_price' ) : '';
						$options[] = trim( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $detail->$key ) ) ) . ( ( '' !== $add ) ? ' (' . $add . ')' : '' );
					}
				}
			}
			if ( ( ! empty( $detail->use_advanced_optionset ) || ! empty( $detail->use_both_option_types ) ) && ! empty( $detail->orderdetail_id ) && class_exists( 'ec_db' ) ) {
				$db = isset( $GLOBALS['ec_db'] ) && is_object( $GLOBALS['ec_db'] ) && method_exists( $GLOBALS['ec_db'], 'get_order_options' ) ? $GLOBALS['ec_db'] : new ec_db();
				foreach ( (array) $db->get_order_options( (int) $detail->orderdetail_id ) as $option ) {
					if ( ! is_object( $option ) ) {
						continue;
					}
					$label = isset( $option->option_label ) ? trim( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $option->option_label ) ) ) : '';
					$type  = isset( $option->option_type ) ? (string) $option->option_type : '';
					$value = isset( $option->option_value ) ? (string) $option->option_value : '';
					if ( 'file' === $type ) {
						$parts = explode( '/', $value );
						$value = (string) end( $parts );
					} elseif ( 'grid' === $type ) {
						$name  = isset( $option->optionitem_name ) ? trim( (string) $option->optionitem_name ) : '';
						$value = ( '' !== $name ) ? $name . ( ( '' !== trim( $value ) ) ? ' (' . trim( $value ) . ')' : '' ) : $value;
					}
					$value = trim( wp_strip_all_tags( wp_easycart_language()->convert_text( $value ) ) );
					if ( '' === $value ) {
						continue;
					}
					$note      = self::option_price_note( $option );
					$options[] = ( ( '' !== $label ) ? $label . ': ' . $value : $value ) . ( ( '' !== $note ) ? ' ' . $note : '' );
				}
			}
			if ( ! empty( $detail->is_giftcard ) ) {
				foreach ( array(
					'gift_card_message'   => array( 'account_orders_details_gift_message', 'Message' ),
					'gift_card_from_name' => array( 'account_orders_details_gift_from', 'From' ),
					'gift_card_to_name'   => array( 'account_orders_details_gift_to', 'To' ),
				) as $property => $phrase ) {
					$value = isset( $detail->$property ) ? trim( wp_strip_all_tags( stripslashes( (string) $detail->$property ) ) ) : '';
					if ( '' !== $value ) {
						$options[] = self::label( 'account_order_details', $phrase[0], $phrase[1] ) . ': ' . $value;
					}
				}
			}
			if ( isset( $detail->subscription_signup_fee ) && (float) $detail->subscription_signup_fee > 0 ) {
				$options[] = trim( self::text( 'product_details', 'product_details_signup_fee_notice1', 'Signup fee:' ) . ' ' . self::money( $detail->subscription_signup_fee ) );
			}
			return array_slice( $options, 0, 16 );
		}

		/**
		 * What an advanced option did to the price, as My Account words it: the store's own price label, a change per item or per
		 * order, or a new price.
		 *
		 * @param object $option ec_order_option row.
		 * @return string '' when it changed nothing.
		 */
		private static function option_price_note( $option ) {
			$each   = isset( $option->optionitem_price ) ? (float) $option->optionitem_price : 0.0;
			$once   = isset( $option->optionitem_price_onetime ) ? (float) $option->optionitem_price_onetime : 0.0;
			$per    = self::text( 'cart', 'cart_item_adjustment', '' );
			$order  = self::text( 'cart', 'cart_order_adjustment', '' );
			$signed = function ( $amount, $words ) {
				return '(' . ( ( $amount > 0 ) ? '+' : '' ) . self::money( $amount ) . ( ( '' !== $words ) ? ' ' . $words : '' ) . ')';
			};
			if ( ! empty( $option->optionitem_enable_custom_price_label ) && ( 0.0 !== $each || 0.0 !== $once ) ) {
				return trim( wp_strip_all_tags( isset( $option->optionitem_custom_price_label ) ? (string) $option->optionitem_custom_price_label : '' ) );
			}
			if ( 0.0 !== $each ) {
				return $signed( $each, $per );
			}
			if ( 0.0 !== $once ) {
				return $signed( $once, $order );
			}
			if ( isset( $option->optionitem_price_override ) && null !== $option->optionitem_price_override && '' !== (string) $option->optionitem_price_override && (float) $option->optionitem_price_override > -1 ) {
				return '(' . trim( self::text( 'cart', 'cart_item_new_price_option', '' ) . ' ' . self::money( $option->optionitem_price_override ) ) . ')';
			}
			return '';
		}

		/**
		 * A line's picture ( the one the order saved ), or ''.
		 *
		 * @param object $detail ec_orderdetail.
		 * @return string Address.
		 */
		public static function line_image( $detail ) {
			if ( ! empty( $detail->is_deconetwork ) && ! empty( $detail->deconetwork_image_link ) ) {
				return 'https://' . get_option( 'ec_option_deconetwork_url' ) . $detail->deconetwork_image_link;
			}
			$image = isset( $detail->image1 ) ? trim( (string) $detail->image1 ) : '';
			if ( '' === $image ) {
				return '';
			}
			if ( 0 === strpos( $image, '//' ) ) {
				return ( is_ssl() ? 'https:' : 'http:' ) . $image;
			}
			if ( 0 === strpos( $image, 'http://' ) || 0 === strpos( $image, 'https://' ) ) {
				return $image;
			}
			if ( false !== strpos( $image, '/' ) || false !== strpos( $image, '\\' ) || false !== strpos( $image, '..' ) ) {
				return '';
			}
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && is_file( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image ) ) {
				return plugins_url( 'wp-easycart-data/products/pics1/' . $image, EC_PLUGIN_DATA_DIRECTORY );
			}
			if ( defined( 'EC_PLUGIN_DIRECTORY' ) && is_file( EC_PLUGIN_DIRECTORY . '/products/pics1/' . $image ) ) {
				return plugins_url( 'wp-easycart/products/pics1/' . $image, EC_PLUGIN_DIRECTORY );
			}
			return '';
		}

		/**
		 * The order's totals, the rows My Account shows in the same order.
		 *
		 * @param object $order ec_orderdisplay.
		 * @return array[] Each: key, label, amount ( plain text ).
		 */
		public static function totals( $order ) {
			$rows = array();
			$add  = function ( $key, $label, $amount ) use ( &$rows ) {
				$rows[] = array(
					'key'    => $key,
					'label'  => $label,
					'amount' => $amount,
				);
			};
			$add( 'subtotal', self::label( 'cart_success', 'cart_payment_complete_order_totals_subtotal', 'Subtotal' ), self::shown( $order, 'display_sub_total' ) );
			if ( isset( $order->tip_total ) && (float) $order->tip_total > 0 ) {
				$add( 'tip', self::label( 'cart_totals', 'cart_totals_tip', 'Tip' ), self::money( $order->tip_total ) );
			}
			if ( get_option( 'ec_option_use_shipping' ) && ( ! empty( $order->shipping_method ) || (float) $order->shipping_total > 0 ) ) {
				$add( 'shipping', self::label( 'cart_success', 'cart_payment_complete_order_totals_shipping', 'Shipping' ), self::money( $order->shipping_total ) );
			}
			if ( (float) $order->tax_total > 0 ) {
				$add( 'tax', self::label( 'cart_success', 'cart_payment_complete_order_totals_tax', 'Tax' ), self::money( $order->tax_total ) );
			}
			$offers = ( isset( $order->applied_offers ) && '' !== (string) $order->applied_offers ) ? json_decode( (string) $order->applied_offers, true ) : false;
			if ( is_array( $offers ) && isset( $offers['applied_offers'] ) && is_array( $offers['applied_offers'] ) ) {
				foreach ( $offers['applied_offers'] as $offer ) {
					if ( ! is_array( $offer ) || ! isset( $offer['amount'] ) || (float) $offer['amount'] <= 0 ) {
						continue;
					}
					$label = isset( $offer['label'] ) ? trim( wp_strip_all_tags( (string) $offer['label'] ) ) : '';
					if ( isset( $offer['code'] ) && '' !== (string) $offer['code'] ) {
						$label .= ' (' . trim( wp_strip_all_tags( (string) $offer['code'] ) ) . ')';
					}
					$add( 'offer', $label, '-' . self::money( $offer['amount'] ) );
				}
			}
			if ( 0.0 !== (float) $order->discount_total && apply_filters( 'wp_easycart_order_details_discount_display', true, $order ) ) {
				$add( 'discount', self::label( 'cart_success', 'cart_payment_complete_order_totals_discount', 'Discount' ), '-' . self::money( $order->discount_total ) );
			}
			if ( method_exists( $order, 'has_duty' ) && $order->has_duty() ) {
				$add( 'duty', self::label( 'cart_success', 'cart_payment_complete_order_totals_duty', 'Duty' ), self::money( $order->duty_total ) );
			}
			if ( method_exists( $order, 'has_vat' ) && $order->has_vat() ) {
				$vat = self::label( 'cart_success', 'cart_payment_complete_order_totals_vat', 'VAT @' );
				if ( isset( $order->vat_rate ) && (float) $order->vat_rate > 0 ) {
					$vat = rtrim( $vat ) . ' ' . rtrim( rtrim( number_format( (float) $order->vat_rate, 3, '.', '' ), '0' ), '.' ) . '%';
				}
				$add( 'vat', $vat, self::money( $order->vat_total ) );
			}
			foreach ( array( 'gst', 'pst', 'hst' ) as $type ) {
				$total = $type . '_total';
				$rate  = $type . '_rate';
				if ( isset( $order->$total ) && (float) $order->$total > 0 ) {
					$label = function_exists( 'wp_easycart_canada_tax_label' ) ? wp_easycart_canada_tax_label( $type, isset( $order->shipping_state ) ? (string) $order->shipping_state : '' ) : strtoupper( $type );
					if ( isset( $order->$rate ) && '' !== (string) $order->$rate ) {
						$label .= ' (' . (string) $order->$rate . '%)';
					}
					$add( $type, $label, self::money( $order->$total ) );
				}
			}
			if ( ! empty( $order->order_fees ) && is_array( $order->order_fees ) ) {
				foreach ( $order->order_fees as $fee ) {
					if ( is_object( $fee ) && isset( $fee->fee_total ) ) {
						$add( 'fee', isset( $fee->fee_label ) ? trim( wp_strip_all_tags( (string) $fee->fee_label ) ) : '', self::money( $fee->fee_total ) );
					}
				}
			}
			if ( method_exists( $order, 'has_refund' ) && $order->has_refund() ) {
				$add( 'refund', self::label( 'account_order_details', 'account_orders_details_refund_total', 'Refunded Amount' ), self::money( $order->refund_total ) );
			}
			$add( 'total', self::label( 'cart_success', 'cart_payment_complete_order_totals_grand_total', 'Order Total' ), self::money( $order->grand_total ) );
			/**
			 * The order confirmation page's totals.
			 *
			 * @since 6.0.4
			 * @param array[] $rows  Each: key, label, amount ( plain text ).
			 * @param object  $order ec_orderdisplay.
			 */
			return (array) apply_filters( 'wp_easycart_success_page_totals', $rows, $order );
		}

		/**
		 * Whether the order ships ( My Account's rule: shipping is on and a line ships ).
		 *
		 * @param object $order ec_orderdisplay.
		 * @return bool
		 */
		public static function ships( $order ) {
			if ( ! get_option( 'ec_option_use_shipping' ) || empty( $order->orderdetails ) || ! is_array( $order->orderdetails ) ) {
				return false;
			}
			foreach ( $order->orderdetails as $detail ) {
				if ( is_object( $detail ) && ! empty( $detail->is_shippable ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * The order's details: date, payment, shipping method, email, coupon code, gift card, VAT number, notes ( My Account's rows ).
		 *
		 * @param object $order ec_orderdisplay.
		 * @return array[] Each: key, label, value ( plain text ).
		 */
		public static function facts( $order ) {
			$facts = array();
			$date  = self::shown( $order, 'display_order_date', array( get_option( 'date_format' ) ) );
			if ( '' !== $date && ! empty( $order->order_date ) ) {
				$facts[] = array(
					'key'   => 'date',
					'label' => self::label( 'account_order_details', 'account_orders_details_order_date', 'Order Date' ),
					'value' => $date,
				);
			}
			$payment = self::shown( $order, 'display_payment_method' );
			if ( isset( $order->creditcard_digits ) && '' !== trim( (string) $order->creditcard_digits ) ) {
				$payment = trim( $payment . ' •••• ' . trim( (string) $order->creditcard_digits ) );
			}
			if ( '' !== $payment ) {
				$facts[] = array(
					'key'   => 'payment',
					'label' => self::label( 'account_order_details', 'account_orders_details_payment_method', 'Payment Method' ),
					'value' => $payment,
				);
			}
			if ( self::ships( $order ) && isset( $order->shipping_method ) && '' !== trim( (string) $order->shipping_method ) ) {
				$facts[] = array(
					'key'   => 'shipping',
					'label' => self::label( 'account_order_details', 'account_orders_details_shipping_method', 'Shipping Method' ),
					'value' => trim( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $order->shipping_method ) ) ),
				);
			}
			$email = isset( $order->user_email ) ? trim( (string) $order->user_email ) : '';
			if ( isset( $order->email_other ) && '' !== trim( (string) $order->email_other ) ) {
				$email .= ( '' !== $email ? ', ' : '' ) . trim( (string) $order->email_other );
			}
			if ( '' !== $email && 'guest' !== $email ) {
				$facts[] = array(
					'key'   => 'email',
					'label' => self::label( 'account_order_details', 'account_orders_details_email', 'Email' ),
					'value' => $email,
				);
			}
			/* My Account's own rows: the code ( or its message, as the store sets it ), the gift card as the store names it, a VAT number. */
			if ( ! empty( $order->promo_code ) && apply_filters( 'wp_easycart_order_details_promo_code_display', true, $order ) ) {
				$facts[] = array(
					'key'   => 'coupon',
					'label' => self::label( 'account_order_details', 'account_orders_details_coupon_code', 'Coupon Code' ),
					'value' => self::shown( $order, 'display_order_promocode' ),
				);
			}
			if ( ! empty( $order->giftcard_id ) ) {
				$facts[] = array(
					'key'   => 'giftcard',
					'label' => self::label( 'account_order_details', 'account_orders_details_gift_card', 'Gift Card' ),
					'value' => self::shown( $order, 'display_order_giftcard' ),
				);
			}
			if ( isset( $order->vat_registration_number ) && '' !== trim( (string) $order->vat_registration_number ) ) {
				$facts[] = array(
					'key'   => 'vat_number',
					'label' => self::label( 'cart_billing_information', 'cart_billing_information_vat_registration_number', 'VAT Registration Number' ),
					'value' => trim( stripslashes( (string) $order->vat_registration_number ) ),
				);
			}
			if ( get_option( 'ec_option_user_order_notes' ) && isset( $order->order_customer_notes ) && '' !== trim( (string) $order->order_customer_notes ) ) {
				$facts[] = array(
					'key'   => 'notes',
					'label' => self::label( 'cart_payment_information', 'cart_payment_information_order_notes_title', 'Order Notes' ),
					'value' => trim( wp_strip_all_tags( stripslashes( (string) $order->order_customer_notes ) ) ),
				);
			}
			/**
			 * The order confirmation page's order details.
			 *
			 * @since 6.0.4
			 * @param array[] $facts Each: key, label, value ( plain text ).
			 * @param object  $order ec_orderdisplay.
			 */
			return (array) apply_filters( 'wp_easycart_success_page_facts', $facts, $order );
		}

		/**
		 * The order's shipping and billing addresses.
		 *
		 * @param object $order ec_orderdisplay.
		 * @return array[] type => array( label, lines ).
		 */
		public static function addresses( $order ) {
			$addresses = array();
			$types     = array( 'billing' => array( 'cart_payment_complete_billing_label', 'Billing Address' ) );
			if ( self::ships( $order ) && ( empty( $order->subscription_id ) || get_option( 'ec_option_collect_shipping_for_subscriptions' ) ) ) {
				$types = array( 'shipping' => array( 'cart_payment_complete_shipping_label', 'Shipping Address' ) ) + $types;
			}
			foreach ( $types as $type => $phrase ) {
				$get   = function ( $field ) use ( $order, $type ) {
					$property = $type . '_' . $field;
					return isset( $order->$property ) ? trim( stripslashes( (string) $order->$property ) ) : '';
				};
				$name  = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
				$place = trim( $get( 'city' ) . ( ( '' !== $get( 'city' ) && '' !== $get( 'state' ) ) ? ', ' : '' ) . $get( 'state' ) . ' ' . $get( 'zip' ) );
				if ( '' === $get( 'address_line_1' ) && '' === $place && '' === $get( 'country' ) ) { /* My Account's rule: any part of it */
					continue;
				}
				$lines              = array_values(
					array_filter(
						array( $name, $get( 'company_name' ), $get( 'address_line_1' ), $get( 'address_line_2' ), $place, ( '' !== $get( 'country_name' ) ) ? $get( 'country_name' ) : $get( 'country' ), $get( 'phone' ) ),
						'strlen'
					)
				);
				$addresses[ $type ] = array(
					'label' => self::label( 'cart_success', $phrase[0], $phrase[1] ),
					'lines' => $lines,
				);
			}
			return $addresses;
		}

		/**
		 * The order's items and totals ( the summary card ).
		 *
		 * @param object $order ec_orderdisplay.
		 */
		public static function print_summary( $order ) {
			$lines = self::lines( $order );
			$count = 0;
			foreach ( $lines as $line ) {
				$count += max( 1, (int) $line['quantity'] );
			}
			echo '<section class="ec_order_success_card ec_order_success_summary" aria-labelledby="ec_order_success_summary_title">';
			echo '<h3 class="ec_order_success_card_title" id="ec_order_success_summary_title">' . esc_html( self::text( 'elementor_checkout', 'confirmation_items', 'Your order' ) ) . '</h3>';
			if ( $lines ) {
				echo '<ul class="ec_order_success_items">';
				foreach ( $lines as $line ) {
					echo '<li class="ec_order_success_item">';
					echo '<span class="ec_order_success_item_image' . ( '' === $line['image'] ? ' ec_order_success_item_image_none' : '' ) . '">';
					if ( '' !== $line['image'] ) {
						echo '<img src="' . esc_url( $line['image'] ) . '" alt="" loading="lazy" decoding="async" />';
					}
					if ( (int) $line['quantity'] > 1 ) {
						echo '<span class="ec_order_success_item_quantity" aria-hidden="true">' . esc_html( (string) (int) $line['quantity'] ) . '</span>';
					}
					echo '</span><span class="ec_order_success_item_details"><span class="ec_order_success_item_title">' . esc_html( $line['title'] ) . '</span>';
					if ( isset( $line['sku'] ) && '' !== (string) $line['sku'] ) {
						echo '<span class="ec_order_success_item_option ec_order_success_item_sku">' . esc_html( (string) $line['sku'] ) . '</span>';
					}
					foreach ( (array) $line['options'] as $option ) {
						echo '<span class="ec_order_success_item_option">' . esc_html( (string) $option ) . '</span>';
					}
					echo '<span class="ec_order_success_item_count">&times; ' . esc_html( (string) (int) $line['quantity'] ) . '</span></span>';
					echo '<span class="ec_order_success_item_total">' . esc_html( (string) $line['total'] ) . '</span></li>';
				}
				echo '</ul>';
			}
			echo '<dl class="ec_order_success_totals">';
			foreach ( self::totals( $order ) as $row ) {
				echo '<div class="ec_order_success_total ec_order_success_total_' . esc_attr( sanitize_key( (string) $row['key'] ) ) . '"><dt>' . esc_html( (string) $row['label'] ) . '</dt><dd>' . esc_html( (string) $row['amount'] ) . '</dd></div>';
			}
			echo '</dl></section>';
		}

		/**
		 * How to pay a bank transfer order that is not paid yet: the store's instructions and its pay link ( as the Elementor Order
		 * Confirmation widget shows them ).
		 *
		 * @param object $order ec_orderdisplay.
		 */
		public static function print_payment( $order ) {
			if ( ! is_object( $order ) || ! empty( $order->is_approved ) || 'manual_bill' !== (string) $order->payment_method || ! get_option( 'ec_option_use_direct_deposit' ) ) {
				return;
			}
			$text = trim( (string) wp_easycart_language()->convert_text( (string) get_option( 'ec_option_direct_deposit_message' ) ) );
			$link = '';
			if ( class_exists( 'wp_easycart_order_pay' ) && method_exists( 'wp_easycart_order_pay', 'available' ) && wp_easycart_order_pay::available() && (float) $order->grand_total > 0 ) {
				$link = (string) wp_easycart_order_pay::url( (int) $order->order_id );
			}
			if ( '' === $text && '' === $link ) {
				return;
			}
			echo '<section class="ec_order_success_card ec_order_success_payment"><h3 class="ec_order_success_card_title">' . esc_html( self::text( 'elementor_checkout', 'confirmation_payment', 'How to pay' ) ) . '</h3>';
			if ( '' !== $text ) {
				echo '<div class="ec_order_success_payment_text">' . nl2br( esc_html( wp_strip_all_tags( $text ) ) ) . '</div>';
			}
			if ( '' !== $link ) {
				echo '<p class="ec_order_success_payment_note">' . esc_html( self::text( 'elementor_checkout', 'confirmation_pay_link_note', 'You can pay for this order online.' ) ) . '</p>';
				echo '<a class="ec_order_success_payment_button" href="' . esc_url( $link ) . '">' . esc_html( self::text( 'elementor_checkout', 'confirmation_pay_now', 'Pay now' ) ) . '</a>';
			}
			echo '</section>';
		}

		/**
		 * The order's details and addresses ( the details card ).
		 *
		 * @param object $order ec_orderdisplay.
		 */
		public static function print_info( $order ) {
			$facts     = self::facts( $order );
			$addresses = self::addresses( $order );
			if ( ! $facts && ! $addresses ) {
				return;
			}
			echo '<section class="ec_order_success_card ec_order_success_info" aria-labelledby="ec_order_success_info_title">';
			echo '<h3 class="ec_order_success_card_title" id="ec_order_success_info_title">' . esc_html( self::label( 'account_order_details', 'account_orders_details_order_info_title', 'Order Information' ) ) . '</h3>';
			if ( $facts ) {
				echo '<dl class="ec_order_success_facts">';
				foreach ( $facts as $fact ) {
					echo '<div class="ec_order_success_fact ec_order_success_fact_' . esc_attr( sanitize_key( (string) $fact['key'] ) ) . '"><dt>' . esc_html( (string) $fact['label'] ) . '</dt><dd>' . nl2br( esc_html( (string) $fact['value'] ) ) . '</dd></div>';
				}
				echo '</dl>';
			}
			if ( $addresses ) {
				echo '<div class="ec_order_success_addresses">';
				foreach ( $addresses as $type => $address ) {
					echo '<div class="ec_order_success_address ec_order_success_address_' . esc_attr( $type ) . '"><h4 class="ec_order_success_address_title">' . esc_html( $address['label'] ) . '</h4><address>' . implode( '<br />', array_map( 'esc_html', $address['lines'] ) ) . '</address></div>';
				}
				echo '</div>';
			}
			echo '</section>';
		}
	}

endif;
