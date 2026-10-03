<?php
/**
 * PayPal ( free edition ): the gateway's settings, loaded into the Payments drawer ( wp_easycart_admin_payment_v2 ).
 * WP EasyCart PRO replaces this file with its own ( Venmo, card and Pay Later buttons, PayPal on the cart page ).
 *
 * 6.0.2: laid out as the drawer's groups ( Payments, Buttons, Checkout, Notifications ) like Square's, with every option
 * in view instead of behind "Advanced Options", and nothing wider than the drawer. Element ids are unchanged:
 * admin/js/payment.js saves from them ( ec_admin_save_paypal_options(), paypal_live_on_off(), paypal_sandbox_on_off() ).
 * A yes / no option is a switch beside a hidden field with the option's id, which the save reads. Every option saves as
 * it changes; the drawer's Save button runs the hidden ec_admin_save_paypal_options() button.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Plan name for the locked options below: the store's own plan, or Pro/Premium when no license is known. */
$wpec_plan_name = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro/Premium', 'wp-easycart' );
$ecpp_badge     = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro';
$ecpp_active    = ( 'paypal' === get_option( 'ec_option_payment_third_party' ) );
$ecpp_sandbox   = (bool) get_option( 'ec_option_paypal_use_sandbox' );
$ecpp_pay_now   = (bool) get_option( 'ec_option_paypal_enable_pay_now' );
/* A mode is connected through WP EasyCart Connect ( merchant id ) or, on older stores, the store's own PayPal app. */
$ecpp_live_ready = class_exists( 'wp_easycart_paypal_webhooks' ) ? '' !== wp_easycart_paypal_webhooks::connection( 'production' ) : '' !== (string) get_option( 'ec_option_paypal_production_merchant_id' );
$ecpp_test_ready = class_exists( 'wp_easycart_paypal_webhooks' ) ? '' !== wp_easycart_paypal_webhooks::connection( 'sandbox' ) : '' !== (string) get_option( 'ec_option_paypal_sandbox_merchant_id' );
$ecpp_live_on    = $ecpp_active && ! $ecpp_sandbox && $ecpp_pay_now && $ecpp_live_ready;
$ecpp_test_on    = $ecpp_active && $ecpp_sandbox && $ecpp_pay_now && $ecpp_test_ready;
if ( class_exists( 'wp_easycart_admin_payment_v2' ) && method_exists( 'wp_easycart_admin_payment_v2', 'connect_urls' ) ) {
	$ecpp_urls = wp_easycart_admin_payment_v2::connect_urls( 'paypal' );
} else {
	/* 6.0.2: WP EasyCart Connect's /paypal-v3/ onboarding ( wp_easycart_paypal_connect ). */
	$ecpp_urls = array(
		'live' => wp_easycart_paypal_connect::onboard_url( 'production', esc_url_raw( admin_url() ) . '?wpeasycart_paypal_onboard=production&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-paypal' ) ),
		'test' => wp_easycart_paypal_connect::onboard_url( 'sandbox', esc_url_raw( admin_url() ) . '?wpeasycart_paypal_onboard=sandbox&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-paypal' ) ),
	);
}
/* The account in the mode in use, for Switch account / Disconnect ( WP EasyCart Connect accounts only ). */
$ecpp_env        = $ecpp_sandbox ? 'test' : 'live';
$ecpp_merchant   = (string) get_option( $ecpp_sandbox ? 'ec_option_paypal_sandbox_merchant_id' : 'ec_option_paypal_production_merchant_id' );
$ecpp_disconnect = ( '' !== $ecpp_merchant && class_exists( 'wp_easycart_admin_payment_v2' ) && method_exists( 'wp_easycart_admin_payment_v2', 'disconnect_url' ) ) ? wp_easycart_admin_payment_v2::disconnect_url( 'paypal', $ecpp_env ) : '';

/**
 * A yes / no option: a switch that writes 1 or 0 into the hidden field payment.js reads, then saves.
 *
 * @param string $id    Option id ( and the hidden field's id ).
 * @param string $label What it does.
 * @param string $desc  One sentence.
 */
