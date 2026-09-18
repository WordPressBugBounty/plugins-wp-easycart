<?php
/**
 * WP EasyCart — Smart categories: free-side support.
 *
 * The engine ( rules → membership, sync, cron ) is a PRO feature and lives in the PRO plugin as
 * ec_smart_categories. This class is what the free plugin needs so the admin keeps working without it:
 * schema detection, reading stored rules, recording an exclusion when a rule-driven member is removed,
 * and the PRO gate the category editor shows.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_smart_categories_support' ) ) :

	final class ec_smart_categories_support {

		const MIN_PRO_VERSION = '6.0.0';

		public static function engine_loaded() { return class_exists( 'ec_smart_categories' ); }

		public static function columns_exist( $recheck = false ) {
			static $ok = null;
			if ( null === $ok || $recheck ) {
				global $wpdb;
				$ok = (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_category LIKE 'smart_mode'" ) && (bool) $wpdb->get_var( "SHOW COLUMNS FROM ec_categoryitem LIKE 'is_smart'" );
			}
			return $ok;
		}

		/** Stored rules JSON → array with defaults. */
		public static function decode_rules( $json ) {
			$d = array( 'mode' => 'all', 'rules' => array(), 'exclude' => array() );
			if ( '' === (string) $json ) { return $d; }
			$r = json_decode( (string) $json, true );
			if ( ! is_array( $r ) ) { return $d; }
			return array(
				'mode'    => isset( $r['mode'] ) && 'any' === $r['mode'] ? 'any' : 'all',
				'rules'   => isset( $r['rules'] ) && is_array( $r['rules'] ) ? array_values( $r['rules'] ) : array(),
				'exclude' => isset( $r['exclude'] ) && is_array( $r['exclude'] ) ? array_values( array_unique( array_map( 'intval', $r['exclude'] ) ) ) : array(),
			);
		}

		/** Is this product's membership in this category rule-driven? */
		public static function is_smart_member( $category_id, $product_id ) {
			global $wpdb;
			if ( ! self::columns_exist() ) { return false; }
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT is_smart FROM ec_categoryitem WHERE category_id = %d AND product_id = %d LIMIT 1', (int) $category_id, (int) $product_id ) );
		}

		/**
		 * Removing a rule-driven member must record an exclusion, otherwise the next sync re-adds it.
		 * Returns true when an exclusion was recorded ( caller still deletes the membership row ).
		 */
		public static function exclude_product( $category_id, $product_id ) {
			global $wpdb;
			if ( ! self::is_smart_member( $category_id, $product_id ) ) { return false; }
			$rules = self::decode_rules( $wpdb->get_var( $wpdb->prepare( 'SELECT smart_rules FROM ec_category WHERE category_id = %d', (int) $category_id ) ) );
			if ( ! in_array( (int) $product_id, $rules['exclude'], true ) ) { $rules['exclude'][] = (int) $product_id; }
			$wpdb->update( 'ec_category', array( 'smart_rules' => wp_json_encode( $rules ) ), array( 'category_id' => (int) $category_id ) );
			return true;
		}

		public static function count_smart() {
			global $wpdb;
			return self::columns_exist() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_category WHERE smart_mode > 0' ) : 0;
		}

		/**
		 * The PRO gate for the category editor. Uses wp_easycart_admin_pro_gate when the admin is loaded
		 * ( states: enabled / upsell / inactive / update / license ) and falls back to engine presence.
		 */
		public static function gate() {
			if ( class_exists( 'wp_easycart_admin_pro_gate' ) ) {
				$gate = wp_easycart_admin_pro_gate::evaluate( array( 'enabled_filter' => 'wp_easycart_smart_categories_enabled', 'min_version' => self::MIN_PRO_VERSION, 'upsell_view' => 'smart-categories' ) );
				/* The engine must actually be loaded for "enabled" to mean anything. */
				if ( 'enabled' === $gate['state'] && ! self::engine_loaded() ) { $gate['state'] = 'update'; $gate['desc'] = __( 'Update WP EasyCart PRO to use this', 'wp-easycart' ); $gate['url'] = self_admin_url( 'plugins.php' ); }
				return $gate;
			}
			$on = self::engine_loaded() && ec_smart_categories::is_enabled();
			return array( 'state' => $on ? 'enabled' : 'upsell', 'desc' => $on ? '' : ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::included_text( 'pro' ) : __( 'Included with Pro and Premium licenses.', 'wp-easycart' ) ), 'url' => $on ? '' : apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/' ) );
		}

		public static function enabled() { $g = self::gate(); return 'enabled' === $g['state'] && self::engine_loaded() && ec_smart_categories::is_enabled(); }
	}

endif;
