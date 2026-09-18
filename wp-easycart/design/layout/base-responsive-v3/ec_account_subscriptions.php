<div id="ec_account_subscriptions">
	<div class="ec_account_mobile">
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_navigation', 'account_navigation_title' ) ); ?></div>
		<?php do_action( 'wpeasycart_account_links' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_billing_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_billing_information' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_billing' ); ?>
		<?php if( get_option( 'ec_option_use_shipping' ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_shipping_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_shipping_information' ) ); ?>
		</div>
		<?php }?>
		<?php do_action( 'wpeasycart_account_links_after_shipping' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_personal_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_basic_inforamtion' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_personal' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_password_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_password' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_password' ); ?>
		<?php if( $this->using_subscriptions( ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_subscriptions_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_subscriptions' )); ?>
		</div>
		<?php }?>
		<?php do_action( 'wpeasycart_account_links_after_subscriptions' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_logout_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_sign_out' )); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_end' ); ?>
	</div>

	<?php
	/* 6.0.0: card list. "Active" holds every subscription still running ( active, trial, past due, paused, cancels at period end ); ended ones ( canceled, expired ) are listed separately and only when there are some. */
	$ec_subs_active = $this->subscriptions->get_active_count();
	$ec_subs_ended  = $this->subscriptions->get_ended_count();
	?>
	<div class="ec_account_left ec_account_subscriptions_v2">
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

	<div class="ec_account_right">
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_navigation', 'account_navigation_title' ) ); ?></div>
		<?php do_action( 'wpeasycart_account_links' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_billing_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_billing_information' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_billing' ); ?>
		<?php if( get_option( 'ec_option_use_shipping' ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_shipping_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_shipping_information' ) ); ?>
		</div>
		<?php }?>
		<?php do_action( 'wpeasycart_account_links_after_shipping' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_personal_information_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_basic_inforamtion' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_personal' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_password_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_password' ) ); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_after_password' ); ?>
		<?php if( $this->using_subscriptions( ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_subscriptions_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_subscriptions' )); ?>
		</div>
		<?php }?>
		<?php do_action( 'wpeasycart_account_links_after_subscriptions' ); ?>
		<div class="ec_cart_input_row">
			<?php $this->display_logout_link( wp_easycart_language( )->get_text( 'account_navigation', 'account_navigation_sign_out' )); ?>
		</div>
		<?php do_action( 'wpeasycart_account_links_end' ); ?>
	</div>
</div>

<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>