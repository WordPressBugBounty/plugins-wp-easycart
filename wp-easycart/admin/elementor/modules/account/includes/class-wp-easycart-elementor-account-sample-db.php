<?php
/**
 * Stand-in for ec_db on the editor's sample account page ( 6.0.2 ): sample orders answer with their sample lines, every
 * other call goes to ec_db. See WP_EasyCart_Elementor_Account_Samples.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Sample_DB' ) ) :

	/**
	 * Stands in for ec_db on the sample account page: sample orders answer with their sample lines, everything else goes to
	 * ec_db.
	 */
	class WP_EasyCart_Elementor_Account_Sample_DB {

		/**
		 * The store's ec_db.
		 *
		 * @var ec_db
		 */
		private $db;

		/**
		 * Samples.
		 *
		 * @var WP_EasyCart_Elementor_Account_Samples
		 */
		private $samples;

		/**
		 * Constructor.
		 *
		 * @param WP_EasyCart_Elementor_Account_Samples $samples Samples.
		 */
		public function __construct( $samples ) {
			$this->db      = new ec_db();
			$this->samples = $samples;
		}

		/**
		 * Lines of an order.
		 *
		 * @param int $order_id Order id.
		 * @param int $user_id  Customer.
		 * @return array
		 */
		public function get_order_details( $order_id, $user_id = 0 ) {
			$rows = $this->samples->detail_rows( $order_id );
			if ( null !== $rows ) {
				return $rows;
			}
			return $this->db->get_order_details( $order_id, $user_id );
		}

		/**
		 * Advanced options of an order line.
		 *
		 * @param int $orderdetail_id Line id.
		 * @return array
		 */
		public function get_order_options( $orderdetail_id ) {
			if ( $this->samples->is_sample_detail( $orderdetail_id ) ) {
				return array();
			}
			return $this->db->get_order_options( $orderdetail_id );
		}

		/**
		 * Everything else: ec_db.
		 *
		 * @param string $name Method.
		 * @param array  $args Arguments.
		 * @return mixed
		 */
		public function __call( $name, $args ) {
			return call_user_func_array( array( $this->db, $name ), $args );
		}
	}

endif;
