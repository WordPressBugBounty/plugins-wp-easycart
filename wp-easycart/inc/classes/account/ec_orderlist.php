<?php

class ec_orderlist{
	protected $mysqli;								// ec_db structure

	public $orders = array();						// array of ec_orderitem structures
	public $num_orders;								// INT
	
	private $user_id;								// INT
	
	function __construct( $user_id ){
		$this->mysqli = new ec_db();
		
		$this->user_id = $user_id;
		
		$this->get_orders( );
	}
	
	private function get_orders( ){
		
		$result = $this->mysqli->get_order_list( $this->user_id );
		$this->num_orders = count($result);
	
		foreach( $result as $orderdata ){
			
			$order = new ec_orderdisplay( $orderdata );
			array_push( $this->orders, $order );
			
		}
		
	}
	
	public function display_order_list( $days_limit = 0, $order_limit = 0 ){
		
		for( $i=0; $i < count( $this->orders ) && ($order_limit == 0 || $i < $order_limit); $i++ ){
			if( $days_limit == 0 || $this->date_within_range( $this->orders[$i]->order_date, $days_limit ) ){
				$order = $this->orders[$i];
				if( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_line.php' ) )	
					include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option('ec_option_base_layout') . '/ec_account_order_line.php' );
				else
					include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option('ec_option_latest_layout') . '/ec_account_order_line.php' );
			}
		}
		
	}
	
	/**
	 * The orders as cards ( 6.0.4, My Account's orders page; template ec_account_order_card.php, one per order ).
	 *
	 * @since 6.0.4
	 */
	public function display_order_cards() {
		$file = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_card.php';
		if ( ! file_exists( $file ) ) {
			$file = EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_account_order_card.php';
		}
		foreach ( $this->orders as $i => $order ) {
			$lines = $this->order_lines( $order->order_id );
			include $file;
		}
	}

	/**
	 * Whether the store keeps its own copy of the order row template ( ec_account_order_line.php ): its orders page then keeps the
	 * table that copy draws.
	 *
	 * @since 6.0.4
	 * @return bool
	 */
	public function uses_line_copy() {
		return file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_account_order_line.php' );
	}

	/** @var array|null Lines of every order in the list, by order id ( order_lines() ). */
	private $line_summaries = null;

	/**
	 * An order's lines for its card: title, quantity and picture, read for every order in the list in one query.
	 *
	 * @since 6.0.4
	 * @param int $order_id Order.
	 * @return array[] Each: title, quantity, image ( address or '' ).
	 */
	public function order_lines( $order_id ) {
		global $wpdb;
		if ( null === $this->line_summaries ) {
			$this->line_summaries = array();
			$ids = array();
			foreach ( $this->orders as $order ) {
				$ids[] = (int) $order->order_id;
			}
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			if ( $ids ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, order_id, title, quantity, image1, is_deconetwork, deconetwork_image_link FROM ec_orderdetail WHERE order_id IN ( ' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' ) ORDER BY orderdetail_id ASC', $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared -- one %d per id.
				foreach ( (array) $rows as $row ) {
					$this->line_summaries[ (int) $row->order_id ][] = array(
						'title'    => trim( wp_strip_all_tags( wp_easycart_language()->convert_text( (string) $row->title ) ) ),
						'quantity' => max( 1, (int) $row->quantity ),
						'image'    => class_exists( 'wp_easycart_order_confirmation' ) ? wp_easycart_order_confirmation::line_image( $row ) : '',
					);
				}
			}
		}
		$order_id = (int) $order_id;
		return isset( $this->line_summaries[ $order_id ] ) ? $this->line_summaries[ $order_id ] : array();
	}

	private function date_within_range( $order_date, $limit ){
		$order_strtotime = strtotime( $order_date );
		$limit_date = strtotime( "-" . $limit . " day" );
		
		if( $order_strtotime > $limit_date )		return true;
		else										return false;
	}
	
}

?>