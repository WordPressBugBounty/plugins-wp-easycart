<?php
/**
 * WP-CLI commands: wp easycart.
 *
 * Loaded from inc/ec_config.php only while WP-CLI runs. Each command is a thin layer over the classes wp-admin already uses
 * ( ec_db_manager, the backfills, the PRO gate ). None calls an AJAX handler: outside AJAX, check_ajax_referer() and
 * wp_send_json() end in die() with exit status 0, which a deploy script would read as success.
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cli' ) ) :

	/**
	 * Registers wp easycart and holds what its commands share.
	 */
	class wp_easycart_cli {

		/** Option: when a command last ran and which groups have run. The usage check-in reads it; nothing else does. */
		const USED_OPTION = 'wp_easycart_cli_used';

		/**
		 * Register the namespace and its groups ( called from inc/ec_config.php while plugins load ).
		 */
		public static function init() {
			require_once __DIR__ . '/class-wp-easycart-cli-namespace.php';
			require_once __DIR__ . '/class-wp-easycart-cli-info-command.php';
			require_once __DIR__ . '/class-wp-easycart-cli-db-command.php';
			require_once __DIR__ . '/class-wp-easycart-cli-tax-command.php'; /* 6.0.5 */
			if ( class_exists( 'wp_easycart_cli_namespace' ) ) {
				WP_CLI::add_command( 'easycart', 'wp_easycart_cli_namespace' );
			}
			WP_CLI::add_command( 'easycart info', 'wp_easycart_cli_info_command' );
			WP_CLI::add_command( 'easycart db', 'wp_easycart_cli_db_command' );
			if ( class_exists( 'wp_easycart_tax_setup' ) ) {
				WP_CLI::add_command( 'easycart tax', 'wp_easycart_cli_tax_command' );
			}
		}

		/**
		 * The admin classes the commands read. The admin layer loads only for a store manager inside wp-admin, and these two
		 * files only declare classes, so they are safe to load on their own ( never admin-init.php ).
		 */
		public static function load_admin_helpers() {
			require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_pro_gate.php';
			require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_edition.php';
		}

		/**
		 * Note that a command group ran: the time and the group's name, nothing else. Written at most once an hour per group.
		 *
		 * @param string $group info | db | tax.
		 */
		public static function used( $group ) {
			$group  = sanitize_key( $group );
			$used   = get_option( self::USED_OPTION );
			$used   = is_array( $used ) ? $used : array();
			$groups = ( isset( $used['groups'] ) && is_array( $used['groups'] ) ) ? $used['groups'] : array();
			if ( in_array( $group, $groups, true ) && isset( $used['last'] ) && (int) $used['last'] > time() - HOUR_IN_SECONDS ) {
				return;
			}
			$groups[] = $group;
			update_option(
				self::USED_OPTION,
				array(
					'last'   => time(),
					'groups' => array_values( array_unique( $groups ) ),
				),
				false
			);
		}

		/**
		 * Print field => value pairs as a table ( yes / no for true / false ), JSON or YAML.
		 *
		 * @param array  $fields Field => value.
		 * @param string $format table | json | yaml.
		 */
		public static function print_fields( $fields, $format ) {
			if ( 'table' !== $format ) {
				WP_CLI::print_value( $fields, array( 'format' => $format ) );
				return;
			}
			$rows = array();
			foreach ( $fields as $field => $value ) {
				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				} elseif ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				$rows[] = array(
					'Field' => $field,
					'Value' => ( '' === (string) $value ) ? '-' : (string) $value,
				);
			}
			\WP_CLI\Utils\format_items( 'table', $rows, array( 'Field', 'Value' ) );
		}
	}

endif;
