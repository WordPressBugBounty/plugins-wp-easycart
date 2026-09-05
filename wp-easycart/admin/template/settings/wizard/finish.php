<?php
/**
 * Step 4 — Finish up.
 * Recommended settings are shown as status ( applied at install ), not asked.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard  = wp_easycart_admin_setup_wizard();
$status  = $wizard->get_recommended_status();
$env     = wp_easycart_admin_setup_wizard::get_environment();
$all_ok  = $wizard->recommended_all_ok();
$attention = 0;
foreach ( $status as $r ) {
	if ( 'ok' !== $r['state'] ) {
		$attention++;
	}
}

$pages = array(
	array( 'id' => (int) get_option( 'ec_option_storepage' ),   'name' => __( 'Store', 'wp-easycart' ),           'desc' => __( 'Where customers browse and shop', 'wp-easycart' ),         'url' => wp_easycart_admin()->store_page ),
	array( 'id' => (int) get_option( 'ec_option_cartpage' ),    'name' => __( 'Cart & Checkout', 'wp-easycart' ), 'desc' => __( 'Cart contents and checkout flow', 'wp-easycart' ),        'url' => wp_easycart_admin()->cart_page ),
	array( 'id' => (int) get_option( 'ec_option_accountpage' ), 'name' => __( 'My Account', 'wp-easycart' ),      'desc' => __( 'Order history, addresses, downloads', 'wp-easycart' ),    'url' => wp_easycart_admin()->account_page ),
);
$menu_name = $wizard->store_pages_in_menu();

$terms_id = get_option( 'ec_option_terms_link' ) ? url_to_postid( get_option( 'ec_option_terms_link' ) ) : 0;
$priv_id  = get_option( 'ec_option_privacy_link' ) ? url_to_postid( get_option( 'ec_option_privacy_link' ) ) : 0;
if ( ! $priv_id ) {
	$priv_id = (int) get_option( 'wp_page_for_privacy_policy' );
}
$from_email = get_option( 'ec_option_order_from_email' ) ? get_option( 'ec_option_order_from_email' ) : get_option( 'admin_email' );
$bcc        = get_option( 'ec_option_bcc_email_addresses' ) ? get_option( 'ec_option_bcc_email_addresses' ) : get_option( 'admin_email' );
$tracking   = get_option( 'ec_option_allow_tracking' );
$tracking_on = ( '-1' != $tracking ); /* unset or pending counts as on-by-default here; the merchant confirms */
$state      = $wizard->get_checklist_state();

