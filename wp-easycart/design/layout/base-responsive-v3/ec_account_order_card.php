<?php
/**
 * Account > Orders: one order as a card ( 6.0.4, the look of the subscriptions list ).
 *
 * Included by ec_orderlist::display_order_cards() for each order. Available: $order ( ec_orderdisplay ), $lines ( its lines: title,
 * quantity, image ), $i ( row index ), $this ( ec_orderlist ).
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ec_card_tone  = $order->account_tone();
$ec_card_url   = wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order->order_id ) );
$ec_card_title = str_replace( '[order_id]', (string) (int) $order->order_id, ec_accountpage::account_phrase( 'account_navigation', 'account_navigation_order_number', 'Order #[order_id]' ) );
$ec_card_image = '';
$ec_card_units = 0;
$ec_card_names = array();
foreach ( (array) $lines as $ec_card_line ) {
	if ( '' === $ec_card_image && '' !== $ec_card_line['image'] ) {
		$ec_card_image = $ec_card_line['image'];
	}
	$ec_card_units  += (int) $ec_card_line['quantity'];
	$ec_card_names[] = $ec_card_line['title'] . ( ( (int) $ec_card_line['quantity'] > 1 ) ? ' × ' . (int) $ec_card_line['quantity'] : '' );
}
$ec_card_more  = max( 0, count( $ec_card_names ) - 2 );
$ec_card_items = implode( ', ', array_slice( $ec_card_names, 0, 2 ) );
if ( $ec_card_more > 0 ) {
	$ec_card_items .= ' ' . str_replace( '[count]', (string) $ec_card_more, ec_accountpage::account_phrase( 'account_orders', 'account_orders_and_more', 'and [count] more' ) );
}
$ec_card_count = ( 1 === $ec_card_units ) ? ec_accountpage::account_phrase( 'account_orders', 'account_orders_item_one', '1 item' ) : str_replace( '[count]', (string) $ec_card_units, ec_accountpage::account_phrase( 'account_orders', 'account_orders_item_many', '[count] items' ) );
/* Settings › Documents › Customer downloads can turn the print link off, and a document rule can for some orders ( as on the order's page ). */
$ec_card_print = class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'customer_download' ) ? wp_easycart_documents::customer_download( 'print_receipt', (int) $order->order_id ) : get_option( 'ec_option_account_print_receipt', 1 );
ob_start();
$order->display_order_date( get_option( 'date_format' ) );
$ec_card_date = trim( (string) ob_get_clean() );
ob_start();
$order->display_grand_total();
$ec_card_total = trim( (string) ob_get_clean() );
?>
<div class="ec_account_order_card is-<?php echo esc_attr( $ec_card_tone ); ?>" id="ec_account_order_card_<?php echo (int) $order->order_id; ?>">
	<a class="ec_account_order_card_media" href="<?php echo esc_url( $ec_card_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' !== $ec_card_image ) { ?>
		<img src="<?php echo esc_url( $ec_card_image ); ?>" alt="" loading="lazy" decoding="async" />
		<?php } else { ?>
		<span class="ec_account_order_card_placeholder"></span>
		<?php } ?>
		<?php if ( count( $ec_card_names ) > 1 ) { ?>
		<span class="ec_account_order_card_more">+<?php echo (int) ( count( $ec_card_names ) - 1 ); ?></span>
		<?php } ?>
	</a>
	<div class="ec_account_order_card_body">
		<div class="ec_account_order_card_head">
			<a class="ec_account_order_card_title" href="<?php echo esc_url( $ec_card_url ); ?>"><?php echo esc_html( $ec_card_title ); ?></a>
			<span class="ec_account_order_card_badge is-<?php echo esc_attr( $ec_card_tone ); ?>"><?php echo esc_html( $order->account_status_text() ); ?></span>
		</div>
		<?php if ( '' !== $ec_card_items ) { ?>
		<div class="ec_account_order_card_items"><?php echo esc_html( $ec_card_items ); ?></div>
		<?php } ?>
		<div class="ec_account_order_card_meta">
			<span class="ec_account_order_card_date"><?php echo esc_html( $ec_card_date ); ?></span>
			<?php if ( $ec_card_units > 0 ) { ?>
			<span class="ec_account_order_card_count"><?php echo esc_html( $ec_card_count ); ?></span>
			<?php } ?>
			<span class="ec_account_order_card_total"><?php echo esc_html( html_entity_decode( $ec_card_total, ENT_QUOTES, 'UTF-8' ) ); ?></span>
		</div>
		<?php do_action( 'wp_easycart_account_order_card', $order ); ?>
	</div>
	<div class="ec_account_order_card_action">
		<?php $order->display_order_link( wp_easycart_language()->get_text( 'account_orders', 'account_orders_view_order_button' ) ); ?>
		<?php if ( $ec_card_print ) { ?>
		<a class="ec_account_order_card_print" href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => (int) $order->order_id ) ) ); ?>" target="_blank"><?php echo esc_html( ec_accountpage::account_phrase( 'cart_success', 'cart_success_print_receipt_text', 'Print Receipt' ) ); ?></a>
		<?php } ?>
	</div>
</div>