$ecpp_switch = function ( $id, $label, $desc ) {
	$on = ( '1' === (string) get_option( $id ) );
	?>
	<div class="ec_admin_toggle">
		<span><?php echo esc_html( $label ); ?><small><?php echo esc_html( $desc ); ?></small></span>
		<input type="hidden" name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo $on ? '1' : '0'; ?>" />
		<label class="ec_admin_switch">
			<input type="checkbox" class="ec_admin_slider_checkbox" value="1" data-for="<?php echo esc_attr( $id ); ?>" onchange="document.getElementById( this.getAttribute( 'data-for' ) ).value = this.checked ? '1' : '0'; ec_admin_save_paypal_options( );"<?php checked( $on ); ?> aria-label="<?php echo esc_attr( $label ); ?>">
			<span class="ec_admin_slider round"></span>
		</label>
	</div>
	<?php
};

/**
 * A select row: label, choices, one sentence.
 *
 * @param string $id      Option id.
 * @param string $label   What it does.
 * @param array  $choices value => text.
 * @param string $desc    One sentence.
 */
$ecpp_select = function ( $id, $label, $choices, $desc ) {
	$current = (string) get_option( $id );
	?>
	<div class="ecsq-field">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" onchange="ec_admin_save_paypal_options( );">
			<?php foreach ( $choices as $value => $text ) { ?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php selected( (string) $value, $current ); ?>><?php echo esc_html( $text ); ?></option>
			<?php } ?>
		</select>
		<small><?php echo esc_html( $desc ); ?></small>
	</div>
	<?php
};

/**
 * A button only WP EasyCart PRO turns on: shown locked, with the plan chip that opens the upgrade popup.
 *
 * @param string $feature Upsell feature key ( paypal_express ).
 * @param string $label   What it does.
 * @param string $desc    One sentence.
 */
