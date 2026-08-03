<?php
$ec_reset_key  = $this->get_reset_password_key();
$ec_reset_user = $this->get_reset_password_user();
?>
<section class="ec_account_page" id="ec_account_reset_password">

	<div class="ec_cart_left">

		<?php if ( $ec_reset_user ) { ?>

			<?php $this->display_account_reset_password_form_start(); ?>

			<div class="ec_cart_header ec_top"><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_title' ); ?></div>

			<div class="ec_cart_input_row">
				<label for="ec_account_reset_password_new_password"><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_new_password_label' ); ?></label>
				<input type="password" name="ec_account_reset_password_new_password" id="ec_account_reset_password_new_password" class="ec_account_reset_password_input_field" autocomplete="new-password" />
			</div>

			<div class="ec_cart_input_row">
				<label for="ec_account_reset_password_retype_new_password"><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_retype_new_password_label' ); ?></label>
				<input type="password" name="ec_account_reset_password_retype_new_password" id="ec_account_reset_password_retype_new_password" class="ec_account_reset_password_input_field" autocomplete="new-password" />
			</div>

			<div class="ec_cart_button_row">
				<input type="submit" value="<?php echo esc_attr( wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_button' ) ); ?>" class="ec_account_button" />
			</div>

			<?php $this->display_account_reset_password_form_end( $ec_reset_key ); ?>

		<?php } else { ?>

			<div class="ec_cart_header ec_top"><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_title' ); ?></div>

			<div class="ec_account_error">
				<div><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_invalid_link' ); ?></div>
			</div>

			<div class="ec_cart_button_row">
				<a class="ec_account_button" href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'forgot_password' ) ); ?>"><?php echo wp_easycart_language()->get_text( 'account_reset_password', 'account_reset_password_request_new_link' ); ?></a>
			</div>

		<?php } ?>

	</div>

	<div style="clear:both;"></div>
	<div id="ec_current_media_size"></div>

</section>