$icon_ok   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>';
$icon_warn = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 8v5M12 16.5h.01M10.3 3.9L2.6 17.6A2 2 0 004.3 20.6h15.4a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>';
?>
<form action="" method="POST" name="wpeasycart_admin_setup_wizard_form" id="wpeasycart_admin_setup_wizard_form" novalidate="novalidate" class="ecwz-form">
	<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_nonce', 'wp-easycart-process-wizard-finish' ); ?>
	<input type="hidden" name="ec_admin_form_action" id="ec_admin_form_action" value="process-wizard-finish">

	<div class="ecwz-body">
		<h2><?php esc_html_e( 'Finish up', 'wp-easycart' ); ?></h2>
		<p class="ecwz-lede"><?php esc_html_e( 'We\'ve already applied the recommended technical settings and created your store pages. Confirm your policies and where notifications go, and you\'re done.', 'wp-easycart' ); ?></p>

		<!-- Recommended settings: status, not a choice -->
		<h3 class="ecwz-sec ecwz-sec-first" id="ecwz-recommended">
			<?php esc_html_e( 'Recommended settings', 'wp-easycart' ); ?>
			<?php if ( $all_ok ) { ?>
			<span class="ecwz-badge ecwz-badge-green"><?php esc_html_e( 'Applied', 'wp-easycart' ); ?></span>
			<?php } else { ?>
			<span class="ecwz-badge ecwz-badge-amber"><?php echo esc_html( sprintf( _n( '%d needs attention', '%d need attention', $attention, 'wp-easycart' ), $attention ) ); ?></span>
			<?php } ?>
		</h3>
		<p class="ecwz-hint ecwz-hint-block"><?php esc_html_e( 'Set for you when EasyCart was installed. They stay on even if you skip setup. Turning one off is a deliberate change under Advanced.', 'wp-easycart' ); ?></p>
		<div class="ecwz-slist">
			<?php foreach ( $status as $r ) {
				$cls = ( 'ok' === $r['state'] ) ? '' : ( ( 'warn' === $r['state'] ) ? ' warn' : ( 'error' === $r['severity'] ? ' bad' : ' off' ) );
				if ( 'ok' === $r['state'] ) {
					$badge = '<span class="ecwz-badge ecwz-badge-green">' . esc_html__( 'On', 'wp-easycart' ) . '</span>';
				} else if ( 'warn' === $r['state'] ) {
					$badge = '<span class="ecwz-badge ecwz-badge-amber">' . ( 'ssl' == $r['key'] ? esc_html__( 'Waiting for certificate', 'wp-easycart' ) : esc_html__( 'Needs permalinks', 'wp-easycart' ) ) . '</span>';
				} else {
					$badge = '<span class="ecwz-badge ecwz-badge-red">' . esc_html__( 'Off', 'wp-easycart' ) . '</span>';
				}
			?>
			<div class="ecwz-srow<?php echo esc_attr( $cls ); ?>" data-rec="<?php echo esc_attr( $r['key'] ); ?>">
				<span class="ecwz-ok"><?php echo ( 'ok' === $r['state'] ) ? $icon_ok : $icon_warn; ?></span>
				<div class="ecwz-txt">
					<b><?php echo esc_html( $r['label'] ); ?> <?php echo $badge; ?></b>
					<span><?php echo wp_kses( $r['message'], array( 'strong' => array(), 'a' => array( 'href' => array(), 'class' => array(), 'target' => array() ) ) ); ?>
					<?php if ( 'warn' === $r['state'] && ! empty( $r['fix_url'] ) ) { ?> <a href="<?php echo esc_url( $r['fix_url'] ); ?>" class="ecwz-lnk" target="_blank"><?php esc_html_e( 'Open Settings › Permalinks', 'wp-easycart' ); ?></a><?php } ?></span>
				</div>
			</div>
			<?php } ?>
			<div class="ecwz-sfoot">
				<span><?php echo sprintf( esc_html__( 'These checks also run continuously in %s.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-status&subpage=store-status" class="ecwz-lnk">' . esc_html__( 'Store Status', 'wp-easycart' ) . '</a>' ); ?></span>
				<span class="ecwz-grow"></span>
				<button type="button" class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" id="ecwz_rec_change"><?php esc_html_e( 'Change…', 'wp-easycart' ); ?></button>
			</div>
		</div>
		<details class="ecwz-adv" id="ecwz_rec_adv">
			<summary><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg> <?php esc_html_e( 'Advanced: change recommended settings', 'wp-easycart' ); ?></summary>
			<div class="ecwz-adv-body">
				<div class="ecwz-adv-row">
					<div class="ecwz-txt"><b><?php esc_html_e( 'Cache compatibility', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Loads cart and account content dynamically so caching never shows one customer\'s cart to another. Turn off only if your host has told you to.', 'wp-easycart' ); ?></span></div>
					<label class="ecwz-tg"><input type="checkbox" name="adv_cache" value="1"<?php checked( $status['cache']['enabled'] ); ?>><span></span></label>
				</div>
				<div class="ecwz-adv-row<?php echo $status['ssl']['locked'] ? ' is-locked' : ''; ?>">
					<div class="ecwz-txt"><b><?php esc_html_e( 'Force secure checkout (SSL)', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Redirects store pages to https. Only available when your site has a working certificate.', 'wp-easycart' ); ?></span></div>
					<label class="ecwz-tg"><input type="checkbox" name="adv_ssl" value="1"<?php checked( $status['ssl']['enabled'] ); ?><?php disabled( $status['ssl']['locked'] ); ?>><span></span></label>
				</div>
				<div class="ecwz-adv-row<?php echo $status['seo']['locked'] ? ' is-locked' : ''; ?>">
					<div class="ecwz-txt"><b><?php esc_html_e( 'SEO-friendly product links', 'wp-easycart' ); ?></b><span><?php esc_html_e( '/store/my-widget/ instead of /store/?model_number=XYZ. Requires WordPress permalinks other than "Plain".', 'wp-easycart' ); ?></span></div>
					<label class="ecwz-tg"><input type="checkbox" name="adv_seo" value="1"<?php checked( $status['seo']['enabled'] ); ?><?php disabled( $status['seo']['locked'] ); ?>><span></span></label>
				</div>
			</div>
		</details>

		<!-- Store pages -->
		<h3 class="ecwz-sec"><?php esc_html_e( 'Store pages', 'wp-easycart' ); ?></h3>
		<div class="ecwz-slist">
			<?php foreach ( $pages as $pg ) { ?>
			<div class="ecwz-srow ecwz-srow-page">
				<span class="ecwz-ok"><?php echo $pg['id'] ? $icon_ok : $icon_warn; ?></span>
				<span class="ecwz-n"><?php echo esc_html( $pg['name'] ); ?></span>
				<span class="ecwz-d"><?php echo esc_html( $pg['desc'] ); ?></span>
				<span class="ecwz-l">
					<?php if ( $pg['id'] ) { ?>
					<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( $pg['url'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'wp-easycart' ); ?></a>
					<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( get_edit_post_link( $pg['id'], '' ) ); ?>" target="_blank"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></a>
					<?php } else { ?>
					<a class="ecwz-btn ecwz-btn-sm" href="admin.php?page=wp-easycart-settings&subpage=initial-setup"><?php esc_html_e( 'Create', 'wp-easycart' ); ?></a>
					<?php } ?>
				</span>
			</div>
			<?php } ?>
			<div class="ecwz-sfoot" id="ecwz_menu_foot">
				<?php if ( $menu_name ) { ?>
				<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'In menu', 'wp-easycart' ); ?></span>
				<span><?php echo esc_html( sprintf( __( 'Store, Cart & Checkout and My Account are in "%s".', 'wp-easycart' ), $menu_name ) ); ?></span>
				<span class="ecwz-grow"></span>
				<a class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Edit menu', 'wp-easycart' ); ?></a>
				<?php } else { ?>
				<span><?php esc_html_e( 'Not yet in your site navigation.', 'wp-easycart' ); ?></span>
				<span class="ecwz-grow"></span>
				<button type="button" class="ecwz-btn ecwz-btn-sm" id="ecwz_menu_add" onclick="wpEasyCartWizard.addMenuItems( this ); return false;"><?php esc_html_e( 'Add all three to your menu', 'wp-easycart' ); ?></button>
				<?php } ?>
			</div>
		</div>

		<!-- Policies -->
		<h3 class="ecwz-sec" id="ecwz-policies"><?php esc_html_e( 'Policies', 'wp-easycart' ); ?></h3>
		<div class="ecwz-frow ecwz-frow-first ecwz-frow-tight">
			<div class="ecwz-lab"><?php esc_html_e( 'Terms & conditions', 'wp-easycart' ); ?><small><?php esc_html_e( 'Linked from checkout. Pick an existing page or start from our outline.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val ecwz-withbtn">
				<?php wp_dropdown_pages( array( 'name' => 'terms_page', 'id' => 'ecwz_terms_page', 'class' => 'ecwz-select', 'selected' => $terms_id, 'show_option_none' => __( '— Select a page —', 'wp-easycart' ), 'option_none_value' => '0', 'post_status' => array( 'publish', 'draft' ) ) ); ?>
				<button type="button" class="ecwz-btn ecwz-btn-sm" onclick="wpEasyCartWizard.createPage( this, 'terms', 'ecwz_terms_page' ); return false;"><?php esc_html_e( 'Create draft', 'wp-easycart' ); ?></button>
			</div>
		</div>
		<div class="ecwz-frow">
			<div class="ecwz-lab"><?php esc_html_e( 'Privacy policy', 'wp-easycart' ); ?><small><?php esc_html_e( 'Pre-filled from WordPress › Settings › Privacy when set.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val ecwz-withbtn">
				<?php wp_dropdown_pages( array( 'name' => 'privacy_page', 'id' => 'ecwz_privacy_page', 'class' => 'ecwz-select', 'selected' => $priv_id, 'show_option_none' => __( '— Select a page —', 'wp-easycart' ), 'option_none_value' => '0', 'post_status' => array( 'publish', 'draft' ) ) ); ?>
				<button type="button" class="ecwz-btn ecwz-btn-sm" onclick="wpEasyCartWizard.createPage( this, 'privacy', 'ecwz_privacy_page' ); return false;"><?php esc_html_e( 'Create draft', 'wp-easycart' ); ?></button>
			</div>
		</div>
		<div class="ecwz-frow">
			<div class="ecwz-lab"><?php esc_html_e( 'Agreement at checkout', 'wp-easycart' ); ?></div>
			<div class="ecwz-val ecwz-val-toggle">
				<label class="ecwz-tg-row"><span class="ecwz-tg"><input type="checkbox" name="require_terms_agreement" value="1"<?php checked( false !== get_option( 'ec_option_require_terms_agreement' ) ? (bool) get_option( 'ec_option_require_terms_agreement' ) : true ); ?>><span></span></span><span class="ecwz-t"><?php esc_html_e( 'Require customers to accept the terms before placing an order', 'wp-easycart' ); ?></span></label>
			</div>
		</div>

		<!-- Notifications -->
		<h3 class="ecwz-sec"><?php esc_html_e( 'Notifications', 'wp-easycart' ); ?></h3>
		<div class="ecwz-frow ecwz-frow-first ecwz-frow-tight">
			<div class="ecwz-lab"><?php esc_html_e( 'Send order emails from', 'wp-easycart' ); ?><small><?php esc_html_e( 'The From and Reply-To address customers see. Use an address on your own domain for best deliverability.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val">
				<input type="email" class="ecwz-input" name="order_from_email" id="ecwz_from_email" value="<?php echo esc_attr( $from_email ); ?>" placeholder="orders@yourdomain.com">
				<div class="ecwz-hint"><?php echo sprintf( esc_html__( 'Sender name comes from your WordPress site title, %s.', 'wp-easycart' ), '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>' ); ?></div>
			</div>
		</div>
		<div class="ecwz-frow">
			<div class="ecwz-lab"><?php esc_html_e( 'Copy new-order emails to', 'wp-easycart' ); ?><small><?php esc_html_e( 'Your admin address. Separate several with commas.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val">
				<div class="ecwz-withbtn">
					<input type="text" class="ecwz-input" name="bcc_email" id="ecwz_bcc_email" value="<?php echo esc_attr( $bcc ); ?>" placeholder="you@example.com">
					<button type="button" class="ecwz-btn ecwz-btn-sm" id="ecwz_send_test" onclick="wpEasyCartWizard.sendTestEmail( this ); return false;"><?php echo ! empty( $state['email_tested'] ) ? esc_html__( 'Send again', 'wp-easycart' ) : esc_html__( 'Send test email', 'wp-easycart' ); ?></button>
				</div>
				<div class="ecwz-hint" id="ecwz_test_hint">
					<?php if ( ! empty( $state['email_tested'] ) ) { ?>
					<span class="ecwz-badge ecwz-badge-green">&#10003; <?php esc_html_e( 'Tested', 'wp-easycart' ); ?></span> <?php echo esc_html( sprintf( __( 'Last test sent %s.', 'wp-easycart' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['email_tested'] ) ) ); ?>
					<?php } else { ?>
					<?php esc_html_e( 'Order emails go through WordPress mail, which many hosts handle poorly. Send a test now so you know before your first order does.', 'wp-easycart' ); ?>
					<?php } ?>
				</div>
				<label class="ecwz-check-row ecwz-check-row-sm"><input type="checkbox" name="subscribe_me" value="1" checked> <span><?php esc_html_e( 'Send me security updates and product news from WP EasyCart', 'wp-easycart' ); ?></span></label>
			</div>
		</div>
		<div class="ecwz-frow">
			<div class="ecwz-lab"><?php esc_html_e( 'Help improve EasyCart', 'wp-easycart' ); ?><small><?php esc_html_e( 'Anonymous usage data: plugin and PHP versions, which features are enabled. Never customer or order data.', 'wp-easycart' ); ?></small></div>
			<div class="ecwz-val ecwz-val-toggle">
				<label class="ecwz-tg-row"><span class="ecwz-tg"><input type="checkbox" name="allow_tracking" value="1"<?php checked( $tracking_on ); ?>><span></span></span><span class="ecwz-t"><?php esc_html_e( 'Share basic usage data', 'wp-easycart' ); ?></span> <a href="https://www.wpeasycart.com/terms-and-conditions/" target="_blank" rel="noopener noreferrer" class="ecwz-lnk ecwz-lnk-sm"><?php esc_html_e( 'What\'s sent?', 'wp-easycart' ); ?></a></label>
			</div>
		</div>
	</div>

	<?php $wizard->render_footer( wp_easycart_admin_setup_wizard::STEP_FINISH, __( 'Finish setup ✓', 'wp-easycart' ) ); ?>
</form>
