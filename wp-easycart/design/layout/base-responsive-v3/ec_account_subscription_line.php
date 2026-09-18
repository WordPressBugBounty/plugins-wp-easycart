<?php
/**
 * Account > Subscriptions list: one subscription.
 *
 * 6.0.0: card row. Included by ec_subscription_list::display_subscription_list() and
 * display_canceled_subscription_list(). Available: $subscription ( ec_subscription ), $i ( row index ), $this ( ec_subscription_list ).
 */
$ec_line_status = $subscription->get_status_key();
$ec_line_ended  = $subscription->is_canceled();
$ec_line_next   = $subscription->get_next_payment_timestamp();
$ec_line_last   = $subscription->get_last_payment_timestamp();
$ec_line_price  = $subscription->get_price_parts();
$ec_line_image  = $subscription->get_image_url();
$ec_line_title  = wp_easycart_language()->convert_text( $subscription->title );
$ec_line_url    = wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription->subscription_id ) );
?>
<div class="ec_account_subscription_card is-<?php echo esc_attr( $ec_line_status ); ?><?php echo ( $ec_line_ended ) ? ' is-ended' : ''; ?>">
	<a class="ec_account_subscription_card_media" href="<?php echo esc_url( $ec_line_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' != $ec_line_image ) { ?>
		<img src="<?php echo esc_url( $ec_line_image ); ?>" alt="" loading="lazy" />
		<?php } else { ?>
		<span class="ec_account_subscription_v2_media_placeholder"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( $ec_line_title ), 0, 1 ) : substr( wp_strip_all_tags( $ec_line_title ), 0, 1 ) ); ?></span>
		<?php } ?>
	</a>
	<div class="ec_account_subscription_card_body">
		<div class="ec_account_subscription_card_head">
			<a class="ec_account_subscription_card_title" href="<?php echo esc_url( $ec_line_url ); ?>"><?php echo wp_easycart_escape_html( $ec_line_title ); ?></a>
			<span class="ec_account_subscription_v2_badge is-<?php echo esc_attr( $ec_line_status ); ?>"><?php echo wp_easycart_escape_html( $subscription->get_status_label() ); ?></span>
		</div>
		<div class="ec_account_subscription_card_price">
			<span class="ec_account_subscription_card_amount"><?php echo esc_html( $ec_line_price['amount'] ); ?></span><span class="ec_account_subscription_v2_period"><?php echo esc_html( $ec_line_price['period'] ); ?></span>
		</div>
		<div class="ec_account_subscription_card_dates">
			<?php if ( $ec_line_next && in_array( $ec_line_status, array( 'active', 'trialing', 'past_due', 'incomplete' ), true ) ) { ?>
			<span class="ec_account_subscription_card_date"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_header_2' ) ); ?>: <b><?php echo esc_html( ec_subscription::format_date( $ec_line_next ) ); ?></b></span>
			<?php } else if ( $ec_line_next && 'canceling' == $ec_line_status ) { ?>
			<span class="ec_account_subscription_card_date"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_ends', 'Ends' ) ); ?>: <b><?php echo esc_html( ec_subscription::format_date( $ec_line_next ) ); ?></b></span>
			<?php } else if ( $ec_line_ended && $ec_line_next && $ec_line_next <= time() ) { ?>
			<span class="ec_account_subscription_card_date"><?php echo wp_easycart_escape_html( ec_subscription::get_text( 'subscription_details_ended', 'Ended' ) ); ?>: <b><?php echo esc_html( ec_subscription::format_date( $ec_line_next ) ); ?></b></span>
			<?php } ?>
			<?php if ( $ec_line_last ) { ?>
			<span class="ec_account_subscription_card_date"><?php echo wp_easycart_escape_html( wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_header_3' ) ); ?>: <b><?php echo esc_html( ec_subscription::format_date( $ec_line_last ) ); ?></b></span>
			<?php } ?>
		</div>
	</div>
	<div class="ec_account_subscription_card_action">
		<?php $subscription->display_subscription_link( wp_easycart_language()->get_text( 'account_subscriptions', 'account_subscriptions_view_subscription_button' ) ); ?>
	</div>
</div>
