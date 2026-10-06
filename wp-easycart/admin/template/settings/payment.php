<?php
/**
 * Settings › Payment ( V2 declaration ).
 *
 * Replaces admin/inc/wp_easycart_admin_payments.php::load_payments() and the
 * shell template admin/template/settings/payments/payment.php. The gateway
 * cards, the test-mode banner and the collapsed "More gateways" list are
 * rendered by wp_easycart_admin_payment_v2 ( admin/inc/wp_easycart_admin_payment_v2.php )
 * through the two section 'render' callables; each gateway's settings open in a
 * drawer. A gateway with a form declaration ( filter 'wp_easycart_payment_gateway_form';
 * PRO: admin/template/settings/payment-forms.php ) renders as V2 fields saved from the
 * drawer footer through its existing save handler; any other gateway embeds its
 * EXISTING partial from admin/template/settings/payments/ ( PRO: its own folder ).
 *
 * 6.0.3: "How customers pay" is one line per way to pay ( Card payments, Checkout buttons, Wallets,
 * Pay later ): the gateway, its state, Manage ( or the step that is missing ) and a ⋯ menu for test mode,
 * Reconnect, Replace, Turn off and Disconnect. An empty line suggests the recommended gateways
 * ( wp_easycart_admin_payment_v2::partners(), filter 'wp_easycart_payment_partner_gateways' ).
 * "Add or change a gateway" is the searchable list: Recommended first, filters by way to pay, rarely
 * chosen gateways folded under "Older gateways" ( filters 'wp_easycart_payment_recommended_gateways',
 * 'wp_easycart_payment_older_gateways' ). The page's one test-mode control is the line above the ways to
 * pay ( ecv2_payment_test_off ); the EasyCart Connect terms are asked when a Connect link is first used.
 * The PRO gates and every save path are unchanged.
 *
 * Bill later edits its wording ( option name, instructions, roles ) in the same drawer through a small
 * V2 form ( ecv2_payment_manual_form / ecv2_payment_manual_save ), which reuses the registry
 * sanitizers and the legacy language + tracking side effects.
 *
 * Assets: the page-level 'enqueue' callable runs on admin_enqueue_scripts.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The controller is included by admin/admin-init.php; this covers any other route that reads the declarations. */
if ( function_exists( 'add_action' ) && ! class_exists( 'wp_easycart_admin_payment_v2' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_payment_v2.php' ) ) {
	require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_payment_v2.php';
}

/* ---------------------------------------------------------------------- */
/* Helpers ( guarded: the file is included once per request )              */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecst_payment_enqueue' ) ) {
	/** Page-level enqueue ( admin_enqueue_scripts ): styles, the legacy payment.js and the page script. */
	function ecst_payment_enqueue( $page = null ) {
		if ( class_exists( 'wp_easycart_admin_payment_v2' ) ) {
			wp_easycart_admin_payment_v2::enqueue( $page );
		}
	}
}

if ( ! function_exists( 'ecst_payment_render_active' ) ) {
	/** Section body: flash, terms gate, test-mode banner and one card per slot. */
	function ecst_payment_render_active( $page, $section ) {
		if ( ! class_exists( 'wp_easycart_admin_payment_v2' ) ) {
			echo '<p>' . esc_html__( 'The payment overview is not available on this install.', 'wp-easycart' ) . '</p>';
			return;
		}
		wp_easycart_admin_payment_v2::render_active( $page, $section );
	}
}

if ( ! function_exists( 'ecst_payment_render_more' ) ) {
	/** Section body: every gateway that is not active, collapsed to a summary row. */
	function ecst_payment_render_more( $page, $section ) {
		if ( ! class_exists( 'wp_easycart_admin_payment_v2' ) ) {
			return;
		}
		wp_easycart_admin_payment_v2::render_more( $page, $section );
	}
}

if ( ! function_exists( 'ecst_payment_render_subscriptions' ) ) {
	/**
	 * Row: subscription products are for sale, but subscriptions need Stripe ( 6.0.2 ). Prints nothing otherwise, and
	 * settings-payment-v2.css hides the empty row.
	 *
	 * @param array $field Field.
	 * @param array $page  Declaration.
	 */
	function ecst_payment_render_subscriptions( $field = array(), $page = array() ) {
		if ( ! class_exists( 'wp_easycart_subscription_gateway' ) ) {
			return;
		}
		$count = wp_easycart_subscription_gateway::stranded_products();
		if ( $count > 0 ) {
			/* Already on Settings › Payments: the lines below are the way to fix it. */
			echo wp_easycart_subscription_gateway::notice_html( 'payments', array( 'count' => $count, 'link' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
		}
	}
}

return array(
	'slug'        => 'payment',
	'title'       => __( 'Payment', 'wp-easycart' ),
	'description' => __( 'What shoppers can pay with, whether each gateway is connected, and whether it is live or in test mode.', 'wp-easycart' ),
	'group'       => 'payments-taxes',
	'order'       => 10,
	'icon'        => 'money-alt',
	'docs'        => array( 'settings', 'payment', 'live-gateway' ),
	'legacy'      => array( 'payment' ),
	'upsell'      => 'default',
	'enqueue'     => 'ecst_payment_enqueue',
	'sections'    => array(

		'active' => array(
			'title'  => __( 'How customers pay', 'wp-easycart' ),
			'hint'   => __( 'One line per way to pay. Settings open in a side panel', 'wp-easycart' ),
			'fields' => array(
				/* 6.0.2: subscriptions are billed through Stripe only; shown while subscription products are for sale and can't be bought. */
				'ecst_payment_subscriptions' => array(
					'type'     => 'html',
					'label'    => __( 'Subscriptions need Stripe', 'wp-easycart' ),
					'desc'     => __( 'Subscription products can only be bought while Stripe takes your card payments.', 'wp-easycart' ),
					'render'   => 'ecst_payment_render_subscriptions',
					'keywords' => array( 'subscription', 'subscriptions', 'recurring', 'membership', 'stripe', 'square' ),
					'legacy'   => array( 'page' => 'payment', 'section' => 'Active gateways', 'label' => 'New in 6.0.2' ),
				),
			),
			/* 6.0.3: the test-mode line above the ways to pay is the page's one control ( ecv2_payment_test_off ); the declared
			   "Turn off test mode" row at the foot of this section is gone. */
			'render' => 'ecst_payment_render_active',
		),

		'more' => array(
			'title'  => __( 'Add or change a gateway', 'wp-easycart' ),
			'hint'   => __( 'Search by name or country. Recommended gateways come first', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'ecst_payment_render_more',
		),
	),
);
