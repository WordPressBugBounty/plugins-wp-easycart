<?php
/**
 * WP EasyCart Admin Order Details - V2 Controller
 *
 * Thin subclass of wp_easycart_admin_details_orders that swaps the template
 * for the modern V2 layout. All data loading, field printing, and the
 * existing AJAX/nonce contracts are inherited unchanged.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_order_details_v2_enabled' ) ) {
	function wp_easycart_order_details_v2_enabled() {
		$default = get_option( 'ec_option_admin_enable_order_details_v2', '1' );
		return (bool) apply_filters( 'wp_easycart_admin_order_details_v2_enabled', $default );
	}
}

if ( ! class_exists( 'wp_easycart_admin_details_orders_v2' ) ) :

	class wp_easycart_admin_details_orders_v2 extends wp_easycart_admin_details_orders {

		public function output( $type = 'edit' ) {
			$this->init();
			if ( 'edit' == $type ) {
				$this->init_data();
			}
			if ( $this->record_not_found ) {
				$this->print_record_not_found_notice();
				return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/orders/orders/order-details-v2.php' );
		}

		/**
		 * Payment badge state used by the header (same mapping the V1 totals
		 * box used + the JS in orders.js updates by status id).
		 */
		public function get_payment_badge() {
			$status_id = (int) $this->order->orderstatus_id;
			if ( 17 === $status_id ) {
				return array( 'class' => 'payment-neutral', 'label' => __( 'Partial Refund', 'wp-easycart' ) );
			} else if ( 16 === $status_id ) {
				return array( 'class' => 'payment-bad', 'label' => __( 'Refunded', 'wp-easycart' ) );
			} else if ( ! empty( $this->order->is_approved ) ) {
				return array( 'class' => 'payment-paid', 'label' => __( 'Paid', 'wp-easycart' ) );
			} else if ( 19 === $status_id ) {
				return array( 'class' => 'payment-bad', 'label' => __( 'Canceled', 'wp-easycart' ) );
			} else if ( 7 === $status_id || 9 === $status_id ) {
				return array( 'class' => 'payment-bad', 'label' => __( 'Failed', 'wp-easycart' ) );
			}
			return array( 'class' => 'payment-processing', 'label' => __( 'Processing', 'wp-easycart' ) );
		}

		/**
		 * Display-format a phone number using the order's country as a hint.
		 * Returns array( 'display' => '(541) 969-0424', 'href' => 'tel:+15419690424' ).
		 * Covers the common patterns without a libphonenumber dependency;
		 * filter 'wp_easycart_ecv2_format_phone' lets PRO/plugins replace it.
		 */
		public function format_phone( $raw, $country = '' ) {
			$raw     = trim( (string) $raw );
			$country = strtoupper( trim( (string) $country ) );
			if ( '' === $raw ) {
				return array( 'display' => '', 'href' => '' );
			}
			$has_plus = ( 0 === strpos( $raw, '+' ) ) || ( 0 === strpos( $raw, '00' ) );
			$digits   = preg_replace( '/\D/', '', $raw );
			if ( 0 === strpos( $raw, '00' ) ) {
				$digits = substr( $digits, 2 );
			}
			$display = $raw;
			$href    = '';

			$nanp = array( 'US', 'CA', 'PR', 'VI', 'GU', 'AS', 'MP', 'BS', 'BB', 'BM', 'DO', 'JM', 'TT' );

			if ( $has_plus && strlen( $digits ) >= 8 ) {
				/* International: +CC then group the national part. */
				$cc  = '';
				$nat = $digits;
				foreach ( array( 1, 2, 3 ) as $len ) {
					$try = substr( $digits, 0, $len );
					if ( '1' === $try || ( 2 === $len && in_array( $try, array( '20','27','30','31','32','33','34','36','39','40','41','43','44','45','46','47','48','49','51','52','53','54','55','56','57','58','60','61','62','63','64','65','66','81','82','84','86','90','91','92','93','94','95','98' ), true ) ) || 3 === $len ) {
						$cc  = $try;
						$nat = substr( $digits, $len );
						break;
					}
				}
				if ( '1' === $cc && 10 === strlen( $nat ) ) {
					$display = '+1 (' . substr( $nat, 0, 3 ) . ') ' . substr( $nat, 3, 3 ) . '-' . substr( $nat, 6 );
				} else {
					$display = '+' . $cc . ' ' . $this->group_digits( $nat );
				}
				$href = '+' . $digits;
			} else if ( in_array( $country, $nanp, true ) || '' === $country ) {
				if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
					$digits = substr( $digits, 1 );
				}
				if ( 10 === strlen( $digits ) ) {
					$display = '(' . substr( $digits, 0, 3 ) . ') ' . substr( $digits, 3, 3 ) . '-' . substr( $digits, 6 );
					$href    = '+1' . $digits;
				}
			} else if ( 'GB' === $country ) {
				if ( 11 === strlen( $digits ) && '0' === $digits[0] ) {
					if ( '02' === substr( $digits, 0, 2 ) ) {
						$display = substr( $digits, 0, 3 ) . ' ' . substr( $digits, 3, 4 ) . ' ' . substr( $digits, 7 );
					} else if ( '07' === substr( $digits, 0, 2 ) ) {
						$display = substr( $digits, 0, 5 ) . ' ' . substr( $digits, 5 );
					} else {
						$display = substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4, 3 ) . ' ' . substr( $digits, 7 );
					}
					$href = '+44' . substr( $digits, 1 );
				}
			} else if ( 'AU' === $country ) {
				if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
					$display = ( '04' === substr( $digits, 0, 2 ) )
						? substr( $digits, 0, 4 ) . ' ' . substr( $digits, 4, 3 ) . ' ' . substr( $digits, 7 )
						: substr( $digits, 0, 2 ) . ' ' . substr( $digits, 2, 4 ) . ' ' . substr( $digits, 6 );
					$href = '+61' . substr( $digits, 1 );
				}
			} else if ( 'FR' === $country ) {
				if ( 10 === strlen( $digits ) && '0' === $digits[0] ) {
					$display = implode( ' ', str_split( $digits, 2 ) );
					$href    = '+33' . substr( $digits, 1 );
				}
			} else if ( 'DE' === $country || 'NL' === $country || 'ES' === $country || 'IT' === $country ) {
				if ( strlen( $digits ) >= 9 ) {
					$display = $this->group_digits( $digits );
				}
			}

			if ( '' === $href ) {
				$href = ( $has_plus ? '+' : '' ) . $digits;
			}
			return apply_filters( 'wp_easycart_ecv2_format_phone', array( 'display' => $display, 'href' => 'tel:' . $href ), $raw, $country );
		}

		private function group_digits( $d ) {
			$len = strlen( $d );
			if ( $len <= 4 ) {
				return $d;
			}
			/* Leading area-ish block, then 3s, with a final 3 or 4. */
			$first = ( $len % 3 === 0 ) ? 3 : ( $len % 3 === 1 ? 4 : 2 );
			$out   = array( substr( $d, 0, $first ) );
			$rest  = substr( $d, $first );
			while ( strlen( $rest ) > 4 ) {
				$out[] = substr( $rest, 0, 3 );
				$rest  = substr( $rest, 3 );
			}
			if ( '' !== $rest ) {
				$out[] = $rest;
			}
			return implode( ' ', $out );
		}

		/**
		 * Fulfillment state for the banner: fulfilled / pickup / unfulfilled / none.
		 */
		public function get_fulfillment_state() {
			$status_id = (int) $this->order->orderstatus_id;
			$tracking  = trim( (string) $this->order->tracking_number );

			if ( 18 === $status_id || 2 === $status_id || '' !== $tracking ) {
				return 'fulfilled';
			}
			if ( 11 === $status_id || ! empty( $this->order->includes_restaurant_type ) ) {
				return 'pickup';
			}
			if ( ! empty( $this->order->is_approved ) && ! in_array( $status_id, array( 16, 19 ), true ) ) {
				return 'unfulfilled';
			}
			return 'none';
		}
	}

endif;