<?php if ( isset( $_GET['account_success'] ) && 'login_success' == $_GET['account_success'] ) {
	do_action( 'wp_easycart_login_success_account' );
} ?>
<?php $ec_account_modern = $this->account_modern(); /* 6.0.4: the account menu beside the page ( ec_accountpage::display_account_navigation() ) and breadcrumbs; an Elementor account widget keeps the older markup */ ?>
<section class="ec_account_page<?php echo ( $ec_account_modern ) ? ' ec_account_v2' : ''; ?>" id="ec_account_dashboard">
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>

	<div class="ec_account_left<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) {
			$ec_dashboard_name = isset( $GLOBALS['ec_user']->first_name ) ? trim( wp_strip_all_tags( stripslashes( (string) $GLOBALS['ec_user']->first_name ) ) ) : '';
			?>
		<div class="ec_account_dashboard_welcome"><?php echo esc_html( ( '' !== $ec_dashboard_name ) ? str_replace( '[name]', $ec_dashboard_name, ec_accountpage::account_phrase( 'account_dashboard', 'account_dashboard_welcome', 'Welcome back, [name]' ) ) : ec_accountpage::account_phrase( 'account_dashboard', 'account_dashboard_welcome_no_name', 'Welcome back' ) ); ?></div>
		<?php } ?>
		<?php do_action( 'wpeasycart_dashboard_top' ); ?>

		<?php /* 6.0.4: recent orders and downloads, and the account details below, each in their own wrapper so a theme or plugin can place, hide or tab them. */ ?>
		<div class="ec_account_dashboard_orders" id="ec_account_dashboard_orders">

		<div class="ec_cart_header ec_top ec_cart_header_no_border"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_recent_orders_title' )?></div>

		<?php if ( $this->orders->num_orders > 0 ) {
			$is_first_order = true;
			$max_orders = 5;
			$current_order = 0;

			for ( $current_order; $current_order < count( $this->orders->orders ) && $current_order < $max_orders; $current_order++ ) {
				$order = $this->orders->orders[$current_order]; 
				$order_details = $this->mysqli->get_order_details( $order->order_id, $GLOBALS['ec_cart_data']->cart_data->user_id );
		?>

		<?php if ( $ec_account_modern ) { /* 6.0.4: each recent order is one card */ ?>
		<div class="ec_account_dashboard_order_v2 is-<?php echo esc_attr( $order->account_tone() ); ?>">
		<?php } ?>
		<div class="ec_account_order_header_row<?php if( !$is_first_order ){?> ec_account_order_header_row_not_first <?php } $is_first_order = false; ?>">
			<div class="ec_account_order_header_column_left ec_account_order_header_column_left_div1">
				<span><?php $order->display_order_status( ); //wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_placed' )?></span>
				<span><?php $order->display_order_date( ); ?></span>
			</div>
			<div class="ec_account_order_header_column_left ec_account_order_header_column_left_div2">
				<span><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_total' )?></span>
				<span><?php $order->display_grand_total( ); ?></span>
			</div>
			<div class="ec_account_order_header_column_left ec_account_order_header_column_left_div3">
				<span><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_ship_to' )?></span>
				<div>
					<a href="#" class="ec_account_dashboard_order_info_link"><?php $order->display_order_shipping_first_name( ); ?> <?php $order->display_order_shipping_last_name( ); ?>
						<span><strong><?php $order->display_order_shipping_first_name( ); ?> <?php $order->display_order_shipping_last_name( ); ?></strong><br />
						<?php if( $order->shipping_company_name != "" ){ ?>
						<?php echo esc_attr( htmlspecialchars( $order->shipping_company_name, ENT_QUOTES ) ); ?><br />
						<?php }?>
						<?php $order->display_order_shipping_address_line_1( ); ?><br />
						<?php if( $order->shipping_address_line_2 != "" ){ ?>
						<?php echo esc_attr( htmlspecialchars( $order->shipping_address_line_2, ENT_QUOTES ) ); ?><br />
						<?php }?>
						<?php $order->display_order_shipping_city( ); ?>, <?php $order->display_order_shipping_state( ); ?> <?php $order->display_order_shipping_zip( ); ?><br />
						<?php $order->display_order_shipping_country( ); ?><?php if( $order->shipping_phone != "" ){ ?><br />
						<?php echo wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_phone' )?>: <?php $order->display_order_shipping_phone( ); ?><?php }?></span>
					</a>
				</div>
			</div>
			<div class="ec_account_order_header_column_left ec_account_order_header_column_left_div4">
				<span><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_order_label' )?> <?php echo esc_attr( $order->order_id ); ?></span>
				<?php /* 6.0.2: Settings › Documents › Customer downloads can turn the print link off, and a document rule can for some orders ( as on the order details page ). */ ?>
				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- language phrases, sanitized when saved. ?>
				<div><a href="<?php echo esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order->order_id ) ) ); ?>"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_view_details' )?></a><?php if ( class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'customer_download' ) ? wp_easycart_documents::customer_download( 'print_receipt', (int) $order->order_id ) : get_option( 'ec_option_account_print_receipt', 1 ) ) { ?> | <a href="<?php echo esc_attr( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => (int) $order->order_id ) ) ); ?>" target="_blank"><?php echo wp_easycart_language( )->get_text( 'cart_success', 'cart_success_print_receipt_text' )?></a><?php } ?></div>
			</div>
		</div>

		<?php foreach ( $order_details as $detail ) {
			$order_item = new ec_orderdetail( $detail ); ?>
		<div class="ec_account_order_item_row">
			<div class="ec_account_order_item_content">
				<div class="ec_account_order_item_image">
					<?php $order_item->display_image( "small" ); ?>
				</div>
				<div class="ec_account_order_item_details">
					<span class="ec_account_order_item_title"><?php $order_item->display_title(); ?><?php if( $detail->quantity > 1 ){ ?> (<?php $order_item->display_quantity(); ?>)<?php }?></span>
					<?php do_action( 'wpeasycart_dashboard_recent_order_item', $order_item ); ?>
					<?php $advanced_optionitem_download_allowed = true;
					if ( $order_item->use_advanced_optionset ) {
						$advanced_options = $this->mysqli->get_order_options( $order_item->orderdetail_id );
						foreach ( $advanced_options as $advanced_option ) {
							if ( ! $advanced_option->optionitem_allow_download ) {
								$advanced_optionitem_download_allowed = false;
							}
							if ( $advanced_option->option_type == "file" ) {
								$file_split = explode( "/", $advanced_option->option_value );
								echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $file_split[1] ) . "</span>";
							} else if( $advanced_option->option_type == "grid" ) {
								echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . wp_easycart_escape_html( $advanced_option->optionitem_name . " (" . $advanced_option->option_value . ")" ) . "</span>";
							} else {
								echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ": " . esc_attr( $advanced_option->option_value ) . "</span>";
							}
						}
					} else {
						if ( $order_item->has_option1() ) {
							echo "<span>"; $order_item->display_option1( ); 
							if( $order_item->has_option1_price( ) ){ 
								echo "("; $order_item->display_option1_price( ); echo ")";
							}
							echo "</span>";
						}
						if ( $order_item->has_option2() ) {
							echo "<span>"; $order_item->display_option2( ); 
							if( $order_item->has_option2_price( ) ){ 
								echo "("; $order_item->display_option2_price( ); echo ")";
							}
							echo "</span>";
						}
						if ( $order_item->has_option3() ) {
							echo "<span>"; $order_item->display_option3( ); 
							if( $order_item->has_option3_price( ) ){ 
								echo "("; $order_item->display_option3_price( ); echo ")";
							}
							echo "</span>";
						}
						if ( $order_item->has_option4() ) {
							echo "<span>"; $order_item->display_option4( ); 
							if( $order_item->has_option4_price( ) ){ 
								echo "("; $order_item->display_option4_price( ); echo ")";
							}
							echo "</span>";
						}
						if ( $order_item->has_option5() ) {
							echo "<span>"; $order_item->display_option5( ); 
							if( $order_item->has_option5_price( ) ){ 
								echo "("; $order_item->display_option5_price( ); echo ")";
							}
							echo "</span>";
						}
					}
					if ( $order_item->has_gift_card_message() ) {
						echo "<span>";
						$order_item->display_gift_card_message( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ); 
						echo "</span>";
					}
					if( $order_item->has_gift_card_from_name() ) {
						echo "<span>";
						$order_item->display_gift_card_from_name( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_from' ) );
						echo "</span>";
					}
					if ( $order_item->has_gift_card_to_name() ) {
						echo "<span>";
						$order_item->display_gift_card_to_name( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_to' ) );
						echo "</span>";
					}
					if ( $order_item->has_print_gift_card_link() && $order->is_approved ) {
						echo "<span>";
						$order_item->display_print_online_link( wp_easycart_language( )->get_text( "account_order_details", "account_orders_details_print_online" ) ); 
						echo "</span>";
					}
					if ( $order_item->include_code && $order->is_approved ) {
						global $wpdb;
						/* 6.0.2: this line's codes ( it read an undefined $this->cart->cart[$i], so no code was ever shown ). */
						$codes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_code WHERE ec_code.orderdetail_id = %d", (int) $order_item->orderdetail_id ) );
						$code_list = "";
						for ( $code_index = 0; $code_index < count( $codes ); $code_index++ ) {
							if( $code_index > 0 )
								$code_list .= ", ";
							$code_list .= $codes[$code_index]->code_val;
						}
						echo "<span>";
						echo wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_your_codes' ); 
						echo esc_attr( $code_list );
						echo "</span>";
					 }

					if ( $order->has_membership_page() ) {
						echo "<span><a href=\"" . esc_attr( $order->get_membership_page_link( ) ) . "\">" . wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_5" ) . "</a></span>";
					 }
					 ?>
					 <span class="ec_account_order_item_price"><?php $order_item->display_unit_price(); ?></span>
				</div>
			</div>
			<?php $product_link = $order_item->get_product_link(); ?>
			<div class="ec_account_order_item_buttons<?php if( $product_link && $order_item->has_download_link( ) && $order->is_approved && $advanced_optionitem_download_allowed ){ echo " ec_account_order_item_two_buttons"; } ?>">

				<?php if ( $order_item->has_download_link( ) && $order->is_approved && $advanced_optionitem_download_allowed ) {
					 echo "<span>";
					 $order_item->display_download_link( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_download' ) );
					 echo "</span>";
				}
				if ( $product_link ) { ?>
				<span class="ec_account_order_item_buy_button"><a href="<?php echo esc_attr( $product_link ); ?>"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_buy_item_again' )?></a></span>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
		<?php if ( $ec_account_modern ) { ?>
		</div>
		<?php } ?>
		<?php } ?>

		<div class="ec_account_dashboard_row_divider">
			<?php $this->display_orders_link( wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_all_orders_linke' ) ); ?>
		</div>

		<?php }else{ echo wp_easycart_language( )->get_text( "account_dashboard", "account_dashboard_recent_orders_none" ); }?>
		<?php if( count( $this->downloads ) > 0 ){
			// 6.0.4: the heading is a phrase ( it was English on every store ); the link counts against its own download ( it passed
			// $order_item, the last line drawn above, so another line's counter moved, or a warning when no order was drawn ).
			$wpeasycart_downloads_title = wp_easycart_language()->get_text( 'account_dashboard', 'account_dashboard_downloads_title' );
			?>
		<?php if ( $ec_account_modern ) { ?>
		<div class="ec_account_dashboard_downloads_v2">
		<?php } ?>
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- language phrase, escaped by get_text(). ?>
		<div class="ec_cart_header"><?php echo ( null !== $wpeasycart_downloads_title && '' !== $wpeasycart_downloads_title ) ? $wpeasycart_downloads_title : 'Your Downloads'; ?></div>
		<?php
		global $wpdb;
		foreach ( $this->downloads as $download ) {
		?>
		<div class="ec_account_download_line">
			<div class="ec_account_download_line_title">
				<a href="<?php echo esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $download->order_id, 'orderdetail_id' => (int) $download->orderdetail_id, 'download_id' => esc_attr( $download->download_id ) ) ) ); ?>" target="_blank" onclick="update_download_count( '<?php echo esc_attr( $download->orderdetail_id ); ?>' );"><?php echo esc_attr( $download->title ); ?></a>
			</div>
			<?php if( isset( $download->maximum_downloads_allowed ) && $download->maximum_downloads_allowed > 0 ){ ?>
			<div class="ec_account_download_line_limit">
				<?php echo "<span id=\"ec_download_count_" . esc_attr( $download->orderdetail_id ) . "\">" . esc_attr( $download->download_count ) . "</span>" . "/" . "<span id=\"ec_download_count_max_" . esc_attr( $download->orderdetail_id ) . "\">" . esc_attr( $download->maximum_downloads_allowed ) . "</span> " . wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_downloads_used' ); ?>
			</div>
			<?php }?>
			<?php /* 6.0.4: ec_db::get_download_list() gives the end of a time limit ( it read the account page's own properties ). */ ?>
			<?php if ( ! empty( $download->download_expires ) ) {
				$date_format = gmdate( 'd M Y', (int) $download->download_expires );
			?>
			<div class="ec_account_download_line_time_limit">
				<?php echo wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_downloads_expire_time' ) . " " . esc_attr( $date_format ); ?>
			</div>
			<?php } ?>

		</div>
		<?php } ?>
		<?php if ( $ec_account_modern ) { ?>
		</div>
		<?php } ?>
		<?php } ?>

		</div>

		<div class="ec_account_dashboard_details" id="ec_account_dashboard_details">
		<?php echo ( $ec_account_modern ) ? '<div class="ec_account_dashboard_card_v2">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
		<div class="ec_cart_header"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_email_title' )?></div>

		<div class="ec_cart_input_row"><?php $GLOBALS['ec_user']->display_email(); ?></div>

		<div class="ec_cart_input_row">
			<?php $this->display_personal_information_link( wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_email_edit_link' ) ); ?>
			<?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_email_note' )?></div>
		<?php echo ( $ec_account_modern ) ? '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
		<?php echo ( $ec_account_modern ) ? '<div class="ec_account_dashboard_card_v2">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
		<div class="ec_cart_header"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_billing_title' )?></div>

		<?php if( $GLOBALS['ec_user']->billing->first_name || $GLOBALS['ec_user']->billing->last_name ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->billing->display_first_name(); ?>
			<?php $GLOBALS['ec_user']->billing->display_last_name(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->company_name ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->billing->display_company_name(); ?>
		</div>
		<?php } ?>

		<?php if( get_option( 'ec_option_collect_vat_registration_number' ) && $GLOBALS['ec_user']->vat_registration_number ){ ?>
		<div class="ec_cart_input_row">
			<?php echo esc_attr( $GLOBALS['ec_user']->vat_registration_number ); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->address_line_1 ){ ?>
		<div class="ec_cart_input_row">
		  <?php $GLOBALS['ec_user']->billing->display_address_line_1(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->address_line_2 != "" ){ ?>
		<div class="ec_cart_input_row">
		  <?php $GLOBALS['ec_user']->billing->display_address_line_2( ); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->city || $GLOBALS['ec_user']->billing->state || $GLOBALS['ec_user']->billing->zip ){ ?>
		<div class="ec_cart_input_row">
		  <?php $GLOBALS['ec_user']->billing->display_city(); ?>, <?php $GLOBALS['ec_user']->billing->display_state(); ?> <?php $GLOBALS['ec_user']->billing->display_zip(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->country ){ ?>
		<div class="ec_cart_input_row">
		  <?php $GLOBALS['ec_user']->billing->display_country(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->billing->phone ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->billing->display_phone(); ?>
		</div>
		<?php } ?>

		<div class="ec_cart_input_row">
			<?php $this->display_billing_information_link( wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_billing_link' ) ); ?>
		</div>
		<?php echo ( $ec_account_modern ) ? '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>

		<?php if( get_option( 'ec_option_use_shipping' ) ){ ?>
		<?php echo ( $ec_account_modern ) ? '<div class="ec_account_dashboard_card_v2">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
		<div class="ec_cart_header"><?php echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_shipping_title' )?></div>

		<?php if( $GLOBALS['ec_user']->shipping->first_name || $GLOBALS['ec_user']->shipping->last_name ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_first_name(); ?>
			<?php $GLOBALS['ec_user']->shipping->display_last_name(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->company_name ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_company_name(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->address_line_1 ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_address_line_1(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->address_line_2 != "" ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_address_line_2( ); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->city || $GLOBALS['ec_user']->shipping->state || $GLOBALS['ec_user']->shipping->zip ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_city(); ?>, <?php $GLOBALS['ec_user']->shipping->display_state(); ?> <?php $GLOBALS['ec_user']->shipping->display_zip(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->country ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_country(); ?>
		</div>
		<?php } ?>

		<?php if( $GLOBALS['ec_user']->shipping->phone ){ ?>
		<div class="ec_cart_input_row">
			<?php $GLOBALS['ec_user']->shipping->display_phone(); ?>
		</div>
		<?php } ?>

		<div class="ec_cart_input_row">
			<?php $this->display_shipping_information_link( wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_shipping_link' )); ?>
		</div>
		<?php echo ( $ec_account_modern ) ? '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
		<?php }?>
		</div>

		<?php do_action( 'wpeasycart_dashboard_bottom' ); /* 6.0.4 */ ?>
	</div>

	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'dashboard' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>
	<div style="clear:both;"></div>
	<div id="ec_current_media_size"></div>
</section>
