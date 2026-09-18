<?php
/**
 * WP EasyCart Admin — icons for the V2 settings pages.
 *
 * One small set of 16px line icons ( 24-unit viewBox, 2px stroke, the same
 * style as the admin sidebar ) plus the default icon for every settings page
 * and section. Declarations may override with 'icon' => '<name>' on a page or
 * a section, or 'mark' => array( 'text' => 'GA', 'bg' => '#e37400' ) for a
 * service lettermark. Unknown names fall back to the page icon, then 'sliders'.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_settings_icons' ) ) :

class wp_easycart_admin_settings_icons {

	/** name => SVG inner markup ( paths only ). */
	private static function paths() {
		return array(
			'sliders'      => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
			'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
			'user-plus'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
			'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
			'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'cart'         => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
			'cart-plus'    => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/><line x1="12" y1="8" x2="12" y2="14"/><line x1="9" y1="11" x2="15" y2="11"/>',
			'sidebar'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/>',
			'list'         => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
			'grid'         => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
			'file-text'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
			'tag'          => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
			'dollar'       => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
			'percent'      => '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
			'package'      => '<path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
			'box'          => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
			'star'         => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
			'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
			'share'        => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
			'swap'         => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
			'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
			'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
			'mail-check'   => '<path d="M22 13V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8"/><polyline points="22,6 12,13 2,6"/><polyline points="16 19 18 21 22 17"/>',
			'send'         => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
			'at'           => '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/>',
			'quote'        => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3z"/>',
			'palette'      => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
			'type'         => '<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>',
			'code'         => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
			'layers'       => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
			'puzzle'       => '<path d="M19.4 12.6a2 2 0 1 0 0-1.2V9a2 2 0 0 0-2-2h-2.4a2 2 0 1 0-1.2 0H11a2 2 0 0 0-2 2v2.4a2 2 0 1 1 0 1.2V15a2 2 0 0 0 2 2h2.4a2 2 0 1 0 1.2 0H17a2 2 0 0 0 2-2z"/>',
			'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
			'smartphone'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
			'monitor'      => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
			'activity'     => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
			'wrench'       => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
			'map-pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
			'map'          => '<polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>',
			'globe'        => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
			'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
			'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'truck'        => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
			'receipt'      => '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/>',
			'credit-card'  => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
			'wallet'       => '<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/><circle cx="17" cy="14" r="1.5"/>',
			'plus-circle'  => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
			'zap'          => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
			'chart'        => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
			'megaphone'    => '<path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
			'store'        => '<path d="M3 9l1-5h16l1 5"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v9h14v-9"/><path d="M10 21v-6h4v6"/>',
			'home'         => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
			'key'          => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
			'database'     => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
			'cloud'        => '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>',
			'refresh'      => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
			'gift'         => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
			'message'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
			'hash'         => '<line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/>',
			'coins'        => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="M16.71 13.88l.7.71-2.82 2.82"/>',
			'banknote'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
			'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
			'alert'        => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
			'eye'          => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
			'image'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
			'flag'         => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
			'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
			'tool'         => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
			'layout'       => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
			'target'       => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
			'book'         => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
			'external'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
			'timer'        => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M5 3L2 6M22 6l-3-3"/>',
			'scale'        => '<path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1zM2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1zM7 21h10M12 3v18M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
			'anchor'       => '<circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/>',
		);
	}

	/** Dashicon names still used by page declarations and the home catalog → set names. */
	private static function aliases() {
		return array(
			'admin-users' => 'users', 'admin-tools' => 'wrench', 'products' => 'package', 'admin-appearance' => 'palette', 'art' => 'palette',
			'email' => 'mail', 'translation' => 'globe', 'money-alt' => 'credit-card', 'car' => 'truck', 'media-spreadsheet' => 'percent',
			'admin-plugins' => 'puzzle', 'admin-settings' => 'wrench', 'list-view' => 'list', 'admin-site-alt3' => 'globe', 'grid-view' => 'grid',
			'filter' => 'sliders', 'location' => 'map-pin', 'editor-ul' => 'file-text', 'admin-generic' => 'sliders',
		);
	}

	/** Default section icons: page slug => ( section slug => icon name ). A string value is an icon; an array is a lettermark. */
	private static function section_defaults() {
		return array(
			'account' => array( 'registration' => 'user-plus', 'account-page' => 'user', 'spam-protection' => 'shield' ),
			'admin' => array( 'quick-add' => 'plus-circle', 'orders' => 'receipt', 'admin-screens' => 'monitor', 'mobile-apps' => 'smartphone', 'storefront' => 'store', 'diagnostics' => 'activity', 'integrations' => 'puzzle' ),
			'checkout' => array( 'cart' => 'cart', 'checkout-flow' => 'zap', 'checkout-form' => 'file-text', 'address-fields' => 'map', 'payment-page' => 'credit-card', 'orders' => 'hash', 'stock-alerts' => 'bell', 'abandoned-cart' => 'refresh', 'pickup-schedule' => 'calendar', 'text-notifications' => 'message' ),
			'design' => array( 'colors' => 'palette', 'typography' => 'type', 'product-listings' => 'grid', 'product-page' => 'layout', 'cart-checkout' => 'cart', 'cart-icon' => 'cart', 'newsletter-popup' => 'mail', 'custom-css' => 'code', 'theme-integration' => 'puzzle', 'templates' => 'layers' ),
			'email-setup' => array( 'deliverability' => 'mail-check', 'sender' => 'at', 'order-emails' => 'receipt', 'account-emails' => 'user', 'receipt-wording' => 'quote' ),
			'initial-setup' => array( 'store-pages' => 'file-text', 'currency' => 'coins', 'goals' => 'target', 'demo-data' => 'database' ),
			'integrations' => array(
				'google-analytics' => array( 'text' => 'GA', 'bg' => '#e37400' ),
				'google-ads'       => array( 'text' => 'Ads', 'bg' => '#4285f4' ),
				'google-merchant'  => array( 'text' => 'GM', 'bg' => '#34a853' ),
				'meta-pixel'       => array( 'text' => 'f', 'bg' => '#0866ff' ),
				'mailerlite'       => array( 'text' => 'ML', 'bg' => '#09c269' ),
				'convertkit'       => array( 'text' => 'K', 'bg' => '#fb6970' ),
				'activecampaign'   => array( 'text' => 'AC', 'bg' => '#356ae6' ),
				'shareasale'       => array( 'text' => 'SAS', 'bg' => '#ff6b00' ),
				'amazon-s3'        => array( 'text' => 'S3', 'bg' => '#ff9900' ),
				'deconetwork'      => array( 'text' => 'D', 'bg' => '#e5322d' ),
				'cart-importer'    => 'download',
			),
			'language-editor' => array( 'languages' => 'globe', 'phrases' => 'quote' ),
			'payment' => array( 'active' => 'credit-card', 'more' => 'plus-circle', 'options' => 'sliders' ),
			'products' => array( 'catalog-mode' => 'eye', 'product-pages' => 'file-text', 'sharing' => 'share', 'product-lists' => 'grid', 'add-to-cart' => 'cart-plus', 'store-sidebar' => 'sidebar', 'pricing' => 'dollar', 'inventory' => 'box', 'reviews' => 'star', 'search' => 'search', 'import-export' => 'swap' ),
			'shipping-settings' => array( 'shipping-method' => 'truck', 'checkout' => 'cart', 'live-rates' => 'zap', 'carriers' => 'key', 'fraktjakt' => 'key', 'ship-from' => 'map-pin', 'packing-slip' => 'file-text', 'zones' => 'map', 'ship-to' => 'globe' ),
			'tax' => array( 'collect' => 'percent', 'state' => 'map', 'country' => 'globe', 'vat' => 'receipt', 'canada' => 'flag', 'duty' => 'anchor', 'automated' => 'cloud' ),
		);
	}

	/** Resolve an icon name ( or dashicon alias ) to a known set name, or ''. */
	public static function resolve( $name ) {
		$name = (string) $name;
		$aliases = self::aliases();
		if ( isset( $aliases[ $name ] ) ) {
			$name = $aliases[ $name ];
		}
		$paths = self::paths();
		return isset( $paths[ $name ] ) ? $name : '';
	}

	/** Inline SVG for a set name. Returns '' for unknown names. */
	public static function svg( $name, $class = 'ecst-ic-svg' ) {
		$name = self::resolve( $name );
		if ( '' === $name ) {
			return '';
		}
		$paths = self::paths();
		// Explicit width/height so the icon is sized even before the stylesheet arrives.
		return '<svg class="' . esc_attr( $class ) . '" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/** Lettermark chip HTML for a service. */
	public static function mark( $mark, $class = 'ecst-mark' ) {
		$text = isset( $mark['text'] ) ? (string) $mark['text'] : '';
		$bg   = isset( $mark['bg'] ) ? sanitize_hex_color( $mark['bg'] ) : '';
		if ( '' === $text ) {
			return '';
		}
		$style = $bg ? ' style="background:' . esc_attr( $bg ) . '"' : '';
		return '<span class="' . esc_attr( $class ) . '"' . $style . ' aria-hidden="true">' . esc_html( $text ) . '</span>';
	}

	/** Page-level icon name for a page declaration ( or catalog entry ). */
	public static function for_page( $page ) {
		$icon = isset( $page['icon'] ) ? self::resolve( $page['icon'] ) : '';
		return '' !== $icon ? $icon : 'sliders';
	}

	/**
	 * The header glyph for a section: array( 'type' => 'svg'|'mark', 'html' => ... ).
	 * Order: section 'mark', section 'icon', central default, page icon.
	 */
	public static function for_section( $page, $section, $class = 'ecst-ic-svg' ) {
		if ( ! empty( $section['mark'] ) && is_array( $section['mark'] ) ) {
			return array( 'type' => 'mark', 'html' => self::mark( $section['mark'] ) );
		}
		$name = '';
		if ( ! empty( $section['icon'] ) ) {
			$name = self::resolve( $section['icon'] );
		}
		if ( '' === $name ) {
			$defaults = self::section_defaults();
			$page_slug = isset( $page['slug'] ) ? $page['slug'] : '';
			$sec_slug  = isset( $section['slug'] ) ? $section['slug'] : '';
			if ( isset( $defaults[ $page_slug ][ $sec_slug ] ) ) {
				$default = $defaults[ $page_slug ][ $sec_slug ];
				if ( is_array( $default ) ) {
					return array( 'type' => 'mark', 'html' => self::mark( $default ) );
				}
				$name = self::resolve( $default );
			}
		}
		if ( '' === $name ) {
			$name = self::for_page( $page );
		}
		return array( 'type' => 'svg', 'html' => self::svg( $name, $class ) );
	}
}

endif;
