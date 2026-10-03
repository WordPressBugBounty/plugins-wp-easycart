<?php
/**
 * One-page checkout: preorder and restaurant pickup ( date, time and store location ), as the classic payment step shows
 * them ( ec_cart_payment.php ). Included by ec_cart_payment_v2.php with $cartpage. Copy this file to your
 * wp-easycart-data layout folder to change it; the file name must stay the same.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $cartpage ) ) {
	$cartpage = $this;
}
?>
<?php if ( $cartpage->cart->has_preorder_items() ) { // For preorder pickup items ?>
<div class="ec_cart_header ec_top">
	<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_title' ) ); ?>
</div>
<div class="ec_cart_subtitle_info">
	<div class="ec_cart_subtitle_info_content"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_subtitle' ) ); ?></div>
	<div class="ec_cart_pickup_group">
		<div class="ec_cart_pickup_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_items' ) ); ?></div>
		<div class="ec_cart_pickup_items">
			<ul>
				<?php foreach ( $cartpage->cart->cart as $cart_item ) { ?>
					<?php if ( $cart_item->is_preorder_type ) { ?>
				<li><?php echo esc_attr( $cart_item->quantity . ' x ' . $cart_item->title ); ?></li>
					<?php }?>
				<?php }?>
			</ul>
		</div>
	</div>
	<div class="ec_cart_error_row" id="ec_preorder_pickup_error">
		<?php echo wp_kses_post( wp_easycart_language()->get_text( 'ec_errors', 'missing_preorder_pickup' ) ) ?> 
	</div>
	<?php if ( get_option( 'ec_option_pickup_enable_locations' ) ) { ?>
	<?php wp_easycart_output_location_popup(); ?>
	<div class="ec_cart_pickup_group">
		<div class="ec_cart_pickup_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_location' ) ); ?></div>
		<div class="ec_cart_pickup_items">
			<?php
			/* 6.0.2: a location deleted since it was chosen reads as no choice ( it used to read a property of null ). */
			$selected_location = null;
			if ( $GLOBALS['ec_cart_data']->cart_data->pickup_location ) {
				global $wpdb;
				$selected_location = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_location WHERE location_id = %d', (int) $GLOBALS['ec_cart_data']->cart_data->pickup_location ) );
			}
			?>
			<?php if ( is_object( $selected_location ) ) { ?>
				<button class="ec_product_select_location ec_product_select_location_product" type="button" data-type="cart"><p><?php echo esc_html( $selected_location->location_label ); ?>, <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'change_location' ) ); ?></p></button>
			<?php } else { ?>
				<button class="ec_product_select_location ec_product_select_location_product" type="button" data-type="cart"><p><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'choose_location' ) ); ?></p></button>
			<?php }?>
		</div>
	</div>
	<?php }?>
	<div class="ec_cart_pickup_group">
		<?php /* 6.0.2: a real label for the date box, and a name for the time list ( screen readers ). */ ?>
		<label class="ec_cart_pickup_label" for="preorder_pickup_date"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_date' ) ); ?></label>
		<div class="ec_cart_pickup_items">
			<input type="text" name="preorder_pickup_date" id="preorder_pickup_date" autocomplete="off" value="<?php echo esc_attr( ( isset( $GLOBALS['ec_cart_data']->cart_data->pickup_date ) && '' != $GLOBALS['ec_cart_data']->cart_data->pickup_date ) ? date_i18n( apply_filters( 'wp_easycart_pickup_date_placeholder_format', 'F d, Y' ), strtotime( $GLOBALS['ec_cart_data']->cart_data->pickup_date ) ) : '' ); ?>" placeholder="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_date_label' ) ) ); ?>" aria-describedby="ec_preorder_pickup_error" />
			<select name="preorder_pickup_time" id="preorder_pickup_time" style="margin-left:5px;" aria-label="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ) ) ); ?>"><?php $selected_pickup_date_time = ''; if ( isset( $GLOBALS['ec_cart_data']->cart_data->pickup_date ) && '' != $GLOBALS['ec_cart_data']->cart_data->pickup_date ) { $selected_pickup_date_time = date( 'H:i', strtotime( $GLOBALS['ec_cart_data']->cart_data->pickup_date ) ); } ?>
				<option value=""<?php if ( '' == $selected_pickup_date_time ) { ?> selected="selected"<?php }?>><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_time_label' ) ); ?></option>
				<?php for ( $hour = 0; $hour < 24; $hour++ ) { ?>
				<option value="<?php echo esc_attr( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?>"<?php if ( date( 'H:i', strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) == $selected_pickup_date_time ) { ?> selected="selected"<?php }?>><?php echo esc_attr( date( get_option('time_format'), strtotime( date( 'Y-m-d ' . $hour . ':00' ) ) ) ); ?> - <?php echo esc_attr( date( get_option('time_format'), strtotime( date( 'Y-m-d ' . $hour . ':00' ) . ' + 1 hour' ) ) ); ?></option>
				<?php } ?>
			</select>
			<script>
				var pickup_selected_date = '';
				var pickup_rules = <?php echo wp_json_encode( $cartpage->cart->get_preorder_schedule() ); ?>;
				function wp_easycart_pickup_disable_invalid_dates( date ) {
					var day = date.getDay(); 
					var today = new Date();
					var minTime = 0;
					var maxTime = 0;
					var formattedDate = jQuery.datepicker.formatDate( 'yy-mm-dd', date );
					if ( pickup_rules.holidays.hasOwnProperty( formattedDate ) ) {
						var holidayRule = pickup_rules.holidays[ formattedDate ];
						if ( typeof holidayRule !== 'undefined' && '1' == holidayRule.is_closed ) {
							return [false, ''];
						} else if ( typeof holidayRule !== 'undefined' ) {
							minTime = holidayRule.min;
							maxTime = holidayRule.max;
						}
					} else {
						var dayRule = pickup_rules[ Object.keys( pickup_rules )[ day ] ];
						if ( typeof dayRule !== 'undefined' && '1' == dayRule.is_closed ) {
							return [false, ''];
						} else if ( typeof dayRule !== 'undefined' ) {
							minTime = dayRule.min;
							maxTime = dayRule.max;
						}
					}
					var minDateTime = new Date( date.getTime() - minTime * 60000 );
					var maxDateTime = new Date( date.getTime() - maxTime * 60000 );
					return [
						( today >= minDateTime && today <= maxDateTime ),
						''
					];
				}
				function wp_easycart_update_pickup_time_box( date, reset ) {
					if ( date ) {
						var open = '00:00';
						var close = '24:00';
						var day = date.getDay(); 
						var today = new Date();
						today.setHours(0, 0, 0, 0);
						var formattedDate = jQuery.datepicker.formatDate( 'yy-mm-dd', date );
						if ( pickup_rules.holidays.hasOwnProperty( formattedDate ) ) {
							var holidayRule = pickup_rules.holidays[ formattedDate ];
							open = holidayRule.open;
							close = holidayRule.close;
						} else {
							var dayRule = pickup_rules[ Object.keys( pickup_rules )[ day ] ];
							open = dayRule.open;
							close = dayRule.close;
						}
						if ( pickup_selected_date != jQuery( '#preorder_pickup_date' ).val() ) {
							pickup_selected_date = jQuery( '#preorder_pickup_date' ).val();
							var open_found = false;
							var close_found = false;
							jQuery( '#preorder_pickup_time > option' ).attr( 'disabled', 'disabled' ).hide();
							jQuery( '#preorder_pickup_time > option[value=""]' ).removeAttr( 'disabled' ).show();
							if ( reset ) {
								jQuery( '#preorder_pickup_time' ).val( '' );
							}
							jQuery( '#preorder_pickup_time > option' ).each( function() {
								if ( jQuery( this ).attr( 'value' ) == open ) {
									open_found = true;
								}
								if ( jQuery( this ).attr( 'value' ) == close ) {
									close_found = true;
								}
								if ( open_found && ! close_found ) {
									jQuery( this ).removeAttr( 'disabled' ).show();
								} else if ( jQuery( this ).attr( 'selected' ) ) {
									jQuery( '#preorder_pickup_time' ).val( '' );
								}
							} );
						}
					} else {
						jQuery( '#preorder_pickup_time > option' ).attr( 'disabled', 'disabled' ).hide();
						jQuery( '#preorder_pickup_time > option[value=""]' ).removeAttr( 'disabled' ).show();
						jQuery( '#preorder_pickup_time' ).val( '' );
					}
				}
				jQuery( '#preorder_pickup_date' ).datepicker( {
					beforeShowDay: wp_easycart_pickup_disable_invalid_dates,
					onSelect: function( dateText, inst ) {
						wp_easycart_update_pickup_time_box( jQuery( this ).datepicker( 'getDate' ), true );
						ec_cart_save_pickup_date_time();
					},
					onClose: function( dateText, inst ) {
						var selectedDate = jQuery( this ).datepicker( 'getDate' );
						var is_valid_date = ( selectedDate ) ? wp_easycart_pickup_disable_invalid_dates( selectedDate ) : [ false ];
						if ( selectedDate && ! is_valid_date[0] ) {
							jQuery( this ).val( '' );
							wp_easycart_update_pickup_time_box( selectedDate, true );
						}
					},
					minDate: 0,
					dateFormat: "<?php echo esc_attr( apply_filters( 'wp_easycart_pickup_date_jquery_format', 'MM d, yy' ) ); ?>",<?php $selected_date = explode( '-', $GLOBALS['ec_cart_data']->cart_data->pickup_date ); if ( is_array( $selected_date ) && count( $selected_date ) == 3 ) { ?>
					defaultDate: new Date( <?php echo esc_attr( (int) $selected_date[0] ); ?>, <?php echo esc_attr( (int) $selected_date[1] ); ?>, <?php echo esc_attr( (int) $selected_date[2] ); ?> )<?php }?>
				} );
				wp_easycart_update_pickup_time_box( jQuery( '#preorder_pickup_date' ).datepicker( 'getDate' ), false );
			</script>
		</div>
	</div>
