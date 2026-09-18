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
 * Partner-first flow: an empty live slot shows "Choose how you take cards" ( Stripe and
 * Square side by side, plus a quieter "Other gateway" that opens the full list below ); an
 * empty third-party slot shows PayPal plus "Other". A filled slot keeps its card, and its
 * "Change gateway" button reveals the same chooser. Partners come from
 * wp_easycart_admin_payment_v2::partners() ( filter 'wp_easycart_payment_partner_gateways' );
 * the PRO gates and every save path are unchanged.
 *
 * Bill later keeps its on/off on its card and edits its wording ( option name,
 * instructions ) in the same drawer through a small V2 form
 * ( ecv2_payment_manual_form / ecv2_payment_manual_save ), which reuses the
 * registry sanitizers and the legacy language + tracking side effects.
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

if ( ! function_exists( 'ecst_payment_test_mode_off' ) ) {
	/** Declared action: switch every active gateway that is in test mode back to live. */
	function ecst_payment_test_mode_off( $action, $page ) {
		if ( ! class_exists( 'wp_easycart_admin_payment_v2' ) ) {
			return new WP_Error( 'ecpay_missing', __( 'The payment controller is not available.', 'wp-easycart' ) );
		}
		return wp_easycart_admin_payment_v2::action_test_mode_off( $action, $page );
	}
}

/* Plan name for copy: the store's own plan, or Pro/Premium when no license is known ( and outside WordPress ). */
$ecv2_payment_plan = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : 'Pro/Premium';

return array(
	'slug'        => 'payment',
	'title'       => __( 'Payment', 'wp-easycart' ),
	'description' => __( 'What shoppers can pay with, whether each gateway is connected, and whether it is live or in test mode.', 'wp-easycart' ),
	'group'       => 'financial',
	'icon'        => 'money-alt',
	'docs'        => array( 'settings', 'payment', 'live-gateway' ),
	'legacy'      => array( 'payment' ),
	'upsell'      => 'default',
	'enqueue'     => 'ecst_payment_enqueue',
	'sections'    => array(

		'active' => array(
			'title'   => __( 'Active gateways', 'wp-easycart' ),
			'hint'    => __( 'One live gateway, one third-party checkout and Bill later can all be on at the same time', 'wp-easycart' ),
			'fields'  => array(),
			'render'  => 'ecst_payment_render_active',
			'actions' => array(
				array(
					'id'       => 'test_mode_off',
					'label'    => __( 'Turn off test mode', 'wp-easycart' ),
					'desc'     => __( 'Switches every active gateway that is in sandbox or test mode back to live. A gateway without a connected live account stays in test mode until you connect one.', 'wp-easycart' ),
					'button'   => __( 'Turn off test mode', 'wp-easycart' ),
					'confirm'  => __( 'Switch all active gateways to live mode? Real cards will be charged from now on.', 'wp-easycart' ),
					'callback' => 'ecst_payment_test_mode_off',
					'legacy'   => 'payment › Live Payment / PayPal / gateway forms › Switch to Live Mode / Test Mode select',
				),
			),
		),

		'more' => array(
			'title'  => __( 'More gateways', 'wp-easycart' ),
			/* translators: %s: plan name, Pro or Premium ( Pro/Premium when no license is known ). */
			'hint'   => sprintf( __( 'Everything else you can switch on. %s gateways unlock with a license', 'wp-easycart' ), $ecv2_payment_plan ),
			'fields' => array(),
			'render' => 'ecst_payment_render_more',
		),
	),
);
