<?php
if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){
	if( !isset( $_COOKIE['ec_cart_facebook_order_id_tracked_' . $order->order_id] ) ){
		echo "<script>
			fbq('track', 'Purchase', {
				content_type: 'product',
				value: " . esc_attr( number_format( $order->grand_total, 2, '.', '' ) ) . ",
				currency: '" . esc_attr( $GLOBALS['currency']->get_currency_code( ) ) . "',
				contents: [";
		for( $i=0; $i<count( $order->orderdetails ); $i++ ){
			if( $i > 0 )
				echo ", ";
			echo "{
				id: '" . esc_attr( $order->orderdetails[$i]->product_id ) . "',
				quantity: " . esc_attr( $order->orderdetails[$i]->quantity ) . ",
				price: " . esc_attr( $order->orderdetails[$i]->unit_price ) . "
			}";
		}		
		echo "]
			});
		</script>";
		setcookie( 'ec_cart_facebook_order_id_tracked_' . $order->order_id, 1, time( ) + ( 3600 * 24 * 30 ), defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/', defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
	}
}
?>

<?php if ( get_option( 'ec_option_googleanalyticsid' ) != "UA-XXXXXXX-X" && get_option( 'ec_option_googleanalyticsid' ) != "" ) { ?>
<script>
	(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
	ga('create', '<?php echo esc_attr( $google_urchin_code ); ?>', '<?php echo esc_attr( $google_wp_url ); ?>');
	ga('send', 'pageview');
	ga('require', 'ecommerce', 'ecommerce.js');
	<?php $this->print_google_transaction( ); ?>
	ga('ecommerce:send');
</script>
<?php } ?>

<?php if ( '' != get_option( 'ec_option_google_ga4_property_id' ) ) { ?>
<?php if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) { ?>
<script>
	jQuery( document ).ready( function() {
		dataLayer.push( { ecommerce: null } );
		dataLayer.push( {
			event: "purchase",
			ecommerce: {
				transaction_id: "<?php echo esc_attr( $order->order_id ); ?>",
				value: <?php echo esc_attr( number_format( $order->grand_total, 2, '.', '' ) ); ?>,
				tax: <?php echo esc_attr( number_format( $order->tax_total + $order->vat_total + $order->hst_total + $order->gst_total + $order->pst_total + $order->duty_total, 2, '.', '' ) ); ?>,
				shipping: <?php echo esc_attr( number_format( $order->shipping_total, 2, '.', '' ) ); ?>,
				currency: "<?php echo esc_attr( $GLOBALS['currency']->get_currency_code( ) ); ?>",
				coupon: "<?php echo esc_attr( $order->promo_code ); ?>",
				items: [
				<?php for( $i=0; $i<count( $order->orderdetails ); $i++ ){ ?>
					{
						item_id: "<?php echo esc_attr( $order->orderdetails[$i]->model_number ); ?>",
						item_name: "<?php echo esc_attr( $order->orderdetails[$i]->title ); ?>",
						index: <?php echo esc_attr( (int) $i ); ?>,
						price: <?php echo esc_attr( number_format( $order->orderdetails[$i]->unit_price, 2, '.', '' ) ); ?>,
						item_brand: "<?php echo esc_attr( $order->orderdetails[$i]->manufacturer_name ); ?>",
						quantity: <?php echo esc_attr( number_format( $order->orderdetails[$i]->quantity, 2, '.', '' ) ); ?>
					},
				<?php } ?>
				]
			}
		} );
	} );
</script>
<?php } else { ?>
<script>
	jQuery( document ).ready( function() {
		gtag( "event", "purchase", {
			transaction_id: "<?php echo esc_attr( $order->order_id ); ?>",
			value: <?php echo esc_attr( number_format( $order->grand_total, 2, '.', '' ) ); ?>,
			tax: <?php echo esc_attr( number_format( $order->tax_total + $order->vat_total + $order->hst_total + $order->gst_total + $order->pst_total + $order->duty_total, 2, '.', '' ) ); ?>,
			shipping: <?php echo esc_attr( number_format( $order->shipping_total, 2, '.', '' ) ); ?>,
			currency: "<?php echo esc_attr( $GLOBALS['currency']->get_currency_code( ) ); ?>",
			coupon: "<?php echo esc_attr( $order->promo_code ); ?>",
			items: [
			<?php for( $i=0; $i<count( $order->orderdetails ); $i++ ){ ?>
				{
					item_id: "<?php echo esc_attr( $order->orderdetails[$i]->model_number ); ?>",
					item_name: "<?php echo esc_attr( $order->orderdetails[$i]->title ); ?>",
					index: <?php echo esc_attr( (int) $i ); ?>,
					price: <?php echo esc_attr( number_format( $order->orderdetails[$i]->unit_price, 2, '.', '' ) ); ?>,
					item_brand: "<?php echo esc_attr( $order->orderdetails[$i]->manufacturer_name ); ?>",
					quantity: <?php echo esc_attr( number_format( $order->orderdetails[$i]->quantity, 2, '.', '' ) ); ?>
				},
			<?php } ?>
			]
		} );
	} );
</script>
<?php } ?>
<?php } ?>

<?php if( get_option( 'ec_option_google_adwords_conversion_id' ) != "" ){ ?>
<!-- Google Code for WP EasyCart Sale Conversion Page -->
<script type="text/javascript">
	/* <![CDATA[ */
	var google_conversion_id = <?php echo esc_attr( get_option( 'ec_option_google_adwords_conversion_id' ) ); ?>;
	var google_transaction_id = "<?php echo esc_attr( $order->order_id ); ?>";
	var google_conversion_language = "<?php echo esc_attr( get_option( 'ec_option_google_adwords_language' ) ); ?>";
	var google_conversion_format = "<?php echo esc_attr( get_option( 'ec_option_google_adwords_format' ) ); ?>";
	var google_conversion_color = "<?php echo esc_attr( get_option( 'ec_option_google_adwords_color' ) ); ?>";
	var google_conversion_label = "<?php echo esc_attr( get_option( 'ec_option_google_adwords_label' ) ); ?>";
	var google_conversion_value = <?php echo esc_attr( number_format( $order->grand_total, 2, '.', '' ) ); ?>;
	var google_conversion_currency = "<?php echo esc_attr( get_option( 'ec_option_google_adwords_currency' ) ); ?>";
	var google_remarketing_only = <?php echo esc_attr( get_option( 'ec_option_google_adwords_remarketing_only' ) ); ?>;
	/* ]]> */
</script>
<script type="text/javascript" src="//www.googleadservices.com/pagead/conversion.js"></script>
<noscript>
	<div style="display:inline;">
	<img height="1" width="1" style="border-style:none;" alt="" src="//www.googleadservices.com/pagead/conversion/<?php echo esc_attr( get_option( 'ec_option_google_adwords_conversion_id' ) ); ?>/?value=<?php echo esc_attr( number_format( $order->grand_total, 2, '.', '' ) ); ?>&amp;currency_code=<?php echo esc_attr( get_option( 'ec_option_google_adwords_currency' ) ); ?>&amp;label=<?php echo esc_attr( get_option( 'ec_option_google_adwords_label' ) ); ?>&amp;guid=ON&amp;script=0"/>
	</div>
</noscript>
<?php } ?>

<?php do_action( 'wpeasycart_success_page_content_top', $order_id, $order ); ?>

<?php if( isset( $error_code ) && $error_code == 'ideal-pending' ){ ?>
<div class="ec_cart_error_row2" style="margin-bottom:20px;">
    <?php echo wp_easycart_language( )->get_text( 'ec_errors', 'ideal_processing' )?> 
</div>
<?php } else if ( ! $order->is_approved && ( 7 == $order->orderstatus_id || 9 == $order->orderstatus_id || 19 == $order->orderstatus_id )  ) { ?>
<div class="ec_cart_error_row2" style="margin-bottom:20px;">
    <?php echo wp_easycart_language( )->get_text( 'ec_errors', 'delayed_payment_failed' )?> 
</div>
<?php } else if ( ! $order->is_approved && 16 == $order->orderstatus_id ) { ?>
<div class="ec_cart_error_row2" style="margin-bottom:20px;">
    <?php echo wp_easycart_language( )->get_text( 'ec_errors', 'order_refunded' )?> 
</div>
<?php } else if ( ! $order->is_approved ) { ?>
<div class="ec_cart_notice_row" style="margin-bottom:20px;">
    <?php echo wp_easycart_language( )->get_text( 'ec_errors', 'payment_processing' )?> 
</div>
<?php }?>
<?php if ( $order->includes_preorder_items ) { ?>
	<div class="ec_cart_notice_row" style="margin-bottom:20px;">
		<?php echo str_replace( '[pickup_date]', esc_attr( date( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y g:i A' ), strtotime( $order->pickup_date ) ) . ' - ' . date( apply_filters( 'wp_easycart_pickup_time_close_placeholder_format', 'g:i A' ), strtotime( $order->pickup_date . ' +1 hour' ) ) ), wp_easycart_language( )->get_text( 'ec_errors', 'preorder_message' ) ); ?> 
	</div>
	<?php if ( $order->location_id ) { ?>
		<?php $location = $order->get_location(); ?>
		<?php if ( is_object( $location ) ) { ?>
			<?php
				$location_address_format = ( ( '' != $location->address_line_1 ) ? $location->address_line_1 : '' ) . ( ( '' != $location->address_line_2 ) ? ' ' . $location->address_line_2 : '' ) . ', ' . $location->city . ( ( '' != $location->state ) ? ' ' . $location->state : '' ) . ( ( '' != $location->zip ) ? ', ' . $location->zip : '' ) . ( ( '' != $location->country ) ? ', ' . $location->country : '' );
				$location_address_format =apply_filters( 'wp_easycart_location_address', $location_address_format, $location );
			?>
			<div class="ec_cart_pickup_location_box">
				<h2><?php echo wp_easycart_language( )->get_text( 'cart_payment_information', 'preorder_location' ); ?></h2>
				<?php if ( get_option( 'ec_option_pickup_location_select_enabled' ) && is_string( get_option( 'ec_option_pickup_location_google_site_key' ) ) && '' != get_option( 'ec_option_pickup_location_google_site_key' ) ) { ?>
				<div class="ec_cart_pickup_location_map" id="pickup_location_map"></div>
				<script>
				(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
					key: "<?php echo esc_attr( get_option( 'ec_option_pickup_location_google_site_key' ) ); ?>",
					v: "weekly",
				});
				</script>
				<script>
					let map;
					async function initMap() {
						const position = { lat: <?php echo esc_attr( $location->latitude ); ?>, lng: <?php echo esc_attr( $location->longitude ); ?> };
						const { Map } = await google.maps.importLibrary("maps");
						const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
						map = new Map(
							document.getElementById( 'pickup_location_map' ),
							{
								zoom: 15,
								center: position,
								mapId: 'mapid'
							}
						);
						const marker = new google.maps.marker.AdvancedMarkerElement({
							map: map,
							position: position,
							title: '<?php echo esc_attr( $location->location_label ); ?>'
						} );
					}
					initMap();
				</script>
				<?php }?>
				<div class="ec_cart_pickup_location_details">
					<h3><?php echo esc_attr( $location->location_label ); ?></h3>
					<p class="ec_cart_pickup_location_address"><?php echo esc_attr( $location_address_format ); ?></p>
					<?php if ( isset( $location->hours_note ) && is_string( $location->hours_note ) && '' != trim( $location->hours_note ) ) { ?>
						<p class="ec_cart_pickup_location_note"><?php echo esc_attr( $location->hours_note ); ?></p>
					<?php } ?>
					<?php if ( ( isset( $location->phone ) && '' != $location->phone ) || ( isset( $location->email ) && '' != $location->email ) ) { ?>
					<div class="ec_cart_pickup_location_note_button_row">
					<?php if ( isset( $location->phone ) && '' != $location->phone ) { ?>
						<a href="tel:<?php echo esc_attr( $location->phone ); ?>" title="<?php echo esc_attr( $location->phone ); ?>" class="ec_cart_pickup_location_note_button_phone"><span class="dashicons dashicons-phone"></span> <?php echo esc_attr( $location->phone ); ?></a>
					<?php } ?>
					<?php if ( isset( $location->email ) && '' != $location->email ) { ?>
						<a href="mailto:<?php echo esc_attr( $location->email ); ?>" title="<?php echo esc_attr( $location->email ); ?>" class="ec_cart_pickup_location_note_button_email"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_attr( $location->email ); ?></a>
					<?php } ?>
					</div>
					<?php }?>
				</div>
			</div>
		<?php }?>
	<?php }?>
<?php } ?>
<?php if ( $order->includes_restaurant_type ) { ?>
<div class="ec_cart_notice_row" style="margin-bottom:20px;">
	<?php echo str_replace( '[pickup_time]', esc_attr( date( apply_filters( 'wp_easycart_pickup_time_placeholder_format', 'g:i A F d, Y' ), strtotime( $order->pickup_time ) ) ), wp_easycart_language( )->get_text( 'ec_errors', 'restaurant_message' ) ); ?> 
</div>
<?php }?>

<div class="ec_cart_success_print_button_v2">
	<?php $this->display_print_receipt_link( '<span class="dashicons dashicons-printer"></span>' . wp_easycart_language( )->get_text( 'cart_success', 'cart_success_print_receipt_text' ), $order_id ); ?>
</div>

<div class="ec_order_success_row">
	<div class="ec_order_success_loader ec_order_success_loader_v2">
		<div class="ec_order_success_loader_loaded">
			<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 161.2 161.2" enable-background="new 0 0 161.2 161.2" xml:space="preserve">
				<path class="ec_order_success_loader_loaded_path" fill="none" stroke="<?php echo esc_attr( get_option( 'ec_option_details_main_color' ) ); ?>" stroke-miterlimit="10" d="M425.9,52.1L425.9,52.1c-2.2-2.6-6-2.6-8.3-0.1l-42.7,46.2l-14.3-16.4c-2.3-2.7-6.2-2.7-8.6-0.1c-1.9,2.1-2,5.6-0.1,7.7l17.6,20.3c0.2,0.3,0.4,0.6,0.6,0.9c1.8,2,4.4,2.5,6.6,1.4c0.7-0.3,1.4-0.8,2-1.5c0.3-0.3,0.5-0.6,0.7-0.9l46.3-50.1C427.7,57.5,427.7,54.2,425.9,52.1z"/>
				<circle class="ec_order_success_loader_loaded_path" fill="none" stroke="<?php echo esc_attr( get_option( 'ec_option_details_main_color' ) ); ?>" stroke-width="4" stroke-miterlimit="10" cx="80.6" cy="80.6" r="62.1"/>
				<polyline class="ec_order_success_loader_loaded_path" fill="none" stroke="<?php echo esc_attr( get_option( 'ec_option_details_main_color' ) ); ?>" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="113,52.8 74.1,108.4 48.2,86.4 "/>
			</svg>
		</div>
	</div>
	<div class="ec_order_success_column2">
		<?php if ( $order->subscription_id > 0 ) { ?>
		<p class="ec_cart_success_order_number ec_cart_success_order_number_v2"><?php echo wp_easycart_language( )->get_text( 'cart_success', 'subscription_success_thank_you_title' ); ?></p>
		<h2 class="ec_cart_success_title ec_cart_success_title_v2"><?php echo wp_easycart_language( )->get_text( 'cart_success', 'subscription_success_thank_you_info' ); ?></h2>
		<?php } else { ?>
		<p class="ec_cart_success_order_number ec_cart_success_order_number_v2"><?php echo wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_order_number' )?> #<?php echo esc_attr( $order_id ); ?></p>
		<h2 class="ec_cart_success_title ec_cart_success_title_v2"><?php echo wp_easycart_language( )->get_text( 'cart_success', 'cart_success_thank_you_title' ); ?></h2>
		<p class="ec_cart_success_subtitle ec_cart_success_subtitle_v2"><?php echo wp_easycart_language( )->get_text( 'cart_success', 'cart_success_will_receive_email' ); ?> <?php echo esc_attr( htmlspecialchars( $order->user_email, ENT_QUOTES ) ); ?><?php echo ( ( isset( $order->email_other ) && '' != $order->email_other ) ? ', ' . esc_attr( htmlspecialchars( $order->email_other, ENT_QUOTES ) ) : '' ); ?></p>
		<?php }?>

		<p class="ec_cart_success_continue_shopping_button ec_cart_success_continue_shopping_button_v2">
			<?php if( $order->has_downloads( ) && $order->is_approved ){ ?>
			<?php $order->display_order_link( wp_easycart_language( )->get_text( 'cart_success', 'cart_success_view_downloads' ) ); ?>

			<?php }else if( $order->has_downloads( ) ){ ?>
			<?php $order->display_order_link( wp_easycart_language( )->get_text( 'cart_success', 'cart_success_view_downloads' ) ); ?>

			<?php }?>

			<?php if( $order->has_membership_page( ) ){ ?>
				<a href="<?php echo esc_attr( $order->get_membership_page_link( ) ); ?>"><?php echo wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_5" ); ?></a>
			<?php }?>

			<?php if ( $order->subscription_id > 0 ) {
				echo "<a href=\"" . esc_attr( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $order->subscription_id ) ) ) . "\">" . wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_view_subscription' ) . "</a>";
			} else {
				if ( $GLOBALS['ec_cart_data']->cart_data->is_guest == "" ) {
					echo "<a href=\"" . esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order_id ) ) ) . "\"> " . wp_easycart_language( )->get_text( 'cart_success', 'cart_payment_receipt_order_details_link' ) . "</a>";
				} else {
					echo "<a href=\"" . esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order_id, 'ec_guest_key' => $GLOBALS['ec_cart_data']->cart_data->guest_key ) ) ) . "\">" . wp_easycart_language( )->get_text( 'cart_success', 'cart_payment_receipt_order_details_link' ) . "</a>";
				}
			} ?>

			<a href="<?php echo esc_attr( $this->return_to_store_page( $this->store_page ) ); ?>"><?php echo wp_easycart_language( )->get_text( 'cart', 'cart_continue_shopping' ); ?></a>
		</p>
	</div>
</div>

<?php do_action( 'wpeasycart_success_page_content_middle', $order_id, $order ); ?>

<?php $order->display_order_customer_notes( ); ?>

<?php do_action( 'wpeasycart_success_page_content_bottom', $order_id, $order ); ?>

<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>