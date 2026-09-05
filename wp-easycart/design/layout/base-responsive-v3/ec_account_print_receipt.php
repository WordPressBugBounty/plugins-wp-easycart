<?php
	$wpec_offer_line_flags = array();
	$wpec_offer_order_summary = array();
	if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
		global $wpdb;
		$wpec_offer_flag_order_id = ( isset( $order_id ) ) ? (int) $order_id : 0;
		if ( $wpec_offer_flag_order_id > 0 ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, product_id, is_free_gift, bundle_group_key, bundle_product_id, applied_offers FROM ec_orderdetail WHERE order_id = %d', $wpec_offer_flag_order_id ) ) as $wpec_offer_flag_row ) {
				$wpec_offer_line_flags[ (int) $wpec_offer_flag_row->orderdetail_id ] = $wpec_offer_flag_row;
			}
			// Order-level applied-offers snapshot (ec_order.applied_offers).
			$wpec_offer_order_json = $wpdb->get_var( $wpdb->prepare( 'SELECT applied_offers FROM ec_order WHERE order_id = %d', $wpec_offer_flag_order_id ) );
			$wpec_offer_order_decoded = ( $wpec_offer_order_json ) ? json_decode( (string) $wpec_offer_order_json, true ) : false;
			if ( is_array( $wpec_offer_order_decoded ) && isset( $wpec_offer_order_decoded['applied_offers'] ) && is_array( $wpec_offer_order_decoded['applied_offers'] ) ) {
				$wpec_offer_order_summary = $wpec_offer_order_decoded['applied_offers'];
			}
		}
	}
	$has_shipping = false;
	$has_billing = false;
	if ( get_option( 'ec_option_use_shipping' ) ) {
		foreach ( $order_details as $cart_item ) {
			if ( $cart_item->is_shippable ) {
				$has_shipping = true;
			}
		}
	}
	if ( isset( $order->billing_address_line_1 ) && '' != $order->billing_address_line_1 ) {
		$has_billing = true;
	}
	if ( isset( $order->billing_address_city ) && '' != $order->billing_address_city ) {
		$has_billing = true;
	}
	if ( isset( $order->billing_address_state ) && '' != $order->billing_address_state ) {
		$has_billing = true;
	}
	if ( isset( $order->billing_address_zip ) && '' != $order->billing_address_zip ) {
		$has_billing = true;
	}
	if ( isset( $order->billing_address_country ) && '' != $order->billing_address_country ) {
		$has_billing = true;
	}
