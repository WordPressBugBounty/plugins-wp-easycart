<?php
/**
 * WP EasyCart Account Dashboard Orders Widget Display for Elementor
 *
 * @package  Wp_Easycart_Elementor_Account_Dashboard_Orders_Widget
 * @author WP EasyCart
 */
$args = shortcode_atts(
	array(
		'order_columns_repeater' => array(),
		'view_orders_button_text' => wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_all_orders_linke' ),
		'no_orders_text' => wp_easycart_language( )->get_text( "account_dashboard", "account_dashboard_recent_orders_none" ),
		'track_shipment_button_text' => 'Track Shipment',
		'buy_again_button_text' => wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_buy_item_again' ),
		'enable_pagination' => 'no',
		'enable_view_all' => 'yes',
		'max_orders' => 5,
		'orders_per_page' => 5,
		'show_order_item_image' => 'yes',
		'show_order_item_details' => 'yes',
		'show_order_item_buttons' => 'yes',
		'orderstatus_ids' => array(),
		'status_indicators_repeater' => array(),
	),
	$atts
);

global $wpdb;
$orders = new ec_orderlist( $GLOBALS['ec_user']->user_id );
$ec_db = new ec_db();

if ( $orders->num_orders > 0 ) {
	$is_first_order = true;
	$enable_pagination = ( 'yes' == $args['enable_pagination'] ) ? true : false;
	$max_orders = ( ! $enable_pagination && (int) $args['max_orders'] > 0 ) ? (int) $args['max_orders'] : $orders->num_orders;
	$per_page = ( $enable_pagination && (int) $args['orders_per_page'] > 0 ) ? (int) $args['orders_per_page'] : $orders->num_orders;
	$current_order = 0;
	$total_orders_displayed = 0; ?>
<div class="wp-easycart-orders-wrapper">
<?php
	for ( $current_order; $current_order < count( $orders->orders ) && $total_orders_displayed < $max_orders; $current_order++ ) {
		if ( ! is_array( $args['orderstatus_ids'] ) || count( $args['orderstatus_ids'] ) == 0 || ( is_array( $args['orderstatus_ids'] ) && count( $args['orderstatus_ids'] ) > 0 && in_array( $orders->orders[ $current_order ]->orderstatus_id, $args['orderstatus_ids'] ) ) ) {
			$is_first_order_item = true;
			$order = $orders->orders[ $current_order ]; 
			$order_details = $ec_db->get_order_details( $order->order_id, $GLOBALS['ec_user']->user_id ); ?>
<div class="wp-easycart-order-item"<?php if ( $total_orders_displayed >= $per_page ) { ?> style="display:none;"<?php }?>>
	<div class="wp-easycart-orders-header-row <?php if ( ! $is_first_order ) {?> ec_account_order_header_row_not_first <?php } ?>">
		<?php foreach ( $args['order_columns_repeater'] as $column ) { ?>
			<div class="wp-easycart-orders-column wp-easycart-orders-column-align-<?php echo esc_attr( ( ( isset( $column['column_alignment'] ) ) ? $column['column_alignment'] : 'none' ) ); ?>">
				<span class="wp-easycart-orders-column-header"><?php $this->wp_easycart_elementor_dashboard_order_list_value( $column, $order, 'header' ); ?></span>
				<span class="wp-easycart-orders-column-content"><?php $this->wp_easycart_elementor_dashboard_order_list_value( $column, $order, 'column' ); ?></span>
			</div>
		<?php }?>
	</div>

	<?php foreach ( $order_details as $detail ) {
		$order_item = new ec_orderdetail( $detail ); ?>
		<div class="wp-easycart-order-item-row">
			<?php if ( ! isset( $args['show_order_item_image'] ) || 'yes' == $args['show_order_item_image'] ) { ?>
			<div class="wp-easycart-order-item-image">
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'before_image', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
				<?php $order_item->display_image( "small" ); ?>
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'after_image', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
			</div>
			<?php }?>
			<?php if ( ! isset( $args['show_order_item_details'] ) || 'yes' == $args['show_order_item_details'] ) { ?>
			<div class="wp-easycart-order-item-details">
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'before_title', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
				<span class="ec_account_order_item_title"><?php $order_item->display_title(); ?><?php if( $detail->quantity > 1 ){ ?> (<?php $order_item->display_quantity(); ?>)<?php }?></span>
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'after_title', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
				<?php do_action( 'wpeasycart_dashboard_recent_order_item', $order_item ); ?>
				<?php $advanced_optionitem_download_allowed = true;
				if ( $order_item->use_advanced_optionset ) {
					$advanced_options = $ec_db->get_order_options( $order_item->orderdetail_id );
					foreach ( $advanced_options as $advanced_option ) {
						if ( ! $advanced_option->optionitem_allow_download ) {
							$advanced_optionitem_download_allowed = false;
						}
						if ( $advanced_option->option_type == "file" ) {
							$file_split = explode( "/", $advanced_option->option_value );
							echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . esc_attr( $file_split[1] ) . "</span>";
						} else if( $advanced_option->option_type == "grid" ) {
							echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ":</span> <span class=\"ec_option_name\">" . wp_easycart_escape_html( $advanced_option->optionitem_name . " (" . $advanced_option->option_value . ")" ) . "</span>";
						} else {
							echo "<span>" . wp_easycart_escape_html( $advanced_option->option_label ) . ": " . esc_attr( $advanced_option->option_value ) . "</span>";
						}
					}
				} else {
					if ( $order_item->has_option1() ) {
						echo "<span>"; $order_item->display_option1( ); 
						if( $order_item->has_option1_price( ) ){ 
							echo "("; $order_item->display_option1_price( ); echo ")";
						}
						echo "</span>";
					}
					if ( $order_item->has_option2() ) {
						echo "<span>"; $order_item->display_option2( ); 
						if( $order_item->has_option2_price( ) ){ 
							echo "("; $order_item->display_option2_price( ); echo ")";
						}
						echo "</span>";
					}
					if ( $order_item->has_option3() ) {
						echo "<span>"; $order_item->display_option3( ); 
						if( $order_item->has_option3_price( ) ){ 
							echo "("; $order_item->display_option3_price( ); echo ")";
						}
						echo "</span>";
					}
					if ( $order_item->has_option4() ) {
						echo "<span>"; $order_item->display_option4( ); 
						if( $order_item->has_option4_price( ) ){ 
							echo "("; $order_item->display_option4_price( ); echo ")";
						}
						echo "</span>";
					}
					if ( $order_item->has_option5() ) {
						echo "<span>"; $order_item->display_option5( ); 
						if( $order_item->has_option5_price( ) ){ 
							echo "("; $order_item->display_option5_price( ); echo ")";
						}
						echo "</span>";
					}
				}
				if ( $order_item->has_gift_card_message() ) {
					echo "<span>";
					$order_item->display_gift_card_message( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_message' ) ); 
					echo "</span>";
				}
				if( $order_item->has_gift_card_from_name() ) {
					echo "<span>";
					$order_item->display_gift_card_from_name( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_from' ) );
					echo "</span>";
				}
				if ( $order_item->has_gift_card_to_name() ) {
					echo "<span>";
					$order_item->display_gift_card_to_name( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_gift_to' ) );
					echo "</span>";
				}
				if ( $order_item->has_print_gift_card_link() && $order->is_approved ) {
					echo "<span>";
					$order_item->display_print_online_link( wp_easycart_language( )->get_text( "account_order_details", "account_orders_details_print_online" ) ); 
					echo "</span>";
				}
				if ( $order_item->include_code && $order->is_approved ) {
					$codes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ec_code WHERE ec_code.orderdetail_id = %d", $cart->cart[$i]->orderdetail_id ) );
					$code_list = "";
					for ( $code_index = 0; $code_index < count( $codes ); $code_index++ ) {
						if( $code_index > 0 )
							$code_list .= ", ";
						$code_list .= $codes[$code_index]->code_val;
					}
					echo "<span>";
					echo wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_your_codes' ); 
					echo esc_attr( $code_list );
					echo "</span>";
				 }

				if ( $order->has_membership_page() ) {
					echo "<span><a href=\"" . esc_attr( $order->get_membership_page_link( ) ) . "\">" . wp_easycart_language( )->get_text( "cart_success", "cart_payment_complete_line_5" ) . "</a></span>";
				 }
				 ?>
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'after_details', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
				 <span class="ec_account_order_item_price"><?php $order_item->display_unit_price(); ?></span>
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'after_price', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
			</div>
			<?php }?>
			<?php if ( ! isset( $args['show_order_item_buttons'] ) || 'yes' == $args['show_order_item_buttons'] ) { ?>
			<?php $product_link = $order_item->get_product_link(); ?>
			<div class="wp-easycart-order-item-buttons">
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'button_list_start', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
				<?php if ( $is_first_order_item && $order->has_tracking_number() ) {
					if ( 'fedex' == strtolower( $order->shipping_carrier ) ) {
						echo '<span class="ec_account_order_item_buy_button"><a href="https://www.fedex.com/fedextrack/summary?trknbr=' . esc_attr( $order->tracking_number ) . '" target="_blank">' . esc_html( $args['track_shipment_button_text'] ) . '</a></span>';
					} else if ( 'usps' == strtolower( $order->shipping_carrier ) ) {
						echo '<span class="ec_account_order_item_buy_button"><a href="https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=3&text28777=&tLabels=' . esc_attr( $order->tracking_number ) . '" target="_blank">' . esc_html( $args['track_shipment_button_text'] ) . '</a></span>';
					} else if ( 'ups' == strtolower( $order->shipping_carrier ) ) {
						echo '<span class="ec_account_order_item_buy_button"><a href="https://www.ups.com/track?loc=en_US&tracknum=' . esc_attr( $order->tracking_number ) . '" target="_blank">' . esc_html( $args['track_shipment_button_text'] ) . '</a></span>';
					}
				} ?>
				<?php if ( $order_item->has_download_link( ) && $order->is_approved && $advanced_optionitem_download_allowed ) {
					 echo "<span>";
					 $order_item->display_download_link( wp_easycart_language( )->get_text( 'account_order_details', 'account_orders_details_download' ) );
					 echo "</span>";
				}
				if ( $product_link ) { ?>
				<span class="ec_account_order_item_buy_button"><a href="<?php echo esc_attr( $product_link ); ?>"><?php echo esc_html( $args['buy_again_button_text'] ); ?></a></span>
				<?php } ?>
				<?php $this->wp_easycart_elementor_dashboard_process_status_indicators( $order->orderstatus_id, 'button_list_end', $is_first_order_item, $args['status_indicators_repeater'] ); ?>
			</div>
			<?php } ?>
		</div>
	<?php $is_first_order_item = false;
		}
		$total_orders_displayed++;
	?>
</div><?php // Close order item wrap
	}
	$is_first_order = false;
} ?>
<?php if ( $is_first_order ) {
	echo '<div class="wp-easycart-no-orders-found">';
		echo esc_attr( $args['no_orders_text'] );
	echo '</div>';
} else if ( $enable_pagination ) {
	$total_pages = ceil( $total_orders_displayed / $per_page );
?>
	<nav class="wp-easycart-orders-pagination" aria-label="<?php echo esc_html__( 'Orders navigation', 'wp-easycart' ); ?>">
		<input type="hidden" class="wp-easycart-orders-pagination-per-page" value="<?php echo $per_page; ?>">
		<input type="hidden" class="wp-easycart-orders-pagination-total-pages" value="<?php echo $total_pages; ?>">

		<a href="#" class="wp-easycart-orders-page-nav wp-easycart-orders-pagination-first-page" data-page="first">&laquo; <?php echo esc_html__( 'First', 'wp-easycart' ); ?></a>
		<a href="#" class="wp-easycart-orders-page-nav wp-easycart-orders-pagination-prev-page" data-page="prev">&lsaquo; <?php echo esc_html__( 'Prev', 'wp-easycart' ); ?></a>

		<div class="wp-easycart-orders-page-numbers">
			<?php for ( $i = 1; $i <= $total_pages; $i++ ) { ?>
				<a href="#" class="wp-easycart-orders-page-nav wp-easycart-orders-pagination-page-num" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
			<?php } ?>
		</div>

		<a href="#" class="wp-easycart-orders-page-nav wp-easycart-orders-pagination-next-page" data-page="next"><?php echo esc_html__( 'Next', 'wp-easycart' ); ?> &rsaquo;</a>
		<a href="#" class="wp-easycart-orders-page-nav wp-easycart-orders-pagination-last-page" data-page="last"><?php echo esc_html__( 'Last' ); ?> &raquo;</a>
	</nav>
<?php } ?>
</div>

<?php if ( 'yes' == $args['enable_view_all'] ) { ?>
<div class="wp-easycart-orders-all-row">
	<a href="<?php echo esc_attr( $account_page . $permalink_divider ); ?>ec_page=orders" class="wp-easycart-orders-all"><?php echo esc_html( $args['view_orders_button_text'] ); ?></a>
</div>
<?php }?>

<?php } else {
	echo '<div class="wp-easycart-no-orders-found">';
		echo esc_attr( $args['no_orders_text'] );
	echo '</div>';
}
