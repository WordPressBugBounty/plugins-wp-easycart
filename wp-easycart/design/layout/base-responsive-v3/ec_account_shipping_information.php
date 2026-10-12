<?php $ec_account_modern = $this->account_modern(); /* 6.0.4: the account menu beside the page ( ec_accountpage::display_account_navigation() ) and breadcrumbs; an Elementor account widget keeps the older markup */ ?>
<div id="ec_account_shipping_information"<?php echo ( $ec_account_modern ) ? ' class="ec_account_page ec_account_v2 ec_account_forms_v2"' : ''; ?>>
	<?php if ( ! $ec_account_modern ) { ?>
	<?php $this->display_account_navigation_legacy( 'mobile' ); ?>
	<?php } ?>

	<div class="ec_account_left<?php echo ( $ec_account_modern ) ? ' ec_account_main_v2' : ''; ?>">
		<?php if ( $ec_account_modern ) { ?>
		<?php $this->display_account_crumbs( $this->account_crumb_trail( 'shipping_information' ) ); ?>
		<?php } ?>
		<?php $this->display_account_shipping_information_form_start(); ?>

		<div class="ec_cart_header ec_top">
			<?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_title' )?>
		</div>

		<?php if( get_option( 'ec_option_display_country_top' ) ){ ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_country"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' )?>*</label>
			<?php $this->display_account_shipping_information_country_input( ); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_country_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_select_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' ); ?>
			</div>
		</div>
		<?php } ?>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_first_name"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_first_name' )?>*</label>
			<?php $this->display_account_shipping_information_first_name_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_first_name_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_first_name' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_last_name"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_last_name' )?>*</label>
			<?php $this->display_account_shipping_information_last_name_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_last_name_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_last_name' ); ?>
			</div>
		</div>

		<?php if( get_option( 'ec_option_enable_company_name' ) ){ ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_company_name"><?php echo wp_easycart_language( )->get_text( 'cart_shipping_information', 'cart_shipping_information_company_name' ); ?><?php if ( get_option( 'ec_option_enable_company_name_required' ) ) { ?>*<?php }?></label>
			<input type="text" name="ec_account_shipping_information_company_name" id="ec_account_shipping_information_company_name" class="ec_account_shipping_information_input_field" value="<?php echo esc_attr( $GLOBALS['ec_user']->shipping->company_name ); ?>">
			<?php if ( get_option( 'ec_option_enable_company_name_required' ) ) { ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_company_name_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_shipping_information', 'cart_shipping_information_company_name' ); ?>
			</div>
			<?php } ?>
		</div>
		<?php } ?>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_address"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_address' )?>*</label>
			<?php $this->display_account_shipping_information_address_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_address_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_address' ); ?>
			</div>
		</div>
		<?php if( get_option( 'ec_option_use_address2' ) ){ ?>
		<div class="ec_cart_input_row">
			<?php $this->display_account_shipping_information_address2_input(); ?>
		</div>
		<?php }?>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_city"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_city' )?>*</label>
			<?php $this->display_account_shipping_information_city_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_city_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_city' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_state"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_state' )?><span id="ec_shipping_state_required">*</span></label>
			<?php $this->display_account_shipping_information_state_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_state_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_state' ); ?>
			</div>
		</div>

		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_zip"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_zip' )?>*</label>
			<?php $this->display_account_shipping_information_zip_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_zip_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_zip' ); ?>
			</div>
		</div>

		<?php if( !get_option( 'ec_option_display_country_top' ) ){ ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_country"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' )?>*</label>
			<?php $this->display_account_shipping_information_country_input( ); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_country_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_select_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_country' ); ?>
			</div>
		</div>
		<?php } ?>

		<?php if( get_option( 'ec_option_collect_user_phone' ) ){ ?>
		<div class="ec_cart_input_row">
			<label for="ec_account_shipping_information_phone"><?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_phone' )?>*</label>
			<?php $this->display_account_shipping_information_phone_input(); ?>
			<div class="ec_cart_error_row" id="ec_account_shipping_information_phone_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_phone' ); ?>
			</div>
		</div>
		<?php }?>

		<div class="ec_cart_button_row">
			<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_update_button' ); ?>" class="ec_account_button" onclick="return ec_account_shipping_information_update_click( );" />
			<?php $this->display_account_shipping_information_cancel_link( wp_easycart_language( )->get_text( 'account_shipping_information', 'account_shipping_information_cancel' ) ); ?>
		</div>

		<?php $this->display_account_shipping_information_form_end(); ?>

	</div>

	<?php if ( $ec_account_modern ) { ?>
	<?php $this->display_account_navigation( 'shipping_information' ); ?>
	<?php } else { ?>
	<?php $this->display_account_navigation_legacy( 'right' ); ?>
	<?php } ?>
</div>
<div style="clear:both;"></div>
<div id="ec_current_media_size"></div>
<?php if ( get_option( 'ec_option_cache_prevent' ) ) { ?>
<script type="text/javascript">
	wpeasycart_account_billing_country_update( );
	wpeasycart_account_shipping_country_update( );
	jQuery( document.getElementById( 'ec_account_billing_information_country' ) ).change( function( ){ wpeasycart_account_billing_country_update( ); } );
	jQuery( document.getElementById( 'ec_account_shipping_information_country' ) ).change( function( ){ wpeasycart_account_shipping_country_update( ); } );
</script>
<?php }?>