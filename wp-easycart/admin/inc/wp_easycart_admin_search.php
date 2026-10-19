<?php
/**
 * WP EasyCart — one admin search ( 6.0.5 ).
 *
 * The header's search box on every EasyCart screen is the only search ( the Settings boxes and the order screen's own Find
 * are folded into it; the list pages keep their filters ). It looks through:
 * - an index kept in the browser: admin pages ( the left menu, the Settings menu, Extensions ), quick actions, every setting
 *   ( the settings registry, with its current value ) and the help articles ( wp_easycart_docs_index ). index() builds it
 *   per person ( capabilities, language, plan ), ecv2_search_index serves it, the browser keeps it until version() changes;
 *   every keystroke searches it with no request;
 * - store records, asked with one request after typing pauses ( ecv2_search_records ): orders, products, customers,
 *   subscriptions, categories, manufacturers and option sets. The query is read first ( parse(): an order number, an email, a
 *   SKU or code, words ), indexed columns are tried before names, each group takes at most LIMIT rows and the request stops
 *   adding groups after BUDGET seconds. WP EasyCart PRO and extensions add their own record types and actions through filters
 *   wp_easycart_admin_search_providers and wp_easycart_admin_search_actions.
 * The window itself is admin/js/admin-search-v2.js.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_search_guard' ) ) {
	/**
	 * Nonce + capability guard for the admin search's AJAX ( anyone who can open an EasyCart screen; each part of the answer
	 * checks its own capability ).
	 *
	 * @since 6.0.5
	 * @return bool
	 */
	function ecv2_search_guard() {
		if ( ! wp_easycart_admin_search::can_search() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to search the store.', 'wp-easycart' ) ), 403 );
		}
		if ( ! isset( $_REQUEST['nonce'] ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), wp_easycart_admin_search::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'This page is out of date. Reload it and search again.', 'wp-easycart' ) ), 403 );
		}
		return true;
	}
}

