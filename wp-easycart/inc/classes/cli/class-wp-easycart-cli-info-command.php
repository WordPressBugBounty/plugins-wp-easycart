<?php
/**
 * WP-CLI: wp easycart info.
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cli_info_command' ) ) :

	/**
	 * Show what is installed and whether the database is up to date.
	 */
	class wp_easycart_cli_info_command {

		/**
		 * Show WP EasyCart's version, the database's state, WP EasyCart PRO and the store's plan.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Output format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     # Check a store after a deploy.
		 *     $ wp easycart info
		 *
		 *     # Stop a deploy script while the database update is unfinished.
		 *     $ wp easycart info --format=json | jq -e '.database == "current"'
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function __invoke( $args, $assoc_args ) {
			wp_easycart_cli::load_admin_helpers();
			wp_easycart_cli::used( 'info' );
			$pro = wp_easycart_admin_pro_gate::pro_status();
			$db  = wp_easycart_cli_db_command::state();
			wp_easycart_cli::print_fields(
				array(
					'version'      => str_replace( '_', '.', EC_CURRENT_VERSION ),
					'database'     => $db['database'],
					'next_step'    => $db['next_step'],
					'backfills'    => $db['backfills'],
					'pro'          => self::pro_state( $pro ),
					'pro_version'  => $pro['version'],
					'plan'         => wp_easycart_admin_edition::tier(),
					'plan_lapsed'  => wp_easycart_admin_edition::is_lapsed(),
					'object_cache' => (bool) wp_using_ext_object_cache(),
					'wordpress'    => get_bloginfo( 'version' ),
					'php'          => PHP_VERSION,
				),
				\WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' )
			);
		}

		/**
		 * WP EasyCart PRO in one word.
		 *
		 * @param array $pro wp_easycart_admin_pro_gate::pro_status().
		 * @return string not_installed | inactive | outdated | active.
		 */
		private static function pro_state( $pro ) {
			if ( empty( $pro['installed'] ) ) {
				return 'not_installed';
			}
			if ( empty( $pro['active'] ) ) {
				return 'inactive';
			}
			return empty( $pro['outdated'] ) ? 'active' : 'outdated';
		}
	}

endif;
