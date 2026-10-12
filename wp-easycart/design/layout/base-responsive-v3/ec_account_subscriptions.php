<?php $ec_account_modern = $this->account_modern(); /* 6.0.4: the account menu beside the page ( ec_accountpage::display_account_navigation() ) and breadcrumbs; an Elementor account widget keeps the older markup */ ?>
<div id="ec_account_subscriptions"<?php echo ( $ec_account_modern ) ? ' class="ec_account_page ec_account_v2"' : ''; ?>>
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>

	<?php
	/* 6.0.0: card list. "Active" holds every subscription still running ( active, trial, past due, paused, cancels at period end ); ended ones ( canceled, expired ) are listed separately and only when there are some. */
	$ec_subs_active = $this->subscriptions->get_active_count();
	$ec_subs_ended  = $this->subscriptions->get_ended_count();
	?>
	<div class="ec_account_left ec_account_subscriptions_v2<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) { ?>
		<?php $this->display_account_crumbs( $this->account_crumb_trail( 'subscriptions' ) ); ?>
		<?php } ?>
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_title' ) ); ?></div>
		<div class="ec_account_subscriptions_holder ec_account_active_subscriptions_list">
			<?php if ( $ec_subs_active > 0 ) { ?>
			<div class="ec_account_subscriptions_row ec_account_subscriptions_v2_list" id="ec_subscriptions_list">
				<?php $this->subscriptions->display_subscription_list(); // Prints ec_account_subscription_line.php for each subscription. ?>
			</div>
			<?php } else { ?>
			<div class="ec_account_subscriptions_v2_empty">
				<div class="ec_subscription_none_found"><?php echo wp_easycart_escape_html( ( $ec_subs_ended > 0 ) ? ec_subscription::get_text( 'account_subscriptions_none_active', 'You have no active subscriptions.' ) : wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_none_found' ) ); ?></div>
			</div>
			<?php } ?>
		</div>
		<?php if ( $ec_subs_ended > 0 ) { ?>
		<div class="ec_cart_header"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'account_canceled_subscriptions_title' ) ); ?></div>
		<div class="ec_account_subscriptions_row ec_account_subscriptions_v2_list is-ended" id="ec_canceled_subscriptions_list">
			<?php $this->subscriptions->display_canceled_subscription_list(); // Prints ec_account_subscription_line.php for each ended subscription. ?>
		</div>
		<?php } ?>
	</div>

	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'subscriptions' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>
</div>

<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>