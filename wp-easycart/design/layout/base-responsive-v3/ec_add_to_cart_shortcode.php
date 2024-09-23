<?php $wpeasycart_addtocart_shortcode_rand = rand( 111111,9999999 );
$rules = array();
foreach ( $product->advanced_optionsets as $advanced_option ) {
	if ( isset( $advanced_option->conditional_logic ) ) {
		$rules[ $advanced_option->option_to_product_id ] = json_decode( $advanced_option->conditional_logic );
	}
} ?>
<script>
var ec_advanced_logic_rules_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> = [
	<?php
	if ( count( $rules ) > 0 ) {
		foreach ( $rules as $key => $option_rules ) {
			if ( is_object( $option_rules ) && isset( $option_rules->enabled ) && $option_rules->enabled && isset( $option_rules->rules ) && is_array( $option_rules->rules ) && count( $option_rules->rules ) > 0 ) {
				echo "{
					'id': " . esc_js( $key ) . ",
					'show_field': " . esc_attr( ( $option_rules->show_field ) ? 'true' : 'false' ) . ",
					'and_rules': '" . esc_attr( $option_rules->and_rules ) . "',
					'rules':[";
						foreach( $option_rules->rules as $rule ){
							echo "
							{	
									'option_id': " . esc_attr( (int) $rule->option_id ) . ",
									'operator': '" . esc_js( $rule->operator ) . "',
									'optionitem_id': " . esc_js( (int) $rule->optionitem_id ) . ",
									'optionitem_value': '" . esc_js( $rule->optionitem_value ) . "'
							},";
						}
					echo "
					]
				},";
			}
		}
	}?>
];
</script>

<?php if ( isset( $_GET['ec_store_success'] ) && 'inquiry_sent' == $_GET['ec_store_success'] && isset( $_GET['model'] ) && $product->model_number == $_GET['model'] ) { ?>
	<div class="ec_cart_success"><div><?php echo esc_attr( wp_easycart_language( )->get_text( "ec_success", "inquiry_sent" ) ); ?></div></div>
<?php } else if ( isset( $_GET['ec_store_success'] ) && 'addtocart' == $_GET['ec_store_success'] && isset( $_GET['model'] ) && $product->model_number == $_GET['model'] ) { ?>
	<div class="ec_cart_success"><div><?php echo esc_attr( str_replace( '[prod_title]', $product->title, wp_easycart_language( )->get_text( "ec_success", "store_added_to_cart" ) ) ); ?></div></div>
<?php }?>

<?php if ( '' == $product->inquiry_url ) { // Regular Add to Cart Form ?>
<form action="<?php echo esc_attr( $product->cart_page ); ?>" method="POST" enctype="multipart/form-data" class="ec_add_to_cart_form<?php echo esc_attr( ( ( isset( $background_add ) && $background_add ) ? ' ec_add_to_cart_form_ajax' : '' ) ); ?>" id="ec_add_to_cart_form_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
	<?php if ( $product->is_subscription_item ) { ?>
	<input type="hidden" name="ec_cart_form_action" value="subscribe_v3" />
	<input type="hidden" name="ec_cart_form_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-subscribe-' . $product->product_id ) ); ?>" />
	<?php } else { ?>
	<input type="hidden" name="ec_cart_form_action" value="add_to_cart_v3" />
	<input type="hidden" name="ec_cart_form_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-add-to-cart-' . $product->product_id ) ); ?>" />
	<?php } ?>
	<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->product_id ); ?>"  />
