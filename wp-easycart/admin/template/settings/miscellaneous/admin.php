<div class="ec_admin_list_line_item ec_admin_demo_data_line">
	<div class="ec_admin_settings_label">
		<div class="dashicons-before dashicons-admin-tools"></div>
		<span><?php esc_attr_e( 'Product Quick Add Options', 'wp-easycart' ); ?></span>
		<a href="<?php echo esc_url_raw( wp_easycart_admin( )->helpsystem->print_docs_url( 'settings', 'additional-settings', 'admin-options' ) ); ?>" target="_blank" class="ec_help_icon_link">
			<div class="dashicons-before ec_help_icon dashicons-info"></div> <?php esc_attr_e( 'Help', 'wp-easycart' ); ?>
		</a>
		<?php wp_easycart_admin( )->helpsystem->print_vids_url('settings', 'additional-settings', 'admin-options');?>
	</div>
	<div class="ec_admin_settings_input ec_admin_settings_live_payment_section">
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_product_show_stock_option', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_product_show_stock_option' ), __( 'Show Stock Options', 'wp-easycart' ), __( 'Enable to show stock options on the product quick creation panel in the admin.', 'wp-easycart' ) ); ?>
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_product_show_shipping_option', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_product_show_shipping_option' ), __( 'Show Shipping Options', 'wp-easycart' ), __( 'Enable to show shipping options on the product quick creation panel in the admin.', 'wp-easycart' ) ); ?>
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_product_show_tax_option', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_product_show_tax_option' ), __( 'Show Tax Options', 'wp-easycart' ), __( 'Enable to show tax options on the product quick creation panel in the admin.', 'wp-easycart' ) ); ?>
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_product_show_variant_option', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_product_show_variant_option' ), __( 'Show Variants', 'wp-easycart' ), __( 'Enable to show variant options on the product quick creation panel in the admin.', 'wp-easycart' ) ); ?>
	</div>
</div>

<div class="ec_admin_list_line_item ec_admin_demo_data_line">
	<div class="ec_admin_settings_label">
		<div class="dashicons-before dashicons-rss"></div>
		<span><?php esc_attr_e( 'Admin Apps Options (Premium Only)', 'wp-easycart' ); ?></span>
		<a href="<?php echo esc_url_raw( wp_easycart_admin( )->helpsystem->print_docs_url( 'settings', 'additional-settings', 'admin-options' ) ); ?>" target="_blank" class="ec_help_icon_link">
			<div class="dashicons-before ec_help_icon dashicons-info"></div> <?php esc_attr_e( 'Help', 'wp-easycart' ); ?>
		</a>
		<?php wp_easycart_admin( )->helpsystem->print_vids_url('settings', 'additional-settings', 'admin-options'); ?>
	</div>
	<div class="ec_admin_settings_input ec_admin_settings_live_payment_section">
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_enable_push_notifications', 'ec_admin_save_additional_options', get_option( 'ec_option_enable_push_notifications' ), __( 'App Notifications', 'wp-easycart' ), __( 'Enable to receive push notifications on your WP EasyCart apps (must have push notifications enabled!).', 'wp-easycart' ) ); ?>
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_enable_legacy_app_auth', 'ec_admin_save_additional_options', get_option( 'ec_option_enable_legacy_app_auth', '1' ), __( 'Legacy App Login (v1)', 'wp-easycart' ), __( 'Allow sign-in from older versions of the WP EasyCart mobile apps. Once all of your devices run the latest app version, disable this for improved security. Disabling immediately removes the stored legacy login data and blocks outdated apps; re-enabling requires each admin to sign in again (on the website or in the new app) before older apps will work.', 'wp-easycart' ) ); ?>
	</div>
</div>

<div class="ec_admin_list_line_item ec_admin_demo_data_line">
	<div class="ec_admin_settings_label">
		<div class="dashicons-before dashicons-admin-tools"></div>
		<span><?php esc_attr_e( 'Additional Admin Options', 'wp-easycart' ); ?></span>
		<a href="<?php echo esc_url_raw( wp_easycart_admin( )->helpsystem->print_docs_url( 'settings', 'additional-settings', 'admin-options' ) ); ?>" target="_blank" class="ec_help_icon_link">
			<div class="dashicons-before ec_help_icon dashicons-info"></div> <?php esc_attr_e( 'Help', 'wp-easycart' ); ?>
		</a>
		<?php wp_easycart_admin( )->helpsystem->print_vids_url('settings', 'additional-settings', 'admin-options'); ?>
	</div>
	<div class="ec_admin_settings_input ec_admin_settings_live_payment_section">
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_orders_list_enable_pickup_date', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_orders_list_enable_pickup_date' ), __( 'Order List: Enable Preorder Pickup Date Display', 'wp-easycart' ), __( 'This will add a column in the order list for the preorder pickup date.', 'wp-easycart' ) ); ?>
		<?php wp_easycart_admin( )->load_toggle_group( 'ec_option_admin_orders_list_enable_pickup_time', 'ec_admin_save_additional_options', get_option( 'ec_option_admin_orders_list_enable_pickup_time' ), __( 'Order List: Enable Restaurant Pickup Time Display', 'wp-easycart' ), __( 'This will add a column in the order list for the restaurant style orders and expected pickup time.', 'wp-easycart' ) ); ?>
		<?php
		$perpage_default = (int) get_option( 'ec_option_admin_default_perpage' );
		if ( ! in_array( $perpage_default, array( 10, 25, 50, 100, 250, 500 ), true ) ) {
			$perpage_default = 25;
		}
		$perpage_options = array();
		foreach ( array( 10, 25, 50, 100, 250, 500 ) as $perpage_value ) {
			$perpage_options[] = (object) array(
				'value' => $perpage_value,
				'label' => sprintf( __( '%d per page', 'wp-easycart' ), $perpage_value ),
			);
		}
		?>
		<?php wp_easycart_admin( )->load_toggle_group_select( 'ec_option_admin_default_perpage', 'ec_admin_save_additional_text_options', $perpage_default, __( 'Admin Lists: Default Records Per Page', 'wp-easycart' ), __( 'Default number of records shown on admin list pages (products, orders, users, etc). Per-page choices made on each list are remembered per admin and per list, and will override this default.', 'wp-easycart' ), $perpage_options, 'ec_option_admin_default_perpage_row', true, false, false, true ); ?>
		<?php do_action( 'wp_easycart_additional_admin_options' ); ?>
	</div>
</div>
