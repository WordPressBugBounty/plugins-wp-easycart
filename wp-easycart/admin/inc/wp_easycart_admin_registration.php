<?php
/**
 * Registration page controller.
 *
 * 6.0.0: renders only the V2 templates; the classic registration_status / registration-expired /
 * registration_none / trial templates and the unused trial activation helpers were removed.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_registration' ) ) :

	/**
	 * Picks the registration template for the current license state.
	 */
	class wp_easycart_admin_registration {

		/**
		 * Render the registration page body for the current license state.
		 */
		public function load_registration_status() {
			$license_status = 'none';
			if ( function_exists( 'wp_easycart_admin_license' ) ) {
				$license_status = wp_easycart_admin_license()->license_check();
			}
			$dir = EC_PLUGIN_DIRECTORY . '/admin/template/registration/';
			if ( 'trial' === $license_status ) {
				/* V2 trial page renders its own upsell ( only once the trial has ended ). */
				include $dir . 'trial-v2.php';
			} elseif ( in_array( $license_status, array( 'activated', 'deactivated', 'communications_error' ), true ) ) {
				include $dir . 'registration-status-v2.php';
			} elseif ( 'expired' === $license_status ) {
				include $dir . 'registration-expired-v2.php';
			} else {
				/* Free, no license: the V2 template renders the upsell card itself. */
				include $dir . 'registration-none-v2.php';
			}
		}
	}

endif;