?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_receipt_title" ) . " " . esc_attr( $order_id ); ?></title>
		<style type='text/css'>
		<!--
			.style20 {font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; }
			.style22 {font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
			.ec_option_label{font-family: Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; }
			.ec_option_name{font-family: Arial, Helvetica, sans-serif; font-size:11px; }
			.ec_admin_page_break{ page-break-before:always; }
			.stylefailed{font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; line-height:1.5em; text-align:center; background:#df371c; color:#FFF; padding:15px; }
			.stylesuccess{font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; line-height:1.5em; text-align:center; background:#5dbf20; color:#FFF; padding:15px; }
		-->
		</style>
	</head>
	<body>
		<table width='539' border='0' align='center'>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<a href="<?php echo esc_url_raw( $store_page ); ?>" target="_blank"><img src="<?php echo esc_attr( $email_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( "name" ) ); ?>" style="max-height:250px; max-width:100%; height:auto;"></a>
				</td>
			</tr>
			<?php if ( $order->includes_preorder_items ) { ?>
			<tr>
				<td colspan='4' align="center" class="stylesuccess">
					<?php
					$ec_pickup_open  = date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $order->pickup_date ) );
					$ec_pickup_close = date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $order->pickup_date . ' +1 hour' ) );
					echo esc_html(
						str_replace(
							'[pickup_date]',
							$ec_pickup_open . ' - ' . $ec_pickup_close,
							wp_easycart_language()->get_text( 'ec_errors', 'preorder_message' )
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<td align="center" class="style22">&nbsp;&nbsp;&nbsp;</td>
			</td>
			<?php } ?>
			<?php if ( $order->includes_restaurant_type ) { ?>
			<tr>
				<td colspan='4' align="center" class="stylesuccess">
					<?php
					$ec_pickup_time = date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $order->pickup_time ) );
					echo esc_html(
						str_replace(
							'[pickup_time]',
							$ec_pickup_time,
							wp_easycart_language()->get_text( 'ec_errors', 'restaurant_message' )
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<td align="center" class="style22">&nbsp;&nbsp;&nbsp;</td>
			</td>
			<?php } ?>
			<tr>
				<td colspan='4' align='left' class='style22'>  
					<p><br><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_1" ) . " " . esc_attr( htmlspecialchars( $order->billing_first_name, ENT_QUOTES ) . " " . htmlspecialchars( $order->billing_last_name, ENT_QUOTES ) ); ?>:</p>
					<p><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_2" ); ?> <strong><?php echo esc_attr( $order_id ); ?> ― <?php if ( isset( $order_timestamp ) ) { echo esc_attr( date_i18n( get_option( 'date_format' ), $order_timestamp ) ); } else { echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $order->order_date ) ) ); } ?></strong></p>
					<p><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_3" ); ?></p>
					<p><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_4" ); ?></p><br />
				</td>
			</tr>
			<?php if( $has_billing ) { ?>
			<tr>
				<td colspan='4' align='left' class='style20'>
					<table width='100%' border='0' align='center' cellpadding='0' cellspacing='0'>
						<tr>
							<td width='47%' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_billing_label" ); ?></td>
							<td width='3%'>&nbsp;</td>
							<td width='50%' bgcolor='#F3F1ED' class='style20'><?php if( $has_shipping ){?><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_shipping_label" ); ?><?php }?></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_first_name, ENT_QUOTES ) . ' ' . htmlspecialchars( $order->billing_last_name, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_first_name, ENT_QUOTES ) . ' ' . htmlspecialchars( $order->shipping_last_name, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<?php if( $order->billing_company_name != "" || ( $has_shipping && $order->shipping_company_name != "" ) ){ ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_company_name, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_company_name, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<?php }?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_address_line_1, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_address_line_1, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<?php if( $order->billing_address_line_2 != "" || $order->shipping_address_line_2 != "" ){ ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_address_line_2, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_address_line_2, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<?php } ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_city, ENT_QUOTES ) ); ?>, <?php echo esc_attr( htmlspecialchars( $order->billing_state, ENT_QUOTES ) ); ?> <?php echo esc_attr( htmlspecialchars( $order->billing_zip, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_city, ENT_QUOTES ) ); ?>, <?php echo esc_attr( htmlspecialchars( $order->shipping_state, ENT_QUOTES ) ); ?> <?php echo esc_attr( htmlspecialchars( $order->shipping_zip, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_country, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_country, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->billing_phone, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'><?php if( $has_shipping ){?><?php echo esc_attr( htmlspecialchars( $order->shipping_phone, ENT_QUOTES ) ); ?><?php }?></span></td>
						</tr>
						<?php if( $order->vat_registration_number != "" ){ ?>
						<tr>
							<td colspan="3"><span class='style22'><strong><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ); ?>:</strong> <?php echo esc_attr( htmlspecialchars( $order->vat_registration_number, ENT_QUOTES ) ); ?></span></td>
						</tr>
						<?php }?>
					</table>
				</td>
			</tr>
			<?php } else if ( $has_shipping ) { ?>
			<tr>
				<td colspan='4' align='left' class='style20'>
					<table width='100%' border='0' align='center' cellpadding='0' cellspacing='0'>
						<tr>
							<td width='47%' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_shipping_label" ); ?></td>
							<td width='3%'>&nbsp;</td>
							<td width='50%' bgcolor='#F3F1ED' class='style20'></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_first_name, ENT_QUOTES ) . ' ' . htmlspecialchars( $order->shipping_last_name, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<?php if( $order->billing_company_name != "" || ( $has_shipping && $order->shipping_company_name != "" ) ){ ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_company_name, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<?php }?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_address_line_1, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<?php if( $order->billing_address_line_2 != "" || $order->shipping_address_line_2 != "" ){ ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_address_line_2, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<?php } ?>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_city, ENT_QUOTES ) ); ?>, <?php echo esc_attr( htmlspecialchars( $order->shipping_state, ENT_QUOTES ) ); ?> <?php echo esc_attr( htmlspecialchars( $order->shipping_zip, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_country, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
						<tr>
							<td><span class='style22'><?php echo esc_attr( htmlspecialchars( $order->shipping_phone, ENT_QUOTES ) ); ?></span></td>
							<td>&nbsp;</td>
							<td><span class='style22'></span></td>
						</tr>
					</table>
				</td>
			</tr>
			<?php } ?>
			<tr>
				<td width='269' align='left'>&nbsp;</td>
				<td width='80' align='center'>&nbsp;</td>
				<td width='91' align='center'>&nbsp;</td>
				<td align='center'>&nbsp;</td>
			</tr>
			<tr>
				<td width='269' align='left' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_details_header_1" ); ?></td>
				<td width='80' align='center' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_details_header_2" ); ?></td>
				<td width='91' align='center' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_details_header_3" ); ?></td>
				<td align='center' bgcolor='#F3F1ED' class='style20'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_details_header_4" ); ?></td>
			</tr>
			<?php for ( $i = 0; $i < count( $order_details ); $i++ ) {
				$unit_discount_promotion = $order_details[$i]->unit_discount_promotion;
				$unit_discount_coupon = $order_details[$i]->unit_discount_coupon;
				$unit_price_base = $order_details[$i]->unit_price;
				if ( get_option( 'ec_option_show_promotion_discount_total' ) && $unit_discount_promotion > 0 ) {
					$unit_price_base += $unit_discount_promotion;
				}
				$unit_price = $GLOBALS['currency']->get_currency_display( $unit_price_base );
				$has_unit_discount = ( apply_filters( 'wp_easycart_order_details_show_discount_unit', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $unit_discount_promotion > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $unit_discount_coupon > 0 ) ) );

				$total_discount_promotion = $order_details[$i]->total_discount_promotion;
				$total_discount_coupon = $order_details[$i]->total_discount_coupon;
				$total_price_base = $order_details[$i]->total_price;
				if ( get_option( 'ec_option_show_promotion_discount_total' ) && $total_discount_promotion > 0 ) {
					$total_price_base += $total_discount_promotion;
				}
				$total_price = $GLOBALS['currency']->get_currency_display( $total_price_base );
				$has_total_discount = ( apply_filters( 'wp_easycart_order_details_show_discount_total', true ) && ( ( get_option( 'ec_option_show_promotion_discount_total' ) && $total_discount_promotion > 0 ) || ( get_option( 'ec_option_show_coupon_discount_total' ) && $total_discount_coupon > 0 ) ) );
			?>
			<tr>
				<td width='269' class='style22'>
					<?php if( get_option( 'ec_option_show_image_on_receipt' ) ){ ?>
					<?php
					if ( $order_details[$i]->is_deconetwork ) {
						$img_url = "https://" . get_option( 'ec_option_deconetwork_url' ) . $order_details[$i]->deconetwork_image_link;

					} else if ( substr( $order_details[$i]->image1, 0, 7 ) == 'http://' || substr( $order_details[$i]->image1, 0, 8 ) == 'https://' ) {
						$img_url = $order_details[$i]->image1;

					} else if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/" . $order_details[$i]->image1 ) && !is_dir( EC_PLUGIN_DATA_DIRECTORY . "/products/pics1/" . $order_details[$i]->image1 ) ) {
						$img_url = plugins_url( "wp-easycart-data/products/pics1/" . $order_details[$i]->image1, EC_PLUGIN_DATA_DIRECTORY );

					} else if ( get_option( 'ec_option_product_image_default' ) && '' != get_option( 'ec_option_product_image_default' ) ) {
						$img_url = get_option( 'ec_option_product_image_default' );

					} else if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . "/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/ec_image_not_found.jpg" ) ) {
						$img_url = plugins_url( "wp-easycart-data/design/theme/" . get_option( 'ec_option_base_theme' ) . "/images/ec_image_not_found.jpg", EC_PLUGIN_DATA_DIRECTORY );

					} else {
						$img_url = plugins_url( "wp-easycart/design/theme/" . get_option( 'ec_option_latest_theme' ) . "/images/ec_image_not_found.jpg", EC_PLUGIN_DIRECTORY );
					}
					?>
					<div style="float:left; width:70px; margin-right:5px;"><img src="<?php echo esc_attr( $img_url ); ?>" style="width:70px; height:auto;" alt="<?php echo esc_attr( $order_details[$i]->title ); ?>" /></div>
					<?php }?>
					<table>
						<tr><td>
							<?php echo wp_easycart_escape_html( $order_details[$i]->title ); ?>
							<?php // Offers v2 fulfillment markers — flags come from the order-wide map
							// built at the top of this template, not from the line row itself.
							$wpec_line_flags = ( isset( $order_details[$i]->orderdetail_id ) && isset( $wpec_offer_line_flags[ (int) $order_details[$i]->orderdetail_id ] ) ) ? $wpec_offer_line_flags[ (int) $order_details[$i]->orderdetail_id ] : false;
							if ( $wpec_line_flags && $wpec_line_flags->is_free_gift ) { ?>
							<span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#fce7f0; color:#c2185b; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase; vertical-align:middle;"><?php echo wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ); ?></span>
							<?php }
							if ( $wpec_line_flags && '' != $wpec_line_flags->bundle_group_key && $wpec_line_flags->bundle_product_id != $wpec_line_flags->product_id ) { ?>
							<span style="display:inline-block; margin-left:6px; padding:1px 8px; background:#eef1f4; color:#4a5560; font-size:11px; font-weight:bold; border-radius:3px; text-transform:uppercase; vertical-align:middle;"><?php echo wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ); ?></span>
							<?php } ?>
							<?php if ( $wpec_line_flags && isset( $wpec_line_flags->applied_offers ) && '' != $wpec_line_flags->applied_offers ) {
								$wpec_line_offer_rows = json_decode( (string) $wpec_line_flags->applied_offers, true );
								if ( is_array( $wpec_line_offer_rows ) && count( $wpec_line_offer_rows ) > 0 ) { ?>
							<div style="margin-top:4px;">
								<?php foreach ( $wpec_line_offer_rows as $wpec_line_offer_row ) { if ( ! isset( $wpec_line_offer_row['label'] ) || ! isset( $wpec_line_offer_row['amount'] ) || (float) $wpec_line_offer_row['amount'] <= 0 ) { continue; } ?>
								<span style="display:inline-block; margin:2px 4px 0 0; padding:1px 8px; background:#e7f5ec; color:#1f7a3d; font-size:11px; border-radius:3px;"><?php echo esc_attr( $wpec_line_offer_row['label'] ); ?> &minus;<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( (float) $wpec_line_offer_row['amount'] ) ); ?></span>
								<?php } ?>
							</div>
							<?php } } ?>
						</td></tr>
						<tr><td class="ec_option_name">
						<?php echo esc_attr( $order_details[$i]->model_number ); ?>
						</td></tr>
						<?php
						if( ! $order_details[$i]->use_advanced_optionset || $order_details[$i]->use_both_option_types ) {
							if( $order_details[$i]->optionitem_name_1 ){
								echo "<tr><td><span class=\"ec_option_name\">" . wp_easycart_escape_html( $order_details[$i]->optionitem_name_1 );
								if( $order_details[$i]->optionitem_price_1 < 0 ){
									echo " (" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_1 ) ) . ")";
								}else if( $order_details[$i]->optionitem_price_1 > 0 ){
									echo " (+" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_1 ) ) . ")";
								}
								echo "</span></td></tr>";
							}
							if( $order_details[$i]->optionitem_name_2 ){
								echo "<tr><td><span class=\"ec_option_name\">" . wp_easycart_escape_html( $order_details[$i]->optionitem_name_2 );
								if( $order_details[$i]->optionitem_price_2 < 0 ){
									echo " (" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_2 ) ) . ")";
								}else if( $order_details[$i]->optionitem_price_2 > 0 ){
									echo " (+" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_2 ) ) . ")";
								}
								echo "</span></td></tr>";
							}
							if( $order_details[$i]->optionitem_name_3 ){
								echo "<tr><td><span class=\"ec_option_name\">" . wp_easycart_escape_html( $order_details[$i]->optionitem_name_3 );
								if( $order_details[$i]->optionitem_price_3 < 0 ){
									echo " (" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_3 ) ) . ")";
								}else if( $order_details[$i]->optionitem_price_3 > 0 ){
									echo " (+" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_3 ) ) . ")";
								}
								echo "</span></td></tr>";
							}
							if( $order_details[$i]->optionitem_name_4 ){
								echo "<tr><td><span class=\"ec_option_name\">" . wp_easycart_escape_html( $order_details[$i]->optionitem_name_4 );
								if( $order_details[$i]->optionitem_price_4 < 0 ){
									echo " (" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_4 ) ) . ")";
								}else if( $order_details[$i]->optionitem_price_4 > 0 ){
									echo " (+" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_4 ) ) . ")";
								}
								echo "</span></td></tr>";
							}
							if( $order_details[$i]->optionitem_name_5 ){
								echo "<tr><td><span class=\"ec_option_name\">" . wp_easycart_escape_html( $order_details[$i]->optionitem_name_5 );
								if( $order_details[$i]->optionitem_price_5 < 0 ){
									echo " (" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_5 ) ) . ")";
								}else if( $order_details[$i]->optionitem_price_5 > 0 ){
									echo " (+" . esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->optionitem_price_5 ) ) . ")";
								}
								echo "</span></td></tr>";
							}
						}//close basic options

						if ( $order_details[$i]->use_advanced_optionset || $order_details[$i]->use_both_option_types ) {
							$advanced_options = $mysqli->get_order_options( $order_details[$i]->orderdetail_id );
							foreach ( $advanced_options as $advanced_option ) {
								if ( $advanced_option->option_type == "file" ) {
									$file_split = explode( "/", $advanced_option->option_value );
									echo "<tr><td><span class=\"ec_option_label\">" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $file_split[1] ) . "";
								} else if ( $advanced_option->option_type == "grid" ) {
									echo "<tr><td><span class=\"ec_option_label\">" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $advanced_option->optionitem_name . " (" . $advanced_option->option_value . ")" ) . "";
								} else {
									echo "<tr><td><span class=\"ec_option_label\">" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $advanced_option->option_value ) . "";
								}

								if ( $advanced_option->optionitem_enable_custom_price_label && ( $advanced_option->optionitem_price != 0 || ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price != 0 ) || ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime != 0 ) ) ) {
									echo esc_attr( $advanced_option->optionitem_custom_price_label );
								} else if ( $advanced_option->optionitem_price > 0 ) {
									echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')';
								} else if ( $advanced_option->optionitem_price < 0 ) {
									echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')';
								} else if ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
									echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')';
								} else if ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
									echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')';
								} else if ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
									echo ' (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_override ) ) . ')';
								}

								echo '</span></td></tr>';
							}
						}

						if( $order_details[$i]->subscription_signup_fee > 0 ){
							echo "<tr><td><span class=\"ec_option_label\">" . wp_easycart_language( )->get_text( 'product_details', 'product_details_signup_fee_notice1' ); ?> <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->subscription_signup_fee ) ) . "</span></td></tr>";
						}
						?>
						<?php do_action( 'wp_easycart_print_receipt_optionitems', $order_details[$i] ); ?>
					</table>
				</td>
				<td width='80' align='center' class='style22'><?php echo esc_attr( $order_details[$i]->quantity ); ?></td>
				<td width='91' align='center' class='style22'><?php
				if ( $has_unit_discount ) {?>
					<table>
						<tr>
							<td class='style22' style="color:red; text-decoration:line-through;"><?php echo esc_attr( apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $order_details[$i]->product_id ) ); ?></td>
						</tr>
						<tr>
							<td class='style22' style="font-weight:bold;"><?php if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
								echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->unit_price - round( $unit_discount_coupon, 2 ) ) );
							} else { 
								echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->unit_price ) );
							} ?></td>
						</tr>
					</table>
				<?php } else {
					echo esc_attr( apply_filters( 'wp_easycart_cart_item_unit_price_display', $unit_price, $order_details[$i]->product_id ) );
				}?></td>
				<td align='center' class='style22'><?php
				if ( $has_total_discount ) {?>
					<table>
						<tr>
							<td class='style22' style="color:red; text-decoration:line-through;"><?php echo esc_attr( $total_price ); ?></td>
						</tr>
						<tr>
							<td class='style22' style="font-weight:bold;"><?php if ( get_option( 'ec_option_show_coupon_discount_total' ) ) {
								echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->total_price - round( $total_discount_coupon, 2 ) ) );
							} else {
								echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_details[$i]->total_price ) );
							} ?></td>
						</tr>
					</table>
				<?php } else {
					echo esc_attr( $total_price );
				} ?></td>
			</tr>
			<?php }//end for loop ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center'>&nbsp;</td>
				<td width='91' align='center'>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_subtotal" ); ?></td>
				<td align='center'  class='style22'><?php echo esc_attr( $subtotal ); ?></td>
			</tr>
			<?php if( $order->tip_total > 0 ){?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( 'cart_totals', 'cart_totals_tip' ); ?></td>
				<td align='center'  class='style22'><?php echo esc_attr( $tip ); ?></td>
			</tr>
			<?php }?>
			<?php if( $order->tax_total > 0 ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_tax" ); ?></td>
				<td align='center' class='style22'><?php echo esc_attr( $tax ); ?></td>
			</tr>
			<?php } ?>
			<?php if( get_option( 'ec_option_use_shipping' ) ){?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_shipping" ); ?></td>
				<td align='center'  class='style22'><?php echo esc_attr( $shipping ); ?></td>
			</tr>
			<?php } ?>
			<?php
			$ec_receipt_offers = ( isset( $wpec_offer_order_summary ) && count( $wpec_offer_order_summary ) > 0 ) ? array( 'applied_offers' => $wpec_offer_order_summary ) : ( ( isset( $order->applied_offers ) && '' != $order->applied_offers ) ? json_decode( (string) $order->applied_offers, true ) : false );
			if ( is_array( $ec_receipt_offers ) && isset( $ec_receipt_offers['applied_offers'] ) ) {
				foreach ( $ec_receipt_offers['applied_offers'] as $ec_receipt_offer ) {
					if ( $ec_receipt_offer['amount'] <= 0 ) { continue; }
			?>
			<tr>
				<td>&nbsp;</td>
				<td align='center' class='style22'>&nbsp;</td>
				<td align='center' class='style22'><?php echo esc_attr( $ec_receipt_offer['label'] ); ?><?php if ( '' != $ec_receipt_offer['code'] ) { echo ' (' . esc_attr( $ec_receipt_offer['code'] ) . ')'; } ?></td>
				<td align='center' class='style22'>-<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $ec_receipt_offer['amount'] ) ); ?></td>
			</tr>
			<?php } } ?>
			<?php if( $order->discount_total > 0 && apply_filters( 'wp_easycart_order_details_discount_display', true, $order ) ){ ?>
			<tr>
				<td>&nbsp;</td>
				<td align='center' class='style22'>&nbsp;</td>
				<td align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_discount" ); ?></td>
				<td align='center'  class='style22'>-<?php echo esc_attr( $discount ); ?></td>
			</tr>
			<?php } ?>
			<?php if( $has_duty ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_duty" ); ?></td>
				<td align='center' class='style22'><?php echo esc_attr( $duty ); ?></td>
			</tr>
			<?php } ?>
			<?php if( $tax_struct->is_vat_enabled( ) ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_vat" ); ?><?php echo esc_attr( $vat_rate ); ?>%</td>
				<td align='center' class='style22'><?php echo esc_attr( $vat ); ?></td>
			</tr>
			<?php }?>
			<?php if( $gst > 0 ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'>GST (<?php echo esc_attr( $gst_rate ); ?>%)</td>
				<td align='center' class='style22'><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $gst ) ); ?></td>
			</tr>
			<?php }?>
			<?php if( $pst > 0 ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'>PST (<?php echo esc_attr( $pst_rate ); ?>%)</td>
				<td align='center' class='style22'><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $pst ) ); ?></td>
			</tr>
			<?php }?>
			<?php if( $hst > 0 ){ ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'>HST (<?php echo esc_attr( $hst_rate ); ?>%)</td>
				<td align='center' class='style22'><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $hst ) ); ?></td>
			</tr>
			<?php }?>
			<?php if ( count( $order_fees ) > 0 ) {
				foreach ( $order_fees as $order_fee ) { ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><?php echo esc_attr( $order_fee->fee_label ); ?></td>
				<td align='center' class='style22'><?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $order_fee->fee_total ) ); ?></td>
			</tr>
				<?php }
			} ?>
			<tr>
				<td width='269'>&nbsp;</td>
				<td width='80' align='center' class='style22'>&nbsp;</td>
				<td width='91' align='center' class='style22'><strong><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_order_totals_grand_total" ); ?></strong></td>
				<td align='center' class='style22'><strong><?php echo esc_attr( $total ); ?></strong></td>
			</tr>
			<?php if( $order->refund_total > 0 ){ ?>
			<tr>
				<td>&nbsp;</td>
				<td align='center' class='style22'>&nbsp;</td>
				<td align='center' class='style22' style="color:red; font-weight:bold;"><?php echo wp_easycart_language( )->get_text( "account_order_details", "account_orders_details_refund_total_short" ); ?></td>
				<td  align='center'  class='style22' style="color:red; font-weight:bold;">-<?php echo esc_attr( $refund ); ?></td>
			</tr>
			<?php }?>
			<tr>
				<td colspan='4' class='style22'>
					<p><br>
					<?php if( get_option( 'ec_option_user_order_notes' ) ){ ?>
						<hr />
						<h4><?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'cart_payment_information_order_notes_title' ); ?></h4>
						<p><?php echo nl2br( esc_attr( wp_unslash( $order->order_customer_notes ) ) ); ?></p>
						<br>
						<hr />
					<?php } ?>
					<?php do_action( 'wpeasycart_print_receipt_order_notes_after', $order_id ); ?>
					<?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_bottom_line_1" ); ?><br>
					<br>
					<?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_bottom_line_2" ); ?></p>
					<p>&nbsp;</p>
				</td>
			</tr>
		</table>
	</body>
</html>