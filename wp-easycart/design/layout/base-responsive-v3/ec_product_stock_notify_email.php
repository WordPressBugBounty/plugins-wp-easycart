<?php
/**
 * Back in stock email ( customer subscribed to a product's stock notifications ).
 *
 * Included by wp_easycart_admin_products_pro::send_notification() ( PRO admin/inc/wp_easycart_admin_products_pro.php ),
 * once per subscriber, with: $product ( ec_product ), $subscriber ( ec_product_subscriber row: email, product_subscriber_id ),
 * $email_logo_url, $subscribers, $product_id, $db, $result, $headers.
 *
 * 6.0.0: on the shared email design ( wp_easycart_email_design, inc/classes/core/class-wp-easycart-email-design.php ).
 * Product image, title, price and a short description, a button to the product and the unsubscribe link in the footer.
 *
 * Hooks kept: wp_easycart_product_details_image_url_type, wp_easycart_product_details_full_size.
 * Language strings kept: ec_stock_notify_email → email_title, view_now, unsubscribe.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'wp_easycart_email_design' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
}

$ed               = 'wp_easycart_email_design';
$ec_notify_lang   = wp_easycart_language();
$ec_notify_images = isset( $product->images ) ? $product->images : null;

/**
 * First image URL for a product_images entry ( image1..image5, image:URL, video / youtube / vimeo thumbnail, attachment id ).
 * Returns '' when the entry gives no image.
 */
$ec_notify_image_for = function ( $entry ) use ( $product ) {
	$entry = (string) $entry;
	if ( 'video:' === substr( $entry, 0, 6 ) || 'vimeo:' === substr( $entry, 0, 6 ) || 'youtube:' === substr( $entry, 0, 8 ) ) {
		$parts = explode( ':::', substr( $entry, ( 'youtube:' === substr( $entry, 0, 8 ) ) ? 8 : 6 ) );
		return ( count( $parts ) >= 2 ) ? (string) $parts[1] : '';
	}
	if ( 'image1' === $entry ) {
		return $product->get_first_image_url();
	} elseif ( 'image2' === $entry ) {
		return $product->get_second_image_url();
	} elseif ( 'image3' === $entry ) {
		return $product->get_third_image_url();
	} elseif ( 'image4' === $entry ) {
		return $product->get_fourth_image_url();
	} elseif ( 'image5' === $entry ) {
		return $product->get_fifth_image_url();
	} elseif ( 'image:' === substr( $entry, 0, 6 ) ) {
		return (string) apply_filters( 'wp_easycart_product_details_image_url_type', substr( $entry, 6 ) );
	}
	$media = wp_get_attachment_image_src( $entry, apply_filters( 'wp_easycart_product_details_full_size', 'medium_large' ) );
	return ( $media && isset( $media[0] ) ) ? (string) $media[0] : '';
};

/* Product image: the first option item's gallery when option item images are on, else the product gallery. */
$ec_notify_image = '';
if ( ! empty( $product->use_optionitem_images ) ) {
	$first_optionitem_id = false;
	if ( ! empty( $product->use_advanced_optionset ) ) {
		$valid_optionset = false;
		foreach ( (array) $product->advanced_optionsets as $adv_optionset ) {
			if ( ! $valid_optionset && in_array( $adv_optionset->option_type, array( 'combo', 'swatch', 'radio' ), true ) ) {
				$valid_optionset = $adv_optionset;
			}
		}
		if ( $valid_optionset ) {
			$optionitems = $product->get_advanced_optionitems( $valid_optionset->option_id );
			if ( count( $optionitems ) > 0 ) {
				$first_optionitem_id = $optionitems[0]->optionitem_id;
			}
		}
	} elseif ( isset( $product->options->optionset1->optionset ) && count( $product->options->optionset1->optionset ) > 0 ) {
		$first_optionitem_id = $product->options->optionset1->optionset[0]->optionitem_id;
	}
	if ( $first_optionitem_id && $ec_notify_images && isset( $ec_notify_images->imageset ) ) {
		foreach ( (array) $ec_notify_images->imageset as $ec_notify_imageset ) {
			if ( '' === $ec_notify_image && (int) $ec_notify_imageset->optionitem_id === (int) $first_optionitem_id ) {
				$ec_notify_image = ( count( $ec_notify_imageset->product_images ) > 0 ) ? $ec_notify_image_for( $ec_notify_imageset->product_images[0] ) : $product->get_first_image_url();
			}
		}
	}
} elseif ( $ec_notify_images && isset( $ec_notify_images->product_images ) && count( $ec_notify_images->product_images ) > 0 ) {
	$ec_notify_image = $ec_notify_image_for( $ec_notify_images->product_images[0] );
}
if ( '' === $ec_notify_image ) {
	$ec_notify_image = $product->get_first_image_url();
}

