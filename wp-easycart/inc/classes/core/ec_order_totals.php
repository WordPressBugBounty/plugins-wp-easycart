<?php
class ec_order_totals {
	public $sub_total;
	public $converted_sub_total;
	public $tax_total;
	public $handling_total;
	public $shipping_total;
	public $shipping_discount;
	public $duty_total;
	public $vat_total;
	public $gst_total;
	public $pst_total;
	public $hst_total;
	public $fee_total;
	public $tip_total;
	public $discount_total;
	public $grand_total;
	public $converted_grand_total;
	public $tax;

	function __construct( $cart, $user, $shipping, $tax, $discount ) {
		$this->tax = $tax;
		$this->sub_total = number_format( $cart->subtotal, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->set_converted_sub_total( $cart );
		$this->handling_total = number_format( $cart->get_handling_total(), $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$shipping_price_full = floatval( $shipping->get_shipping_price( $this->handling_total, $discount, false ) );
		$shipping_price = floatval( $shipping->get_shipping_price( $this->handling_total, $discount ) );
		$this->shipping_discount = $shipping_price_full - $shipping_price;
		$setting_row = $GLOBALS['ec_setting']->setting_row;
		$global_handling = ( isset( $setting_row ) && is_object( $setting_row ) && isset( $setting_row->shipping_handling_rate ) ) ? $setting_row->shipping_handling_rate : 0;
		$this->handling_total = number_format( $this->handling_total + $global_handling, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->shipping_total = number_format( $shipping_price, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		if ( 'square' == get_option( 'ec_option_payment_process_method' ) ) {
			$this->tax_total = number_format( round( $tax->tax_total, 2, PHP_ROUND_HALF_EVEN ), $GLOBALS['currency']->get_decimal_length(), '.', '' );
		} else {
			$this->tax_total = number_format( $tax->tax_total, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		}
		$this->duty_total = number_format( $tax->duty_total, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->vat_total = number_format( $tax->vat_total, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->gst_total = number_format( $tax->gst, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->pst_total = number_format( $tax->pst, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->hst_total = number_format( $tax->hst, $GLOBALS['currency']->get_decimal_length(), '.', '' );
		$this->tip_total = 0;
		if ( get_option( 'ec_option_enable_tips' ) ) {
			$this->tip_total = ( $GLOBALS['ec_cart_data']->cart_data->tip_rate == 'custom' ) ? number_format( $GLOBALS['ec_cart_data']->cart_data->tip_amount, $GLOBALS['currency']->get_decimal_length( ), '.', '' ) : (float) number_format( $GLOBALS['ec_cart_data']->cart_data->tip_rate / 100 * $this->converted_sub_total, $GLOBALS['currency']->get_decimal_length(), '.', '' );
			$this->tip_total = ( $this->tip_total < 0 ) ? 0 : $this->tip_total;
		}
		if ( 'vat' == strtolower(substr( $discount->coupon_code, 0, 3 ) ) ) {
			$mysqli = new ec_db();
			$promocode_row = $GLOBALS['ec_coupons']->redeem_coupon_code( $discount->coupon_code );
			if ( $promocode_row && $promocode_row->is_free_item_based ) {
				$this->vat_total = number_format( 0, $GLOBALS['currency']->get_decimal_length(), '.', '' );
			}
		}
		// 6.0.3: the gift card pays last, like a payment, on the real grand total ( fees, tips and VAT included ).
		$this->settle_giftcard( $discount, $tax );
		// Percentage flex-fees calculated on the order total subtract the full discount ( coupon + gift card ).
		if ( is_object( $discount ) && isset( $discount->discount_total ) && method_exists( $tax, 'set_fee_discount_total' ) ) {
			$tax->set_fee_discount_total( $discount->discount_total );
		}
		$this->fee_total = number_format( $this->sum_fees( $tax ), $GLOBALS['currency']->get_decimal_length( ), '.', '' );
		$this->discount_total = number_format( $discount->discount_total, $GLOBALS['currency']->get_decimal_length( ), '.', '' );
		$this->shipping_total = $this->shipping_total - $discount->shipping_discount;
		$this->grand_total = number_format( $this->get_grand_total( $tax ), $GLOBALS['currency']->get_decimal_length( ), '.', '' );
		$this->set_converted_grand_total( $tax );
	}

	private function sum_fees( $tax ) {
		$total = 0;
		for ( $i = 0; $i < count( $tax->fees ); $i++ ) {
			$total += $tax->fees[ $i ]->amount;
		}
		return $total;
	}

	/**
	 * The gift card's share of this order ( ec_discount::settle_giftcard() ): what the order costs after every other discount,
	 * with the fees worked out on what the card leaves to pay ( a card processing fee is charged only on what a card pays ).
	 *
	 * @since 6.0.3
	 *
	 * @param ec_discount $discount The order's discounts.
	 * @param ec_tax      $tax      The order's taxes and fees.
	 */
	private function settle_giftcard( $discount, $tax ) {
		if ( ! is_object( $discount ) || ! method_exists( $discount, 'settle_giftcard' ) || empty( $discount->giftcard_balance ) ) {
			return;
		}
		$decimals = $GLOBALS['currency']->get_decimal_length();
		$others   = (float) $discount->discount_total - (float) $discount->giftcard_discount;
		if ( method_exists( $tax, 'set_fee_discount_total' ) ) {
			$tax->set_fee_discount_total( $others + (float) $discount->giftcard_balance );
		}
		$gross = (float) $this->sub_total + (float) $this->shipping_total - (float) $discount->shipping_discount + (float) $this->tax_total + (float) $this->gst_total + (float) $this->pst_total + (float) $this->hst_total + round( $this->sum_fees( $tax ), $decimals ) + (float) $this->duty_total + (float) $this->tip_total;
		if ( ! $tax->vat_included ) {
			$gross += (float) $this->vat_total;
		}
		$gross = round( $gross, $decimals );
		/* The other discounts as the Discounts row rounds them ( an offer can leave half a cent: 10% of $99.95 ), so a card that covers
		 * the order brings the grand total to exactly zero, never -$0.01. */
		$owed = $gross - round( $others, $decimals );
		$discount->settle_giftcard( $owed, $decimals );
		$over = round( round( (float) $discount->discount_total, $decimals ) - $gross, $decimals );
		if ( $over > 0 ) {
			$discount->settle_giftcard( $owed - $over, $decimals );
		}
	}

	private function get_grand_total( $tax ) {
		if ( $tax->vat_included ) {
			return $this->sub_total + $this->shipping_total + $this->tax_total + $this->gst_total + $this->pst_total + $this->hst_total + $this->fee_total + $this->duty_total - $this->discount_total + $this->tip_total;
		} else {
			return $this->sub_total + $this->shipping_total + $this->tax_total + $this->gst_total + $this->pst_total + $this->hst_total + $this->fee_total + $this->duty_total + $this->vat_total - $this->discount_total + $this->tip_total;
		}
	}

	public function get_grand_total_in_cents() {
		return number_format( $this->grand_total * 100, 0, '', '' );
	}

	private function set_converted_sub_total( $cart ) {
		$this->converted_sub_total = 0;
		foreach ( $cart->cart as $cartitem ) {
			$this->converted_sub_total += ( isset( $cartitem->converted_total_price ) ) ? $cartitem->converted_total_price : ( $cartitem->price * $cart->shippable_total_items );
		}
	}

	public function get_converted_sub_total() {
		return $this->converted_sub_total;
	}

	private function set_converted_grand_total( $tax ) {
		if ( $tax->vat_included ) {
			$this->converted_grand_total = $this->get_converted_sub_total( ) + $GLOBALS['currency']->convert_price( $this->shipping_total + $this->tax_total + $this->gst_total + $this->pst_total + $this->hst_total + $this->fee_total + $this->duty_total - $this->discount_total + $this->tip_total );
		} else {
			$this->converted_grand_total = $this->get_converted_sub_total( ) + $GLOBALS['currency']->convert_price( $this->shipping_total + $this->tax_total + $this->gst_total + $this->pst_total + $this->hst_total + $this->fee_total + $this->duty_total + $this->vat_total - $this->discount_total + $this->tip_total );
		}
	}

	public function get_converted_grand_total() {
		return $this->converted_grand_total;
	}
}
