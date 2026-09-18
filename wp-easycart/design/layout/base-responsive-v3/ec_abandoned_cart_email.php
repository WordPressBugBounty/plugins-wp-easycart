<?php
/**
 * Abandoned cart email ( legacy single reminder ).
 *
 * Included by wpeasycart_send_abandoned_cart_email() ( wpeasycart.php ) and
 * wp_easycart_admin_abandon_cart_pro::wpeasycart_send_email_reminder() ( PRO ) with:
 *   $tempcart_item ( session / customer row ), $tempcart_rows ( ec_product.* + tempcart_quantity, optionitem_id_1..5 ),
 *   $ec_load_url ( signed return-to-cart link, built by the sender ), $email_logo_url, $cart_page, $permalink_divider,
 *   $store_page ( core sender only ), $wpdb.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Free-gift and bundle child lines stay hidden, product images keep https, and option names are escaped.
 *
 * Hooks kept: wpeasycart_cartitem_image1, wpeasycart_cartitem_image1_optionitem, wp_easycart_email_receipt_image_width.
 * Language strings kept: ec_abandoned_cart_email → email_title, something_in_cart, complete_question, complete_checkout.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}
global $wpdb;

$ed          = 'wp_easycart_email_design';
$ec_ac_lang  = wp_easycart_language();
$ec_ac_rows  = ( isset( $tempcart_rows ) && is_array( $tempcart_rows ) ) ? $tempcart_rows : array();
$ec_ac_img_w = (int) apply_filters( 'wp_easycart_email_receipt_image_width', 70 );
$ec_ac_open  = array(
	'title'     => wp_strip_all_tags( $ec_ac_lang->get_text( 'ec_abandoned_cart_email', 'email_title' ) ),
	'preheader' => $ec_ac_lang->get_text( 'ec_abandoned_cart_email', 'complete_question' ),
	'logo_url'  => isset( $email_logo_url ) ? (string) $email_logo_url : (string) get_option( 'ec_option_email_logo' ),
);
if ( ! empty( $store_page ) ) {
	$ec_ac_open['store_url'] = (string) $store_page;
}
$ed::open( $ec_ac_open );

$ed::section_start( array( 'top' => 16 ) );
$ed::heading( wp_kses_post( $ec_ac_lang->get_text( 'ec_abandoned_cart_email', 'something_in_cart' ) ) );
$ed::paragraph( wp_kses_post( $ec_ac_lang->get_text( 'ec_abandoned_cart_email', 'complete_question' ) ), array( 'margin' => '0' ) );
$ed::section_end();

$ec_ac_has_items = false;
foreach ( $ec_ac_rows as $tempcart_row ) {
	if ( ( isset( $tempcart_row->free_gift_offer_id ) && $tempcart_row->free_gift_offer_id > 0 ) || ( isset( $tempcart_row->bundle_group_key ) && '' != $tempcart_row->bundle_group_key && isset( $tempcart_row->bundle_product_id ) && $tempcart_row->bundle_product_id != $tempcart_row->product_id ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- numeric strings from the database.
		continue;
	}
	if ( ! $ec_ac_has_items ) {
		$ed::items_start(
			array(
				'product' => esc_html__( 'Product', 'wp-easycart' ),
				'qty'     => esc_html__( 'Qty', 'wp-easycart' ),
			)
		);
		$ec_ac_has_items = true;
	}

	/* Main image ( first gallery entry when the product uses the image gallery ). */
	$product_images = ( isset( $tempcart_row->product_images ) && '' != $tempcart_row->product_images ) ? explode( ',', $tempcart_row->product_images ) : array(); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- legacy value.
	$image1         = $tempcart_row->image1;
	if ( count( $product_images ) > 0 ) {
		if ( 'image1' === $product_images[0] || 'image2' === $product_images[0] || 'image3' === $product_images[0] || 'image4' === $product_images[0] || 'image5' === $product_images[0] ) {
			$ec_ac_img_key = $product_images[0];
			$ec_ac_img_val = isset( $tempcart_row->{$ec_ac_img_key} ) ? (string) $tempcart_row->{$ec_ac_img_key} : '';
			if ( 'http://' === substr( $ec_ac_img_val, 0, 7 ) || 'https://' === substr( $ec_ac_img_val, 0, 8 ) ) {
				$image1 = $ec_ac_img_val;
			} else {
				$image1 = plugins_url( '/wp-easycart-data/products/pics' . substr( $ec_ac_img_key, 5 ) . '/' . $ec_ac_img_val, EC_PLUGIN_DATA_DIRECTORY );
			}
		} elseif ( 'image:' === substr( $product_images[0], 0, 6 ) ) {
			$image1 = substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 );
		} elseif ( 'video:' === substr( $product_images[0], 0, 6 ) ) {
			$video_str = substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 );
			$video_arr = explode( ':::', $video_str );
			if ( count( $video_arr ) >= 2 ) {
				$image1 = $video_arr[1];
			}
		} elseif ( 'youtube:' === substr( $product_images[0], 0, 8 ) ) {
			$youtube_video_str = substr( $product_images[0], 8, strlen( $product_images[0] ) - 8 );
			$youtube_video_arr = explode( ':::', $youtube_video_str );
			if ( count( $youtube_video_arr ) >= 2 ) {
				$image1 = $youtube_video_arr[1];
			}
		} elseif ( 'vimeo:' === substr( $product_images[0], 0, 6 ) ) {
			$vimeo_video_str = substr( $product_images[0], 6, strlen( $product_images[0] ) - 6 );
			$vimeo_video_arr = explode( ':::', $vimeo_video_str );
			if ( count( $vimeo_video_arr ) >= 2 ) {
				$image1 = $vimeo_video_arr[1];
			}
		} else {
			$product_image_media = wp_get_attachment_image_src( $product_images[0], 'large' );
			if ( $product_image_media && isset( $product_image_media[0] ) ) {
				$image1 = $product_image_media[0];
			}
		}
	}
	$image1               = apply_filters( 'wpeasycart_cartitem_image1', $image1, $tempcart_row->product_id, isset( $tempcart_row->cartitem_id ) ? $tempcart_row->cartitem_id : 0 );
	$tempcart_row->image1 = $image1;

	/* Option item image ( basic option 1, else the first image-capable advanced option ). */
	$tempcart_row->image1_optionitem = ( isset( $GLOBALS['ec_options'] ) && is_object( $GLOBALS['ec_options'] ) ) ? $GLOBALS['ec_options']->get_optionitem_image1( $tempcart_row->product_id, $tempcart_row->optionitem_id_1 ) : '';
	if ( ( ! empty( $tempcart_row->use_advanced_optionset ) || ! empty( $tempcart_row->use_both_option_types ) ) && isset( $GLOBALS['ec_options'], $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_options'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $tempcart_row->cartitem_id ) ) {
		$advanced_found   = false;
		$advanced_options = $GLOBALS['ec_cart_data']->get_advanced_cart_options( $tempcart_row->cartitem_id );
		foreach ( (array) $advanced_options as $advanced_optionset ) {
			$advanced_option_data = $GLOBALS['ec_options']->get_option( $advanced_optionset->option_id );
			if ( ! $advanced_found && $advanced_option_data && in_array( $advanced_option_data->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
				$tempcart_row->image1_optionitem = $GLOBALS['ec_options']->get_optionitem_image1( $tempcart_row->product_id, $advanced_optionset->optionitem_id );
				$advanced_found                  = true;
			}
		}
	}
	$tempcart_row->image1_optionitem = (string) apply_filters( 'wpeasycart_cartitem_image1_optionitem', $tempcart_row->image1_optionitem, $tempcart_row->product_id, isset( $tempcart_row->cartitem_id ) ? $tempcart_row->cartitem_id : 0 );

	if ( ! empty( $tempcart_row->is_deconetwork ) ) {
		$img_url = 'https://' . get_option( 'ec_option_deconetwork_url' ) . $tempcart_row->deconetwork_image_link;
	} elseif ( 'http://' === substr( $tempcart_row->image1_optionitem, 0, 7 ) || 'https://' === substr( $tempcart_row->image1_optionitem, 0, 8 ) ) {
		$img_url = $tempcart_row->image1_optionitem;
	} elseif ( '' !== $tempcart_row->image1_optionitem && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $tempcart_row->image1_optionitem ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $tempcart_row->image1_optionitem ) ) {
		$img_url = plugins_url( 'wp-easycart-data/products/pics1/' . $tempcart_row->image1_optionitem, EC_PLUGIN_DATA_DIRECTORY );
	} else {
		$img_url = $ed::product_image_url( $tempcart_row->image1 );
	}

	$ed::item_start(
		array(
			'image_url'   => $img_url,
			'image_alt'   => $ec_ac_lang->convert_text( $tempcart_row->title ),
			'image_width' => $ec_ac_img_w,
			'title_html'  => wp_kses_post( $ec_ac_lang->convert_text( $tempcart_row->title ) ),
		)
	);
	for ( $ec_ac_n = 1; $ec_ac_n <= 5; $ec_ac_n++ ) {
		$ec_ac_optionitem_id = isset( $tempcart_row->{'optionitem_id_' . $ec_ac_n} ) ? (int) $tempcart_row->{'optionitem_id_' . $ec_ac_n} : 0;
		if ( ! $ec_ac_optionitem_id ) {
			continue;
		}
		$optionitem = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_optionitem.optionitem_name, ec_option.option_label FROM ec_optionitem, ec_option WHERE ec_optionitem.option_id = ec_option.option_id AND ec_optionitem.optionitem_id = %d', $ec_ac_optionitem_id ) );
		if ( $optionitem ) {
			$ed::detail( wp_kses_post( $ec_ac_lang->convert_text( $optionitem->optionitem_name ) ) );
		}
	}
	$ed::item_end( array( 'qty' => $tempcart_row->tempcart_quantity ) );
}
if ( $ec_ac_has_items ) {
	$ed::items_end();
}

$ed::button_row(
	$ec_load_url,
	wp_strip_all_tags( $ec_ac_lang->get_text( 'ec_abandoned_cart_email', 'complete_checkout' ) ),
	array(
		'top'    => 24,
		'bottom' => 8,
		'arrow'  => true,
	)
);
$ed::close();
