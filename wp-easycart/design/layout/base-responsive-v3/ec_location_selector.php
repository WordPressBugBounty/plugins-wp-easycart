<div class="wpeasycart-location-popup" style="display:none;">
	<div class="wpeasycart-location-popup-modal-content">
		<button class="wpeasycart-location-popup-modal-close-btn">&times;</button>
		<h2><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_title' ); ?></h2>
		<div class="wpeasycart-location-popup-location-search-area">
			<?php if ( get_option( 'ec_option_pickup_location_google_site_key' ) && '' != get_option( 'ec_option_pickup_location_google_site_key' ) ) { ?>
			<label for="location-input"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_location_label' ); ?></label>
			<div class="wpeasycart-location-popup-input-group">
				<input type="text" id="wpeasycart_location_input" placeholder="<?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_placeholder' ); ?>">
				<button class="wpeasycart-location-popup-find-btn"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_find_stores' ); ?></button>
			</div>
			<?php }?>
			<button class="wpeasycart-location-popup-use-location-btn">
				<i class="fas fa-location-arrow"></i> <?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_current_location' ); ?>
			</button>
			<div class="wp-easycart-location-popup-error error1" style="display:none;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_error1' ); ?></div>
			<div class="wp-easycart-location-popup-error error2" style="display:none;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_error2' ); ?></div>
			<div class="wp-easycart-location-popup-error error3" style="display:none;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_error3' ); ?></div>
			<div class="wp-easycart-location-popup-error error4" style="display:none;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_error4' ); ?></div>
			<div class="wp-easycart-location-popup-error error5" style="display:none;"><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_error5' ); ?></div>
		</div>
		<hr class="wpeasycart-location-popup-divider">
		<div class="wpeasycart-location-popup-store-results">
			<h3><?php echo wp_easycart_language( )->get_text( 'product_details', 'store_select_nearby_stores' ); ?></h3>
			<div class="wpeasycart-location-list-loader" style="display:none;"><div></div><div></div><div></div><div></div></div>
			<div class="wpeasycart-location-list"></div>
		</div>
		<input type="hidden" id="wpeasycart_location_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-location' ) ); ?>" />
	</div>
</div>