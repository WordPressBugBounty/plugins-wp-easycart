<?php
/**
 * Product inquiry email ( the store copy, and the shopper copy when "send me a copy" is ticked ).
 *
 * Included by ec_cartpage::process_send_inquiry() and ec_cartpage::send_inquiry() with:
 *   $product, $inquiry_name, $inquiry_email, $inquiry_message, $send_copy, $option1 … $option5 ( optionitem rows, or
 *   option names from send_inquiry() ), $option1_option … $option5_option ( ec_option rows, process_send_inquiry() only ),
 *   $option_vals ( advanced options ), $file_temp_num ( customer upload folder ), $has_product_options,
 *   $email_logo_url, $store_page, $permalink_divider.
 *
 * The sender renders this once: the shopper copy is the same HTML with the upload download links removed
 * ( wp_easycart_admin_order_uploads::strip_links() ), then filtered by wpeasycart_inquiry_email_content /
 * wpeasycart_inquiry_email_admin_content. Upload links therefore stay plain <a href> tags built by
 * wp_easycart_admin_order_uploads::inquiry_url().
 *
 * 6.0.0: rebuilt on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * The price calculation is unchanged. The message keeps its line breaks ( it printed a literal "<br />" before ), the
 * SKU includes advanced option SKU extensions, and the helper function can no longer be declared twice. Copy this file to
 * your wp-easycart-data layout folder to customise it; the file name must stay the same.
 *
 * Language strings kept: product_details / product_details_inquiry_title, _name, _email, _message, _thank_you.
 * Options kept: ec_option_hide_price_inquiry, ec_option_model_number_extension, ec_option_enable_metric_unit_display.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
if ( ! isset( $product ) || ! is_object( $product ) ) {
	return;
}

$inquiry_model_number   = $product->model_number;
$unit_price             = 0;
$options_price          = 0;
$grid_price_change      = 0;
$options_price_onetime  = 0;
$price_multiplier       = 0;
$options_weight         = isset( $options_weight ) ? $options_weight : 0;
$options_weight_onetime = isset( $options_weight_onetime ) ? $options_weight_onetime : 0;
$weight                 = isset( $weight ) ? $weight : 0;
$weight_multiplier      = isset( $weight_multiplier ) ? $weight_multiplier : 1;
$option1                = isset( $option1 ) ? $option1 : '';
$option2                = isset( $option2 ) ? $option2 : '';
$option3                = isset( $option3 ) ? $option3 : '';
$option4                = isset( $option4 ) ? $option4 : '';
$option5                = isset( $option5 ) ? $option5 : '';
$option_vals            = ( isset( $option_vals ) && is_array( $option_vals ) ) ? $option_vals : array();
$file_temp_num          = isset( $file_temp_num ) ? $file_temp_num : '';

if ( ! function_exists( 'wp_easycart_inquiry_get_dimension_decimal' ) ) {
	/**
	 * Fraction of an inch as a decimal ( dimension options ).
	 *
	 * @param string $value Fraction, e.g. 3/8.
	 * @return float
	 */
	function wp_easycart_inquiry_get_dimension_decimal( $value ) {
		$fractions = array(
			'1/16'  => .0625,
			'1/8'   => .1250,
			'3/16'  => .1875,
			'1/4'   => .2500,
			'5/16'  => .3125,
			'3/8'   => .3750,
			'7/16'  => .4375,
			'1/2'   => .5000,
			'9/16'  => .5625,
			'5/8'   => .6250,
			'11/16' => .6875,
			'3/4'   => .7500,
			'13/16' => .8125,
			'7/8'   => .8750,
			'15/16' => .9375,
		);
		return isset( $fractions[ (string) $value ] ) ? $fractions[ (string) $value ] : 0;
	}
}

/* Basic option prices ( optionitem rows only; send_inquiry() passes names ). */
foreach ( array( $option1, $option2, $option3, $option4, $option5 ) as $ec_inq_basic ) {
	if ( is_object( $ec_inq_basic ) && isset( $ec_inq_basic->optionitem_price ) ) {
		$options_price += $ec_inq_basic->optionitem_price;
	}
}

