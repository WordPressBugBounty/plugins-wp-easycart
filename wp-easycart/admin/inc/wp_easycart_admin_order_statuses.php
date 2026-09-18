<?php
/**
 * Order status editor handlers.
 *
 * The V2 Checkout settings page ( admin/template/settings/checkout.php, driven by
 * admin/js/settings-checkout-v2.js ) adds, renames, recolours, flags and archives
 * rows of ec_orderstatus through these classic admin-ajax actions. They used to
 * live in the legacy checkout controller ( wp_easycart_admin_checkout ), which was
 * removed with the classic Checkout page.
 *
 * Actions ( all POST, nonce 'wp-easycart-settings-checkout' ):
 *   ec_admin_ajax_add_orderstatus            ( order_status, color_code, is_approved ) → echoes the new id
 *   ec_admin_ajax_save_orderstatus           ( status_id, order_status, color_code )
 *   ec_admin_ajax_save_orderstatus_approved  ( status_id, is_approved )  — custom statuses only
 *   ec_admin_ajax_archieve_orderstatus       ( status_id )               — custom statuses only
 *
 * @package wp-easycart
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_order_statuses' ) ) :

	/**
	 * Writes to ec_orderstatus for the order status editor.
	 *
	 * @since 6.0.0
	 */
	final class wp_easycart_admin_order_statuses {

		/** Statuses 1-19 ship with the plugin and drive payments, stock, downloads and reports. @since 6.0.0 */
		const BUILTIN_STATUS_MAX = 19;

		/** @var wp_easycart_admin_order_statuses|null Singleton. */
		protected static $_instance = null;

		/**
		 * Singleton access.
		 *
		 * @return wp_easycart_admin_order_statuses
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Insert a custom status.
		 *
		 * @param string $order_status Status name.
		 * @param string $color_code   Hex colour.
		 * @param int    $is_approved  1 when orders in this status count as paid.
		 * @return int|false New status_id, false when access is denied.
		 */
		public function add_order_status( $order_status, $color_code, $is_approved ) {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-settings-checkout' ) ) {
				return false;
			}

			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ec_orderstatus( order_status, color_code, is_approved ) VALUES( %s, %s, %d )', wp_easycart_admin_verification()->min_filter( $order_status ), wp_easycart_admin_verification()->min_filter( $color_code ), wp_easycart_admin_verification()->filter_bool_int( $is_approved ) ) );
			$status_id = $wpdb->insert_id;
			do_action( 'wpeasycart_order_status_added', $status_id );
			return $status_id;
		}

		/**
		 * Rename / recolour a status ( built-in or custom ).
		 *
		 * @param int    $status_id    Status id.
		 * @param string $order_status Status name.
		 * @param string $color_code   Hex colour.
		 * @return void|false
		 */
		public function update_order_status( $status_id, $order_status, $color_code ) {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-settings-checkout' ) ) {
				return false;
			}

			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_orderstatus SET order_status = %s, color_code = %s WHERE status_id = %d', wp_easycart_admin_verification()->min_filter( $order_status ), wp_easycart_admin_verification()->min_filter( $color_code ), wp_easycart_admin_verification()->filter_int( $status_id ) ) );
			do_action( 'wpeasycart_order_status_updated', $status_id );
		}

		/**
		 * Flip the paid flag of a custom status.
		 *
		 * @param int $status_id   Status id.
		 * @param int $is_approved 1 = paid.
		 * @return void|false
		 */
		public function update_order_status_approved( $status_id, $is_approved ) {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-settings-checkout' ) ) {
				return false;
			}
			if ( (int) $status_id <= self::BUILTIN_STATUS_MAX ) {
				return false; // Built-in paid flags are fixed; the V2 editor never offers the switch.
			}

			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_orderstatus SET is_approved = %d WHERE status_id = %d', wp_easycart_admin_verification()->filter_bool_int( $is_approved ), wp_easycart_admin_verification()->filter_int( $status_id ) ) );
			do_action( 'wpeasycart_order_status_updated', $status_id );
		}

		/**
		 * Archive ( soft delete ) a custom status.
		 *
		 * @param int $status_id Status id.
		 * @return void|false
		 */
		public function archieve_order_status( $status_id ) {
			if ( ! wp_easycart_admin_verification()->verify_access( 'wp-easycart-settings-checkout' ) ) {
				return false;
			}
			if ( (int) $status_id <= self::BUILTIN_STATUS_MAX ) {
				return false; // Built-in statuses cannot be deleted.
			}

			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_orderstatus SET is_archieved = 1 WHERE status_id = %d', wp_easycart_admin_verification()->filter_int( $status_id ) ) );
			do_action( 'wpeasycart_order_status_deleted', $status_id );
		}
	}
