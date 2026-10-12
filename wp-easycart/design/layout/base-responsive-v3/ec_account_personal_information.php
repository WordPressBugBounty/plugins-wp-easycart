<?php $ec_account_modern = $this->account_modern(); /* 6.0.4: the account menu beside the page ( ec_accountpage::display_account_navigation() ) and breadcrumbs; an Elementor account widget keeps the older markup */ ?>
<div id="ec_account_personal_information"<?php echo ( $ec_account_modern ) ? ' class="ec_account_page ec_account_v2 ec_account_forms_v2"' : ''; ?>>
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>

	<div class="ec_account_left<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) { ?>
		<?php $this->display_account_crumbs( $this->account_crumb_trail( 'personal_information' ) ); ?>
		<?php } ?>
		<?php $this->display_account_personal_information_form_start( ); ?>
		<div class="ec_cart_header ec_top"><?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_title' )?></div>

		<div class="ec_cart_input_row">
			<label for="ec_account_personal_information_first_name"><?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_first_name' )?></label>
			<?php $this->display_account_personal_information_first_name_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_personal_information_first_name_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_first_name' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<label for="ec_account_personal_information_last_name"><?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_last_name' )?></label>
			<?php $this->display_account_personal_information_last_name_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_personal_information_last_name_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_last_name' ); ?>
			</div>
		</div>

		<?php if( get_option( 'ec_option_collect_vat_registration_number' ) ){ ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_personal_information_vat_registration_number"><?php echo wp_easycart_language( )->get_text( 'cart_billing_information', 'cart_billing_information_vat_registration_number' ); ?></label>
			<?php $this->display_account_personal_information_vat_registration_number_input(); ?>
		</div>
		<?php }?>

		<div class="ec_cart_input_row">
			<label for="ec_account_personal_information_email"><?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email' )?></label>
			<?php $this->display_account_personal_information_email_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_personal_information_email_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email' ); ?>
			</div>
		</div>

		<?php if( get_option( 'ec_option_enable_extra_email' ) ) { ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_personal_information_email_other"><?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_email_other' )?></label>
			<?php $this->display_account_personal_information_email_other_input(); ?>
		</div>
		<?php }?>

		<?php if( get_option( 'ec_option_show_subscriber_feature' ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_account_personal_information_is_subscriber_input(); ?> <?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_subscribe' )?>
		</div>
		<?php }?>

		<?php do_action( 'wpeasycart_account_personal_information_fields', $this ); /* 6.0.2: remembered checkout answers ( WP EasyCart PRO checkout fields ) */ ?>

		<div class="ec_cart_button_row">
			<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_update_button' ); ?>" class="ec_account_button" onclick="return ec_account_personal_information_update_click( );" />
			<?php $this->display_account_personal_information_cancel_link( wp_easycart_language( )->get_text( 'account_personal_information', 'account_personal_information_cancel_link' ) ); ?>
		</div>

		<?php $this->display_account_personal_information_form_end( ); ?>
	</div>

	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'personal_information' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>
</div>
<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>