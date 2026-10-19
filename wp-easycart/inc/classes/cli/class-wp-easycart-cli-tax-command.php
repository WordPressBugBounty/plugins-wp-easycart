<?php
/**
 * WP-CLI: wp easycart tax ( recommended tax settings by store country, 6.0.5 ).
 *
 * @package WP EasyCart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cli_tax_command' ) ) :

	/**
	 * See, apply or undo the tax settings WP EasyCart recommends for a store's country.
	 */
	class wp_easycart_cli_tax_command {

		/**
		 * Show the recommended tax settings for a country and what applying them would change.
		 *
		 * ## OPTIONS
		 *
		 * [--country=<country>]
		 * : Two-letter country code. Default: the store's country.
		 *
		 * [--state=<state>]
		 * : US state or Canadian province code ( TX, ON ).
		 *
		 * [--eu=<mode>]
		 * : EU stores: how other EU consumers pay. home = your country's VAT ( under €10,000 a year ), oss = each country's own.
		 * ---
		 * options:
		 *   - home
		 *   - oss
		 * ---
		 *
		 * [--pst=<where>]
		 * : Canadian stores: PST / QST in your own province only ( home ) or every PST province ( all ).
		 * ---
		 * options:
		 *   - home
		 *   - all
		 * ---
		 *
		 * [--excluded]
		 * : VAT / GST added at checkout instead of included in prices.
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
		 *     $ wp easycart tax recommend --country=DE --eu=oss
		 *     $ wp easycart tax recommend --country=US --state=TX --format=json
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function recommend( $args, $assoc_args ) {
			wp_easycart_cli::used( 'tax' );
			list( $cc, $choices ) = self::read( $assoc_args );
			$rec                  = wp_easycart_tax_setup::recommend( $cc, $choices );
			$format               = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
			if ( empty( $rec['supported'] ) ) {
				WP_CLI::error( sprintf( 'WP EasyCart has no recommended tax settings for %s yet.', $cc ) );
			}
			$changes = wp_easycart_tax_setup::changes( $rec );
			if ( 'table' !== $format ) {
				WP_CLI::print_value(
					array(
						'country'  => $rec['country'],
						'state'    => $rec['state'],
						'system'   => $rec['system'],
						'checked'  => $rec['checked'],
						'summary'  => $rec['summary'],
						'notes'    => $rec['notes'],
						'settings' => $rec['settings'],
						'changes'  => $changes,
					),
					array( 'format' => $format )
				);
				return;
			}
			WP_CLI::log( sprintf( '%s%s ( checked %s ):', $rec['country_name'], '' !== $rec['state_name'] ? ', ' . $rec['state_name'] : '', $rec['checked'] ) );
			foreach ( $rec['summary'] as $line ) {
				WP_CLI::log( '  · ' . $line );
			}
			foreach ( $rec['notes'] as $line ) {
				WP_CLI::log( '  ! ' . $line );
			}
			if ( ! $changes ) {
				WP_CLI::success( 'The store\'s tax settings already match.' );
				return;
			}
			\WP_CLI\Utils\format_items( 'table', $changes, array( 'label', 'from', 'to', 'why' ) );
		}

		/**
		 * Apply the recommended tax settings ( undo puts back what was there ).
		 *
		 * ## OPTIONS
		 *
		 * [--country=<country>]
		 * : Two-letter country code. Default: the store's country.
		 *
		 * [--state=<state>]
		 * : US state or Canadian province code.
		 *
		 * [--eu=<mode>]
		 * : home | oss.
		 *
		 * [--pst=<where>]
		 * : home | all.
		 *
		 * [--excluded]
		 * : VAT / GST added at checkout instead of included in prices.
		 *
		 * [--yes]
		 * : Apply without asking.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp easycart tax apply --country=AU --yes
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function apply( $args, $assoc_args ) {
			wp_easycart_cli::used( 'tax' );
			list( $cc, $choices ) = self::read( $assoc_args );
			$rec                  = wp_easycart_tax_setup::recommend( $cc, $choices );
			if ( empty( $rec['supported'] ) ) {
				WP_CLI::error( sprintf( 'WP EasyCart has no recommended tax settings for %s yet.', $cc ) );
			}
			if ( in_array( $rec['system'], array( 'us', 'canada' ), true ) && '' === $rec['state'] ) {
				WP_CLI::error( 'Give the state or province: --state=TX' );
			}
			$changes = wp_easycart_tax_setup::changes( $rec );
			if ( ! $changes ) {
				WP_CLI::success( 'The store\'s tax settings already match.' );
				return;
			}
			WP_CLI::confirm( sprintf( 'Apply %d change(s) to the tax settings?', count( $changes ) ), $assoc_args );
			$done = wp_easycart_tax_setup::apply( $cc, $choices );
			if ( is_wp_error( $done ) ) {
				WP_CLI::error( $done->get_error_message() );
			}
			WP_CLI::success( sprintf( '%d change(s) applied. "wp easycart tax undo" puts the previous settings back.', (int) $done ) );
		}

		/**
		 * Put back the tax settings the last apply changed.
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function undo( $args, $assoc_args ) {
			wp_easycart_cli::used( 'tax' );
			$done = wp_easycart_tax_setup::undo();
			if ( is_wp_error( $done ) ) {
				WP_CLI::error( $done->get_error_message() );
			}
			WP_CLI::success( 'The previous tax settings are back.' );
		}

		/**
		 * The country and choices from the options.
		 *
		 * @param array $assoc_args Options.
		 * @return array country, choices
		 */
		private static function read( $assoc_args ) {
			$cc    = strtoupper( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'country', wp_easycart_tax_setup::store_country() ) );
			$parts = array();
			foreach ( array( 'state', 'eu', 'pst' ) as $key ) {
				$value = \WP_CLI\Utils\get_flag_value( $assoc_args, $key, null );
				if ( null !== $value ) {
					$parts[] = $key . ':' . $value;
				}
			}
			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'excluded', false ) ) {
				$parts[] = 'included:0';
			}
			return array( $cc, wp_easycart_tax_setup::choices_with( $cc, implode( '|', $parts ) ) );
		}
	}

endif;
