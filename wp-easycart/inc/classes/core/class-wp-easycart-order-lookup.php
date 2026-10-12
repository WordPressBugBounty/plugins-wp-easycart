<?php
/**
 * WP EasyCart — guest order lookup ( 6.0.4 ).
 *
 * A shopper who ordered without an account types the order number and the email they used; when both match an order, the
 * order's link ( the account page's order details with the order's guest key, the link the receipt already carries ) is
 * emailed to the address on the order. The page always answers the same way, so it never says whether an order exists, and
 * the email goes out after the answer where PHP-FPM allows ( fastcgi_finish_request ), so the time it takes gives nothing
 * away either.
 *
 * Shortcode [ec_order_lookup], the account page's ec_page=order_lookup, the withdrawal page ( wpec_view=lookup ) and a link
 * under the account sign-in form. The form posts without a page nonce ( it works on cached pages ); 5 tries in 15 minutes and
 * 20 a day from one address, 3 emails a day for one order, and the robot check from Settings › Checkout protection after 2
 * tries when one is set up ( wp_easycart_withdrawals::hit(), check_needed() ). Available whether or not the withdrawal button
 * is on. Wording: language section withdrawal ( lookup_* ).
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_lookup' ) ) :

	/**
	 * Guest order lookup.
	 *
	 * @since 6.0.4
	 */
	final class wp_easycart_order_lookup {

		/** Emails one order may receive in a day. */
		const LIMIT_ORDER = 3;

		/** Register hooks. */
		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
			add_action( 'wpeasycart_account_login_post', array( __CLASS__, 'login_link' ) );
		}

		/** Shortcode [ec_order_lookup]. */
		public static function register_shortcode() {
			add_shortcode( 'ec_order_lookup', array( __CLASS__, 'shortcode' ) );
		}

		/**
		 * The lookup form's address: the withdrawal page ( wpec_view=lookup ) when the button is on and has its page, else the
		 * account page ( ec_page=order_lookup ).
		 *
		 * @return string
		 */
		public static function url() {
			if ( class_exists( 'wp_easycart_withdrawals' ) && wp_easycart_withdrawals::enabled() && wp_easycart_withdrawals::page_id() > 0 ) {
				return wp_easycart_withdrawals::page_url( array( 'wpec_view' => 'lookup' ) );
			}
			$url = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_account_page() : home_url( '/' );
			return add_query_arg( 'ec_page', 'order_lookup', $url );
		}

		/**
		 * The order's own link: the account page's order details with its guest key ( as the receipt links it ).
		 *
		 * @param object $order Order row.
		 * @return string
		 */
		public static function order_url( $order ) {
			if ( ! function_exists( 'wpeasycart_links' ) || ! class_exists( 'wp_easycart_order_pay' ) ) {
				return '';
			}
			return (string) wpeasycart_links()->get_account_page(
				'order_details',
				array(
					'order_id'     => (int) $order->order_id,
					'ec_guest_key' => wp_easycart_order_pay::ensure_key( (int) $order->order_id ),
				)
			);
		}

		/**
		 * A post of the lookup form: the same answer every time ( sent ), an error for a missing field, too many tries or a robot
		 * check.
		 *
		 * @return array The page's state.
		 */
		public static function handle_post() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- the form works on cached pages, so it carries no page nonce: it only ever emails an order's link to the order's own address, and the rate limits protect it.
			$values = array(
				'order_ref' => isset( $_POST['wpec_lookup_order'] ) ? substr( preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_POST['wpec_lookup_order'] ) ) ), 0, 64 ) : '',
				'email'     => isset( $_POST['wpec_lookup_email'] ) ? sanitize_email( wp_unslash( $_POST['wpec_lookup_email'] ) ) : '',
				'website'   => isset( $_POST['wpec_lookup_website'] ) ? sanitize_text_field( wp_unslash( $_POST['wpec_lookup_website'] ) ) : '',
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$state = array(
				'view'   => 'lookup',
				'values' => $values,
				'errors' => array(),
				'sent'   => false,
				'check'  => wp_easycart_withdrawals::check_needed( 'lk' ),
			);
			if ( '' === $values['order_ref'] || '' === $values['email'] ) {
				$state['errors'][] = wp_easycart_withdrawals::text( 'error_fields' );
				return $state;
			}
			if ( ! is_email( $values['email'] ) ) {
				$state['errors'][] = wp_easycart_withdrawals::text( 'error_email' );
				return $state;
			}
			if ( $state['check'] && ! wp_easycart_withdrawals::check_passed( $state['check'] ) ) {
				$state['errors'][] = wp_easycart_withdrawals::text( 'error_check' );
				return $state;
			}
			if ( ! wp_easycart_withdrawals::hit( wp_easycart_withdrawals::ip_limits( 'lk' ) ) ) {
				$state['errors'][] = wp_easycart_withdrawals::text( 'error_limit' );
				return $state;
			}
			$state['sent'] = true;
			if ( '' !== $values['website'] ) {
				return $state; /* the hidden field only a robot fills in: the same answer, nothing sent */
			}
			$order = wp_easycart_withdrawals::find_by_email( $values['order_ref'], $values['email'] );
			if ( $order && wp_easycart_withdrawals::hit( array( 'wpec_lk_o_' . (int) $order->order_id => array( self::LIMIT_ORDER, DAY_IN_SECONDS ) ) ) ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					add_action(
						'shutdown',
						function () use ( $order ) {
							fastcgi_finish_request();
							self::send( $order );
						},
						0
					);
				} else {
					self::send( $order );
				}
			}
			return $state;
		}

		/**
		 * Email an order's link to the address on the order.
		 *
		 * @param object $order Order row.
		 * @return bool
		 */
		public static function send( $order ) {
			$to  = (string) $order->user_email;
			$url = self::order_url( $order );
			if ( ! is_email( $to ) || '' === $url ) {
				return false;
			}
			$lookup_email   = array(
				'order_id' => (int) $order->order_id,
				'url'      => $url,
				'name'     => trim( wp_unslash( (string) $order->billing_first_name ) ),
			);
			$subject        = wp_easycart_withdrawals::text( 'lookup_email_subject', array( 'order_id' => (int) $order->order_id ) );
			$email_logo_url = (string) get_option( 'ec_option_email_logo' );
			$page_id        = (int) get_option( 'ec_option_storepage' );
			$store_page     = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );
			ob_start();
			include wp_easycart_withdrawals::template( 'ec_order_lookup_email.php' );
			$html = (string) ob_get_clean();
			if ( class_exists( 'ec_email' ) ) {
				return (bool) ec_email::send(
					$to,
					wp_strip_all_tags( $subject ),
					$html,
					array(
						'type'     => 'order_lookup',
						'order_id' => (int) $order->order_id,
						'channel'  => 'order',
					)
				);
			}
			return (bool) wp_mail( $to, wp_strip_all_tags( $subject ), $html, array( 'Content-Type: text/html; charset=utf-8' ) );
		}

		/**
		 * [ec_order_lookup]: the lookup form. heading="0" leaves out its heading.
		 *
		 * @param array $atts Shortcode attributes.
		 * @return string
		 */
		public static function shortcode( $atts ) {
			$atts = shortcode_atts( array( 'heading' => '1' ), $atts, 'ec_order_lookup' );
			return self::render( array( 'heading' => '0' !== (string) $atts['heading'] ) );
		}

		/**
		 * Draw the lookup form ( ec_order_lookup.php ).
		 *
		 * @param array $args heading, embedded ( inside the withdrawal page ).
		 * @return string
		 */
		public static function render( $args = array() ) {
			$state  = wp_easycart_withdrawals::state();
			$lookup = array(
				'heading'  => ! empty( $args['heading'] ),
				'embedded' => ! empty( $args['embedded'] ),
				'values'   => array(
					'order_ref' => '',
					'email'     => '',
				),
				'errors'   => array(),
				'sent'     => false,
				'check'    => null,
				'action'   => wp_easycart_withdrawals::current_url(),
			);
			if ( is_array( $state ) && isset( $state['view'] ) && 'lookup' === $state['view'] ) {
				$lookup = array_merge( $lookup, array_intersect_key( $state, array_flip( array( 'values', 'errors', 'sent', 'check' ) ) ) );
			}
			wp_easycart_withdrawals::enqueue_check( $lookup['check'] );
			/**
			 * What the order lookup form shows ( ec_order_lookup.php ).
			 *
			 * @since 6.0.4
			 * @param array $lookup heading, embedded, values ( order_ref, email ), errors, sent, check, action.
			 */
			$lookup = (array) apply_filters( 'wp_easycart_order_lookup_view', $lookup );
			ob_start();
			include wp_easycart_withdrawals::template( 'ec_order_lookup.php' );
			return (string) ob_get_clean();
		}

		/** Under the account sign-in form ( wpeasycart_account_login_post ): a link to the lookup. */
		public static function login_link() {
			echo '<p class="ec_order_lookup_link"><a href="' . esc_url( self::url() ) . '">' . esc_html( wp_easycart_withdrawals::text( 'login_lookup' ) ) . '</a></p>';
		}
	}

	wp_easycart_order_lookup::init();

endif;