/* Advanced option prices and SKU extensions ( unchanged calculation ). */
if ( $product->use_both_option_types || $product->use_advanced_optionset ) {
	foreach ( $option_vals as $advanced_option ) {
		$advanced_option_details = $GLOBALS['ec_options']->get_optionitem( $advanced_option['optionitem_id'] );
		$advanced_option_data    = $GLOBALS['ec_options']->get_option( $advanced_option['option_id'] );
		if ( ! is_object( $advanced_option_details ) || ! is_object( $advanced_option_data ) ) {
			continue;
		}
		if ( '' != $advanced_option['optionitem_model_number'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- legacy data may be null.
			$inquiry_model_number = $inquiry_model_number . get_option( 'ec_option_model_number_extension' ) . $advanced_option['optionitem_model_number'];
		}
		if ( 'grid' == $advanced_option_data->option_type ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- database string.
			$grid_id = $advanced_option['option_id'];
			if ( 0 != $advanced_option_details->optionitem_price ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$grid_price_change = $grid_price_change + ( $advanced_option_details->optionitem_price * $advanced_option['optionitem_value'] );
			} elseif ( 0 != $advanced_option_details->optionitem_price_onetime ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$grid_price_change = $grid_price_change + $advanced_option_details->optionitem_price_onetime;
			} elseif ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$grid_price_change = $grid_price_change + ( ( $advanced_option_details->optionitem_price_override - $product->price ) * $advanced_option['optionitem_value'] );
			} elseif ( $advanced_option_details->optionitem_price_multiplier > 1 ) {
				$grid_price_change = $product->price * ( $advanced_option_details->optionitem_price_multiplier - 1 );
			}
		} elseif ( 'number' == $advanced_option_data->option_type ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- database string.
			if ( 0 != $advanced_option_details->optionitem_price ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_price = $options_price + ( $advanced_option_details->optionitem_price * $advanced_option['optionitem_value'] );
			} elseif ( 0 != $advanced_option_details->optionitem_price_onetime ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_price_onetime = $options_price_onetime + $advanced_option_details->optionitem_price_onetime;
			} elseif ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$product->price = $advanced_option_details->optionitem_price_override;
			}
			if ( 0 != $advanced_option_details->optionitem_price_multiplier ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				if ( 0 == $price_multiplier ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric.
					$price_multiplier = 1;
				}
				$price_multiplier = $price_multiplier * $advanced_option_details->optionitem_price_multiplier * $advanced_option['optionitem_value'];
			}
		} elseif ( 'dimensions1' == $advanced_option_data->option_type || 'dimensions2' == $advanced_option_data->option_type ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- database string.
			$dimensions = json_decode( $advanced_option['optionitem_value'] );
			$dimensions = is_array( $dimensions ) ? $dimensions : array();
			if ( 2 === count( $dimensions ) ) {
				if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) {
					$product->price = $product->price * ( ( $dimensions[0] / 12 ) * ( $dimensions[1] / 12 ) );
				} else {
					$product->price = $product->price * ( ( $dimensions[0] / 1000 ) * ( $dimensions[1] / 1000 ) );
				}
			} elseif ( 4 === count( $dimensions ) ) {
				if ( ! get_option( 'ec_option_enable_metric_unit_display' ) ) {
					$product->price = $product->price * ( ( ( intval( $dimensions[0] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[1] ) ) / 12 ) * ( ( intval( $dimensions[2] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[3] ) ) / 12 ) );
				} else {
					$product->price = $product->price * ( ( ( intval( $dimensions[0] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[1] ) ) / 1000 ) * ( ( intval( $dimensions[2] ) + wp_easycart_inquiry_get_dimension_decimal( $dimensions[3] ) ) / 1000 ) );
				}
			}
		} else {
			if ( 0 != $advanced_option_details->optionitem_price ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_price = $options_price + $advanced_option_details->optionitem_price;
			} elseif ( 0 != $advanced_option_details->optionitem_price_onetime ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_price_onetime = $options_price_onetime + $advanced_option_details->optionitem_price_onetime;
			} elseif ( $advanced_option_details->optionitem_price_override >= 0 ) {
				$product->price = $advanced_option_details->optionitem_price_override;
			}
			if ( 0 != $advanced_option_details->optionitem_price_multiplier ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				if ( 0 == $price_multiplier ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric.
					$price_multiplier = 1;
				}
				$price_multiplier = $price_multiplier * $advanced_option_details->optionitem_price_multiplier;
			}
			if ( isset( $advanced_option_details->optionitem_price_per_character ) && $advanced_option_details->optionitem_price_per_character > 0 ) {
				$num_chars     = strlen( preg_replace( '/\s+/', '', $advanced_option['optionitem_value'] ) );
				$options_price = $options_price + ( $num_chars * $advanced_option_details->optionitem_price_per_character );
			}
			if ( 0 != $advanced_option_details->optionitem_weight ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_weight = $options_weight + $advanced_option_details->optionitem_weight;
			} elseif ( 0 != $advanced_option_details->optionitem_weight_onetime ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
				$options_weight_onetime = $options_weight_onetime + $advanced_option_details->optionitem_weight_onetime;
			} elseif ( $advanced_option_details->optionitem_weight_override >= 0 ) {
				$weight = $advanced_option_details->optionitem_weight_override;
			}
			if ( $advanced_option_details->optionitem_weight_multiplier > 1 ) {
				$weight_multiplier = $advanced_option_details->optionitem_weight_multiplier;
			}
		}
	}
}
$unit_price = $product->price + $options_price;
if ( $price_multiplier > 0 ) {
	$unit_price = $unit_price * $price_multiplier;
}

