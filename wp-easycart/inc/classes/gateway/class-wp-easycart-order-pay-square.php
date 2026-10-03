<?php
/**
 * WP EasyCart — Square charge for an order's pay link ( wp_easycart_order_pay, 6.0.2 ).
 *
 * The checkout's ec_square builds its charge from the cart and creates a Square order first. Paying an existing order needs
 * neither: this subclass sends one /v2/payments request for what is due ( the order's total, or the balance of an order
 * changed after it was paid ) ( with one idempotency key per card token, so
 * a retried request cannot charge twice ) and lets ec_square::handle_gateway_response() record it exactly as the checkout
 * does ( gateway_transaction_id JSON, which refunds read ).
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'ec_square' ) && ! class_exists( 'wp_easycart_order_pay_square' ) ) :

	/**
	 * Square charge for one existing order.
	 *
	 * @since 6.0.2
	 */
	class wp_easycart_order_pay_square extends ec_square {

		/**
		 * The Square payment id of the last successful charge_order().
		 *
		 * @since 6.0.2
		 * @var string
		 */
		public $payment_id = '';

		/**
		 * Charge the order.
		 *
		 * @param object $order   Order row ( billing address already saved ).
		 * @param string $token   Card token ( sourceId ).
		 * @param string $verify  Buyer verification token ( SCA ), or ''.
		 * @param int    $amount  Amount in the smallest unit.
		 * @param bool   $balance 6.0.2: a payment toward the balance of an order already paid. Its card and Square ids stay
		 *                        off the order, which keeps the first payment's ( its refunds go through them ).
		 * @return true|WP_Error
		 */
		public function charge_order( $order, $token, $verify, $amount, $balance = false ) {
			if ( '' === (string) get_option( 'ec_option_square_currency' ) ) {
				$this->set_currency();
			}
			if ( '' === (string) get_option( 'ec_option_square_application_id' ) ) {
				$this->renew_token();
			}
			$location = (string) ( get_option( 'ec_option_square_is_sandbox' ) ? get_option( 'ec_option_square_sandbox_location_id' ) : get_option( 'ec_option_square_location_id' ) );
			$generic  = wp_strip_all_tags( wp_easycart_order_pay::text( 'pay_error', __( 'The payment could not be completed. Please try again, or use another payment method.', 'wp-easycart' ) ) );
			if ( '' === $location ) {
				return new WP_Error( 'square', $generic );
			}
			$this->order_id     = (int) $order->order_id;
			$this->order_totals = (object) array( 'grand_total' => (float) $order->grand_total );
			$currency           = (string) get_option( 'ec_option_square_currency' );
			$data               = array(
				'source_id'           => $token,
				'amount_money'        => array(
					'amount'   => (int) $amount,
					'currency' => $currency,
				),
				'idempotency_key'     => substr( 'wpec-' . (int) $order->order_id . '-' . md5( $token ), 0, 45 ),
				'reference_id'        => (string) (int) $order->order_id,
				'note'                => 'EasyCart - Order ' . (int) $order->order_id,
				'billing_address'     => array(
					'address_line_1'                  => (string) $order->billing_address_line_1,
					'address_line_2'                  => (string) $order->billing_address_line_2,
					'locality'                        => (string) $order->billing_city,
					'administrative_district_level_1' => (string) $order->billing_state,
					'postal_code'                     => (string) $order->billing_zip,
					'country'                         => strtoupper( (string) $order->billing_country ),
				),
				'buyer_email_address' => (string) $order->user_email,
				'location_id'         => $location,
			);
			if ( '' !== $verify ) {
				$data['verification_token'] = $verify;
			}
			/* The same application fee the checkout charges through the WP EasyCart Square app. */
			$fee = (int) number_format( (int) $amount * apply_filters( 'wp_easycart_stripe_connect_fee_rate', 2 ) * .01, 0, '', '' ); /* 6.0.2: on what is charged ( a balance is less than the total ) */
			if ( $fee > 0 && 'USD' === $currency && ! get_option( 'ec_option_square_is_sandbox' ) ) {
				$data['app_fee_money'] = array(
					'amount'   => $fee,
					'currency' => $currency,
				);
			}
			$request  = new WP_Http();
			$response = $request->request(
				$this->get_gateway_url(),
				array(
					'method'  => 'POST',
					'headers' => array(
						'Accept'         => 'application/json',
						'Content-Type'   => 'application/json',
						'Authorization'  => 'Bearer ' . ( get_option( 'ec_option_square_is_sandbox' ) ? get_option( 'ec_option_square_sandbox_access_token' ) : get_option( 'ec_option_square_access_token' ) ),
						'Square-Version' => '2022-05-12',
					),
					'body'    => wp_json_encode( $data ),
					'timeout' => 30,
				)
			);
			if ( is_wp_error( $response ) ) {
				$this->mysqli->insert_response( (int) $order->order_id, 1, 'SQUARE PAY LINK CURL ERROR', $response->get_error_message() );
				return new WP_Error( 'square', $generic );
			}
			global $wpdb;
			$body   = (string) wp_remote_retrieve_body( $response );
			$decode = json_decode( $body );
			$first  = $balance ? $wpdb->get_var( $wpdb->prepare( 'SELECT gateway_transaction_id FROM ec_order WHERE order_id = %d', (int) $order->order_id ) ) : null;
			if ( ! $balance && isset( $decode->payment->card_details->card ) ) {
				$card = $decode->payment->card_details->card;
				$wpdb->update(
					'ec_order',
					array(
						'payment_method'    => isset( $card->card_brand ) ? (string) $card->card_brand : 'credit_card',
						'cc_exp_month'      => isset( $card->exp_month ) ? (string) $card->exp_month : '',
						'cc_exp_year'       => isset( $card->exp_year ) ? (string) $card->exp_year : '',
						'creditcard_digits' => isset( $card->last_4 ) ? (string) $card->last_4 : '',
					),
					array( 'order_id' => (int) $order->order_id )
				);
			}
			$this->handle_gateway_response( $body );
			if ( null !== $first ) {
				/* handle_gateway_response() writes the new payment's ids on the order: put the first payment's back. */
				$wpdb->update( 'ec_order', array( 'gateway_transaction_id' => (string) $first ), array( 'order_id' => (int) $order->order_id ) );
			}
			if ( ! $this->is_success ) {
				return new WP_Error( 'square', ( '' !== (string) $this->error_message ) ? (string) $this->error_message : $generic );
			}
			$this->payment_id = isset( $decode->payment->id ) ? (string) $decode->payment->id : '';
			return true;
		}
	}

endif;