$ecpp_locked = function ( $feature, $label, $desc ) use ( $wpec_plan_name, $ecpp_badge ) {
	?>
	<div class="ec_admin_toggle ecsq-locked">
		<?php /* translators: 1: what the button does ( a sentence ); 2: plan name, Pro or Premium ( Pro/Premium when no license is known ). */ ?>
		<span><?php echo esc_html( $label ); ?><small><?php echo esc_html( sprintf( __( '%1$s Available with %2$s.', 'wp-easycart' ), $desc, $wpec_plan_name ) ); ?></small></span>
		<button type="button" class="ecsq-pro-chip" onclick="if ( typeof window.ecdv2_upsell === 'function' ) { window.ecdv2_upsell( { context: 'paypal_express', feature: '<?php echo esc_js( $feature ); ?>' } ); } else if ( typeof window.show_pro_required === 'function' ) { window.show_pro_required(); } return false;"><?php echo esc_html( $ecpp_badge ); ?></button>
	</div>
	<?php
};
?>
<div class="ec_admin_paypal_row ecsq ecpp">
	<div class="ec_admin_slider_row">
		<?php wp_easycart_admin()->preloader->print_preloader( 'ec_admin_paypal_display_loader' ); ?>
		<div class="ec_admin_slider_row_description">
			<div><?php esc_html_e( 'PayPal gives your customers a payment method that most are comfortable using and does not require an SSL Certificate.', 'wp-easycart' ); ?></div>
			<?php if ( '' !== $ecpp_merchant ) { ?>
				<div class="ecsq-actions">
					<a href="<?php echo esc_url( $ecpp_urls[ $ecpp_env ] ); ?>"><?php echo $ecpp_sandbox ? esc_html__( 'Switch sandbox account', 'wp-easycart' ) : esc_html__( 'Switch account', 'wp-easycart' ); ?></a>
					<?php if ( '' !== $ecpp_disconnect ) { ?>
						<a class="ecsq-danger" href="<?php echo esc_url( $ecpp_disconnect ); ?>" data-ecpay-confirm="<?php echo esc_attr( $ecpp_sandbox ? __( 'Disconnect your PayPal sandbox account? PayPal stops working in sandbox mode until you connect one again.', 'wp-easycart' ) : __( 'Disconnect your PayPal account? Shoppers can’t pay with PayPal until you connect one again.', 'wp-easycart' ) ); ?>"><?php esc_html_e( 'Disconnect', 'wp-easycart' ); ?></a>
					<?php } ?>
				</div>
			<?php } ?>
			<input type="hidden" value="<?php echo esc_attr( get_option( 'ec_option_paypal_email' ) ); ?>" name="ec_option_paypal_email" id="ec_option_paypal_email" />
			<input type="hidden" name="use_paypal" id="use_paypal" value="<?php echo $ecpp_active ? 1 : 0; ?>" />
			<input type="hidden" name="ec_option_paypal_use_sandbox" id="ec_option_paypal_use_sandbox" value="<?php echo $ecpp_sandbox ? 1 : 0; ?>" />
			<input type="hidden" name="ec_option_paypal_collect_shipping" id="ec_option_paypal_collect_shipping" value="<?php echo esc_attr( get_option( 'ec_option_paypal_collect_shipping' ) ); ?>" />
		</div>

		<div class="ecsq-group">
			<div class="ecsq-group-t"><?php esc_html_e( 'Payments', 'wp-easycart' ); ?></div>
			<div class="ec_admin_toggles_wrap">
				<div class="ec_admin_toggle">
					<span><?php esc_html_e( 'Take live payments', 'wp-easycart' ); ?><small><?php echo $ecpp_live_ready ? esc_html__( 'Shoppers pay into your PayPal business account.', 'wp-easycart' ) : esc_html__( 'Connects your PayPal business account and takes real payments.', 'wp-easycart' ); ?></small></span>
					<?php if ( ! $ecpp_pay_now || ! $ecpp_live_ready ) { ?><a href="<?php echo esc_url( $ecpp_urls['live'] ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return paypal_live_on_off( );" class="ec_admin_slider_checkbox" value="1" id="ec_option_paypal_enable_live"<?php checked( $ecpp_live_on ); ?> aria-label="<?php esc_attr_e( 'Take live payments', 'wp-easycart' ); ?>">
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ecpp_pay_now || ! $ecpp_live_ready ) { ?></a><?php } ?>
				</div>
				<div class="ec_admin_toggle">
					<span><?php esc_html_e( 'Test with the PayPal sandbox', 'wp-easycart' ); ?><small><?php esc_html_e( 'Uses a PayPal sandbox account, so no real money moves.', 'wp-easycart' ); ?></small></span>
					<?php if ( ! $ecpp_pay_now || ! $ecpp_test_ready ) { ?><a href="<?php echo esc_url( $ecpp_urls['test'] ); ?>"><span></span><?php } ?>
					<label class="ec_admin_switch">
						<input type="checkbox" onclick="return paypal_sandbox_on_off( );" class="ec_admin_slider_checkbox" value="1" id="ec_option_paypal_enable_sandbox"<?php checked( $ecpp_test_on ); ?> aria-label="<?php esc_attr_e( 'Test with the PayPal sandbox', 'wp-easycart' ); ?>">
						<span class="ec_admin_slider round"></span>
					</label>
					<?php if ( ! $ecpp_pay_now || ! $ecpp_test_ready ) { ?></a><?php } ?>
				</div>
			</div>
		</div>

		<div class="ecsq-group">
			<div class="ecsq-group-t"><?php esc_html_e( 'Buttons', 'wp-easycart' ); ?></div>
			<div class="ecsq-group-hint"><?php esc_html_e( 'PayPal shows each button only to shoppers who can use it, by country and device.', 'wp-easycart' ); ?></div>
			<?php /* Venmo, card and Pay Later need WP EasyCart PRO: the free panel saves them off, as its old selects did. */ ?>
			<input type="hidden" name="ec_option_paypal_use_venmo" id="ec_option_paypal_use_venmo" value="0" />
			<input type="hidden" name="ec_option_paypal_use_card" id="ec_option_paypal_use_card" value="0" />
			<input type="hidden" name="ec_option_paypal_use_paylater" id="ec_option_paypal_use_paylater" value="0" />
			<div class="ec_admin_toggles_wrap">
				<?php
				$ecpp_locked( 'venmo', __( 'Venmo button', 'wp-easycart' ), __( 'Shoppers in the US pay with Venmo.', 'wp-easycart' ) );
				$ecpp_locked( 'card', __( 'Debit or credit card button', 'wp-easycart' ), __( 'Shoppers pay by card without a PayPal account.', 'wp-easycart' ) );
				$ecpp_locked( 'paylater', __( 'Pay Later', 'wp-easycart' ), __( 'PayPal’s pay-in-installments offers, where PayPal has them.', 'wp-easycart' ) );
				$ecpp_switch( 'ec_option_paypal_enable_credit', __( 'Advertise PayPal Credit', 'wp-easycart' ), __( 'Adds a PayPal Credit button for shoppers PayPal offers it to.', 'wp-easycart' ) );
				$ecpp_locked( 'cart', __( 'PayPal on the cart page', 'wp-easycart' ), __( 'Shoppers check out with PayPal straight from the cart, before filling in the checkout.', 'wp-easycart' ) );
				?>
			</div>
			<?php
			$ecpp_select(
				'ec_option_paypal_button_color',
				__( 'Button color', 'wp-easycart' ),
				array(
					'gold'   => __( 'Gold (Recommended)', 'wp-easycart' ),
					'blue'   => __( 'Blue', 'wp-easycart' ),
					'silver' => __( 'Silver', 'wp-easycart' ),
					'black'  => __( 'Black', 'wp-easycart' ),
				),
				__( 'The color of the PayPal button at checkout.', 'wp-easycart' )
			);
			$ecpp_select(
				'ec_option_paypal_button_shape',
				__( 'Button shape', 'wp-easycart' ),
				array(
					'pill' => __( 'Pill (Recommended)', 'wp-easycart' ),
					'rect' => __( 'Rectangular', 'wp-easycart' ),
				),
				__( 'Rounded ends, or square corners to match the other buttons on your site.', 'wp-easycart' )
			);
			?>
		</div>

		<div class="ecsq-group">
			<div class="ecsq-group-t"><?php esc_html_e( 'Checkout', 'wp-easycart' ); ?></div>
			<?php
			$ecpp_select(
				'ec_option_paypal_currency_code',
				__( 'Currency', 'wp-easycart' ),
				array(
					'USD' => 'U.S. Dollar',
					'AUD' => 'Australian Dollar',
					'BRL' => 'Brazilian Real',
					'CAD' => 'Canadian Dollar',
					'CZK' => 'Czech Koruna',
					'DKK' => 'Danish Krone',
					'EUR' => 'Euro',
					'HKD' => 'Hong Kong Dollar',
					'HUF' => 'Hungarian Forint',
					'ILS' => 'Israeli New Sheqel',
					'JPY' => 'Japanese Yen',
					'MYR' => 'Malaysian Ringgit',
					'MXN' => 'Mexican Peso',
					'NOK' => 'Norwegian Krone',
					'NZD' => 'New Zealand Dollar',
					'PHP' => 'Philippine Peso',
					'PLN' => 'Polish Zloty',
					'GBP' => 'Pound Sterling',
					'SGD' => 'Singapore Dollar',
					'SEK' => 'Swedish Krona',
					'CHF' => 'Swiss Franc',
					'TWD' => 'Taiwan New Dollar',
					'THB' => 'Thai Baht',
					'TRY' => 'Turkish Lira',
				),
				__( 'The currency PayPal charges in.', 'wp-easycart' )
			);
			?>
			<div class="ec_admin_toggles_wrap">
				<?php $ecpp_switch( 'ec_option_paypal_use_selected_currency', __( 'Charge in the shopper’s chosen currency', 'wp-easycart' ), __( 'When a shopper picks a currency in your store’s currency switcher, PayPal charges in that currency instead.', 'wp-easycart' ) ); ?>
			</div>
			<?php
			$ecpp_select(
				'ec_option_paypal_weight_unit',
				__( 'Weight unit', 'wp-easycart' ),
				array(
					'lbs' => 'LBS',
					'kgs' => 'KGS',
				),
				__( 'Pounds or kilograms. Your store also weighs its packages in this unit.', 'wp-easycart' )
			);
			$ecpp_select(
				'ec_option_paypal_lc',
				__( 'PayPal page language', 'wp-easycart' ),
				array(
					'US'    => 'United States',
					'AU'    => 'Australia',
					'AT'    => 'Austria',
					'BE'    => 'Belgium',
					'BR'    => 'Brazil',
					'CA'    => 'Canada',
					'CH'    => 'Switzerland',
					'CN'    => 'China',
					'DE'    => 'Germany',
					'ES'    => 'Spain',
					'GB'    => 'United Kingdom',
					'FR'    => 'France',
					'IT'    => 'Italy',
					'NL'    => 'Netherlands',
					'PL'    => 'Poland',
					'PT'    => 'Portugal',
					'RU'    => 'Russia',
					'da_DK' => 'Danish (for Denmark only)',
					'he_IL' => 'Hebrew (all)',
					'id_ID' => 'Indonesian (for Indonesia only)',
					'jp_JP' => 'Japanese (for Japan only)',
					'no_NO' => 'Norwegian (for Norway only)',
					'pt_BR' => 'Brazilian Portuguese (for Portugal and Brazil only)',
					'ru_RU' => 'Russian (for Lithuania, Latvia, and Ukraine only)',
					'sv_SE' => 'Swedish (for Sweden only)',
					'th_TH' => 'Thai (for Thailand only)',
					'tr_TR' => 'Turkish (for Turkey only)',
					'zh_CN' => 'Simplified Chinese (for China only)',
					'zh_HK' => 'Traditional Chinese (for Hong Kong only)',
					'zh_TW' => 'Traditional Chinese (for Taiwan only)',
				),
				__( 'The language of PayPal’s own payment page, when shoppers are sent there ( PayPal Standard ).', 'wp-easycart' )
			);
			$ecpp_select(
				'ec_option_paypal_charset',
				__( 'Character set', 'wp-easycart' ),
				array(
					'UTF-8'                 => 'UTF-8',
					'Big5'                  => 'Big5 (Traditional Chinese in Taiwan)',
					'EUC-JP'                => 'EUC-JP',
					'EUC-KR'                => 'EUC-KR',
					'EUC-TW'                => 'EUC-TW',
					'gb2312'                => 'gb2312 (Simplified Chinese)',
					'gbk'                   => 'gbk',
					'HZ-GB-2312'            => 'HZ-GB-2312 (Traditional Chinese in Hong Kong)',
					'ibm-862'               => 'ibm-862 (Hebrew with European characters)',
					'ISO-2022-CN'           => 'ISO-2022-CN',
					'ISO-2022-JP'           => 'ISO-2022-JP',
					'ISO-2022-KR'           => 'ISO-2022-KR',
					'ISO-8859-1'            => 'ISO-8859-1 (Western European Languages)',
					'ISO-8859-2'            => 'ISO-8859-2',
					'ISO-8859-3'            => 'ISO-8859-3',
					'ISO-8859-4'            => 'ISO-8859-4',
					'ISO-8859-5'            => 'ISO-8859-5',
					'ISO-8859-6'            => 'ISO-8859-6',
					'ISO-8859-7'            => 'ISO-8859-7',
					'ISO-8859-8'            => 'ISO-8859-8',
					'ISO-8859-9'            => 'ISO-8859-9',
					'ISO-8859-13'           => 'ISO-8859-13',
					'ISO-8859-15'           => 'ISO-8859-15',
					'KOI8-R'                => 'KOI8-R (Cyrillic)',
					'Shift_JIS'             => 'Shift_JIS',
					'UTF-7'                 => 'UTF-7',
					'UTF-16'                => 'UTF-16',
					'UTF-16BE'              => 'UTF-16BE',
					'UTF-16LE'              => 'UTF-16LE',
					'UTF16_PlatformEndian'  => 'UTF16_PlatformEndian',
					'UTF16_OppositeEndian'  => 'UTF16_OppositeEndian',
					'UTF-32'                => 'UTF-32',
					'UTF-32BE'              => 'UTF-32BE',
					'UTF-32LE'              => 'UTF-32LE',
					'UTF32_PlatformEndian'  => 'UTF32_PlatformEndian',
					'UTF32_OppositeEndian'  => 'UTF32_OppositeEndian',
					'US-ASCII'              => 'US-ASCII',
					'windows-1250'          => 'windows-1250',
					'windows-1251'          => 'windows-1251',
					'windows-1252'          => 'windows-1252',
					'windows-1253'          => 'windows-1253',
					'windows-1254'          => 'windows-1254',
					'windows-1255'          => 'windows-1255',
					'windows-1256'          => 'windows-1256',
					'windows-1257'          => 'windows-1257',
					'windows-1258'          => 'windows-1258',
					'windows-874'           => 'windows-874 (Thai)',
					'windows-949'           => 'windows-949 (Korean)',
					'x-mac-greek'           => 'x-mac-greek',
					'x-mac-turkish'         => 'x-mac-turkish',
					'x-mac-centraleurroman' => 'x-mac-centraleurroman',
					'x-mac-cyrillic'        => 'x-mac-cyrillic',
					'ebcdic-cp-us'          => 'ebcdic-cp-us',
					'ibm-1047'              => 'ibm-1047',
				),
				__( 'How your store’s text is sent to PayPal’s payment page ( PayPal Standard ). Leave it on UTF-8 unless PayPal asks otherwise.', 'wp-easycart' )
			);
			?>
			<?php /* The drawer's Save button runs this ( one ec_admin_save_ button = footer save ); hidden by the drawer. */ ?>
			<input type="button" onclick="return ec_admin_save_paypal_options( );" class="ecsq-save" value="<?php esc_attr_e( 'Save', 'wp-easycart' ); ?>" />
		</div>

		<?php
		/* 6.0.2: what PayPal's notifications are doing, with Set up / Secure / Register again ( wp_easycart_paypal_webhooks ). */
		if ( class_exists( 'wp_easycart_paypal_webhooks' ) ) {
			wp_easycart_paypal_webhooks::print_group();
		}
		?>
	</div>
</div>
