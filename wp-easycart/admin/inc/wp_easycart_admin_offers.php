<?php
/**
 * WP EasyCart Admin Offers (free stub).
 *
 * Fires the offers hub action. The core plugin registers show_upgrade on
 * this action (setup_pro_hooks); the PRO plugin removes that and attaches
 * its hub renderer when licensed — the same replacement pattern used by
 * the legacy coupons/promotions stubs.
 */
class wp_easycart_admin_offers {
	public function load_offers_hub() {
		do_action( 'wp_easycart_admin_offers_hub' );
	}
}