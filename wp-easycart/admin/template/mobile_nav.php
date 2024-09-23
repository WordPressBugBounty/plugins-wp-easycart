<div class="ec_admin_mobile_menu" id="ec_admin_mobile_menu_main">
	<div class="ec_admin_mobile_menu_inner">
		<div>
			<div class="ec_admin_mobile_menu_close">
				<a href="#" onclick="ec_admin_hide_mobile_menu( ); return false;"><div class="dashicons-before dashicons-no"></div></a>
			</div>
			<ul>
				<li>
					<a href="admin.php?page=wp-easycart-dashboard">
						<span class="dashicons dashicons-analytics"></span> <?php esc_attr_e( 'Reports', 'wp-easycart' ); ?>
					</a>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-license-status">
						<span class="dashicons dashicons-warning"></span> <?php esc_attr_e( 'Store Status', 'wp-easycart' ); ?>
					</a>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-products&subpage=products" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-store"></span>
						<?php esc_attr_e( 'Products', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-products&subpage=products"><?php esc_attr_e( 'Products', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=inventory"><?php esc_attr_e( 'Inventory', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=option"><?php esc_attr_e( 'Option Sets', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=category"><?php esc_attr_e( 'Categories', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=menus"><?php esc_attr_e( 'Menus', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=manufacturers"><?php esc_attr_e( 'Manufacturers', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=reviews"><?php esc_attr_e( 'Product Reviews', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-products&subpage=subscriptionplans"><?php esc_attr_e( 'Subscription Plans', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-orders&subpage=orders" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-cart"></span> <?php esc_attr_e( 'Orders', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-orders&subpage=orders"><?php esc_attr_e( 'Orders', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-orders&subpage=subscriptions"><?php esc_attr_e( 'Subscriptions', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-orders&subpage=downloads"><?php esc_attr_e( 'Manage Downloads', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-users&subpage=accounts" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-admin-users"></span> <?php esc_attr_e( 'Users', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-users&subpage=accounts"><?php esc_attr_e( 'User Accounts', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-users&subpage=user-roles"><?php esc_attr_e( 'User Roles', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-users&subpage=subscribers"><?php esc_attr_e( 'Subscribers', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-rates&subpage=coupons" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-money-alt"></span> <?php esc_attr_e( 'Marketing', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-rates&subpage=coupons"><?php esc_attr_e( 'Coupons', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-rates&subpage=promotions"><?php esc_attr_e( 'Promotions', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-rates&subpage=gift-cards"><?php esc_attr_e( 'Gift Cards', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-rates&subpage=abandon-cart"><?php esc_attr_e( 'Abandoned Cart', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-settings" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-admin-tools"></span> <?php esc_attr_e( 'Basic Settings', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-settings"><?php esc_attr_e( 'Initial Setup', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=products"><?php esc_attr_e( 'Products', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=checkout"><?php esc_attr_e( 'Checkout', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=account"><?php esc_attr_e( 'Accounts', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=payment"><?php esc_attr_e( 'Payment', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=tax"><?php esc_attr_e( 'Taxes', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=fee"><?php esc_attr_e( 'Flex-Fees', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=shipping-settings"><?php esc_attr_e( 'Shipping Settings', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=shipping-rates"><?php esc_attr_e( 'Shipping Rates', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-settings" onclick="ec_admin_toggle_mobile_submenu( jQuery( this ).parent() ); return false;">
						<span class="dashicons dashicons-hammer"></span> <?php esc_attr_e( 'Advanced Settings', 'wp-easycart' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</a>
					<ul>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=miscellaneous"><?php esc_attr_e( 'Additional Settings', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=design"><?php esc_attr_e( 'Design', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=language-editor"><?php esc_attr_e( 'Language Editor', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=email-setup"><?php esc_attr_e( 'Email Setup', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=country"><?php esc_attr_e( 'Countries', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=states"><?php esc_attr_e( 'States/Territories', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=perpage"><?php esc_attr_e( 'Per Page Options', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=pricepoint"><?php esc_attr_e( 'Price Points', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=third-party"><?php esc_attr_e( 'Third Party', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=cart-importer"><?php esc_attr_e( 'Cart Importer', 'wp-easycart' ); ?></a></li>
						<li><a href="admin.php?page=wp-easycart-settings&subpage=logs"><?php esc_attr_e( 'Log Entries', 'wp-easycart' ); ?></a></li>
					</ul>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-status&subpage=store-status">
						<span class="dashicons dashicons-sos"></span> <?php esc_attr_e( 'Diagnostics', 'wp-easycart' ); ?>
					</a>
				</li>
				<li>
					<a href="admin.php?page=wp-easycart-registration&subpage=registration">
						<span class="dashicons dashicons-plugins-checked"></span> <?php esc_attr_e( 'Registration', 'wp-easycart' ); ?>
					</a>
				</li>
			</ul>
		</div>
	</div>
</div>