/* Output */
$ed                 = 'wp_easycart_email_design';
$ec_inq_lang        = wp_easycart_language();
$ec_inq_title       = $ec_inq_lang->get_text( 'product_details', 'product_details_inquiry_title' );
$ec_inq_name        = isset( $inquiry_name ) ? stripslashes( (string) $inquiry_name ) : '';
$ec_inq_email       = isset( $inquiry_email ) ? stripslashes( (string) $inquiry_email ) : '';
$ec_inq_message     = isset( $inquiry_message ) ? stripslashes( (string) $inquiry_message ) : '';
$ec_inq_label_clean = function ( $text ) {
	return rtrim( ltrim( trim( (string) $text ), '*' ), ': ' );
};

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_inq_title ),
		'preheader' => wp_strip_all_tags( $product->title ) . ( '' !== $ec_inq_name ? ' - ' . $ec_inq_name : '' ),
		'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
		'store_url' => ( isset( $store_page ) && '' !== (string) $store_page ) ? (string) $store_page : null,
	)
);

$ed::section_start();
$ed::heading( wp_kses_post( $ec_inq_title ) );
$ed::section_end();

/* Product, SKU, price and chosen options */
$ed::section_start( array( 'top' => 4 ) );
$ed::card_start();
echo '<div style="' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:15px;">' . wp_kses_post( $product->title ) . '</div>';
echo '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">';
if ( '' !== (string) $inquiry_model_number ) {
	$ed::detail( esc_html( $inquiry_model_number ), array( 'nolink' => true, 'style' => 'padding:2px 0 4px 0;' ) );
}
if ( $product->use_both_option_types || ! $product->use_advanced_optionset ) {
	$ec_inq_basic_options = array(
		array( $option1, isset( $option1_option ) ? $option1_option : false ),
		array( $option2, isset( $option2_option ) ? $option2_option : false ),
		array( $option3, isset( $option3_option ) ? $option3_option : false ),
		array( $option4, isset( $option4_option ) ? $option4_option : false ),
		array( $option5, isset( $option5_option ) ? $option5_option : false ),
	);
	foreach ( $ec_inq_basic_options as $ec_inq_basic_pair ) {
		if ( ! $ec_inq_basic_pair[0] ) {
			continue;
		}
		$ec_inq_basic_name  = is_object( $ec_inq_basic_pair[0] ) ? ( isset( $ec_inq_basic_pair[0]->optionitem_name ) ? $ec_inq_basic_pair[0]->optionitem_name : '' ) : (string) $ec_inq_basic_pair[0];
		$ec_inq_basic_label = ( is_object( $ec_inq_basic_pair[1] ) && isset( $ec_inq_basic_pair[1]->option_label ) ) ? $ec_inq_basic_pair[1]->option_label : '';
		$ed::option_detail( wp_easycart_escape_html( $ec_inq_basic_label ), wp_easycart_escape_html( $ec_inq_basic_name ) );
	}
}
if ( $product->use_both_option_types || $product->use_advanced_optionset ) {
	foreach ( $option_vals as $advanced_option ) {
		if ( 'file' == $advanced_option['option_type'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- sanitized string.
			/* 6.0.0: customer uploads are private; link through the gated download handler ( signed link when private links are on ). */
			$ec_upload_url = class_exists( 'wp_easycart_admin_order_uploads' ) ? wp_easycart_admin_order_uploads::inquiry_url( $file_temp_num, $advanced_option['optionitem_value'] ) : '';
			if ( '' !== $ec_upload_url ) {
				$ed::option_detail( wp_kses_post( $advanced_option['option_label'] ), '<a href="' . esc_url( $ec_upload_url ) . '" target="_blank" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $advanced_option['optionitem_value'] ) . '</a>' );
			} else {
				$ed::option_detail( wp_kses_post( $advanced_option['option_label'] ), esc_html( $advanced_option['optionitem_value'] ) );
			}
		} elseif ( 'grid' == $advanced_option['option_type'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- sanitized string.
			$ed::option_detail( wp_kses_post( $advanced_option['option_label'] ), esc_html( $advanced_option['optionitem_name'] . ' (' . $advanced_option['optionitem_value'] . ')' ) );
		} else {
			$ed::option_detail( wp_kses_post( $advanced_option['option_label'] ), esc_html( $advanced_option['optionitem_value'] ) );
		}
	}
}
echo '</table>';
if ( ! get_option( 'ec_option_hide_price_inquiry' ) ) {
	echo '<div style="margin-top:8px;' . esc_attr( $ed::css( 'strong' ) ) . 'font-size:16px;">' . $ed::money( $unit_price ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- money() escapes the formatted amount.
}
$ed::card_end();
$ed::section_end();

/* Who asked */
$ed::section_start( array( 'top' => 16 ) );
$ed::key_values(
	array(
		array(
			'label' => wp_kses_post( $ec_inq_label_clean( $ec_inq_lang->get_text( 'product_details', 'product_details_inquiry_name' ) ) ),
			'value' => esc_html( $ec_inq_name ),
		),
		array(
			'label' => wp_kses_post( $ec_inq_label_clean( $ec_inq_lang->get_text( 'product_details', 'product_details_inquiry_email' ) ) ),
			'value' => ( '' !== $ec_inq_email ) ? '<a href="' . esc_url( 'mailto:' . $ec_inq_email ) . '" style="' . esc_attr( $ed::css( 'link' ) ) . '">' . esc_html( $ec_inq_email ) . '</a>' : '',
		),
	)
);
$ed::section_end();

/* Message */
if ( '' !== trim( $ec_inq_message ) ) {
	$ed::section_start( array( 'top' => 12 ) );
	$ed::label( wp_kses_post( $ec_inq_label_clean( $ec_inq_lang->get_text( 'product_details', 'product_details_inquiry_message' ) ) ) );
	$ed::card_start( array( 'padding' => '12px 14px', 'background' => '#ffffff' ) );
	echo nl2br( esc_html( $ec_inq_message ) );
	$ed::card_end();
	$ed::section_end();
}

$ed::section_start( array( 'top' => 24, 'bottom' => 8 ) );
$ed::paragraph( wp_kses_post( $ec_inq_lang->get_text( 'product_details', 'product_details_inquiry_thank_you' ) ), array( 'margin' => '0' ) );
$ed::section_end();

$ed::close();
