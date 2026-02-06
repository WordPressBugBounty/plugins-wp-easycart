<?php
	global $wpdb;
	$order_status = $wpdb->get_results( "SELECT ec_orderstatus.status_id AS value, ec_orderstatus.order_status AS label, ec_orderstatus.is_approved FROM ec_orderstatus ORDER BY status_id ASC" );
?>
<div class="ec_admin_slideout_container" id="order_duplicate_box" style="z-index:1028;">
	<div class="ec_admin_slideout_container_content">
		<?php wp_easycart_admin( )->preloader->print_preloader( "ec_admin_order_duplicate_display_loader" ); ?>
		<input type="hidden" id="wp_easycart_order_duplicate_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-order-duplicate' ) ); ?>" />
		<header class="ec_admin_slideout_container_content_header">
			<div class="ec_admin_slideout_container_content_header_inner">
				<h3><?php esc_attr_e( 'Order Duplicate', 'wp-easycart' ); ?></h3>
				<div class="ec_admin_slideout_close" onclick="wp_easycart_admin_close_slideout( 'order_duplicate_box' );">
					<div class="dashicons-before dashicons-no-alt"></div>
				</div>
			</div>
		</header>
		<div class="ec_admin_slideout_container_content_inner">
			<div class="ec_admin_slideout_container_notice">
				<?php esc_attr_e( 'Important Note: Duplicating an order does not automatically charge the customer, you must collect any additional payment from the customer separately. Inventory is also not removed for duplicate orders, please manage this as it applies to your situation.', 'wp-easycart' ); ?>
			</div>
			<div class="ec_admin_slideout_container_input_row">
				<div class="ec_admin_slideout_container_simple_row">
					<strong><?php esc_attr_e( 'Duplicate Order', 'wp-easycart' ); ?> #<span id="ec_dup_order_id"></span></strong>
				</div>
				<div class="ec_admin_slideout_container_simple_row">
					<strong><?php esc_attr_e( 'Shipping Address', 'wp-easycart' ); ?>:</strong><br /><br />
					<span id="ec_dup_order_shipping_address"></span>
					<hr>
					<strong><?php esc_attr_e( 'Items', 'wp-easycart' ); ?>:</strong><br /><br />
					<span id="ec_dup_order_items"></span>
				</div>
			</div>
			<div class="ec_admin_slideout_container_input_row">
				<label for="ec_dup_order_status"><?php esc_attr_e( 'Order Status', 'wp-easycart' ); ?></label>
				<div>
					<select id="ec_dup_order_status" name="ec_dup_order_status" class="select2-basic" data-partial-refund="<?php echo esc_attr__( 'Partial Refund', 'wp-easycart' ); ?>" data-refunded="<?php echo esc_attr__( 'Refunded', 'wp-easycart' ); ?>" data-paid="<?php echo esc_attr__( 'Paid', 'wp-easycart' ); ?>" data-cancelled="<?php echo esc_attr__( 'Canceled', 'wp-easycart' ); ?>" data-failed="<?php echo esc_attr__( 'Failed', 'wp-easycart' ); ?>" data-pending="<?php echo esc_attr__( 'Processing', 'wp-easycart' ); ?>">
						<?php foreach( $order_status as $status ){ ?>
						<option value="<?php echo esc_attr( $status->value ); ?>" isapproved="<?php echo esc_attr( $status->is_approved ); ?>"><?php echo esc_attr( $status->label ); ?></option>
						<?php }?>
					</select>
				</div>
			</div>
		</div>
		<footer class="ec_admin_slideout_container_content_footer">
			<div class="ec_admin_slideout_container_content_footer_inner">
				<div class="ec_admin_slideout_container_content_footer_inner_body">
					<ul>
						<li class="ec_admin_mobile_hide">
							<button onclick="ec_admin_cancel_order_duplicate( );">
								<span><?php esc_attr_e( 'Cancel', 'wp-easycart' ); ?></span>
							</button>
						</li>
						<li>
							<button onclick="ec_admin_complete_order_duplicate( );">
								<span><?php esc_attr_e( 'Create Duplicate', 'wp-easycart' ); ?></span>
							</button>
						</li>
					</ul>
				</div>
			</div>
		</footer>
	</div>
</div>
<script>jQuery( document.getElementById( 'order_duplicate_box' ) ).appendTo( document.body );</script>