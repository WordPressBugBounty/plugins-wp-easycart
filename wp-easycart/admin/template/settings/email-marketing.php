<?php
/**
 * Settings › Email marketing ( V2 declaration ).
 *
 * MailerLite, Kit ( formerly ConvertKit ) and ActiveCampaign, moved here from Settings › Integrations in 6.0.2 and
 * rebuilt around one sync engine in WP EasyCart PRO. Each service has two sections: the connection and the newsletter
 * list, then store data ( orders, customers who did not sign up, abandoned carts ). Everything is a Pro feature: the rows
 * are declared locked here and unlock through the gate; PRO 6.0.2 attaches the pieces a declaration cannot express from
 * wp-easycart-pro/admin/template/settings/email-marketing.php ( the pickers filled from each service's API, the status
 * and recent activity under each connection, and the section actions: check connection, sync existing subscribers, send
 * past orders ). New rows carry 'pro_min_version' => '6.0.2', so a store with an older PRO sees Update, never an upsell.
 *
 * Option names from before 6.0.2 keep their names ( ec_option_enable_mailerlite, ec_option_mailerlite_api_key,
 * ec_option_enable_convertkit, ec_option_convertkit_api_key, ec_option_convertkit_api_secret, ec_option_convertkit_form,
 * ec_option_enable_activecampaign, ec_option_activecampaign_api_url, ec_option_activecampaign_api_key,
 * ec_option_activecampaign_list ), so an older PRO keeps reading them.
 *
 * Products and categories choose their own groups and tags in their editors ( PRO ).
 *
 * @package wp-easycart
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_settings_email_marketing_stored' ) ) {
	/**
	 * Starting options for a picker filled from a service's API: the chosen values, so the row renders and validates
	 * before PRO replaces the list ( a select also gets its "choose" entry, '0' ).
	 *
	 * @since 6.0.2
	 * @param array $field Field declaration ( normalised: key, type, placeholder ).
	 * @return array value => label.
	 */
	function wp_easycart_settings_email_marketing_stored( $field ) {
		$key     = isset( $field['key'] ) ? (string) $field['key'] : '';
		$options = array();
		if ( isset( $field['type'] ) && 'select' === $field['type'] ) {
			$options['0'] = ( isset( $field['placeholder'] ) && '' !== (string) $field['placeholder'] ) ? (string) $field['placeholder'] : __( 'Choose one', 'wp-easycart' );
		}
		$stored = ( '' !== $key && function_exists( 'get_option' ) ) ? get_option( $key, '' ) : '';
		foreach ( is_array( $stored ) ? $stored : explode( ',', (string) $stored ) as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value && '0' !== $value ) {
				/* translators: %s: an id at the email marketing service. */
				$options[ $value ] = sprintf( __( 'ID %s', 'wp-easycart' ), $value );
			}
		}
		return $options;
	}
}

if ( ! function_exists( 'wp_easycart_settings_email_marketing_sanitize_url' ) ) {
	/**
	 * ActiveCampaign API URL: https, the host only ( the API adds /api/3 ).
	 *
	 * @since 6.0.2
	 * @param string $value Pasted value.
	 * @param array  $field Field.
	 * @return string
	 */
	function wp_easycart_settings_email_marketing_sanitize_url( $value, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the registry's sanitize signature.
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) ) {
			$value = 'https://' . $value;
		}
		$host = wp_parse_url( $value, PHP_URL_HOST );
		return is_string( $host ) && '' !== $host ? 'https://' . strtolower( $host ) : '';
	}
}

if ( ! function_exists( 'wp_easycart_settings_email_marketing_render_more' ) ) {
	/**
	 * The "other services" section: where to find Mailchimp and anything else.
	 *
	 * @since 6.0.2
	 * @param array $page    Page declaration.
	 * @param array $section Section declaration.
	 */
	function wp_easycart_settings_email_marketing_render_more( $page, $section ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the engine's section 'render' signature.
		echo '<p class="ecst-row-desc" style="margin:0;">' . esc_html__( 'Mailchimp and other services connect through extensions.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-extensions' ) ) . '">' . esc_html__( 'Browse extensions', 'wp-easycart' ) . '</a></p>';
	}
}

