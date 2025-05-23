<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_location' ) ) :

	final class wp_easycart_admin_location {

		protected static $_instance = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {}

		public function load_location_list() {
			if ( isset( $_GET['ec_admin_form_action'] ) && ( ( isset( $_GET['location_id'] ) && 'edit' == $_GET['ec_admin_form_action'] ) || 'add-new' == $_GET['ec_admin_form_action'] ) ) {
				do_action( 'wp_easycart_admin_location_details' );
			} else {
				do_action( 'wp_easycart_admin_location_list' );
			}
		}
	}
endif;

function wp_easycart_admin_location() {
	return wp_easycart_admin_location::instance();
}
wp_easycart_admin_location();