</div>
<?php if ( get_option( 'ec_option_shedule_pickup_preorder' ) && '' != get_option( 'ec_option_shedule_pickup_preorder' ) ) { ?>
<div class="ec_cart_header">
	<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'preorder_pickup_title' ) ); ?>
</div>
<div class="ec_cart_subtitle_info">
	<?php echo wp_easycart_escape_html( get_option( 'ec_option_shedule_pickup_preorder' ) ); ?>
</div>
<?php } ?>
<?php } ?>

<?php if ( $cartpage->cart->has_restaurant_items() ) {
	$restaurant_hours = $cartpage->cart->get_restaurant_hours();
	$start_time = ( ( $restaurant_hours->start_hour > $restaurant_hours->now_hour ) ? $restaurant_hours->start_hour : $restaurant_hours->now_hour ) . ':' . ( ( $restaurant_hours->now_hour < $restaurant_hours->start_hour ) ? $restaurant_hours->start_minute :  $restaurant_hours->now_minute );
	$now_timestamp = time();
	$end_timestamp = strtotime( $restaurant_hours->end_hour . ':' . $restaurant_hours->end_minute );
	$start_time_timestamp = strtotime( $start_time );
	$start_time_adjusted_timestamp = $start_time_timestamp + ( 60 * (int) get_option( 'ec_option_restaurant_pickup_asap_length' ) );
	$start_min = (int) date( 'i', $start_time_adjusted_timestamp );
	$start_min_rounded = ( ceil( $start_min / (int) get_option( 'ec_option_restaurant_schedule_range' ) ) * (int) get_option( 'ec_option_restaurant_schedule_range' ) ) % 60;
	$start_min_adjusted = ( $start_min_rounded - $start_min ) * 60; 
	$start_time_adjusted_timestamp_2 = $start_time_adjusted_timestamp + $start_min_adjusted;
	$start_hour = (int) date( 'G', $start_time_adjusted_timestamp_2 );
?>
<div class="ec_cart_header ec_top">
	<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_title' ) ); ?><?php if ( $end_timestamp - $now_timestamp < 60 * 60 && $end_timestamp - $now_timestamp > 0 ) { ?> <div class="ec_cart_restaurant_timer"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_closes' ) ); ?> <span class="ec_cart_restaurant_timer_min"><?php echo esc_attr( floor( ( $end_timestamp - $now_timestamp ) / 60 ) ); ?></span>:<span class="ec_cart_restaurant_timer_sec"><?php echo esc_attr( sprintf( '%02d', ( ( $end_timestamp - $now_timestamp ) % 60 ) ) ); ?></span><script>ec_cart_restaurant_start_timer();</script></div><?php }?>
