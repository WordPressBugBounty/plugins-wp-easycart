<?php if ( isset( $_GET['account_error'] ) ) {
	$error_text = wp_easycart_language( )->get_text( "ec_errors", preg_replace( '/[^a-zA-Z0-9\-\_]/', '', sanitize_text_field( $_GET['account_error'] ) ) );
	if ( $error_text ) {
		echo "<div class=\"ec_account_error\"><div>" . esc_attr( $error_text ) . "</div></div>";
	}
} ?>
<section class="ec_account_page" id="ec_account_restricted">
	<div class="ec_restricted"><?php echo wp_easycart_language( )->get_text( 'product_page', 'product_page_restricted_line_1' ); ?></div>
	<?php if( $GLOBALS['ec_user']->user_id == "" || $GLOBALS['ec_user']->user_id == 0 ){ ?>
	<div class="ec_account_left ec_account_login">
		<form action="<?php echo esc_attr( $this->account_page ); ?>" method="POST">  

			<input type="hidden" name="ec_goto_page" value="store" />   

			<div class="ec_cart_header ec_top">
				<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_login_title' )?>
			</div>
			<div class="ec_account_subheader">
				<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_login_sub_title' )?>
			</div>

			<div class="ec_cart_input_row">
				<label for="ec_account_login_widget_email"><?php echo wp_easycart_language( )->get_text( 'account_login', 'account_login_email_label' )?>*</label>
				<input type="text" name="ec_account_login_email" id="ec_account_login_widget_email" class="ec_account_login_input_field" autocomplete="off" autocapitalize="off" />
			</div>

			<div class="ec_cart_error_row" id="ec_account_login_widget_email_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_login', 'cart_login_email_label' ); ?>
			</div>

			<div class="ec_cart_input_row">
				<label for="ec_account_login_widget_password"><?php echo wp_easycart_language( )->get_text( 'account_login', 'account_login_password_label' )?>*</label>
				<input type="password" name="ec_account_login_password" id="ec_account_login_widget_password" class="ec_account_login_input_field" />
			</div>
			<div class="ec_cart_error_row" id="ec_account_login_widget_password_error">
				<?php echo wp_easycart_language( )->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ); ?> <?php echo wp_easycart_language( )->get_text( 'cart_login', 'cart_login_password_label' ); ?>
			</div>

			<?php if( get_option( 'ec_option_enable_recaptcha' ) && get_option( 'ec_option_recaptcha_site_key' ) != '' ){ ?>
				<input type="hidden" id="ec_grecaptcha_response_login_widget" name="ec_grecaptcha_response_login" value="" />
				<input type="hidden" id="ec_grecaptcha_site_key" value="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" />
				<div class="ec_cart_input_row" data-sitekey="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" id="ec_account_login_widget_recaptcha"></div>
			<?php }?>

			 <div class="ec_cart_button_row">
				<?php $this->display_account_login_forgot_password_link( wp_easycart_language( )->get_text( 'account_login', 'account_login_forgot_password_link' ) ); ?>
				<input type="submit" value="<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_login_button' ); ?>" class="ec_account_button" onclick="return ec_account_login_widget_button_click( );" />
			</div>

			<input type="hidden" name="ec_account_form_action" value="login">
			<input type="hidden" name="ec_account_form_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-account-login' ) ); ?>" />
		</form>
	</div>
	<div class="ec_account_right ec_account_login">
		<div class="ec_cart_header ec_top">
			<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_new_user_title' )?>
		</div>

		<div class="ec_account_subheader">
			<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_new_user_sub_title' )?>
		</div>

		<div class="ec_cart_input_row">
			<?php echo wp_easycart_language( )->get_text( 'account_login', 'account_new_user_message' )?>
		</div>

		<div class="ec_cart_button_row">
			<a href="<?php echo esc_attr( $this->account_page ); ?>?ec_page=register" class="ec_account_login_create_account_button"><?php echo wp_easycart_language( )->get_text( 'account_login', 'account_new_user_button' ); ?></a>
		</div>
	</div>
	<?php }?>
</section>
<?php if( get_option( 'ec_option_cache_prevent' ) ){ ?>
<script type="text/javascript">
	if( jQuery( document.getElementById( 'ec_account_login_widget_recaptcha' ) ).length ){
		var wpeasycart_login_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_widget_recaptcha' ), {
			'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
			'callback' : wpeasycart_login_recaptcha_callback
		});
	}
</script>
<?php }?>