<?php
/**
 * Subscriptions and the store's payment gateway ( 6.0.2 ).
 *
 * WP EasyCart bills subscriptions through Stripe only: the subscription page shows Stripe's card form, and every
 * subscription path charges through ec_stripe ( API keys, PRO ) or ec_stripe_connect, whatever gateway the store picked.
 * With another gateway ( Square, for example ), or with Stripe chosen but not connected, nothing can be bought. So the
 * subscription page shows a message instead of a payment form, and the admin says so wherever subscriptions are set up:
 * the product editor, the offer and coupon editors, Settings › Payments and Store Status.
 *
 * status() is the one answer ( filter wp_easycart_subscriptions_status ); wp_easycart_subscriptions_ready() is its short
 * form, which WP EasyCart PRO asks before a code may discount a subscription.
 *
 * @package wp-easycart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscription_gateway' ) ) :

	/**
	 * Can this store sell subscriptions, and what to tell the merchant and the shopper when it can't.
	 */
	final class wp_easycart_subscription_gateway {

		/** Settings › Payments, relative to wp-admin. */
		const SETTINGS_PATH = 'admin.php?page=wp-easycart-settings&subpage=payment';

		/**
		 * Whether subscriptions can be sold, and why not.
		 *
		 * @return array {
		 *     @type bool   $ready            Stripe is the card gateway and is connected.
		 *     @type string $reason           '' | gateway ( the card gateway is not Stripe ) | connect ( Stripe is not connected ).
		 *     @type string $gateway          ec_option_payment_process_method ( '' when none ).
		 *     @type string $gateway_name     Its name for messages.
		 *     @type string $third_party_name The third-party checkout's name ( PayPal, … ), or ''.
		 * }
		 */
		public static function status() {
			$method = self::option_key( 'ec_option_payment_process_method' );
			$third  = self::option_key( 'ec_option_payment_third_party' );
			$reason = '';
			if ( ! in_array( $method, array( 'stripe', 'stripe_connect' ), true ) ) {
				$reason = 'gateway';
			} elseif ( ! self::stripe_connected( $method ) ) {
				$reason = 'connect';
			}
			$status = array(
				'ready'            => ( '' === $reason ),
				'reason'           => $reason,
				'gateway'          => $method,
				'gateway_name'     => self::gateway_name( $method ),
				'third_party_name' => self::gateway_name( $third ),
			);
			if ( function_exists( 'apply_filters' ) ) {
				/**
				 * Whether this store can sell subscriptions. A payment extension that bills subscriptions itself can say
				 * ready here.
				 *
				 * @since 6.0.2
				 * @param array $status See wp_easycart_subscription_gateway::status().
				 */
				$filtered = apply_filters( 'wp_easycart_subscriptions_status', $status );
				if ( is_array( $filtered ) ) {
					$status          = array_merge( $status, $filtered );
					$status['ready'] = (bool) $status['ready'];
					if ( $status['ready'] ) {
						$status['reason'] = '';
					} elseif ( ! in_array( $status['reason'], array( 'gateway', 'connect' ), true ) ) {
						$status['reason'] = 'gateway';
					}
				}
			}
			return $status;
		}

		/**
		 * Can shoppers buy subscriptions, and codes discount them?
		 *
		 * @return bool
		 */
		public static function ready() {
			$status = self::status();
			return $status['ready'];
		}

		/**
		 * A gateway option's value, '' for none.
		 *
		 * @param string $option Option name.
		 * @return string
		 */
		private static function option_key( $option ) {
			$value = trim( (string) get_option( $option ) );
			return ( '0' === $value ) ? '' : $value;
		}

		/**
		 * Stripe's keys for the mode it is in, the ones the subscription checkout charges with ( as
		 * ec_stripe_subscriptions::available() in WP EasyCart PRO reads them ).
		 *
		 * @param string $method stripe | stripe_connect.
		 * @return bool
		 */
		private static function stripe_connected( $method ) {
			if ( 'stripe' === $method ) {
				/* API keys ( WP EasyCart PRO ): the page's card form needs the publishable key, the server the secret one, and
				 * the subscription calls PRO's ec_stripe ( without it they would stop with a fatal error ). */
				return class_exists( 'ec_stripe' ) && '' !== trim( (string) get_option( 'ec_option_stripe_api_key' ) ) && '' !== trim( (string) get_option( 'ec_option_stripe_public_api_key' ) );
			}
			$token = get_option( 'ec_option_stripe_connect_use_sandbox' ) ? get_option( 'ec_option_stripe_connect_sandbox_access_token' ) : get_option( 'ec_option_stripe_connect_production_access_token' );
			if ( function_exists( 'apply_filters' ) ) {
				$token = apply_filters( 'wp_easycart_stripe_connect_api_key', $token );
			}
			return '' !== trim( (string) $token );
		}

		/**
		 * A gateway's name for messages.
		 *
		 * @param string $key Gateway option value.
		 * @return string '' for none.
		 */
		public static function gateway_name( $key ) {
			$key = (string) $key;
			if ( '' === $key || '0' === $key ) {
				return '';
			}
			$names = array(
				'stripe'         => 'Stripe',
				'stripe_connect' => 'Stripe',
				'square'         => 'Square',
				'paypal'         => 'PayPal',
			);
			if ( isset( $names[ $key ] ) ) {
				return $names[ $key ];
			}
			if ( class_exists( 'wp_easycart_order_pay' ) ) {
				return wp_easycart_order_pay::gateway_label( $key );
			}
			return ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
		}

		/**
		 * One sentence on what the store takes payments with ( or why Stripe can't bill yet ).
		 *
		 * @param array|null $status status(), or null to read it.
		 * @return string Plain text.
		 */
		public static function gateway_sentence( $status = null ) {
			$status = is_array( $status ) ? $status : self::status();
			if ( 'connect' === $status['reason'] ) {
				if ( 'stripe' === $status['gateway'] && ! class_exists( 'ec_stripe' ) ) {
					return __( 'Stripe with API keys is your card gateway, but it runs through WP EasyCart PRO, which isn\'t installed.', 'wp-easycart' );
				}
				return ( 'stripe' === $status['gateway'] ) ? __( 'Stripe is your card gateway, but its API keys aren\'t saved yet.', 'wp-easycart' ) : __( 'Stripe is your card gateway, but it isn\'t connected yet.', 'wp-easycart' );
			}
			if ( '' !== $status['gateway_name'] ) {
				/* translators: %s: payment gateway name, e.g. Square. */
				return sprintf( __( 'Your store takes card payments with %s.', 'wp-easycart' ), $status['gateway_name'] );
			}
			if ( '' !== $status['third_party_name'] ) {
				/* translators: %s: payment gateway name, e.g. PayPal. */
				return sprintf( __( 'Your store takes payments with %s only.', 'wp-easycart' ), $status['third_party_name'] );
			}
			return __( 'Your store has no card gateway yet.', 'wp-easycart' );
		}

		/**
		 * "Subscriptions need Stripe." with what the store uses, for short hints.
		 *
		 * @return string Plain text.
		 */
		public static function summary() {
			/* translators: %s: what the store takes payments with, e.g. "Your store takes card payments with Square." */
			return sprintf( __( 'Subscriptions need Stripe. %s', 'wp-easycart' ), self::gateway_sentence() );
		}

		/**
		 * The merchant's notice for one place in the admin.
		 *
		 * @param string $context product | offer | coupon | payments | status.
		 * @param int    $count   Subscription products for sale ( payments, status ).
		 * @return array title, detail ( plain text ).
		 */
		public static function text( $context, $count = 0 ) {
			$status   = self::status();
			$sentence = self::gateway_sentence( $status );
			$count    = max( 0, (int) $count );
			$title    = __( 'Subscriptions need Stripe', 'wp-easycart' );
			switch ( $context ) {
				case 'offer':
					$then = __( 'Until Stripe takes your card payments, codes can\'t discount subscription products.', 'wp-easycart' );
					break;
				case 'coupon':
					$then = __( 'Until Stripe takes your card payments, subscription products can\'t be bought, so the duration has no effect.', 'wp-easycart' );
					break;
				case 'payments':
				case 'status':
					/* translators: %s: number of products. */
					$title = sprintf( _n( '%s subscription product can\'t be bought', '%s subscription products can\'t be bought', $count, 'wp-easycart' ), number_format_i18n( $count ) );
					$then  = __( 'Subscriptions need Stripe: until Stripe takes your card payments, shoppers see a message instead of a checkout.', 'wp-easycart' );
					break;
				default:
					$then = __( 'Until Stripe takes your card payments, shoppers see a message instead of a checkout for subscription products.', 'wp-easycart' );
			}
			return array(
				'title'  => $title,
				'detail' => $sentence . ' ' . $then,
			);
		}

		/**
		 * Settings › Payments.
		 *
		 * @return string
		 */
		public static function settings_url() {
			return function_exists( 'admin_url' ) ? admin_url( self::SETTINGS_PATH ) : self::SETTINGS_PATH;
		}

		/**
		 * Subscription products shoppers can reach ( switched on in the store ).
		 *
		 * @return int
		 */
		public static function products_for_sale() {
			global $wpdb;
			if ( ! isset( $wpdb ) ) {
				return 0;
			}
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_product WHERE is_subscription_item = 1 AND activate_in_store = 1' );
		}

		/**
		 * Subscription products are for sale and can't be bought: Settings › Payments and Store Status say so.
		 *
		 * @return int How many, 0 when nothing needs saying.
		 */
		public static function stranded_products() {
			return self::ready() ? 0 : self::products_for_sale();
		}

		/**
		 * The admin notice for a context, or '' when subscriptions can be sold.
		 *
		 * @param string $context See text().
		 * @param array  $args    count ( int ), link ( bool, default true: Open Settings › Payments ), new_tab ( bool ), class.
		 * @return string HTML.
		 */
		public static function notice_html( $context, $args = array() ) {
			if ( self::ready() ) {
				return '';
			}
			$args    = array_merge(
				array(
					'count'   => 0,
					'link'    => true,
					'new_tab' => false,
					'class'   => '',
				),
				(array) $args
			);
			$text    = self::text( $context, $args['count'] );
			$class   = trim( 'wpec-subscription-gateway-notice ' . $args['class'] );
			$actions = array();
			if ( $args['link'] ) {
				$actions[] = array(
					'label'  => __( 'Open Settings › Payments', 'wp-easycart' ),
					'url'    => self::settings_url(),
					'link'   => true,
					'target' => $args['new_tab'] ? '_blank' : '',
				);
			}
			if ( function_exists( 'wp_easycart_admin_notice_html' ) ) {
				return wp_easycart_admin_notice_html(
					'warning',
					$text['title'],
					array(
						'detail'      => $text['detail'],
						'actions'     => $actions,
						'dismissible' => false,
						'class'       => $class,
					)
				);
			}
			$html = '<div class="notice notice-warning inline ' . esc_attr( $class ) . '"><p><strong>' . esc_html( $text['title'] ) . '</strong> ' . esc_html( $text['detail'] );
			foreach ( $actions as $action ) {
				$html .= ' <a href="' . esc_url( $action['url'] ) . '"' . ( '' !== $action['target'] ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $action['label'] ) . '</a>';
			}
			return $html . '</p></div>';
		}

		/**
		 * What shoppers read on the subscription page ( Settings › Languages › Cart - Login, "Subscription Unavailable" ).
		 *
		 * @return string Safe HTML.
		 */
		public static function shopper_text() {
			$text = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'cart_login', 'cart_subscription_unavailable' ) : '';
			return ( '' !== trim( $text ) ) ? $text : esc_html__( 'This subscription can\'t be bought online right now. Please contact us to sign up.', 'wp-easycart' );
		}

		/**
		 * Is the person looking at the storefront someone who runs the store?
		 *
		 * @return bool
		 */
		private static function is_staff() {
			return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WP EasyCart's store manager role, as the subscription template checks it.
		}

		/**
		 * The subscription page when subscriptions can't be bought: a message instead of a payment form that could not
		 * charge, and for store staff what to fix. Printed by ec_cartpage::display_subscription_page() before the template,
		 * so a theme's copy of ec_cart_subscription.php gets it too.
		 */
		public static function print_unavailable_page() {
			echo '<section class="ec_cart_page ec_cart_subscription ec_cart_subscription_unavailable">';
			if ( self::is_staff() ) {
				echo '<div class="ec_subscription_unavailable_staff" role="note" style="float:left; width:100%; box-sizing:border-box; margin:20px 0 0; padding:12px 16px; border:1px solid #f0c36d; border-left-width:4px; background:#fff8e5; color:#4a3000; font-size:14px; line-height:1.5;">';
				echo '<strong>' . esc_html__( 'Only store staff see this note.', 'wp-easycart' ) . '</strong> ';
				echo esc_html( self::summary() ) . ' ';
				echo esc_html__( 'Until Stripe takes your card payments, shoppers see the message below instead of a checkout.', 'wp-easycart' ) . ' ';
				echo '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Open Settings › Payments', 'wp-easycart' ) . '</a>';
				echo '</div>';
			}
			echo '<div class="ec_subscription_purchased ec_subscription_unavailable" role="status">' . self::shopper_text() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shopper_text() is the language file's escaped phrase or esc_html__().
			echo '<div style="clear:both;"></div></section>';
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_subscriptions_ready' ) ) {
	/**
	 * Can this store sell subscriptions ( Stripe is the card gateway and is connected )? WP EasyCart PRO asks it before a
	 * code may discount a subscription.
	 *
	 * @since 6.0.2
	 * @return bool
	 */
	function wp_easycart_subscriptions_ready() {
		return wp_easycart_subscription_gateway::ready();
	}
}