</div>
<div class="ec_cart_subtitle_info">
	<div class="ec_cart_subtitle_info_content"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_subtitle' ) ); ?></div>
	<div class="ec_cart_pickup_group">
		<div class="ec_cart_pickup_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_items' ) ); ?></div>
		<div class="ec_cart_pickup_items">
			<ul>
				<?php foreach ( $cartpage->cart->cart as $cart_item ) { ?>
					<?php if ( $cart_item->is_restaurant_type ) { ?>
				<li><?php echo esc_attr( $cart_item->quantity . ' x ' . $cart_item->title ); ?></li>
					<?php }?>
				<?php }?>
			</ul>
		</div>
	</div>
	<div class="ec_cart_pickup_group">
		<div class="ec_cart_pickup_label"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_time' ) ); ?></div>
		<div class="ec_cart_pickup_items">
			<?php if ( $cartpage->cart->is_restaurant_open() ) { ?>
			<ul>
				<li><input type="radio" value="ASAP" name="restaurant_pickup_time" id="restaurant_pickup_time_asap"<?php if ( isset( $GLOBALS['ec_cart_data']->cart_data->pickup_asap ) && 1 == $GLOBALS['ec_cart_data']->cart_data->pickup_asap ) { ?> checked="checked"<?php }?> /> <label for="restaurant_pickup_time_asap"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'pickup_asap' ) ); ?></label></li>
				<?php if ( get_option( 'ec_option_restaurant_allow_scheduling' ) && ( $start_hour < $restaurant_hours->end_hour || ( $start_hour == $restaurant_hours->end_hour && $start_min_rounded <= $restaurant_hours->end_minute ) ) ) { ?>
				<li>
					<input type="radio" value="schedule" name="restaurant_pickup_time" id="restaurant_pickup_time_schedule"<?php if ( isset( $GLOBALS['ec_cart_data']->cart_data->pickup_asap ) && 0 == $GLOBALS['ec_cart_data']->cart_data->pickup_asap ) { ?> checked="checked"<?php }?> /> 
					<label for="restaurant_pickup_time_schedule"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'pickup_schedule' ) ); ?></label>
					<select name="restaurant_pickup_time" id="restaurant_pickup_time" aria-label="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_time' ) ) ); /* 6.0.2 */ ?>">
						<?php
						for ( $hour = $start_hour; $hour <= $restaurant_hours->end_hour; $hour++ ) {
							for ( $minute = ( ( $hour == $start_hour ) ? $start_min_rounded : 0 ); $minute <= ( ( $hour < $restaurant_hours->end_hour ) ? 59 : $restaurant_hours->end_minute ); $minute += (int) get_option( 'ec_option_restaurant_schedule_range' ) ) {?>
						<option value="<?php echo esc_attr( $hour . ':' . $minute ); ?>"<?php if ( $hour . ':' . $minute == $GLOBALS['ec_cart_data']->cart_data->pickup_time ) { ?> selected="selected"<?php }?>><?php echo esc_attr( date( get_option('time_format'), strtotime( date( 'Y-m-d ' . $hour . ':' . $minute ) ) ) ); ?></option>
						<?php }
						}
						?>
					</select>
				</li>
				<?php } ?>
			</ul>
			<?php } else { ?>
			<div class="ec_cart_pickup_closed"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_closed' ) ); ?></div>
			<?php }?>
		</div>
	</div>
</div>
<?php if ( $cartpage->cart->is_restaurant_open() && get_option( 'ec_option_shedule_pickup_restaurant' ) && '' != get_option( 'ec_option_shedule_pickup_restaurant' ) ) { ?>
<div class="ec_cart_header">
	<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'restaurant_pickup_title' ) ); ?>
</div>
<div class="ec_cart_subtitle_info">
	<?php echo wp_easycart_escape_html( get_option( 'ec_option_shedule_pickup_restaurant' ) ); ?>
</div>
<?php } ?>
<?php } ?>