return array(
	'slug'        => 'newsletter-services', /* 6.0.2: not email-marketing, the WP EasyCart Email Marketing extension's page */
	'title'       => __( 'Email marketing', 'wp-easycart' ),
	'description' => __( 'Keep MailerLite, Kit or ActiveCampaign in step with your newsletter list, customers, orders and abandoned carts.', 'wp-easycart' ),
	'group'       => 'marketing',
	'order'       => 20,
	'plan'        => 'pro', /* 6.0.2: a Pro page ( lock in the sidebar ) */
	'icon'        => 'email-alt',
	'docs'        => array( 'settings', 'third-party', 'email-marketing' ),
	'legacy'      => array(),
	'upsell'      => 'default',
	'sections'    => array(

		'mailerlite'           => array(
			'title'   => __( 'MailerLite', 'wp-easycart' ),
			'hint'    => __( 'Newsletter subscribers, kept in step both ways', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_enable_mailerlite'            => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with MailerLite', 'wp-easycart' ),
					'desc'     => __( 'Everyone who joins your newsletter is added to MailerLite, and people who unsubscribe on either side leave both lists.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'subscribers' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Mailer Lite Setup', 'label' => 'Enable Mailer Lite' ),
				),
				'ec_option_mailerlite_api_key'           => array(
					'type'     => 'password',
					'label'    => __( 'API token', 'wp-easycart' ),
					'desc'     => __( 'From MailerLite › Integrations › API. A key from MailerLite Classic still works for subscribers.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_mailerlite',
					'keywords' => array( 'api key', 'token', 'mailerlite' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Mailer Lite Setup', 'label' => 'Mailer Lite API Token' ),
				),
				'ec_option_mailerlite_groups_newsletter' => array(
					'type'            => 'multiselect',
					'label'           => __( 'Groups for newsletter subscribers', 'wp-easycart' ),
					'desc'            => __( 'New subscribers join these groups. Products and categories can add buyers to more groups in their editors.', 'wp-easycart' ),
					'default'         => '',
					'options'         => 'wp_easycart_settings_email_marketing_stored',
					'display'         => 'picker',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_mailerlite',
					'keywords'        => array( 'group', 'mailerlite', 'segment' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_mailerlite_profile'           => array(
					'type'            => 'toggle',
					'label'           => __( 'Send phone and address', 'wp-easycart' ),
					'desc'            => __( 'Fills the phone, company, city, state, ZIP and country fields from the subscriber’s latest order.', 'wp-easycart' ),
					'default'         => 1,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_mailerlite',
					'keywords'        => array( 'fields', 'profile', 'phone', 'address' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'check_connection',
					'label'           => __( 'Check the connection', 'wp-easycart' ),
					'desc'            => __( 'Tries the API token and sets up unsubscribes coming back from MailerLite.', 'wp-easycart' ),
					'button'          => __( 'Check connection', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
				array(
					'id'              => 'sync_subscribers',
					'label'           => __( 'Send your existing subscribers', 'wp-easycart' ),
					'desc'            => __( 'Adds everyone already on your newsletter list, a batch a minute in the background.', 'wp-easycart' ),
					'button'          => __( 'Sync subscribers', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'mailerlite-store'     => array(
			'title'   => __( 'MailerLite store data', 'wp-easycart' ),
			'hint'    => __( 'Orders and abandoned checkouts for MailerLite’s e-commerce automations', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_mailerlite_ecommerce'      => array(
					'type'            => 'toggle',
					'label'           => __( 'Send orders to MailerLite', 'wp-easycart' ),
					'desc'            => __( 'Purchases start MailerLite’s purchase automations and show in its reports. Needs the new MailerLite on a Comfort or Power plan.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'keywords'        => array( 'ecommerce', 'orders', 'purchases', 'shop' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_mailerlite_all_buyers'     => array(
					'type'            => 'toggle',
					'label'           => __( 'Include customers who did not sign up', 'wp-easycart' ),
					'desc'            => __( 'Their orders are recorded as customers who do not accept marketing, and they join no group.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_mailerlite_ecommerce',
					'keywords'        => array( 'customers', 'consent', 'buyers' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_mailerlite_carts'          => array(
					'type'            => 'toggle',
					'label'           => __( 'Send abandoned checkouts', 'wp-easycart' ),
					'desc'            => __( 'Carts left by subscribers go to MailerLite with a link back to the cart, for its abandoned checkout automation.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_mailerlite_ecommerce',
					'keywords'        => array( 'abandoned cart', 'abandoned checkout', 'recovery' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_mailerlite_cart_reminders' => array(
					'type'            => 'toggle',
					'label'           => __( 'Let MailerLite send the cart reminders', 'wp-easycart' ),
					'desc'            => __( 'Your store’s own abandoned cart emails skip the carts MailerLite gets, so nobody is reminded twice.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_mailerlite_carts',
					'keywords'        => array( 'abandoned cart', 'reminders' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'MailerLite store data', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'send_past_orders',
					'label'           => __( 'Send past orders', 'wp-easycart' ),
					'desc'            => __( 'Sends every paid order in the background.', 'wp-easycart' ),
					'button'          => __( 'Send past orders', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'kit'                  => array(
			'title'   => __( 'Kit', 'wp-easycart' ),
			'hint'    => __( 'Formerly ConvertKit. Newsletter subscribers added to a Kit form, kept in step both ways', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_enable_convertkit'     => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with Kit', 'wp-easycart' ),
					'desc'     => __( 'Everyone who joins your newsletter is subscribed to the form chosen below, and people who unsubscribe on either side leave both lists.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'Enable ConvertKit' ),
				),
				'ec_option_kit_api_key'           => array(
					'type'            => 'password',
					'label'           => __( 'API key', 'wp-easycart' ),
					'desc'            => __( 'From Kit › Settings › Developer › API keys ( a V4 key ). Needed for tags, the last name field, cart tags and unsubscribes coming back.', 'wp-easycart' ),
					'default'         => '',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_convertkit',
					'keywords'        => array( 'api key', 'v4', 'convertkit', 'kit' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_convertkit_api_secret' => array(
					'type'     => 'password',
					'label'    => __( 'V3 API secret', 'wp-easycart' ),
					'desc'     => __( 'Same Developer page, under V3. Records purchases: Kit’s newer API only lets approved apps do that.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_convertkit',
					'keywords' => array( 'api secret', 'v3', 'purchases', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit API Secret' ),
				),
				'ec_option_convertkit_api_key'    => array(
					'type'     => 'text',
					'label'    => __( 'V3 API key', 'wp-easycart' ),
					'desc'     => __( 'Only needed without a V4 key: the older connection lists forms and tags with it.', 'wp-easycart' ),
					'default'  => '',
					'advanced' => true,
					'pro'      => true,
					'parent'   => 'ec_option_enable_convertkit',
					'keywords' => array( 'api key', 'v3', 'convertkit', 'kit' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit API Key' ),
				),
				'ec_option_convertkit_form'       => array(
					'type'        => 'select',
					'label'       => __( 'Form to subscribe to', 'wp-easycart' ),
					'desc'        => __( 'If the form asks people to confirm, Kit sends its confirmation email first. Optional with the V4 API key; the older V3 keys only subscribe through a form.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => __( 'Choose a form', 'wp-easycart' ),
					'options'     => 'wp_easycart_settings_email_marketing_stored',
					'pro'         => true,
					'parent'      => 'ec_option_enable_convertkit',
					'keywords'    => array( 'form', 'convertkit', 'kit' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'ConvertKit Setup', 'label' => 'ConvertKit Form' ),
				),
				'ec_option_kit_tags_newsletter'   => array(
					'type'            => 'multiselect',
					'label'           => __( 'Tags for newsletter subscribers', 'wp-easycart' ),
					'desc'            => __( 'New subscribers get these tags. Products and categories can tag buyers in their editors.', 'wp-easycart' ),
					'default'         => '',
					'options'         => 'wp_easycart_settings_email_marketing_stored',
					'display'         => 'picker',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_convertkit',
					'keywords'        => array( 'tags', 'convertkit', 'kit', 'segment' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'check_connection',
					'label'           => __( 'Check the connection', 'wp-easycart' ),
					'desc'            => __( 'Tries the keys and sets up unsubscribes coming back from Kit.', 'wp-easycart' ),
					'button'          => __( 'Check connection', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
				array(
					'id'              => 'sync_subscribers',
					'label'           => __( 'Send your existing subscribers', 'wp-easycart' ),
					'desc'            => __( 'Subscribes everyone already on your newsletter list to the form, a batch a minute in the background.', 'wp-easycart' ),
					'button'          => __( 'Sync subscribers', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'kit-store'            => array(
			'title'   => __( 'Kit store data', 'wp-easycart' ),
			'hint'    => __( 'Purchases and abandoned carts for Kit automations', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_kit_purchases'      => array(
					'type'            => 'toggle',
					'label'           => __( 'Record purchases in Kit', 'wp-easycart' ),
					'desc'            => __( 'Subscribers’ orders become Kit purchases, for purchase automations. Needs the V3 API secret.', 'wp-easycart' ),
					'default'         => 1,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'keywords'        => array( 'purchases', 'orders', 'commerce' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_kit_carts'          => array(
					'type'            => 'toggle',
					'label'           => __( 'Tag subscribers who leave a cart', 'wp-easycart' ),
					'desc'            => __( 'The tag is added when a subscriber leaves a cart and removed when they buy. Start a Kit automation from it.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'keywords'        => array( 'abandoned cart', 'tag', 'recovery' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_kit_cart_tag'       => array(
					'type'            => 'select',
					'label'           => __( 'Abandoned cart tag', 'wp-easycart' ),
					'desc'            => __( 'Needs the V4 API key.', 'wp-easycart' ),
					'default'         => '',
					'placeholder'     => __( 'Choose a tag', 'wp-easycart' ),
					'options'         => 'wp_easycart_settings_email_marketing_stored',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_kit_carts',
					'keywords'        => array( 'abandoned cart', 'tag' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_kit_cart_reminders' => array(
					'type'            => 'toggle',
					'label'           => __( 'Let Kit send the cart reminders', 'wp-easycart' ),
					'desc'            => __( 'Your store’s own abandoned cart emails skip the carts Kit tags, so nobody is reminded twice.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_kit_carts',
					'keywords'        => array( 'abandoned cart', 'reminders' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'Kit store data', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'send_past_orders',
					'label'           => __( 'Send past orders', 'wp-easycart' ),
					'desc'            => __( 'Records subscribers’ earlier orders as purchases and adds their product tags, in the background.', 'wp-easycart' ),
					'button'          => __( 'Send past orders', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'activecampaign'       => array(
			'title'   => __( 'ActiveCampaign', 'wp-easycart' ),
			'hint'    => __( 'Newsletter subscribers on an ActiveCampaign list, kept in step both ways', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_enable_activecampaign'          => array(
					'type'     => 'toggle',
					'label'    => __( 'Sync subscribers with ActiveCampaign', 'wp-easycart' ),
					'desc'     => __( 'Everyone who joins your newsletter is added to the list chosen below, and people who unsubscribe on either side leave both lists.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'newsletter', 'email marketing', 'crm' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Enable Active Campaign' ),
				),
				'ec_option_activecampaign_api_url'         => array(
					'type'        => 'url',
					'label'       => __( 'API URL', 'wp-easycart' ),
					'desc'        => __( 'Your account’s API access URL, under Settings › Developer in ActiveCampaign.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'https://youraccount.api-us1.com',
					'sanitize'    => 'wp_easycart_settings_email_marketing_sanitize_url',
					'pro'         => true,
					'parent'      => 'ec_option_enable_activecampaign',
					'keywords'    => array( 'api url', 'activecampaign', 'endpoint' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign API URL' ),
				),
				'ec_option_activecampaign_api_key'         => array(
					'type'     => 'password',
					'label'    => __( 'API key', 'wp-easycart' ),
					'desc'     => __( 'From the same Developer page. Only sent from your server.', 'wp-easycart' ),
					'default'  => '',
					'pro'      => true,
					'parent'   => 'ec_option_enable_activecampaign',
					'keywords' => array( 'api key', 'activecampaign', 'token' ),
					'legacy'   => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign API Key' ),
				),
				'ec_option_activecampaign_list'            => array(
					'type'        => 'select',
					'label'       => __( 'List to add subscribers to', 'wp-easycart' ),
					'desc'        => __( 'Create a list in ActiveCampaign for store subscribers.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => __( 'Choose a list', 'wp-easycart' ),
					'options'     => 'wp_easycart_settings_email_marketing_stored',
					'pro'         => true,
					'parent'      => 'ec_option_enable_activecampaign',
					'keywords'    => array( 'list', 'activecampaign', 'contacts' ),
					'legacy'      => array( 'page' => 'third-party', 'section' => 'Active Campaign Setup', 'label' => 'Active Campaign List' ),
				),
				'ec_option_activecampaign_optin_form'      => array(
					'type'            => 'select',
					'label'           => __( 'Ask new subscribers to confirm', 'wp-easycart' ),
					'desc'            => __( 'Pick an ActiveCampaign form that uses double opt-in: new subscribers get its confirmation email and join the list once they confirm.', 'wp-easycart' ),
					'default'         => '',
					'placeholder'     => __( 'No, add them straight away', 'wp-easycart' ),
					'options'         => 'wp_easycart_settings_email_marketing_stored',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_activecampaign',
					'keywords'        => array( 'double opt-in', 'confirm', 'form' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_activecampaign_tags_newsletter' => array(
					'type'            => 'multiselect',
					'label'           => __( 'Tags for newsletter subscribers', 'wp-easycart' ),
					'desc'            => __( 'New subscribers get these tags. Products and categories can tag buyers in their editors.', 'wp-easycart' ),
					'default'         => '',
					'options'         => 'wp_easycart_settings_email_marketing_stored',
					'display'         => 'picker',
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_enable_activecampaign',
					'keywords'        => array( 'tags', 'activecampaign', 'segment' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'check_connection',
					'label'           => __( 'Check the connection', 'wp-easycart' ),
					'desc'            => __( 'Tries the API URL and key and sets up unsubscribes coming back from ActiveCampaign.', 'wp-easycart' ),
					'button'          => __( 'Check connection', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
				array(
					'id'              => 'sync_subscribers',
					'label'           => __( 'Send your existing subscribers', 'wp-easycart' ),
					'desc'            => __( 'Adds everyone already on your newsletter list to the list, in the background.', 'wp-easycart' ),
					'button'          => __( 'Sync subscribers', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'activecampaign-store' => array(
			'title'   => __( 'ActiveCampaign store data', 'wp-easycart' ),
			'hint'    => __( 'Orders, refunds and abandoned carts through ActiveCampaign’s e-commerce data', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_activecampaign_ecommerce'      => array(
					'type'            => 'toggle',
					'label'           => __( 'Send orders to ActiveCampaign', 'wp-easycart' ),
					'desc'            => __( 'Orders show on the contact and start “Makes a purchase” automations; refunds update them.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'keywords'        => array( 'ecommerce', 'deep data', 'orders', 'purchases' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_activecampaign_all_buyers'     => array(
					'type'            => 'toggle',
					'label'           => __( 'Include customers who did not sign up', 'wp-easycart' ),
					'desc'            => __( 'They become contacts with their orders and product tags but join no list. ActiveCampaign bills by contacts.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_activecampaign_ecommerce',
					'keywords'        => array( 'customers', 'consent', 'buyers' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_activecampaign_carts'          => array(
					'type'            => 'toggle',
					'label'           => __( 'Send abandoned carts', 'wp-easycart' ),
					'desc'            => __( 'Carts left by subscribers go to ActiveCampaign with a link back to the cart, and are marked recovered when they buy.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_activecampaign_ecommerce',
					'keywords'        => array( 'abandoned cart', 'recovery' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign store data', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_activecampaign_cart_reminders' => array(
					'type'            => 'toggle',
					'label'           => __( 'Let ActiveCampaign send the cart reminders', 'wp-easycart' ),
					'desc'            => __( 'Your store’s own abandoned cart emails skip the carts ActiveCampaign gets, so nobody is reminded twice.', 'wp-easycart' ),
					'default'         => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'parent'          => 'ec_option_activecampaign_carts',
					'keywords'        => array( 'abandoned cart', 'reminders' ),
					'legacy'          => array( 'page' => 'newsletter-services', 'section' => 'ActiveCampaign store data', 'label' => 'New in 6.0.2' ),
				),
			),
			'actions' => array(
				array(
					'id'              => 'send_past_orders',
					'label'           => __( 'Send past orders', 'wp-easycart' ),
					'desc'            => __( 'Sends every paid order in the background as historical data, which never starts automations.', 'wp-easycart' ),
					'button'          => __( 'Send past orders', 'wp-easycart' ),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
				),
			),
		),

		'more-services'        => array(
			'title'  => __( 'Other services', 'wp-easycart' ),
			'hint'   => __( 'Mailchimp and more', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'wp_easycart_settings_email_marketing_render_more',
		),
	),
);
