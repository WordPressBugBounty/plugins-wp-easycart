<?php
/**
 * WP EasyCart Admin — Extensions ( EasyCart › Extensions, a page in the admin shell ).
 *
 * Every store gets the catalog of Premium extensions. A Free, Pro or trial store sees what Premium adds and how to get it
 * ( the Premium popup; a licensed Pro store's link is the discounted upgrade, see wp_easycart_admin_edition::premium_offer() ).
 * A Premium store installs, updates and manages its extensions here once WP EasyCart Premium is installed; WP EasyCart PRO
 * offers that plugin in one click through the filter wp_easycart_premium_install_url. WP EasyCart Premium fills the page in:
 *
 *   wp_easycart_extensions_catalog        filter: the catalog ( remote versions, statuses, new extensions )
 *   wp_easycart_extension_card            filter: one card's state, chip, status line and actions
 *   wp_easycart_extensions_page_banner    filter: the banner above the catalog ( HTML )
 *   wp_easycart_extensions_page_actions   filter: header buttons ( HTML )
 *   wp_easycart_extensions_page_notice    action: notices above the catalog
 *   wp_easycart_admin_extensions_nav      filter: left nav entries under Extensions ( label, url, current )
 *   wp_easycart_extension_page_<slug>     action: an extension page that is not a settings declaration
 *   wp_easycart_extension_slot            filter: an extension's row on another screen ( Taxes, the label popup, the
 *                                         product editor, Integrations ); return HTML to replace the default
 *   wp_easycart_extensions_refresh        action: Refresh / Check again ( 6.0.2 ): forget kept answers about the license,
 *                                         WP EasyCart Premium and the catalog; the page asks again when it opens
 *   wp_easycart_premium_install_status    filter: why the one-click install is not offered ( WP EasyCart PRO, 6.0.2 )
 *   wp_easycart_premium_download_url      filter: a link that downloads WP EasyCart Premium as a .zip, for a site that
 *                                         cannot install it from the dashboard ( WP EasyCart PRO, 6.0.2 )
 *
 * An extension's settings page is a settings declaration with 'host' => 'extensions' ( see the settings registry ): it is
 * served at admin.php?page=wp-easycart-extensions&subpage=<slug> and stays out of the Settings home.
 *
 * The wp-admin Extensions menu ( page ec_adminv2 ) redirects here. It stays in the WordPress menu only while an older
 * extension still hangs its own screen under it ( BlueCheck, Groupon, Optimal Logistics and Tabs did ).
 *
 * @since 6.0.2 Replaces the static extensions brochure ( extensions-dashboard.php ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_extensions' ) ) :

	final class wp_easycart_admin_extensions {

		/** The page in the admin shell. */
		const PAGE = 'wp-easycart-extensions';

		/** The old wp-admin menu page. */
		const LEGACY_PAGE = 'ec_adminv2';

		/** WP EasyCart Premium, the plugin that installs, updates and manages extensions. */
		const PREMIUM_BASENAME = 'wp-easycart-premium/wp-easycart-premium.php';

		/** The extensions guide. */
		const DOCS = 'https://docs.wpeasycart.com/wp-easycart-extensions-guide/';

		/** Where Premium extensions were downloaded before WP EasyCart Premium. */
		const ACCOUNT_URL = 'https://www.wpeasycart.com/my-account/';

		/** The admin-post action that puts an out-of-date extension's notice off for a week ( 6.0.2 ). */
		const SNOOZE_ACTION = 'wp_easycart_extension_snooze';

		/** The admin-post action that hides an extension tip for the person ( 6.0.2 ). */
		const TIP_ACTION = 'wp_easycart_extension_tip_hide';

		/** User meta: the tips this person hid, tip => time ( 6.0.2 ). */
		const TIP_META = 'wp_easycart_extension_tips_hidden';

		/**
		 * The admin-post action ( and its nonce ) behind Refresh and Check again: forget the answers this page keeps, fire
		 * wp_easycart_extensions_refresh, and open the page again ( 6.0.2 ).
		 */
		const REFRESH_ACTION = 'wp_easycart_extensions_recheck';

		protected static $_instance = null;

		/**
		 * Normalised catalog for this request.
		 *
		 * @var array|null
		 */
		private static $catalog = null;

		/**
		 * get_plugins() for this request.
		 *
		 * @var array|null
		 */
		private static $plugins = null;

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			add_action( 'admin_init', array( $this, 'redirect_legacy_page' ) );
			add_action( 'admin_menu', array( $this, 'tidy_legacy_menu' ), 999 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
			add_action( 'wp_easycart_admin_upsell_popup', array( $this, 'print_popup' ), 20 );
			add_filter( 'wp_easycart_upsell_catalog', array( $this, 'upsell_catalog' ) );
			add_action( 'wp_easycart_admin_messages', array( $this, 'outdated_notice' ), 15 ); /* 6.0.2 */
			add_action( 'admin_post_' . self::SNOOZE_ACTION, array( $this, 'snooze_outdated' ) ); /* 6.0.2 */
			add_action( 'admin_post_' . self::TIP_ACTION, array( $this, 'hide_tip' ) ); /* 6.0.2 */
			add_action( 'admin_post_' . self::REFRESH_ACTION, array( $this, 'refresh' ) ); /* 6.0.2 */
			add_filter( 'wp_easycart_admin_success_messages', array( $this, 'refresh_message' ) ); /* 6.0.2 */
		}

		/* ------------------------------------------------------------------ */
		/* Catalog                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Categories, in display order.
		 *
		 * @return array slug => label
		 */
		public static function categories() {
			return array(
				'shipping'    => __( 'Shipping & labels', 'wp-easycart' ),
				'fulfillment' => __( 'Print on demand & fulfillment', 'wp-easycart' ),
				'tax'         => __( 'Tax', 'wp-easycart' ),
				'accounting' => __( 'Accounting', 'wp-easycart' ),
				'marketing'  => __( 'Sales channels & marketing', 'wp-easycart' ),
				'tools'      => __( 'Store tools', 'wp-easycart' ),
				'apps'       => __( 'Apps', 'wp-easycart' ),
			);
		}

		/**
		 * The extensions every store can browse. WP EasyCart Premium replaces versions and statuses with the server's.
		 *
		 * Entry keys: name, category, summary, headline, logo ( two letters ), color, folders ( install folders to match ),
		 * file ( main file ), match_file ( also match the main file in another folder, e.g. a zip unpacked as name-main ),
		 * docs, legacy_page ( the extension's own wp-admin screen ), requires ( pro, plugin ), status ( available | coming |
		 * retiring | retired ), status_note, replaced_by ( 6.0.2: a retired extension's successor, offered on its card, see
		 * replacement_action() ), links ( link-only cards: label, url, and optional title and icon apple | android | desktop,
		 * see icon_svg() ), features ( key => array( title, desc ) ), min_version
		 * ( 6.0.2: an installed version older than this is out of date for this WP EasyCart: its card says Update and EasyCart
		 * screens show update_note, see outdated() ), update_note ( why to update, in the store's words ), integrations ( 6.0.2:
		 * bool, list the extension on Settings › Integrations, see print_integrations_section() ).
		 *
		 * coming ( 6.0.2 ): announced but not released. The card says Coming soon with nothing to install, activate or download,
		 * Integrations shows it without an action, and upsell surfaces list it as coming. WP EasyCart Premium replaces the status
		 * with its server's for every slug the server lists, so a released extension turns available there; other stores keep
		 * coming until a WP EasyCart release changes the entry. A coming extension that is installed anyway ( a developer
		 * testing it ) counts as available ( status() ).
		 *
		 * @return array slug => entry
		 */
		public static function catalog() {
			if ( null !== self::$catalog ) {
				return self::$catalog;
			}
			$guide = self::DOCS;
			$catalog = array(
				'shipstation' => array(
					'name'        => 'ShipStation',
					'category'    => 'shipping',
					'summary'     => __( 'Connects your store to ShipStation: paid orders go in, shipments and tracking come back. Labels, rates at checkout and stock too.', 'wp-easycart' ),
					'headline'    => __( 'Ship every order through ShipStation', 'wp-easycart' ),
					'logo'        => 'SS',
					'color'       => '#12a5c6',
					'folders'     => array( 'wp-easycart-shipstation' ),
					'file'        => 'wp-easycart-shipstation.php',
					'docs'        => $guide . '?section=shipstation',
					'min_version' => '2.1.0',
					'update_note' => __( 'Its settings went missing in WP EasyCart 6.0.0, and ShipStation API keys now expire every few months, which stops orders reaching ShipStation. Version 2.1 brings the settings back and connects ShipStation as a store with nothing to expire. Orders keep going to ShipStation until you update.', 'wp-easycart' ),
					'features'    => array(
						'orders'        => array( __( 'Orders flow in on their own', 'wp-easycart' ), __( 'ShipStation reads paid orders from your store, and edits and cancellations follow.', 'wp-easycart' ) ),
						'tracking'      => array( __( 'Every shipment comes back', 'wp-easycart' ), __( 'Each shipment lands on the order with its items and tracking number, and the customer gets the shipped email.', 'wp-easycart' ) ),
						'labels'        => array( __( 'Labels and rates in EasyCart', 'wp-easycart' ), __( 'Buy ShipStation labels from the order screen and show its rates at checkout.', 'wp-easycart' ) ),
						'subscriptions' => array( __( 'Monthly boxes', 'wp-easycart' ), __( 'Subscriptions paid ahead ship a box every month.', 'wp-easycart' ) ),
					),
				),
				'stamps' => array(
					'name'        => 'Stamps.com',
					'category'    => 'shipping',
					'summary'     => __( 'Buys USPS, UPS, FedEx and DHL Express labels with your Stamps.com account from the order screen, one order or many, and brings tracking back to every order.', 'wp-easycart' ),
					'headline'    => __( 'Print labels from your Stamps.com account on the order screen', 'wp-easycart' ),
					'logo'        => 'St',
					'color'       => '#0b6e3f',
					'folders'     => array( 'wp-easycart-stamps' ),
					'file'        => 'wp_easycart_stamps.php',
					'docs'        => $guide . '?section=stamps-com',
					// 1.x's Settings screen; 2.0 sends this slug to its settings under Extensions.
					'legacy_page' => 'wpec-stamps-settings',
					'features'    => array(
						'labels'   => array( __( 'Every carrier on your account', 'wp-easycart' ), __( 'Compare USPS, UPS, FedEx and DHL Express rates and print the label right on the order.', 'wp-easycart' ) ),
						'bulk'     => array( __( 'Many orders at once', 'wp-easycart' ), __( 'Buy labels for a batch of orders, print them on one page and make the day\'s SCAN form.', 'wp-easycart' ) ),
						'tracking' => array( __( 'Tracking comes back', 'wp-easycart' ), __( 'Tracking lands on the order and in the shipped email, and follows the package until it is delivered.', 'wp-easycart' ) ),
						'returns'  => array( __( 'Returns and refunds', 'wp-easycart' ), __( 'Email a return label, void a label for a refund, or reprint it.', 'wp-easycart' ) ),
					),
				),
				'shippo' => array(
					'name'     => 'Shippo',
					'category' => 'shipping',
					'summary'  => __( 'Buys and prints labels for USPS, UPS, FedEx, DHL and more from the order screen, brings tracking and delivery back to the order, and shows Shippo rates at checkout.', 'wp-easycart' ),
					'headline' => __( 'Print labels for every carrier from the order screen', 'wp-easycart' ),
					'logo'     => 'Sh',
					'color'    => '#0aa143',
					'folders'  => array( 'wp-easycart-shippo' ),
					'file'     => 'wp-easycart-shippo.php',
					'docs'     => $guide,
					'features' => array(
						'labels'   => array( __( 'Labels on the order', 'wp-easycart' ), __( 'Compare carrier rates, then buy and print the label without leaving EasyCart, one order or many.', 'wp-easycart' ) ),
						'tracking' => array( __( 'Tracking comes back', 'wp-easycart' ), __( 'Tracking and delivery update the order, even for labels bought in Shippo.', 'wp-easycart' ) ),
						'rates'    => array( __( 'Rates at checkout', 'wp-easycart' ), __( 'Offer the Shippo services you choose as shipping options.', 'wp-easycart' ) ),
					),
				),
				'optimalship' => array(
					'name'        => 'OptimalShip',
					'category'    => 'shipping',
					'summary'     => __( 'Shows DHL Express rates from your Optimal Logistics account at checkout, with your markup, insurance and delivery days.', 'wp-easycart' ),
					'headline'    => __( 'Offer DHL Express at your OptimalShip rates', 'wp-easycart' ),
					'logo'        => 'OS',
					'color'       => '#b45309',
					'folders'     => array( 'wp-easycart-optimalship' ),
					'file'        => 'wpeasycart_optimalship.php',
					'docs'        => $guide . '?section=optimal-logistics',
					'requires'    => array( 'pro' => true ),
					'min_version' => '2.0.0',
					'update_note' => __( 'Version 2.0 moves its settings into EasyCart, keeps checkout working when OptimalShip is slow, and quotes in the units DHL expects. DHL keeps showing at checkout until you update.', 'wp-easycart' ),
					'features'    => array(
						'rate'    => array( __( 'DHL Express at checkout', 'wp-easycart' ), __( 'Priced live by OptimalShip from the cart\'s boxes and destination, with delivery days.', 'wp-easycart' ) ),
						'pricing' => array( __( 'Your pricing', 'wp-easycart' ), __( 'Add your markup, insure shipments, offer free DHL over an amount and choose the countries.', 'wp-easycart' ) ),
						'account' => array( __( 'Your account', 'wp-easycart' ), __( 'Uses the DHL rates on your own Optimal Logistics account.', 'wp-easycart' ) ),
					),
				),
				/* 6.0.2: print on demand, announced, not released yet ( status coming ). Both use the fulfillment groundwork
				   ( wp_easycart_fulfillment, wp_easycart_shipping_groups ) and WP EasyCart Premium 1.2.0's fulfillment core. */
				'printful' => array(
					'name'     => 'Printful',
					'category' => 'fulfillment',
					'summary'  => __( 'Sells Printful\'s print-on-demand products in your store: import them with their colors, sizes and mockups, show Printful shipping at checkout, send paid orders to Printful and bring tracking back.', 'wp-easycart' ),
					'headline' => __( 'Sell print-on-demand products made and shipped by Printful', 'wp-easycart' ),
					'logo'     => 'Pf',
					'color'    => '#d9432b',
					'folders'  => array( 'wp-easycart-printful' ),
					'file'     => 'wp-easycart-printful.php',
					'docs'     => $guide . '?section=printful',
					'requires' => array( 'pro' => true ),
					'status'   => 'coming',
					'features' => array(
						'products' => array( __( 'Your Printful products, in your store', 'wp-easycart' ), __( 'Import what you design in Printful with its colors, sizes, mockups and your prices.', 'wp-easycart' ) ),
						'orders'   => array( __( 'Orders go to Printful', 'wp-easycart' ), __( 'Paid orders reach Printful on their own, after a delay you choose or as drafts you confirm.', 'wp-easycart' ) ),
						'shipping' => array( __( 'Printful shipping at checkout', 'wp-easycart' ), __( 'Printful\'s rates and delivery times, even when the cart also holds products you ship yourself.', 'wp-easycart' ) ),
						'tracking' => array( __( 'Tracking comes back', 'wp-easycart' ), __( 'Each Printful package lands on the order with its tracking, and the customer gets the shipped email.', 'wp-easycart' ) ),
					),
				),
				'printify' => array(
					'name'     => 'Printify',
					'category' => 'fulfillment',
					'summary'  => __( 'Sells Printify\'s print-on-demand products in your store: publish or import them with their variants and mockups, show Printify shipping at checkout, send paid orders to Printify and bring tracking back.', 'wp-easycart' ),
					'headline' => __( 'Sell print-on-demand products made by Printify\'s print providers', 'wp-easycart' ),
					'logo'     => 'Py',
					'color'    => '#1f9d55',
					'folders'  => array( 'wp-easycart-printify' ),
					'file'     => 'wp-easycart-printify.php',
					'docs'     => $guide . '?section=printify',
					'requires' => array( 'pro' => true ),
					'status'   => 'coming',
					'features' => array(
						'products' => array( __( 'Publish from Printify', 'wp-easycart' ), __( 'Products you publish in Printify arrive in your store with their variants, mockups and prices.', 'wp-easycart' ) ),
						'orders'   => array( __( 'Orders go to Printify', 'wp-easycart' ), __( 'Paid orders reach Printify on their own, after a hold you choose.', 'wp-easycart' ) ),
						'shipping' => array( __( 'Printify shipping at checkout', 'wp-easycart' ), __( 'Printify\'s shipping for its items, even when the cart also holds products you ship yourself.', 'wp-easycart' ) ),
						'tracking' => array( __( 'Tracking comes back', 'wp-easycart' ), __( 'Each package lands on the order with its tracking, and the customer gets the shipped email.', 'wp-easycart' ) ),
					),
				),
				'avatax' => array(
					'name'     => 'Avalara AvaTax',
					'category' => 'tax',
					'summary'  => __( 'Calculates sales tax at checkout with Avalara and records every order in Avalara for your returns.', 'wp-easycart' ),
					'headline' => __( 'Let Avalara calculate and record your sales tax', 'wp-easycart' ),
					'logo'     => 'Av',
					'color'    => '#ea580c',
					'folders'  => array( 'wp-easycart-avatax' ),
					'file'     => 'wp_easycart_avatax.php',
					'docs'     => $guide,
					'features' => array(
						'live'    => array( __( 'Tax at checkout', 'wp-easycart' ), __( 'For the exact cart, address and customer, from Avalara.', 'wp-easycart' ) ),
						'record'  => array( __( 'Ready to file', 'wp-easycart' ), __( 'Orders are recorded, committed, voided and refunded in Avalara as they change.', 'wp-easycart' ) ),
						'backup'  => array( __( 'Backup rates', 'wp-easycart' ), __( 'Checkout keeps working if Avalara can\'t be reached.', 'wp-easycart' ) ),
					),
				),
				'quickbooks-desktop' => array(
					'name'        => 'QuickBooks Desktop',
					'category'    => 'accounting',
					'summary'     => __( 'Syncs orders, customers and products with QuickBooks Desktop through the QuickBooks Web Connector.', 'wp-easycart' ),
					'headline'    => __( 'Keep QuickBooks Desktop in step with your store', 'wp-easycart' ),
					'logo'        => 'QB',
					'color'       => '#2ca01c',
					'folders'     => array( 'wp-easycart-quickbooks' ),
					'file'        => 'wpeasycart-quickbooks.php',
					'docs'        => $guide,
					'legacy_page' => 'wpeasycart-quickbooks',
					'integrations' => true,
					'min_version' => '2.1.0',
					'update_note' => __( 'Version 2.1 moves its settings to EasyCart › Extensions › QuickBooks Desktop, shows what reached QuickBooks and what it refused, sends tips, Flex-Fees and how each order was paid, and fixes new products, refunds, VAT, duty and Canadian taxes that did not reach QuickBooks correctly. Orders keep going to QuickBooks until you update.', 'wp-easycart' ),
					'features'    => array(
						'orders'    => array( __( 'Orders in QuickBooks', 'wp-easycart' ), __( 'The Web Connector adds each order to QuickBooks Desktop.', 'wp-easycart' ) ),
						'customers' => array( __( 'Customers and products', 'wp-easycart' ), __( 'Matched to your QuickBooks lists.', 'wp-easycart' ) ),
						'schedule'  => array( __( 'On a schedule', 'wp-easycart' ), __( 'No exports and no retyping.', 'wp-easycart' ) ),
					),
				),
				/* 6.0.2: announced, not released yet ( status coming ). */
				'xero' => array(
					'name'     => 'Xero',
					'category' => 'accounting',
					'summary'  => __( 'Sends each order to Xero as an invoice with your store\'s tax, and records its payments. Refunds become credit notes, and an invoice paid in Xero marks the order paid.', 'wp-easycart' ),
					'headline' => __( 'Send every order to Xero', 'wp-easycart' ),
					'logo'     => 'Xe',
					'color'    => '#0f6e8e',
					'folders'  => array( 'wp-easycart-xero' ),
					'file'     => 'wp-easycart-xero.php',
					'docs'     => $guide . '?section=xero',
					'status'   => 'coming',
					'features' => array(
						'invoices' => array( __( 'Orders become invoices', 'wp-easycart' ), __( 'Each order goes to Xero as an invoice with its customer and the tax your store charged.', 'wp-easycart' ) ),
						'payments' => array( __( 'Payments and refunds', 'wp-easycart' ), __( 'Payments are recorded on the invoice, and refunds become credit notes.', 'wp-easycart' ) ),
						'paid'     => array( __( 'Paid in Xero, paid here', 'wp-easycart' ), __( 'When an invoice is paid in Xero, the order is marked paid.', 'wp-easycart' ) ),
					),
				),
				'quickbooks-online' => array(
					'name'     => 'QuickBooks Online',
					'category' => 'accounting',
					'summary'  => __( 'Sends each paid order to QuickBooks Online as a sales receipt ( an unpaid one as an invoice ), with your products as items and your store\'s own tax amount. Refunds become refund receipts, and invoices paid in QuickBooks and stock counts come back.', 'wp-easycart' ),
					'headline' => __( 'Keep QuickBooks Online in step with your store', 'wp-easycart' ),
					'logo'     => 'QB',
					'color'    => '#108000',
					'folders'  => array( 'wp-easycart-quickbooks-online' ),
					'file'     => 'wp-easycart-quickbooks-online.php',
					'docs'     => $guide . '?section=quickbooks-online',
					'status'   => 'coming',
					'features' => array(
						'orders' => array( __( 'Orders in QuickBooks Online', 'wp-easycart' ), __( 'A paid order becomes a sales receipt and an unpaid one an invoice, with the tax your store charged.', 'wp-easycart' ) ),
						'items'  => array( __( 'Products and refunds', 'wp-easycart' ), __( 'Your products become QuickBooks items, and refunds become refund receipts.', 'wp-easycart' ) ),
						'back'   => array( __( 'Payments and stock come back', 'wp-easycart' ), __( 'An invoice paid in QuickBooks marks the order paid, and stock counts follow QuickBooks.', 'wp-easycart' ) ),
					),
				),
				'facebook' => array(
					'name'     => 'Facebook & Instagram',
					'category' => 'marketing',
					'summary'  => __( 'Keeps a product feed for your Facebook and Instagram catalog.', 'wp-easycart' ),
					'logo'     => 'Fb',
					'color'    => '#1877f2',
					'folders'  => array( 'wp-easycart-facebook' ),
					'file'     => 'wp-easycart-facebook.php',
					'docs'     => $guide . '?section=facebook-instagram',
					'integrations' => true,
					'features' => array(
						'feed'        => array( __( 'Product feed', 'wp-easycart' ), __( 'Your products, prices and stock in Meta\'s catalog format.', 'wp-easycart' ) ),
						'current'     => array( __( 'Always current', 'wp-easycart' ), __( 'The feed follows your products as they change.', 'wp-easycart' ) ),
						'conversions' => array( __( 'Purchases reported', 'wp-easycart' ), __( 'Orders are sent to Meta from your server.', 'wp-easycart' ) ),
					),
				),
				'email-marketing' => array(
					'name'         => __( 'Email Marketing', 'wp-easycart' ),
					'category'     => 'marketing',
					'summary'      => __( 'Connects your store to Mailchimp or Klaviyo: customers, orders, products and abandoned carts go to your email marketing, so you can send cart reminders, product recommendations and campaigns by what people bought. Newsletter sign-ups stay in step both ways.', 'wp-easycart' ),
					'headline'     => __( 'Email that knows what your customers bought', 'wp-easycart' ),
					'logo'         => 'Em',
					'color'        => '#db2777',
					'folders'      => array( 'wp-easycart-email-marketing' ),
					'file'         => 'wp-easycart-email-marketing.php',
					'docs'         => $guide,
					'integrations' => true,
					'features'     => array(
						'carts'           => array( __( 'Abandoned cart emails', 'wp-easycart' ), __( 'Carts left behind reach Mailchimp or Klaviyo with their items and a link back, so its automations can bring the customer back.', 'wp-easycart' ) ),
						'recommendations' => array( __( 'Product recommendations', 'wp-easycart' ), __( 'Your products and orders go to Mailchimp or Klaviyo, so emails can suggest what each customer may buy next.', 'wp-easycart' ) ),
						'segments'        => array( __( 'Segments by what people bought', 'wp-easycart' ), __( 'Target customers by product, spend or last order. Sign-ups and unsubscribes stay in step both ways.', 'wp-easycart' ) ),
					),
				),
				'mandrill' => array(
					'name'     => 'Mandrill',
					'category' => 'marketing',
					'summary'  => __( 'Sends store email through Mailchimp Transactional ( Mandrill ).', 'wp-easycart' ),
					'logo'     => 'Md',
					'color'    => '#c02539',
					'folders'  => array( 'wp-easycart-mandrill' ),
					'file'     => 'wpeasycart_mandrill.php',
					'docs'     => $guide . '?section=mandrill-email',
				),
				'groupon' => array(
					'name'        => 'Groupon & Deal Sites',
					'category'    => 'marketing',
					'summary'     => __( 'Runs Groupon and Wowcher deals in your store: imports their voucher codes, honors what buyers paid after a deal ends, and lists redeemed codes to report.', 'wp-easycart' ),
					'headline'    => __( 'Sell a Groupon or Wowcher deal through your store', 'wp-easycart' ),
					'logo'        => 'Gr',
					'color'       => '#53a318',
					'folders'     => array( 'wp-easycart-groupon' ),
					'file'        => 'wpeasycart_groupon.php',
					'docs'        => $guide . '?section=groupon-importer',
					'min_version' => '2.0.0',
					'update_note' => __( 'Version 1 saves every code file you upload in its plugin folder, and importing a file again makes used vouchers work again. Version 2 keeps deal codes with your offers, brings your earlier imports over with their use counts, and never stores the files.', 'wp-easycart' ),
					'features'    => array(
						'import'   => array( __( 'Import deal codes', 'wp-easycart' ), __( 'Load a Groupon or Wowcher code file. Codes already used stay used, however often you import.', 'wp-easycart' ) ),
						'paid'     => array( __( 'Honor the amount paid', 'wp-easycart' ), __( 'When a deal\'s promotional value ends, its unused codes switch to what the buyer paid, as Groupon\'s terms require.', 'wp-easycart' ) ),
						'report'   => array( __( 'Report redemptions', 'wp-easycart' ), __( 'A list of redeemed codes to copy into Groupon Merchant Center or upload to Wowcher, and a reminder before payday.', 'wp-easycart' ) ),
						'results'  => array( __( 'Deal results', 'wp-easycart' ), __( 'Codes redeemed, spend beyond the voucher, new customers and repeat orders for each deal.', 'wp-easycart' ) ),
					),
				),
				'affiliatewp' => array(
					'name'        => 'AffiliateWP',
					'category'    => 'marketing',
					'summary'     => __( 'Commission rules for your products in AffiliateWP, and referrals for subscriptions, pay-later orders and refunds.', 'wp-easycart' ),
					'headline'    => __( 'Pay your affiliates the right commission on every order', 'wp-easycart' ),
					'logo'        => 'Af',
					'color'       => '#e34f43',
					'folders'     => array( 'wp-easycart-affiliatewp' ),
					'file'        => 'affiliatewp-affiliate-product-rates.php',
					/* AffiliateWP's own add-on uses the same file name, so only this folder counts. */
					'match_file'  => false,
					'docs'        => $guide . '?section=affiliatewp',
					'requires'    => array( 'plugin' => 'AffiliateWP' ),
					'min_version' => '2.0.0',
					'update_note' => __( 'Version 1.0 applies its product rates only to the classic checkout, so orders from the one-page checkout, Stripe, Square and PayPal earn AffiliateWP\'s default rate instead. Version 2.0 applies them to every order, moves them into EasyCart › Extensions › AffiliateWP, and adds referrals for subscriptions, pay-later orders and refunds. Your rates move over on their own.', 'wp-easycart' ),
					'features'    => array(
						'rules'   => array( __( 'Commission rules', 'wp-easycart' ), __( 'A percentage or an amount per item on chosen products, for chosen affiliates or everyone.', 'wp-easycart' ) ),
						'orders'  => array( __( 'Every order counts', 'wp-easycart' ), __( 'Subscriptions and payments that finish after checkout earn a referral too.', 'wp-easycart' ) ),
						'refunds' => array( __( 'Paid, then earned', 'wp-easycart' ), __( 'Pay-later orders earn once paid; refunds take the commission back.', 'wp-easycart' ) ),
					),
				),
				'tabs' => array(
					'name'        => 'Tabs',
					'category'    => 'tools',
					'summary'     => __( 'Product tabs that sell: size charts, nutrition labels, specifications, FAQs and video in ten tab styles, built from blocks or an industry template.', 'wp-easycart' ),
					'headline'    => __( 'Product tabs that sell', 'wp-easycart' ),
					'logo'        => 'Tb',
					'color'       => '#7c3aed',
					'folders'     => array( 'wp-easycart-tabs' ),
					'file'        => 'wp-easycart-tabs.php',
					'docs'        => $guide,
					/* Tabs 1.x's own screen; Tabs 2.0 and later edit in the product editor and at Extensions › Tabs, and clear this. */
					'legacy_page' => 'ec-admin-tabs',
					'min_version' => '3.0.0',
					'update_note' => __( 'Version 3.0 edits tabs in the product editor, adds shared tabs, a tab designer with 36 blocks, industry templates and ten tab styles. Your tabs carry over and keep their look until you choose a new style, and they keep showing until you update.', 'wp-easycart' ),
					'features'    => array(
						'designer'  => array( __( 'A tab designer', 'wp-easycart' ), __( 'Build each product\'s tabs from 36 blocks, size charts to nutrition labels, beside a live preview.', 'wp-easycart' ) ),
						'templates' => array( __( 'Templates for your industry', 'wp-easycart' ), __( 'Fourteen ready-made sets of tabs, from apparel to supplements, in one click.', 'wp-easycart' ) ),
						'styles'    => array( __( 'Ten tab styles', 'wp-easycart' ), __( 'Stacked sections, accordions, pills and more, a style for phones, or beside the product photos.', 'wp-easycart' ) ),
						'shared'    => array( __( 'Shared tabs', 'wp-easycart' ), __( 'Write Shipping & returns once and show it on every product, a category or a manufacturer.', 'wp-easycart' ) ),
					),
				),
				'usersync' => array(
					'name'        => __( 'WordPress User Sync', 'wp-easycart' ),
					'category'    => 'tools',
					'summary'     => __( 'One login for your store and your WordPress site: customers are WordPress users, and roles follow what they buy.', 'wp-easycart' ),
					'headline'    => __( 'One login for your store and your WordPress site', 'wp-easycart' ),
					'logo'        => 'Us',
					'color'       => '#3858e9',
					'folders'     => array( 'wp-easycart-enable-wp-users' ),
					'file'        => 'wp-easycart-enable-wp-users.php',
					'docs'        => 'https://docs.wpeasycart.com/docs/extension-guides/wp-enable-user-extension/',
					'min_version' => '2.0.0',
					'update_note' => __( 'Version 2.0 gives WordPress User Sync a settings page, links your existing customers to their WordPress accounts and fixes sign-in problems between the store and WordPress. Customers keep signing in until you update.', 'wp-easycart' ),
					'features'    => array(
						'login'   => array( __( 'One login', 'wp-easycart' ), __( 'Customers sign in once, with their WordPress password, on the store or the site.', 'wp-easycart' ) ),
						'roles'   => array( __( 'Roles from purchases', 'wp-easycart' ), __( 'A product or an active subscription gives a WordPress role, for membership and course plugins.', 'wp-easycart' ) ),
						'link'    => array( __( 'Link existing customers', 'wp-easycart' ), __( 'A dry run first, then every customer joined to their WordPress account safely.', 'wp-easycart' ) ),
					),
				),
				'bluecheck' => array(
					'name'        => 'BlueCheck',
					'category'    => 'tools',
					'summary'     => __( 'Confirms a shopper\'s age with BlueCheck before age-restricted products are sold, on every checkout, and keeps a record of each check on the order.', 'wp-easycart' ),
					'headline'    => __( 'Check ages before restricted products sell', 'wp-easycart' ),
					'logo'        => 'Bc',
					'color'       => '#0369a1',
					'folders'     => array( 'wp-easycart-bluecheck' ),
					'file'        => 'wp-easycart-bluecheck.php',
					'docs'        => $guide . '?section=bluecheck',
					'legacy_page' => 'wpeasycart-bluecheck',
					'min_version' => '2.0.0',
					'update_note' => __( 'Version 1 checks age on the classic checkout\'s first step only, so orders from the one-page checkout, express buttons and subscriptions are not checked. Version 2.0 checks every order at Place order and keeps a record of each check. Your BlueCheck account keeps working after the update.', 'wp-easycart' ),
					'features'    => array(
						'every'   => array( __( 'Every checkout', 'wp-easycart' ), __( 'One-page and classic, express buttons and subscriptions, checked at Place order.', 'wp-easycart' ) ),
						'rules'   => array( __( 'Your rules', 'wp-easycart' ), __( 'Only the categories and products you choose, at 18, 19 or 21, with states you cannot ship to.', 'wp-easycart' ) ),
						'records' => array( __( 'A record of every check', 'wp-easycart' ), __( 'The result, method and BlueCheck reference on the order, kept for four years.', 'wp-easycart' ) ),
						'once'    => array( __( 'Verify once', 'wp-easycart' ), __( 'Customers who passed are not asked again for a year.', 'wp-easycart' ) ),
					),
				),
				'apps' => array(
					'name'     => __( 'Store manager apps', 'wp-easycart' ),
					'category' => 'apps',
					'summary'  => __( 'Manage orders and products from the iPhone, iPad and Android apps, or the desktop app for Windows and Mac. The apps check your license when you sign in.', 'wp-easycart' ),
					'logo'     => 'Ap',
					'color'    => '#1f2937',
					'docs'     => 'https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/',
					'links'    => array(
						/* 6.0.2: the listing that is live ( the old id616846878 one is gone ), and each button names its platforms. */
						array(
							'label' => __( 'iPhone & iPad', 'wp-easycart' ),
							'url'   => 'https://apps.apple.com/us/app/wp-easycart-for-iphone/id1289942523',
							'icon'  => 'apple',
							'title' => __( 'WP EasyCart on the App Store', 'wp-easycart' ),
						),
						array(
							'label' => __( 'Android', 'wp-easycart' ),
							'url'   => 'https://play.google.com/store/apps/details?id=com.wpeasycart.android&hl=en',
							'icon'  => 'android',
							'title' => __( 'WP EasyCart on Google Play', 'wp-easycart' ),
						),
						array(
							'label' => __( 'Windows & Mac', 'wp-easycart' ),
							'url'   => 'https://www.wpeasycart.com/air-install/',
							'icon'  => 'desktop',
							'title' => __( 'The WP EasyCart desktop app for Windows and Mac', 'wp-easycart' ),
						),
					),
					'features' => array(
						'orders'   => array( __( 'Orders on the go', 'wp-easycart' ), __( 'See new orders and update them from your phone.', 'wp-easycart' ) ),
						'products' => array( __( 'Products and stock', 'wp-easycart' ), __( 'Edit prices and stock from any device.', 'wp-easycart' ) ),
						'desktop'  => array( __( 'A desktop app too', 'wp-easycart' ), __( 'The same tools on your computer.', 'wp-easycart' ) ),
					),
				),
				'mailchimp' => array(
					'name'        => 'MailChimp',
					'category'    => 'marketing',
					'summary'     => __( 'Synced EasyCart subscribers to Mailchimp lists.', 'wp-easycart' ),
					'logo'        => 'Mc',
					'color'       => '#9ca3af',
					'folders'     => array( 'wp-easycart-mailchimp' ),
					'status'      => 'retired',
					'status_note' => __( 'Mailchimp shut down the API this extension used, so it no longer does anything. The Email Marketing extension connects your store to Mailchimp or Klaviyo instead.', 'wp-easycart' ),
					'replaced_by' => 'email-marketing',
				),
			);
			/**
			 * Filter the extensions catalog.
			 *
			 * @since 6.0.2
			 * @param array $catalog slug => entry ( see catalog() ).
			 */
			$catalog = apply_filters( 'wp_easycart_extensions_catalog', $catalog );
			$out = array();
			foreach ( (array) $catalog as $slug => $entry ) {
				$slug = sanitize_key( $slug );
				if ( '' === $slug || ! is_array( $entry ) ) {
					continue;
				}
				$entry = wp_parse_args( $entry, array(
					'name'        => $slug,
					'category'    => 'tools',
					'summary'     => '',
					'headline'    => '',
					'logo'        => strtoupper( substr( $slug, 0, 2 ) ),
					'color'       => '#6b7280',
					'folders'     => array(),
					'file'        => '',
					'match_file'  => true,
					'docs'        => self::DOCS,
					'legacy_page' => '',
					'requires'    => array(),
					'status'      => 'available',
					'status_note' => '',
					'replaced_by' => '',
					'links'       => array(),
					'features'    => array(),
					'min_version' => '',
					'update_note' => '',
					'integrations' => false,
				) );
				$entry['integrations'] = (bool) $entry['integrations'];
				$entry['replaced_by']  = sanitize_key( (string) $entry['replaced_by'] );
				$entry['slug']    = $slug;
				$entry['folders'] = array_values( array_filter( array_map( 'sanitize_file_name', (array) $entry['folders'] ) ) );
				if ( ! in_array( $entry['status'], array( 'available', 'coming', 'retiring', 'retired' ), true ) ) {
					$entry['status'] = 'available';
				}
				$out[ $slug ] = $entry;
			}
			self::$catalog = $out;
			return self::$catalog;
		}

		/**
		 * One catalog entry, or false.
		 *
		 * @param string $slug Extension slug.
		 * @return array|false
		 */
		public static function get( $slug ) {
			$catalog = self::catalog();
			$slug    = sanitize_key( $slug );
			return isset( $catalog[ $slug ] ) ? $catalog[ $slug ] : false;
		}

		/**
		 * An entry's status on this site: its catalog status, except that a coming extension installed anyway ( a
		 * developer testing it ) counts as available.
		 *
		 * @since 6.0.2
		 * @param array $ext Catalog entry.
		 * @return string available | coming | retiring | retired
		 */
		public static function status( $ext ) {
			if ( 'coming' === $ext['status'] && '' !== self::installed( $ext['slug'] ) ) {
				return 'available';
			}
			return $ext['status'];
		}

		/**
		 * The upsell catalog key for an extension ( ext_<slug> ).
		 *
		 * @param string $slug Extension slug.
		 * @return string
		 */
		public static function upsell_key( $slug ) {
			return 'ext_' . str_replace( '-', '_', sanitize_key( $slug ) );
		}

		/**
		 * The Extensions page, or an extension's section of it.
		 *
		 * @param string $subpage Extension slug, or '' for the catalog.
		 * @param array  $args    Extra query arguments.
		 * @return string
		 */
		public static function url( $subpage = '', $args = array() ) {
			$query = array( 'page' => self::PAGE );
			if ( '' !== $subpage ) {
				$query['subpage'] = $subpage;
			}
			return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
		}

		/* ------------------------------------------------------------------ */
		/* What is on this site                                                */
		/* ------------------------------------------------------------------ */

		/**
		 * Installed plugins.
		 *
		 * @return array basename => header data
		 */
		private static function plugins() {
			if ( null === self::$plugins ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				self::$plugins = get_plugins();
			}
			return self::$plugins;
		}

		/**
		 * The installed plugin for an extension ( folder/file.php ), or ''.
		 *
		 * @param string $slug Extension slug.
		 * @return string
		 */
		public static function installed( $slug ) {
			$ext = self::get( $slug );
			if ( ! $ext || $ext['links'] ) {
				return '';
			}
			$plugins = self::plugins();
			foreach ( $plugins as $basename => $data ) {
				$folder = dirname( $basename );
				if ( in_array( $folder, $ext['folders'], true ) && ( '' === $ext['file'] || basename( $basename ) === $ext['file'] ) ) {
					return $basename;
				}
			}
			if ( $ext['match_file'] && '' !== $ext['file'] ) {
				foreach ( $plugins as $basename => $data ) {
					if ( '.' !== dirname( $basename ) && basename( $basename ) === $ext['file'] ) {
						return $basename;
					}
				}
			}
			return '';
		}

		/**
		 * Installed and active.
		 *
		 * @param string $slug Extension slug.
		 * @return bool
		 */
		public static function is_active( $slug ) {
			$basename = self::installed( $slug );
			return ( '' !== $basename && is_plugin_active( $basename ) );
		}

		/**
		 * The installed version, or ''.
		 *
		 * @param string $slug Extension slug.
		 * @return string
		 */
		public static function installed_version( $slug ) {
			$basename = self::installed( $slug );
			$plugins  = self::plugins();
			return ( '' !== $basename && isset( $plugins[ $basename ]['Version'] ) ) ? (string) $plugins[ $basename ]['Version'] : '';
		}

		/**
		 * An installed extension older than the version this WP EasyCart needs ( the catalog's min_version ). It keeps
		 * running; the card says Update and EasyCart screens show why ( outdated_notice() ).
		 *
		 * @since 6.0.2
		 * @param string $slug Extension slug.
		 * @return array|null version ( installed ), min_version, note, basename; null when up to date or not installed.
		 */
		public static function outdated( $slug ) {
			$ext = self::get( $slug );
			if ( ! $ext || '' === $ext['min_version'] || 'available' !== self::status( $ext ) ) {
				return null;
			}
			$version = self::installed_version( $slug );
			if ( '' === $version || version_compare( $version, $ext['min_version'], '>=' ) ) {
				return null;
			}
			return array(
				'version'     => $version,
				'min_version' => (string) $ext['min_version'],
				'note'        => (string) $ext['update_note'],
				'basename'    => self::installed( $slug ),
			);
		}

		/**
		 * Where to update an extension: WP EasyCart Premium's Extensions page when it is active ( it updates Premium
		 * extensions; ?update=<slug> asks it to update this one there and then, Premium 1.1.1 ), else the Plugins screen
		 * filtered to the extension.
		 *
		 * @since 6.0.2
		 * @param array $ext Catalog entry.
		 * @return string
		 */
		public static function update_url( $ext ) {
			$plugin = self::premium_plugin();
			if ( $plugin['active'] ) {
				return self::url( '', array( 'update' => $ext['slug'] ) );
			}
			return self_admin_url( 'plugins.php?plugin_status=all&s=' . rawurlencode( $ext['name'] ) );
		}

		/**
		 * Action wp_easycart_admin_messages: an active extension that is out of date for this WP EasyCart ( one notice each,
		 * put off for a week with "Remind me in a week" ).
		 *
		 * @since 6.0.2
		 */
		public function outdated_notice() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$snoozed = get_user_meta( get_current_user_id(), 'wp_easycart_extension_snooze', true );
			$snoozed = is_array( $snoozed ) ? $snoozed : array();
			foreach ( self::catalog() as $slug => $ext ) {
				$outdated = self::outdated( $slug );
				if ( ! $outdated || ! self::is_active( $slug ) || ( isset( $snoozed[ $slug ] ) && time() < (int) $snoozed[ $slug ] ) ) {
					continue;
				}
				$snooze = wp_nonce_url(
					add_query_arg(
						array(
							'action' => self::SNOOZE_ACTION,
							'ext'    => $slug,
						),
						admin_url( 'admin-post.php' )
					),
					self::SNOOZE_ACTION . '-' . $slug
				);
				/* 6.0.2: the shell's V2 notice ( it was an unstyled one-off with its own inline styles ). */
				$actions = array();
				if ( current_user_can( 'update_plugins' ) ) {
					$actions[] = array(
						'label'   => __( 'Update', 'wp-easycart' ),
						'url'     => self::update_url( $ext ),
						'primary' => true,
					);
				}
				$actions[] = array(
					'label' => __( 'Remind me in a week', 'wp-easycart' ),
					'url'   => $snooze,
				);
				$notice = wp_easycart_admin::notice_html(
					'warning',
					/* translators: 1: extension name, 2: installed version, 3: needed version. */
					sprintf( __( 'Update %1$s: version %2$s is out of date, %3$s is ready', 'wp-easycart' ), $ext['name'], $outdated['version'], $outdated['min_version'] ),
					array(
						'detail'      => $outdated['note'],
						'dismissible' => false,
						'class'       => 'ecext-outdated',
						'actions'     => $actions,
					)
				);
				echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
			}
		}

		/**
		 * The admin-post action: put an out-of-date extension's notice off for a week.
		 *
		 * @since 6.0.2
		 */
		public function snooze_outdated() {
			$slug = isset( $_GET['ext'] ) ? sanitize_key( wp_unslash( $_GET['ext'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- builds the nonce action checked next.
			check_admin_referer( self::SNOOZE_ACTION . '-' . $slug );
			if ( '' !== $slug && self::get( $slug ) ) {
				$snoozed          = get_user_meta( get_current_user_id(), 'wp_easycart_extension_snooze', true );
				$snoozed          = is_array( $snoozed ) ? $snoozed : array();
				$snoozed[ $slug ] = time() + WEEK_IN_SECONDS;
				update_user_meta( get_current_user_id(), 'wp_easycart_extension_snooze', $snoozed );
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url() );
			exit;
		}

		/**
		 * The admin-post action: hide a tip for the person who clicked its ×. Answers JSON when the tip's script asks ( ajax=1 ),
		 * else goes back to the page.
		 *
		 * @since 6.0.2
		 */
		public function hide_tip() {
			$tip = isset( $_GET['tip'] ) ? sanitize_key( wp_unslash( $_GET['tip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- builds the nonce action checked next.
			check_admin_referer( self::TIP_ACTION . '-' . $tip );
			if ( in_array( $tip, self::tips(), true ) ) {
				$hidden         = get_user_meta( get_current_user_id(), self::TIP_META, true );
				$hidden         = is_array( $hidden ) ? $hidden : array();
				$hidden[ $tip ] = time();
				update_user_meta( get_current_user_id(), self::TIP_META, $hidden );
			}
			if ( ! empty( $_GET['ajax'] ) ) {
				wp_send_json_success();
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : self::url() );
			exit;
		}

		/**
		 * Tips a person can hide.
		 *
		 * @since 6.0.2
		 * @return array
		 */
		private static function tips() {
			return array( 'offer_tools' );
		}

		/**
		 * The person hid this tip.
		 *
		 * @since 6.0.2
		 * @param string $tip Tip key.
		 * @return bool
		 */
		private static function tip_hidden( $tip ) {
			$hidden = get_user_meta( get_current_user_id(), self::TIP_META, true );
			return is_array( $hidden ) && isset( $hidden[ $tip ] );
		}

		/**
		 * The store's Premium license: 'active', 'lapsed' ( past its end date ) or 'none' ( Free, Pro, a trial ).
		 *
		 * @return string
		 */
		public static function premium_state() {
			if ( ! class_exists( 'wp_easycart_admin_edition' ) || ! wp_easycart_admin_edition::is_premium() ) {
				return 'none';
			}
			return wp_easycart_admin_edition::is_lapsed() ? 'lapsed' : 'active';
		}

		/**
		 * WP EasyCart Premium on this site.
		 *
		 * @return array installed, active, version.
		 */
		public static function premium_plugin() {
			$plugins   = self::plugins();
			$installed = isset( $plugins[ self::PREMIUM_BASENAME ] );
			$active    = $installed && is_plugin_active( self::PREMIUM_BASENAME );
			$version   = defined( 'WP_EASYCART_PREMIUM_VERSION' ) ? (string) WP_EASYCART_PREMIUM_VERSION : ( $installed && isset( $plugins[ self::PREMIUM_BASENAME ]['Version'] ) ? (string) $plugins[ self::PREMIUM_BASENAME ]['Version'] : '' );
			return array(
				'installed' => $installed,
				'active'    => $active,
				'version'   => $version,
			);
		}

		/**
		 * The one-click link that installs WP EasyCart Premium ( and then $slug ), or '' when PRO cannot offer it here.
		 *
		 * @param string $slug Extension to install once WP EasyCart Premium is in place, or ''.
		 * @return string
		 */
		public static function premium_install_url( $slug = '' ) {
			if ( 'active' !== self::premium_state() || ! current_user_can( 'install_plugins' ) ) {
				return '';
			}
			$plugin = self::premium_plugin();
			if ( $plugin['installed'] ) {
				return '';
			}
			/**
			 * The link that installs WP EasyCart Premium in one click ( WP EasyCart PRO provides it ).
			 *
			 * @since 6.0.2
			 * @param string $url  '' when one-click install is not available.
			 * @param string $slug The extension to install afterwards, or ''.
			 */
			return (string) apply_filters( 'wp_easycart_premium_install_url', '', sanitize_key( $slug ) );
		}

		/**
		 * Why the one-click install of WP EasyCart Premium is not offered here, for the banner's words ( 6.0.2 ). Asked only
		 * after premium_install_url() came back empty for a current Premium license.
		 *
		 * reason: offered | permission ( this user cannot install plugins ) | file_mods ( the site allows no installs from the
		 * dashboard ) | update_pro ( WP EasyCart PRO is older than its one-click install ) | not_premium, expired, inactive,
		 * not_found, not_offered ( the download server does not offer it to this license yet ) | site ( it knows the license by
		 * another site address ) | unreachable, server, rate ( no answer from it ) | unknown ( a WP EasyCart PRO that does not
		 * say ). checked: when the download server was last asked ( timestamp, 0 = not known ).
		 *
		 * @return array reason, checked.
		 */
		public static function premium_install_status() {
			$status = array(
				'reason'  => 'unknown',
				'checked' => 0,
			);
			/* 6.0.2: the site first. DISALLOW_FILE_MODS takes install_plugins from everyone, so the store's administrator was
			   told to ask a site administrator. */
			if ( ! self::file_mods_allowed() && ( current_user_can( 'manage_options' ) || current_user_can( 'activate_plugins' ) ) ) {
				$status['reason'] = 'file_mods';
				return $status;
			}
			if ( ! current_user_can( 'install_plugins' ) ) {
				$status['reason'] = 'permission';
				return $status;
			}
			if ( ! has_filter( 'wp_easycart_premium_install_url' ) ) {
				/* WP EasyCart PRO 6.0.1 and older have no one-click install ( it came with 6.0.2 ): Update, never an upsell. */
				if ( defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) && version_compare( (string) WP_EASYCART_ADMIN_PRO_VERSION, '6.0.2', '<' ) ) {
					$status['reason'] = 'update_pro';
				}
				return $status;
			}
			/**
			 * Why WP EasyCart Premium's one-click install is not offered ( WP EasyCart PRO answers ).
			 *
			 * @since 6.0.2
			 * @param array $status reason ( see above ), checked ( timestamp of the last answer from the download server, or 0 ).
			 */
			$status = apply_filters( 'wp_easycart_premium_install_status', $status );
			return array(
				'reason'  => ( is_array( $status ) && isset( $status['reason'] ) && '' !== sanitize_key( $status['reason'] ) ) ? sanitize_key( $status['reason'] ) : 'unknown',
				'checked' => ( is_array( $status ) && isset( $status['checked'] ) ) ? max( 0, (int) $status['checked'] ) : 0,
			);
		}

		/**
		 * This site lets plugins be installed from the dashboard ( 6.0.2 ). DISALLOW_FILE_MODS, or the file_mod_allowed
		 * filter, turns that off, and WordPress then takes install_plugins and update_plugins from everyone.
		 *
		 * @return bool
		 */
		private static function file_mods_allowed() {
			if ( function_exists( 'wp_is_file_mod_allowed' ) ) {
				return wp_is_file_mod_allowed( 'capability_update_core' ) && wp_is_file_mod_allowed( 'wp_easycart_install_premium' );
			}
			return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
		}

		/**
		 * The link that downloads WP EasyCart Premium as a .zip, for a current Premium license on a site that cannot install
		 * it from the dashboard, or '' ( 6.0.2 ). WP EasyCart PRO 6.0.2 provides it; nothing is downloaded until it is
		 * followed.
		 *
		 * @return string Unescaped URL.
		 */
		public static function premium_download_url() {
			if ( 'active' !== self::premium_state() || ! current_user_can( 'manage_options' ) ) {
				return '';
			}
			$plugin = self::premium_plugin();
			if ( $plugin['installed'] ) {
				return '';
			}
			/**
			 * The link that downloads WP EasyCart Premium as a .zip ( WP EasyCart PRO provides it ).
			 *
			 * @since 6.0.2
			 * @param string $url '' when it is not available.
			 */
			return (string) apply_filters( 'wp_easycart_premium_download_url', '' );
		}

		/**
		 * Refresh / Check again: the nonce'd admin-post link, or '' when there is nothing to ask again ( no WP EasyCart PRO,
		 * so no license ) or the user may not ( 6.0.2 ).
		 *
		 * @return string
		 */
		public static function refresh_url() {
			if ( ! defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'install_plugins' ) ) ) {
				return '';
			}
			return wp_nonce_url( add_query_arg( array( 'action' => self::REFRESH_ACTION ), admin_url( 'admin-post.php' ) ), self::REFRESH_ACTION );
		}

		/**
		 * The admin-post action behind Refresh and Check again: forget what this page keeps, then open it again, where the
		 * license and WP EasyCart Premium's one-click install are asked for afresh ( 6.0.2 ).
		 */
		public function refresh() {
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'install_plugins' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'wp-easycart' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( self::REFRESH_ACTION );
			self::forget_cached_answers();
			wp_safe_redirect( self::url( '', array( 'ecext_checked' => 1 ) ) );
			exit;
		}

		/**
		 * Forget every answer that could show this page an old plan ( 6.0.2 ). Nothing here is lost: the next page asks again.
		 *
		 * WP EasyCart PRO keeps two answers of its own: whether the download server offers WP EasyCart Premium to this license
		 * ( transient wpec_premium_available, PRO 6.0.2 ) and the license itself ( transient ec_license_data, an hour; PRO 6.0.1
		 * does not ask again when this page opens ). They are forgotten here by name, so a PRO that does not listen to
		 * wp_easycart_extensions_refresh is covered too; PRO 6.0.2 and WP EasyCart Premium forget their own on that action.
		 */
		public static function forget_cached_answers() {
			if ( defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) ) {
				delete_transient( 'wpec_premium_available' );
				delete_transient( 'ec_license_data' );
			}
			self::$catalog = null;
			if ( class_exists( 'wp_easycart_admin_edition' ) ) {
				wp_easycart_admin_edition::reset();
			}
			/**
			 * The Extensions page was asked to check again ( Refresh, Check again ): forget kept answers about the license,
			 * WP EasyCart Premium and the extensions catalog.
			 *
			 * @since 6.0.2
			 */
			do_action( 'wp_easycart_extensions_refresh' );
		}

		/**
		 * Filter wp_easycart_admin_success_messages: the note after Refresh / Check again ( 6.0.2 ).
		 *
		 * @param array $messages Success messages so far.
		 * @return array
		 */
		public function refresh_message( $messages ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: picks the note to show after the redirect.
			if ( isset( $_GET['page'], $_GET['ecext_checked'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
				$messages[] = __( 'Checked again just now.', 'wp-easycart' );
			}
			return $messages;
		}

		/**
		 * WordPress's own activation link for an installed plugin, or '' when the user may not activate plugins.
		 *
		 * @param string $basename folder/file.php.
		 * @return string
		 */
		public static function activate_url( $basename ) {
			if ( '' === $basename || ! current_user_can( 'activate_plugins' ) ) {
				return '';
			}
			return wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $basename ) ), 'activate-plugin_' . $basename );
		}

		/**
		 * The extension's own wp-admin screen ( an older extension's settings ), or ''.
		 *
		 * @param array $ext Catalog entry.
		 * @return string
		 */
		public static function legacy_settings_url( $ext ) {
			if ( '' === $ext['legacy_page'] || ! self::is_active( $ext['slug'] ) ) {
				return '';
			}
			return admin_url( 'admin.php?page=' . rawurlencode( $ext['legacy_page'] ) );
		}

		/* ------------------------------------------------------------------ */
		/* Cards                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * One card's state, chip, status line and actions.
		 *
		 * state: retired | retiring | coming | links | upsell | available | inactive | active | lapsed-available |
		 * lapsed-inactive | lapsed-active. Actions are arrays for action_html().
		 *
		 * @param array $ext Catalog entry.
		 * @return array
		 */
		public static function card( $ext ) {
			$prem     = self::premium_state();
			$status   = self::status( $ext );
			$basename = self::installed( $ext['slug'] );
			$active   = ( '' !== $basename && is_plugin_active( $basename ) );
			$version  = self::installed_version( $ext['slug'] );
			$upsell   = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( self::upsell_key( $ext['slug'] ) ) : '';
			$card     = array(
				'state'   => '',
				'chip'    => '',
				'status'  => '',
				'actions' => array(),
			);
			$learn = array(
				'label'   => __( 'Learn more', 'wp-easycart' ),
				'onclick' => $upsell,
			);

			if ( 'retired' === $status || 'retiring' === $status ) {
				$card['state']  = $status;
				$card['chip']   = '<span class="ecext-chip is-muted">' . esc_html( 'retired' === $status ? __( 'Retired', 'wp-easycart' ) : __( 'Retiring', 'wp-easycart' ) ) . '</span>';
				$card['status'] = esc_html( '' !== $ext['status_note'] ? $ext['status_note'] : __( 'This extension is no longer offered.', 'wp-easycart' ) );
				$successor      = self::replacement_action( $ext );
				if ( $successor ) {
					$card['actions'][] = $successor;
				}
				if ( '' !== $basename && current_user_can( 'activate_plugins' ) ) {
					$card['actions'][] = array(
						'label' => __( 'Remove on the Plugins screen', 'wp-easycart' ),
						'url'   => self_admin_url( 'plugins.php?plugin_status=all&s=' . rawurlencode( $ext['name'] ) ),
					);
				}
			} elseif ( 'coming' === $status ) {
				/* 6.0.2: announced, not released: nothing to install, activate or download yet. */
				$card['state'] = 'coming';
				$card['chip']  = '<span class="ecext-chip is-coming">' . esc_html__( 'Coming soon', 'wp-easycart' ) . '</span>';
				if ( 'active' === $prem ) {
					$card['status'] = esc_html__( 'Included with your license when it\'s released', 'wp-easycart' );
				} else {
					$card['status'] = esc_html__( 'Coming soon to Premium', 'wp-easycart' );
					if ( 'none' === $prem ) {
						$card['actions'][] = $learn;
					}
				}
			} elseif ( $ext['links'] ) {
				$card['state'] = 'links';
				if ( 'none' === $prem ) {
					$card['chip']      = '<span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span>';
					$card['status']    = esc_html__( 'Included with Premium', 'wp-easycart' );
					$card['actions'][] = $learn;
				} else {
					$card['status'] = esc_html__( 'Included with your license', 'wp-easycart' );
					foreach ( $ext['links'] as $link ) {
						$card['actions'][] = array(
							'label'  => $link['label'],
							'url'    => $link['url'],
							'target' => '_blank',
							'icon'   => isset( $link['icon'] ) ? (string) $link['icon'] : '',
							'title'  => isset( $link['title'] ) ? (string) $link['title'] : '',
						);
					}
				}
			} elseif ( 'none' === $prem ) {
				$card['state']     = 'upsell';
				$card['chip']      = '<span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span>';
				/* translators: %s: installed version number. */
				$card['status']    = '' !== $basename ? esc_html( sprintf( __( 'Installed · v%s · updates need Premium', 'wp-easycart' ), $version ) ) : esc_html__( 'Included with Premium', 'wp-easycart' );
				$card['actions'][] = $learn;
			} elseif ( 'active' === $prem ) {
				$card['chip'] = '<span class="ecext-chip is-included">' . esc_html__( 'Included', 'wp-easycart' ) . '</span>';
				if ( $active ) {
					$card['state']  = 'active';
					/* translators: %s: installed version number. */
					$card['status'] = '<span class="ecext-dot is-on" aria-hidden="true"></span>' . esc_html( sprintf( __( 'Active · v%s', 'wp-easycart' ), $version ) );
					$settings       = self::legacy_settings_url( $ext );
					if ( '' !== $settings ) {
						$card['actions'][] = array(
							'label' => __( 'Settings', 'wp-easycart' ),
							'url'   => $settings,
						);
					}
				} elseif ( '' !== $basename ) {
					$card['state']  = 'inactive';
					$card['status'] = '<span class="ecext-dot" aria-hidden="true"></span>' . esc_html__( 'Installed · not active', 'wp-easycart' );
					$activate       = self::activate_url( $basename );
					if ( '' !== $activate ) {
						$card['actions'][] = array(
							'label'   => __( 'Activate', 'wp-easycart' ),
							'url'     => $activate,
							'primary' => true,
						);
					}
				} else {
					$card['state']  = 'available';
					$card['status'] = esc_html__( 'Included with your license', 'wp-easycart' );
					$install        = self::premium_install_url( $ext['slug'] );
					$card['actions'][] = ( '' !== $install )
						? array(
							'label'   => __( 'Install', 'wp-easycart' ),
							'url'     => $install,
							'primary' => true,
						)
						: array(
							'label'  => __( 'Download', 'wp-easycart' ),
							'url'    => self::ACCOUNT_URL,
							'target' => '_blank',
						);
				}
			} else {
				if ( $active ) {
					$card['state']  = 'lapsed-active';
					/* translators: %s: installed version number. */
					$card['status'] = '<span class="ecext-dot is-on" aria-hidden="true"></span>' . esc_html( sprintf( __( 'Running · v%s', 'wp-easycart' ), $version ) );
				} elseif ( '' !== $basename ) {
					$card['state']  = 'lapsed-inactive';
					$card['status'] = '<span class="ecext-dot" aria-hidden="true"></span>' . esc_html__( 'Installed · not active', 'wp-easycart' );
				} else {
					$card['state']     = 'lapsed-available';
					$card['status']    = esc_html__( 'Renew Premium to install', 'wp-easycart' );
					$card['actions'][] = array(
						'label'    => __( 'Install', 'wp-easycart' ),
						'disabled' => true,
						'title'    => __( 'Renew Premium to install', 'wp-easycart' ),
					);
				}
			}

			/* 6.0.2: an installed version older than this WP EasyCart needs says Update, whatever the plan. */
			$outdated = self::outdated( $ext['slug'] );
			if ( $outdated ) {
				/* translators: %s: version number. */
				$card['chip']   .= ' <span class="ecext-chip is-update">' . esc_html( sprintf( __( 'Update to %s', 'wp-easycart' ), $outdated['min_version'] ) ) . '</span>';
				/* translators: 1: installed version, 2: needed version. */
				$card['status'] .= ( '' !== $card['status'] ? ' · ' : '' ) . esc_html( sprintf( __( 'v%1$s is out of date: update to v%2$s', 'wp-easycart' ), $outdated['version'], $outdated['min_version'] ) );
				/* Every plan: the update that brings an extension up to date comes through its own update feed too. */
				if ( current_user_can( 'update_plugins' ) ) {
					array_unshift(
						$card['actions'],
						array(
							'label'   => __( 'Update', 'wp-easycart' ),
							'url'     => self::update_url( $ext ),
							'primary' => true,
						)
					);
				}
			}

			/**
			 * Filter one extension card on the Extensions page.
			 *
			 * @since 6.0.2
			 * @param array $card    state, chip ( HTML ), status ( HTML ), actions ( see action_html() ).
			 * @param array $ext     Catalog entry.
			 * @param array $context premium ( active|lapsed|none ), basename, active, version.
			 */
			return apply_filters( 'wp_easycart_extension_card', $card, $ext, array(
				'premium'  => $prem,
				'basename' => $basename,
				'active'   => $active,
				'version'  => $version,
			) );
		}

		/**
		 * One button or link.
		 *
		 * @param array $a label, url, onclick, primary, target, disabled, title, class.
		 * @return string
		 */
		public static function action_html( $a ) {
			$a = wp_parse_args( $a, array(
				'label'    => '',
				'url'      => '',
				'onclick'  => '',
				'primary'  => false,
				'target'   => '',
				'disabled' => false,
				'title'    => '',
				'class'    => '',
				'icon'     => '',
			) );
			$cls   = 'ecv2-btn ecv2-btn-sm' . ( $a['primary'] ? ' ecv2-btn-primary' : '' ) . ( '' !== $a['class'] ? ' ' . $a['class'] : '' );
			$title = '' !== $a['title'] ? ' title="' . esc_attr( $a['title'] ) . '"' : '';
			$label = self::icon_svg( $a['icon'] ) . esc_html( $a['label'] );
			if ( $a['disabled'] ) {
				return '<button type="button" class="' . esc_attr( $cls ) . '" disabled' . $title . '>' . $label . '</button>';
			}
			if ( '' !== $a['onclick'] ) {
				return '<button type="button" class="' . esc_attr( $cls ) . '" onclick="' . esc_attr( $a['onclick'] ) . '"' . $title . '>' . $label . '</button>';
			}
			$blank = ( '_blank' === $a['target'] );
			return '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $a['url'] ) . '"' . ( $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ) . $title . '>' . $label . ( $blank ? ' <span class="dashicons dashicons-external" aria-hidden="true"></span>' : '' ) . '</a>';
		}

		/**
		 * A small platform icon for a button ( 6.0.2 ): apple, android or desktop; '' for anything else. Fixed markup only, so a
		 * catalog filter can name an icon but never add its own HTML.
		 *
		 * @param string $name Icon name.
		 * @return string SVG, or ''.
		 */
		public static function icon_svg( $name ) {
			$paths = array(
				'apple'   => '<path fill="currentColor" d="M16.37 12.63c-.02-2.3 1.88-3.4 1.96-3.46-1.07-1.56-2.73-1.78-3.32-1.8-1.41-.14-2.76.83-3.47.83-.72 0-1.82-.81-2.99-.79-1.54.02-2.96.9-3.75 2.27-1.6 2.78-.41 6.89 1.15 9.14.76 1.1 1.67 2.34 2.86 2.3 1.15-.05 1.58-.74 2.97-.74 1.38 0 1.77.74 2.98.72 1.23-.02 2.01-1.12 2.76-2.23.87-1.28 1.23-2.52 1.25-2.58-.03-.01-2.39-.92-2.4-3.66zM14.1 5.88c.63-.77 1.06-1.83.94-2.88-.91.04-2.01.61-2.66 1.37-.58.67-1.09 1.76-.96 2.8 1.02.08 2.05-.52 2.68-1.29z"/>',
				'android' => '<path fill="currentColor" d="M4 18a8 8 0 0 1 16 0z"/><path d="M8 9.3 6.3 6.4M16 9.3l1.7-2.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/><circle cx="9" cy="14.5" r="1.05" fill="#fff"/><circle cx="15" cy="14.5" r="1.05" fill="#fff"/>',
				'desktop' => '<rect x="3" y="4" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 20h6M12 16v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/>',
			);
			if ( ! is_string( $name ) || ! isset( $paths[ $name ] ) ) {
				return '';
			}
			return '<svg class="ecext-btn-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
		}

		/**
		 * The button a retired or retiring extension's card offers for its successor ( catalog replaced_by, 6.0.2 ): the
		 * successor's own card decides it, so it installs, activates or opens it the way that card would ( WP EasyCart
		 * Premium's buttons when it runs ). Try X to install or activate, Open X once it runs, Learn about X without Premium;
		 * nothing when the successor is not available or only a renewal would allow it.
		 *
		 * @since 6.0.2
		 * @param array $ext The retired extension's catalog entry.
		 * @return array action for action_html(), or array().
		 */
		public static function replacement_action( $ext ) {
			$slug = isset( $ext['replaced_by'] ) ? sanitize_key( (string) $ext['replaced_by'] ) : '';
			$new  = ( '' !== $slug && ( ! isset( $ext['slug'] ) || $slug !== $ext['slug'] ) ) ? self::get( $slug ) : false;
			if ( ! $new || 'available' !== self::status( $new ) ) {
				return array();
			}
			$card = self::card( $new );
			if ( in_array( $card['state'], array( 'active', 'lapsed-active' ), true ) ) {
				return array(
					/* translators: %s: extension name. */
					'label'   => sprintf( __( 'Open %s', 'wp-easycart' ), $new['name'] ),
					'url'     => self::url( $slug ),
					'primary' => true,
				);
			}
			foreach ( (array) $card['actions'] as $action ) {
				if ( ! empty( $action['disabled'] ) ) {
					continue;
				}
				if ( 'upsell' === $card['state'] ) {
					/* translators: %s: extension name. */
					$action['label'] = sprintf( __( 'Learn about %s', 'wp-easycart' ), $new['name'] );
				} elseif ( ! empty( $action['primary'] ) ) {
					/* translators: %s: extension name. */
					$action['label'] = sprintf( __( 'Try %s', 'wp-easycart' ), $new['name'] );
				} else {
					continue;
				}
				$action['primary'] = true;
				return $action;
			}
			return array();
		}

		/**
		 * The logo tile: two letters on the extension's color.
		 *
		 * @param array  $ext   Catalog entry.
		 * @param string $class Extra class.
		 * @return string
		 */
		public static function logo_html( $ext, $class = '' ) {
			$color = sanitize_hex_color( $ext['color'] );
			return '<span class="ecext-logo' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" style="background:' . esc_attr( $color ? $color : '#6b7280' ) . ';" aria-hidden="true">' . esc_html( substr( (string) $ext['logo'], 0, 2 ) ) . '</span>';
		}

		/* ------------------------------------------------------------------ */
		/* The page                                                            */
		/* ------------------------------------------------------------------ */

		/** Back-compat: wp_easycart_admin::load_extensions_content() calls this. */
		public function load_extensions() {
			$this->render_page();
		}

		/**
		 * The catalog, an extension's settings declaration, an extension's own page, or its locked page.
		 */
		public function render_page() {
			$sub = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
			if ( '' !== $sub ) {
				if ( class_exists( 'wp_easycart_admin_settings_registry' ) && class_exists( 'wp_easycart_admin_settings_page_v2' ) ) {
					$page = wp_easycart_admin_settings_registry::page( $sub );
					if ( $page && 'extensions' === $page['host'] ) {
						wp_easycart_admin_settings_page_v2::render( $sub );
						return;
					}
				}
				if ( has_action( 'wp_easycart_extension_page_' . $sub ) ) {
					do_action( 'wp_easycart_extension_page_' . $sub );
					return;
				}
				$ext = self::get( $sub );
				if ( $ext ) {
					self::render_extension( $ext );
					return;
				}
			}
			include EC_PLUGIN_DIRECTORY . '/admin/template/extensions/extensions-v2.php';
		}

		/**
		 * The banner above the catalog.
		 *
		 * @return string HTML.
		 */
		public static function banner_html() {
			$prem   = self::premium_state();
			$plugin = self::premium_plugin();
			$offer  = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::premium_offer() : array( 'mode' => 'get', 'url' => '', 'cta' => '', 'title' => '', 'desc' => '' );
			$state  = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::state() : array( 'end' => 0 );
			$end    = ! empty( $state['end'] ) ? date_i18n( get_option( 'date_format' ), (int) $state['end'] ) : '';
			$html   = '';

			if ( 'none' === $prem ) {
				if ( 'upgrade' === $offer['mode'] ) {
					$title = __( 'Your Pro license upgrades to Premium at a discount.', 'wp-easycart' );
					$text  = __( 'Premium adds every extension on this page, installed and updated from here.', 'wp-easycart' );
				} elseif ( 'trial' === $offer['mode'] ) {
					$title = __( 'Upgrade your trial to Premium to get every extension.', 'wp-easycart' );
					$text  = __( 'Premium is everything in Pro, plus every extension on this page.', 'wp-easycart' );
				} else {
					$title = __( 'Premium includes every extension, plus everything in Pro.', 'wp-easycart' );
					$text  = __( 'One license installs and updates ShipStation, Stamps.com, AvaTax, QuickBooks Desktop and the rest from this page, with QuickBooks Online and Xero coming soon.', 'wp-easycart' );
				}
				$html = '<div class="ecext-banner is-premium"><span class="ecv2-cl-pro-badge ecv2-cl-pro-badge-lg">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span><div class="ecext-banner-text"><b>' . esc_html( $title ) . '</b><span>' . esc_html( $text ) . '</span></div><div class="ecext-banner-actions">' . self::action_html( array( 'label' => $offer['cta'], 'url' => $offer['url'], 'target' => '_blank', 'primary' => true ) ) . '</div></div>';
			} elseif ( 'lapsed' === $prem ) {
				/* translators: %s: date the license ended. */
				$title = '' !== $end ? sprintf( __( 'Your Premium license expired on %s.', 'wp-easycart' ), $end ) : __( 'Your Premium license expired.', 'wp-easycart' );
				$html  = '<div class="ecext-banner is-lapsed" role="alert"><span class="ecext-banner-ic" aria-hidden="true">!</span><div class="ecext-banner-text"><b>' . esc_html( $title ) . '</b><span>' . esc_html__( 'Your extensions keep running with their current settings. Installing, updating and changing settings need an active license.', 'wp-easycart' ) . '</span></div><div class="ecext-banner-actions">' . self::action_html( array( 'label' => $offer['cta'], 'url' => $offer['url'], 'target' => '_blank', 'primary' => true ) ) . '</div></div>';
			} elseif ( ! $plugin['installed'] ) {
				$install = self::premium_install_url();
				if ( '' !== $install ) {
					$html = '<div class="ecext-banner is-install"><span class="ecext-banner-ic is-green" aria-hidden="true">+</span><div class="ecext-banner-text"><b>' . esc_html__( 'Your Premium license includes every extension.', 'wp-easycart' ) . '</b><span>' . esc_html__( 'Install WP EasyCart Premium, the plugin that installs, updates and manages them from this page. It takes one click.', 'wp-easycart' ) . '</span></div><div class="ecext-banner-actions">' . self::action_html( array( 'label' => __( 'Install WP EasyCart Premium', 'wp-easycart' ), 'url' => $install, 'primary' => true ) ) . '</div></div>';
				} else {
					$html = self::premium_fallback_banner();
				}
			} elseif ( ! $plugin['active'] ) {
				$activate = self::activate_url( self::PREMIUM_BASENAME );
				$html     = '<div class="ecext-banner is-install"><span class="ecext-banner-ic is-green" aria-hidden="true">+</span><div class="ecext-banner-text"><b>' . esc_html__( 'WP EasyCart Premium is installed but not active.', 'wp-easycart' ) . '</b><span>' . esc_html__( 'Activate it to install, update and manage your extensions from this page.', 'wp-easycart' ) . '</span></div><div class="ecext-banner-actions">' . ( '' !== $activate ? self::action_html( array( 'label' => __( 'Activate WP EasyCart Premium', 'wp-easycart' ), 'url' => $activate, 'primary' => true ) ) : '' ) . '</div></div>';
			} else {
				/* translators: %s: date the license ends. */
				$html = '<div class="ecext-banner is-status"><span class="ecext-dot is-on" aria-hidden="true"></span><div class="ecext-banner-text"><span>' . esc_html( '' !== $end ? sprintf( __( 'Premium license, active until %s', 'wp-easycart' ), $end ) : __( 'Premium license, active', 'wp-easycart' ) ) . '</span></div></div>';
			}
			/**
			 * Filter the banner above the extensions catalog.
			 *
			 * @since 6.0.2
			 * @param string $html   Banner HTML.
			 * @param string $prem   active|lapsed|none.
			 * @param array  $plugin WP EasyCart Premium: installed, active, version.
			 */
			return (string) apply_filters( 'wp_easycart_extensions_page_banner', $html, $prem, $plugin );
		}

		/**
		 * The banner for a current Premium license when WP EasyCart Premium is not installed and its one-click install is
		 * not offered ( 6.0.2 ). It used to send everyone to their account downloads; now it says why, and when the reason
		 * can pass ( a license upgraded moments ago, no answer from the download server ) it leads with Check again, keeping
		 * the account download as the second choice. On a site that allows no installs from the dashboard it leads with
		 * WP EasyCart Premium as a .zip when WP EasyCart PRO offers one ( premium_download_url() ), with how to add it.
		 *
		 * @return string HTML.
		 */
		private static function premium_fallback_banner() {
			$status  = self::premium_install_status();
			$check   = self::refresh_url();
			$account = array(
				'label'  => __( 'Download from my account', 'wp-easycart' ),
				'url'    => self::ACCOUNT_URL,
				'target' => '_blank',
			);
			$again   = array(
				'label'   => __( 'Check again', 'wp-easycart' ),
				'url'     => $check,
				'primary' => true,
			);
			$asked   = false;
			$actions = array();
			switch ( $status['reason'] ) {
				case 'permission':
					$text = __( 'A site administrator can install WP EasyCart Premium from this page in one click. It installs, updates and manages every extension.', 'wp-easycart' );
					break;
				case 'file_mods':
					$zip              = self::premium_download_url();
					$account['label'] = __( 'Open my account', 'wp-easycart' );
					if ( '' !== $zip ) {
						/* 6.0.2: WP EasyCart Premium as a .zip from WP EasyCart PRO, the account second. */
						$text      = __( 'This site does not allow plugins to be installed from the dashboard. Download WP EasyCart Premium, unzip it into wp-content/plugins with your host\'s file manager or SFTP ( or add it the way this site is deployed ), then activate it on Plugins.', 'wp-easycart' );
						$actions[] = array(
							'label'   => __( 'Download WP EasyCart Premium ( .zip )', 'wp-easycart' ),
							'url'     => $zip,
							'primary' => true,
						);
					} else {
						$text               = __( 'This site does not allow plugins to be installed from the dashboard. Download the extensions from your WP EasyCart account and add them the way this site is deployed.', 'wp-easycart' );
						$account['primary'] = true;
					}
					$actions[] = $account;
					break;
				case 'update_pro':
					$text = __( 'Update WP EasyCart PRO to install WP EasyCart Premium in one click. It installs, updates and manages every extension from this page.', 'wp-easycart' );
					if ( current_user_can( 'update_plugins' ) ) {
						$actions[] = array(
							'label'   => __( 'Update WP EasyCart PRO', 'wp-easycart' ),
							'url'     => self_admin_url( 'plugins.php' ),
							'primary' => true,
						);
					}
					$actions[] = $account;
					break;
				case 'unreachable':
				case 'server':
				case 'rate':
					$text      = __( 'This site could not get an answer from the WP EasyCart download server, so WP EasyCart Premium cannot be installed in one click right now. Check again in a few minutes, or download the extensions from your account.', 'wp-easycart' );
					$asked     = true;
					$actions[] = $again;
					$actions[] = $account;
					break;
				case 'site':
					$text      = __( 'The download server has your license registered to another site address, so WP EasyCart Premium cannot be installed in one click here. Activate your key on this site, then check again.', 'wp-easycart' );
					$asked     = true;
					$actions[] = $again;
					$actions[] = array(
						'label' => __( 'Check my license', 'wp-easycart' ),
						'url'   => admin_url( 'admin.php?page=wp-easycart-registration&subpage=registration' ),
					);
					$actions[] = $account;
					break;
				default:
					/* not_premium, expired, inactive, not_found, not_offered, unknown: most often a license upgraded or renewed
					   moments ago, before the download server caught up. */
					$text      = __( 'Upgraded or renewed your license moments ago? The download server may not have caught up yet. Check again to install WP EasyCart Premium in one click.', 'wp-easycart' );
					$asked     = ( 'unknown' !== $status['reason'] );
					$actions[] = $again;
					$actions[] = $account;
					break;
			}
			$note = '';
			if ( $asked && $status['checked'] > 0 && ( time() - $status['checked'] ) >= MINUTE_IN_SECONDS ) {
				/* translators: %s: time since, e.g. "12 minutes". */
				$note = '<small class="ecext-banner-note">' . esc_html( sprintf( __( 'Last checked %s ago.', 'wp-easycart' ), human_time_diff( $status['checked'] ) ) ) . '</small>';
			}
			$buttons = '';
			foreach ( $actions as $action ) {
				if ( '' === (string) $action['url'] ) {
					continue; /* Check again without a link ( see refresh_url() ): the next action leads. */
				}
				if ( '' === $buttons ) {
					$action['primary'] = true;
				}
				$buttons .= self::action_html( $action );
			}
			return '<div class="ecext-banner is-install" data-ecext-reason="' . esc_attr( $status['reason'] ) . '"><span class="ecext-banner-ic is-green" aria-hidden="true">+</span><div class="ecext-banner-text"><b>' . esc_html__( 'Your Premium license includes every extension.', 'wp-easycart' ) . '</b><span>' . esc_html( $text ) . '</span>' . $note . '</div>' . ( '' !== $buttons ? '<div class="ecext-banner-actions">' . $buttons . '</div>' : '' ) . '</div>';
		}

		/**
		 * An extension's page when nothing else serves it: what it does, and how to get it or where its settings are.
		 *
		 * @param array $ext Catalog entry.
		 */
		private static function render_extension( $ext ) {
			$card = self::card( $ext );
			echo '<div class="ecv2-wrap ecext" data-ext="' . esc_attr( $ext['slug'] ) . '">';
			echo '<div class="ecv2-page-header"><div class="ecv2-page-header-left">' . self::logo_html( $ext, 'is-lg' ) . '<div class="ecv2-page-header-text"><h1 class="ecv2-page-title">' . esc_html( $ext['name'] ) . ' ' . wp_kses_post( $card['chip'] ) . '</h1><p class="ecv2-page-subline">' . esc_html( $ext['summary'] ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- logo_html() escapes its parts.
			echo '<div class="ecv2-page-header-right"><a class="ecv2-btn ecv2-btn-ghost" href="' . esc_url( self::url() ) . '">' . esc_html__( 'All extensions', 'wp-easycart' ) . '</a>';
			if ( '' !== $ext['docs'] ) {
				echo '<a class="ecv2-btn" href="' . esc_url( $ext['docs'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Docs', 'wp-easycart' ) . ' <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
			}
			echo '</div></div>';
			if ( class_exists( 'wp_easycart_admin_upsell' ) && 'none' === self::premium_state() && $ext['features'] ) {
				wp_easycart_admin_upsell::print_feature_strip( self::upsell_key( $ext['slug'] ) );
			}
			echo '<div class="ecext-panel"><div class="ecext-panel-status">' . wp_kses_post( $card['status'] ) . '</div><div class="ecext-panel-actions">';
			foreach ( $card['actions'] as $action ) {
				echo self::action_html( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action_html() escapes every part.
			}
			echo '</div></div>';
			echo '</div>';
		}

		/**
		 * Left nav entries under Extensions ( WP EasyCart Premium adds one per installed extension ).
		 *
		 * @return array rows of label, url, current.
		 */
		public static function nav_items() {
			/**
			 * Filter the left nav entries under Extensions.
			 *
			 * @since 6.0.2
			 * @param array $items Rows of label, url, current ( bool ).
			 */
			$items = apply_filters( 'wp_easycart_admin_extensions_nav', array() );
			$out   = array();
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) && ! empty( $item['label'] ) && ! empty( $item['url'] ) ) {
					$out[] = wp_parse_args( $item, array( 'current' => false ) );
				}
			}
			return $out;
		}

		/* ------------------------------------------------------------------ */
		/* The old wp-admin Extensions menu                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * admin.php?page=ec_adminv2 → the Extensions page.
		 */
		public function redirect_legacy_page() {
			if ( wp_doing_ajax() || ! isset( $_GET['page'] ) || self::LEGACY_PAGE !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect of an old menu slug.
				return;
			}
			wp_safe_redirect( self::url() );
			exit;
		}

		/**
		 * Drop the old top-level Extensions menu unless an older extension still hangs its own screen under it.
		 */
		public function tidy_legacy_menu() {
			global $submenu;
			$others = 0;
			if ( isset( $submenu[ self::LEGACY_PAGE ] ) && is_array( $submenu[ self::LEGACY_PAGE ] ) ) {
				foreach ( $submenu[ self::LEGACY_PAGE ] as $item ) {
					if ( isset( $item[2] ) && self::LEGACY_PAGE !== $item[2] ) {
						$others++;
					}
				}
			}
			if ( 0 === $others ) {
				remove_menu_page( self::LEGACY_PAGE );
			}
		}

		/* ------------------------------------------------------------------ */
		/* Assets and the Premium popup                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Styles on every EasyCart screen ( the slots sit on Taxes, orders, products and Integrations ); the catalog script
		 * on the Extensions page only.
		 */
		public function enqueue() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page check.
			if ( 0 !== strpos( $page, 'wp-easycart' ) ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_admin_extensions_v2_css', plugins_url( 'wp-easycart/admin/css/extensions-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			if ( self::PAGE === $page ) {
				wp_enqueue_script( 'wp_easycart_admin_extensions_v2_js', plugins_url( 'wp-easycart/admin/js/extensions-v2.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );
			}
		}

		/**
		 * The Premium popup ( upgrade-screen.php in Premium mode ). It is printed on its own, beside the upsell popup, because
		 * PRO removes that one on licensed stores and swaps its content for the renewal page on lapsed ones, while a Pro or
		 * lapsed store still needs the Premium offer. A Premium store with a current license never sees it.
		 */
		public function print_popup() {
			if ( ! class_exists( 'wp_easycart_admin_upsell' ) || 'active' === self::premium_state() ) {
				return;
			}
			$ecv2_upsell = wp_easycart_admin_upsell::entry( 'extensions' );
			echo '<div id="ec_admin_premium_popup"><div class="ec_admin_upsell_popup_inner"><div class="ec_admin_upsell_popup_content">';
			include EC_PLUGIN_DIRECTORY . '/admin/template/upgrade/upgrade-screen.php';
			echo '<div style="clear:both;"></div></div></div></div>';
			echo '<script>jQuery( document.getElementById( \'ec_admin_premium_popup\' ) ).appendTo( document.body );</script>';
		}

		/**
		 * Premium entries in the upsell catalog: 'extensions', 'labels' and ext_<slug> for every extension.
		 *
		 * @param array $catalog Upsell catalog.
		 * @return array
		 */
		public function upsell_catalog( $catalog ) {
			$f = function( $icon, $title, $desc ) {
				return array( 'icon' => $icon, 'title' => $title, 'desc' => $desc );
			};
			$general = array(
				'shipping'   => $f( 'dashicons-airplane', __( 'Shipping labels', 'wp-easycart' ), __( 'ShipStation, Stamps.com and Shippo, from the order screen.', 'wp-easycart' ) ),
				'tax'        => $f( 'dashicons-media-spreadsheet', __( 'Automated tax', 'wp-easycart' ), __( 'Avalara AvaTax rates at checkout.', 'wp-easycart' ) ),
				'accounting' => $f( 'dashicons-chart-bar', __( 'Accounting', 'wp-easycart' ), __( 'Orders and customers synced to QuickBooks Desktop, with QuickBooks Online and Xero coming soon.', 'wp-easycart' ) ),
				'fulfillment' => $f( 'dashicons-art', __( 'Print on demand', 'wp-easycart' ), __( 'Printful and Printify products made and shipped for you, coming soon.', 'wp-easycart' ) ),
				'install'    => $f( 'dashicons-admin-plugins', __( 'Installed from your dashboard', 'wp-easycart' ), __( 'One click to install, and updates like any other plugin.', 'wp-easycart' ) ),
			);
			$catalog['extensions'] = array(
				'title'    => __( 'Extensions', 'wp-easycart' ),
				'headline' => __( 'Every extension, included with Premium', 'wp-easycart' ),
				'lede'     => __( 'Premium adds ShipStation, Stamps.com, AvaTax, QuickBooks Desktop and the rest to everything in Pro, installed and updated from your dashboard. QuickBooks Online and Xero are coming soon.', 'wp-easycart' ),
				'plan'     => 'premium',
				'icon'     => 'dashicons-admin-plugins',
				'docs'     => self::DOCS,
				'features' => $general,
			);
			$catalog['labels'] = array(
				'title'    => __( 'Shipping labels', 'wp-easycart' ),
				'headline' => __( 'Print shipping labels without leaving EasyCart', 'wp-easycart' ),
				'lede'     => __( 'ShipStation, Stamps.com and Shippo come with Premium, along with every other extension.', 'wp-easycart' ),
				'plan'     => 'premium',
				'icon'     => 'dashicons-printer',
				'docs'     => self::DOCS,
				'features' => array(
					'stamps'      => $f( 'dashicons-tag', 'Stamps.com', __( 'USPS, UPS, FedEx and DHL Express labels from your Stamps.com account, on the order screen.', 'wp-easycart' ) ),
					'shipstation' => $f( 'dashicons-airplane', 'ShipStation', __( 'Paid orders flow to ShipStation, and tracking flows back.', 'wp-easycart' ) ),
					'shippo'      => $f( 'dashicons-products', 'Shippo', __( 'Compare every carrier and print the label from the order screen.', 'wp-easycart' ) ),
				),
			);
			foreach ( self::catalog() as $slug => $ext ) {
				$status = self::status( $ext );
				if ( 'available' !== $status && 'coming' !== $status ) {
					continue;
				}
				$features = array();
				foreach ( $ext['features'] as $key => $feature ) {
					if ( is_array( $feature ) && isset( $feature[0], $feature[1] ) ) {
						$features[ sanitize_key( $key ) ] = $f( 'dashicons-yes-alt', $feature[0], $feature[1] );
					}
				}
				/* 6.0.2: a coming extension is offered as coming, never as something Premium installs today. */
				if ( 'coming' === $status ) {
					/* translators: %s: extension name. */
					$headline = sprintf( __( '%s, coming soon to Premium', 'wp-easycart' ), $ext['name'] );
					/* translators: %s: what the extension does, one sentence. */
					$lede = sprintf( __( '%s It is coming soon to Premium, and every Premium license will include it.', 'wp-easycart' ), $ext['summary'] );
				} else {
					/* translators: %s: extension name. */
					$headline = sprintf( __( '%s, included with Premium', 'wp-easycart' ), $ext['name'] );
					/* translators: %s: what the extension does, one sentence. */
					$lede = sprintf( __( '%s It comes with Premium, along with every other extension.', 'wp-easycart' ), $ext['summary'] );
				}
				$catalog[ self::upsell_key( $slug ) ] = array(
					'title'    => $ext['name'],
					'headline' => '' !== $ext['headline'] ? $ext['headline'] : $headline,
					'lede'     => $lede,
					'plan'     => 'premium',
					'icon'     => 'dashicons-admin-plugins',
					'docs'     => $ext['docs'],
					'features' => $features ? $features : $general,
				);
			}
			return $catalog;
		}

		/* ------------------------------------------------------------------ */
		/* Slots on other screens                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * An extension's row on another screen, when WP EasyCart Premium or the extension replaces the default.
		 *
		 * @param string $slot    tax_services | order_labels | product_tabs | integrations.
		 * @param string $slug    Extension slug.
		 * @param array  $context Anything the screen passes ( e.g. product ).
		 * @return string|null HTML, or null for the default.
		 */
		private static function slot_override( $slot, $slug, $context = array() ) {
			/**
			 * Replace an extension's row on another EasyCart screen.
			 *
			 * @since 6.0.2
			 * @param string|null $html    null keeps the default.
			 * @param string      $slot    tax_services | order_labels | product_tabs | integrations.
			 * @param string      $slug    Extension slug.
			 * @param array       $context premium ( active|lapsed|none ), active ( bool ) and the screen's own data.
			 */
			$html = apply_filters( 'wp_easycart_extension_slot', null, $slot, $slug, array_merge( array( 'premium' => self::premium_state(), 'active' => self::is_active( $slug ) ), (array) $context ) );
			return is_string( $html ) ? $html : null;
		}

		/**
		 * Where a slot's button goes for an extension this Premium store has not installed: one-click install, the
		 * Extensions page ( WP EasyCart Premium active ), or the account download.
		 *
		 * @param array $ext Catalog entry.
		 * @return array action for action_html().
		 */
		private static function install_action( $ext ) {
			$plugin = self::premium_plugin();
			if ( $plugin['active'] ) {
				/* translators: %s: extension name. */
				return array( 'label' => sprintf( __( 'Install %s', 'wp-easycart' ), $ext['name'] ), 'url' => self::url( '', array( 'install' => $ext['slug'] ) ), 'primary' => true );
			}
			$install = self::premium_install_url( $ext['slug'] );
			if ( '' !== $install ) {
				/* translators: %s: extension name. */
				return array( 'label' => sprintf( __( 'Install %s', 'wp-easycart' ), $ext['name'] ), 'url' => $install, 'primary' => true );
			}
			return array( 'label' => __( 'Download from your account', 'wp-easycart' ), 'url' => self::ACCOUNT_URL, 'target' => '_blank' );
		}

		/**
		 * A service row: logo, name and chip, one line, and actions on the right.
		 *
		 * @param array  $ext     Catalog entry.
		 * @param string $chip    HTML.
		 * @param string $line    HTML.
		 * @param array  $actions For action_html().
		 * @return string
		 */
		private static function service_row( $ext, $chip, $line, $actions ) {
			$html  = '<div class="ecext-row" data-ext="' . esc_attr( $ext['slug'] ) . '">' . self::logo_html( $ext );
			$html .= '<div class="ecext-row-text"><b>' . esc_html( $ext['name'] ) . ( '' !== $chip ? ' ' . $chip : '' ) . '</b><span>' . $line . '</span></div><div class="ecext-row-actions">';
			foreach ( $actions as $action ) {
				$html .= self::action_html( $action );
			}
			return $html . '</div></div>';
		}

		/**
		 * The default row for an extension on another screen, by the store's plan and what is installed. A coming extension
		 * ( 6.0.2 ) gets a Coming soon row with no action.
		 *
		 * @param array  $ext        Catalog entry.
		 * @param string $active_line What to say when it is installed and active ( HTML ).
		 * @param array  $manage     Action when it is active and the license is current, or array().
		 * @return string
		 */
		private static function default_row( $ext, $active_line, $manage = array() ) {
			$prem    = self::premium_state();
			$active  = self::is_active( $ext['slug'] );
			$premium = '<span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span>';
			$learn   = array(
				'label'   => __( 'Learn more', 'wp-easycart' ),
				'onclick' => class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( self::upsell_key( $ext['slug'] ) ) : '',
			);
			if ( 'coming' === self::status( $ext ) ) {
				$line = esc_html( $ext['summary'] ) . ' ' . ( 'active' === $prem ? esc_html__( 'Included with your license when it\'s released.', 'wp-easycart' ) : esc_html__( 'Coming soon to Premium.', 'wp-easycart' ) );
				return self::service_row( $ext, '<span class="ecext-chip is-coming">' . esc_html__( 'Coming soon', 'wp-easycart' ) . '</span>', $line, array() );
			}
			if ( 'none' === $prem ) {
				$offer = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::premium_offer() : array( 'mode' => 'get' );
				$line  = esc_html( $ext['summary'] ) . ( 'upgrade' === $offer['mode'] ? ' ' . esc_html__( 'Your Pro license upgrades to Premium at a discount.', 'wp-easycart' ) : '' );
				return self::service_row( $ext, $premium, $line, array( $learn ) );
			}
			if ( 'lapsed' === $prem ) {
				$offer = wp_easycart_admin_edition::premium_offer();
				$line  = $active
					? '<span class="ecext-dot is-on" aria-hidden="true"></span> ' . esc_html__( 'Running with its current settings. Renew Premium to change them.', 'wp-easycart' )
					: esc_html__( 'Included with Premium. Renew to install it.', 'wp-easycart' );
				return self::service_row( $ext, '', $line, array( array( 'label' => $offer['cta'], 'url' => $offer['url'], 'target' => '_blank' ) ) );
			}
			if ( $active ) {
				return self::service_row( $ext, '', $active_line, $manage ? array( $manage ) : array() );
			}
			$basename = self::installed( $ext['slug'] );
			if ( '' !== $basename ) {
				$activate = self::activate_url( $basename );
				return self::service_row( $ext, '<span class="ecext-chip is-included">' . esc_html__( 'Included', 'wp-easycart' ) . '</span>', esc_html__( 'Installed but not active.', 'wp-easycart' ), '' !== $activate ? array( array( 'label' => __( 'Activate', 'wp-easycart' ), 'url' => $activate, 'primary' => true ) ) : array() );
			}
			return self::service_row( $ext, '<span class="ecext-chip is-included">' . esc_html__( 'Included', 'wp-easycart' ) . '</span>', esc_html( $ext['summary'] ) . ' ' . esc_html__( 'Included with your license.', 'wp-easycart' ), array( self::install_action( $ext ) ) );
		}

		/**
		 * Settings › Taxes › Automated tax services: the AvaTax row ( a settings 'html' field render callable ).
		 *
		 * @param array $field Field declaration.
		 * @param array $page  Page declaration.
		 */
		public static function print_tax_row( $field, $page = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- settings render callable signature.
			$ext = self::get( 'avatax' );
			if ( ! $ext ) {
				return;
			}
			$html = self::slot_override( 'tax_services', 'avatax', array( 'field' => $field ) );
			if ( null === $html ) {
				$manage = self::legacy_settings_url( $ext );
				$html   = self::default_row(
					$ext,
					'<span class="ecext-dot is-on" aria-hidden="true"></span> ' . esc_html__( 'AvaTax is calculating tax at checkout.', 'wp-easycart' ),
					'' !== $manage ? array( 'label' => __( 'Settings', 'wp-easycart' ), 'url' => $manage ) : array()
				);
			}
			echo '<div class="ecext-slot">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or a filter's HTML.
		}

		/**
		 * The order screen's Create shipping label popup, under the connected services: what Premium adds, or the label
		 * extensions this Premium store has not installed yet.
		 *
		 * @since 6.0.2 $order, passed on to the slot's context.
		 * @param array       $on    shipstation / stamps / shippo => bool ( active and set up ).
		 * @param object|null $order The order on screen.
		 */
		public static function print_label_services( $on, $order = null ) {
			$labels = array( 'shipstation', 'stamps', 'shippo' );
			$html   = self::slot_override(
				'order_labels',
				'labels',
				array(
					'services' => $on,
					'order'    => $order,
				)
			);
			if ( null === $html ) {
				$prem    = self::premium_state();
				$any     = in_array( true, array_map( 'boolval', (array) $on ), true );
				$missing = array();
				foreach ( $labels as $slug ) {
					$ext = self::get( $slug );
					if ( $ext && 'coming' !== self::status( $ext ) && empty( $on[ $slug ] ) && ! self::is_active( $slug ) ) {
						$missing[] = $ext['name'];
					}
				}
				$html = '';
				if ( 'none' === $prem && ! $any ) {
					/* 6.0.2: the label window closes first, so the Premium popup is not opened behind it. */
					$onclick = ( class_exists( 'wp_easycart_admin_upsell' ) ? 'if ( window.ecodv2_label_popup_close ) { window.ecodv2_label_popup_close(); } ' . wp_easycart_admin_upsell::onclick( 'labels' ) : '' );
					$html    = '<button type="button" class="ecodv2-svc ecodv2-svc-install ecext-svc" onclick="' . esc_attr( $onclick ) . '"><span class="ecodv2-svc-logo is-neutral">&#65291;</span><span><span class="ecodv2-svc-name">' . esc_html__( 'ShipStation, Stamps.com or Shippo', 'wp-easycart' ) . ' <span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span></span><br><span class="ecodv2-svc-sub">' . esc_html__( 'Print labels without leaving EasyCart.', 'wp-easycart' ) . '</span></span><span class="ecodv2-svc-cta">' . esc_html__( 'See label extensions', 'wp-easycart' ) . '</span></button>';
				} elseif ( 'lapsed' === $prem && ! $any ) {
					$offer = wp_easycart_admin_edition::premium_offer();
					$html  = '<a class="ecodv2-svc ecodv2-svc-install ecext-svc" href="' . esc_url( $offer['url'] ) . '" target="_blank" rel="noopener noreferrer"><span class="ecodv2-svc-logo is-neutral">&#65291;</span><span><span class="ecodv2-svc-name">' . esc_html__( 'ShipStation, Stamps.com or Shippo', 'wp-easycart' ) . '</span><br><span class="ecodv2-svc-sub">' . esc_html__( 'Included with Premium. Renew to install one.', 'wp-easycart' ) . '</span></span><span class="ecodv2-svc-cta">' . esc_html( $offer['cta'] ) . ' &#8599;</span></a>';
				} elseif ( 'active' === $prem && $missing ) {
					if ( $any ) {
						/* translators: %s: extension names, e.g. "Shippo" or "Stamps.com, Shippo". */
						$html = '<p class="ecext-svc-more">' . esc_html( sprintf( __( 'Also included with your license: %s.', 'wp-easycart' ), implode( ', ', $missing ) ) ) . ' <a href="' . esc_url( self::url( '', array() ) . '#shipping' ) . '">' . esc_html__( 'Install from Extensions', 'wp-easycart' ) . '</a></p>';
					} else {
						$html = '<a class="ecodv2-svc ecodv2-svc-install ecext-svc" href="' . esc_url( self::url() . '#shipping' ) . '"><span class="ecodv2-svc-logo is-neutral">&#65291;</span><span><span class="ecodv2-svc-name">' . esc_html__( 'ShipStation, Stamps.com or Shippo', 'wp-easycart' ) . ' <span class="ecext-chip is-included">' . esc_html__( 'Included', 'wp-easycart' ) . '</span></span><br><span class="ecodv2-svc-sub">' . esc_html__( 'Print labels without leaving EasyCart. Included with your license.', 'wp-easycart' ) . '</span></span><span class="ecodv2-svc-cta">' . esc_html__( 'Choose one', 'wp-easycart' ) . ' &rarr;</span></a>';
					}
				}
			}
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or a filter's HTML.
		}

		/**
		 * The product editor's Product tabs card ( General panel, after Specifications ).
		 *
		 * @param object $product The product being edited.
		 */
		public static function print_product_tabs_card( $product ) {
			$ext = self::get( 'tabs' );
			if ( ! $ext || 'coming' === self::status( $ext ) ) {
				return;
			}
			$product_id = ( is_object( $product ) && isset( $product->product_id ) ) ? (int) $product->product_id : 0;
			$html       = self::slot_override( 'product_tabs', 'tabs', array( 'product' => $product, 'product_id' => $product_id ) );
			$prem       = self::premium_state();
			$active     = self::is_active( 'tabs' );
			if ( null === $html ) {
				if ( 'lapsed' === $prem && ! $active ) {
					return;
				}
				$chip = ( 'none' === $prem ) ? '<span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span>' : '';
				$body = '';
				if ( 'none' === $prem ) {
					$onclick = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( self::upsell_key( 'tabs' ) ) : '';
					$body    = '<div class="ecext-tabs-sample" aria-hidden="true"><span>' . esc_html__( 'Sizing guide', 'wp-easycart' ) . '</span><span>' . esc_html__( 'Care instructions', 'wp-easycart' ) . '</span><span>' . esc_html__( 'FAQ', 'wp-easycart' ) . '</span></div>'
						. '<div class="ecext-panel is-inline"><div class="ecext-panel-status">' . esc_html__( 'Add any tab you like to this product\'s page with the Tabs extension, included with Premium.', 'wp-easycart' ) . '</div><div class="ecext-panel-actions">' . self::action_html( array( 'label' => __( 'Learn more', 'wp-easycart' ), 'onclick' => $onclick ) ) . '</div></div>';
				} elseif ( 'lapsed' === $prem ) {
					$offer = wp_easycart_admin_edition::premium_offer();
					$body  = '<div class="ecext-panel is-inline"><div class="ecext-panel-status">' . esc_html__( 'This product\'s tabs still show on its page. Renew Premium to edit them.', 'wp-easycart' ) . '</div><div class="ecext-panel-actions">' . self::action_html( array( 'label' => $offer['cta'], 'url' => $offer['url'], 'target' => '_blank' ) ) . '</div></div>';
				} elseif ( $active ) {
					$url  = admin_url( 'admin.php?page=' . rawurlencode( $ext['legacy_page'] ) . ( $product_id ? '&product_id=' . $product_id : '' ) );
					$body = '<div class="ecext-panel is-inline"><div class="ecext-panel-status">' . esc_html__( 'Tabs is active. Edit this product\'s tabs on the Tabs screen.', 'wp-easycart' ) . '</div><div class="ecext-panel-actions">' . self::action_html( array( 'label' => __( 'Edit tabs', 'wp-easycart' ), 'url' => $url ) ) . '</div></div>';
				} else {
					$basename = self::installed( 'tabs' );
					$action   = ( '' !== $basename ) ? array( 'label' => __( 'Activate', 'wp-easycart' ), 'url' => self::activate_url( $basename ), 'primary' => true ) : self::install_action( $ext );
					$body     = '<div class="ecext-panel is-inline"><div class="ecext-panel-status">' . esc_html__( 'Add any tab you like to this product\'s page. The Tabs extension is included with your license.', 'wp-easycart' ) . '</div><div class="ecext-panel-actions">' . self::action_html( $action ) . '</div></div>';
				}
				$html = '<div class="ecdv2-card ecext-product-tabs" data-ecdv2-section="extension_tabs"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Product tabs', 'wp-easycart' ) . ( '' !== $chip ? ' ' . $chip : '' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Extra tabs next to Description and Specifications', 'wp-easycart' ) . '</span></div><div class="ecdv2-card-body">' . $body . '</div></div>';
			}
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or a filter's HTML.
		}

		/**
		 * The Offers hub's deal-site row ( Overview tab, under its content; PRO prints it ): Groupon & Deal Sites, which keeps
		 * each deal's codes in an offer. While the extension is not active it is only a one-line tip the person can hide: an
		 * option to know about, never a task.
		 *
		 * @since 6.0.2
		 */
		public static function print_offer_tools() {
			$ext = self::get( 'groupon' );
			if ( ! $ext || 'available' !== $ext['status'] ) {
				return;
			}
			if ( ! self::is_active( 'groupon' ) ) {
				if ( ! self::tip_hidden( 'offer_tools' ) ) {
					echo '<div class="ecext-offer-tools">' . self::offer_tools_tip( $ext ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
				}
				return;
			}
			$html = self::slot_override( 'offer_tools', 'groupon' );
			if ( null === $html ) {
				$html = self::default_row(
					$ext,
					'<span class="ecext-dot is-on" aria-hidden="true"></span> ' . esc_html__( 'Active. Your deals and their codes are on its page.', 'wp-easycart' ),
					array(
						'label' => __( 'Open deals', 'wp-easycart' ),
						'url'   => self::url( 'groupon' ),
					)
				);
			}
			echo '<div class="ecext-slot ecext-offer-tools">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or a filter's HTML.
		}

		/**
		 * The Offers hub tip for Groupon & Deal Sites while it is not active: what it does, what the store's plan says about it,
		 * one link, and a × that hides the tip for this person ( hide_tip() ).
		 *
		 * @since 6.0.2
		 * @param array $ext Catalog entry.
		 * @return string HTML.
		 */
		private static function offer_tools_tip( $ext ) {
			$prem = self::premium_state();
			$link = '';
			if ( 'active' === $prem ) {
				$note     = __( 'Included with your license.', 'wp-easycart' );
				$basename = self::installed( $ext['slug'] );
				if ( '' !== $basename ) {
					$url  = self::activate_url( $basename );
					$link = '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Activate it', 'wp-easycart' ) . '</a>' : '';
				} else {
					$action = self::install_action( $ext );
					$blank  = isset( $action['target'] ) && '_blank' === $action['target'];
					$link   = '<a href="' . esc_url( $action['url'] ) . '"' . ( $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $blank ? $action['label'] : __( 'Install it', 'wp-easycart' ) ) . '</a>';
				}
			} elseif ( 'lapsed' === $prem && class_exists( 'wp_easycart_admin_edition' ) ) {
				$note  = __( 'Included with Premium.', 'wp-easycart' );
				$offer = wp_easycart_admin_edition::premium_offer();
				$link  = '<a href="' . esc_url( $offer['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $offer['cta'] ) . '</a>';
			} else {
				$note    = __( 'Included with Premium.', 'wp-easycart' );
				$onclick = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( self::upsell_key( $ext['slug'] ) ) : '';
				$link    = '' !== $onclick ? '<button type="button" class="ecext-tip-link" onclick="' . esc_attr( $onclick ) . '">' . esc_html__( 'Learn more', 'wp-easycart' ) . '</button>' : '';
			}
			$hide = wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::TIP_ACTION,
						'tip'    => 'offer_tools',
					),
					admin_url( 'admin-post.php' )
				),
				self::TIP_ACTION . '-offer_tools'
			);
			/* The × hides the tip at once and saves in the background; without fetch() it follows the link, which comes back here. */
			$close = "var t=this.closest('.ecext-offer-tools');if(!t||!window.fetch){return true;}fetch(this.href+'&ajax=1',{credentials:'same-origin'});t.parentNode.removeChild(t);return false;";
			$html  = '<p class="ecext-tip" data-ext="' . esc_attr( $ext['slug'] ) . '"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="ecext-tip-text">';
			$html .= esc_html__( 'Selling on Groupon or Wowcher? Groupon & Deal Sites imports each deal\'s voucher codes into an offer and lists the redeemed ones to report.', 'wp-easycart' ) . ' ' . esc_html( $note );
			$html .= '' !== $link ? ' ' . $link : '';
			$html .= '</span><a class="ecext-tip-close" href="' . esc_url( $hide ) . '" title="' . esc_attr__( 'Hide this tip', 'wp-easycart' ) . '" aria-label="' . esc_attr__( 'Hide this tip', 'wp-easycart' ) . '" onclick="' . esc_attr( $close ) . '">&times;</a></p>';
			return $html;
		}

		/**
		 * Settings › Integrations › Premium extensions ( a section render callable ): every catalog entry that says
		 * integrations => true ( QuickBooks Desktop, Facebook & Instagram, Email Marketing ) and every accounting extension, in
		 * catalog order; coming ones say Coming soon, with no action.
		 *
		 * @param array $page    Page declaration.
		 * @param array $section Section declaration.
		 */
		public static function print_integrations_section( $page = array(), $section = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- settings render callable signature.
			echo '<div class="ecext-slot">';
			/* 6.0.2: integrations => true or an accounting extension, available or coming ( status() ), in catalog order. */
			foreach ( self::catalog() as $slug => $ext ) {
				if ( empty( $ext['integrations'] ) && 'accounting' !== $ext['category'] ) {
					continue;
				}
				$status = self::status( $ext );
				if ( 'available' !== $status && 'coming' !== $status ) {
					continue;
				}
				$html = self::slot_override( 'integrations', $slug );
				if ( null === $html ) {
					$manage = self::legacy_settings_url( $ext );
					$html   = self::default_row(
						$ext,
						'<span class="ecext-dot is-on" aria-hidden="true"></span> ' . esc_html__( 'Active.', 'wp-easycart' ),
						'' !== $manage ? array( 'label' => __( 'Settings', 'wp-easycart' ), 'url' => $manage ) : array()
					);
				}
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or a filter's HTML.
			}
			echo '<p class="ecext-slot-more"><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'See every extension', 'wp-easycart' ) . ' &rarr;</a></p>';
			echo '</div>';
		}
	}
endif; // End if class_exists check

function wp_easycart_admin_extensions() {
	return wp_easycart_admin_extensions::instance();
}
wp_easycart_admin_extensions();
