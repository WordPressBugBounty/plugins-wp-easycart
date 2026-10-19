<?php
/**
 * The short subscription checkout ( 6.0.5, Matt: as simple as possible, an option in the admin ). For a subscription that ships nothing,
 * the subscription page asks only for the name, the country, the state or province ( where the country has a list ) and the postcode
 * ( where the country uses one ), with the payment on the same screen: no street, city, company or phone, and no Save & Continue between
 * the address and the card. The totals follow the country, state and postcode as they are typed ( the tax ). A signed-in customer sees
 * the same short form, filled in from their account; the parts it does not ask for keep what the account holds.
 *
 * Off for a subscription that ships ( the full address and the shipping methods ), and while a tax service works out the tax ( TaxJar,
 * TaxCloud, Avalara and the like look the rate up from the street address ).
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_short' ) ) :

	/**
	 * Short subscription checkout.
	 */
	class wp_easycart_subscription_short {

		/**
		 * The setting ( Settings › Checkout › Checkout form ).
		 */
		const OPTION = 'ec_option_subscription_short_checkout';

		/**
		 * The billing parts the short form does not ask for: the field name => the checkout session's column. They go on the page as
		 * hidden fields holding what the session or the account has, so saving the short form never empties them.
		 *
		 * @return array
		 */
		public static function kept_parts() {
			return array(
				'company_name' => 'billing_company_name',
				'address'      => 'billing_address_line_1',
				'address2'     => 'billing_address_line_2',
				'city'         => 'billing_city',
				'phone'        => 'billing_phone',
			);
		}

		/**
		 * Whether the store asks for the short subscription checkout.
		 *
		 * @return bool
		 */
		public static function enabled() {
			/**
			 * Whether the subscription page asks only for the name, country, state and postcode when the subscription ships nothing.
			 *
			 * @since 6.0.5
			 * @param bool $enabled Settings › Checkout › Checkout form › Short subscription checkout.
			 */
			return (bool) apply_filters( 'wp_easycart_subscription_short_checkout', '1' === (string) get_option( self::OPTION, '0' ) );
		}

		/**
		 * Whether a subscription asks for a shipping address on its page ( the same rule the page prints its shipping parts by ).
		 *
		 * @param object $product ec_product.
		 * @return bool
		 */
		public static function ships( $product ) {
			return (bool) ( get_option( 'ec_option_collect_shipping_for_subscriptions' ) && get_option( 'ec_option_use_shipping' ) && is_object( $product ) && ! empty( $product->is_shippable ) );
		}

		/**
		 * The tax service that works out the store's tax from the street address, or '' for none: TaxCloud or TaxJar while they are the
		 * one calculating, or a registered service that is ready. A saved choice of a service that is not loaded ( an extension switched
		 * off ) is none: wp_easycart_tax_providers::active_id() still names it, so reading that alone turned the short form off on a
		 * store that charges its own rates.
		 *
		 * @return string taxcloud | taxjar | a service id | ''.
		 */
		public static function tax_service() {
			$id = '';
			if ( class_exists( 'wp_easycart_tax_providers' ) && method_exists( 'wp_easycart_tax_providers', 'active_id' ) ) {
				$id = (string) wp_easycart_tax_providers::active_id();
				if ( 'taxcloud' !== $id && 'taxjar' !== $id ) {
					$provider = method_exists( 'wp_easycart_tax_providers', 'active' ) ? wp_easycart_tax_providers::active() : null;
					$id       = ( $provider && method_exists( $provider, 'id' ) ) ? (string) $provider->id() : '';
				}
			}
			/**
			 * The tax service that keeps subscriptions on the full billing address ( it needs the street ). Answer '' to use the short
			 * form with a service that rates by postcode.
			 *
			 * @since 6.0.5
			 * @param string $id taxcloud | taxjar | a service id | ''.
			 */
			return (string) apply_filters( 'wp_easycart_subscription_short_tax_service', $id );
		}

		/**
		 * A tax service's name for a note.
		 *
		 * @param string $id Tax service id.
		 * @return string
		 */
		public static function tax_service_name( $id ) {
			if ( 'taxcloud' === $id ) {
				return 'TaxCloud';
			}
			if ( 'taxjar' === $id ) {
				return 'TaxJar';
			}
			if ( class_exists( 'wp_easycart_tax_providers' ) && method_exists( 'wp_easycart_tax_providers', 'get' ) ) {
				$provider = wp_easycart_tax_providers::get( $id );
				if ( $provider && method_exists( $provider, 'label' ) && '' !== (string) $provider->label() ) {
					return (string) $provider->label();
				}
			}
			return (string) $id;
		}

		/**
		 * Why a subscription's page keeps the full billing address while the setting is on ( '' = it does not ).
		 *
		 * @param object|null $product ec_product, or null for the store as a whole ( only the tax service ).
		 * @return string ships | tax | ''.
		 */
		public static function blocker( $product = null ) {
			if ( null !== $product && self::ships( $product ) ) {
				return 'ships';
			}
			if ( '' !== self::tax_service() ) {
				return 'tax';
			}
			return '';
		}

		/**
		 * What a store manager is told while the setting is on and a page still asks the full address ( '' when it does not ).
		 *
		 * @param object|null $product ec_product, or null for the store as a whole.
		 * @return string
		 */
		public static function blocker_text( $product = null ) {
			$why = self::blocker( $product );
			if ( 'ships' === $why ) {
				return __( 'Short subscription checkout is on, but this subscription ships to the customer, so its page asks for the full address.', 'wp-easycart' );
			}
			if ( 'tax' === $why ) {
				/* translators: %s: a tax service, e.g. TaxJar. */
				return sprintf( __( 'Short subscription checkout is on, but %s works out your tax from the street address, so subscription pages ask for the full billing address. Switch the tax service off in Settings › Taxes to use the short form.', 'wp-easycart' ), self::tax_service_name( self::tax_service() ) );
			}
			return '';
		}

		/**
		 * The note on a subscription page that keeps the full form while the setting is on, for store managers only ( the store's
		 * customers never see it ).
		 *
		 * @param object $product ec_product.
		 */
		public static function print_manager_note( $product ) {
			if ( ! self::enabled() || ! ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) ) {
				return;
			}
			$text = self::blocker_text( $product );
			if ( '' === $text ) {
				return;
			}
			echo '<p class="ec_subscription_short_note" role="note">' . esc_html( $text ) . ' <span class="ec_subscription_short_note_who">' . esc_html__( 'Only store managers see this note.', 'wp-easycart' ) . '</span></p>';
		}

		/**
		 * Whether this subscription's page asks the short form.
		 *
		 * @param object $product ec_product.
		 * @return bool
		 */
		public static function applies( $product ) {
			$applies = self::enabled() && '' === self::blocker( $product );
			/**
			 * Whether a subscription's page asks the short form.
			 *
			 * @since 6.0.5
			 * @param bool   $applies The setting on, nothing shipped, no tax service.
			 * @param object $product ec_product.
			 */
			return (bool) apply_filters( 'wp_easycart_subscription_short_checkout_applies', $applies, $product );
		}

		/**
		 * The parts that belong to a place ( the street and town ): taken from one address, and emptied on the page when the shopper
		 * picks another country than theirs ( a street in Ohio does not belong in Canada ).
		 *
		 * @return string[]
		 */
		public static function place_parts() {
			return array( 'address', 'address2', 'city' );
		}

		/**
		 * A value of the checkout session ( '' when it has none ).
		 *
		 * @param string $column ec_tempcart_data column.
		 * @return string
		 */
		private static function session_value( $column ) {
			if ( isset( $GLOBALS['ec_cart_data']->cart_data->{ $column } ) && '0' !== (string) $GLOBALS['ec_cart_data']->cart_data->{ $column } ) {
				return (string) $GLOBALS['ec_cart_data']->cart_data->{ $column };
			}
			return '';
		}

		/**
		 * A value of the account's billing address ( '' for a guest ).
		 *
		 * @param string $part ec_address::get_value() key.
		 * @return string
		 */
		private static function account_value( $part ) {
			if ( isset( $GLOBALS['ec_user']->billing ) && is_object( $GLOBALS['ec_user']->billing ) && method_exists( $GLOBALS['ec_user']->billing, 'get_value' ) ) {
				$value = (string) $GLOBALS['ec_user']->billing->get_value( $part );
				return ( '0' === $value ) ? '' : $value;
			}
			return '';
		}

		/**
		 * Where the street and town come from: the checkout session once it holds an address, else the account.
		 *
		 * @return string session | account
		 */
		public static function place_source() {
			return ( '' !== self::session_value( 'billing_address_line_1' ) || '' !== self::session_value( 'billing_city' ) ) ? 'session' : 'account';
		}

		/**
		 * The country the kept street and town belong to ( '' when there are none ).
		 *
		 * @return string ISO code.
		 */
		public static function kept_country() {
			if ( '' === self::kept( 'address' ) && '' === self::kept( 'city' ) ) {
				return '';
			}
			return ( 'session' === self::place_source() ) ? self::session_value( 'billing_country' ) : self::account_value( 'country2' );
		}

		/**
		 * What a part the short form does not ask for holds: the street and town from one address ( place_source() ), the company
		 * and phone from the checkout session, else the account's billing address.
		 *
		 * @param string $part company_name | address | address2 | city | phone.
		 * @return string
		 */
		public static function kept( $part ) {
			$parts = self::kept_parts();
			if ( ! isset( $parts[ $part ] ) ) {
				return '';
			}
			if ( in_array( $part, self::place_parts(), true ) ) {
				return ( 'session' === self::place_source() ) ? self::session_value( $parts[ $part ] ) : self::account_value( $part );
			}
			$value = self::session_value( $parts[ $part ] );
			return ( '' !== $value ) ? $value : self::account_value( $part );
		}

		/**
		 * The short form's own attributes on #ec_cart_billing_form: what its script needs to refresh the totals as the place is
		 * typed ( the product, the subscription tax call's nonce ), the country the kept street belongs to, and the country the
		 * page's totals were worked out for.
		 *
		 * @param object $product ec_product.
		 * @return string Escaped attributes, each with a leading space.
		 */
		public static function form_attributes( $product ) {
			$session = isset( $GLOBALS['ec_cart_data']->ec_cart_id ) ? $GLOBALS['ec_cart_data']->ec_cart_id : '';
			$attrs   = array(
				'data-short'           => '1',
				'data-product'         => is_object( $product ) ? (int) $product->product_id : 0,
				'data-nonce'           => wp_create_nonce( 'wp-easycart-update-subscription-tax-' . $session ),
				'data-kept-country'    => self::kept_country(),
				'data-session-country' => self::session_value( 'billing_country' ),
			);

			$out = '';
			foreach ( $attrs as $name => $value ) {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
			return $out;
		}

		/**
		 * The hidden fields for the parts the short form does not ask for ( the ids the page's scripts read ). The street and town
		 * carry data-wpec-kept="place", the company and phone "1".
		 */
		public static function print_kept_fields() {
			foreach ( array_keys( self::kept_parts() ) as $part ) {
				$kind = in_array( $part, self::place_parts(), true ) ? 'place' : '1';
				echo '<input type="hidden" id="ec_cart_billing_' . esc_attr( $part ) . '" name="ec_cart_billing_' . esc_attr( $part ) . '" value="' . esc_attr( self::kept( $part ) ) . '" data-wpec-kept="' . esc_attr( $kind ) . '" />';
			}
		}
	}

endif;