/* Price ( hidden for catalog / inquiry / login-for-pricing products; custom label when the product replaces it ). */
$ec_notify_price_html = '';
if ( ! empty( $product->replace_price_label ) && '' !== (string) $product->custom_price_label ) {
	$ec_notify_price_html = wp_kses_post( $product->custom_price_label );
} elseif ( empty( $product->is_catalog_mode ) && empty( $product->is_inquiry_mode ) && empty( $product->login_for_pricing ) && empty( $product->is_donation ) ) {
	if ( ! empty( $product->show_custom_price_range ) && isset( $product->price_range_low ) ) {
		$ec_notify_price_html = $ed::money( $product->price_range_low ) . ( ( isset( $product->price_range_high ) && $product->price_range_high > 0 ) ? ' &ndash; ' . $ed::money( $product->price_range_high ) : '' );
	} else {
		$ec_notify_price_html = $ed::money( $product->price );
		if ( isset( $product->list_price ) && (float) $product->list_price > (float) $product->price ) {
			$ec_notify_price_html .= ' <span style="color:#6b7280;font-weight:400;text-decoration:line-through;">' . $ed::money( $product->list_price ) . '</span>';
		}
	}
}

/* Short plain-text description. */
ob_start();
$product->display_product_description();
$ec_notify_description = trim( wp_trim_words( wp_strip_all_tags( (string) ob_get_clean() ), 40 ) );

$ec_notify_link = $product->get_product_link();

$ed::open(
	array(
		'title'     => wp_strip_all_tags( $ec_notify_lang->get_text( 'ec_stock_notify_email', 'email_title' ) ),
		'preheader' => wp_strip_all_tags( $product->title ),
		'logo_url'  => (string) $email_logo_url,
	)
);

$ed::section_start( array( 'top' => 16 ) );
$ed::heading( wp_kses_post( $ec_notify_lang->get_text( 'ec_stock_notify_email', 'email_title' ) ) );
$ed::section_end();

$ed::section_start(
	array(
		'top'    => 8,
		'bottom' => 8,
	)
);
$ed::card_start( array( 'padding' => '16px' ) );
?>
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"><tr>
<?php if ( '' !== $ec_notify_image ) { ?>
	<td class="ec-email-col" valign="top" width="172" style="width:172px;padding-bottom:12px;padding-<?php echo esc_attr( $ed::ctx()['end'] ); ?>:16px;"><a href="<?php echo esc_url( $ec_notify_link ); ?>" target="_blank"><img src="<?php echo esc_url( $ec_notify_image ); ?>" width="156" alt="<?php echo esc_attr( wp_strip_all_tags( $product->title ) ); ?>" style="display:block;width:156px;max-width:100%;height:auto;border-radius:6px;border:1px solid #e5e7eb;background-color:#ffffff;" /></a></td>
<?php } ?>
	<td class="ec-email-col" valign="top" align="<?php echo esc_attr( $ed::ctx()['start'] ); ?>" style="<?php echo esc_attr( $ed::css( 'text' ) ); ?>">
		<p style="margin:0 0 6px 0;<?php echo esc_attr( $ed::css( 'strong' ) ); ?>font-size:17px;line-height:1.35;"><?php echo esc_html( $product->title ); ?></p>
		<?php if ( '' !== $ec_notify_price_html ) { ?>
		<p style="margin:0 0 10px 0;<?php echo esc_attr( $ed::css( 'strong' ) ); ?>font-size:16px;"><?php echo $ec_notify_price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from $ed::money() and wp_kses_post() above. ?></p>
		<?php } ?>
		<?php if ( '' !== $ec_notify_description ) { ?>
		<p style="margin:0 0 4px 0;<?php echo esc_attr( $ed::css( 'muted' ) ); ?>"><?php echo esc_html( $ec_notify_description ); ?></p>
		<?php } ?>
		<?php
		$ed::button(
			$ec_notify_link,
			wp_strip_all_tags( $ec_notify_lang->get_text( 'ec_stock_notify_email', 'view_now' ) ),
			array(
				'margin' => '14px 0 0 0',
				'arrow'  => true,
			)
		);
		?>
	</td>
</tr></table>
<?php
$ed::card_end();
$ed::section_end();

$ed::close(
	array(
		'footer_html' => '<a href="' . esc_url( $product->get_product_unsubscribe_link( $subscriber->email, $subscriber->product_subscriber_id ) ) . '" target="_blank" style="color:#6b7280;text-decoration:underline;">' . wp_kses_post( $ec_notify_lang->get_text( 'ec_stock_notify_email', 'unsubscribe' ) ) . '</a>',
	)
);
