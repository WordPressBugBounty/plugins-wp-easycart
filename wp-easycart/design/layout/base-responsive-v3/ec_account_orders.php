<?php
/* 6.0.4: the account menu beside the page and the orders as cards ( ec_account_order_card.php, the look of the subscriptions list ),
   with breadcrumbs. Inside an Elementor account widget ( account_modern() false ), or with the store's own copy of
   ec_account_order_line.php, the orders keep the table that widget's settings and that copy style. */
$ec_account_modern = $this->account_modern();
$ec_orders_cards   = $ec_account_modern && ! $this->orders->uses_line_copy();
?>
<section class="ec_account_page<?php echo ( $ec_account_modern ) ? ' ec_account_v2' : ''; ?>" id="ec_account_orders">
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>
	<div class="ec_account_left<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) { ?>
		<?php $this->display_account_crumbs( $this->account_crumb_trail( 'orders' ) ); ?>
		<?php } ?>
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_language( )->get_text( 'account_orders', 'account_orders_title' )?></div>
		<?php if ( $this->orders->num_orders > 0 ) { ?>
		<?php if ( $ec_orders_cards ) { ?>
		<div class="ec_account_orders_holder ec_account_orders_v2_list" id="ec_orders_list">
			<?php $this->orders->display_order_cards(); // prints ec_account_order_card.php for each order ?>
		</div>
		<?php } else { ?>
		<div class="ec_account_orders_holder">
			<div class="ec_account_order_line_header">
				<div class="ec_account_order_line_column1_header"><?php echo wp_easycart_language( )->get_text( 'account_orders', 'account_orders_header_1' )?></div>
				<div class="ec_account_order_line_column2_header"><?php echo wp_easycart_language( )->get_text( 'account_orders', 'account_orders_header_2' )?></div>
				<div class="ec_account_order_line_column3_header"><?php echo wp_easycart_language( )->get_text( 'account_orders', 'account_orders_header_3' )?></div>
				<div class="ec_account_order_line_column4_header"><?php echo wp_easycart_language( )->get_text( 'account_orders', 'account_orders_header_4' )?></div>
				<div class="ec_account_order_line_column5_header"></div>
			</div>
			<div class="ec_account_orders_row" id="ec_orders_list">
				<?php $this->orders->display_order_list( ); //prints out a list of orders of type ec_account_order_line.php ?>
			</div>
		</div>
		<?php } ?>
		<?php } else if ( $ec_account_modern ) { ?>
		<div class="ec_account_orders_v2_empty">
			<div class="ec_account_orders_v2_empty_text"><?php echo wp_easycart_language( )->get_text( "account_dashboard", "account_dashboard_recent_orders_none" ); ?></div>
			<?php if ( $this->store_page ) { ?>
			<a class="ec_account_orders_v2_shop" href="<?php echo esc_url( $this->store_page ); ?>"><?php echo esc_html( ec_accountpage::account_phrase( 'cart', 'cart_continue_shopping', 'Continue Shopping' ) ); ?></a>
			<?php } ?>
		</div>
		<?php } else { echo wp_easycart_language( )->get_text( "account_dashboard", "account_dashboard_recent_orders_none" ); }?>
	</div>
	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'orders' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>

	<div style="clear:both;"></div>
	<div id="ec_current_media_size"></div>
</section>
