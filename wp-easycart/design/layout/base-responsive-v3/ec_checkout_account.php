<?php
/**
 * 6.0.5: Contact, with the email first ( Matt: "retyping email feels redundant", "returning customer box to login feels out of place";
 * the regular checkout, for anything that needs an account, as the subscription page ). Drawn by ec_cartpage::display_checkout_account()
 * at the top of the classic checkout's details page, and on the one-page checkout when the order needs an account.
 *
 * - The order needs an account ( a download, a members-only page, no guest checkout: wp_easycart_checkout_signup::required() ): the email
 *   and Continue. An email that has an account asks for its password ( Sign in ); a new email asks for a password for the new account,
 *   or, with "New customers choose a password after paying" on, is locked for the session and the checkout opens ( the account is made
 *   when the order is placed ). Change goes back to the email. The step is the subscription page's ( #ec_subscription_account, its
 *   classes and ec-store.js wpeasycart_subscription_* ): data-open names the states that open the rest of the checkout ( the classic
 *   page saves the new account's password with its details ), data-reload asks for the page again when the rest is not drawn yet ( the
 *   one-page checkout draws the address, shipping and payment only once the email is settled ).
 * - Guest checkout ( the classic page ): the email, "Already have an account? Log in" opening the password right under it ( no second
 *   email ), a note when the email typed has an account, and Create Account with a single password.
 *
 * No retyped email or password: the email is shown back once settled, a likely typo is suggested ( ec-store.js
 * wpeasycart_email_suggestion() ) and every password can be shown ( .ec_password_toggle ).
 *
 * @package wp-easycart
 * @since   6.0.5
 *
 * @var ec_cartpage $cartpage    The checkout.
 * @var string      $wpec_layout classic | onepage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpec_signup   = wp_easycart_checkout_signup::page_state( $cartpage );
$wpec_required = (bool) $wpec_signup['required'];
$wpec_state    = (string) $wpec_signup['state'];
$wpec_email    = (string) $wpec_signup['email'];
$wpec_mark     = ( '' !== $wpec_email ) ? ( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $wpec_email, 0, 1, 'UTF-8' ), 'UTF-8' ) : strtoupper( substr( $wpec_email, 0, 1 ) ) ) : '';
$wpec_classic  = ( 'classic' === $wpec_layout );
$wpec_dynamic  = $wpec_classic ? (bool) get_option( 'ec_option_cache_prevent' ) : wp_easycart_cart_is_dynamic();
$wpec_cart_id  = $GLOBALS['ec_cart_data']->ec_cart_id;
$wpec_show     = wp_strip_all_tags( wp_easycart_checkout_signup::text( 'checkout_password_show', 'Show' ) );
$wpec_hide     = wp_strip_all_tags( wp_easycart_checkout_signup::text( 'checkout_password_hide', 'Hide' ) );
?>
<div class="ec_cart_header ec_cart_header_no_border ec_cart_contact_header_v2<?php echo $wpec_classic ? ' ec_top' : ''; ?>" id="ec_cart_contact_header">
	<span><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_onepage', 'contact' ) ); ?></span>
	<?php if ( ! $wpec_required ) { ?>
	<a href="#" role="button" id="ec_user_login_link" class="ec_cart_login_header_link ec_cart_login_toggle_v2 ec_checkout_signin_toggle" aria-controls="ec_user_login_form" aria-expanded="false"><span class="ec_cart_login_toggle_question"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_already_have_account2' ) ); ?></span> <span class="ec_cart_login_toggle_action"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_already_have_account_login' ) ); ?></span></a>
	<?php } ?>
</div>

<?php if ( $wpec_required && ! $wpec_classic ) { ?>
<div id="ec_cart_create_account_loader" class="wpec-busy ec_cart_create_account_wait" style="display:none; position:fixed; top:0; right:0; bottom:0; left:0; z-index:999990;">
	<div class="wpec-busy-card" role="status" aria-live="polite">
		<span class="wpec-busy-spinner" aria-hidden="true"></span>
		<span class="wpec-busy-status"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_payment_information', 'create_account_please_wait' ) ); ?></span>
	</div>
</div>
<?php } ?>

<div class="ec_subscription_account ec_checkout_account ec_checkout_account_<?php echo esc_attr( $wpec_classic ? 'classic' : 'onepage' ); ?>" id="ec_subscription_account" data-state="<?php echo esc_attr( $wpec_state ); ?>" data-later="<?php echo $wpec_signup['later'] ? '1' : '0'; ?>" data-nonce="<?php echo esc_attr( $wpec_signup['nonce'] ); ?>" data-context="checkout" data-open="<?php echo $wpec_classic ? 'new later' : 'later'; ?>" data-reload="<?php echo $wpec_classic ? '0' : '1'; ?>">

	<div class="ec_cart_input_row ec_subscription_email_entry">
		<label for="ec_contact_email"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_email' ) ); ?>*</label>
		<div class="ec_subscription_email_field">
			<input type="email" name="ec_contact_email" id="ec_contact_email" class="ec_cart_contact_information_input_text" value="<?php echo esc_attr( $wpec_email ); ?>" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false"<?php echo $wpec_required ? ' aria-describedby="ec_subscription_email_hint"' : ''; ?><?php echo ( 'later' === $wpec_state ) ? ' readonly' : ''; ?> />
			<?php if ( $wpec_required ) { ?>
			<button type="button" class="ec_cart_button ec_subscription_email_continue" id="ec_subscription_email_continue"><?php echo esc_html( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_continue', 'Continue' ) ) ); ?></button>
			<?php } ?>
		</div>
		<div class="ec_cart_error_row" id="ec_contact_email_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_please_enter_valid' ) ); ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_email' ) ); ?>
		</div>
		<p class="ec_checkout_email_suggest" id="ec_contact_email_suggest" data-text="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'checkout_email_suggest', 'Did you mean [email]?' ) ) ); ?>" aria-live="polite" hidden></p>
		<div class="ec_cart_error_row ec_subscription_email_busy" id="ec_subscription_email_busy" role="alert"></div>
		<?php if ( $wpec_required ) { ?>
		<p class="ec_subscription_account_hint" id="ec_subscription_email_hint"><?php echo wp_kses_post( wp_easycart_checkout_signup::text( 'checkout_email_hint', 'Your order needs an account. Start with your email: if you already have one, we’ll ask for your password.' ) ); ?></p>
		<?php } else { ?>
		<div class="ec_checkout_email_known" id="ec_checkout_email_known" role="status" hidden>
			<span class="ec_checkout_email_known_text"><?php echo wp_kses_post( wp_easycart_checkout_signup::text( 'checkout_email_known', 'You already have an account with this email. Sign in for a faster checkout.' ) ); ?></span>
			<button type="button" class="ec_checkout_email_known_signin"><?php echo esc_html( wp_strip_all_tags( wp_easycart_language()->get_text( 'cart_login', 'cart_login_button' ) ) ); ?></button>
		</div>
		<?php } ?>
	</div>

	<?php if ( $wpec_required ) { /* The email once settled, with Change ( the signed-in card's look ); the line under it says what comes next. */ ?>
	<div class="ec_cart_input_row ec_cart_signed_in_v2 ec_subscription_email_locked" id="ec_subscription_email_locked">
		<span class="ec_cart_signed_in_avatar ec_subscription_email_mark" aria-hidden="true"><?php echo esc_html( $wpec_mark ); ?></span>
		<span class="ec_cart_signed_in_who">
			<span class="ec_cart_signed_in_name ec_subscription_email_value"><?php echo esc_html( $wpec_email ); ?></span>
			<span class="ec_cart_signed_in_email ec_subscription_account_note" aria-live="polite" data-note-existing="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_existing', 'Welcome back. Enter your password to sign in.' ) ) ); ?>" data-note-new="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_new', 'Create a password for your new account.' ) ) ); ?>" data-note-later="<?php echo esc_attr( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_later', 'You’ll choose a password for your account after checkout.' ) ) ); ?>"><?php echo ( 'later' === $wpec_state ) ? esc_html( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_later', 'You’ll choose a password for your account after checkout.' ) ) ) : ''; ?></span>
		</span>
		<span class="ec_cart_signed_in_switch"><a href="#" role="button" class="ec_cart_login_header_link ec_subscription_email_change" id="ec_subscription_email_change"><?php echo esc_html( wp_strip_all_tags( wp_easycart_checkout_signup::text( 'subscription_email_change', 'Change' ) ) ); ?></a></span>
	</div>
	<?php } ?>

	<?php /* Sign in: the password of the email above. The classic page posts its form with ec_login_selector ticked; the one-page checkout signs in by AJAX. */ ?>
	<div id="ec_user_login_form">
		<input type="checkbox" name="ec_login_selector" id="ec_login_selector" value="login" style="display:none" tabindex="-1" aria-hidden="true" />
		<input type="hidden" id="ec_cart_login_email" name="ec_cart_login_email" value="<?php echo esc_attr( $wpec_email ); ?>" />
		<div class="ec_cart_error_row" id="ec_cart_login_email_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) ); ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_email_label' ) ); ?>
		</div>

		<div class="ec_cart_input_row">
			<?php do_action( 'wpeasycart_pre_login_password_display' ); ?>
			<label for="ec_cart_login_password"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_password_label' ) ); ?>*</label>
			<span class="ec_password_field"><input type="password" id="ec_cart_login_password" name="ec_cart_login_password" autocomplete="current-password" /><button type="button" class="ec_password_toggle" aria-controls="ec_cart_login_password" aria-pressed="false" data-show="<?php echo esc_attr( $wpec_show ); ?>" data-hide="<?php echo esc_attr( $wpec_hide ); ?>"><?php echo esc_html( $wpec_show ); ?></button></span>
		</div>
		<div class="ec_cart_error_row" id="ec_cart_login_password_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) ); ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_login', 'cart_login_password_label' ) ); ?>
		</div>

		<?php if ( wp_easycart_recaptcha_ready( 'cart' ) ) { ?>
		<input type="hidden" id="ec_grecaptcha_response_login" name="ec_grecaptcha_response_login" value="" />
		<div class="ec_cart_input_row" data-sitekey="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" id="ec_account_login_recaptcha"></div>
		<?php } ?>

		<div class="ec_cart_error_row" id="ec_cart_login_invalid_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'ec_errors', 'login_failed' ) ); ?>
		</div>
		<div class="ec_cart_error_row" id="ec_cart_not_activated_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'ec_errors', 'not_activated' ) ); ?>
		</div>

		<div class="ec_cart_button_row ec_cart_login_actions_v2">
			<a href="<?php echo esc_attr( wpeasycart_links()->get_account_page( 'forgot_password' ) ); ?>" class="ec_account_login_link"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_login', 'account_login_forgot_password_link' ) ); ?></a>
			<?php if ( ! $wpec_required ) { ?>
			<a href="#" role="button" class="ec_account_login_cancel_link ec_checkout_signin_cancel"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_personal_information', 'account_personal_information_cancel_link' ) ); ?></a>
			<?php } ?>
			<?php if ( $wpec_classic ) { ?>
			<input type="submit" value="<?php echo esc_attr( wp_easycart_language()->get_text( 'cart_login', 'cart_login_button' ) ); ?>" class="ec_cart_button ec_checkout_signin_button" onclick="return wpeasycart_checkout_signin_submit();" />
			<?php } else { ?>
			<input type="submit" value="<?php echo esc_attr( wp_easycart_language()->get_text( 'cart_login', 'cart_login_button' ) ); ?>" class="ec_cart_button ec_checkout_signin_button" onclick="return ec_cart_validate_login_v2( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-cart-login-' . $wpec_cart_id ) ); ?>' );" />
			<?php } ?>
		</div>

		<?php if ( $wpec_dynamic && wp_easycart_recaptcha_ready( 'cart' ) ) { ?>
		<script type="text/javascript">
			if ( jQuery( document.getElementById( 'ec_account_login_recaptcha' ) ).length ) {
				var wpeasycart_login_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_login_recaptcha' ), {
					'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
					'callback' : wpeasycart_login_recaptcha_callback
				});
			}
		</script>
		<?php } ?>
	</div>

	<?php if ( $wpec_required ) { /* A new email that needs its password now ( the classic page saves the account with its details: Continue below ). */ ?>
	<div id="ec_user_contact_form">
		<div class="ec_cart_error" id="ec_subscription_email_exists" style="display:none;">
			<div><?php echo wp_kses_post( wp_easycart_language()->get_text( 'ec_errors', 'register_email_error' ) ); ?></div>
		</div>

		<?php if ( $wpec_classic ) { ?>
		<input type="hidden" name="ec_create_account_selector" id="ec_create_account_selector" value="create_account" disabled />
		<?php } else if ( get_option( 'ec_option_use_contact_name' ) ) { ?>
		<div class="ec_cart_input_row">
			<div class="ec_cart_input_left_half">
				<label for="ec_contact_first_name"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ) ); ?>*</label>
				<input type="text" name="ec_contact_first_name" id="ec_contact_first_name" class="ec_cart_contact_information_input_text" value="<?php echo esc_attr( (string) $GLOBALS['ec_cart_data']->cart_data->first_name ); ?>" autocomplete="given-name" />
				<div class="ec_cart_error_row" id="ec_contact_first_name_error">
					<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) ); ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_first_name' ) ); ?>
				</div>
			</div>
			<div class="ec_cart_input_right_half">
				<label for="ec_contact_last_name"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_last_name' ) ); ?>*</label>
				<input type="text" name="ec_contact_last_name" id="ec_contact_last_name" class="ec_cart_contact_information_input_text" value="<?php echo esc_attr( (string) $GLOBALS['ec_cart_data']->cart_data->last_name ); ?>" autocomplete="family-name" />
				<div class="ec_cart_error_row" id="ec_contact_last_name_error">
					<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_please_enter_your' ) ); ?> <?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_last_name' ) ); ?>
				</div>
			</div>
		</div>
		<?php } ?>

		<div class="ec_cart_input_row">
			<?php do_action( 'wpeasycart_pre_password_display' ); ?>
			<label for="ec_contact_password"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_password' ) ); ?>*</label>
			<span class="ec_password_field"><input type="password" name="ec_contact_password" id="ec_contact_password" class="ec_cart_contact_information_input_text" autocomplete="new-password" /><button type="button" class="ec_password_toggle" aria-controls="ec_contact_password" aria-pressed="false" data-show="<?php echo esc_attr( $wpec_show ); ?>" data-hide="<?php echo esc_attr( $wpec_hide ); ?>"><?php echo esc_html( $wpec_show ); ?></button></span>
			<div class="ec_cart_error_row" id="ec_contact_password_error">
				<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_length_error' ) ); ?>
			</div>
		</div>

		<?php if ( $wpec_classic && get_option( 'ec_option_show_subscriber_feature' ) ) { ?>
		<div class="ec_cart_input_row">
			<label class="ec_cart_check_label ec_agree_box" for="ec_cart_is_subscriber"><input type="checkbox" name="ec_cart_is_subscriber" id="ec_cart_is_subscriber" class="ec_account_register_input_field" value="1" />
			<span class="ec_cart_check_text"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_register', 'account_register_subscribe' ) ); ?></span></label>
		</div>
		<?php } ?>

		<?php if ( get_option( 'ec_option_require_account_terms' ) ) { $wpec_terms_id = $wpec_classic ? 'ec_terms' : 'ec_account_terms'; ?>
		<div class="ec_cart_error_row" id="<?php echo esc_attr( $wpec_terms_id ); ?>_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_payment_accept_terms' ) ); ?>
		</div>
		<div class="ec_cart_input_row">
			<label class="ec_cart_check_label ec_agree_box" for="<?php echo esc_attr( $wpec_terms_id ); ?>_agree"><input type="checkbox" name="<?php echo esc_attr( $wpec_terms_id ); ?>_agree" id="<?php echo esc_attr( $wpec_terms_id ); ?>_agree" class="ec_account_register_input_field" />
			<span class="ec_cart_check_text"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_register', 'account_register_agree_terms' ) ); ?></span></label>
		</div>
		<?php } ?>

		<?php if ( ! $wpec_classic ) { ?>
			<?php if ( wp_easycart_recaptcha_ready() ) { /* as ec_ajax_subscription_create_account() requires */ ?>
			<input type="hidden" id="ec_grecaptcha_response_register" name="ec_grecaptcha_response_register" value="" />
			<div class="ec_cart_input_row" data-sitekey="<?php echo esc_attr( get_option( 'ec_option_recaptcha_site_key' ) ); ?>" id="ec_account_register_recaptcha"></div>
			<?php } ?>

			<div class="ec_cart_button_row">
				<div class="ec_cart_button" id="ec_address_save" role="button" tabindex="0" onclick="return subscription_create_account( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-subscription-create-account-' . $wpec_cart_id ) ); ?>' );"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_create_account' ) ); ?></div>
				<div class="ec_cart_button_working" id="ec_address_save_working"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart', 'cart_please_wait' ) ); ?></div>
			</div>

			<?php if ( $wpec_dynamic && wp_easycart_recaptcha_ready() ) { ?>
			<script type="text/javascript">
				if ( jQuery( document.getElementById( 'ec_account_register_recaptcha' ) ).length ) {
					var wpeasycart_register_recaptcha = grecaptcha.render( document.getElementById( 'ec_account_register_recaptcha' ), {
						'sitekey' : jQuery( document.getElementById( 'ec_grecaptcha_site_key' ) ).val( ),
						'callback' : wpeasycart_register_recaptcha_callback
					});
				}
			</script>
			<?php } ?>
		<?php } ?>
	</div>

	<?php } else { /* Guest checkout: an account is a choice ( the classic page saves it with the details ). */ ?>
	<div class="ec_cart_input_row ec_checkout_create_choice">
		<label class="ec_cart_check_label ec_agree_box" for="ec_create_account_selector"><input type="checkbox" name="ec_create_account_selector" id="ec_create_account_selector" value="create_account" onchange="ec_toggle_create_account( );" />
		<span class="ec_cart_check_text"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_create_account' ) ); ?></span></label>
	</div>
	<div id="ec_user_create_form">
		<div class="ec_cart_input_row">
			<?php do_action( 'wpeasycart_pre_password_display' ); ?>
			<label for="ec_contact_password"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_contact_information', 'cart_contact_information_password' ) ); ?>*</label>
			<span class="ec_password_field"><input type="password" name="ec_contact_password" id="ec_contact_password" class="ec_cart_contact_information_input_text" autocomplete="new-password" /><button type="button" class="ec_password_toggle" aria-controls="ec_contact_password" aria-pressed="false" data-show="<?php echo esc_attr( $wpec_show ); ?>" data-hide="<?php echo esc_attr( $wpec_hide ); ?>"><?php echo esc_html( $wpec_show ); ?></button></span>
			<div class="ec_cart_error_row" id="ec_contact_password_error">
				<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_length_error' ) ); ?>
			</div>
		</div>

		<?php if ( get_option( 'ec_option_show_subscriber_feature' ) ) { ?>
		<div class="ec_cart_input_row">
			<label class="ec_cart_check_label ec_agree_box" for="ec_cart_is_subscriber"><input type="checkbox" name="ec_cart_is_subscriber" id="ec_cart_is_subscriber" class="ec_account_register_input_field" value="1" />
			<span class="ec_cart_check_text"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_register', 'account_register_subscribe' ) ); ?></span></label>
		</div>
		<?php } ?>

		<?php if ( get_option( 'ec_option_require_account_terms' ) ) { ?>
		<div class="ec_cart_error_row" id="ec_terms_error">
			<?php echo wp_kses_post( wp_easycart_language()->get_text( 'cart_form_notices', 'cart_notice_payment_accept_terms' ) ); ?>
		</div>
		<div class="ec_cart_input_row">
			<label class="ec_cart_check_label ec_agree_box" for="ec_terms_agree"><input type="checkbox" name="ec_terms_agree" id="ec_terms_agree" class="ec_account_register_input_field" />
			<span class="ec_cart_check_text"><?php echo wp_kses_post( wp_easycart_language()->get_text( 'account_register', 'account_register_agree_terms' ) ); ?></span></label>
		</div>
		<?php } ?>
	</div>
	<?php } ?>
</div>
