<?php
/**
 * WP-CLI: the wp easycart namespace ( its help text ).
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cli_namespace' ) && class_exists( 'WP_CLI\Dispatcher\CommandNamespace' ) ) :

	/**
	 * Manage WP EasyCart from the command line.
	 *
	 * ## EXAMPLES
	 *
	 *     # What is installed, and whether the database is up to date.
	 *     $ wp easycart info
	 *
	 *     # Finish the database update after updating from the command line.
	 *     $ wp plugin update wp-easycart && wp easycart db upgrade
	 */
	class wp_easycart_cli_namespace extends \WP_CLI\Dispatcher\CommandNamespace {
	}

endif;