if ( ! class_exists( 'wp_easycart_admin_search' ) ) :

	/**
	 * The admin search.
	 */
	class wp_easycart_admin_search {

		/** Nonce action. */
		const NONCE = 'wp-easycart-ecv2-search';

		/** Option: a number raised whenever a setting is saved, so the browser's copy of the index is made again. */
		const REV_OPTION = 'wp_easycart_admin_search_rev';

		/** Rows per group of records. */
		const LIMIT = 5;

		/** Seconds the records request may spend before it stops adding groups. */
		const BUDGET = 0.4;

		/** The EasyCart capabilities the index depends on. */
		const CAPS = array( 'wpec_reports', 'wpec_store_status', 'wpec_products', 'wpec_orders', 'wpec_users', 'wpec_marketing', 'wpec_settings', 'wpec_manager', 'wpec_diagnostics', 'wpec_registration' );

		/** Hooks. */
		public static function init() {
			add_action( 'wp_ajax_ecv2_search_index', array( __CLASS__, 'ajax_index' ) );
			add_action( 'wp_ajax_ecv2_search_records', array( __CLASS__, 'ajax_records' ) );
			add_action( 'wp_easycart_settings_saved', array( __CLASS__, 'settings_saved' ) );
			add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		}

		/** The help articles are refreshed weekly once someone manages the store. */
		public static function admin_init() {
			if ( class_exists( 'wp_easycart_docs_index' ) && self::can_search() ) {
				wp_easycart_docs_index::schedule();
			}
		}

		/**
		 * A setting was saved: the index's values change ( once a request ).
		 */
		public static function settings_saved() {
			static $done = false;
			if ( ! $done ) {
				$done = true;
				update_option( self::REV_OPTION, (int) get_option( self::REV_OPTION, 0 ) + 1, false );
			}
		}

		/**
		 * Has the person this capability ( administrators have them all )?
		 *
		 * @param string $cap Capability.
		 * @return bool
		 */
		public static function can( $cap ) {
			return current_user_can( 'manage_options' ) || current_user_can( $cap );
		}

		/**
		 * May the person search at all ( any EasyCart screen )?
		 *
		 * @return bool
		 */
		public static function can_search() {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			foreach ( self::CAPS as $cap ) {
				if ( current_user_can( $cap ) ) {
					return true;
				}
			}
			return false;
		}

		/* ------------------------------------------------------------------ The browser's index */

		/**
		 * Changes whenever the index would: WP EasyCart and PRO versions, the language, the person's capabilities, the license,
		 * active plugins, a saved setting, the help articles.
		 *
		 * @return string
		 */
		public static function version() {
			$caps = '';
			foreach ( self::CAPS as $cap ) {
				$caps .= self::can( $cap ) ? '1' : '0';
			}
			$parts = array(
				defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : '',
				defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) ? WP_EASYCART_ADMIN_PRO_VERSION : '',
				function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
				$caps,
				(string) get_option( self::REV_OPTION, 0 ),
				class_exists( 'wp_easycart_docs_index' ) ? (string) wp_easycart_docs_index::stamp() : '',
				md5( wp_json_encode( (array) get_option( 'active_plugins', array() ) ) ),
				md5( wp_json_encode( get_option( 'wp_easycart_license_info' ) ) ),
				admin_url(),
			);
			/**
			 * Parts of the admin search index's version ( add one for anything an extension puts in the index that can change ).
			 *
			 * @since 6.0.5
			 * @param array $parts Strings.
			 */
			$parts = (array) apply_filters( 'wp_easycart_admin_search_version_parts', $parts );
			return substr( md5( implode( '|', array_map( 'strval', $parts ) ) ), 0, 12 );
		}

		/**
		 * The index for this person: kept in a transient per version for 12 hours.
		 *
		 * @return array List of items { t: page | action | setting | doc, l: label, s: where, u: address, k: more words, d: a line
		 *               about it, v: a setting's value, c: plan chip pro | premium | update, n: opens in a new tab, key / pg: a
		 *               setting's key and page }.
		 */
		public static function index() {
			$key    = 'wpec_search_ix_' . self::version();
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			$items = array_merge( self::pages(), self::actions(), self::settings(), self::docs() );
			/**
			 * The admin search's index for the person asking.
			 *
			 * @since 6.0.5
			 * @param array $items Items ( see index() ).
			 */
			$items = array_values( (array) apply_filters( 'wp_easycart_admin_search_index', $items ) );
			set_transient( $key, $items, 12 * HOUR_IN_SECONDS );
			return $items;
		}

		/**
		 * One index item.
		 *
		 * @param string $type  page | action | setting | doc.
		 * @param string $label Label.
		 * @param string $where Where it is ( a trail ).
		 * @param string $url   Address.
		 * @param array  $more  k, d, v, c, n, key, pg.
		 * @return array
		 */
		private static function item( $type, $label, $where, $url, $more = array() ) {
			$item = array(
				't' => $type,
				'l' => wp_strip_all_tags( html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' ) ),
				's' => wp_strip_all_tags( html_entity_decode( (string) $where, ENT_QUOTES, 'UTF-8' ) ),
				'u' => (string) $url,
			);
			foreach ( $more as $key => $value ) {
				if ( '' !== $value && null !== $value && false !== $value ) {
					$item[ $key ] = is_string( $value ) ? wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) : $value;
				}
			}
			return $item;
		}

		/**
		 * The plan chip of a locked menu entry: '' when open, else pro ( the left menu's lock icon shows ).
		 *
		 * @return string
		 */
		private static function menu_lock() {
			return ( '' !== trim( (string) apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) ) ) ? 'pro' : '';
		}

		/**
		 * Admin pages: the left menu ( its capabilities and locks ), the Settings menu's pages, Extensions.
		 *
		 * @return array
		 */
		public static function pages() {
			$out  = array();
			$lock = self::menu_lock();
			$a    = 'admin.php?page=';
			$add  = function ( $cap, $label, $where, $url, $words = '', $chip = '' ) use ( &$out ) {
				if ( ( '' === $cap || self::can( $cap ) ) ) {
					$out[] = self::item(
						'page',
						$label,
						$where,
						admin_url( $url ),
						array(
							'k' => $words,
							'c' => $chip,
						)
					);
				}
			};
			$add( 'wpec_reports', __( 'Reports', 'wp-easycart' ), '', $a . 'wp-easycart-dashboard', 'dashboard sales stats analytics revenue' );
			$add( 'wpec_store_status', __( 'Store Status', 'wp-easycart' ), '', $a . 'wp-easycart-license-status', 'license trial upgrade checklist' );
			$p = __( 'Products', 'wp-easycart' );
			$add( 'wpec_products', $p, '', $a . 'wp-easycart-products&subpage=products', 'catalog items list' );
			$add( 'wpec_products', __( 'Inventory', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=inventory', 'stock quantity' );
			$add( 'wpec_products', __( 'Option Sets', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=option', 'variants options sizes colors swatches' );
			$add( 'wpec_products', __( 'Categories', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=category', 'collections groups' );
			$add( 'wpec_products', __( 'Menus', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=menus', 'store menu levels' );
			$add( 'wpec_products', __( 'Manufacturers', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=manufacturers', 'brands' );
			$add( 'wpec_products', __( 'Product Reviews', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=reviews', 'ratings stars' );
			$add( 'wpec_products', __( 'Subscription Plans', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=subscriptionplans', 'recurring pricing table tiers', $lock );
			$add( 'wpec_products', __( 'Import', 'wp-easycart' ), $p, $a . 'wp-easycart-products&subpage=import', 'import woocommerce square csv migrate move' );
			$o = __( 'Orders', 'wp-easycart' );
			$add( 'wpec_orders', $o, '', $a . 'wp-easycart-orders&subpage=orders', 'sales purchases invoices ship fulfill' );
			$add( 'wpec_orders', __( 'Subscriptions', 'wp-easycart' ), $o, $a . 'wp-easycart-orders&subpage=subscriptions', 'recurring billing members', $lock );
			$add( 'wpec_orders', __( 'Manage Downloads', 'wp-easycart' ), $o, $a . 'wp-easycart-orders&subpage=downloads', 'digital files', $lock );
			$u = __( 'Users', 'wp-easycart' );
			$add( 'wpec_users', __( 'Customers', 'wp-easycart' ), $u, $a . 'wp-easycart-users&subpage=accounts', 'customers accounts user accounts shoppers' );
			$add( 'wpec_users', __( 'User Roles', 'wp-easycart' ), $u, $a . 'wp-easycart-users&subpage=user-roles', 'b2b wholesale pricing roles' );
			$add( 'wpec_users', __( 'Subscribers', 'wp-easycart' ), $u, $a . 'wp-easycart-users&subpage=subscribers', 'newsletter email list' );
			if ( self::can( 'wpec_users' ) && self::can( 'wpec_manager' ) ) {
				$add( '', __( 'Limit Access', 'wp-easycart' ), $u, $a . 'wp-easycart-users&subpage=limit-access', 'members only pages membership restrict lock' );
			}
			$m = __( 'Marketing', 'wp-easycart' );
			$add( 'wpec_marketing', __( 'Offers', 'wp-easycart' ), $m, $a . 'wp-easycart-rates&subpage=offers', 'coupons discounts promotions deals codes sale', $lock );
			$add( 'wpec_marketing', __( 'Cart Links', 'wp-easycart' ), $m, $a . 'wp-easycart-rates&subpage=cart-links', 'buy links share' );
			$add( 'wpec_marketing', __( 'Abandoned Cart', 'wp-easycart' ), $m, $a . 'wp-easycart-rates&subpage=abandon-cart', 'recovery reminder email', $lock );
			$add( 'wpec_marketing', __( 'Gift Cards', 'wp-easycart' ), $m, $a . 'wp-easycart-rates&subpage=gift-cards', 'vouchers', $lock );
			$s = __( 'Settings', 'wp-easycart' );
			if ( self::can( 'wpec_settings' ) ) {
				$add( '', $s, '', $a . 'wp-easycart-settings&subpage=home', 'options configure setup' );
				if ( class_exists( 'wp_easycart_admin_settings_home' ) ) {
					foreach ( wp_easycart_admin_settings_home::navigation() as $group ) {
						foreach ( (array) $group['pages'] as $slug => $entry ) {
							$out[] = self::item(
								'page',
								isset( $entry['title'] ) ? $entry['title'] : $slug,
								$s . ' › ' . $group['label'],
								isset( $entry['url'] ) ? $entry['url'] : admin_url( $a . 'wp-easycart-settings&subpage=' . $slug ),
								array(
									'd' => isset( $entry['description'] ) ? (string) $entry['description'] : '',
									'k' => implode( ' ', array_merge( array( $slug ), isset( $entry['aliases'] ) ? (array) $entry['aliases'] : array() ) ),
									'c' => ( isset( $entry['plan'] ) && in_array( $entry['plan'], array( 'pro', 'premium' ), true ) && self::page_locked( $entry ) ) ? $entry['plan'] : '',
								)
							);
						}
					}
				}
			}
			if ( self::can( 'wpec_manager' ) && class_exists( 'wp_easycart_admin_extensions' ) ) {
				$x = __( 'Extensions', 'wp-easycart' );
				$add( '', $x, '', 'admin.php?page=wp-easycart-extensions', 'add-ons integrations premium' );
				foreach ( wp_easycart_admin_extensions::catalog() as $slug => $ext ) {
					if ( empty( $ext['name'] ) || ( isset( $ext['status'] ) && in_array( $ext['status'], array( 'retired', 'retiring' ), true ) ) ) {
						continue;
					}
					$out[] = self::item( 'page', $ext['name'], $x, admin_url( $a . 'wp-easycart-extensions' ) . '#ecext-' . rawurlencode( (string) $slug ), array( 'd' => isset( $ext['summary'] ) ? (string) $ext['summary'] : '' ) );
				}
			}
			$add( 'wpec_diagnostics', __( 'Diagnostics', 'wp-easycart' ), '', $a . 'wp-easycart-status&subpage=store-status', 'troubleshoot system status health logs' );
			$add( 'wpec_registration', __( 'Registration', 'wp-easycart' ), '', $a . 'wp-easycart-registration&subpage=registration', 'license key activate renew' );
			/* Entries other plugins added to the old palette ( label, url, group, kw ). */
			foreach ( (array) apply_filters( 'wp_easycart_shell_search_index', array() ) as $legacy ) {
				if ( is_array( $legacy ) && ! empty( $legacy['label'] ) && ! empty( $legacy['url'] ) ) {
					$url   = (string) $legacy['url'];
					$out[] = self::item( 'page', $legacy['label'], isset( $legacy['group'] ) ? (string) $legacy['group'] : '', 0 === strpos( $url, 'http' ) ? $url : admin_url( $url ), array( 'k' => isset( $legacy['kw'] ) ? (string) $legacy['kw'] : '' ) );
				}
			}
			return $out;
		}

		/**
		 * Is a Settings menu entry with a plan locked for this store?
		 *
		 * @param array $entry Settings catalog entry.
		 * @return bool
		 */
		private static function page_locked( $entry ) {
			if ( ! class_exists( 'wp_easycart_admin_pro_gate' ) ) {
				return true;
			}
			if ( ! wp_easycart_admin_pro_gate::is_enabled() ) {
				return true;
			}
			return ( 'premium' === $entry['plan'] && class_exists( 'wp_easycart_admin_edition' ) && ! wp_easycart_admin_edition::is_premium() );
		}

		/**
		 * Quick actions ( Add product … ). WP EasyCart PRO adds its own ( filter wp_easycart_admin_search_actions ). An address ending
		 * #wpec-add-new opens that list and clicks its own Add button ( admin-search-v2.js ), so the list decides how adding works.
		 *
		 * @return array
		 */
		public static function actions() {
			$out   = array();
			$where = __( 'Quick action', 'wp-easycart' );
			$a     = 'admin.php?page=';
			if ( self::can( 'wpec_products' ) ) {
				$out[] = self::item( 'action', __( 'Add product', 'wp-easycart' ), $where, function_exists( 'wp_easycart_admin_product_new_url' ) ? wp_easycart_admin_product_new_url() : admin_url( $a . 'wp-easycart-products&subpage=products&ec_admin_form_action=add-new' ), array( 'k' => 'new product create item' ) );
				$out[] = self::item( 'action', __( 'Add category', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-products&subpage=category' ) . '#wpec-add-new', array( 'k' => 'new category create' ) );
				$out[] = self::item( 'action', __( 'Add option set', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-products&subpage=option' ) . '#wpec-add-new', array( 'k' => 'new option set variants create' ) );
				$out[] = self::item( 'action', __( 'Add manufacturer', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-products&subpage=manufacturers' ) . '#wpec-add-new', array( 'k' => 'new brand create' ) );
				$out[] = self::item( 'action', __( 'Import products', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-products&subpage=import' ), array( 'k' => 'import woocommerce square csv' ) );
			}
			if ( self::can( 'wpec_users' ) ) {
				$out[] = self::item( 'action', __( 'Add customer', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-users&subpage=accounts' ) . '#wpec-add-new', array( 'k' => 'new customer account user create' ) );
			}
			if ( self::can( 'wpec_diagnostics' ) ) {
				$out[] = self::item( 'action', __( 'Run diagnostics', 'wp-easycart' ), $where, admin_url( $a . 'wp-easycart-status&subpage=store-status' ), array( 'k' => 'check problems troubleshoot status' ) );
			}
			$store = (int) get_option( 'ec_option_storepage' );
			$store = $store ? get_permalink( $store ) : home_url( '/' );
			$out[] = self::item(
				'action',
				__( 'View my store', 'wp-easycart' ),
				$where,
				(string) $store,
				array(
					'k' => 'storefront shop visit open',
					'n' => 1,
				)
			);
			/**
			 * Quick actions in the admin search ( index items of type action ).
			 *
			 * @since 6.0.5
			 * @param array $out Items.
			 */
			return (array) apply_filters( 'wp_easycart_admin_search_actions', $out );
		}

		/**
		 * Every setting and settings section, from the settings registry ( its own words, keywords, the current value without a
		 * query, the plan chip ).
		 *
		 * @return array
		 */
		public static function settings() {
			if ( ! self::can( 'wpec_settings' ) || ! class_exists( 'wp_easycart_admin_settings_registry' ) ) {
				return array();
			}
			$out = array();
			foreach ( wp_easycart_admin_settings_registry::pages() as $slug => $page ) {
				if ( empty( $page['sections'] ) ) {
					continue;
				}
				$host  = isset( $page['host'] ) ? $page['host'] : 'settings';
				$trail = ( 'extensions' === $host ? __( 'Extensions', 'wp-easycart' ) : __( 'Settings', 'wp-easycart' ) ) . ' › ' . $page['title'];
				foreach ( $page['sections'] as $section_slug => $section ) {
					$section_chip = self::chip( $section );
					$where        = $trail . ( ! empty( $section['title'] ) ? ' › ' . $section['title'] : '' );
					foreach ( $section['fields'] as $key => $field ) {
						if ( 'html' === $field['type'] || '' === trim( (string) $field['label'] ) ) {
							continue;
						}
						$value = ( 'password' === $field['type'] ) ? '' : wp_easycart_admin_settings_registry::value_text( $field, false );
						$value = ( __( 'Empty', 'wp-easycart' ) === $value ) ? '' : $value;
						$value = function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( (string) $value ), 0, 40 ) : substr( wp_strip_all_tags( (string) $value ), 0, 40 );
						$chip  = self::chip( $field );
						$out[] = self::item(
							'setting',
							$field['label'],
							$where,
							wp_easycart_admin_settings_registry::page_url( $slug, $key, $host ),
							array(
								'd'   => function_exists( 'mb_substr' ) ? mb_substr( wp_strip_all_tags( (string) $field['desc'] ), 0, 160 ) : substr( wp_strip_all_tags( (string) $field['desc'] ), 0, 160 ),
								'k'   => implode( ' ', (array) $field['keywords'] ),
								'v'   => $value,
								'c'   => '' !== $chip ? $chip : $section_chip,
								'key' => $key,
								'pg'  => $slug,
							)
						);
					}

					// A section drawn from its own markup or tools ( order statuses, the box library … ) is found by its title.
					if ( ( ! empty( $section['render'] ) || ! empty( $section['actions'] ) ) && ! empty( $section['title'] ) ) {
						$words = isset( $section['keywords'] ) ? implode( ' ', (array) $section['keywords'] ) : '';
						foreach ( isset( $section['actions'] ) ? (array) $section['actions'] : array() as $action ) {
							$words .= ' ' . ( isset( $action['label'] ) ? $action['label'] : '' );
						}
						$out[] = self::item(
							'setting',
							$section['title'],
							$trail,
							wp_easycart_admin_settings_registry::page_url( $slug, '', $host ) . '#ecst-sec-' . rawurlencode( $section_slug ),
							array(
								'd'  => isset( $section['hint'] ) ? (string) $section['hint'] : '',
								'k'  => $words,
								'c'  => $section_chip,
								'pg' => $slug,
							)
						);
					}
				}
			}
			/* Things that live in a page's own markup ( payment gateways, carriers … ), as the Settings search had them. */
			foreach ( (array) apply_filters( 'wp_easycart_settings_search_items', array() ) as $extra ) {
				if ( ! is_array( $extra ) || empty( $extra['label'] ) || empty( $extra['url'] ) ) {
					continue;
				}
				$where = trim( ( isset( $extra['page_title'] ) ? (string) $extra['page_title'] : '' ) . ( ! empty( $extra['section_title'] ) ? ' › ' . $extra['section_title'] : '' ), ' ›' );
				$out[] = self::item(
					'setting',
					$extra['label'],
					__( 'Settings', 'wp-easycart' ) . ( '' !== $where ? ' › ' . $where : '' ),
					(string) $extra['url'],
					array(
						'd'  => isset( $extra['desc'] ) ? (string) $extra['desc'] : '',
						'k'  => isset( $extra['keywords'] ) ? implode( ' ', (array) $extra['keywords'] ) : '',
						'v'  => ( empty( $extra['locked'] ) && isset( $extra['value_text'] ) ) ? (string) $extra['value_text'] : '',
						'c'  => ! empty( $extra['locked'] ) ? 'pro' : '',
						'pg' => isset( $extra['page'] ) ? (string) $extra['page'] : '',
					)
				);
			}
			return $out;
		}

		/**
		 * A declaration's chip: '' when it can be used, update when only a newer WP EasyCart PRO is missing, else its plan.
		 *
		 * @param array $decl Field or section declaration.
		 * @return string
		 */
		private static function chip( $decl ) {
			if ( empty( $decl['pro'] ) || ! wp_easycart_admin_settings_registry::is_locked( $decl ) ) {
				return '';
			}
			if ( class_exists( 'wp_easycart_admin_pro_gate' ) ) {
				$state = wp_easycart_admin_pro_gate::evaluate( array( 'min_version' => isset( $decl['pro_min_version'] ) ? $decl['pro_min_version'] : '6.0.0' ) );
				if ( isset( $state['state'] ) && 'update' === $state['state'] ) {
					return 'update';
				}
			}
			return ( 'premium' === $decl['pro'] ) ? 'premium' : 'pro';
		}

		/**
		 * Help articles ( wp_easycart_docs_index ).
		 *
		 * @return array
		 */
		public static function docs() {
			if ( ! class_exists( 'wp_easycart_docs_index' ) ) {
				return array();
			}
			$out  = array();
			$list = wp_easycart_docs_index::get();
			foreach ( $list['items'] as $doc ) {
				if ( empty( $doc['t'] ) || empty( $doc['u'] ) ) {
					continue;
				}
				$out[] = self::item(
					'doc',
					(string) $doc['t'],
					isset( $doc['c'] ) ? (string) $doc['c'] : '',
					esc_url_raw( (string) $doc['u'] ),
					array(
						'd'  => isset( $doc['e'] ) ? (string) $doc['e'] : '',
						'n'  => 1,
						/* 6.0.5: what the article is about ( its headings and key words ), for admin-search-v2.js docScore(). */
						'dh' => isset( $doc['h'] ) ? (string) $doc['h'] : '',
						'dw' => isset( $doc['w'] ) ? (string) $doc['w'] : '',
					)
				);
			}
			/* The words most articles use ( wp_easycart_docs_index::describe() ): they weigh less in a question. Not a row. */
			if ( $out && ! empty( $list['common'] ) && is_array( $list['common'] ) ) {
				$out[] = array(
					't'  => 'docmeta',
					'l'  => '',
					's'  => '',
					'u'  => '',
					'dc' => implode( ' ', array_map( 'sanitize_key', $list['common'] ) ),
				);
			}
			return $out;
		}

		/* ------------------------------------------------------------------ The page */

		/**
		 * The window's settings ( wp_localize_script on every EasyCart screen ).
		 *
		 * @return array
		 */
		public static function config() {
			$mac = isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 'Mac' );
			return array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'version'     => self::version(),
				'user'        => (int) get_current_user_id(),
				'here'        => self::here(),
				'context'     => self::context(),
				'records'     => self::can( 'wpec_orders' ) || self::can( 'wpec_products' ) || self::can( 'wpec_users' ),
				'mod'         => $mac ? '⌘' : 'Ctrl',
				'i18n'        => array(
					'label'       => __( 'Search everything', 'wp-easycart' ),
					'placeholder' => __( 'Search orders, products, settings, help…', 'wp-easycart' ),
					'close'       => __( 'Close', 'wp-easycart' ),
					'all'         => __( 'All', 'wp-easycart' ),
					'recent'      => __( 'Recent', 'wp-easycart' ),
					'actions'     => __( 'Quick actions', 'wp-easycart' ),
					'pages'       => __( 'Pages', 'wp-easycart' ),
					'settings'    => __( 'Settings', 'wp-easycart' ),
					'help'        => __( 'Help', 'wp-easycart' ),
					'this_page'   => __( 'This order', 'wp-easycart' ),
					'looking'     => __( 'Looking in orders, products and customers…', 'wp-easycart' ),
					'loading'     => __( 'Getting the search ready…', 'wp-easycart' ),
					/* translators: %s: what was typed. */
					'none'        => __( 'Nothing matches “%s”.', 'wp-easycart' ),
					/* translators: %s: what was typed. */
					'docs_for'    => __( 'Search the help site for “%s”', 'wp-easycart' ),
					'see_all'     => __( 'See all', 'wp-easycart' ),
					'tip'         => __( 'Type # and a number for an order, or paste an email, SKU or code.', 'wp-easycart' ),
					'tip_label'   => __( 'Tip:', 'wp-easycart' ),
					'move'        => __( 'move', 'wp-easycart' ),
					'open'        => __( 'open', 'wp-easycart' ),
					'new_tab'     => __( 'new tab', 'wp-easycart' ),
					'next_group'  => __( 'next group', 'wp-easycart' ),
					'opens_docs'  => __( 'opens docs.wpeasycart.com', 'wp-easycart' ),
					'docs'        => __( 'Docs', 'wp-easycart' ),
					'pro'         => __( 'Pro', 'wp-easycart' ),
					'premium'     => __( 'Premium', 'wp-easycart' ),
					'update'      => __( 'Update', 'wp-easycart' ),
					'failed'      => __( 'Store records could not be searched just now.', 'wp-easycart' ),
					'partial'     => __( 'Some groups took too long; open a list to see more.', 'wp-easycart' ),
					/* translators: %s: how long ago, e.g. "5 minutes". */
					'ago'         => __( '%s ago', 'wp-easycart' ),
					'just_now'    => __( 'just now', 'wp-easycart' ),
					'search_all'  => __( 'Search everywhere', 'wp-easycart' ),
					'wp_commands' => __( 'WordPress commands', 'wp-easycart' ),
					/* translators: %d: number of results shown. */
					'results'     => __( 'Results: %d', 'wp-easycart' ),
				),
				'docs_search' => 'https://docs.wpeasycart.com/?s=',
			);
		}

		/**
		 * Which screen this is, for ranking: settings | orders | order | products | product | users | marketing | other.
		 *
		 * @return string
		 */
		public static function context() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : '';
			$action  = isset( $_GET['ec_admin_form_action'] ) ? sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( 'wp-easycart-settings' === $page ) {
				return 'settings';
			}
			if ( 'wp-easycart-orders' === $page ) {
				return ( 'edit' === $action && 'subscriptions' !== $subpage ) ? 'order' : 'orders';
			}
			if ( 'wp-easycart-products' === $page ) {
				return ( 'edit' === $action && ( '' === $subpage || 'products' === $subpage ) ) ? 'product' : 'products';
			}
			if ( 'wp-easycart-users' === $page ) {
				return 'users';
			}
			if ( 'wp-easycart-rates' === $page ) {
				return 'marketing';
			}
			return 'other';
		}

		/**
		 * The record or page on screen, for the window's Recent list ( kept in the browser ): an order, a product, a customer, a
		 * subscription or a settings page. Null elsewhere.
		 *
		 * @return array|null { label, sub, url }
		 */
		public static function here() {
			global $wpdb;
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
			$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : '';
			$action  = isset( $_GET['ec_admin_form_action'] ) ? sanitize_key( wp_unslash( $_GET['ec_admin_form_action'] ) ) : '';
			$order   = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
			$product = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
			$user    = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
			$sub     = isset( $_GET['subscription_id'] ) ? (int) $_GET['subscription_id'] : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( 'edit' === $action && 'wp-easycart-orders' === $page && 'subscriptions' !== $subpage && $order > 0 && self::can( 'wpec_orders' ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, billing_first_name, billing_last_name, user_email FROM ec_order WHERE order_id = %d', $order ) );
				if ( $row ) {
					$name = trim( wp_unslash( $row->billing_first_name . ' ' . $row->billing_last_name ) );
					return array(
						/* translators: %d: order number. */
						'label' => sprintf( __( 'Order #%d', 'wp-easycart' ), $order ) . ( '' !== $name ? ' · ' . $name : '' ),
						'sub'   => __( 'Order', 'wp-easycart' ),
						'url'   => self::order_url( $order ),
					);
				}
			}
			if ( 'edit' === $action && 'wp-easycart-products' === $page && $product > 0 && self::can( 'wpec_products' ) ) {
				$title = $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ec_product WHERE product_id = %d', $product ) );
				if ( null !== $title ) {
					return array(
						'label' => wp_strip_all_tags( wp_unslash( $title ) ),
						'sub'   => __( 'Product', 'wp-easycart' ),
						'url'   => self::product_url( $product ),
					);
				}
			}
			if ( 'edit' === $action && 'wp-easycart-users' === $page && $user > 0 && self::can( 'wpec_users' ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT first_name, last_name, email FROM ec_user WHERE user_id = %d', $user ) );
				if ( $row ) {
					$name = trim( wp_unslash( $row->first_name . ' ' . $row->last_name ) );
					return array(
						'label' => '' !== $name ? $name : (string) $row->email,
						'sub'   => __( 'Customer', 'wp-easycart' ),
						'url'   => self::customer_url( $user ),
					);
				}
			}
			if ( 'edit' === $action && 'subscriptions' === $subpage && $sub > 0 && self::can( 'wpec_orders' ) ) {
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT title, email FROM ec_subscription WHERE subscription_id = %d', $sub ) );
				if ( $row ) {
					return array(
						'label' => wp_strip_all_tags( wp_unslash( $row->title ) ) . ' · ' . $row->email,
						'sub'   => __( 'Subscription', 'wp-easycart' ),
						'url'   => self::subscription_url( $sub ),
					);
				}
			}
			if ( 'wp-easycart-settings' === $page && '' !== $subpage && 'home' !== $subpage && class_exists( 'wp_easycart_admin_settings_home' ) ) {
				foreach ( wp_easycart_admin_settings_home::navigation() as $group ) {
					foreach ( (array) $group['pages'] as $slug => $entry ) {
						if ( $slug === $subpage || in_array( $subpage, isset( $entry['aliases'] ) ? (array) $entry['aliases'] : array(), true ) ) {
							return array(
								'label' => (string) $entry['title'],
								'sub'   => __( 'Settings', 'wp-easycart' ),
								'url'   => isset( $entry['url'] ) ? (string) $entry['url'] : admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . $slug ),
							);
						}
					}
				}
			}
			return null;
		}

		/* ------------------------------------------------------------------ Records */

		/**
		 * What the query looks like.
		 *
		 * @param string $q Query.
		 * @return array { q: trimmed, kind: number | email | code | text, number: int for #123 / 123, order: typed with # ( an order
		 *               number only ) }
		 */
		public static function parse( $q ) {
			$q    = trim( (string) $q );
			$out  = array(
				'q'      => $q,
				'kind'   => 'text',
				'number' => 0,
				'order'  => ( 0 === strpos( $q, '#' ) ),
			);
			$bare = ltrim( $q, '#' );
			if ( '' !== $bare && ctype_digit( $bare ) && strlen( $bare ) <= 12 ) {
				$out['kind']   = 'number';
				$out['number'] = (int) $bare;
			} elseif ( false !== strpos( $q, '@' ) && false === strpos( $q, ' ' ) ) {
				$out['kind'] = 'email';
			} elseif ( false === strpos( $q, ' ' ) && strlen( $q ) >= 3 && strlen( $q ) <= 64 && preg_match( '/[0-9]/', $q ) && preg_match( '/^[A-Za-z0-9._\-\/]+$/', $q ) ) {
				$out['kind'] = 'code';
			}
			return $out;
		}

		/**
		 * Record types: key => { label, cap, callback( $parse, $limit ) → rows, more( $q ) → address, order }.
		 *
		 * @return array
		 */
		public static function providers() {
			$providers = array(
				'orders'        => array(
					'label'    => __( 'Orders', 'wp-easycart' ),
					'cap'      => 'wpec_orders',
					'callback' => array( __CLASS__, 'find_orders' ),
					'more'     => array( __CLASS__, 'more_orders' ),
					'order'    => 10,
				),
				'customers'     => array(
					'label'    => __( 'Customers', 'wp-easycart' ),
					'cap'      => 'wpec_users',
					'callback' => array( __CLASS__, 'find_customers' ),
					'more'     => array( __CLASS__, 'more_customers' ),
					'order'    => 20,
				),
				'products'      => array(
					'label'    => __( 'Products', 'wp-easycart' ),
					'cap'      => 'wpec_products',
					'callback' => array( __CLASS__, 'find_products' ),
					'more'     => array( __CLASS__, 'more_products' ),
					'order'    => 30,
				),
				'subscriptions' => array(
					'label'    => __( 'Subscriptions', 'wp-easycart' ),
					'cap'      => 'wpec_orders',
					'callback' => array( __CLASS__, 'find_subscriptions' ),
					'more'     => array( __CLASS__, 'more_subscriptions' ),
					'order'    => 40,
				),
				'catalog'       => array(
					'label'    => __( 'Categories, brands and option sets', 'wp-easycart' ),
					'cap'      => 'wpec_products',
					'callback' => array( __CLASS__, 'find_catalog' ),
					'order'    => 60,
				),
			);
			/**
			 * Record types in the admin search. Each: label, cap ( a capability, or a callable answering whether this person
			 * may see them ), callback( $parse, $limit ) returning rows { id, title, sub, url, badge: { text, color }, thumb,
			 * hit ( an exact match: shown first with its actions ), actions: [ { label, url } ] }, more( $q ) the list
			 * filtered by the query ( optional ), order.
			 *
			 * @since 6.0.5
			 * @param array $providers key => provider.
			 */
			$providers = (array) apply_filters( 'wp_easycart_admin_search_providers', $providers );
			uasort(
				$providers,
				function ( $a, $b ) {
					return ( isset( $a['order'] ) ? (int) $a['order'] : 100 ) - ( isset( $b['order'] ) ? (int) $b['order'] : 100 );
				}
			);
			return $providers;
		}

		/**
		 * The record groups for a query.
		 *
		 * @param string $q     Query.
		 * @param string $scope A provider key, or '' for all.
		 * @return array { groups: [ { key, label, items, more } ], partial: bool }
		 */
		public static function records( $q, $scope = '' ) {
			$parse = self::parse( $q );
			$out   = array(
				'groups'  => array(),
				'partial' => false,
				'kind'    => $parse['kind'],
			);
			if ( '' === $parse['q'] || strlen( $parse['q'] ) > 100 || ( 'text' === $parse['kind'] && strlen( $parse['q'] ) < 2 ) ) {
				return $out;
			}
			$start = microtime( true );
			foreach ( self::providers() as $key => $provider ) {
				if ( '' !== $scope && $scope !== $key ) {
					continue;
				}
				if ( empty( $provider['callback'] ) || ! is_callable( $provider['callback'] ) ) {
					continue;
				}
				$cap = isset( $provider['cap'] ) ? $provider['cap'] : 'manage_options';
				if ( is_callable( $cap ) ? ! call_user_func( $cap ) : ! self::can( (string) $cap ) ) {
					continue;
				}
				if ( '' === $scope && microtime( true ) - $start > self::BUDGET ) {
					$out['partial'] = true;
					break;
				}
				$rows = (array) call_user_func( $provider['callback'], $parse, '' !== $scope ? 20 : self::LIMIT + 1 );
				if ( empty( $rows ) ) {
					continue;
				}
				$limit           = '' !== $scope ? 20 : self::LIMIT;
				$out['groups'][] = array(
					'key'   => (string) $key,
					'label' => (string) $provider['label'],
					'items' => array_values( array_slice( $rows, 0, $limit ) ),
					'more'  => ( count( $rows ) > $limit || '' !== $scope ) && ! empty( $provider['more'] ) && is_callable( $provider['more'] ) ? (string) call_user_func( $provider['more'], $parse['q'] ) : '',
				);
			}
			$out['ms'] = (int) round( ( microtime( true ) - $start ) * 1000 ); /* the server's own time, for the probe */
			return $out;
		}

		/**
		 * A LIKE pattern for words anywhere.
		 *
		 * @param string $q Text.
		 * @return string
		 */
		private static function like( $q ) {
			global $wpdb;
			return '%' . $wpdb->esc_like( $q ) . '%';
		}

		/**
		 * A LIKE pattern for a start ( indexed columns ).
		 *
		 * @param string $q Text.
		 * @return string
		 */
		private static function starts( $q ) {
			global $wpdb;
			return $wpdb->esc_like( $q ) . '%';
		}

		/**
		 * Every typed word ( four at most ) somewhere in these columns: "sam rivera" finds Sam in the first name and Rivera in the
		 * last, "mug blue" a product called "Blue Stoneware Mug".
		 *
		 * @param array  $columns Columns ( fixed names ).
		 * @param string $q       Query.
		 * @param array  $values  Values for prepare(), added to.
		 * @return string SQL with %s placeholders.
		 */
		private static function words_sql( $columns, $q, &$values ) {
			global $wpdb;
			$parts = array();
			foreach ( array_slice( array_values( array_filter( preg_split( '/\s+/', trim( (string) $q ) ), 'strlen' ) ), 0, 4 ) as $word ) {
				$any = array();
				foreach ( $columns as $column ) {
					$any[]    = $column . ' LIKE %s';
					$values[] = '%' . $wpdb->esc_like( $word ) . '%';
				}
				$parts[] = '( ' . implode( ' OR ', $any ) . ' )';
			}
			return $parts ? '( ' . implode( ' AND ', $parts ) . ' )' : '1 = 0';
		}

		/**
		 * The order screen's address.
		 *
		 * @param int $id Order.
		 * @return string
		 */
		public static function order_url( $id ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&order_id=' . (int) $id . '&ec_admin_form_action=edit' );
		}

		/**
		 * The product editor's address.
		 *
		 * @param int $id Product.
		 * @return string
		 */
		public static function product_url( $id ) {
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $id );
		}

		/**
		 * The customer screen's address.
		 *
		 * @param int $id ec_user id.
		 * @return string
		 */
		public static function customer_url( $id ) {
			return admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&user_id=' . (int) $id . '&ec_admin_form_action=edit&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-edit' ) );
		}

		/**
		 * The subscription screen's address.
		 *
		 * @param int $id Subscription.
		 * @return string
		 */
		public static function subscription_url( $id ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=subscriptions&subscription_id=' . (int) $id . '&ec_admin_form_action=edit' );
		}

		/**
		 * Orders: the number ( exact first ), an email ( starts with ), a tracking number or payment reference, else names,
		 * emails and companies. Newest first.
		 *
		 * @param array $parse parse().
		 * @param int   $limit Rows.
		 * @return array
		 */
		public static function find_orders( $parse, $limit ) {
			global $wpdb;
			$select = 'SELECT ec_order.order_id, ec_order.order_date, ec_order.grand_total, ec_order.user_id, ec_order.user_email, ec_order.billing_first_name, ec_order.billing_last_name, ec_order.shipping_first_name, ec_order.shipping_last_name, ec_order.billing_company_name, ec_order.tracking_number, ec_orderstatus.order_status, ec_orderstatus.color_code, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id )';
			$q      = $parse['q'];
			$limit  = max( 1, (int) $limit );
			$rows   = array();
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is a fixed string; values are bound by prepare().
			switch ( $parse['kind'] ) {
				case 'number':
					$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ec_order.order_id = %d OR CAST( ec_order.order_id AS CHAR ) LIKE %s ORDER BY ( ec_order.order_id = %d ) DESC, ec_order.order_id DESC LIMIT %d", $parse['number'], self::starts( (string) $parse['number'] ), $parse['number'], $limit ) );
					break;
				case 'email':
					$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ec_order.user_email LIKE %s ORDER BY ec_order.order_id DESC LIMIT %d", self::starts( $q ), $limit ) );
					break;
				case 'code':
					$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ec_order.tracking_number = %s OR ec_order.gateway_transaction_id LIKE %s ORDER BY ec_order.order_id DESC LIMIT %d", $q, self::starts( $q ), $limit ) );
					if ( count( $rows ) < $limit ) {
						$rows = array_merge( $rows, (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ec_order.user_email LIKE %s OR ec_order.billing_last_name LIKE %s ORDER BY ec_order.order_id DESC LIMIT %d", self::like( $q ), self::like( $q ), $limit ) ) );
					}
					break;
				default:
					$values   = array();
					$where    = self::words_sql( array( 'ec_order.billing_first_name', 'ec_order.billing_last_name', 'ec_order.shipping_first_name', 'ec_order.shipping_last_name', 'ec_order.user_email', 'ec_order.billing_company_name' ), $q, $values );
					$values[] = $limit;
					$rows     = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE {$where} ORDER BY ec_order.order_id DESC LIMIT %d", $values ) );
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out  = array();
			$seen = array();
			foreach ( $rows as $row ) {
				$id = (int) $row->order_id;
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$name        = trim( wp_unslash( $row->billing_first_name . ' ' . $row->billing_last_name ) );
				$name        = '' !== $name ? $name : trim( wp_unslash( $row->shipping_first_name . ' ' . $row->shipping_last_name ) );
				$name        = '' !== $name ? $name : trim( wp_unslash( (string) $row->billing_company_name ) );
				$when        = strtotime( (string) $row->order_date );
				$hit         = ( 'number' === $parse['kind'] && $id === $parse['number'] ) || ( 'code' === $parse['kind'] && '' !== (string) $row->tracking_number && strtolower( $row->tracking_number ) === strtolower( $q ) );
				$item        = array(
					'id'    => $id,
					/* translators: %d: order number. */
					'title' => sprintf( __( '#%d', 'wp-easycart' ), $id ) . ( '' !== $name ? ' · ' . wp_strip_all_tags( $name ) : '' ),
					'sub'   => trim( self::money( $row->grand_total ) . ( $when ? ' · ' . date_i18n( get_option( 'date_format', 'M j, Y' ), $when ) : '' ) . ( $hit ? ' · ' . $row->user_email : '' ) ),
					'url'   => self::order_url( $id ),
					'badge' => array(
						'text'  => wp_strip_all_tags( wp_unslash( (string) $row->order_status ) ),
						'color' => sanitize_hex_color( (string) $row->color_code ) ? (string) $row->color_code : '',
					),
					'hit'   => $hit,
				);
				if ( $hit ) {
					/* translators: %d: order number. */
					$item['title']   = sprintf( __( 'Order #%d', 'wp-easycart' ), $id ) . ( '' !== $name ? ' · ' . wp_strip_all_tags( $name ) : '' );
					$item['actions'] = array(
						array(
							'label' => __( 'Open order', 'wp-easycart' ),
							'url'   => self::order_url( $id ),
						),
					);
					/* 6.0.5: Ship only while the order has items to send, Track once it has shipped ( Matt ). */
					$next = self::order_next_action( $id );
					if ( $next ) {
						$item['actions'][] = $next;
					}
					if ( class_exists( 'wp_easycart_admin_order_screen' ) && method_exists( 'wp_easycart_admin_order_screen', 'packing_slip_url' ) ) {
						$item['actions'][] = array(
							'label' => __( 'Print packing slip', 'wp-easycart' ),
							'url'   => wp_easycart_admin_order_screen::packing_slip_url( $id ),
							'new'   => 1,
						);
					}
					if ( (int) $row->user_id > 0 && self::can( 'wpec_users' ) ) {
						$item['actions'][] = array(
							'label' => __( 'Customer', 'wp-easycart' ),
							'url'   => self::customer_url( (int) $row->user_id ),
						);
					}
				}
				$out[] = $item;
			}
			usort(
				$out,
				function ( $a, $b ) {
					return (int) $b['hit'] - (int) $a['hit'];
				}
			);
			return $out;
		}

		/**
		 * The orders list searched for the query.
		 *
		 * @param string $q Query.
		 * @return string
		 */
		public static function more_orders( $q ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&s=' . rawurlencode( ltrim( $q, '#' ) ) );
		}

		/**
		 * The order's next step on an exact match, as the order screen reads it ( wp_easycart_admin_order_screen::fulfillment() ):
		 * Ship while the store still has items to send ( unfulfilled or partly shipped, not one the customer collects ), else Track
		 * once it has a tracking link; nothing for an order not paid, refunded, cancelled, digital or waiting to be picked up.
		 *
		 * @since 6.0.5 ( Matt: a shipped order offered Ship )
		 * @param int $id Order ID.
		 * @return array|null { label, url, new }
		 */
		public static function order_next_action( $id ) {
			global $wpdb;
			if ( ! class_exists( 'wp_easycart_admin_order_screen' ) || ! method_exists( 'wp_easycart_admin_order_screen', 'fulfillment' ) ) {
				return null;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ( ec_orderstatus.status_id = ec_order.orderstatus_id ) WHERE ec_order.order_id = %d', (int) $id ) );
			if ( ! $order ) {
				return null;
			}
			$state = wp_easycart_admin_order_screen::fulfillment( $order );
			if ( in_array( $state['state'], array( 'unfulfilled', 'partial' ), true ) && empty( $state['local_pickup'] ) && ! empty( $state['store_open'] ) ) {
				return array(
					'label' => __( 'Ship', 'wp-easycart' ),
					'url'   => self::order_url( (int) $id ) . '&ecodv2_open=tracking',
				);
			}
			if ( in_array( $state['state'], array( 'fulfilled', 'partial' ), true ) && method_exists( 'wp_easycart_admin_order_screen', 'tracking_list' ) ) {
				foreach ( (array) wp_easycart_admin_order_screen::tracking_list( $order ) as $tracking ) {
					$url = isset( $tracking['url'] ) ? esc_url_raw( (string) $tracking['url'] ) : '';
					if ( '' !== $url ) {
						return array(
							'label' => __( 'Track', 'wp-easycart' ),
							'url'   => $url,
							'new'   => 1,
						);
					}
				}
			}
			return null;
		}

		/**
		 * Customers: an email ( starts with, the unique index ), the account number, else names and emails.
		 *
		 * @param array $parse parse().
		 * @param int   $limit Rows.
		 * @return array
		 */
		public static function find_customers( $parse, $limit ) {
			global $wpdb;
			$q      = $parse['q'];
			$limit  = max( 1, (int) $limit );
			$select = 'SELECT user_id, first_name, last_name, email, completed_order_count FROM ec_user';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is a fixed string; values are bound by prepare().
			if ( 'email' === $parse['kind'] ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE email LIKE %s ORDER BY ( email = %s ) DESC, user_id DESC LIMIT %d", self::starts( $q ), $q, $limit ) );
			} elseif ( 'number' === $parse['kind'] ) {
				if ( ! empty( $parse['order'] ) ) {
					return array(); /* #123 is an order number */
				}
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE user_id = %d LIMIT 1", $parse['number'] ) );
			} else {
				$values   = array();
				$where    = self::words_sql( array( 'first_name', 'last_name', 'email' ), $q, $values );
				$values[] = $limit;
				$rows     = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE {$where} ORDER BY user_id DESC LIMIT %d", $values ) );
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out = array();
			foreach ( $rows as $row ) {
				$name  = trim( wp_unslash( $row->first_name . ' ' . $row->last_name ) );
				$count = (int) $row->completed_order_count;
				$hit   = 'email' === $parse['kind'] && strtolower( $row->email ) === strtolower( $q );
				$item  = array(
					'id'    => (int) $row->user_id,
					'title' => '' !== $name ? wp_strip_all_tags( $name ) : (string) $row->email,
					/* translators: %d: number of orders. */
					'sub'   => (string) $row->email . ' · ' . sprintf( _n( '%d order', '%d orders', $count, 'wp-easycart' ), $count ),
					'url'   => self::customer_url( (int) $row->user_id ),
					'hit'   => $hit,
				);
				if ( $hit ) {
					$item['actions'] = array(
						array(
							'label' => __( 'Open customer', 'wp-easycart' ),
							'url'   => self::customer_url( (int) $row->user_id ),
						),
					);
					if ( self::can( 'wpec_orders' ) ) {
						$item['actions'][] = array(
							'label' => __( 'Their orders', 'wp-easycart' ),
							'url'   => self::more_orders( (string) $row->email ),
						);
					}
				}
				$out[] = $item;
			}
			return $out;
		}

		/**
		 * The customers list searched for the query.
		 *
		 * @param string $q Query.
		 * @return string
		 */
		public static function more_customers( $q ) {
			return admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&s=' . rawurlencode( $q ) );
		}

		/**
		 * Products: a SKU or code ( the product's, then a variant's ), the product ID, else titles. Drafts are left out.
		 *
		 * @param array $parse parse().
		 * @param int   $limit Rows.
		 * @return array
		 */
		public static function find_products( $parse, $limit ) {
			global $wpdb;
			$q     = $parse['q'];
			$limit = max( 1, (int) $limit );
			if ( ! function_exists( 'wp_easycart_admin_catalog_v2_thumb_select' ) && is_readable( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php' ) ) {
				include_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_catalog_v2.php'; /* the product list's thumbnail rule */
			}
			$drafts = function_exists( 'wp_easycart_product_draft_sql' ) ? wp_easycart_product_draft_sql( 'p' ) : ''; /* its % comes doubled, for prepare() */
			$cols   = 'p.product_id, p.post_id, p.title, p.model_number, p.price, p.activate_in_store, p.stock_quantity, p.show_stock_quantity, p.use_optionitem_quantity_tracking, ' . ( function_exists( 'wp_easycart_admin_catalog_v2_thumb_select' ) ? wp_easycart_admin_catalog_v2_thumb_select( 'p' ) : 'p.product_images, p.image1, p.image2, p.image3, p.image4, p.image5, p.use_optionitem_images' );
			$select = 'SELECT ' . $cols . ' FROM ec_product p';
			$rows   = array();
			if ( 'number' === $parse['kind'] && ! empty( $parse['order'] ) ) {
				return array(); /* #123 is an order number */
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $select and $drafts are fixed strings; values are bound by prepare().
			if ( 'number' === $parse['kind'] ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE ( p.product_id = %d OR p.model_number LIKE %s ){$drafts} ORDER BY ( p.product_id = %d ) DESC, p.product_id DESC LIMIT %d", $parse['number'], self::starts( (string) $parse['number'] ), $parse['number'], $limit ) );
			} elseif ( 'code' === $parse['kind'] || 'email' === $parse['kind'] || ( false === strpos( $q, ' ' ) && strlen( $q ) >= 3 ) ) {
				/* A SKU ( FX-MUG-RED needs no digit ): the product's, then a variant's, both by their start. */
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE p.model_number LIKE %s{$drafts} ORDER BY ( p.model_number = %s ) DESC, p.product_id DESC LIMIT %d", self::starts( $q ), $q, $limit ) );
				if ( count( $rows ) < $limit ) {
					$variants = (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$cols}, v.sku AS variant_sku FROM ec_optionitemquantity v INNER JOIN ec_product p ON p.product_id = v.product_id WHERE v.sku LIKE %s{$drafts} ORDER BY ( v.sku = %s ) DESC LIMIT %d", self::starts( $q ), $q, $limit ) );
					$rows     = array_merge( $rows, $variants );
				}
			}
			if ( 'number' !== $parse['kind'] && count( $rows ) < $limit ) {
				$values   = array();
				$where    = self::words_sql( array( 'p.title', 'p.model_number' ), $q, $values );
				$values[] = self::starts( $q );
				$values[] = $limit;
				$rows     = array_merge( $rows, (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE {$where}{$drafts} ORDER BY ( p.title LIKE %s ) DESC, p.product_id DESC LIMIT %d", $values ) ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $values carries the placeholders words_sql() adds too.
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$out  = array();
			$seen = array();
			foreach ( $rows as $row ) {
				$id = (int) $row->product_id;
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$sku         = ! empty( $row->variant_sku ) ? (string) $row->variant_sku : (string) $row->model_number;
				$hit         = ( 'number' === $parse['kind'] && $id === $parse['number'] ) || ( 'number' !== $parse['kind'] && '' !== $sku && strtolower( $sku ) === strtolower( $q ) );
				$stock       = '';
				if ( ! empty( $row->show_stock_quantity ) || ! empty( $row->use_optionitem_quantity_tracking ) ) {
					/* translators: %d: units in stock. */
					$stock = sprintf( __( '%d in stock', 'wp-easycart' ), (int) $row->stock_quantity );
				}
				$thumb = function_exists( 'wp_easycart_admin_catalog_v2_product_thumb' ) ? (string) wp_easycart_admin_catalog_v2_product_thumb( $row ) : '';
				$item  = array(
					'id'    => $id,
					'title' => wp_strip_all_tags( wp_unslash( (string) $row->title ) ),
					/* translators: %s: the SKU. */
					'sub'   => trim( sprintf( __( 'SKU %s', 'wp-easycart' ), $sku ) . ' · ' . self::money( $row->price ) . ( '' !== $stock ? ' · ' . $stock : '' ) ),
					'url'   => self::product_url( $id ),
					'thumb' => 0 === strpos( $thumb, 'http' ) || 0 === strpos( $thumb, '//' ) ? $thumb : '',
					'badge' => empty( $row->activate_in_store ) ? array(
						'text'  => __( 'Draft', 'wp-easycart' ),
						'color' => '',
					) : null,
					'hit'   => $hit,
				);
				if ( $hit ) {
					$item['actions'] = array(
						array(
							'label' => __( 'Edit product', 'wp-easycart' ),
							'url'   => self::product_url( $id ),
						),
					);
					$link            = ( ! empty( $row->activate_in_store ) && (int) $row->post_id > 0 && function_exists( 'wp_easycart_store_post_link' ) ) ? wp_easycart_store_post_link( (int) $row->post_id ) : '';
					if ( $link ) {
						$item['actions'][] = array(
							'label' => __( 'View in store', 'wp-easycart' ),
							'url'   => $link,
							'new'   => 1,
						);
					}
				}
				$out[] = $item;
			}
			usort(
				$out,
				function ( $a, $b ) {
					return (int) $b['hit'] - (int) $a['hit'];
				}
			);
			return $out;
		}

		/**
		 * The products list searched for the query.
		 *
		 * @param string $q Query.
		 * @return string
		 */
		public static function more_products( $q ) {
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=products&s=' . rawurlencode( $q ) );
		}

		/**
		 * Subscriptions: the number, a Stripe subscription ID, else the customer's name, email or the plan.
		 *
		 * @param array $parse parse().
		 * @param int   $limit Rows.
		 * @return array
		 */
		public static function find_subscriptions( $parse, $limit ) {
			global $wpdb;
			$q      = $parse['q'];
			$limit  = max( 1, (int) $limit );
			$select = 'SELECT subscription_id, subscription_status, title, email, first_name, last_name, price, stripe_subscription_id FROM ec_subscription';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $select is a fixed string; values are bound by prepare().
			if ( 'number' === $parse['kind'] ) {
				if ( ! empty( $parse['order'] ) ) {
					return array(); /* #123 is an order number */
				}
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE subscription_id = %d LIMIT 1", $parse['number'] ) );
			} elseif ( 'code' === $parse['kind'] ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE stripe_subscription_id LIKE %s ORDER BY subscription_id DESC LIMIT %d", self::starts( $q ), $limit ) );
			} elseif ( 'email' === $parse['kind'] ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE email LIKE %s ORDER BY subscription_id DESC LIMIT %d", self::starts( $q ), $limit ) );
			} else {
				$rows = array();
			}
			if ( empty( $rows ) && 'number' !== $parse['kind'] ) {
				$values   = array();
				$where    = self::words_sql( array( 'first_name', 'last_name', 'email', 'title' ), $q, $values );
				$values[] = $limit;
				$rows     = (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE {$where} ORDER BY subscription_id DESC LIMIT %d", $values ) );
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out = array();
			foreach ( $rows as $row ) {
				$name  = trim( wp_unslash( $row->first_name . ' ' . $row->last_name ) );
				$out[] = array(
					'id'    => (int) $row->subscription_id,
					'title' => wp_strip_all_tags( wp_unslash( (string) $row->title ) ) . ' · ' . ( '' !== $name ? wp_strip_all_tags( $name ) : (string) $row->email ),
					'sub'   => (string) $row->email . ' · ' . self::money( $row->price ),
					'url'   => self::subscription_url( (int) $row->subscription_id ),
					'badge' => array(
						'text'  => wp_strip_all_tags( (string) $row->subscription_status ),
						'color' => '',
					),
					'hit'   => ( 'number' === $parse['kind'] ) || ( 'code' === $parse['kind'] && strtolower( (string) $row->stripe_subscription_id ) === strtolower( $q ) ),
				);
			}
			return $out;
		}

		/**
		 * The subscriptions list searched for the query.
		 *
		 * @param string $q Query.
		 * @return string
		 */
		public static function more_subscriptions( $q ) {
			return admin_url( 'admin.php?page=wp-easycart-orders&subpage=subscriptions&s=' . rawurlencode( $q ) );
		}

		/**
		 * Categories, manufacturers and the shared option sets, by name.
		 *
		 * @param array $parse parse().
		 * @param int   $limit Rows.
		 * @return array
		 */
		public static function find_catalog( $parse, $limit ) {
			global $wpdb;
			if ( 'text' !== $parse['kind'] && 'code' !== $parse['kind'] ) {
				return array();
			}
			$q     = $parse['q'];
			$limit = max( 1, (int) $limit );
			$out   = array();
			$ask   = function ( $select, $column, $extra ) use ( $q, $limit ) {
				global $wpdb;
				$values   = array();
				$where    = self::words_sql( array( $column ), $q, $values );
				$values[] = $limit;
				return (array) $wpdb->get_results( $wpdb->prepare( "{$select} WHERE {$where}{$extra} ORDER BY {$column} LIMIT %d", $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed table and column names; values are bound by prepare().
			};
			foreach ( $ask( 'SELECT category_id, category_name, is_active FROM ec_category', 'category_name', '' ) as $row ) {
				$out[] = array(
					'id'    => 'c' . (int) $row->category_id,
					'title' => wp_strip_all_tags( wp_unslash( (string) $row->category_name ) ),
					'sub'   => __( 'Category', 'wp-easycart' ),
					'url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=category&ec_admin_form_action=edit&category_id=' . (int) $row->category_id ),
					'badge' => empty( $row->is_active ) ? array(
						'text'  => __( 'Off', 'wp-easycart' ),
						'color' => '',
					) : null,
				);
			}
			foreach ( $ask( 'SELECT manufacturer_id, name FROM ec_manufacturer', 'name', '' ) as $row ) {
				$out[] = array(
					'id'    => 'm' . (int) $row->manufacturer_id,
					'title' => wp_strip_all_tags( wp_unslash( (string) $row->name ) ),
					'sub'   => __( 'Manufacturer', 'wp-easycart' ),
					'url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers&ec_admin_form_action=edit&manufacturer_id=' . (int) $row->manufacturer_id ),
				);
			}
			foreach ( $ask( 'SELECT option_id, option_name FROM ec_option', 'option_name', ' AND ( owner_product_id IS NULL OR owner_product_id = 0 )' ) as $row ) {
				$out[] = array(
					'id'    => 'o' . (int) $row->option_id,
					'title' => wp_strip_all_tags( wp_unslash( (string) $row->option_name ) ),
					'sub'   => __( 'Option set', 'wp-easycart' ),
					'url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=option&ec_admin_form_action=edit&option_id=' . (int) $row->option_id ),
				);
			}
			return array_slice( $out, 0, $limit );
		}

		/**
		 * An amount in the store's currency.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		private static function money( $amount ) {
			if ( class_exists( 'wp_easycart_admin_order_screen' ) && method_exists( 'wp_easycart_admin_order_screen', 'money' ) ) {
				return wp_strip_all_tags( wp_easycart_admin_order_screen::money( (float) $amount ) );
			}
			return number_format_i18n( (float) $amount, 2 );
		}

		/* ------------------------------------------------------------------ AJAX */

		/** GET → { version, items }: the index for this person. */
		public static function ajax_index() {
			ecv2_search_guard();
			wp_send_json_success(
				array(
					'version' => self::version(),
					'items'   => self::index(),
				)
			);
		}

		/** POST q, scope → the record groups. */
		public static function ajax_records() {
			ecv2_search_guard();
			$q     = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';
			$scope = isset( $_REQUEST['scope'] ) ? sanitize_key( wp_unslash( $_REQUEST['scope'] ) ) : '';
			wp_send_json_success( self::records( $q, $scope ) );
		}
	}

	wp_easycart_admin_search::init();

endif;
