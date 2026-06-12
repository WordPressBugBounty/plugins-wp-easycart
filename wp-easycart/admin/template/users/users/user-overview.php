<?php
/**
 * Customer Snapshot — Free / Upsell Version
 *
 * Shown at the top of the user-details screen when editing an existing customer
 * on non-licensed installs. The header row (name, email, quick actions) is live
 * data and fully functional. Everything below it is a static, blurred mockup
 * with an overlay that opens the standard PRO upsell popup
 * (#ec_admin_upsell_popup via show_pro_required()).
 *
 * The licensed PRO plugin replaces this entire file via the
 * 'wp_easycart_admin_user_overview_file' filter.
 *
 * Expected context ($this = wp_easycart_admin_details_user):
 *   $this->user, $this->billing_info
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$full_name     = trim( $this->user->first_name . ' ' . $this->user->last_name );
$billing_phone = ( isset( $this->billing_info->phone ) ) ? $this->billing_info->phone : '';
$orders_link   = 'admin.php?page=wp-easycart-orders&subpage=orders&filter_2=' . (int) $this->user->user_id;

/*
 * PRO gate (same system as the product table, see wp_easycart_admin_pro_gate).
 * If PRO 5.8.17+ is licensed, this template never renders (the PRO plugin
 * replaces it via the 'wp_easycart_admin_user_overview_file' filter), so by
 * the time we get here the gate resolves to a locked state: either no
 * PRO / unlicensed (upsell) or a licensed PRO older than 5.8.17 (update).
 */
$gate          = $this->get_overview_gate();
$gate_message  = wp_easycart_admin_pro_gate::message( $gate, __( 'Customer Snapshot', 'wp-easycart' ) );
$pro_outdated  = ( defined( 'WP_EASYCART_ADMIN_PRO_VERSION' )
	&& version_compare( WP_EASYCART_ADMIN_PRO_VERSION, '5.8.17', '<' )
	&& function_exists( 'wp_easycart_admin_license' )
	&& wp_easycart_admin_license()->is_licensed() );
$update_url    = $pro_outdated ? admin_url( 'plugins.php?plugin_status=upgrade' ) : '';
$cta_label     = $pro_outdated ? __( 'Update WP EasyCart PRO', 'wp-easycart' ) : __( 'Upgrade to PRO', 'wp-easycart' );

/* Static placeholder rows for the blurred mockup. Never real data. */
$ecuo_demo_orders = array(
	array( '#1024', __( 'Shipped', 'wp-easycart' ),   '$84.00', __( '2× Classic Tee · Sticker Pack', 'wp-easycart' ),  __( '3 days ago · 3 items', 'wp-easycart' ) ),
	array( '#0991', __( 'Completed', 'wp-easycart' ), '$129.50', __( 'Hoodie · 2× Enamel Pin', 'wp-easycart' ),        __( '2 weeks ago · 3 items', 'wp-easycart' ) ),
	array( '#0875', __( 'Completed', 'wp-easycart' ), '$42.00', __( 'Classic Tee', 'wp-easycart' ),                    __( 'last month · 1 item', 'wp-easycart' ) ),
	array( '#0712', __( 'Refunded', 'wp-easycart' ),  '$58.25', __( 'Tote Bag · Sticker Pack', 'wp-easycart' ),        __( '3 months ago · 2 items', 'wp-easycart' ) ),
);
?>