endif;

if ( ! function_exists( 'wp_easycart_admin_order_statuses' ) ) {
	/**
	 * Access point.
	 *
	 * @since 6.0.0
	 * @return wp_easycart_admin_order_statuses
	 */
	function wp_easycart_admin_order_statuses() {
		return wp_easycart_admin_order_statuses::instance();
	}
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every handler verifies the 'wp-easycart-settings-checkout' nonce and capability through wp_easycart_admin_verification()->verify_access() inside the class method it delegates to.

if ( ! function_exists( 'ec_admin_ajax_add_orderstatus' ) ) {
	add_action( 'wp_ajax_ec_admin_ajax_add_orderstatus', 'ec_admin_ajax_add_orderstatus' );
	/** POST order_status, color_code, is_approved → echoes the new status_id. */
	function ec_admin_ajax_add_orderstatus() {
		if ( ! isset( $_POST['order_status'] ) ) {
			die();
		}
		if ( ! isset( $_POST['color_code'] ) ) {
			die();
		}
		$is_approved = isset( $_POST['is_approved'] ) ? (int) $_POST['is_approved'] : 0;
		$insert_id   = wp_easycart_admin_order_statuses()->add_order_status( sanitize_text_field( wp_unslash( $_POST['order_status'] ) ), sanitize_text_field( wp_unslash( $_POST['color_code'] ) ), $is_approved );
		echo esc_attr( $insert_id );
		die();
	}
}

if ( ! function_exists( 'ec_admin_ajax_save_orderstatus' ) ) {
	add_action( 'wp_ajax_ec_admin_ajax_save_orderstatus', 'ec_admin_ajax_save_orderstatus' );
	/** POST status_id, order_status, color_code → renames / recolours the status. */
	function ec_admin_ajax_save_orderstatus() {
		if ( ! isset( $_POST['status_id'] ) ) {
			die();
		}
		if ( ! isset( $_POST['order_status'] ) ) {
			die();
		}
		if ( ! isset( $_POST['color_code'] ) ) {
			die();
		}
		wp_easycart_admin_order_statuses()->update_order_status( (int) $_POST['status_id'], sanitize_text_field( wp_unslash( $_POST['order_status'] ) ), sanitize_text_field( wp_unslash( $_POST['color_code'] ) ) );
		die();
	}
}

if ( ! function_exists( 'ec_admin_ajax_save_orderstatus_approved' ) ) {
	add_action( 'wp_ajax_ec_admin_ajax_save_orderstatus_approved', 'ec_admin_ajax_save_orderstatus_approved' );
	/** POST status_id, is_approved → flips the paid flag of a custom status. */
	function ec_admin_ajax_save_orderstatus_approved() {
		if ( ! isset( $_POST['status_id'] ) ) {
			die();
		}
		$is_approved = isset( $_POST['is_approved'] ) ? (int) $_POST['is_approved'] : 0;
		wp_easycart_admin_order_statuses()->update_order_status_approved( (int) $_POST['status_id'], $is_approved );
		die();
	}
}

if ( ! function_exists( 'ec_admin_ajax_archieve_orderstatus' ) ) {
	add_action( 'wp_ajax_ec_admin_ajax_archieve_orderstatus', 'ec_admin_ajax_archieve_orderstatus' );
	/** POST status_id → archives a custom status. */
	function ec_admin_ajax_archieve_orderstatus() {
		if ( ! isset( $_POST['status_id'] ) ) {
			die();
		}
		wp_easycart_admin_order_statuses()->archieve_order_status( (int) $_POST['status_id'] );
		die();
	}
}

// phpcs:enable WordPress.Security.NonceVerification.Missing
