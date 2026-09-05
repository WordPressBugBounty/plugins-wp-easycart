<?php
/**
 * Order line item ( V2.1 — approved redesign ).
 *
 * DOM order changed to details / price / total / actions so the row grid
 * lays out naturally ( actions on the right ). All element IDs, classes,
 * conditionals, and hooks from the previous template are preserved — the
 * PRO line-edit flow updates this markup by ID and is unaffected. Offer
 * chips moved from inline styles to ecodv2 chip classes.
 */

$advanced_options = $wpdb->get_results( $wpdb->prepare( "SELECT ec_order_option.* FROM ec_order_option WHERE ec_order_option.orderdetail_id = %s ORDER BY order_option_id", $line_item->orderdetail_id ));
if( $advanced_options ){
	$order_detail_row = $wpdb->get_row( $wpdb->prepare( "SELECT ec_orderdetail.is_deconetwork, ec_orderdetail.deconetwork_id, ec_orderdetail.deconetwork_name, ec_orderdetail.deconetwork_product_code, ec_orderdetail.deconetwork_options, ec_orderdetail.deconetwork_color_code, ec_orderdetail.product_id, ec_orderdetail.deconetwork_image_link FROM ec_orderdetail WHERE ec_orderdetail.orderdetail_id = %d", $line_item->orderdetail_id ) );
	if( $order_detail_row !== false && $order_detail_row->is_deconetwork ){
		$deconetwork1 = new stdClass( );
		$deconetwork1->orderdetail_id = $advanced_options->orderdetail_id;
		$deconetwork1->option_name = __( 'DecoNetwork ID', 'wp-easycart' ) . ': ';
		$deconetwork1->optionitem_name = "";
		$deconetwork1->option_type = "text";
		$deconetwork1->option_value = $order_detail_row->deconetwork_id;
		$deconetwork1->option_price_change = "";

		$advanced_options[] = $deconetwork1;

		$deconetwork2 = new stdClass( );
		$deconetwork2->option_name = __( 'DecoNetwork Name', 'wp-easycart' ) . ': ';
		$deconetwork2->optionitem_name = "";
		$deconetwork2->option_type = "text";
		$deconetwork2->option_value =  $order_detail_row->deconetwork_name;
		$deconetwork2->option_price_change = "";
		$advanced_options[] = $deconetwork2;

		$deconetwork3 = new stdClass( );
		$deconetwork3->option_name = __( 'DecoNetwork Product Code', 'wp-easycart' ) . ': ';
		$deconetwork3->optionitem_name = "";
		$deconetwork3->option_type = "text";
		$deconetwork3->option_value = $order_detail_row->deconetwork_product_code;
		$deconetwork3->option_price_change = "";
		$advanced_options[] = $deconetwork3;

		$deconetwork4 = new stdClass( );
		$deconetwork4->option_name = __( 'DecoNetwork Options', 'wp-easycart' ) . ': ';
		$deconetwork4->optionitem_name = "";
		$deconetwork4->option_type = "text";
		$deconetwork4->option_value = $order_detail_row->deconetwork_options;
		$deconetwork4->option_price_change = "";
		$advanced_options[] = $deconetwork4;

		$deconetwork5 = new stdClass( );
		$deconetwork5->option_name = __( 'DecoNetwork Color Code', 'wp-easycart' ) . ': ';
		$deconetwork5->optionitem_name = "";
		$deconetwork5->option_type = "text";
		$deconetwork5->option_value = $order_detail_row->deconetwork_color_code;
		$deconetwork5->option_price_change = "";
		$advanced_options[] = $deconetwork5;

		$deconetwork6 = new stdClass( );
		$deconetwork6->option_name = __( 'DecoNetwork Image Link', 'wp-easycart' ) . ': ';
		$deconetwork6->optionitem_name = "";
		$deconetwork6->option_type = "text";
		$deconetwork6->option_value = $order_detail_row->deconetwork_image_link;
		$deconetwork6->option_price_change = "";
		$advanced_options[] = $deconetwork6;
	}
} ?>
<div class="ec_admin_order_details_line_item ecodv2-line" id="ec_admin_order_details_line_item_<?php echo esc_attr( $line_item->orderdetail_id ); ?>">

	<div class="ec_admin_order_details_item_details">
		<span id="ec_admin_order_details_item_title_display_<?php echo esc_attr( $line_item->orderdetail_id ); ?>" class="ecodv2-line-title"><?php echo wp_easycart_escape_html( $line_item->title );?></span>
		<?php
		if ( isset( $line_item->refunded_quantity ) && (int) $line_item->refunded_quantity > 0 ) {
			if ( (int) $line_item->refunded_quantity >= (int) $line_item->quantity ) {
				echo ' <span class="ecodv2-chip ecodv2-chip-refunded">' . esc_attr__( 'Refunded', 'wp-easycart' ) . '</span>';
			} else {
				echo ' <span class="ecodv2-chip ecodv2-chip-refunded">' . sprintf( esc_attr__( '%1$d of %2$d refunded', 'wp-easycart' ), (int) $line_item->refunded_quantity, (int) $line_item->quantity ) . '</span>';
			}
		}
		// Offers v2: fulfillment + applied-offer chips for this line.
		if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() ) {
			$wpec_line_offer_bits = array();
			if ( isset( $line_item->is_free_gift ) && $line_item->is_free_gift ) {
				$wpec_line_offer_bits[] = '<span class="ecodv2-chip ecodv2-chip-gift">' . wp_easycart_offers_text( 'cart_offers', 'gift_line_label' ) . '</span>';
			}
			if ( isset( $line_item->bundle_group_key ) && '' != $line_item->bundle_group_key ) {
				$wpec_line_offer_bits[] = '<span class="ecodv2-chip ecodv2-chip-bundle">' . wp_easycart_offers_text( 'cart_offers', 'bundle_line_label' ) . ' #' . esc_attr( substr( $line_item->bundle_group_key, 0, 6 ) ) . '</span>';
			}
			$wpec_line_offers = ( isset( $line_item->applied_offers ) && '' != $line_item->applied_offers ) ? json_decode( (string) $line_item->applied_offers, true ) : false;
			if ( is_array( $wpec_line_offers ) ) {
				foreach ( $wpec_line_offers as $wpec_line_offer ) {
					if ( isset( $wpec_line_offer['label'] ) && isset( $wpec_line_offer['amount'] ) && (float) $wpec_line_offer['amount'] > 0 ) {
						$wpec_line_offer_bits[] = '<span class="ecodv2-chip ecodv2-chip-offer">' . esc_attr( $wpec_line_offer['label'] ) . ' &minus;' . esc_attr( $GLOBALS['currency']->get_currency_display( (float) $wpec_line_offer['amount'] ) ) . '</span>';
					}
				}
			}
			if ( count( $wpec_line_offer_bits ) > 0 ) {
				echo '<div class="ec_admin_order_details_line_item_offers ecodv2-line-chips">' . implode( ' ', $wpec_line_offer_bits ) . '</div>'; // phpcs:ignore
			}
		}
		?>
		<div class="ecodv2-item-meta">
		<div class="ec_details_option_label">SKU:</div> <div class="ec_details_option_value" id="ec_admin_order_details_item_model_number_display_<?php echo esc_attr( $line_item->orderdetail_id ); ?>"><?php echo esc_attr( $line_item->model_number );?></div>
		<?php
		if( $line_item->subscription_signup_fee > 0 ){
			echo '<div class="ec_details_option_label">' . esc_attr__( 'Sign Up Fee', 'wp-easycart' ) . ':</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_fee_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ';
			if( $GLOBALS['currency']->get_symbol_location() ) {
				echo esc_attr( $GLOBALS['currency']->get_symbol( ) );
			}
			echo esc_attr( number_format( $line_item->subscription_signup_fee, 2 ) );
			echo '</div>';
		}
		if( $line_item->optionitem_label_1 || $line_item->optionitem_name_1 ){
			if( $line_item->optionitem_label_1 )
				echo '<div class="ec_details_option_label">' . esc_attr( $line_item->optionitem_label_1 ).':</div> ';
			else 
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ' 1:</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_name_1_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->optionitem_name_1 ) . '</div>';
		}
		if( $line_item->optionitem_label_2 || $line_item->optionitem_name_2 ){
			if($line_item->optionitem_label_2)
				echo '<div class="ec_details_option_label">' . esc_attr( $line_item->optionitem_label_2 ) . ':</div> ';
			else 
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ' 2:</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_name_2_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->optionitem_name_2 ) . '</div>';
		}
		if( $line_item->optionitem_label_3 || $line_item->optionitem_name_3 ){
			if($line_item->optionitem_label_3)
				echo '<div class="ec_details_option_label">' . esc_attr( $line_item->optionitem_label_3 ) . ':</div> ';
			else 
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ' 3:</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_name_3_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->optionitem_name_3 ) . '</div>';
		}
		if( $line_item->optionitem_label_4 || $line_item->optionitem_name_4 ){
			if($line_item->optionitem_label_4)
				echo '<div class="ec_details_option_label">' . esc_attr( $line_item->optionitem_label_4 ) . ':</div> ';
			else 
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ' 4:</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_name_4_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->optionitem_name_4 ) . '</div>';
		}
		if( $line_item->optionitem_label_5 || $line_item->optionitem_name_5 ){
			if($line_item->optionitem_label_5)
				echo '<div class="ec_details_option_label">' . esc_attr( $line_item->optionitem_label_5 ) . ':</div> ';
			else 
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ' 5:</div> ';
			echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_optionitem_name_5_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->optionitem_name_5 ) . '</div>';
		}
		$is_download_allowed = true;
		foreach( $advanced_options as $advanced_option ){
			if ( ! $advanced_option->optionitem_allow_download ) {
				$is_download_allowed = false;
			}
			if ( $advanced_option->option_name ) {
				echo '<div class="ec_details_option_label">' . esc_attr( $advanced_option->option_name ) . ':</div> ';
			} else {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Option', 'wp-easycart' ) . ':</div> ';
			}
			if ( $advanced_option->option_type == 'file' ) {
				echo '<div class="ec_details_option_value"> <a href="' . esc_url( plugins_url( '/wp-easycart-data/products/uploads/' . esc_attr( $advanced_option->option_value ), EC_PLUGIN_DATA_DIRECTORY ) ) . '" target="_blank">' . esc_attr__( 'Download File', 'wp-easycart' ) . '</a>';
			} else if( $advanced_option->option_type == "grid" ) {
				echo '<div class="ec_details_option_value"> ' . esc_attr( $advanced_option->optionitem_name ) . ' (' . esc_attr( $advanced_option->option_value ) . ')';
			} else {
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_adv_optionitem_' . esc_attr( $line_item->orderdetail_id ) . '_' . esc_attr( $advanced_option->order_option_id ) . '"> ' . esc_attr( $advanced_option->option_value );
			}

			if ( $advanced_option->optionitem_enable_custom_price_label && ( $advanced_option->optionitem_price != 0 || ( isset( $advanced_option->optionitem_price ) && $advanced_option->optionitem_price != 0 ) || ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime != 0 ) ) ) {
				echo '<span class="ec_details_option_pricing">' . esc_attr( $advanced_option->optionitem_custom_price_label ) . '</span>';
			} else if ( $advanced_option->optionitem_price > 0 ) {
				echo '<span class="ec_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
			} else if ( $advanced_option->optionitem_price < 0 ) {
				echo '<span class="ec_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
			} else if ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime > 0 ) {
				echo '<span class="ec_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
			} else if ( isset( $advanced_option->optionitem_price_onetime ) && $advanced_option->optionitem_price_onetime < 0 ) {
				echo '<span class="ec_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
			} else if ( isset( $advanced_option->optionitem_price_override ) && $advanced_option->optionitem_price_override > -1 ) {
				echo '<span class="ec_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->optionitem_price_override ) ) . ')</span>';
			} else if ( $advanced_option->option_price_change != '0.000' && $advanced_option->option_price_change == (string) number_format( (float) $advanced_option->option_price_change, 3, '.', '' ) ) {
				echo '<span class="ec_details_option_pricing"> ' . esc_attr( $GLOBALS['currency']->get_currency_display( $advanced_option->option_price_change ) ) . '</span>';
			} else if ( $advanced_option->option_price_change != '0.000' ) {
				echo '<span class="ec_details_option_pricing"> ' . esc_attr( $advanced_option->option_price_change ) . '</span>';
			}

			echo '</div>';
		}

		if( $line_item->is_giftcard ){
			if( $line_item->giftcard_id ){
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Gift Card ID', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_giftcard_id_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->giftcard_id ) . '</div>';
			}
			if($line_item->gift_card_email) {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Gift Card Send to Email', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_gift_card_email_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->gift_card_email ) . '</div>';
			}
			if($line_item->gift_card_to_name) {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Gift Card To', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_gift_card_to_name_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->gift_card_to_name ) . '</div>';
			}
			if($line_item->gift_card_from_name) {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Gift Card From', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_gift_card_from_name_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->gift_card_from_name ) . '</div>';
			}
			if($line_item->gift_card_message) {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Gift Card Message', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value" id="ec_admin_order_details_item_gift_card_message_display_' . esc_attr( $line_item->orderdetail_id ) . '"> ' . esc_attr( $line_item->gift_card_message ) . '</div>';
			}
			if($line_item->gift_card_email) {
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Manage', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value"><a href="#" onclick="return ec_admin_resend_giftcard(' . esc_attr( $line_item->order_id ) . ', ' . esc_attr( $line_item->orderdetail_id ) . ');">Resend Gift Card Email</a></div>';
			}
		}

		if( $line_item->is_download ){
			if ( ! $is_download_allowed ) {
				echo '<div id="ec_details_option_no_downloads_' . esc_attr( $line_item->orderdetail_id ) . '">';
					echo '<div class="ec_details_option_label" style="float:left; width:100%; font-size:14px; margin-left:0px;">' . esc_attr__( 'Download disabled by option item settings.', 'wp-easycart' ) . '</div> ';
					echo '<div class="ec_details_option_value" style="float:left; width:100%; padding-left:10px;"><a class="ec_admin_order_edit_button" style="float:left;" onclick="ec_admin_enable_download_item(\'' . esc_attr( $line_item->orderdetail_id ) . '\')">' . esc_attr__( 'Enable Download', 'wp-easycart' ) . '</a></div>';
				echo '</div>';
			}
			if ( ! $is_download_allowed ) {
				echo '<div id="ec_details_option_yes_downloads_' . esc_attr( $line_item->orderdetail_id ) . '" style="display:none;">';
			}
			if( $line_item->is_amazon_download == 1 ){
				if( $line_item->amazon_key ){
					echo '<div class="ec_details_option_label">' . esc_attr__( 'Download File Name (S3 Server)', 'wp-easycart' ) . ':</div> ';
					echo '<div class="ec_details_option_value"> ' . esc_attr( $line_item->amazon_key ) . '</div>';
				}
			}else{
				if( $line_item->download_file_name ){
					echo '<div class="ec_details_option_label">' . esc_attr__( 'Download File Name (Web Server)', 'wp-easycart' ) . ':</div> ';
					echo '<div class="ec_details_option_value"> ' . esc_attr( $line_item->download_file_name ) . '</div>';
				}
			}
			if( $line_item->maximum_downloads_allowed ){
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Max Downloads Allowed', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value"> ' . esc_attr( $line_item->maximum_downloads_allowed ) . '</div>';
			}
			if( $line_item->download_timelimit_seconds ){
				echo '<div class="ec_details_option_label">' . esc_attr__( 'Download Time (seconds)', 'wp-easycart' ) . ':</div> ';
				echo '<div class="ec_details_option_value"> ' . esc_attr( $line_item->download_timelimit_seconds ) . '</div>';
			}
			echo '<div><div class="ec_details_option_label">' . esc_attr__( 'Unique Download ID (view manage downloads to find key)', 'wp-easycart' ) . ':</div></div>';
			echo '<div class="ec_details_option_value">';
			echo '<select class="ec_order_download_key select2" style="width:100% !important; float:left;" data-orderdetail-id="' . esc_attr( $line_item->orderdetail_id ) . '">';
			echo '<option value="0">' . esc_attr__( 'Select Download', 'wp-easycart' ) . '</option>';
			if( $line_item->download_key != '0' ){
				echo '<option value="' . esc_attr( $line_item->download_key ) . '" selected="selected">' . esc_attr( $line_item->download_key ) . '</option>';
			}
			echo '</select>';
			echo '</div>';
			if ( ! $is_download_allowed ) {
				echo '</div>';
			}
		}

		if( $line_item->subscription_id != 0 ){
			echo '<div class="ec_details_option_label">' . esc_attr__( 'Subscription ID', 'wp-easycart' ) . ' (' . esc_attr( $line_item->subscription_id ) . '):</div> ';
			echo '<div class="ec_details_option_value"><a href="admin.php?page=wp-easycart-orders&subpage=subscriptions&subscription_id=' . esc_attr( $line_item->subscription_id ) . '&ec_admin_form_action=edit" target="_blank">' . esc_attr__( 'View Subscription Information', 'wp-easycart' ) . '</a></div>';
		}

		do_action( 'wp_easycart_admin_order_details_item', $line_item );

		?>
		</div><?php /* .ecodv2-item-meta */ ?>
	</div>

	<div class="ec_admin_order_details_item_price" id="ec_admin_order_details_item_price_display_<?php echo esc_attr( $line_item->orderdetail_id ); ?>"><?php echo esc_attr( $line_item->quantity ); ?><span> x </span><?php if( $GLOBALS['currency']->get_symbol_location( ) ){ echo esc_attr( $GLOBALS['currency']->get_symbol( ) ); } ?><?php echo esc_attr( apply_filters( 'wp_easycart_cart_item_unit_price_display', number_format( $line_item->unit_price, 2 ), $line_item->product_id ) ); ?></div>
	<div class="ec_admin_order_details_item_total" id="ec_admin_order_details_item_total_display_<?php echo esc_attr( $line_item->orderdetail_id ); ?>"><?php if( $GLOBALS['currency']->get_symbol_location( ) ){ echo esc_attr( $GLOBALS['currency']->get_symbol( ) ); } ?><?php echo esc_attr( number_format( $line_item->total_price, 2 ) ); ?></div>

	<div class="ec_admin_order_details_item_actions">
		<?php $delete_line_action = apply_filters( 'wp_easycart_admin_order_details_delete_line_action', 'show_pro_required' ); ?>
		<?php $edit_line_action = apply_filters( 'wp_easycart_admin_order_details_edit_line_action', 'show_pro_required' ); ?>
		<div class="dashicons-before dashicons-edit" onclick="ecodv2_open_line_modal( '<?php echo esc_attr( $line_item->orderdetail_id ); ?>', '<?php echo esc_attr( $edit_line_action ); ?>' ); return false;" id="ec_admin_order_line_edit_<?php echo esc_attr( $line_item->orderdetail_id ); ?>" title="<?php esc_attr_e( 'Edit line', 'wp-easycart' ); ?>"></div>
		<div class="dashicons-before dashicons-trash" onclick="ecodv2_confirm_line_delete( '<?php echo esc_attr( $line_item->orderdetail_id ); ?>', this, '<?php echo esc_attr( $delete_line_action ); ?>' ); return false;" title="<?php esc_attr_e( 'Remove line', 'wp-easycart' ); ?>"></div>
	</div>

	<?php do_action( 'wp_easycart_admin_order_details_line_item_end', $line_item ); ?>
</div>