<div class="ec_user_overview">
	<div class="ecuo-card">

		<!-- Header (live data, fully functional on free) -->
		<div class="ecuo-head">
			<div class="ecuo-avatar"><?php echo esc_html( strtoupper( substr( $this->user->first_name, 0, 1 ) . substr( $this->user->last_name, 0, 1 ) ) ); ?></div>
			<div class="ecuo-head-text">
				<div class="ecuo-name"><?php echo esc_html( $full_name ); ?> <span class="ecuo-id">#<?php echo (int) $this->user->user_id; ?></span></div>
				<div class="ecuo-sub">
					<a href="mailto:<?php echo esc_attr( $this->user->email ); ?>"><?php echo esc_html( $this->user->email ); ?></a>
					<?php if ( $billing_phone ) { ?> &middot; <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $billing_phone ) ); ?>"><?php echo esc_html( $billing_phone ); ?></a><?php } ?>
					&middot; <?php echo esc_html( ucfirst( $this->user->user_level ) ); ?>
				</div>
			</div>
			<div class="ecuo-head-actions">
				<a href="<?php echo esc_url( $orders_link ); ?>" class="ecuo-btn"><span class="dashicons-before dashicons-cart"></span><?php esc_html_e( 'View Orders', 'wp-easycart' ); ?></a>
				<a href="admin.php?page=wp-easycart-users&subpage=accounts&ec_admin_form_action=user-login-override&user_id=<?php echo (int) $this->user->user_id; ?>&wp_easycart_nonce=<?php echo esc_attr( wp_create_nonce( 'wp-easycart-action-login-as-user' ) ); ?>" class="ecuo-btn"><span class="dashicons-before dashicons-admin-users"></span><?php esc_html_e( 'Login as User', 'wp-easycart' ); ?></a>
			</div>
		</div>

		<!-- Locked PRO preview -->
		<div class="ecuo-locked"
			data-gate="<?php echo esc_attr( wp_json_encode( $gate ) ); ?>"
			<?php if ( $update_url ) { ?>data-update-url="<?php echo esc_url( $update_url ); ?>"<?php } ?>
			onclick="ec_admin_user_overview_locked_click( this ); return false;" role="button" tabindex="0" onkeydown="if( event.key === 'Enter' || event.key === ' ' ){ ec_admin_user_overview_locked_click( this ); return false; }" aria-label="<?php echo esc_attr( $gate_message ); ?>">

			<div class="ecuo-blur" aria-hidden="true">

				<!-- Fake KPIs -->
				<div class="ecuo-kpis">
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Lifetime Net', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value">$1,248.50</span></div>
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Orders', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value">14</span></div>
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Avg Order', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value">$89.18</span></div>
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Active Subs', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value is-green">2</span></div>
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Refunded', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value">$58.25</span></div>
					<div class="ecuo-kpi"><span class="ecuo-kpi-label"><?php esc_html_e( 'Last Order', 'wp-easycart' ); ?></span><span class="ecuo-kpi-value"><small><?php esc_html_e( '3 days ago', 'wp-easycart' ); ?></small></span></div>
				</div>

				<div class="ecuo-body ecuo-body-locked">

					<!-- Fake order list -->
					<div class="ecuo-panel">
						<div class="ecuo-panel-head">
							<span class="ecuo-panel-title"><?php esc_html_e( 'Order History', 'wp-easycart' ); ?></span>
							<span class="ecuo-panel-link"><?php esc_html_e( 'View all', 'wp-easycart' ); ?> &rarr;</span>
						</div>
						<div class="ecuo-scroll ecuo-scroll-locked">
							<?php foreach ( $ecuo_demo_orders as $demo ) { ?>
								<span class="ecuo-order">
									<span class="ecuo-order-line1">
										<span class="ecuo-order-id"><?php echo esc_html( $demo[0] ); ?></span>
										<span class="ecuo-status"><?php echo esc_html( $demo[1] ); ?></span>
										<span class="ecuo-order-total"><?php echo esc_html( $demo[2] ); ?></span>
									</span>
									<span class="ecuo-order-products"><?php echo esc_html( $demo[3] ); ?></span>
									<span class="ecuo-order-line3"><span class="ecuo-order-meta"><?php echo esc_html( $demo[4] ); ?></span></span>
								</span>
							<?php } ?>
						</div>
					</div>

					<!-- Fake (smaller) notes box -->
					<div class="ecuo-panel ecuo-panel-notes-locked">
						<div class="ecuo-panel-head">
							<span class="ecuo-panel-title"><?php esc_html_e( 'Notes &amp; Activity', 'wp-easycart' ); ?></span>
						</div>
						<div class="ecuo-addnote">
							<div class="ecuo-addnote-input ecuo-addnote-fake"><?php esc_html_e( 'Add an internal note about this customer…', 'wp-easycart' ); ?></div>
						</div>
						<div class="ecuo-scroll ecuo-scroll-locked-sm">
							<div class="ecuo-note is-admin">
								<div class="ecuo-note-head"><span class="ecuo-note-author"><?php esc_html_e( 'Order Note', 'wp-easycart' ); ?></span><span class="ecuo-note-date"><?php esc_html_e( '2 weeks ago', 'wp-easycart' ); ?></span></div>
								<div class="ecuo-note-body"><?php esc_html_e( 'Customer requested gift wrapping on all future orders.', 'wp-easycart' ); ?></div>
							</div>
						</div>
					</div>

				</div>
			</div>

			<!-- Overlay -->
			<div class="ecuo-lock-overlay">
				<span class="dashicons <?php echo $pro_outdated ? 'dashicons-update' : 'dashicons-lock'; ?> ecuo-lock-icon"></span>
				<span class="ecuo-lock-title"><?php esc_html_e( 'Customer Snapshot', 'wp-easycart' ); ?> <span class="ecuo-lock-badge"><?php echo $pro_outdated ? esc_html__( 'UPDATE', 'wp-easycart' ) : esc_html__( 'PRO', 'wp-easycart' ); ?></span></span>
				<span class="ecuo-lock-text"><?php echo esc_html( $gate_message ); ?></span>
				<?php if ( ! $pro_outdated ) { ?>
					<span class="ecuo-lock-text"><?php esc_html_e( 'See lifetime value, full order history, subscriptions, downloads, abandoned carts and a customer notes feed — all in one place.', 'wp-easycart' ); ?></span>
				<?php } ?>
				<span class="ecuo-btn ecuo-btn-primary"><?php echo esc_html( $cta_label ); ?></span>
			</div>

		</div>

	</div>
</div>