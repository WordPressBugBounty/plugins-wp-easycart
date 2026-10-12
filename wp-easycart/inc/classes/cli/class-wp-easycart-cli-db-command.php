<?php
/**
 * WP-CLI: wp easycart db.
 *
 * In wp-admin an update finishes in three parts: install_db() brings the tables up to date ( plugins_loaded ), try_db_update()
 * runs the version steps ( init, 20 seconds a request ) and admin_init runs the background fills. WP-CLI never fires
 * admin_init, so a store updated with wp plugin update stayed part updated until someone opened wp-admin.
 *
 * @package WP EasyCart
 * @since 6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cli_db_command' ) ) :

	/**
	 * Check and finish WP EasyCart's database updates.
	 *
	 * ## EXAMPLES
	 *
	 *     # After updating from the command line.
	 *     $ wp plugin update wp-easycart && wp easycart db upgrade
	 */
	class wp_easycart_cli_db_command {

		/** Seconds upgrade waits while another request holds the update lock ( the lock itself lasts 5 minutes ). */
		const LOCK_WAIT = 300;

		/** Passes upgrade makes before it gives up ( 20 seconds each, so a little over an hour ). */
		const MAX_PASSES = 200;

		/**
		 * Show whether the database is up to date.
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
		 *     $ wp easycart db status
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function status( $args, $assoc_args ) {
			wp_easycart_cli::used( 'db' );
			$state  = self::state();
			$fields = array(
				'database'       => $state['database'],
				'next_step'      => $state['next_step'],
				'tables'         => $state['tables'] . ' / ' . EC_UPGRADE_DB,
				'failing'        => $state['failing'],
				'skipped'        => $state['skipped'],
				'update_running' => $state['running'],
			);
			foreach ( self::fills() as $key => $fill ) {
				$fields[ $key ] = self::fill_done( $fill ) ? 'done' : 'pending';
			}
			wp_easycart_cli::print_fields( $fields, \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ) );
		}

		/**
		 * Finish the database update: the tables, the version steps and the background fills that otherwise wait for wp-admin.
		 *
		 * Safe to run at any time. Every step is idempotent, a finished update does nothing, and stopping part way ( Ctrl+C )
		 * loses nothing: the next run carries on where this one stopped.
		 *
		 * ## OPTIONS
		 *
		 * [--skip-failing]
		 * : Move past steps that keep failing so the rest can finish, as "Continue without this change" does on Store Status.
		 *
		 * [--retry-skipped]
		 * : Try the steps skipped earlier again.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp easycart db upgrade
		 *     Tables: up to date.
		 *     Running wpeasycart_sql_6_0_4_withdrawals...
		 *     Reports history: done.
		 *     Amounts paid: done.
		 *     Gateway log tokens: done.
		 *     Success: The WP EasyCart database is up to date ( 6.0.4 ).
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function upgrade( $args, $assoc_args ) {
			wp_easycart_cli::used( 'db' );
			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'retry-skipped', false ) ) {
				ec_db_manager::clear_skipped_steps();
			}
			$skip    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-failing', false );
			$manager = new ec_db_manager();

			/* 1. The tables ( dbDelta of the schema ), as opening wp-admin does when EC_UPGRADE_DB changed. */
			if ( (string) EC_UPGRADE_DB !== (string) get_option( 'ec_option_db_new_version' ) ) {
				if ( ! $manager->install_db( true ) ) {
					self::fail( __( 'Some WP EasyCart tables could not be created or updated.', 'wp-easycart' ), $manager->get_install_errors() );
				}
				update_option( 'ec_option_is_installed', '1' );
				WP_CLI::log( __( 'Tables: updated.', 'wp-easycart' ) );
			} else {
				WP_CLI::log( __( 'Tables: up to date.', 'wp-easycart' ) );
			}

			/* 2. The version steps, pass after pass. Each pass works up to 20 seconds and keeps its place. */
			$waited = 0;
			$passes = 0;
			while ( ec_db_manager::update_pending() ) {
				if ( get_transient( 'ec_db_update_lock' ) ) {
					if ( $waited >= self::LOCK_WAIT ) {
						WP_CLI::error( __( 'Another request has been running the database update for 5 minutes. Run this again later.', 'wp-easycart' ) );
					}
					WP_CLI::log( __( 'Waiting: another request is running the update...', 'wp-easycart' ) );
					sleep( 10 );
					$waited += 10;
					continue;
				}
				if ( ++$passes > self::MAX_PASSES ) {
					WP_CLI::error( __( 'The update is still running after an hour. Run this again to carry on.', 'wp-easycart' ) );
				}
				delete_transient( 'ec_db_update_backoff' ); // This command is the retry the backoff waits for.
				/* translators: %s: name of the update step */
				WP_CLI::log( sprintf( __( 'Running %s...', 'wp-easycart' ), ec_db_manager::update_in_progress() ) );
				$manager->try_db_update();
				$failing = ec_db_manager::get_failing_steps();
				if ( $failing && $skip ) {
					ec_db_manager::skip_failing_steps();
					/* translators: %s: names of the update steps */
					WP_CLI::warning( sprintf( __( 'Skipped %s. The store keeps working; Store Status lists the change as not made.', 'wp-easycart' ), implode( ', ', $failing ) ) );
				} elseif ( $failing ) {
					self::fail(
						/* translators: %s: names of the update steps */
						sprintf( __( 'These update steps failed: %s. Fix the cause and run this again, or add --skip-failing to finish without them.', 'wp-easycart' ), implode( ', ', $failing ) ),
						$manager->get_install_errors()
					);
				}
				wp_easycart_cache::runtime();
			}

			/* 3. The background fills, which run on admin_init in wp-admin. */
			foreach ( self::fills() as $fill ) {
				self::run_fill( $fill );
			}
			wp_easycart_reports::schedule(); // The Reports summary email's daily check, also set up on admin_init.

			$problems = $manager->verify_db();
			if ( $problems ) {
				/* translators: %d: number of problems */
				WP_CLI::warning( sprintf( _n( '%d table problem remains. Run: wp easycart db verify', '%d table problems remain. Run: wp easycart db verify', count( $problems ), 'wp-easycart' ), count( $problems ) ) );
			}
			/* translators: %s: WP EasyCart version */
			WP_CLI::success( sprintf( __( 'The WP EasyCart database is up to date ( %s ).', 'wp-easycart' ), str_replace( '_', '.', EC_CURRENT_VERSION ) ) );
		}

		/**
		 * Check every WP EasyCart table and column against the schema, and repair what is missing.
		 *
		 * ## OPTIONS
		 *
		 * [--repair]
		 * : Add the missing tables and columns, as the Store Status page's Repair does.
		 *
		 * [--yes]
		 * : Repair without asking first.
		 *
		 * [--format=<format>]
		 * : Output format for the problems.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - json
		 *   - csv
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp easycart db verify
		 *     $ wp easycart db verify --repair --yes
		 *
		 * @param array $args       Positional arguments ( none ).
		 * @param array $assoc_args Options.
		 */
		public function verify( $args, $assoc_args ) {
			wp_easycart_cli::used( 'db' );
			$manager  = new ec_db_manager();
			$problems = $manager->verify_db( true );
			if ( ! $problems ) {
				WP_CLI::success( __( 'Every WP EasyCart table and column is in place.', 'wp-easycart' ) );
				return;
			}
			$rows = array();
			foreach ( $problems as $problem ) {
				$rows[] = array(
					'type'    => $problem['type'],
					'problem' => $problem['error'],
				);
			}
			\WP_CLI\Utils\format_items( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'type', 'problem' ) );
			if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'repair', false ) ) {
				/* translators: %d: number of problems */
				WP_CLI::error( sprintf( _n( '%d problem found. Run again with --repair to fix it.', '%d problems found. Run again with --repair to fix them.', count( $problems ), 'wp-easycart' ), count( $problems ) ) );
			}
			WP_CLI::confirm( __( 'Repair these tables?', 'wp-easycart' ), $assoc_args );
			$remaining = $manager->try_repair();
			if ( $remaining ) {
				self::fail( __( 'Some problems could not be repaired.', 'wp-easycart' ), wp_list_pluck( $remaining, 'error' ) );
			}
			WP_CLI::success( __( 'Repaired. Every WP EasyCart table and column is in place.', 'wp-easycart' ) );
		}

		/**
		 * Where the database stands ( wp easycart info shows it too ).
		 *
		 * @return array database ( current | pending | failing ), next_step, tables, failing, skipped, running, backfills.
		 */
		public static function state() {
			$failing  = ec_db_manager::get_failing_steps();
			$tables   = (string) get_option( 'ec_option_db_new_version' );
			$database = 'current';
			if ( $failing ) {
				$database = 'failing';
			} elseif ( ec_db_manager::update_pending() || (string) EC_UPGRADE_DB !== $tables ) {
				$database = 'pending';
			}
			$backfills = 'done';
			foreach ( self::fills() as $fill ) {
				if ( ! self::fill_done( $fill ) ) {
					$backfills = 'pending';
				}
			}
			return array(
				'database'  => $database,
				'next_step' => (string) ec_db_manager::update_in_progress(),
				'tables'    => $tables,
				'failing'   => $failing,
				'skipped'   => ec_db_manager::get_skipped_steps(),
				'running'   => (bool) get_transient( 'ec_db_update_lock' ),
				'backfills' => $backfills,
			);
		}

		/**
		 * The background fills an update leaves for admin_init: key => label, the call that does one slice, the option that
		 * keeps its place ( 'done' once finished ).
		 *
		 * @return array
		 */
		private static function fills() {
			return array(
				'reports_history' => array(
					'label'  => __( 'Reports history', 'wp-easycart' ),
					'run'    => array( 'wp_easycart_order_ledger', 'backfill' ),
					'option' => wp_easycart_order_ledger::BACKFILL_OPTION,
				),
				'amounts_paid'    => array(
					'label'  => __( 'Amounts paid', 'wp-easycart' ),
					'run'    => array( 'wp_easycart_order_payments', 'backfill' ),
					'option' => wp_easycart_order_payments::BACKFILL_OPTION,
				),
				'log_tokens'      => array(
					'label'  => __( 'Gateway log tokens', 'wp-easycart' ),
					'run'    => array( 'ec_logs', 'scrub_stored_tokens' ),
					'option' => ec_logs::SCRUB_OPTION,
				),
			);
		}

		/**
		 * Has a fill finished?
		 *
		 * @param array $fill One of fills().
		 * @return bool
		 */
		private static function fill_done( $fill ) {
			$state = get_option( $fill['option'] );
			return is_array( $state ) && ! empty( $state['done'] );
		}

		/**
		 * Run one fill to the end, slice after slice. A slice that changes nothing ( its table is not ready, a query failed )
		 * stops it with a warning, and wp-admin carries it on later as before.
		 *
		 * @param array $fill One of fills().
		 */
		private static function run_fill( $fill ) {
			$last = null;
			while ( ! self::fill_done( $fill ) ) {
				call_user_func( $fill['run'] );
				$now = wp_json_encode( get_option( $fill['option'] ) );
				if ( $now === $last ) {
					/* translators: %s: name of the background fill */
					WP_CLI::warning( sprintf( __( '%s stopped making progress. wp-admin will carry it on.', 'wp-easycart' ), $fill['label'] ) );
					return;
				}
				$last = $now;
				wp_easycart_cache::runtime();
			}
			/* translators: %s: name of the background fill */
			WP_CLI::log( sprintf( __( '%s: done.', 'wp-easycart' ), $fill['label'] ) );
		}

		/**
		 * Print the database's own messages, then stop with exit status 1.
		 *
		 * @param string $message What failed.
		 * @param array  $details Messages.
		 */
		private static function fail( $message, $details ) {
			foreach ( array_slice( (array) $details, 0, 20 ) as $detail ) {
				WP_CLI::log( '  - ' . $detail );
			}
			WP_CLI::error( $message );
		}
	}

endif;
