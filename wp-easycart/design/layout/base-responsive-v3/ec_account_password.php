<?php $ec_account_modern = $this->account_modern(); /* 6.0.4: the account menu beside the page ( ec_accountpage::display_account_navigation() ) and breadcrumbs; an Elementor account widget keeps the older markup */ ?>
<section class="ec_account_page<?php echo ( $ec_account_modern ) ? ' ec_account_v2 ec_account_forms_v2' : ''; ?>" id="ec_account_password">
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>

	<div class="ec_account_left<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) { ?>
		<?php $this->display_account_crumbs( $this->account_crumb_trail( 'password' ) ); ?>
		<?php } ?>
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_title' )?></div>
		<div class="ec_account_password_main_sub_title"><?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_sub_title' )?></div>

		<?php $this->display_account_password_form_start( ); ?>
		<?php do_action( 'wpeasycart_change_password_top' ); ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_password_current_password"><?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' )?></label>
			<?php $this->display_account_password_current_password(); ?>
			<div class="ec_cart_error_row" id="ec_account_password_current_password_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_current_password' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<?php do_action( 'wpeasycart_pre_password_display' ); ?>
			<label for="ec_account_password_new_password"><?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_new_password' )?></label>
			<?php $this->display_account_password_new_password(); ?>
			<div class="ec_cart_error_row" id="ec_account_password_new_password_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_length_error' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<label for="ec_account_password_retype_new_password"><?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_retype_new_password' )?></label>
			<?php $this->display_account_password_retype_new_password(); ?>
			<div class="ec_cart_error_row" id="ec_account_password_retype_new_password_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_passwords_do_not_match' ); ?>
			</div>
		</div>

		<div class="ec_cart_button_row">
			<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'account_password', 'account_password_update_button' ); ?>" class="ec_account_button" onclick="<?php $ec_password_save_js_function = apply_filters( 'wpeasycart_update_password_js_function', 'return ec_account_password_button_click( );' ); echo esc_attr( $ec_password_save_js_function ); ?>" />
			<?php $this->display_account_password_cancel_link( wp_easycart_language( )->get_text( 'account_password', 'account_password_cancel_button' ) ); ?>
		</div>

		<?php $this->display_account_password_form_end( ); ?>
	</div>

	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'password' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>
	<div style="clear:both;"></div>
	<div id="ec_current_media_size"></div>
</section>
