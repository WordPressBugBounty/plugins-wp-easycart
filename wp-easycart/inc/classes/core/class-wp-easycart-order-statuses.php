<?php
/**
 * WP EasyCart — order status names in the admin's language ( 6.0.5, admin review M2 ).
 *
 * Order statuses are rows in ec_orderstatus, so a German admin read "Order Shipped" and "Direct Deposit Received". label() shows a
 * built-in status through a translatable name while it still has the name WP EasyCart gave it; a status the store renamed, or made,
 * shows as the store wrote it. Since 6.0.5 a new store's bank transfer statuses are "Awaiting Bank Transfer" and "Bank Transfer
 * Received" ( stores set up before keep "Direct Deposit Pending" / "Direct Deposit Received", which they can rename ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_statuses' ) ) :

	/**
	 * Built-in order status names.
	 */
	class wp_easycart_order_statuses {

		/**
		 * The names WP EasyCart gives its statuses, each with its translatable label.
		 *
		 * @return array status id => array( English name => label ).
		 */
		public static function defaults() {
			$names = array(
				1  => array( 'Status Not Found' => __( 'Status Not Found', 'wp-easycart' ) ),
				2  => array( 'Order Shipped' => __( 'Order Shipped', 'wp-easycart' ) ),
				3  => array( 'Order Confirmed' => __( 'Order Confirmed', 'wp-easycart' ) ),
				4  => array( 'Order on Hold' => __( 'Order on Hold', 'wp-easycart' ) ),
				5  => array( 'Order Started' => __( 'Order Started', 'wp-easycart' ) ),
				6  => array( 'Card Approved' => __( 'Card Approved', 'wp-easycart' ) ),
				7  => array( 'Card Denied' => __( 'Card Denied', 'wp-easycart' ) ),
				8  => array( 'Third Party Pending' => __( 'Third Party Pending', 'wp-easycart' ) ),
				9  => array( 'Third Party Error' => __( 'Third Party Error', 'wp-easycart' ) ),
				10 => array( 'Third Party Approved' => __( 'Third Party Approved', 'wp-easycart' ) ),
				11 => array( 'Ready for Pickup' => __( 'Ready for Pickup', 'wp-easycart' ) ),
				12 => array( 'Pending Approval' => __( 'Pending Approval', 'wp-easycart' ) ),
				14 => array(
					'Direct Deposit Pending' => __( 'Direct Deposit Pending', 'wp-easycart' ),
					'Awaiting Bank Transfer' => __( 'Awaiting Bank Transfer', 'wp-easycart' ),
				),
				15 => array(
					'Direct Deposit Received' => __( 'Direct Deposit Received', 'wp-easycart' ),
					'Bank Transfer Received'  => __( 'Bank Transfer Received', 'wp-easycart' ),
				),
				16 => array( 'Refunded Order' => __( 'Refunded Order', 'wp-easycart' ) ),
				17 => array( 'Partial Refund' => __( 'Partial Refund', 'wp-easycart' ) ),
				18 => array( 'Order Picked Up' => __( 'Order Picked Up', 'wp-easycart' ) ),
				19 => array( 'Order Cancelled' => __( 'Order Cancelled', 'wp-easycart' ) ),
			);
			/* Statuses made on first use ( the Delivered status; WP EasyCart PRO's Draft and Awaiting Payment ). */
			$made = array(
				'ec_option_orderstatus_delivered' => array( 'Order Delivered' => __( 'Order Delivered', 'wp-easycart' ) ),
				'ec_option_orderstatus_draft'     => array( 'Draft' => __( 'Draft', 'wp-easycart' ) ),
				'ec_option_orderstatus_awaiting'  => array( 'Awaiting Payment' => __( 'Awaiting Payment', 'wp-easycart' ) ),
			);
			foreach ( $made as $option => $name ) {
				$id = (int) get_option( $option, 0 );
				if ( $id > 0 && ! isset( $names[ $id ] ) ) {
					$names[ $id ] = $name;
				}
			}
			/**
			 * Built-in order status names and their labels.
			 *
			 * @since 6.0.5
			 * @param array $names status id => array( English name => label ).
			 */
			return (array) apply_filters( 'wp_easycart_order_status_defaults', $names );
		}

		/**
		 * A status as the admin shows it: its translated name while it has WP EasyCart's name, else as the store wrote it.
		 *
		 * @param int|object $status Status id, or a row with status_id and order_status.
		 * @param string     $name   The stored name ( when an id is given ).
		 * @return string
		 */
		public static function label( $status, $name = '' ) {
			if ( is_object( $status ) ) {
				$name   = isset( $status->order_status ) ? (string) $status->order_status : '';
				$status = isset( $status->status_id ) ? (int) $status->status_id : ( isset( $status->orderstatus_id ) ? (int) $status->orderstatus_id : 0 );
			}
			$name     = wp_unslash( (string) $name );
			$defaults = self::defaults();
			$key      = strtolower( trim( $name ) );
			if ( isset( $defaults[ (int) $status ] ) ) {
				foreach ( $defaults[ (int) $status ] as $english => $label ) {
					if ( strtolower( $english ) === $key ) {
						return (string) $label;
					}
				}
			}
			return $name;
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_order_status_label' ) ) {
	/**
	 * An order status as the admin shows it ( wp_easycart_order_statuses::label() ).
	 *
	 * @since 6.0.5
	 * @param int|object $status Status id or row.
	 * @param string     $name   Stored name.
	 * @return string
	 */
	function wp_easycart_order_status_label( $status, $name = '' ) {
		return wp_easycart_order_statuses::label( $status, $name );
	}
}
