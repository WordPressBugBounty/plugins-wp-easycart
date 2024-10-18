<div class="ec_admin_list_line_item ec_admin_demo_data_line">

	<div class="ec_admin_settings_label">
		<div class="dashicons-before dashicons-admin-generic"></div>
		<span><?php esc_attr_e( 'Checkout Schedule Settings', 'wp-easycart' ); ?></span>
		<a href="<?php echo esc_url( wp_easycart_admin( )->helpsystem->print_docs_url( 'settings', 'checkout', 'schedule' ) );?>" target="_blank" class="ec_help_icon_link">
			<div class="dashicons-before ec_help_icon dashicons-info"></div>  <?php esc_attr_e( 'Help', 'wp-easycart' ); ?>
		</a>
		<?php wp_easycart_admin( )->helpsystem->print_vids_url( 'settings', 'checkout', 'schedule' ); ?>
	</div>
	<div class="ec_admin_settings_input ec_admin_settings_live_payment_section wp_easycart_admin_no_padding">
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_restaurant_allow_scheduling', 'ec_admin_save_cart_settings_options', get_option( 'ec_option_restaurant_allow_scheduling' ), __( 'Restaurants: Allow Scheduling', 'wp-easycart' ), __( 'Enable to allow customers to schedule a restaurant order.', 'wp-easycart' ) ); ?>

		<?php $restaurant_schedule_ranges = array(
			(object) array(
				'value'	=> 5,
				'label'	=> 5 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
			(object) array(
				'value'	=> 10,
				'label'	=> 10 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
			(object) array(
				'value'	=> 15,
				'label'	=> 15 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
			(object) array(
				'value'	=> 20,
				'label'	=> 20 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
			(object) array(
				'value'	=> 30,
				'label'	=> 30 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
			(object) array(
				'value'	=> 60,
				'label'	=> 60 . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			),
		); ?>
		<?php wp_easycart_admin( )->load_toggle_group_select( 'ec_option_restaurant_schedule_range', 'ec_admin_save_checkout_text_setting', get_option( 'ec_option_restaurant_schedule_range' ), __( 'Retaurant Orders: Scheduling Range', 'wp-easycart-pro' ), __( 'The difference in minutes between possible scheduling times.', 'wp-easycart-pro' ), $restaurant_schedule_ranges, 'ec_option_restaurant_pickup_asap_length_row' ); ?>

		<?php $restaurant_pickup_times = array();
		for ( $i = 5; $i <= 90; $i=$i+5 ) {
			$restaurant_pickup_times[] = (object) array(
				'value'	=> $i,
				'label'	=> $i . ' ' . esc_attr__( 'Minutes', 'wp-easycart-pro' ),
			);
		} ?>
		<?php wp_easycart_admin( )->load_toggle_group_select( 'ec_option_restaurant_pickup_asap_length', 'ec_admin_save_checkout_text_setting', get_option( 'ec_option_restaurant_pickup_asap_length' ), __( 'Retaurant Orders: Expected Prep Time', 'wp-easycart-pro' ), __( 'This is the amount of time from order to pickup for ASAP restaurant orders.', 'wp-easycart-pro' ), $restaurant_pickup_times, 'ec_option_restaurant_pickup_asap_length_row' ); ?>

		<?php wp_easycart_admin( )->load_toggle_group_textarea( 'ec_option_shedule_pickup_preorder', 'ec_admin_save_checkout_text_setting', get_option( 'ec_option_shedule_pickup_preorder' ), __( 'Cart Schedule: Preorder for Pickup Instructions', 'wp-easycart' ), __( 'This applies to preorder for pickup products only and shows in the box where the customer chooses a pickup date.', 'wp-easycart' ), __( 'Enter a customer message', 'wp-easycart' ), 'ec_option_shedule_pickup_preorder_row' ); ?>

		<?php wp_easycart_admin( )->load_toggle_group_textarea( 'ec_option_shedule_pickup_restaurant', 'ec_admin_save_checkout_text_setting', get_option( 'ec_option_shedule_pickup_restaurant' ), __( 'Cart Schedule: Restaurant Pickup Instructions', 'wp-easycart' ), __( 'This applies to restaurant style products only and shows in the box where the customer chooses their order pickup time.', 'wp-easycart' ), __( 'Enter a customer message', 'wp-easycart' ), 'ec_option_shedule_pickup_restaurant_row' ); ?>
	</div>
</div>