<?php } else { // Custom Inquiry Form ?>
<form action="<?php echo esc_attr( $product->inquiry_url ); ?>" method="GET" enctype="multipart/form-data" class="ec_add_to_cart_form">
	<input type="hidden" name="model_number" value="<?php echo esc_attr( $product->model_number ); ?>" />
<?php } ?>

	<?php /* GIFT CARD OPTIONS */ ?>
	<?php if( $product->is_giftcard ){ ?>
	<div class="ec_details_options ec_details_options_gift_card" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
		<div class="ec_details_option_row_error ec_giftcard_error" id="ec_details_giftcard_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'ec_errors', 'missing_gift_card_options' ); ?></div>
		<div class="ec_details_option_row">
			<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_recipient_name' ); ?></div>
			<div class="ec_details_option_data"><input type="text" name="ec_giftcard_to_name" id="ec_giftcard_to_name_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" /></div>
		</div>

		<div class="ec_details_option_row">
			<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_recipient_email' ); ?></div>
			<div class="ec_details_option_data"><input type="text" name="ec_giftcard_to_email" id="ec_giftcard_to_email_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" /></div>
		</div>

		<div class="ec_details_option_row">
			<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_sender_name' ); ?></div>
			<div class="ec_details_option_data"><input type="text" name="ec_giftcard_from_name" id="ec_giftcard_from_name_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" /></div>
		</div>

		<div class="ec_details_option_row">
			<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_gift_card_message' ); ?></div>
			<div class="ec_details_option_data"><textarea name="ec_giftcard_message" id="ec_giftcard_message_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></textarea></div>
		</div>
	</div>
	<?php }?>
	<?php /* END GIFT CARD OPTIONS */ ?>

	<?php /* DONATION OPTIONS */ ?>
	<?php if( $product->is_donation ){ ?>
	<div class="ec_details_options ec_details_options_donation" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
		<div class="ec_details_option_row_error ec_donation_error" id="ec_details_donation_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_donation_error' ) . " " . esc_attr( $GLOBALS['currency']->get_currency_display( $product->price ) ); ?>.</div>
		<div class="ec_details_option_row">
			<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_donation_amount' ); ?></div>
			<div class="ec_details_option_data"><input type="number" step=".01" min="<?php echo esc_attr( $GLOBALS['currency']->get_number_only( $product->price ) ); ?>" class="ec_donation_amount" name="ec_donation_amount" id="ec_donation_amount_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_number_only( $product->price ) ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-use-advanced-optionset="<?php echo ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0'; ?>" /></div>
		</div>
	</div>
	<?php } ?>
	<?php /* END DONATION OPTIONS */ ?>

	<?php $vat_rate_multiplier = 1;
	if ( ( $product->is_catalog_mode && get_option( 'ec_option_hide_price_seasonal' ) ) || ( $product->is_inquiry_mode && get_option( 'ec_option_hide_price_inquiry' ) ) ) {
		// NO PRICE SHOWN
	} else if ( $product->vat_rate > 0  && get_option( 'ec_option_show_multiple_vat_pricing' ) ) {
		global $wpdb;
		$vat_row = $wpdb->get_row( "SELECT ec_taxrate.vat_rate, ec_taxrate.vat_added, ec_taxrate.vat_included FROM ec_taxrate WHERE ec_taxrate.vat_added = 1 OR ec_taxrate.vat_included = 1" );
		$vat_rate = ( $vat_row && is_object( $vat_row ) && isset( $vat_row->vat_rate ) ) ? $vat_row->vat_rate : 0;
		$vat_rate_multiplier = ( $vat_rate / 100 ) + 1;
	}
	?>

			<?php /* PRODUCT BASIC OPTIONS */ 
			$has_quantity_grid = false;
			?>
			<?php if( $product->has_options && ( ! $product->use_advanced_optionset || $product->use_both_option_types ) ){ ?>
			<div class="ec_details_options ec_details_options_basic" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
			<?php 
			$optionsets = array( $product->options->optionset1, $product->options->optionset2, $product->options->optionset3, $product->options->optionset4, $product->options->optionset5 );

			for( $i=0; $i<5; $i++ ){ ?>

				<?php
				/* START BASIC SWATCHES AREA */
				if( count( $optionsets[$i]->optionset ) > 0 && $optionsets[$i]->option_type == 'basic-swatch' ){ ?>
					<div class="ec_details_option_row_error ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" id="ec_details_option_row_error_<?php echo esc_attr( $optionsets[$i]->option_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_missing_option' ); ?> <?php echo wp_easycart_escape_html( $optionsets[$i]->option_label ); ?></div>
					<input type="hidden" name="ec_option<?php echo esc_attr( $i+1 ); ?>" id="ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="0" />
					<div class="ec_details_option_row">
						<div class="ec_details_option_label"><?php echo wp_easycart_escape_html( $optionsets[$i]->option_label ); ?><span class="ec_details_option_label_selected ec_details_option_label_selected_<?php echo esc_attr( $i + 1 ); ?>"></span></div>
						<ul class="ec_details_swatches ec_details_html_swatches ec_details_swatches_<?php echo esc_attr( ( ( isset( $optionsets[$i]->option_meta['swatch_size'] ) ) ? (int) $optionsets[$i]->option_meta['swatch_size'] : 30 ) ); ?>">
						<?php
						for ( $j=0; $j<count( $optionsets[$i]->optionset ); $j++ ) {
							// Check the in stock status for this option item
							if ( $product->allow_backorders ) {
								$optionitem_in_stock = true;
							} else if( $product->use_optionitem_quantity_tracking && ( $i > 0 || $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] <= 0 ) ) {
								$optionitem_in_stock = false;
							} else {
								$optionitem_in_stock = true;
							}
							if ( $product->options->verify_optionitem( ( $i+1 ), $optionsets[$i]->optionset[$j]->optionitem_id ) ) {
								if ( '' != $optionsets[ $i ]->optionset[ $j ]->optionitem_icon ) {
						?>
						<li class="ec_details_swatch <?php echo ( 0 == $i ) ? 'ec_optionitem_images' : ''; ?> ec_option<?php echo esc_attr( $i+1 ); ?> ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $optionitem_in_stock ){ ?> ec_active <?php }?><?php if( $optionsets[$i]->optionset[$j]->optionitem_initially_selected || ( isset( $optionsets[$i]->option_meta['url_var'] ) && $optionsets[$i]->option_meta['url_var'] != '' && isset( $_GET[$optionsets[$i]->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionsets[$i]->option_meta['url_var']] ) ) == strtolower( $optionsets[$i]->optionset[$j]->optionitem_name ) ) || ( isset( $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] ) && $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] == $optionsets[$i]->optionset[$j]->optionitem_name ) ){ ?> ec_selected<?php }?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-optionitem-id="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_id ); ?>"<?php if( $product->use_optionitem_quantity_tracking && $i == 0 ){ ?> data-optionitem-quantity="<?php echo esc_attr( $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] ); ?>"<?php }?> data-optionitem-price="<?php if( $optionsets[$i]->optionset[$j]->optionitem_price != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price ); }else{ echo "0.00"; } ?>" data-optionitem-price-onetime="<?php if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ); }else{ echo "0.00"; } ?>" data-optionitem-price-override="<?php if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_override ); }else{ echo "-1.00"; } ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_multiplier ); ?>"><img src="<?php if( substr( $optionsets[$i]->optionset[$j]->optionitem_icon, 0, 7 ) == 'http://' || substr( $optionsets[$i]->optionset[$j]->optionitem_icon, 0, 8 ) == 'https://' ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_icon ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/swatches/" . $optionsets[$i]->optionset[$j]->optionitem_icon, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" title="<?php 
							echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_name ); ?><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if( $optionsets[$i]->optionset[$j]->optionitem_enable_custom_price_label && ( $optionsets[$i]->optionset[$j]->optionitem_price != 0 || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price ) && $optionsets[$i]->optionset[$j]->optionitem_price != 0 ) || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime != 0 ) ) ) {
								?> (<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_custom_price_label ); ?>)<?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override > -1 ){
								?> (<?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ); ?> <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_override ) ); ?>)<?php
							} ?>" /></li>
							<?php } else { // HTML Swatch ?>
						<li class="ec_details_swatch wpeasycart-html-swatch <?php echo ( 0 == $i ) ? 'ec_optionitem_images' : ''; ?> ec_option<?php echo esc_attr( $i+1 ); ?> ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $optionitem_in_stock ){ ?> ec_active <?php }?><?php if( $optionsets[$i]->optionset[$j]->optionitem_initially_selected || ( isset( $optionsets[$i]->option_meta['url_var'] ) && $optionsets[$i]->option_meta['url_var'] != '' && isset( $_GET[$optionsets[$i]->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionsets[$i]->option_meta['url_var']] ) ) == strtolower( $optionsets[$i]->optionset[$j]->optionitem_name ) ) || ( isset( $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] ) && $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] == $optionsets[$i]->optionset[$j]->optionitem_name ) ){ ?> ec_selected<?php }?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-optionitem-id="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_id ); ?>"<?php if( $product->use_optionitem_quantity_tracking && $i == 0 ){ ?> data-optionitem-quantity="<?php echo esc_attr( $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] ); ?>"<?php }?> data-optionitem-price="<?php if( $optionsets[$i]->optionset[$j]->optionitem_price != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price ); }else{ echo "0.00"; } ?>" data-optionitem-price-onetime="<?php if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ); }else{ echo "0.00"; } ?>" data-optionitem-price-override="<?php if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_override ); }else{ echo "-1.00"; } ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_multiplier ); ?>" title="<?php 
							echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_name ); ?><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if( $optionsets[$i]->optionset[$j]->optionitem_enable_custom_price_label && ( $optionsets[$i]->optionset[$j]->optionitem_price != 0 || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price ) && $optionsets[$i]->optionset[$j]->optionitem_price != 0 ) || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime != 0 ) ) ) {
								?> <?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_custom_price_label ); ?><?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override > -1 ){
								?> (<?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ); ?> <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_override ) ); ?>)<?php
							} ?>"><?php echo wp_easycart_escape_html( $optionsets[$i]->optionset[$j]->optionitem_name ); ?></li>
							<?php } ?>
						<?php }
						}
						?>
						</ul>
						<div class="ec_option_loading" id="ec_option_loading_<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_loading_options' ); ?></div>
					</div>
				<?php
				/* START COMBO BOX AREA */
				}else if( count( $optionsets[$i]->optionset ) > 0 && $optionsets[$i]->optionset[0]->optionitem_name != "" ){ ?>
				<div class="ec_details_option_row_error ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" id="ec_details_option_row_error_<?php echo esc_attr( $optionsets[$i]->option_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_missing_option' ); ?> <?php echo wp_easycart_escape_html( $optionsets[$i]->option_label ); ?></div>

				<div class="ec_details_option_row">
					<select name="ec_option<?php echo esc_attr( $i+1 ); ?>" id="ec_option<?php echo esc_attr( $i+1 ); ?>" class="ec_details_combo ec_option<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_option<?php echo esc_attr( $i+1 ); ?><?php if( $product->use_optionitem_quantity_tracking && $i > 0 ){ ?> ec_inactive<?php }?> <?php echo ( 0 == $i ) ? 'ec_optionitem_images' : ''; ?>"<?php if( $product->use_optionitem_quantity_tracking && $i > 0 ){ ?> disabled="disabled"<?php }?>>
					<option value="0"<?php if( $product->use_optionitem_quantity_tracking && $i == 0 ){ ?> data-optionitem-quantity="<?php echo esc_attr( $product->stock_quantity ); ?>"<?php }?> data-optionitem-price="0.00" data-optionitem-price-onetime="0.00" data-optionitem-price-override="-1" data-optionitem-price-multiplier="-1.00"><?php echo wp_easycart_escape_html( $optionsets[$i]->option_label ); ?></option>
					<?php
					for( $j=0; $j<count( $optionsets[$i]->optionset ); $j++ ){
						// Check the in stock status for this option item
						if( $product->allow_backorders ){
							$optionitem_in_stock = true;
						}else if( $product->use_optionitem_quantity_tracking && ( $i > 0 || $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] <= 0 ) ){
							$optionitem_in_stock = false;
						}else{
							$optionitem_in_stock = true;
						}
						if ( $product->options->verify_optionitem( ( $i + 1 ), $optionsets[$i]->optionset[$j]->optionitem_id ) ) {
					?>
					<?php if( !$product->use_optionitem_quantity_tracking || $i != 0 || $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] > 0 || $optionitem_in_stock ){ ?> 
					<option value="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_id ); ?>"<?php if( $product->use_optionitem_quantity_tracking && $i == 0 ){ ?> data-optionitem-quantity="<?php echo esc_attr( $product->option1quantity[$optionsets[$i]->optionset[$j]->optionitem_id] ); ?>"<?php }?> data-optionitem-price="<?php if( $optionsets[$i]->optionset[$j]->optionitem_price != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price ); }else{ echo "0.00"; } ?>" data-optionitem-price-onetime="<?php if( $optionsets[$i]->optionset[$j]->optionitem_price_onetime != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ); }else{ echo "0.00"; } ?>" data-optionitem-price-override="<?php if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override != "" ){ echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_override ); }else{ echo "-1.00"; } ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_price_multiplier ); ?>"<?php if( $optionsets[$i]->optionset[$j]->optionitem_initially_selected || ( isset( $optionsets[$i]->option_meta['url_var'] ) && $optionsets[$i]->option_meta['url_var'] != '' && isset( $_GET[$optionsets[$i]->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionsets[$i]->option_meta['url_var']] ) ) == strtolower( $optionsets[$i]->optionset[$j]->optionitem_name ) ) || ( isset( $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] ) && $_GET['o'.$optionsets[$i]->optionset[$j]->option_id] == $optionsets[$i]->optionset[$j]->optionitem_name ) ){ ?> selected="selected"<?php }?>><?php echo wp_easycart_escape_html( $optionsets[$i]->optionset[$j]->optionitem_name ); ?> <?php 
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if( $optionsets[$i]->optionset[$j]->optionitem_enable_custom_price_label && ( $optionsets[$i]->optionset[$j]->optionitem_price != 0 || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price ) && $optionsets[$i]->optionset[$j]->optionitem_price != 0 ) || ( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime != 0 ) ) ) {
								?> <?php echo esc_attr( $optionsets[$i]->optionset[$j]->optionitem_custom_price_label ); ?><?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( $optionsets[$i]->optionset[$j]->optionitem_price < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime > 0 ){
								?> (+<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) && $optionsets[$i]->optionset[$j]->optionitem_price_onetime < 0 ){
								?> (<?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_onetime ) ); ?> <?php echo wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ); ?>)<?php
							} else if( isset( $optionsets[$i]->optionset[$j]->optionitem_price_override ) && $optionsets[$i]->optionset[$j]->optionitem_price_override > -1 ){
								?> (<?php echo wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ); ?> <?php echo esc_attr( $GLOBALS['currency']->get_currency_display( $optionsets[$i]->optionset[$j]->optionitem_price_override ) ); ?>)<?php
							} ?></option>
					<?php }?>
					<?php }
					}
					?>
					</select>
					<div class="ec_option_loading" id="ec_option_loading_<?php echo esc_attr( $i+1 ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_loading_options' ); ?></div>
				</div>
				<?php
				}
				/* END COMBO BOX AREA*/
			}
			?>
			</div>
			<?php } ?>
			<?php /* END BASIC OPTIONS */ ?>

			<?php /* PRODUCT ADVANCED OPTIONS */ ?>
			<?php 

			$add_price_grid = 0;
			$add_order_price_grid = 0;
			$override_price_grid = -1;
			if( ( $product->use_advanced_optionset || $product->use_both_option_types ) && count( $product->advanced_optionsets ) > 0 ){ ?>
			<div class="ec_details_options ec_details_options_advanced" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<?php
				$first_optionitem_image_type = true;
				foreach( $product->advanced_optionsets as $optionset ){
					$optionitems = $product->get_advanced_optionitems( $optionset->option_id );
				?>
				<?php 
				if( $optionset->option_required ){ 
				?>
				<div class="ec_details_option_row_error" id="ec_details_adv_option_row_error_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo ( '' != $optionset->option_error_text ) ? wp_easycart_escape_html( $optionset->option_error_text ) : wp_easycart_language( )->get_text( 'product_details', 'product_details_missing_option' ) . ' ' . wp_easycart_escape_html( $optionset->option_label ); // Escaped from language class ?></div>
				<?php
				}
				?>
				<div class="ec_details_option_row ec_option_type_<?php echo esc_attr( $optionset->option_type ); ?>" data-option-id="<?php echo esc_attr( $optionset->option_id ); ?>" data-product-option-id="<?php echo esc_attr( $optionset->option_to_product_id ); ?>" data-option-required="<?php echo esc_attr( $optionset->option_required ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php echo ( ! $product->is_option_initially_visible( $optionset ) ) ? ' style="display:none"' : ''; ?>>
					<?php if( $optionset->option_type != "combo" ){ ?>
					<div class="ec_details_option_label"><?php echo wp_easycart_escape_html( $optionset->option_label ); ?><?php if( $optionset->option_type == "swatch" ){ ?><span class="ec_details_option_label_selected ec_details_option_label_selected_<?php echo esc_attr( $i + 1 ); ?>"><?php foreach( $optionitems as $optionitem ) { 
						if ( $optionitem->optionitem_initially_selected ) {
							echo esc_attr( $optionitem->optionitem_name );
							break;
						}
					} ?></span><?php }?></div>
					<?php }?>
					<div class="ec_details_option_data">
					<?php
					/* START ADVANCED CHECBOX TYPE */
					if( $optionset->option_type == "checkbox" ){
					?>

						<?php
						foreach( $optionitems as $optionitem ){
						?>

							<div class="ec_details_checkbox_row"><input type="checkbox" class="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $optionitem->optionitem_id ); ?>" value="<?php echo esc_html( wp_easycart_escape_html( $optionitem->optionitem_name ) ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-id="<?php echo esc_attr( $optionitem->optionitem_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitem->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitem->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitem->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitem->optionitem_price_multiplier ); ?>"<?php if( $optionitem->optionitem_initially_selected || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ) == strtolower( $optionitem->optionitem_name ) ) || ( isset( $_GET['o'.$optionset->option_id] ) && sanitize_text_field( $_GET['o'.$optionset->option_id] ) == $optionitem->optionitem_name ) ){ ?> checked="checked"<?php }?> /> <?php echo wp_easycart_escape_html( $optionitem->optionitem_name ); ?> <?php 
								if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
									// No pricing shown in this case.
								} else if ( $optionitem->optionitem_enable_custom_price_label && ( $optionitem->optionitem_price != 0 || ( isset( $optionitem->optionitem_price ) && $optionitem->optionitem_price != 0 ) || ( isset( $optionitem->optionitem_price_onetime ) && $optionitem->optionitem_price_onetime != 0 ) ) ) {
									echo '<span class="ec_product_details_option_pricing">' . esc_attr( wp_easycart_language( )->convert_text( $optionitem->optionitem_custom_price_label ) ) . '</span>';
								} else if ( $optionitem->optionitem_price > 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price < 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price_onetime > 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price_onetime < 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
								} else if ( isset( $optionitem->optionitem_price_override ) && $optionitem->optionitem_price_override > -1 ) {
									echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_override ) ) . ')</span>';
								} ?></div>

						<?php
						}
						?>

					<?php

					/* START ADVANCED COMBO TYPE */
					}else if( $optionset->option_type == "combo" ){
					?>
						<select name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-option-id="<?php echo esc_attr( $optionset->option_id ); ?>" data-product-option-id="<?php echo esc_attr( $optionset->option_to_product_id ); ?>"<?php echo ( $first_optionitem_image_type ) ? ' class="ec_optionitem_images"' : ''; ?>>
						<option value="0" data-optionitem-price="0.000" data-optionitem-price-onetime="0.000" data-optionitem-price-override="-1.000" data-optionitem-price-multiplier="-1.000"><?php echo wp_easycart_escape_html( $optionset->option_label ); ?></option>
						<?php
						foreach( $optionitems as $optionitem ){
						?>

							<option value="<?php echo esc_attr( $optionitem->optionitem_id ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitem->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitem->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitem->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitem->optionitem_price_multiplier ); ?>"<?php if( $optionitem->optionitem_initially_selected || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ) == strtolower( $optionitem->optionitem_name ) ) || ( isset( $_GET['o'.$optionset->option_id] ) && sanitize_text_field( $_GET['o'.$optionset->option_id] ) == $optionitem->optionitem_name ) ){ ?> selected="selected"<?php }?>><?php echo wp_easycart_escape_html( $optionitem->optionitem_name ); ?> <?php
								if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
									// No pricing shown in this case.
								} else if ( $optionitem->optionitem_enable_custom_price_label && ( $optionitem->optionitem_price != 0 || ( isset( $optionitem->optionitem_price ) && $optionitem->optionitem_price != 0 ) || ( isset( $optionitem->optionitem_price_onetime ) && $optionitem->optionitem_price_onetime != 0 ) ) ) {
									echo '<span class="ec_product_details_option_pricing">' . esc_attr( wp_easycart_language( )->convert_text( $optionitem->optionitem_custom_price_label ) ) . '</span>';
								} else if ( $optionitem->optionitem_price > 0 ) {
									echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')';
								} else if ( $optionitem->optionitem_price < 0 ) {
									echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')';
								} else if ( $optionitem->optionitem_price_onetime > 0 ) {
									echo ' (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')';
								} else if ( $optionitem->optionitem_price_onetime < 0 ) {
									echo ' (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')';
								} else if ( isset( $optionitem->optionitem_price_override ) && $optionitem->optionitem_price_override > -1 ) {
									echo ' (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_override ) ) . ')';
								} ?></option>

						<?php
						}
						?>
						</select>
					<?php
						$first_optionitem_image_type = false;

					/* START ADVANCED DATE TYPE*/
					}else if( $optionset->option_type == "date" ){
					?>

						<input type="text" value="<?php if( isset( $_GET['o'.$optionset->option_id] ) || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) ) ){ echo esc_attr( htmlspecialchars( ( ( isset( $_GET['o'.$optionset->option_id] ) ) ? sanitize_text_field( $_GET['o'.$optionset->option_id] ) : sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ), ENT_QUOTES ) ); } ?>" class="ec_is_datepicker" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" /><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( wp_easycart_language( )->convert_text( $optionitem->optionitem_custom_price_label ) ) . '</span>';
							} else if ( $optionitems[0]->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
							} ?>

					<?php

					/* START ADVANCED FILE TYPE */
					}else if( $optionset->option_type == "file" ){
					?>

						<input type="file" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" /><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitems[0]->optionitem_custom_price_label ) . '</span>';
							} else if ( $optionitems[0]->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price < 0 ){
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
							} ?>

					<?php

					/* START ADVANCED SWATCH TYPE */
					}else if( $optionset->option_type == "swatch" ){
						$initial_swatch_selected_val = 0; 
						?>
						<ul class="ec_details_swatches ec_details_swatches_<?php echo esc_attr( ( ( isset( $optionset->option_meta['swatch_size'] ) ) ? (int) $optionset->option_meta['swatch_size'] : 30 ) ); ?>">
							<?php
							for( $j=0; $j<count( $optionitems ); $j++ ){
								$initial_swatch_selected_val = ( $optionitems[$j]->optionitem_initially_selected ) ? $optionitems[$j]->optionitem_id : $initial_swatch_selected_val;
								if ( '' != $optionitems[$j]->optionitem_icon ) {
								?>
								<li class="ec_details_swatch ec_advanced <?php echo ( $first_optionitem_image_type ) ? 'ec_optionitem_images' : ''; ?> ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_active<?php if( $optionitems[$j]->optionitem_initially_selected || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) && sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) == $optionitems[$j]->optionitem_name ) || ( isset( $_GET['o'.$optionset->option_id] ) && sanitize_text_field( $_GET['o'.$optionset->option_id] ) == $optionitems[$j]->optionitem_name ) ){ ?> ec_selected<?php }?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-id="<?php echo esc_attr( $optionitems[$j]->optionitem_id ); ?>" data-option-id="<?php echo esc_attr( $optionset->option_id ); ?>" data-product-option-id="<?php echo esc_attr( $optionset->option_to_product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[$j]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[$j]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[$j]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[$j]->optionitem_price_multiplier ); ?>"><img src="<?php if( substr( $optionitems[$j]->optionitem_icon, 0, 7 ) == 'http://' || substr( $optionitems[$j]->optionitem_icon, 0, 8 ) == 'https://' ){ echo esc_attr( $optionitems[$j]->optionitem_icon ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/swatches/" . $optionitems[$j]->optionitem_icon, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" title="<?php echo esc_attr( $optionitems[$j]->optionitem_name ); ?> <?php 
									if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
										// No pricing shown in this case.
									} else if ( $optionitems[$j]->optionitem_enable_custom_price_label && ( $optionitems[$j]->optionitem_price != 0 || ( isset( $optionitems[$j]->optionitem_price ) && $optionitems[$j]->optionitem_price != 0 ) || ( isset( $optionitems[$j]->optionitem_price_onetime ) && $optionitems[$j]->optionitem_price_onetime != 0 ) ) ) {
										echo ' ' . esc_attr( $optionitems[$j]->optionitem_custom_price_label );
									} else if( $optionitems[$j]->optionitem_price > 0 ) {
										echo ' +' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' );
									} else if( $optionitems[$j]->optionitem_price < 0 ) {
										echo ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' );
									} else if( $optionitems[$j]->optionitem_price_onetime > 0 ) {
										echo ' +' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' );
									} else if( $optionitems[$j]->optionitem_price_onetime < 0 ) {
										echo ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' );
									} else if( isset( $optionitems[$j]->optionitem_price_override ) && $optionitems[$j]->optionitem_price_override > -1 ) {
										echo ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_override ) );
									} ?>" /></li>
							<?php
								} else { ?>
									<li class="ec_details_swatch wpeasycart-html-swatch ec_advanced <?php echo ( $first_optionitem_image_type ) ? 'ec_optionitem_images' : ''; ?> ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_active<?php if( $optionitems[$j]->optionitem_initially_selected || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) && sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) == $optionitems[$j]->optionitem_name ) || ( isset( $_GET['o'.$optionset->option_id] ) && sanitize_text_field( $_GET['o'.$optionset->option_id] ) == $optionitems[$j]->optionitem_name ) ){ ?> ec_selected<?php }?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-id="<?php echo esc_attr( $optionitems[$j]->optionitem_id ); ?>" data-option-id="<?php echo esc_attr( $optionset->option_id ); ?>" data-product-option-id="<?php echo esc_attr( $optionset->option_to_product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[$j]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[$j]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[$j]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[$j]->optionitem_price_multiplier ); ?>" title="<?php echo esc_attr( $optionitems[$j]->optionitem_name ); ?><?php 
									if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
										// No pricing shown in this case.
									} else if ( $optionitems[$j]->optionitem_enable_custom_price_label && ( $optionitems[$j]->optionitem_price != 0 || ( isset( $optionitems[$j]->optionitem_price ) && $optionitems[$j]->optionitem_price != 0 ) || ( isset( $optionitems[$j]->optionitem_price_onetime ) && $optionitems[$j]->optionitem_price_onetime != 0 ) ) ) {
										echo ' ' . esc_attr( $optionitems[$j]->optionitem_custom_price_label );
									} else if( $optionitems[$j]->optionitem_price > 0 ) {
										echo ' +' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' );
									} else if( $optionitems[$j]->optionitem_price < 0 ) {
										echo ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' );
									} else if( $optionitems[$j]->optionitem_price_onetime > 0 ) {
										echo ' +' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' );
									} else if( $optionitems[$j]->optionitem_price_onetime < 0 ) {
										echo ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' );
									} else if( isset( $optionitems[$j]->optionitem_price_override ) && $optionitems[$j]->optionitem_price_override > -1 ) {
										echo ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[$j]->optionitem_price_override ) );
									} ?>"><?php echo wp_easycart_escape_html( $optionitems[$j]->optionitem_name ); ?></li>
								<?php }
							}
							$first_optionitem_image_type = false;
							?>
						</ul>
						<input type="hidden" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $initial_swatch_selected_val ); ?>" />
						<?php 

					/* START ADVANCED GRID TYPE */
					}else if( $optionset->option_type == "grid" ){
						$has_quantity_grid = true;
					?>

						<?php
						foreach( $optionitems as $optionitem ){

						if( $optionitem->optionitem_initial_value > 0 ){	
							if( $optionitem->optionitem_price >= 0 ){
								$add_price_grid = $add_price_grid + $optionitem->optionitem_price;

							}else if( $optionitem->optionitem_price_override >= 0 ){
								$override_price_grid = $optionitem->optionitem_price_override;

							}else if( $optionitem->optionitem_price_onetime > 0 ){
								$add_order_price_grid = $add_order_price_grid + $optionitem->optionitem_price_onetime;

							}
						}
						?>

							<div class="ec_details_grid_row"><span><?php echo wp_easycart_escape_html( $optionitem->optionitem_name ); ?></span><input type="number" min="<?php if( $product->min_purchase_quantity > 0 ){ echo esc_attr( $product->min_purchase_quantity ); }else{ echo '0'; } ?>"<?php if( $product->show_stock_quantity || $product->max_purchase_quantity > 0 ){ ?> max="<?php if( $product->max_purchase_quantity > 0 ){ echo esc_attr( $product->max_purchase_quantity ); }else{ echo esc_attr( $product->stock_quantity ); } ?>"<?php }?> step="1" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $optionitem->optionitem_id ); ?>" value="<?php echo number_format( (float) esc_attr( $optionitem->optionitem_initial_value ), 0, "", "" ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitem->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitem->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitem->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitem->optionitem_price_multiplier ); ?>" /><?php
								if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
									// No pricing shown in this case.
								} else if ( $optionitem->optionitem_enable_custom_price_label && ( $optionitem->optionitem_price != 0 || ( isset( $optionitem->optionitem_price ) && $optionitem->optionitem_price != 0 ) || ( isset( $optionitem->optionitem_price_onetime ) && $optionitem->optionitem_price_onetime != 0 ) ) ) {
									echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitem->optionitem_custom_price_label ) . '</span>';
								} else if ( $optionitem->optionitem_price > 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price < 0 ){
									echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price_onetime > 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
								} else if ( $optionitem->optionitem_price_onetime < 0 ) {
									echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
								} else if ( isset( $optionitem->optionitem_price_override ) && $optionitem->optionitem_price_override > -1 ) {
									echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_override ) ) . ')</span>';
								} ?></div>

						<?php
						}
						?>

					<?php

					/* START ADVANCED RADIO TYPE */
					}else if( $optionset->option_type == "radio" ){
					?>

						<?php
						foreach( $optionitems as $optionitem ){
						?>

							<div class="ec_details_radio_row <?php echo ( $first_optionitem_image_type ) ? 'ec_optionitem_images' : ''; ?>"><input type="radio" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" value="<?php echo esc_attr( $optionitem->optionitem_id ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitem->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitem->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitem->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitem->optionitem_price_multiplier ); ?>"<?php if( $optionitem->optionitem_initially_selected || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) && strtolower( sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ) == strtolower( $optionitem->optionitem_name ) ) || ( isset( $_GET['o'.$optionset->option_id] ) && sanitize_text_field( $_GET['o'.$optionset->option_id] ) == $optionitem->optionitem_name ) ){ ?> checked="checked"<?php }?> /> <?php echo wp_easycart_escape_html( $optionitem->optionitem_name ); ?> <?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitem->optionitem_enable_custom_price_label && ( $optionitem->optionitem_price != 0 || ( isset( $optionitem->optionitem_price ) && $optionitem->optionitem_price != 0 ) || ( isset( $optionitem->optionitem_price_onetime ) && $optionitem->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitem->optionitem_custom_price_label ) . '</span>';
							} else if ( $optionitem->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitem->optionitem_price < 0 ){
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitem->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitem->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitem->optionitem_price_override ) && $optionitem->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitem->optionitem_price_override ) ) . ')</span>';
							} ?></div>

						<?php
						}
						?>

					<?php

					/* START ADVANCED TEXT TYPE */
					}else if( $optionset->option_type == "text" ){
					?>

						<input type="text" value="<?php if( isset( $_GET['o'.$optionset->option_id] ) || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) ) ){ echo esc_attr( htmlspecialchars( ( ( isset( $_GET['o'.$optionset->option_id] ) ) ? sanitize_text_field( $_GET['o'.$optionset->option_id] ) : sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ), ENT_QUOTES ) ); }else if( $optionitems[0]->optionitem_initial_value != '' ){ echo esc_attr( $optionitems[0]->optionitem_initial_value ); } ?>" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( isset( $optionset->option_meta['min_length'] ) && '' != $optionset->option_meta['min_length'] ) { ?> minlength="<?php echo esc_attr( $optionset->option_meta['min_length'] ); ?>"<?php }?><?php if( isset( $optionset->option_meta['max_length'] ) && '' != $optionset->option_meta['max_length'] ) { ?> maxlength="<?php echo esc_attr( $optionset->option_meta['max_length'] ); ?>"<?php }?> data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" data-optionitem-price-per-character="<?php echo esc_attr( $optionitems[0]->optionitem_price_per_character ); ?>" /><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitems[0]->optionitem_custom_price_label ) . '</span>';
							} else if ( $optionitems[0]->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price < 0 ){
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
							} ?>

					<?php

					/* START ADVANCED NUMBER TYPE */
					}else if( $optionset->option_type == "number" ){
					?>

						<input type="number" value="<?php if( isset( $_GET['o'.$optionset->option_id] ) || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) ) ){ echo esc_attr( htmlspecialchars( ( ( isset( $_GET['o'.$optionset->option_id] ) ) ? sanitize_text_field( $_GET['o'.$optionset->option_id] ) : sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ), ENT_QUOTES ) ); }else if( $optionitems[0]->optionitem_initial_value != '' ){ echo esc_attr( $optionitems[0]->optionitem_initial_value ); } ?>" min="<?php echo esc_attr( $optionset->option_meta['min'] ); ?>" max="<?php echo esc_attr( $optionset->option_meta['max'] ); ?>" step="<?php echo esc_attr( $optionset->option_meta['step'] ); ?>" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" /><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitems[0]->optionitem_custom_price_label ) . '</span>';
							} else if ( $optionitems[0]->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price < 0 ){
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
							} ?>

					<?php

					/* START ADVANCED TEXT AREA TYPE */
					}else if( $optionset->option_type == "textarea" ){
					?>

						<textarea name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" data-optionitem-price-per-character="<?php echo esc_attr( $optionitems[0]->optionitem_price_per_character ); ?>"><?php if( isset( $_GET['o'.$optionset->option_id] ) || ( isset( $optionset->option_meta['url_var'] ) && $optionset->option_meta['url_var'] != '' && isset( $_GET[$optionset->option_meta['url_var']] ) ) ){ echo esc_attr( htmlspecialchars( ( ( isset( $_GET['o'.$optionset->option_id] ) ) ? sanitize_text_field( $_GET['o'.$optionset->option_id] ) : sanitize_text_field( $_GET[$optionset->option_meta['url_var']] ) ), ENT_QUOTES ) ); } ?></textarea><?php
							if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
								// No pricing shown in this case.
							} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
								echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitems[0]->optionitem_custom_price_label ) . '</span>';
							} else if ( $optionitems[0]->optionitem_price > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price < 0 ){
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime > 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( $optionitems[0]->optionitem_price_onetime < 0 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
							} else if ( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ) {
								echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
							} ?>

					<?php

					/* START ADVANCED DIMENSIONS TYPE */
					}else if( $optionset->option_type == "dimensions1" || $optionset->option_type == "dimensions2" ){

						// Type 1 is NO sub dimensions (34")
						// Type 2 USES sub dimensions (34 1/2")

						$type = 2;

						if( $optionitems[0]->optionitem_name == "DimensionType1" )
							$type = 1;
					?>

						<?php if ( $product->login_for_pricing && ! $product->is_login_for_pricing_valid() ) {
							// No pricing shown in this case.
						} else if ( $optionitems[0]->optionitem_enable_custom_price_label && ( $optionitems[0]->optionitem_price != 0 || ( isset( $optionitems[0]->optionitem_price ) && $optionitems[0]->optionitem_price != 0 ) || ( isset( $optionitems[0]->optionitem_price_onetime ) && $optionitems[0]->optionitem_price_onetime != 0 ) ) ) {
							echo '<span class="ec_product_details_option_pricing">' . esc_attr( $optionitems[0]->optionitem_custom_price_label ) . '</span>';
						} else if( $optionitems[0]->optionitem_price > 0 ){
							echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>';
						} else if( $optionitems[0]->optionitem_price < 0 ){
							echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ')</span>'; }else if( $optionitems[0]->optionitem_price_onetime > 0 ){ echo '<span class="ec_product_details_option_pricing"> (+' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
						} else if( $optionitems[0]->optionitem_price_onetime < 0 ){
							echo '<span class="ec_product_details_option_pricing"> (' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_onetime ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_order_adjustment' ) . ')</span>';
						} else if( isset( $optionitems[0]->optionitem_price_override ) && $optionitems[0]->optionitem_price_override > -1 ){
							echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_new_price_option' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_override ) ) . ')</span>';
						}else if( isset( $optionitems[0]->optionitem_price_per_character ) && $optionitems[0]->optionitem_price_per_character > 0 ){
							echo '<span class="ec_product_details_option_pricing"> (' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment' ) . ' ' . esc_attr( $GLOBALS['currency']->get_currency_display( $optionitems[0]->optionitem_price_per_character ) ) . ' ' . wp_easycart_language( )->get_text( 'cart', 'cart_item_adjustment_per_character' ) . ')</span>';
						} ?>

						<input type="text" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_width" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_width" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" data-optionitem-price-per-character="<?php echo esc_attr( $optionitems[0]->optionitem_price_per_character ); ?>" class="ec_dimensions_box ec_dimensions_width" data-option-id="<?php echo esc_attr( $optionset->option_id ); ?>" data-product-option-id="<?php echo esc_attr( $optionset->option_to_product_id ); ?>" data-is-metric="<?php echo esc_attr( get_option( 'ec_option_enable_metric_unit_display' ) ); ?>" />
						

						<?php if( $type == 2 ){ ?>
							<select name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_sub_width" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_sub_width" class="ec_dimensions_select">
								<option value="0">0</option>
								<option value="1/16">1/16</option>
								<option value="1/8">1/8</option>
								<option value="3/16">3/16</option>
								<option value="1/4">1/4</option>
								<option value="5/16">5/16</option>
								<option value="3/8">3/8</option>
								<option value="7/16">7/16</option>
								<option value="1/2">1/2</option>
								<option value="9/16">9/16</option>
								<option value="5/8">5/8</option>
								<option value="11/16">11/16</option>
								<option value="3/4">3/4</option>
								<option value="13/16">13/16</option>
								<option value="7/8">7/8</option>
								<option value="15/16">15/16</option>
							</select>
						<?php }?>

						<span class="ec_dimensions_seperator">x</span>

						<input type="text" name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_height" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_height" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-optionitem-price="<?php echo esc_attr( $optionitems[0]->optionitem_price ); ?>" data-optionitem-price-onetime="<?php echo esc_attr( $optionitems[0]->optionitem_price_onetime ); ?>" data-optionitem-price-override="<?php echo esc_attr( $optionitems[0]->optionitem_price_override ); ?>" data-optionitem-price-multiplier="<?php echo esc_attr( $optionitems[0]->optionitem_price_multiplier ); ?>" data-optionitem-price-per-character="<?php echo esc_attr( $optionitems[0]->optionitem_price_per_character ); ?>" class="ec_dimensions_box" />
						
						<?php if( $type == 2 ){ ?>
						<select name="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_sub_height" id="ec_option_adv_<?php echo esc_attr( $optionset->option_to_product_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_sub_height" class="ec_dimensions_select">
							<option value="0">0</option>
							<option value="1/16">1/16</option>
							<option value="1/8">1/8</option>
							<option value="3/16">3/16</option>
							<option value="1/4">1/4</option>
							<option value="5/16">5/16</option>
							<option value="3/8">3/8</option>
							<option value="7/16">7/16</option>
							<option value="1/2">1/2</option>
							<option value="9/16">9/16</option>
							<option value="5/8">5/8</option>
							<option value="11/16">11/16</option>
							<option value="3/4">3/4</option>
							<option value="13/16">13/16</option>
							<option value="7/8">7/8</option>
							<option value="15/16">15/16</option>
						</select>
						<?php }?>

					<?php
					}
				?>
					</div>
				</div>				
				<?php
				}
				?>
			</div>
			<?php }?>
			<?php /* END ADVANCED OPTIONS*/ ?>
			<?php if ( ( isset( $optionsets ) && count( $optionsets ) > 0 ) || ( $product->advanced_optionsets && count( $product->advanced_optionsets ) > 0 ) ) { ?>
			<div class="ec_details_options_divider_post"></div>
			<?php } ?>
			<?php /* PRODUCT ADD TO CART */ ?>
			<div class="ec_details_option_row_error" id="ec_addtocart_quantity_exceeded_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_maximum_quantity' ); ?></div>
			<div class="ec_details_option_row_error" id="ec_addtocart_quantity_minimum_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_minimum_quantity_text1' ); ?> <?php echo esc_attr( $product->min_purchase_quantity ); ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_minimum_quantity_text2' ); ?></div>
			<div class="ec_details_option_row_error" id="ec_addtocart_quantity_maximum_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_maximum_quantity_text1' ); ?> <?php echo esc_attr( $product->max_purchase_quantity ); ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_maximum_quantity_text2' ); ?></div>

			<?php
			do_action( 'wp_easycart_product_details_pre_add_to_cart', $product->product_id );

			$show_add_to_cart_area = true;
			$show_add_to_cart_area = apply_filters( 'wp_easycart_product_details_show_cart_area', $show_add_to_cart_area );

			if( $show_add_to_cart_area ){ ?>
				<div class="ec_details_add_to_cart_area">

				<?php /* CATALOG MODE */ ?>
				<?php if( apply_filters( 'wp_easycart_catalog_display', get_option( 'ec_option_display_as_catalog' ) ) ){
					if ( get_option( 'ec_option_vacation_mode_button_text' ) && '' != get_option( 'ec_option_vacation_mode_button_text' ) ) { ?>
						<div class="ec_seasonal_mode"><?php echo wp_easycart_escape_html( apply_filters( 'wp_easycart_vacation_mode_text', wp_easycart_language( )->convert_text( get_option( 'ec_option_vacation_mode_button_text' ) ), $product->product_id ) ); ?></div>
					<?php }

				} else if ( $product->login_for_pricing && !$product->is_login_for_pricing_valid( ) && $GLOBALS['ec_user']->user_id != 0 ) { ?>
					<div class="ec_seasonal_mode"><?php echo wp_easycart_escape_html( apply_filters( 'wp_easycart_login_for_pricing_text', wp_easycart_language( )->get_text( 'product_page', 'product_page_login_for_price_no_access' ), $product->product_id ) ); ?></div>

				<?php } else if ( $product->login_for_pricing && !$product->is_login_for_pricing_valid( ) ) { ?>
					<div class="ec_details_add_to_cart"><a href="<?php echo esc_attr( $account_page ); ?>" style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>"><?php echo esc_attr( ( $product->login_for_pricing_label != '' ) ? $product->login_for_pricing_label : wp_easycart_language( )->get_text( 'product_page', 'product_page_login_for_price' ) ); ?></a></div>

				<?php } else if( $product->is_catalog_mode ) { ?>
					<div class="ec_details_seasonal_mode"><?php echo esc_attr( $product->catalog_mode_phrase ); ?></div>	

				<?php /* INQUIRY BUTTON */ ?>
				<?php } else if( $product->is_inquiry_mode ) { ?>
					<?php if( get_option( 'ec_option_use_inquiry_form' ) || $product->inquiry_url == "" ){ ?>
						<div class="ec_details_option_row_error ec_inquiry_error" id="ec_details_inquiry_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'ec_errors', 'missing_inquiry_options' ); ?></div>
						<div class="ec_details_option_row">
							<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_name' ); ?></div>
							<div class="ec_details_option_data"><input type="text" name="ec_inquiry_name" id="ec_inquiry_name_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" /></div>
						</div>
						<div class="ec_details_option_row">
							<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_email' ); ?></div>
							<div class="ec_details_option_data"><input type="text" name="ec_inquiry_email" id="ec_inquiry_email_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" /></div>
						</div>
						<div class="ec_details_option_row">
							<div class="ec_details_option_label"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_message' ); ?></div>
							<div class="ec_details_option_data"><textarea name="ec_inquiry_message" id="ec_inquiry_message_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></textarea></div>
						</div>
						<div class="ec_details_option_row">
							<div class="ec_details_option_data"><input type="checkbox" name="ec_inquiry_send_copy" id="ec_inquiry_send_copy_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" /> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquiry_send_copy' ); ?></div>
						</div>

						<?php /* Maybe add recaptcha */ ?>
						<?php if( get_option( 'ec_option_enable_recaptcha' ) && get_option( 'ec_option_recaptcha_site_key' ) != '' ){ ?>
						<input type="hidden" id="ec_grecaptcha_response_inquiry" name="ec_grecaptcha_response_inquiry" value="" />
						<input type="hidden" id="ec_grecaptcha_site_key" value="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" />
						<div class="ec_cart_input_row" data-sitekey="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" id="ec_product_details_inquiry_recaptcha"></div>
						<?php }?>
					<?php }?>

					<div class="ec_details_add_to_cart">
						<?php if( get_option( 'ec_option_use_inquiry_form' ) || $product->inquiry_url == "" ){ ?>
						<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquire' ); ?>" onclick="return ec_details_submit_inquiry( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> );" style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
						<?php }else{ ?>
						<a href="<?php echo esc_attr( $product->inquiry_url ); ?>" style="margin-left:0px !important;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_inquire' ); ?></a>
						<?php }?>
					</div>

					<input type="hidden" name="ec_cart_form_action" value="send_inquiry" />
					<input type="hidden" name="ec_cart_form_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-send-inquiry' ) ); ?>" />
					<input type="hidden" name="ec_inquiry_model_number" value="<?php echo esc_attr( $product->model_number ); ?>" />

				<?php /* DecoNetwork BUTTON */ ?>
				<?php } else if( $product->is_deconetwork ) { ?>
					<?php if( get_option( 'ec_option_deconetwork_allow_blank_products' ) ){ // Custom option to have both add to cart and design now ?>
						<div class="ec_details_quantity" data-use-advanced-optionset="<?php echo ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0'; ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-min-purchase-quantity="<?php echo esc_attr( ( ( $product->min_purchase_quantity > 0 ) ? $product->min_purchase_quantity : '1' ) ); ?>" data-max-purchase-quantity="<?php echo esc_attr( ( ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : $product->stock_quantity ) ); ?>" data-show-stock-quantity="<?php echo esc_attr( $product->show_stock_quantity ); ?>" <?php if( $has_quantity_grid ){ ?> style="display:none;"<?php }?>>
							<input type="button" value="-" class="ec_minus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
							<input type="number" value="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" name="ec_quantity" id="ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" autocomplete="off" step="1" min="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" class="ec_quantity"<?php if( $product->show_stock_quantity || $product->max_purchase_quantity > 0 ){ ?> max="<?php echo ( $product->max_purchase_quantity > 0 ) ? esc_attr( $product->max_purchase_quantity ) : esc_attr( $product->stock_quantity ); ?>"<?php } ?> />
							<input type="button" value="+" class="ec_plus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
						</div>
						<div class="ec_details_add_to_cart ec_deconetwork_custom_space">
							<input type="submit" value="<?php echo esc_attr( apply_filters( 'wp_easycart_product_details_add_to_cart_value', wp_easycart_language( )->get_text( 'product_details', 'product_details_add_to_cart' ), $product->product_id ) ); ?>" onclick="<?php if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){ ?>wp_easycart_facebook_add_to_cart_track_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( ); <?php }?>return ec_details_add_to_cart( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> );"<?php if( $has_quantity_grid ){ ?> style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>"<?php }?> />
						</div>
					<?php } ?>

					<div class="ec_details_add_to_cart">
						<a href="<?php echo esc_attr( $product->get_deconetwork_link( ) ); ?>" style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>"><?php echo wp_easycart_language( )->get_text( 'product_page', 'product_design_now' ); ?></a>
					</div>

				<?php /* SUBSCRIPTION BUTTON */ ?>
				<?php } else if( $product->is_subscription_item ) { // && !class_exists( "ec_stripe" ) ){ ?>

					<?php if ( !get_option( 'ec_option_subscription_one_only' ) ) { ?>
						<div class="ec_details_quantity" data-use-advanced-optionset="<?php echo ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0'; ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-min-purchase-quantity="<?php echo esc_attr( ( ( $product->min_purchase_quantity > 0 ) ? $product->min_purchase_quantity : '1' ) ); ?>" data-max-purchase-quantity="<?php echo esc_attr( ( ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : $product->stock_quantity ) ); ?>" data-show-stock-quantity="<?php echo esc_attr( $product->show_stock_quantity ); ?>" <?php if( $has_quantity_grid ){ ?> style="display:none;"<?php }?>>
							<input type="button" value="-" class="ec_minus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
							<input type="number" value="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" name="ec_quantity" id="ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" autocomplete="off" step="1" min="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" class="ec_quantity"<?php if( $product->show_stock_quantity || $product->max_purchase_quantity > 0 ){ ?> max="<?php echo ( $product->max_purchase_quantity > 0 ) ? esc_attr( $product->max_purchase_quantity ) : esc_attr( $product->stock_quantity ); } ?>" />
							<input type="button" value="+" class="ec_plus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
						</div>
					<?php } else { ?>
						<input type="hidden" id="ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="1" />
					<?php } ?>

					<div class="ec_details_add_to_cart">
						<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_sign_up_now' ); ?>" onclick="<?php do_action( 'wp_easycart_product_details_subscription_button_onclick', $product ); ?><?php if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){ ?>wp_easycart_facebook_add_to_cart_track_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( ); <?php }?>return ec_details_add_to_cart( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> );"<?php if( get_option( 'ec_option_subscription_one_only' ) ){ ?> style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>"<?php } ?> />
					</div>
					<span class="ec_details_hidden_base_price" id="ec_base_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo esc_attr( $product->price ); ?></span>


				<?php /* REGULAR BUTTON + QUANTITY */ ?>
				<?php } else if( $product->in_stock( ) || ( $product->allow_backorders && $product->use_optionitem_quantity_tracking ) || apply_filters( 'wp_easycart_product_details_allow_add_to_cart', false, $product->product_id ) ) { ?>
					<div class="ec_details_quantity" data-use-advanced-optionset="<?php echo ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0'; ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-min-purchase-quantity="<?php echo esc_attr( ( ( $product->min_purchase_quantity > 0 ) ? $product->min_purchase_quantity : '1' ) ); ?>" data-max-purchase-quantity="<?php echo esc_attr( ( ( $product->max_purchase_quantity > 0 ) ? $product->max_purchase_quantity : $product->stock_quantity ) ); ?>" data-show-stock-quantity="<?php echo esc_attr( $product->show_stock_quantity ); ?>" <?php if( $has_quantity_grid ){ ?> style="display:none;"<?php }?>>
						<input type="button" value="-" class="ec_minus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
						<input type="number" value="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" name="ec_quantity" id="ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" autocomplete="off" step="1" min="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" class="ec_quantity"<?php if( ( !$product->allow_backorders && $product->show_stock_quantity ) || $product->max_purchase_quantity > 0 ){ ?> max="<?php echo ( $product->max_purchase_quantity > 0 ) ? esc_attr( $product->max_purchase_quantity ) : esc_attr( $product->stock_quantity ); ?>"<?php }?> />
						<input type="button" value="+" class="ec_plus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
					</div>
					<div class="ec_details_add_to_cart">
						<input type="submit" value="<?php echo esc_attr( apply_filters( 'wp_easycart_product_details_add_to_cart_value', wp_easycart_language( )->get_text( 'product_details', 'product_details_add_to_cart' ), $product->product_id ) ); ?>" onclick="<?php if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){ ?>wp_easycart_facebook_add_to_cart_track_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( ); <?php }?>return ec_details_add_to_cart( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> );" style="<?php if( $has_quantity_grid ){ ?>margin-left:0px !important;<?php } ?><?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
					</div>

					<?php /* PRICING AREA FOR OPTIONS */ ?>
					<?php if( $product->has_options || $product->use_advanced_optionset || $product->use_both_option_types ){ ?>
					<div class="ec_details_final_price"<?php echo ( ( isset( $atts['show_price'] ) && !$atts['show_price'] ) ) ? ' style="display:none;"' : ''; ?>><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_your_price' ); ?> <span id="ec_final_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php if( $override_price_grid > -1 ){ echo esc_attr( $GLOBALS['currency']->get_currency_display( $override_price_grid ) ); }else if( $add_price_grid > 0 ){ echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->price_options + $add_price_grid ) ); }else{ echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->price_options ) ); } ?></span></div>
					<?php } ?>
					<span class="ec_details_hidden_base_price" id="ec_base_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo esc_attr( $product->price ); ?></span>

				<?php /* OUT OF STOCK BUT BACKORDERS ALLOWED */ ?>
				<?php } else if( $product->allow_backorders ) { ?>
					<div class="ec_details_quantity" data-use-advanced-optionset="<?php echo ( $product->use_advanced_optionset || $product->use_both_option_types ) ? '1' : '0'; ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-min-purchase-quantity="<?php echo esc_attr( ( ( $product->min_purchase_quantity > 0 ) ? $product->min_purchase_quantity : '1' ) ); ?>" data-max-purchase-quantity="100000000" data-show-stock-quantity="<?php echo esc_attr( $product->show_stock_quantity ); ?>" <?php if( $has_quantity_grid ){ ?> style="display:none;"<?php }?>>
						<input type="button" value="-" class="ec_minus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
						<input type="number" value="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" name="ec_quantity" id="ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" autocomplete="off" step="1" min="<?php echo ( $product->min_purchase_quantity > 0 ) ? esc_attr( $product->min_purchase_quantity ) : '1'; ?>" class="ec_quantity"<?php if( ! $product->allow_backorders && $product->max_purchase_quantity > 0 ){ ?> max="<?php echo esc_attr( $product->max_purchase_quantity ); ?>"<?php }?> />
						<input type="button" value="+" class="ec_plus" style="<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>" />
					</div>
					<div class="ec_details_add_to_cart">
						<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backorder_button' ); ?>" onclick="<?php if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){ ?>wp_easycart_facebook_add_to_cart_track_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( ); <?php }?>return ec_details_add_to_cart( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> );"<?php if( $has_quantity_grid ){ ?> style="margin-left:0px !important;<?php echo ( isset( $atts['add_to_cart_color'] ) ) ? 'background-color:' . esc_attr( $atts['add_to_cart_color'] ) . ' !important;' : ''; ?>"<?php }?> />
					</div>

					<?php /* PRICING AREA FOR OPTIONS */ ?>
					<?php if ( $product->has_options || $product->use_advanced_optionset || $product->use_both_option_types ) { ?>
						<div class="ec_details_final_price"<?php echo ( ( isset( $atts['show_price'] ) && !$atts['show_price'] ) ) ? ' style="display:none;"' : ''; ?>><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_your_price' ); ?> <span id="ec_final_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php if( $override_price_grid > -1 ){ echo esc_attr( $GLOBALS['currency']->get_currency_display( $override_price_grid ) ); }else if( $add_price_grid > 0 ){ echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->price_options + $add_price_grid ) ); }else{ echo esc_attr( $GLOBALS['currency']->get_currency_display( $product->price_options ) ); } ?></span></div>
					<?php } ?>
					<span class="ec_details_hidden_base_price" id="ec_base_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo esc_attr( $product->price ); ?></span>

				<?php /* OUT OF STOCK INFO (NO ADD TO CART CASE) */ ?>
				<?php } else { ?>
					<div class="ec_out_of_stock"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_out_of_stock' ); ?></div>
					<?php if( get_option( 'ec_option_enable_inventory_notification' ) ){ ?>
						<div class="ec_cart_success" style="display:none;" id="ec_product_details_stock_notify_complete_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><div><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_notify_subscribe_success' ); ?></div></div>
						<div class="ec_out_of_stock_notify" id="ec_product_details_stock_notify_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
							<div class="ec_out_of_stock_notify_loader_cover" style="display:none;" id="ec_product_details_stock_notify_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_loader_cover"></div>
							<div class="ec_out_of_stock_notify_loader" style="display:none;" id="ec_product_details_stock_notify_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_loader"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
							<div class="ec_out_of_stock_notify_title"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_notify_subscribe_title' ); ?></div>
							<div class="ec_out_of_stock_notify_input">
								<div class="ec_cart_error_row" id="ec_email_notify_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>_error">
									<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_contact_information', 'cart_contact_information_email' ); ?>
								</div>
								<input type="text" id="ec_email_notify_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="" placeholder="<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_notify_subscribe_email_placeholder' ); ?>" />
							</div>

							<?php if( get_option( 'ec_option_enable_recaptcha' ) && get_option( 'ec_option_recaptcha_site_key' ) != '' ){ ?>
							<div class="ec_out_of_stock_notify_grecaptcha" style="float:left; width:100%; margin:-20px 0 5px; padding:0 15px;">
								<input type="hidden" id="ec_grecaptcha_response_product_details" name="ec_grecaptcha_response_product_details" value="" />
								<input type="hidden" id="ec_grecaptcha_site_key" value="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" />
								<div class="ec_cart_input_row" data-sitekey="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" id="ec_product_details_recaptcha"></div>
							</div>
							<?php }?>

							<div class="ec_out_of_stock_notify_button">
								<input type="button" onclick="ec_notify_submit( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>, '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-subscribe-to-stock-notification-' . (int) $product->product_id ) ); ?>' );" value="<?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_notify_subscribe_button_title' ); ?>" />
							</div>
						</div>
					<?php }?>
				<?php }?>
			</div>
		<?php } //END FILTER FOR HIDING ADD TO CART ?>

	<?php if( !$product->in_stock( ) && $product->allow_backorders ){ ?>
	<div class="ec_details_backorder_info" id="ec_back_order_info_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_out_of_stock' ); ?><?php if( $product->backorder_fill_date != "" ){ ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_backorder_until' ); ?> <?php echo esc_attr( $product->backorder_fill_date ); ?><?php }?></div>
	<?php }?>

	<input type="hidden" id="ec_allow_backorders_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->allow_backorders ); ?>" />
	<input type="hidden" id="ec_default_sku_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->model_number ); ?>" />
	<?php if ( ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) && ( ! $product->is_catalog_mode || ! get_option( 'ec_option_hide_price_seasonal' ) ) && ( ! $product->is_inquiry_mode || ! get_option( 'ec_option_hide_price_inquiry' ) ) ) { ?>
	<input type="hidden" id="ec_default_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->price ); ?>" />
	<input type="hidden" id="price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->price ); ?>" />
	<input type="hidden" id="ec_base_option_price_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->price ); ?>" />
	<?php }?>
	<input type="hidden" id="use_optionitem_images_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->use_optionitem_images ); ?>" />
	<input type="hidden" id="use_optionitem_quantity_tracking_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->use_optionitem_quantity_tracking ); ?>" />
	<input type="hidden" id="min_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->min_purchase_quantity ); ?>" />
	<input type="hidden" id="max_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $product->max_purchase_quantity ); ?>" />
	<input type="hidden" id="vat_added_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo ( isset( $vat_row ) && $vat_row->vat_added ) ? '1' : '0'; ?>" />
	<input type="hidden" id="vat_rate_multiplier_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $vat_rate_multiplier ); ?>" />
	<input type="hidden" id="currency_symbol_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_symbol( ) ); ?>" />
	<input type="hidden" id="num_decimals_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_decimal_length( ) ); ?>" />
	<input type="hidden" id="decimal_symbol_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_decimal_symbol( ) ); ?>" />
	<input type="hidden" id="grouping_symbol_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_grouping_symbol( ) ); ?>" />
	<input type="hidden" id="conversion_rate_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_conversion_rate( ) ); ?>" />
	<input type="hidden" id="symbol_location_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_symbol_location( ) ); ?>" />
	<input type="hidden" id="currency_code_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_currency_code( ) ); ?>" />
	<input type="hidden" id="show_currency_code_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $GLOBALS['currency']->get_show_currency_code( ) ); ?>" />
	<input type="hidden" id="product_details_nonce_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-product-details-' . (int) $product->product_id ) ); ?>" />
	<script>
	var tier_quantities_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> = [<?php if( !$product->using_role_price ){ for( $tier_i = 0; $tier_i < count( $product->pricetiers ); $tier_i++ ){ if( $tier_i > 0 ){ echo ","; } echo esc_attr( $product->pricetiers[$tier_i][1] ); } } ?>];
	var tier_prices_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> = [<?php if( !$product->using_role_price ){ for( $tier_i = 0; $tier_i < count( $product->pricetiers ); $tier_i++ ){ if( $tier_i > 0 ){ echo ","; } echo esc_attr( $product->pricetiers[$tier_i][0] ); } } ?>];
	var varitation_data_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> = {<?php foreach ( $product->options->variation_array as $variation_key => $variation_item ) {
		if ( isset( $variation_item->quantity ) && isset( $variation_item->sku ) && isset( $variation_item->price ) ) {
			echo '"' . esc_attr( $variation_key ) . '":{quantity:' . esc_attr( $variation_item->quantity ) . ',sku:"' . esc_attr( $variation_item->sku ) . '",price:"' . esc_attr( $variation_item->price ) . '",tracking:' . esc_attr( ( ( $variation_item->is_stock_tracking_enabled ) ? 'true' : 'false' ) ) . ',enabled:' . esc_attr( ( ( $variation_item->is_enabled ) ? 'true' : 'false' ) ) . '},';
		}
	} ?> };
	function wp_easycart_add_to_cart_js_validation_end_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( errors ){
		<?php do_action( 'wp_easycart_add_to_cart_js_validation_end', $product->product_id ); ?>
		return errors;
	}<?php if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){ ?>
	function wp_easycart_facebook_add_to_cart_track_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>( ){
		if( ec_details_add_to_cart( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ) ){
			fbq('track', 'AddToCart', {
				content_name: '<?php echo esc_attr( ucwords( strtolower( strip_tags( stripslashes( $product->title ) ) ) ) ); ?>',
				content: [{id: '<?php echo esc_attr( $product->product_id ); ?>', quantity: jQuery( document.getElementById( 'ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>' ) ).val( ), item_price: <?php echo number_format( esc_attr( $product->price ), 2, '.', '' ); ?>}],
				content_type: 'product',
				value: Number( jQuery( document.getElementById( 'ec_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>' ) ).val( ) * <?php echo number_format( esc_attr( $product->price ), 2, '.', '' ); ?> ).toFixed( 2 ),
				currency: '<?php echo esc_attr( $GLOBALS['currency']->get_currency_code( ) ); ?>'
			});
		}
	}<?php }?>
	</script>

<?php /* END ADD TO CART */ ?>
</form>

<?php if( ( $product->show_stock_quantity || $product->use_optionitem_quantity_tracking ) && $product->stock_quantity > 0 && get_option( 'ec_option_show_stock_quantity' ) ) { ?>
	<div class="ec_details_stock_total" style="display:none !important;">
		<span id="ec_details_stock_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" style="display:none !important;"><?php echo esc_attr( $product->stock_quantity ); ?></span>
	</div>
<?php }else{ ?>
	<span id="ec_details_stock_quantity_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" style="display:none !important;">10000000</span>
<?php }?>
