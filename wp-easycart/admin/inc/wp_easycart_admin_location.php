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
				self::print_tabs( 'location' );
				do_action( 'wp_easycart_admin_location_list' );
			}
		}

		/**
		 * Store schedule and store locations share one Settings page with two tabs. Each tab is its own URL
		 * ( subpage=schedule / subpage=location ), so either can be linked to and old bookmarks keep working.
		 *
		 * @since 6.0.0
		 * @param string $tab 'schedule' or 'location'.
		 * @return string
		 */
		public static function tab_url( $tab ) {
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . ( 'location' === $tab ? 'location' : 'schedule' ) );
		}

		/**
		 * Tab strip above both lists ( and above the locked preview without PRO ).
		 *
		 * @since 6.0.0
		 * @param string $active 'schedule' or 'location'.
		 */
		public static function print_tabs( $active ) {
			$tabs = array(
				'schedule' => __( 'Store schedule', 'wp-easycart' ),
				'location' => __( 'Locations', 'wp-easycart' ),
			);
			echo '<div class="ecv2-wrap ecv2-tabs-wrap ecsl-tabs-wrap"><div class="ecv2-tabs" role="tablist">';
			foreach ( $tabs as $key => $label ) {
				echo '<a class="ecv2-tab' . ( $key === $active ? ' is-active' : '' ) . '" href="' . esc_url( self::tab_url( $key ) ) . '" role="tab"' . ( $key === $active ? ' aria-selected="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			echo '</div>';
			if ( 'location' === $active && ! get_option( 'ec_option_pickup_enable_locations' ) ) {
				$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout#ecst-ec_option_pickup_enable_locations' );
				echo '<div class="ecv2-tab-notice" role="status">';
				echo '<div class="ecv2-tab-notice-text"><b>' . esc_html__( 'Multiple pickup locations is turned off', 'wp-easycart' ) . '</b>';
				echo '<span>' . esc_html__( 'You can add and edit locations here, but shoppers will not see them until Multiple pickup locations is turned on under Settings > Checkout.', 'wp-easycart' ) . '</span></div>';
				echo '<a class="ecv2-btn" href="' . esc_url( $url ) . '">' . esc_html__( 'Turn on pickup locations', 'wp-easycart' ) . ' &rarr;</a>';
				echo '</div>';
			}
			echo '</div>';
		}
	}
endif;

function wp_easycart_admin_location() {
	return wp_easycart_admin_location::instance();
}
wp_easycart_admin_location();
