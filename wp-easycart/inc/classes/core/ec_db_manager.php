<?php

class ec_db_manager {

	/**
	 * Set by update functions when a required ALTER did not take effect
	 * ( $wpdb->query() returns false on SQL errors without throwing, so
	 * Throwable-only catching in try_db_update() misses those failures ).
	 */
	private $update_failed = false;
	private $update_errors = array();

	/**
	 * Wall-clock budget (seconds) one admin/cron request may spend inside try_db_update().
	 * Batched steps return false when it is exhausted and the next request resumes them.
	 *
	 * @since 6.0.0
	 */
	const UPDATE_TIME_BUDGET = 20;

	/** Rows per UPDATE when a version step rewrites an existing table. @since 6.0.0 */
	const UPDATE_BATCH_SIZE = 5000;

	/** Option holding the version-chain progress ( array of completed step names ). @since 6.0.0 */
	const PROGRESS_OPTION = 'ec_option_db_update_progress';

	/** Option that records the EC_UPGRADE_DB the schema was last verified against. @since 6.0.0 */
	const SCHEMA_VERIFIED_OPTION = 'ec_option_db_schema_verified';

	/** microtime() at which the running try_db_update() must stop starting new work; 0 = no limit. */
	private $update_deadline = 0;

	public function install_db( $force = false ) {
		global $wpdb;

		if ( ! $force && get_transient( 'ec_db_install_backoff' ) ) {
			return false;
		}

		$this->run_initial_update();
		$show_errors = $wpdb->hide_errors();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		/* Index definitions dbDelta cannot convert (unique -> plain, prefix length changes) must be
		   converged before dbDelta runs, otherwise it issues ADD KEY and hits "Duplicate key name". */
		$this->normalize_indexes();
		dbDelta( self::get_schema( ) );

		$install_errors = $this->get_missing_table_errors();

		if ( ! count( $install_errors ) ) {
			/* Seed base data only on a truly empty store. install_base_data() never writes to ec_user,
			   so testing ec_user (as older versions did) re-seeds every store with no registered
			   customers and produces hundreds of "Duplicate entry for key PRIMARY" errors. ec_setting
			   row 1 is always written by the seed, so it is the reliable marker. */
			$setting_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_setting" );
			if ( 0 === $setting_count ) {
				$this->install_base_data();
			}
		}

		if ( $show_errors ) {
			$wpdb->show_errors();
		}

		if ( count( $install_errors ) ) {
			update_option( 'ec_option_db_install_errors', $install_errors );
			set_transient( 'ec_db_install_backoff', 1, 10 * MINUTE_IN_SECONDS );
			foreach ( $install_errors as $install_error ) {
				//error_log( 'WP EasyCart DB install: ' . $install_error );
			}
			return false;
		}

		delete_option( 'ec_option_db_install_errors' );
		delete_transient( 'ec_db_install_backoff' );
		$this->install_recommended_defaults();
		$this->apply_default_order_status_colors();
		update_option( 'ec_option_db_version', EC_CURRENT_DB );
		update_option( 'ec_option_db_new_version', EC_UPGRADE_DB );
		return true;
	}

	public function get_missing_table_errors() {
		global $wpdb;
		$errors = array();
		$create_statements = explode( ';', $this->get_schema() );
		foreach ( $create_statements as $create_statement ) {
			if ( ! preg_match( '/CREATE\sTABLE\s([a-z0-9\_]+)\s\(/', $create_statement, $match ) ) {
				continue;
			}
			$table = $match[1];
			if ( $this->table_exists( $table ) ) {
				continue;
			}
			$wpdb->query( $this->sanitize_column_sql( $create_statement ) );
			if ( $this->table_exists( $table ) ) {
				continue;
			}
			$errors[] = $table . ': ' . ( $wpdb->last_error ? $wpdb->last_error : 'table could not be created' );
		}
		return $errors;
	}

	public function table_exists( $table ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s", $table ) );
	}

	public function get_install_errors() {
		$errors = get_option( 'ec_option_db_install_errors' );
		return is_array( $errors ) ? $errors : array();
	}

	public function install_recommended_defaults() {
		if ( class_exists( 'ec_wpoptionset' ) && method_exists( 'ec_wpoptionset', 'apply_recommended_defaults' ) ) {
			try {
				ec_wpoptionset::apply_recommended_defaults();
			} catch ( \Throwable $e ) {
				//error_log( 'WP EasyCart: apply_recommended_defaults failed: ' . $e->getMessage() );
			}
			return;
		}
	}

	/**
	 * Built-in order statuses still on the colour column's old default ( white, from 5.7.6, or empty ) get the colours a
	 * fresh install seeds, so the status editor and order chips are not a wall of white swatches. A colour the merchant
	 * picked is never changed, and custom statuses are left alone. Runs from install_db(), so once per EC_UPGRADE_DB.
	 *
	 * @since 6.0.0
	 */
	public function apply_default_order_status_colors() {
		global $wpdb;
		if ( ! $this->table_exists( 'ec_orderstatus' ) || ! $this->column_exists( 'ec_orderstatus', 'color_code' ) ) {
			return;
		}
		$green    = '#81D742';
		$amber    = '#DD9933';
		$red      = '#FF3030';
		$defaults = array(
			1  => '#999999', // Status Not Found.
			2  => $green,    // Order Shipped.
			3  => $green,    // Order Confirmed.
			4  => $amber,    // Order on Hold.
			5  => $amber,    // Order Started.
			6  => $green,    // Card Approved.
			7  => $red,      // Card Denied.
			8  => $amber,    // Third Party Pending.
			9  => $red,      // Third Party Error.
			10 => $green,    // Third Party Approved.
			11 => $amber,    // Ready for Pickup.
			12 => $amber,    // Pending Approval.
			14 => $amber,    // Direct Deposit Pending.
			15 => $green,    // Direct Deposit Received.
			16 => $red,      // Refunded Order.
			17 => $amber,    // Partial Refund.
			18 => $green,    // Order Picked Up.
			19 => $red,      // Order Cancelled.
		);
		foreach ( $defaults as $status_id => $color ) {
			$wpdb->query( $wpdb->prepare( "UPDATE ec_orderstatus SET color_code = %s WHERE status_id = %d AND ( color_code IS NULL OR TRIM( color_code ) = '' OR UPPER( TRIM( color_code ) ) IN ( '#FFFFFF', '#FFF', 'WHITE' ) )", $color, $status_id ) );
		}
	}

	public function uninstall_db() {
		global $wpdb;
		$tables = $this->get_uninstall_tables();
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS " . $table . ";" );
		}
	}

	public function check_db() {
		global $wpdb;
		$tables = $this->get_uninstall_tables( );
		foreach ( $tables as $table ) {
			if ( ! $this->table_exists( $table ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Compare every ec_* table against get_schema() and return the fixes needed.
	 *
	 * Runs SHOW FULL COLUMNS for every table, so once a pass has come back clean for the current
	 * EC_UPGRADE_DB the result is remembered in SCHEMA_VERIFIED_OPTION and later calls return
	 * array() immediately. Pass $force = true to re-check anyway ( Diagnostics "re-check":
	 * callers may hand through `! empty( $_GET['recheck'] )` ). try_repair() and the end of
	 * try_db_update() always force.
	 *
	 * @since 6.0.0 Added $force; earlier versions always ran the full check.
	 * @param bool $force Re-check even when the schema was already verified at this EC_UPGRADE_DB.
	 * @return array
	 */
	public function verify_db( $force = false ) {
		global $wpdb;
		$errors = array( );
		if ( ! $force && (string) get_option( self::SCHEMA_VERIFIED_OPTION ) === (string) EC_UPGRADE_DB ) {
			/* Same schema, already verified: keep the plugin-version marker current so
			   database_check_current() stops asking on every admin page. */
			if ( version_compare( str_replace( '_', '.', EC_CURRENT_VERSION ), (string) get_option( 'ec_option_db_version_verified' ), '>' ) ) {
				update_option( 'ec_option_db_version_verified', str_replace( '_', '.', EC_CURRENT_VERSION ) );
			}
			return $errors;
		}
		$collate = '';
		$collation = '';
		if( $wpdb->has_cap( 'collation' ) ){
			$collation = str_replace( 'DEFAULT ', '', $wpdb->get_charset_collate() );
			$collate = $wpdb->collate;
		}
		$schema = $this->get_schema( );
		$create_statements = explode( ';', $schema );
		foreach( $create_statements as $create_statement ){
			preg_match_all( '/CREATE\sTABLE\s([a-z0-9\_]*)\s\((.*)\)/s', $create_statement, $table_data );
			if( count( $table_data ) == 3 && is_array( $table_data[1] ) && count( $table_data[1] ) == 1 && $table_data[1][0] != '' ){
				$table_name = trim( $table_data[1][0] );
				$table_rows_string = $table_data[2][0];
				$table_rows_strings = explode( PHP_EOL, $table_rows_string );
				$table_rows = array( );
				foreach( $table_rows_strings as $table_row_string ){
					preg_match_all( '/([a-z0-9\_]*)\s(text|[a-zA-Z\(\)\,0-9]*)(\sDEFAULT NULL|\sNOT\sNULL\s|\sNOT\sNULL|\sNULL\s|\sNULL|\s|.*)(DEFAULT.*|.*)\,/', trim( $table_row_string ), $table_row_item );
					if( count( $table_row_item ) == 5 && count( $table_row_item[1] ) && count( $table_row_item[2] ) && count( $table_row_item[3] ) && count( $table_row_item[4] ) && $table_row_item[1][0] != '' ){
						if( substr( $table_row_item[4][0], strlen( $table_row_item[4][0] ) - 1, 1 ) == "'" ){
							$temp_default_string = substr( $table_row_item[4][0], 0, strlen( $table_row_item[4][0] ) - 1 );
						}else{
							$temp_default_string = $table_row_item[4][0];
						}

						$table_rows[(string)$table_row_item[1][0]] = (object) array(
							'Field'     => $table_row_item[1][0],
							'Type'      => $table_row_item[2][0],
							'Collation' => $collate,
							'Null'      => ( trim( $table_row_item[3][0] ) == 'NOT NULL' ) ? 'NO' : 'YES',
							'Default'   => str_replace( "CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", "CURRENT_TIMESTAMP", str_replace( "ON UPDATE CURRENT_TIMESTAMP CURRENT_TIMESTAMP", "CURRENT_TIMESTAMP", str_replace( "DEFAULT ", "", str_replace( "DEFAULT '", "", $temp_default_string ) ) ) ),
							'SQL'       => substr( $table_row_string, 0, strlen( $table_row_string ) - 1 )
						);
					}
				}

				$table_schema_rows = $wpdb->get_results( "SHOW FULL COLUMNS FROM " . $table_name );

				// Check the table exists at all
				if( !$table_schema_rows ){
					$errors[] = array( 'type' => 'missing_table', 'sql_fix' => $create_statement, 'error' => 'The table ' . $table_name . ' is missing from your database.' );

				}else{
					$table_schema = array( );
					foreach( $table_schema_rows as $table_schema_row ){
						$table_schema[$table_schema_row->Field] = $table_schema_row;
					}

					foreach( $table_rows as $table_row_item ){

						// Check that the table column exists
						if( !isset( $table_schema[ $table_row_item->Field ] ) ){
							$errors[] = array( 'type' => 'missing_field', 'sql_fix' => 'ALTER TABLE ' . $table_name . ' ADD COLUMN ' . $this->sanitize_column_sql( $table_row_item->SQL ), 'error' => $table_name . ' is missing "' . $table_row_item->Field . '"' );

						// Exists, lets check its valid
						}else{

							// Check the type is correct (removed to prevent unecessary error notices)
							if( strtolower( trim( $table_schema[ $table_row_item->Field ]->Type ) ) != strtolower( trim( $table_row_item->Type ) ) ){
								//$errors[] = array( 'type' => 'incorrect_type', 'sql_fix' => 'ALTER TABLE ' . $table_name . ' MODIFY ' . $table_row_item->SQL, 'error' => $table_name . '.' . $table_row_item->Field . ' is the wrong type, listed as "' . trim( $table_schema[ $table_row_item->Field]->Type ) . '" should be "' . trim( $table_row_item->Type ) . '"' );
							}
								
							// Check if VARCHAR and verify encoding
							/*if ( apply_filters( 'wp_easycart_enable_encoding_verification', false ) && 'varchar' == substr( $table_schema[ $table_row_item->Field ]->Type, 0, 7 ) && isset( $table_schema[ $table_row_item->Field ]->Collation ) && strtolower( trim( $table_schema[ $table_row_item->Field ]->Collation ) ) != strtolower( trim( $table_row_item->Collation ) ) ) {
								$errors[] = array(
									'type' => 'incorrect_encoding',
									'sql_fix' => 'ALTER TABLE ' . $table_name . ' MODIFY ' . $table_row_item->Field . ' ' . $table_row_item->Type . ' ' . $collation . $table_row_item->Default,
									'error' => $table_name . '.' . $table_row_item->Field . ' is the encoding wrong type, listed as "' . trim( $table_schema[ $table_row_item->Field ]->Collation ) . '" should be "' . $table_row_item->Collation . '"',
								);
							}*/

							// Check the Null / Not Null is correct (removed to prevent unecessary error notices)
							if( trim( $table_schema[ $table_row_item->Field ]->Null ) != trim( $table_row_item->Null ) ){
								//$errors[] = array( 'type' => 'incorrect_null', 'sql_fix' => 'ALTER TABLE ' . $table_name . ' MODIFY ' . $table_row_item->SQL, 'error' => $table_name . '.' . $table_row_item->Field . ' is the wrong "IS NULL" value, listed as "' . trim( $table_schema[ $table_row_item->Field]->Null ) . '" should be "' . trim( $table_row_item->Null ) . '"' );
							}

							// Check the Default is correct (removed to prevent unecessary error notices)
							if( strtoupper( trim( $table_row_item->Default ) ) == 'AUTO_INCREMENT' ){
								// Ignore

							}else if( strtoupper( trim( $table_row_item->Default ) ) == 'CURRENT_TIMESTAMP' ){
								// Ignore

							}

						}
					}
				}
			}
		}
		if ( ! count( $errors ) ) {
			update_option( 'ec_option_db_version_verified', str_replace( '_', '.', EC_CURRENT_VERSION ) );
			update_option( self::SCHEMA_VERIFIED_OPTION, (string) EC_UPGRADE_DB );
		} else {
			delete_option( self::SCHEMA_VERIFIED_OPTION );
		}
		return $errors;
	}

	public function try_repair() {
		global $wpdb;
		$wpdb->query( 'SET innodb_strict_mode=OFF' );
		$errors = $this->verify_db( true );
		foreach ( $errors as $error ) {
			$result = $wpdb->query( $this->sanitize_column_sql( $error['sql_fix'] ) );
		}
		$remaining = $this->verify_db( true );
		if ( ! count( $remaining ) ) {
			/* Structure is clean: clear any recorded install failures and the retry
			   backoff so the next install_db() pass runs immediately, and mark the
			   current version verified so the repair notice stops showing. */
			delete_option( 'ec_option_db_install_errors' );
			delete_transient( 'ec_db_install_backoff' );
			delete_transient( 'ec_db_update_backoff' );
			update_option( 'ec_option_db_version_verified', str_replace( '_', '.', EC_CURRENT_VERSION ) );
		}
		return $remaining;
	}

	/**
	 * MySQL 5.7/8.0 reject literal defaults on BLOB/TEXT/GEOMETRY/JSON columns.
	 * Strip them from any column definition or ALTER we are about to run.
	 */
	public function sanitize_column_sql( $sql ) {
		return preg_replace( "/\\b((?:tiny|medium|long)?(?:text|blob)|geometry|json)(\\s+NOT\\s+NULL|\\s+NULL)?\\s+DEFAULT\\s+''/i", '$1 NULL', $sql );
	}

	public function get_db_errors() {
		global $wpdb;
		$error = "You are missing the following DB tables: ";
		$tables = $this->get_uninstall_tables( );
		$first = true;
		foreach ( $tables as $table ) {
			if ( ! $this->table_exists( $table ) ) {
				if ( ! $first ) {
					$error .= ", ";
				}
				$error .= $table;
				$first = false;
			}
		}
		return $error;
	}

	/**
	 * Whether the current request may run the version-upgrade chain.
	 *
	 * Only a plain admin page load, WP-Cron or WP-CLI pays for the upgrade; the storefront and
	 * admin-ajax never do. Cron keeps a store moving even when nobody opens wp-admin.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function update_allowed_in_request() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return true;
		}
		return false;
	}

	/**
	 * True while the stored DB version is behind the plugin version, i.e. the chain still has to run.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function update_pending() {
		return version_compare( str_replace( '_', '.', EC_CURRENT_VERSION ), (string) get_option( 'ec_option_db_version_updated' ), '>' );
	}

	/**
	 * The upgrade step that has not finished yet, for Store Status / Diagnostics.
	 *
	 * Returns false once the chain has completed for this version. While it is pending it returns
	 * the name of the first step not recorded in PROGRESS_OPTION ( the one a resume will run next ),
	 * or 'finalize' when every step is done and only the version bump / verify remains.
	 *
	 * @since 6.0.0
	 * @return string|false
	 */
	public static function update_in_progress() {
		if ( ! self::update_pending() ) {
			return false;
		}
		$manager = new ec_db_manager();
		$completed = self::get_update_progress();
		foreach ( $manager->get_new_update_functions() as $function ) {
			if ( ! in_array( $function, $completed, true ) ) {
				return $function;
			}
		}
		return 'finalize';
	}

	/**
	 * Names of the version steps already completed for the pending upgrade.
	 *
	 * @since 6.0.0
	 * @return string[]
	 */
	public static function get_update_progress() {
		$progress = get_option( self::PROGRESS_OPTION );
		if ( ! is_array( $progress ) || ! isset( $progress['completed'] ) || ! is_array( $progress['completed'] ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $progress['completed'] ) ) );
	}

	private function save_update_progress( array $completed ) {
		update_option( self::PROGRESS_OPTION, array(
			'target'    => str_replace( '_', '.', EC_CURRENT_VERSION ),
			'completed' => array_values( array_unique( $completed ) ),
			'updated'   => time(),
		), false );
	}

	/**
	 * True once the request's UPDATE_TIME_BUDGET is spent. Batched steps call this between
	 * chunks and return false ( "come back later" ) when it is.
	 */
	private function out_of_time() {
		return $this->update_deadline > 0 && microtime( true ) >= $this->update_deadline;
	}

	/**
	 * Run the outstanding version steps for this release.
	 *
	 * Resumable: every completed step is recorded in PROGRESS_OPTION, so a request that times out
	 * ( or a batched step that returns false because UPDATE_TIME_BUDGET is spent ) continues at the
	 * first unfinished step on the next admin/cron request instead of starting over. The version
	 * option is only bumped once every step has finished cleanly, so a partial run never claims a
	 * version it did not complete.
	 */
	public function try_db_update() {
		if ( ! self::update_allowed_in_request() ) {
			return;
		}
		if ( get_transient( 'ec_db_update_lock' ) || get_transient( 'ec_db_update_backoff' ) ) {
			return;
		}
		set_transient( 'ec_db_update_lock', 1, 5 * MINUTE_IN_SECONDS );
		$this->update_deadline = microtime( true ) + self::UPDATE_TIME_BUDGET;

		$functions = $this->get_new_update_functions( );
		$completed = self::get_update_progress();
		$failed = false;
		$paused = false;
		$this->update_failed = false;
		$this->update_errors = array();
		foreach ( $functions as $function ) {
			if ( in_array( $function, $completed, true ) || ! method_exists( $this, $function ) ) {
				continue;
			}
			if ( $this->out_of_time() ) {
				$paused = true;
				break;
			}
			$errors_before = count( $this->update_errors );
			$result = null;
			try {
				$result = $this->$function();
			} catch ( \Throwable $e ) {
				$result = null;
				$failed = true;
				$this->update_errors[] = $function . ': ' . $e->getMessage();
			}
			if ( count( $this->update_errors ) > $errors_before ) {
				/* Not recorded as complete: it re-runs after the backoff. Keep going so steps that do
				   not depend on it still land ( every step is idempotent ). */
				$failed = true;
				continue;
			}
			if ( false === $result ) {
				/* A batched step ran out of time with work left; it saved its own cursor. */
				$paused = true;
				break;
			}
			$completed[] = $function;
			$this->save_update_progress( $completed );
		}
		if ( $paused ) {
			/* No backoff: the next admin/cron request resumes at the first unfinished step. */
			delete_transient( 'ec_db_update_lock' );
			return;
		}
		/* $wpdb->query() returns false on SQL errors without throwing; update functions
		   that verify their own ALTERs report those failures via $this->update_failed
		   so the version only bumps when the migration actually took effect. */
		if ( ! $failed && ! $this->update_failed ) {
			update_option( 'ec_option_db_version_updated', str_replace( '_', '.', EC_CURRENT_VERSION ) );
			delete_option( self::PROGRESS_OPTION );
		} else {
			/* Do not re-run the migration on every request while the cause persists; surface the
			   messages on the Store Status page. try_repair() clears both on a clean verify. The
			   progress option is kept so the steps that did complete are not repeated. */
			set_transient( 'ec_db_update_backoff', 1, 10 * MINUTE_IN_SECONDS );
			update_option( 'ec_option_db_install_errors', array_merge( $this->get_install_errors(), $this->update_errors ) );
		}
		/* One forced structure check per completed chain; verify_db() marks the version on a clean pass. */
		$this->verify_db( true );
		$this->update_deadline = 0;
		delete_transient( 'ec_db_update_lock' );
	}

	public function get_new_update_functions() {
		$function_list = array(
			'4.3.3' => array(
				'wpeasycart_sql_4_3_3'
			),
			'5.0.0' => array(
				'wpeasycart_sql_5_0_0'
			),
			'5.0.2' => array(
				'wpeasycart_sql_5_0_2'
			),
			'5.1.14' => array(
				'wpeasycart_sql_5_1_14'
			),
			'5.1.16' => array(
				'wpeasycart_sql_5_1_16'
			),
			'5.2.1' => array(
				'wpeasycart_sql_5_2_1'
			),
			'5.2.2' => array(
				'wpeasycart_sql_5_2_2'
			),
			'5.3.4' => array(
				'wpeasycart_sql_5_3_4'
			),
			'5.3.5' => array(
				'wpeasycart_sql_5_3_5'
			),
			'5.3.11' => array(
				'wpeasycart_sql_5_3_11'
			),
			'5.3.14' => array(
				'wpeasycart_sql_5_3_14'
			),
			'5.4.1' => array(
				'wpeasycart_sql_5_4_1'
			),
			'5.4.3' => array(
				'wpeasycart_sql_5_4_3'
			),
			'5.4.6' => array(
				'wpeasycart_sql_5_4_6'
			),
			'5.4.9' => array(
				'wpeasycart_sql_5_4_9'
			),
			'5.5.6' => array(
				'wpeasycart_sql_5_5_6'
			),
			'5.5.11' => array(
				'wpeasycart_sql_5_5_11'
			),
			'5.6.0' => array(
				'wpeasycart_sql_5_6_0'
			),
			'5.6.1' => array(
				'wpeasycart_sql_5_6_1'
			),
			'5.6.4' => array(
				'wpeasycart_sql_5_6_4'
			),
			'5.7.5' => array(
				'wpeasycart_sql_5_7_5'
			),
			'5.7.6' => array(
				'wpeasycart_sql_5_7_6'
			),
			'5.8.1' => array(
				'wpeasycart_sql_5_8_1'
			),
			'5.8.2' => array(
				'wpeasycart_sql_5_8_2'
			),
			'5.8.11' => array(
				'wpeasycart_sql_5_8_11'
			),
			'5.8.12' => array(
				'wpeasycart_sql_5_8_12'
			),
			'5.8.13' => array(
				'wpeasycart_sql_5_8_13'
			),
			'5.8.15' => array(
				'wpeasycart_sql_5_8_15'
			),
			'5.8.16' => array(
				'wpeasycart_sql_5_8_16'
			),
			'5.9.2' => array(
				'wpeasycart_sql_5_9_2'
			),
			'5.9.4' => array(
				'wpeasycart_sql_5_9_4'
			),
			'6.0.0' => array(
				'wpeasycart_sql_6_0_0',
				'wpeasycart_sql_6_0_0_catalog',
				'wpeasycart_sql_6_0_0_reviews',
				'wpeasycart_sql_6_0_0_email',
				'wpeasycart_sql_6_0_0_abandoned',
				'wpeasycart_sql_6_0_0_fees',
				'wpeasycart_sql_6_0_0_live_rates',
				'wpeasycart_sql_6_0_0_stock',
				'wpeasycart_sql_6_0_0_indexes',
				/* Batched data steps last so every schema change above lands before the long-running rewrites start. */
				'wpeasycart_sql_6_0_0_user_dates',
				'wpeasycart_sql_6_0_0_zero_dates'
			),
		);

		$return_functions = array();
		foreach ( $function_list as $version => $functions ) {
			if ( version_compare( $version, get_option( 'ec_option_db_version_updated' ), '>' ) ) {
				for ( $i = 0; $i < count( $functions ); $i++ ) {
					$return_functions[] = $functions[ $i ];
				}
			}
		}
		return $return_functions;
	}

	private function run_initial_update( ){
		if( !get_option( 'ec_option_db_insert_v4' ) || get_option( 'ec_option_db_insert_v4' ) == '0' ){
			global $wpdb;
			$this->rename_column( 'ec_menulevel1', 'order', 'menu_order', 'int(11)' );
			$this->rename_column( 'ec_menulevel2', 'order', 'menu_order', 'int(11)' );
			$this->rename_column( 'ec_menulevel3', 'order', 'menu_order', 'int(11)' );
			$this->rename_column( 'ec_pricepoint', 'order', 'pricepoint_order', 'int(11)' );
			$this->rename_column( 'ec_promotion', 'limit', 'promo_limit', 'int(11)' );
			update_option( 'ec_option_db_insert_v4', 1 );
		}
	}

	/* Database Upgrade Scripts */
	private function wpeasycart_sql_4_3_3() {
		global $wpdb;
		$this->add_column( 'ec_country', 'vat_b2b_enabled', "tinyint(1) NOT NULL DEFAULT '1'" );
		$this->add_column( 'ec_order', 'tip_total', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_orderstatus', 'is_archieved', "tinyint(1) DEFAULT '0'" );
		$wpdb->query( "ALTER TABLE ec_taxrate MODIFY stripe_taxrate_id varchar(255) NOT NULL" );
		$this->add_column( 'ec_tempcart_data', 'tip_amount', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_tempcart_data', 'tip_rate', "varchar(10) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_review', 'reviewer_name', "varchar(255) NOT NULL DEFAULT ''" );
	}
	
	private function wpeasycart_sql_5_0_0() {
		global $wpdb;
		$this->add_column( 'ec_optionitemimage', 'product_images', "text NULL" );
		$this->add_column( 'ec_product', 'product_images', "text NULL" );
		$this->add_column( 'ec_product', 'sort_position', "int(11) NOT NULL DEFAULT '0'" );
	}
	
	private function wpeasycart_sql_5_0_2() {
		global $wpdb;
		$this->add_column( 'ec_product', 'shopify_id', "varchar(255) NOT NULL DEFAULT ''" );
	}
	
	private function wpeasycart_sql_5_1_14() {
		global $wpdb;
		$collate = "";
		$max_index_length = 191;
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_order_log (
		  order_log_id int(11) NOT NULL AUTO_INCREMENT,
		  order_id int(11) NOT NULL DEFAULT '0',
		  order_log_key varchar(100) NOT NULL DEFAULT '',
		  order_log_timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (order_log_id),
		  UNIQUE KEY order_log_id (order_log_id),
		  KEY order_id (order_id)
		) $collate;
		CREATE TABLE IF NOT EXISTS ec_order_log_meta (
		  order_log_meta_id int(11) NOT NULL AUTO_INCREMENT,
		  order_log_id int(11) NOT NULL DEFAULT '0',
		  order_id int(11) NOT NULL DEFAULT '0',
		  order_log_meta_key varchar(100) NOT NULL DEFAULT '',
		  order_log_meta_value text,
		  PRIMARY KEY  (order_log_meta_id),
		  UNIQUE KEY order_log_meta_id (order_log_meta_id),
		  KEY order_log_id (order_log_id),
		  KEY order_id (order_id)
		) $collate;" );
	}
	
	private function wpeasycart_sql_5_1_16() {
		global $wpdb;
		$this->add_column( 'ec_tempcart_data', 'amazon_session_id', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart_data', 'amazon_buyer_id', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart_data', 'amazon_payment_selection', "varchar(255) NOT NULL DEFAULT ''" );
	}
	
	private function wpeasycart_sql_5_2_1() {
		global $wpdb;
		$this->add_column( 'ec_order', 'success_page_shown', "tinyint(1) NOT NULL DEFAULT 0" );
	}
	
	private function wpeasycart_sql_5_2_2() {
		global $wpdb;
		$this->add_column( 'ec_order', 'email_other', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart_data', 'email_other', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_user', 'email_other', "varchar(255) NOT NULL DEFAULT ''" );
	}
	
	private function wpeasycart_sql_5_3_4() {
		global $wpdb;
		$this->add_column( 'ec_product', 'square_variation_id', "varchar(255) NOT NULL DEFAULT ''" );
	}
	
	private function wpeasycart_sql_5_3_5() {
		global $wpdb;
		$this->add_column( 'ec_category', 'is_active', "tinyint(1) NOT NULL DEFAULT '1'" );
		$this->add_column( 'ec_optionitem', 'optionitem_enable_custom_price_label', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_optionitem', 'optionitem_custom_price_label', "text" );
		$this->add_column( 'ec_optionitemquantity', 'sku', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_optionitemquantity', 'price', "float(15,3) NOT NULL DEFAULT '-1.000'" );
		$this->add_column( 'ec_optionitemquantity', 'is_enabled', "tinyint(1) NOT NULL DEFAULT '1'" );
		$this->add_column( 'ec_optionitemquantity', 'is_stock_tracking_enabled', "tinyint(1) NOT NULL DEFAULT '1'" );
		$this->add_column( 'ec_optionitemquantity', 'square_id', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_orderdetail', 'use_both_option_types', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_product', 'use_both_option_types', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_3_11() {
		global $wpdb;
		$this->add_column( 'ec_product', 'ship_to_billing', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_3_14() {
		global $wpdb;
		$this->add_column( 'ec_tempcart_data', 'taxjar_tax_amount', "varchar(255) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart_data', 'taxjar_address_verified', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_4_1() {
		global $wpdb;
		$this->add_column( 'ec_product', 'subscription_shipping_recurring', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_4_3() {
		global $wpdb;
		$this->add_column( 'ec_tempcart_data', 'taxcloud_address_last_verified', "text" );
		$this->add_column( 'ec_order_option', 'optionitem_price', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_price_onetime', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_price_override', "float(15,3) NOT NULL DEFAULT '-1.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_price_multiplier', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_price_per_character', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_weight', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_weight_onetime', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_weight_override', "float(15,3) NOT NULL DEFAULT '-1.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_weight_multiplier', "float(15,3) NOT NULL DEFAULT '0.00'" );
		$this->add_column( 'ec_order_option', 'optionitem_disallow_shipping', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_order_option', 'optionitem_enable_custom_price_label', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_order_option', 'optionitem_custom_price_label', "text" );
	}

	private function wpeasycart_sql_5_4_6() {
		global $wpdb;
		$this->add_column( 'ec_tempcart_data', 'stripe_last_pi_data', "text" );
	}

	private function wpeasycart_sql_5_4_9() {
		global $wpdb;
		$collate = "";
		$max_index_length = 191;
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_fee (
		  fee_id int(11) NOT NULL AUTO_INCREMENT,
		  fee_label varchar(512)  NOT NULL DEFAULT '',
		  fee_admin_description text,
		  fee_country text,
		  fee_state text,
		  fee_zip text,
		  fee_city text,
		  fee_category text,
		  fee_role text,
		  fee_zone text,
		  fee_type int(11) NOT NULL DEFAULT '1',
		  fee_rate float(15,3) NOT NULL DEFAULT '0.000',
		  fee_price float(15,3) NOT NULL DEFAULT '0.000',
		  fee_min float(15,3) NOT NULL DEFAULT '0.000',
		  fee_max float(15,3) NOT NULL DEFAULT '-1.000',
		  PRIMARY KEY  (fee_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_order_fee (
		  order_fee_id int(11) NOT NULL AUTO_INCREMENT,
		  order_id int(11) NOT NULL DEFAULT '0',
		  fee_label varchar(512)  NOT NULL DEFAULT '',
		  fee_rate float(15,3) NOT NULL DEFAULT '0.000',
		  fee_total float(15,3) NOT NULL DEFAULT '0.000',
		  PRIMARY KEY  (order_fee_id),
		  KEY order_id (order_id)
		) $collate;" );
		$this->add_column( 'ec_product', 'shipping_restriction', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_5_6() {
		global $wpdb;
		$this->add_column( 'ec_product', 'enable_price_label', "int(11) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_product', 'replace_price_label', "int(11) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_product', 'custom_price_label', "varchar(512) NOT NULL DEFAULT ''" );
	}

	private function wpeasycart_sql_5_5_11() {
		global $wpdb;
		$this->add_column( 'ec_fee', 'fee_payment_type', "varchar(255) DEFAULT ''" );
	}

	private function wpeasycart_sql_5_6_0() {
		global $wpdb;
		$this->add_column( 'ec_product', 'stripe_product_id', "varchar(255) DEFAULT ''" );
		$this->add_column( 'ec_product', 'stripe_default_price_id', "varchar(255) DEFAULT ''" );
		$this->add_column( 'ec_product', 'subscription_recurring_email', "tinyint(1) NOT NULL DEFAULT '1'" );
	}

	private function wpeasycart_sql_5_6_1() {
		global $wpdb;
		$this->add_column( 'ec_promocode', 'apply_to_shipping', "tinyint(1) NOT NULL DEFAULT '0'" );
	}

	private function wpeasycart_sql_5_6_4() {
		global $wpdb;
		$this->add_column( 'ec_option_to_product', 'stripe_price_id', "text" );
		$this->add_column( 'ec_optionitemquantity', 'google_merchant', "text NULL" );
	}

	private function wpeasycart_sql_5_7_5() {
		global $wpdb;
		$collate = "";
		$max_index_length = 191;
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_schedule (
			  schedule_id int(11) NOT NULL AUTO_INCREMENT,
			  schedule_label varchar(255) DEFAULT '',
			  day_of_week varchar(20) NOT NULL DEFAULT '',
			  is_holiday tinyint(1) NOT NULL DEFAULT 1,
			  holiday_date date DEFAULT NULL,
			  apply_to_retail tinyint(1) NOT NULL DEFAULT 0,
			  apply_to_preorder tinyint(1) NOT NULL DEFAULT 0,
			  apply_to_restaurant tinyint(1) NOT NULL DEFAULT 0,
			  retail_start varchar(32) NOT NULL DEFAULT '',
			  retail_end varchar(32) NOT NULL DEFAULT '',
			  preorder_start varchar(32) NOT NULL DEFAULT '',
			  preorder_end varchar(32) NOT NULL DEFAULT '',
			  preorder_open_time varchar(32) NOT NULL DEFAULT '',
			  preorder_close_time varchar(32) NOT NULL DEFAULT '',
			  restaurant_start varchar(32) NOT NULL DEFAULT '',
			  restaurant_end varchar(32) NOT NULL DEFAULT '',
			  retail_closed tinyint(1) NOT NULL DEFAULT 0,
			  preorder_closed tinyint(1) NOT NULL DEFAULT 0,
			  restaurant_closed tinyint(1) NOT NULL DEFAULT 0,
			  PRIMARY KEY  (schedule_id),
			  UNIQUE KEY schedule_id (schedule_id)
		) $collate;" );
		$this->add_column( 'ec_product', 'is_preorder_type', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_product', 'is_restaurant_type', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_tempcart_data', 'pickup_date', "varchar(32) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart_data', 'pickup_asap', "tinyint(1) NOT NULL DEFAULT 1" );
		$this->add_column( 'ec_tempcart_data', 'pickup_time', "varchar(32) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_order', 'includes_preorder_items', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_order', 'includes_restaurant_type', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_order', 'pickup_date', "datetime NULL" );
		$this->add_column( 'ec_order', 'pickup_asap', "tinyint(1) NOT NULL DEFAULT 1" );
		$this->add_column( 'ec_order', 'pickup_time', "datetime NULL" );
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "1",
				"schedule_label" => "Sunday",
				"day_of_week" => "SUN",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "2",
				"schedule_label" => "Monday",
				"day_of_week" => "MON",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "3",
				"schedule_label" => "Tuesday",
				"day_of_week" => "TUE",
				"is_holiday" => "0",

				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "4",
				"schedule_label" => "Wednesday",
				"day_of_week" => "WED",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "5",
				"schedule_label" => "Thursday",
				"day_of_week" => "THU",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "6",
				"schedule_label" => "Friday",
				"day_of_week" => "FRI",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "7",
				"schedule_label" => "Saturday",
				"day_of_week" => "SAT",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);
	}

	private function wpeasycart_sql_5_7_6() {
		global $wpdb;
		$this->add_column( 'ec_orderstatus', 'color_code', "varchar(40) NOT NULL DEFAULT '#FFFFFF'" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_name_1 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_name_2 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_name_3 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_name_4 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_name_5 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_label_1 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_label_2 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_label_3 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_label_4 text NULL" );
		$wpdb->query( "ALTER TABLE ec_orderdetail MODIFY COLUMN optionitem_label_5 text NULL" );
	}

	private function wpeasycart_sql_5_8_1() {
		global $wpdb;
		$collate = "";
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_location (
		  location_id int(11) NOT NULL AUTO_INCREMENT,
		  location_label varchar(255) DEFAULT '',
		  address_line_1 varchar(255) NOT NULL DEFAULT '',
		  address_line_2 varchar(255) NOT NULL DEFAULT '',
		  city varchar(255) NOT NULL DEFAULT '',
		  state varchar(255) NOT NULL DEFAULT '',
		  country varchar(255) NOT NULL DEFAULT '',
		  zip varchar(255) NOT NULL DEFAULT '',
		  phone varchar(255) NOT NULL DEFAULT '',
		  email varchar(255) NOT NULL DEFAULT '',
		  latitude decimal(9,6) DEFAULT NULL,
		  longitude decimal(9,6) DEFAULT NULL,
		  PRIMARY KEY  (location_id),
		  UNIQUE KEY location_id (location_id)
		) $collate;" );
		$this->add_column( 'ec_product', 'pickup_locations', "text NULL" );
	}

	private function wpeasycart_sql_5_8_2() {
		global $wpdb;
		$collate = "";
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_location_to_product (
		  location_product_id int(11) NOT NULL AUTO_INCREMENT,
		  location_id int(11) NOT NULL DEFAULT 0,
		  product_id int(11) NOT NULL DEFAULT 0,
		  PRIMARY KEY  (location_product_id),
		  UNIQUE KEY location_product_id (location_product_id),
		  KEY location_id (location_id),
		  KEY product_id (product_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_location_to_schedule (
		  location_schedule_id int(11) NOT NULL AUTO_INCREMENT,
		  location_id int(11) NOT NULL DEFAULT 0,
		  schedule_id int(11) NOT NULL DEFAULT 0,
		  PRIMARY KEY  (location_schedule_id),
		  UNIQUE KEY location_schedule_id (location_schedule_id),
		  KEY location_id (location_id),
		  KEY product_id (schedule_id)
		) $collate;" );
		$this->add_column( 'ec_order', 'location_id', "int(11) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_tempcart_data', 'pickup_location', "int(11) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_location', 'hours_note', "text NULL" );
	}

	private function wpeasycart_sql_5_8_11() {
		global $wpdb;
		$this->add_column( 'ec_promocode', 'first_order_only', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_user', 'allow_shipping_bypass', "tinyint(1) NOT NULL DEFAULT 0" );
	}

	private function wpeasycart_sql_5_8_12() {
		global $wpdb;
		$this->add_column( 'ec_order', 'converted_cart_id', "varchar(100) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_user', 'is_stripe_test_user', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_product', 'stripe_product_id_sandbox', "varchar(255) DEFAULT ''" );
		$this->add_column( 'ec_product', 'stripe_default_price_id_sandbox', "varchar(255) DEFAULT ''" );
	}

	private function wpeasycart_sql_5_8_13() {
		global $wpdb;
		$this->add_column( 'ec_orderdetail', 'unit_discount_promotion', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_orderdetail', 'unit_discount_coupon', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_orderdetail', 'total_discount_promotion', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_orderdetail', 'total_discount_coupon', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_order', 'promo_code_message', "varchar(1024) NOT NULL DEFAULT ''" );
	}

	private function wpeasycart_sql_5_8_15() {
		global $wpdb;
		// Test for failed DB update in last DB version and correct if missing.
		$column_exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_schema = %s AND table_name = "ec_orderdetail" AND column_name = "unit_discount_promotion"', DB_NAME ) );
		if ( ! $column_exists ) {
			$this->add_column( 'ec_orderdetail', 'unit_discount_promotion', "float(15,3) NOT NULL DEFAULT '0.000'" );
			$this->add_column( 'ec_orderdetail', 'unit_discount_coupon', "float(15,3) NOT NULL DEFAULT '0.000'" );
			$this->add_column( 'ec_orderdetail', 'total_discount_promotion', "float(15,3) NOT NULL DEFAULT '0.000'" );
			$this->add_column( 'ec_orderdetail', 'total_discount_coupon', "float(15,3) NOT NULL DEFAULT '0.000'" );
			$this->add_column( 'ec_order', 'promo_code_message', "varchar(1024) NOT NULL DEFAULT ''" );
		}
	}
	private function wpeasycart_sql_5_8_16() {
		global $wpdb;

		$indexes = array(
			'ec_roleprice' => array( 'idx_product_role' => 'product_id, role_label(100)' ),
			'ec_review' => array( 'idx_product_approved' => 'product_id, approved' ),
			'ec_categoryitem' => array( 'idx_category_product' => 'category_id, product_id' ),
			'ec_product' => array(
				'idx_storefront_default' => 'activate_in_store, role_id, sort_position',
				'idx_post_id' => 'post_id',
			),
		);

		foreach ( $indexes as $table => $defs ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s", $table ) );
			if ( ! $table_exists ) {
				continue;
			}

			foreach ( $defs as $index_name => $columns ) {
				$this->add_index( $table, $index_name, $columns );
			}
		}
	}
	private function wpeasycart_sql_5_9_2() {
		global $wpdb;
		$this->add_column( 'ec_user', 'password_admin_v1', "varchar(32) NOT NULL DEFAULT ''" );
	}
	private function wpeasycart_sql_5_9_4() {
		global $wpdb;
		$collate = "";
		$max_index_length = 191;
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_cart_link (
			  cart_link_id int(11) NOT NULL AUTO_INCREMENT,
			  link_token varchar(16) NOT NULL DEFAULT '',
			  link_label varchar(255) NOT NULL DEFAULT '',
			  promo_codes text,
			  destination varchar(16) NOT NULL DEFAULT 'cart',
			  clear_cart tinyint(1) NOT NULL DEFAULT 0,
			  is_active tinyint(1) NOT NULL DEFAULT 1,
			  expires datetime DEFAULT NULL,
			  max_uses int(11) NOT NULL DEFAULT 0,
			  use_count int(11) NOT NULL DEFAULT 0,
			  created_by bigint(20) NOT NULL DEFAULT 0,
			  created_at datetime DEFAULT NULL,
			  last_used datetime DEFAULT NULL,
			  PRIMARY KEY  (cart_link_id),
			  UNIQUE KEY cart_link_token (link_token)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_cart_link_item (
			  cart_link_item_id int(11) NOT NULL AUTO_INCREMENT,
			  cart_link_id int(11) NOT NULL DEFAULT 0,
			  product_id int(11) NOT NULL DEFAULT 0,
			  quantity int(11) NOT NULL DEFAULT 1,
			  optionitem_id_1 int(11) NOT NULL DEFAULT 0,
			  optionitem_id_2 int(11) NOT NULL DEFAULT 0,
			  optionitem_id_3 int(11) NOT NULL DEFAULT 0,
			  optionitem_id_4 int(11) NOT NULL DEFAULT 0,
			  optionitem_id_5 int(11) NOT NULL DEFAULT 0,
			  modifier_values text,
			  sort_order int(11) NOT NULL DEFAULT 0,
			  PRIMARY KEY  (cart_link_item_id),
			  KEY cart_link_item_link (cart_link_id),
			  KEY cart_link_item_product (product_id)
		) $collate;" );
		$this->add_column( 'ec_order', 'cart_link_id', "int(11) NOT NULL DEFAULT 0" );
		$this->add_index( 'ec_order', 'order_cart_link', 'cart_link_id' );
		$this->add_column( 'ec_product', 'is_bundle', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_tempcart', 'bundle_group_key', "varchar(64) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_tempcart', 'bundle_product_id', "int(11) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_tempcart', 'free_gift_offer_id', "int(11) NOT NULL DEFAULT '0'" );
		$this->add_index( 'ec_tempcart', 'tempcart_bundle_group', 'bundle_group_key' );
		$this->add_column( 'ec_orderdetail', 'bundle_group_key', "varchar(64) NOT NULL DEFAULT ''" );
		$this->add_column( 'ec_orderdetail', 'bundle_product_id', "int(11) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_orderdetail', 'is_free_gift', "tinyint(1) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_orderdetail', 'applied_offers', "longtext" );
		$this->add_column( 'ec_order', 'offer_discount_total', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_order', 'applied_offers', "longtext" );
		$this->add_column( 'ec_user', 'lifetime_spend', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_user', 'completed_order_count', "int(11) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_user', 'last_order_date', "datetime DEFAULT NULL" );
		$this->add_column( 'ec_user', 'history_aggregates_built', "tinyint(1) NOT NULL DEFAULT 0" );
		$this->add_column( 'ec_user', 'date_created', "timestamp NULL DEFAULT CURRENT_TIMESTAMP" );
		/* Backfilling date_created from each customer's first order used to be one correlated UPDATE
		   over the whole table here. It is now queued and drained in 5,000-row chunks by
		   wpeasycart_sql_6_0_0_user_dates() at the end of the chain ( @since 6.0.0 ). */
		$this->queue_batch_job( 'user_dates', array( array( 'table' => 'ec_user', 'pk' => 'user_id', 'cursor' => 0 ) ) );
		$this->add_column( 'ec_user', 'last_login', "datetime DEFAULT NULL" );
		$this->add_index( 'ec_user', 'user_date_created', 'date_created' );
		$this->add_index( 'ec_user', 'user_last_order_date', 'last_order_date' );
		$this->add_index( 'ec_orderdetail', 'idx_order_product', 'order_id,product_id' );
		$this->add_column( 'ec_product', 'reorder_point', "int(11) NOT NULL DEFAULT '-1'" );
		$this->add_column( 'ec_optionitemquantity', 'reorder_point', "int(11) NOT NULL DEFAULT '-1'" );
		$this->add_column( 'ec_orderdetail', 'refunded_quantity', "int(11) NOT NULL DEFAULT '0'" );
		$this->add_column( 'ec_order', 'shipping_refund_total', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$this->add_column( 'ec_order', 'tax_refund_total', "float(15,3) NOT NULL DEFAULT '0.000'" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer (
		  offer_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_name varchar(255) NOT NULL DEFAULT '',
		  offer_label varchar(255) NOT NULL DEFAULT '',
		  offer_description text,
		  offer_status varchar(20) NOT NULL DEFAULT 'draft',
		  trigger_type varchar(20) NOT NULL DEFAULT 'code',
		  action_type varchar(40) NOT NULL DEFAULT 'item_discount',
		  action_config longtext,
		  start_date datetime DEFAULT NULL,
		  end_date datetime DEFAULT NULL,
		  schedule_config longtext,
		  is_exclusive tinyint(1) NOT NULL DEFAULT '0',
		  combine_item_discounts tinyint(1) NOT NULL DEFAULT '0',
		  combine_cart_discounts tinyint(1) NOT NULL DEFAULT '0',
		  combine_shipping_discounts tinyint(1) NOT NULL DEFAULT '1',
		  priority int(11) NOT NULL DEFAULT '10',
		  apply_limit int(11) NOT NULL DEFAULT '0',
		  max_discount_amount float(15,3) NOT NULL DEFAULT '0.000',
		  min_item_price_floor float(15,3) NOT NULL DEFAULT '0.000',
		  max_redemptions int(11) NOT NULL DEFAULT '0',
		  times_redeemed int(11) NOT NULL DEFAULT '0',
		  max_redemptions_per_customer int(11) NOT NULL DEFAULT '0',
		  max_redemptions_per_day int(11) NOT NULL DEFAULT '0',
		  applies_to_subscriptions tinyint(1) NOT NULL DEFAULT '0',
		  applies_to_sale_items tinyint(1) NOT NULL DEFAULT '1',
		  discount_base varchar(20) NOT NULL DEFAULT 'unit_price',
		  include_modifier_prices tinyint(1) NOT NULL DEFAULT '0',
		  display_config longtext,
		  legacy_promocode_id varchar($max_index_length) NOT NULL DEFAULT '',
		  legacy_promotion_id int(11) NOT NULL DEFAULT '0',
		  created_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		  modified_date timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  PRIMARY KEY  (offer_id),
		  UNIQUE KEY offer_offer_id (offer_id),
		  KEY offer_status_trigger (offer_status,trigger_type),
		  KEY offer_dates (start_date,end_date),
		  KEY offer_legacy_promotion (legacy_promotion_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer_code (
		  offer_code_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_id int(11) NOT NULL DEFAULT '0',
		  code varchar($max_index_length) NOT NULL DEFAULT '',
		  max_redemptions int(11) NOT NULL DEFAULT '0',
		  times_redeemed int(11) NOT NULL DEFAULT '0',
		  assigned_email varchar(255) NOT NULL DEFAULT '',
		  is_active tinyint(1) NOT NULL DEFAULT '1',
		  created_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (offer_code_id),
		  UNIQUE KEY offer_code_code (code),
		  KEY offer_code_offer_id (offer_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer_target (
		  offer_target_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_id int(11) NOT NULL DEFAULT '0',
		  target_side varchar(10) NOT NULL DEFAULT 'both',
		  target_mode varchar(10) NOT NULL DEFAULT 'include',
		  entity_type varchar(20) NOT NULL DEFAULT 'all',
		  entity_id int(11) NOT NULL DEFAULT '0',
		  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
		  entity_config longtext,
		  PRIMARY KEY  (offer_target_id),
		  KEY offer_target_offer_id (offer_id),
		  KEY offer_target_entity (entity_type,entity_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer_condition (
		  offer_condition_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_id int(11) NOT NULL DEFAULT '0',
		  condition_group int(11) NOT NULL DEFAULT '1',
		  condition_type varchar(40) NOT NULL DEFAULT '',
		  condition_value longtext,
		  PRIMARY KEY  (offer_condition_id),
		  KEY offer_condition_offer_id (offer_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer_exclusion (
		  offer_exclusion_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_id int(11) NOT NULL DEFAULT '0',
		  excluded_offer_id int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (offer_exclusion_id),
		  KEY offer_exclusion_offer_id (offer_id),
		  KEY offer_exclusion_excluded_id (excluded_offer_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_offer_redemption (
		  offer_redemption_id int(11) NOT NULL AUTO_INCREMENT,
		  offer_id int(11) NOT NULL DEFAULT '0',
		  offer_code_id int(11) NOT NULL DEFAULT '0',
		  code varchar($max_index_length) NOT NULL DEFAULT '',
		  order_id int(11) NOT NULL DEFAULT '0',
		  user_id int(11) NOT NULL DEFAULT '0',
		  email varchar(255) NOT NULL DEFAULT '',
		  discount_amount float(15,3) NOT NULL DEFAULT '0.000',
		  redemption_status varchar(20) NOT NULL DEFAULT 'completed',
		  redemption_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (offer_redemption_id),
		  KEY offer_redemption_offer_id (offer_id),
		  KEY offer_redemption_order_id (order_id),
		  KEY offer_redemption_user_id (user_id),
		  KEY offer_redemption_offer_email (offer_id,email(100))
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_product_bundle (
		  product_bundle_id int(11) NOT NULL AUTO_INCREMENT,
		  product_id int(11) NOT NULL DEFAULT '0',
		  pricing_mode varchar(20) NOT NULL DEFAULT 'fixed_price',
		  discount_amount float(15,3) NOT NULL DEFAULT '0.000',
		  discount_percentage float(15,3) NOT NULL DEFAULT '0.000',
		  display_mode varchar(20) NOT NULL DEFAULT 'single_line',
		  allow_component_edit tinyint(1) NOT NULL DEFAULT '0',
		  stock_mode varchar(20) NOT NULL DEFAULT 'component',
		  PRIMARY KEY  (product_bundle_id),
		  UNIQUE KEY product_bundle_product_id (product_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_product_bundle_item (
		  product_bundle_item_id int(11) NOT NULL AUTO_INCREMENT,
		  product_bundle_id int(11) NOT NULL DEFAULT '0',
		  component_product_id int(11) NOT NULL DEFAULT '0',
		  quantity int(11) NOT NULL DEFAULT '1',
		  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
		  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
		  customer_selects_options tinyint(1) NOT NULL DEFAULT '0',
		  price_allocation float(15,3) NOT NULL DEFAULT '0.000',
		  sort_order int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (product_bundle_item_id),
		  KEY product_bundle_item_bundle_id (product_bundle_id),
		  KEY product_bundle_item_component (component_product_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_tempcart_offer (
		  tempcart_offer_id int(11) NOT NULL AUTO_INCREMENT,
		  session_id varchar(100) NOT NULL DEFAULT '',
		  offer_id int(11) NOT NULL DEFAULT '0',
		  offer_code_id int(11) NOT NULL DEFAULT '0',
		  code varchar($max_index_length) NOT NULL DEFAULT '',
		  applied_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (tempcart_offer_id),
		  KEY tempcart_offer_session_id (session_id),
		  KEY tempcart_offer_offer_id (offer_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_order_tag (
		  tag_id int(11) NOT NULL AUTO_INCREMENT,
		  tag_label varchar(100) NOT NULL DEFAULT '',
		  tag_color varchar(20) NOT NULL DEFAULT '#6a737d',
		  PRIMARY KEY  (tag_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_order_tag_item (
		  order_id int(11) NOT NULL,
		  tag_id int(11) NOT NULL,
		  KEY order_id (order_id),
		  KEY tag_id (tag_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_user_tag (
		  tag_id int(11) NOT NULL AUTO_INCREMENT,
		  tag_label varchar(100) NOT NULL DEFAULT '',
		  tag_color varchar(7) NOT NULL DEFAULT '#6b7280',
		  PRIMARY KEY  (tag_id),
		  UNIQUE KEY user_tag_label (tag_label)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_user_to_tag (
		  user_to_tag_id int(11) NOT NULL AUTO_INCREMENT,
		  user_id int(11) NOT NULL DEFAULT '0',
		  tag_id int(11) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (user_to_tag_id),
		  UNIQUE KEY user_to_tag_pair (user_id,tag_id),
		  KEY user_to_tag_tag (tag_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_user_note (
		  note_id int(11) NOT NULL AUTO_INCREMENT,
		  user_id int(11) NOT NULL DEFAULT '0',
		  wp_user_id int(11) NOT NULL DEFAULT '0',
		  note text,
		  created datetime DEFAULT NULL,
		  pinned tinyint(1) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (note_id),
		  KEY user_note_user (user_id)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_user_activity (
		  activity_id bigint(20) NOT NULL AUTO_INCREMENT,
		  user_id int(11) NOT NULL DEFAULT '0',
		  activity_type varchar(50) NOT NULL DEFAULT '',
		  activity_date datetime DEFAULT NULL,
		  object_type varchar(50) NOT NULL DEFAULT '',
		  object_id bigint(20) NOT NULL DEFAULT '0',
		  meta text,
		  actor_type varchar(20) NOT NULL DEFAULT '',
		  actor_id bigint(20) NOT NULL DEFAULT '0',
		  ip_address varchar(45) NOT NULL DEFAULT '',
		  PRIMARY KEY  (activity_id),
		  KEY user_activity_user_date (user_id,activity_date),
		  KEY user_activity_type (activity_type)
		) $collate;" );
		$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_inventory_log (
			log_id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id int(11) NOT NULL DEFAULT '0',
			optionitemquantity_id int(11) NOT NULL DEFAULT '0',
			location_id int(11) NOT NULL DEFAULT '0',
			delta int(11) NOT NULL DEFAULT '0',
			new_quantity int(11) NOT NULL DEFAULT '0',
			reason varchar(40) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL DEFAULT '',
			note text NULL,
			user_id bigint(20) NOT NULL DEFAULT '0',
			created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (log_id),
			KEY product_id (product_id),
			KEY optionitemquantity_id (optionitemquantity_id),
			KEY created (created)
		) $collate;" );
		update_option( 'ec_option_db_5_9_4_tables_complete', '1' );
		$this->run_post_sync_audit();
	}

	private function wpeasycart_sql_6_0_0() {
		global $wpdb;
		if ( ! get_option( 'ec_option_db_5_9_4_tables_complete' ) ) {
			$this->wpeasycart_sql_5_9_4();
		}
		// Converge these TEXT columns to `text NULL` on every database type. Older versions /
		// repairs left them as `text NOT NULL DEFAULT ''` on MariaDB (which accepts TEXT
		// defaults; MySQL does not). A bare DROP DEFAULT is NOT safe here: it would leave
		// `text NOT NULL` with no default on MariaDB, and MariaDB 10.2.4+ runs
		// STRICT_TRANS_TABLES by default, so any INSERT that omits the column would then
		// fail with error 1364. MODIFY ... text NULL matches get_schema() on both engines,
		// and dbDelta never fixes nullability on its own, so this is the only place the
		// two engine states converge.
		$text_columns = array(
			'ec_download' => array( 'download_file_name' ),
			'ec_location' => array( 'hours_note' ),
			'ec_optionitemimage' => array( 'image1', 'image2', 'image3', 'image4', 'image5' ),
			'ec_orderdetail' => array( 'optionitem_name_1', 'optionitem_name_2', 'optionitem_name_3', 'optionitem_name_4', 'optionitem_name_5', 'optionitem_label_1', 'optionitem_label_2', 'optionitem_label_3', 'optionitem_label_4', 'optionitem_label_5', 'download_file_name', 'download_key' ),
			'ec_product' => array( 'download_file_name', 'image1', 'image2', 'image3', 'image4', 'image5' ),
			'ec_subscriber' => array( 'email' ),
			'ec_subscription' => array( 'title', 'email' ),
			'ec_tempcart_optionitem' => array( 'optionitem_model_number' ),
		);
		foreach ( $text_columns as $table => $columns ) {
			if ( ! $this->table_exists( $table ) ) {
				continue;
			}
			foreach ( $columns as $column ) {
				$col = $wpdb->get_row( $wpdb->prepare( "SHOW FULL COLUMNS FROM `$table` WHERE Field = %s" , $column ) );
				if ( ! $col ) {
					continue;
				}
				if ( 'NO' === $col->Null || null !== $col->Default ) {
					$wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN `$column` text NULL" );
					$alter_error = $wpdb->last_error; // the SHOW below resets $wpdb->last_error
					$col = $wpdb->get_row( $wpdb->prepare( "SHOW FULL COLUMNS FROM `$table` WHERE Field = %s" , $column ) );
					if ( $col && ( 'NO' === $col->Null || null !== $col->Default ) ) {
						$this->record_update_failure( '6.0.0: could not relax ' . $table . '.' . $column . ' to text NULL: ' . $alter_error );
					}
				}
			}
		}
		// Relax event-specific datetimes. NOT NULL without a default breaks any insert that omits
		// the column on a strict-mode connection, and the old zero-date defaults are invalid on
		// stock MySQL 5.7+. These columns are only set for certain product/order types, so allow NULL.
		$datetime_columns = array(
			'ec_order' => array( 'pk' => 'order_id', 'columns' => array( 'last_updated', 'pickup_date', 'pickup_time' ) ),
			'ec_product' => array( 'pk' => 'product_id', 'columns' => array( 'last_viewed' ) ),
			'ec_promotion' => array( 'pk' => 'promotion_id', 'columns' => array( 'start_date', 'end_date' ) ),
		);
		/* Hosts that re-enforce STRICT_TRANS_TABLES / NO_ZERO_DATE on the session reject a MODIFY
		   while rows still hold '0000-00-00 00:00:00'. Relax the session for this block only;
		   set_sql_mode() with no arguments restores WP's sanitized mode. */
		$wpdb->query( "SET SESSION sql_mode = ''" );
		$zero_date_jobs = array();
		foreach ( $datetime_columns as $table => $def ) {
			if ( ! $this->table_exists( $table ) ) {
				continue;
			}
			foreach ( $def['columns'] as $column ) {
				$col = $wpdb->get_row( $wpdb->prepare( "SHOW FULL COLUMNS FROM `$table` WHERE Field = %s", $column ) );
				if ( ! $col ) {
					continue;
				}
				if ( 'NO' === $col->Null || null !== $col->Default ) {
					$wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN `$column` datetime NULL" );
					$alter_error = $wpdb->last_error; // the SHOW below resets $wpdb->last_error
					$col = $wpdb->get_row( $wpdb->prepare( "SHOW FULL COLUMNS FROM `$table` WHERE Field = %s", $column ) );
					if ( $col && ( 'NO' === $col->Null || null !== $col->Default ) ) {
						$this->record_update_failure( '6.0.0: could not relax ' . $table . '.' . $column . ' to datetime NULL: ' . $alter_error );
						continue;
					}
				}
				/* Normalizing legacy zero-dates to NULL used to be one unbounded UPDATE per column here.
				   It is now queued and drained by primary-key range in wpeasycart_sql_6_0_0_zero_dates(). */
				$zero_date_jobs[] = array( 'table' => $table, 'pk' => $def['pk'], 'column' => $column, 'cursor' => 0 );
			}
		}
		$wpdb->set_sql_mode();
		$this->queue_batch_job( 'zero_dates', $zero_date_jobs );
		$this->normalize_indexes();
		// Force install_db() (full dbDelta with the corrected schema) to run again on next load.
		delete_option( 'ec_option_db_new_version' );
		delete_transient( 'ec_db_install_backoff' );
	}

	private function wpeasycart_sql_6_0_0_catalog() {
		$cols = array(
			array( 'ec_category', 'smart_mode', "tinyint(1) NOT NULL DEFAULT '0'" ),
			array( 'ec_category', 'smart_rules', 'text' ),
			array( 'ec_categoryitem', 'is_smart', "tinyint(1) NOT NULL DEFAULT '0'" ),
		);
		foreach ( $cols as $c ) {
			if ( ! $this->add_column( $c[0], $c[1], $c[2] ) || ( $this->table_exists( $c[0] ) && ! $this->column_exists( $c[0], $c[1] ) ) ) {
				$this->record_update_failure( '6.0.0 catalog: could not add ' . $c[0] . '.' . $c[1] );
			}
		}
	}

	/**
	 * Flex-Fees: fee_basis chooses what a percentage fee is calculated on.
	 * 'subtotal' (default, legacy behavior) or 'order_total' (subtotal + shipping + tax - discounts).
	 * install_db() / dbDelta adds the column from get_schema() as well (EC_UPGRADE_DB 104); this keeps
	 * the version-script path in step for stores whose dbDelta pass has not run yet.
	 */
	/**
	 * 6.0.0 merges the two low stock numbers into one.
	 *
	 * ec_option_low_stock_trigger_total ( Settings > Checkout > Stock alerts ) predates
	 * 6.0.0 and stays canonical, so a merchant's long-standing value survives.
	 * ec_option_inventory_low_stock_threshold was added for the Inventory screen during
	 * 6.0.0 development: its row only exists if someone saved that control, so when it
	 * does and the canonical key was never moved off its shipped default, the merchant's
	 * number is carried across. If both were set and they differ, the canonical one wins
	 * and the discarded number is recorded so it can be checked.
	 *
	 * Runs once: its own flag option, not the version chain, since 6.0.0 upgrade steps
	 * can repeat.
	 */
	private function wpeasycart_sql_6_0_0_stock() {
		if ( get_option( 'ec_option_db_6_0_0_stock_threshold_merged' ) ) {
			return;
		}
		update_option( 'ec_option_db_6_0_0_stock_threshold_merged', '1' );

		$legacy = get_option( 'ec_option_inventory_low_stock_threshold', false );
		if ( false === $legacy || '' === $legacy ) {
			return;
		}
		$legacy = (int) $legacy;
		if ( $legacy < 1 ) {
			return;
		}

		$canonical = get_option( 'ec_option_low_stock_trigger_total', '' );
		/* 5 is the default every pre-6.0.0 store shipped with; a fresh 6.0.0 install has no legacy row to migrate. */
		$untouched = ( '' === $canonical || false === $canonical || null === $canonical || 5 === (int) $canonical );

		if ( $untouched ) {
			if ( (int) $canonical !== $legacy ) {
				update_option( 'ec_option_low_stock_trigger_total', (string) $legacy );
			}
			return;
		}

		if ( (int) $canonical !== $legacy ) {
			update_option( 'ec_option_low_stock_threshold_merge_note', sprintf( 'Kept the Stock alerts threshold of %1$d; the Inventory screen was set to %2$d.', (int) $canonical, $legacy ) );
		}
	}

	private function wpeasycart_sql_6_0_0_fees() {
		if ( ! $this->add_column( 'ec_fee', 'fee_basis', "varchar(32) NOT NULL DEFAULT 'subtotal'" ) || ( $this->table_exists( 'ec_fee' ) && ! $this->column_exists( 'ec_fee', 'fee_basis' ) ) ) {
			$this->record_update_failure( '6.0.0 fees: could not add ec_fee.fee_basis' );
		}
	}

	/**
	 * Live rate cache: an index on ec_cart_id ( every cart update reads and replaces a cart's saved quote ) and a
	 * created date so PRO can delete quotes for carts that no longer exist. dbDelta adds both from get_schema()
	 * as well ( EC_UPGRADE_DB 105 ).
	 *
	 * `timestamp NULL DEFAULT CURRENT_TIMESTAMP` ( the same shape as ec_order_log.order_log_timestamp and
	 * ec_user.date_created ): MySQL before 5.6.5 only accepts DEFAULT CURRENT_TIMESTAMP on timestamp columns.
	 */
	private function wpeasycart_sql_6_0_0_live_rates() {
		if ( ! $this->table_exists( 'ec_live_rate_cache' ) ) {
			return;
		}
		if ( ! $this->add_column( 'ec_live_rate_cache', 'created', 'timestamp NULL DEFAULT CURRENT_TIMESTAMP' ) || ! $this->column_exists( 'ec_live_rate_cache', 'created' ) ) {
			$this->record_update_failure( '6.0.0 live rates: could not add ec_live_rate_cache.created' );
		}
		if ( ! $this->add_index( 'ec_live_rate_cache', 'ec_cart_id', 'ec_cart_id(191)' ) || ! $this->index_exists( 'ec_live_rate_cache', 'ec_cart_id' ) ) {
			$this->record_update_failure( '6.0.0 live rates: could not index ec_live_rate_cache.ec_cart_id' );
		}
		if ( ! $this->add_index( 'ec_live_rate_cache', 'created', 'created' ) || ! $this->index_exists( 'ec_live_rate_cache', 'created' ) ) {
			$this->record_update_failure( '6.0.0 live rates: could not index ec_live_rate_cache.created' );
		}
	}

	private function wpeasycart_sql_6_0_0_reviews() {
		global $wpdb;
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		$cols = array(
			array( 'reply_text', 'text' ),
			array( 'reply_date', 'datetime DEFAULT NULL' ),
			array( 'reply_user_id', "int(11) NOT NULL DEFAULT '0'" ),
			array( 'verified', "tinyint(1) NOT NULL DEFAULT '0'" ),
			array( 'request_id', "int(11) NOT NULL DEFAULT '0'" ),
			array( 'reviewer_email', "varchar(255) NOT NULL DEFAULT ''" ),
			array( 'held_reason', "varchar(255) NOT NULL DEFAULT ''" ),
		);
		foreach ( $cols as $c ) {
			if ( ! $this->add_column( 'ec_review', $c[0], $c[1] ) || ! $this->column_exists( 'ec_review', $c[0] ) ) {
				$this->record_update_failure( '6.0.0 reviews: could not add ec_review.' . $c[0] );
			}
		}
		if ( ! $this->table_exists( 'ec_review_request' ) ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_review_request (
			request_id int(11) NOT NULL AUTO_INCREMENT,
			order_id int(11) NOT NULL DEFAULT '0',
			product_id int(11) NOT NULL DEFAULT '0',
			user_id int(11) NOT NULL DEFAULT '0',
			email varchar(255) NOT NULL DEFAULT '',
			first_name varchar(255) NOT NULL DEFAULT '',
			token varchar(64) NOT NULL DEFAULT '',
			scheduled_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			reminded_at datetime DEFAULT NULL,
			opened_at datetime DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_id int(11) NOT NULL DEFAULT '0',
			unsubscribed tinyint(1) NOT NULL DEFAULT '0',
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (request_id),
			UNIQUE KEY token (token),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY email_product (email(100), product_id),
			KEY scheduled_at (scheduled_at)
		) $collate;" );
			if ( ! $this->table_exists( 'ec_review_request' ) ) {
				$this->record_update_failure( '6.0.0 reviews: could not create ec_review_request: ' . $wpdb->last_error );
			}
		}
	}

	private function wpeasycart_sql_6_0_0_email() {
		global $wpdb;
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		if ( ! $this->table_exists( 'ec_email_log' ) ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_email_log (
			log_id int(11) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT NULL,
			email_type varchar(40) NOT NULL DEFAULT '',
			order_id int(11) NOT NULL DEFAULT '0',
			to_email varchar(255) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			transport varchar(30) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			error_text text,
			attempts int(11) NOT NULL DEFAULT '1',
			queue_id int(11) NOT NULL DEFAULT '0',
			body_hash varchar(40) NOT NULL DEFAULT '',
			PRIMARY KEY  (log_id),
			KEY created_at (created_at),
			KEY order_id (order_id),
			KEY status (status),
			KEY to_email (to_email(100))
		) $collate;" );
			if ( ! $this->table_exists( 'ec_email_log' ) ) {
				$this->record_update_failure( '6.0.0 email: could not create ec_email_log: ' . $wpdb->last_error );
			}
		}
		if ( ! $this->table_exists( 'ec_email_queue' ) ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_email_queue (
			queue_id int(11) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT NULL,
			email_type varchar(40) NOT NULL DEFAULT '',
			order_id int(11) NOT NULL DEFAULT '0',
			channel varchar(20) NOT NULL DEFAULT 'order',
			to_email varchar(255) NOT NULL DEFAULT '',
			subject varchar(255) NOT NULL DEFAULT '',
			message longtext,
			headers text,
			attempts int(11) NOT NULL DEFAULT '0',
			next_attempt datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			last_error text,
			PRIMARY KEY  (queue_id),
			KEY status_next (status, next_attempt),
			KEY order_id (order_id)
		) $collate;" );
			if ( ! $this->table_exists( 'ec_email_queue' ) ) {
				$this->record_update_failure( '6.0.0 email: could not create ec_email_queue: ' . $wpdb->last_error );
			}
		}
	}

	private function wpeasycart_sql_6_0_0_abandoned() {
		global $wpdb;
		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		if ( ! $this->table_exists( 'ec_abandoned_cart' ) ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_abandoned_cart ( abandoned_cart_id int(11) NOT NULL AUTO_INCREMENT, session_id varchar(191) NOT NULL DEFAULT '', email varchar(255) NOT NULL DEFAULT '', first_name varchar(255) NOT NULL DEFAULT '', last_name varchar(255) NOT NULL DEFAULT '', user_id int(11) NOT NULL DEFAULT '0', phone varchar(64) NOT NULL DEFAULT '', status varchar(20) NOT NULL DEFAULT 'active', stage varchar(20) NOT NULL DEFAULT 'cart', items_json longtext, item_count int(11) NOT NULL DEFAULT '0', subtotal decimal(12,2) NOT NULL DEFAULT '0.00', currency varchar(8) NOT NULL DEFAULT '', coupon_code varchar(64) NOT NULL DEFAULT '', shipping_country varchar(8) NOT NULL DEFAULT '', locale varchar(16) NOT NULL DEFAULT '', card_error varchar(255) NOT NULL DEFAULT '', first_seen datetime DEFAULT NULL, last_activity datetime DEFAULT NULL, abandoned_at datetime DEFAULT NULL, recovered_at datetime DEFAULT NULL, recovered_order_id int(11) NOT NULL DEFAULT '0', recovered_total decimal(12,2) NOT NULL DEFAULT '0.00', sequence_step int(11) NOT NULL DEFAULT '0', next_send_at datetime DEFAULT NULL, offer_code_id int(11) NOT NULL DEFAULT '0', offer_code varchar(64) NOT NULL DEFAULT '', offer_code_expires datetime DEFAULT NULL, admin_notes text, PRIMARY KEY  (abandoned_cart_id), UNIQUE KEY session_id (session_id), KEY status_next (status, next_send_at), KEY email (email(100)), KEY last_activity (last_activity), KEY first_seen (first_seen) ) $collate;" );
			if ( ! $this->table_exists( 'ec_abandoned_cart' ) ) { $this->record_update_failure( '6.0.0 abandoned: could not create ec_abandoned_cart: ' . $wpdb->last_error ); }
		}
		if ( ! $this->table_exists( 'ec_abandoned_cart_event' ) ) {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS ec_abandoned_cart_event ( event_id int(11) NOT NULL AUTO_INCREMENT, abandoned_cart_id int(11) NOT NULL DEFAULT '0', type varchar(24) NOT NULL DEFAULT '', step int(11) NOT NULL DEFAULT '0', detail text, created_at datetime DEFAULT NULL, PRIMARY KEY  (event_id), KEY cart_type (abandoned_cart_id, type), KEY created_at (created_at) ) $collate;" );
			if ( ! $this->table_exists( 'ec_abandoned_cart_event' ) ) { $this->record_update_failure( '6.0.0 abandoned: could not create ec_abandoned_cart_event: ' . $wpdb->last_error ); }
		}
	}

	/**
	 * Converge index definitions that dbDelta cannot change on its own. Idempotent and cheap
	 * (information_schema lookups only), so it is safe to run before every dbDelta pass.
	 */
	public function normalize_indexes() {
		global $wpdb;
		// ec_product.model_number: a UNIQUE prefix index rejects a second product with an empty / shared model number.
		if ( $this->table_exists( 'ec_product' ) && $this->index_is_unique( 'ec_product', 'product_model_number' ) ) {
			$wpdb->query( "ALTER TABLE ec_product DROP INDEX product_model_number" );
			$this->add_index( 'ec_product', 'product_model_number', 'model_number(191)' );
		}
		// ec_roleprice.idx_product_role: 4 + 191*4 = 768 bytes exceeds the 767 byte limit on InnoDB COMPACT rows.
		if ( $this->table_exists( 'ec_roleprice' ) ) {
			$sub_part = $wpdb->get_var( $wpdb->prepare( "SELECT sub_part FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s AND column_name = %s", 'ec_roleprice', 'idx_product_role', 'role_label' ) );
			if ( null !== $sub_part && (int) $sub_part !== 100 ) {
				$wpdb->query( "ALTER TABLE ec_roleprice DROP INDEX idx_product_role" );
			}
			if ( ! $this->index_exists( 'ec_roleprice', 'idx_product_role' ) ) {
				$this->add_index( 'ec_roleprice', 'idx_product_role', 'product_id, role_label(100)' );
			}
		}
	}

	/**
	 * Idempotent schema helpers for upgrade scripts. install_db() runs dbDelta on plugins_loaded
	 * and try_db_update() runs on init, so by the time a version script executes, dbDelta has
	 * usually already added the column / index. Plain ALTER ... ADD then fails with
	 * "Duplicate column name" / "Duplicate key name"; these helpers check first so an upgrade
	 * never logs errors regardless of which hook reaches the database first.
	 */
	public function column_exists( $table, $column ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = %s AND column_name = %s", $table, $column ) );
	}

	public function add_column( $table, $column, $definition ) {
		global $wpdb;
		if ( ! $this->table_exists( $table ) || $this->column_exists( $table, $column ) ) {
			return true;
		}
		return false !== $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `$column` " . $this->sanitize_column_sql( $definition ) );
	}

	public function add_index( $table, $index, $columns ) {
		global $wpdb;
		if ( ! $this->table_exists( $table ) || $this->index_exists( $table, $index ) ) {
			return true;
		}
		return false !== $wpdb->query( "ALTER TABLE `$table` ADD INDEX `$index` ($columns)" );
	}

	public function rename_column( $table, $from, $to, $definition ) {
		global $wpdb;
		if ( ! $this->table_exists( $table ) || ! $this->column_exists( $table, $from ) || $this->column_exists( $table, $to ) ) {
			return true;
		}
		return false !== $wpdb->query( "ALTER TABLE `$table` CHANGE `$from` `$to` $definition" );
	}

	private function record_update_failure( $message ) {
		$this->update_failed = true;
		$this->update_errors[] = $message;
		//error_log( 'WP EasyCart DB update ' . $message );
	}

	private function index_exists( $table, $index ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s", $table, $index ) );
	}

	private function index_is_unique( $table, $index ) {
		global $wpdb;
		$non_unique = $wpdb->get_var( $wpdb->prepare( "SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s LIMIT 1", $table, $index ) );
		return null !== $non_unique && '0' === (string) $non_unique;
	}

	/**
	 * Hand the post-sync audit to WP-Cron instead of running it inline.
	 *
	 * audit( true ) walks every product / category row and calls get_post() per row, so on a large
	 * catalog it cannot finish inside the admin request that runs the version chain, and a timeout
	 * here would have re-run the whole 5.9.4 step forever. wpeasycart.php handles the
	 * 'wp_easycart_post_sync_audit' event ( @since 6.0.0 ).
	 */
	private function run_post_sync_audit() {
		if ( ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( 'wp_easycart_post_sync_audit' ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wp_easycart_post_sync_audit' );
		}
	}

	/**
	 * Indexes for the admin's hot queries: order list filters / sorts, the response log,
	 * abandoned-cart sweeps, the catalog price sort and the stock-adjustment lookup.
	 * One ALTER per index so a failure on one does not block the rest; existing indexes are
	 * skipped. Prefix ( 191 ) on the varchar(255) columns keeps utf8mb4 under the 767-byte
	 * limit on MySQL 5.5 / 5.6. dbDelta adds the same keys from get_schema() ( EC_UPGRADE_DB 106 ).
	 *
	 * Returns false when the request's time budget runs out between indexes so the chain
	 * resumes here on the next admin/cron request.
	 *
	 * @since 6.0.0
	 */
	private function wpeasycart_sql_6_0_0_indexes() {
		global $wpdb;
		$indexes = array(
			array( 'ec_order', 'order_order_date', 'order_date' ),
			array( 'ec_order', 'order_orderstatus_id', 'orderstatus_id' ),
			array( 'ec_order', 'order_order_viewed', 'order_viewed' ),
			array( 'ec_order', 'order_user_email', 'user_email(191)' ),
			array( 'ec_order', 'order_shipping_country', 'shipping_country(191)' ),
			array( 'ec_order', 'order_billing_country', 'billing_country(191)' ),
			array( 'ec_response', 'response_response_time', 'response_time' ),
			array( 'ec_response', 'response_error_time', 'is_error, response_time' ),
			array( 'ec_response', 'response_processor', 'processor(191)' ),
			array( 'ec_tempcart', 'tempcart_last_changed_date', 'last_changed_date' ),
			array( 'ec_product', 'product_active_price', 'activate_in_store, price' ),
			array( 'ec_orderdetail', 'orderdetail_product_stock', 'product_id, stock_adjusted' ),
		);
		foreach ( $indexes as $i => $def ) {
			list( $table, $index, $columns ) = $def;
			if ( ! $this->table_exists( $table ) || $this->index_exists( $table, $index ) ) {
				continue;
			}
			$added = $this->add_index( $table, $index, $columns );
			if ( ! $added || ! $this->index_exists( $table, $index ) ) {
				$this->record_update_failure( '6.0.0 indexes: could not add ' . $table . '.' . $index . ' (' . $columns . '): ' . $wpdb->last_error );
			}
			if ( $i < count( $indexes ) - 1 && $this->out_of_time() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Drain the ec_user.date_created backfill queued by wpeasycart_sql_5_9_4(): each customer's
	 * date_created becomes the date of their first order ( NULL when they have none ), exactly
	 * what the old single UPDATE produced, but 5,000 users per statement and resumable.
	 *
	 * @since 6.0.0
	 */
	private function wpeasycart_sql_6_0_0_user_dates() {
		return $this->drain_batch_job( 'user_dates', 'date_created = ( SELECT MIN( o.order_date ) FROM ec_order o WHERE o.user_id = ec_user.user_id )', '' );
	}

	/**
	 * Drain the zero-date normalisations queued by wpeasycart_sql_6_0_0(): every
	 * '0000-00-00 00:00:00' in the relaxed datetime columns becomes NULL, by primary-key range.
	 *
	 * @since 6.0.0
	 */
	private function wpeasycart_sql_6_0_0_zero_dates() {
		global $wpdb;
		/* NO_ZERO_DATE hosts reject the zero-date literal in the WHERE; relax the session for the batch only. */
		$wpdb->query( "SET SESSION sql_mode = ''" );
		$done = $this->drain_batch_job( 'zero_dates', '`{column}` = NULL', "`{column}` = '0000-00-00 00:00:00'" );
		$wpdb->set_sql_mode();
		return $done;
	}

	/**
	 * Queue a set of row-rewrite jobs for a later batched step. Each job is
	 * array( 'table', 'pk', 'cursor' [, 'column' ] ). Re-queuing a job for a table/column that is
	 * already pending keeps the pending cursor so a re-run of the queuing step does not restart it.
	 *
	 * @since 6.0.0
	 */
	private function queue_batch_job( $job_key, array $jobs ) {
		if ( ! count( $jobs ) ) {
			return;
		}
		$option = 'ec_option_db_batch_' . $job_key;
		$pending = get_option( $option );
		$pending = is_array( $pending ) ? $pending : array();
		foreach ( $jobs as $job ) {
			$id = $job['table'] . '.' . ( isset( $job['column'] ) ? $job['column'] : '*' );
			if ( isset( $pending[ $id ] ) && isset( $pending[ $id ]['cursor'] ) ) {
				continue;
			}
			$pending[ $id ] = $job;
		}
		update_option( $option, $pending, false );
	}

	/**
	 * Run the queued jobs for $job_key until they are finished or the request's time budget is
	 * spent. `{column}` in $set_sql / $where_sql is replaced with the job's column. Returns true
	 * when nothing is left ( the queue option is removed ), false when the chain should come back.
	 *
	 * @since 6.0.0
	 */
	private function drain_batch_job( $job_key, $set_sql, $where_sql ) {
		$option = 'ec_option_db_batch_' . $job_key;
		$pending = get_option( $option );
		if ( ! is_array( $pending ) || ! count( $pending ) ) {
			delete_option( $option );
			return true;
		}
		foreach ( $pending as $id => $job ) {
			if ( empty( $job['table'] ) || empty( $job['pk'] ) || ! $this->table_exists( $job['table'] ) ) {
				unset( $pending[ $id ] );
				continue;
			}
			$column = isset( $job['column'] ) ? $job['column'] : '';
			if ( '' !== $column && ! $this->column_exists( $job['table'], $column ) ) {
				unset( $pending[ $id ] );
				continue;
			}
			$cursor = isset( $job['cursor'] ) ? (int) $job['cursor'] : 0;
			/* Persist the cursor after every chunk so a hard PHP timeout resumes at the last chunk,
			   not at the last soft pause. One option write per UPDATE_BATCH_SIZE rows. */
			$persist = function ( $position ) use ( &$pending, $id, $option ) {
				$pending[ $id ]['cursor'] = (int) $position;
				update_option( $option, $pending, false );
			};
			$finished = $this->batch_update_by_pk(
				$job['table'],
				$job['pk'],
				str_replace( '{column}', $column, $set_sql ),
				str_replace( '{column}', $column, $where_sql ),
				$cursor,
				$persist
			);
			if ( $finished ) {
				unset( $pending[ $id ] );
				update_option( $option, $pending, false );
				continue;
			}
			return false;
		}
		delete_option( $option );
		return true;
	}

	/**
	 * UPDATE `$table` SET $set_sql [WHERE $where_sql] in UPDATE_BATCH_SIZE-row primary-key ranges,
	 * starting after $cursor. The upper bound of each range is the real Nth key after the cursor
	 * ( not cursor + N ), so gaps in the id sequence never produce runaway empty passes. $cursor is
	 * advanced by reference; returns true when the last row has been passed, false when the time
	 * budget ran out first. Identifiers are hard-coded schema names supplied by the update scripts.
	 *
	 * @since 6.0.0
	 */
	private function batch_update_by_pk( $table, $pk, $set_sql, $where_sql, &$cursor, $on_progress = null ) {
		global $wpdb;
		$cursor = (int) $cursor;
		$extra = ( '' !== trim( (string) $where_sql ) ) ? ' AND ( ' . $where_sql . ' )' : '';
		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table / $pk are schema identifiers from the upgrade scripts, not input.
			$upper = $wpdb->get_var( $wpdb->prepare( "SELECT `$pk` FROM `$table` WHERE `$pk` > %d ORDER BY `$pk` ASC LIMIT 1 OFFSET %d", $cursor, self::UPDATE_BATCH_SIZE - 1 ) );
			if ( null === $upper ) {
				/* Fewer than a full batch remains: finish the tail in one statement. */
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- identifiers and the SET / WHERE fragments are hard-coded in this file.
				$wpdb->query( $wpdb->prepare( "UPDATE `$table` SET $set_sql WHERE `$pk` > %d" . $extra, $cursor ) );
				return true;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- identifiers and the SET / WHERE fragments are hard-coded in this file.
			$wpdb->query( $wpdb->prepare( "UPDATE `$table` SET $set_sql WHERE `$pk` > %d AND `$pk` <= %d" . $extra, $cursor, (int) $upper ) );
			$cursor = (int) $upper;
			if ( $on_progress && is_callable( $on_progress ) ) {
				call_user_func( $on_progress, $cursor );
			}
			if ( $this->out_of_time() ) {
				return false;
			}
		}
	}
	/* END DATABASE UPGRADE SCRIPTS */

	private function get_uninstall_tables() {
		$tables = array( 
			"ec_address",
			"ec_affiliate_rule",
			"ec_affiliate_rule_to_affiliate",
			"ec_affiliate_rule_to_product",
			"ec_bundle",
			"ec_cart_link",
			"ec_cart_link_item",
			"ec_category",
			"ec_categoryitem",
			"ec_code",
			"ec_country",
			"ec_customfield",
			"ec_customfielddata",
			"ec_download",
			"ec_fee",
			"ec_giftcard",
			"ec_inventory_log",
			"ec_live_rate_cache",
			"ec_location",
			"ec_location_to_product",
			"ec_location_to_schedule",
			"ec_manufacturer",
			"ec_menulevel1",
			"ec_menulevel2",
			"ec_menulevel3",
			"ec_offer",
			"ec_offer_code",
			"ec_offer_condition",
			"ec_offer_exclusion",
			"ec_offer_redemption",
			"ec_offer_target",
			"ec_option",
			"ec_option_to_product",
			"ec_optionitem",
			"ec_optionitemimage",
			"ec_optionitemquantity",
			"ec_order",
			"ec_order_fee",
			"ec_order_log",
			"ec_order_log_meta",
			"ec_order_option",
			"ec_order_tag",
			"ec_order_tag_item",
			"ec_orderdetail",
			"ec_orderstatus",
			"ec_pageoption",
			"ec_perpage",
			"ec_pricepoint",
			"ec_pricetier",
			"ec_product",
			"ec_product_bundle",
			"ec_product_bundle_item",
			"ec_product_google_attributes",
			"ec_product_subscriber",
			"ec_promocode",
			"ec_promotion",
			"ec_response",
			"ec_review",
			"ec_role",
			"ec_roleaccess",
			"ec_roleprice",
			"ec_schedule",
			"ec_setting",
			"ec_shipping_class",
			"ec_shipping_class_to_rate",
			"ec_shippingrate",
			"ec_state",
			"ec_subscriber",
			"ec_subscription",
			"ec_subscription_plan",
			"ec_taxrate",
			"ec_tempcart",
			"ec_tempcart_data",
			"ec_tempcart_optionitem",
			"ec_tempcart_offer",
			"ec_timezone",
			"ec_user",
			"ec_user_activity",
			"ec_user_note",
			"ec_user_tag",
			"ec_user_to_tag",
			"ec_webhook",
			"ec_zone",
			"ec_zone_to_location"
		);

		return $tables;

	}

	private function get_schema() {
		global $wpdb;
		$collate = "";
		$max_index_length = 191;
		if( $wpdb->has_cap( 'collation' ) ){
			$collate = $wpdb->get_charset_collate( );
		}
		$schema = "CREATE TABLE ec_abandoned_cart (
  abandoned_cart_id int(11) NOT NULL AUTO_INCREMENT,
  session_id varchar($max_index_length) NOT NULL DEFAULT '',
  email varchar(255) NOT NULL DEFAULT '',
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  user_id int(11) NOT NULL DEFAULT '0',
  phone varchar(64) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  stage varchar(20) NOT NULL DEFAULT 'cart',
  items_json longtext,
  item_count int(11) NOT NULL DEFAULT '0',
  subtotal decimal(12,2) NOT NULL DEFAULT '0.00',
  currency varchar(8) NOT NULL DEFAULT '',
  coupon_code varchar(64) NOT NULL DEFAULT '',
  shipping_country varchar(8) NOT NULL DEFAULT '',
  locale varchar(16) NOT NULL DEFAULT '',
  card_error varchar(255) NOT NULL DEFAULT '',
  first_seen datetime DEFAULT NULL,
  last_activity datetime DEFAULT NULL,
  abandoned_at datetime DEFAULT NULL,
  recovered_at datetime DEFAULT NULL,
  recovered_order_id int(11) NOT NULL DEFAULT '0',
  recovered_total decimal(12,2) NOT NULL DEFAULT '0.00',
  sequence_step int(11) NOT NULL DEFAULT '0',
  next_send_at datetime DEFAULT NULL,
  offer_code_id int(11) NOT NULL DEFAULT '0',
  offer_code varchar(64) NOT NULL DEFAULT '',
  offer_code_expires datetime DEFAULT NULL,
  admin_notes text,
  PRIMARY KEY  (abandoned_cart_id),
  UNIQUE KEY session_id (session_id),
  KEY status_next (status, next_send_at),
  KEY email (email(100)),
  KEY last_activity (last_activity),
  KEY first_seen (first_seen)
) $collate;
CREATE TABLE ec_abandoned_cart_event (
  event_id int(11) NOT NULL AUTO_INCREMENT,
  abandoned_cart_id int(11) NOT NULL DEFAULT '0',
  type varchar(24) NOT NULL DEFAULT '',
  step int(11) NOT NULL DEFAULT '0',
  detail text,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (event_id),
  KEY cart_type (abandoned_cart_id, type),
  KEY created_at (created_at)
) $collate;
CREATE TABLE ec_address (
  address_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL DEFAULT '0',
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  address_line_1 varchar(255) NOT NULL DEFAULT '',
  address_line_2 varchar(255) DEFAULT '',
  city varchar(255) NOT NULL DEFAULT '',
  state varchar(128) NOT NULL DEFAULT '',
  zip varchar(128) NOT NULL DEFAULT '',
  country varchar(255) NOT NULL DEFAULT '',
  phone varchar(255) NOT NULL DEFAULT '',
  company_name varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (address_id),
  UNIQUE KEY address_id (address_id),
  KEY ec_address_idx1 (address_id),
  KEY ec_address_idx2 (user_id)
) $collate;
CREATE TABLE ec_affiliate_rule (
  affiliate_rule_id int(11) NOT NULL AUTO_INCREMENT,
  rule_name varchar(255) NOT NULL DEFAULT '',
  rule_type varchar(20) NOT NULL DEFAULT '',
  rule_amount float(15,3) NOT NULL DEFAULT '0.000',
  rule_limit int(11) NOT NULL DEFAULT '0',
  rule_active tinyint(1) NOT NULL DEFAULT '1',
  rule_recurring tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (affiliate_rule_id),
  UNIQUE KEY affiliate_rule_id (affiliate_rule_id)
) $collate;
CREATE TABLE ec_affiliate_rule_to_affiliate (
  rule_to_account_id int(11) NOT NULL AUTO_INCREMENT,
  affiliate_rule_id int(11) NOT NULL DEFAULT '0',
  affiliate_id varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY  (rule_to_account_id),
  UNIQUE KEY rule_to_account_id (rule_to_account_id),
  KEY affiliate_rule_id (affiliate_rule_id) ,
  KEY affiliate_id (affiliate_id)
) $collate;
CREATE TABLE ec_affiliate_rule_to_product (
  rule_to_product_id int(11) NOT NULL AUTO_INCREMENT,
  affiliate_rule_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (rule_to_product_id),
  UNIQUE KEY rule_to_product_id (rule_to_product_id),
  KEY affiliate_rule_id (affiliate_rule_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_bundle (
  bundle_id int(11) NOT NULL AUTO_INCREMENT,
  key_product_id int(11) NOT NULL DEFAULT '0',
  bundled_product_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (bundle_id),
  UNIQUE KEY bundle_id (bundle_id)
) $collate;
CREATE TABLE ec_cart_link (
  cart_link_id int(11) NOT NULL AUTO_INCREMENT,
  link_token varchar(16) NOT NULL DEFAULT '',
  link_label varchar(255) NOT NULL DEFAULT '',
  promo_codes text,
  destination varchar(16) NOT NULL DEFAULT 'cart',
  clear_cart tinyint(1) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  expires datetime DEFAULT NULL,
  max_uses int(11) NOT NULL DEFAULT 0,
  use_count int(11) NOT NULL DEFAULT 0,
  created_by bigint(20) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  last_used datetime DEFAULT NULL,
  PRIMARY KEY  (cart_link_id),
  UNIQUE KEY cart_link_token (link_token)
) $collate;
CREATE TABLE ec_cart_link_item (
  cart_link_item_id int(11) NOT NULL AUTO_INCREMENT,
  cart_link_id int(11) NOT NULL DEFAULT 0,
  product_id int(11) NOT NULL DEFAULT 0,
  quantity int(11) NOT NULL DEFAULT 1,
  optionitem_id_1 int(11) NOT NULL DEFAULT 0,
  optionitem_id_2 int(11) NOT NULL DEFAULT 0,
  optionitem_id_3 int(11) NOT NULL DEFAULT 0,
  optionitem_id_4 int(11) NOT NULL DEFAULT 0,
  optionitem_id_5 int(11) NOT NULL DEFAULT 0,
  modifier_values text,
  sort_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (cart_link_item_id),
  KEY cart_link_item_link (cart_link_id),
  KEY cart_link_item_product (product_id)
) $collate;
CREATE TABLE ec_category (
  category_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  is_active tinyint(1) NOT NULL DEFAULT '1',
  category_name varchar(255) NOT NULL DEFAULT '',
  post_id int(11) NOT NULL DEFAULT '0',
  parent_id int(11) NOT NULL DEFAULT '0',
  short_description text,
  image text,
  featured_category tinyint(1) NOT NULL DEFAULT '0',
  priority int(11) NOT NULL DEFAULT '0',
  square_id varchar(255) NOT NULL DEFAULT '',
  smart_mode tinyint(1) NOT NULL DEFAULT '0',
  smart_rules text,
  PRIMARY KEY  (category_id),
  UNIQUE KEY category_id (category_id)
) $collate;
CREATE TABLE ec_categoryitem (
  categoryitem_id int(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  is_smart tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (categoryitem_id),
  UNIQUE KEY categoryitem_id (categoryitem_id),
  KEY product_id (product_id),
  KEY category_id (category_id),
  KEY idx_category_product (category_id, product_id)
) $collate;
CREATE TABLE ec_code (
  code_id int(11) NOT NULL AUTO_INCREMENT,
  code_val varchar(255) DEFAULT '',
  product_id int(11) NOT NULL DEFAULT '0',
  orderdetail_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (code_id),
  UNIQUE KEY code_id (code_id),
  KEY product_id (product_id),
  KEY orderdetail_id (orderdetail_id)
) $collate;
CREATE TABLE ec_country (
  id_cnt int(11) NOT NULL AUTO_INCREMENT,
  name_cnt varchar(255) NOT NULL DEFAULT '',
  iso2_cnt varchar(10) NOT NULL DEFAULT '',
  iso3_cnt varchar(10) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL,
  vat_rate_cnt float(9,3) NOT NULL DEFAULT '0.000',
  ship_to_active tinyint(1) NOT NULL DEFAULT '1',
  stripe_taxrate_id varchar(100) NOT NULL DEFAULT '',
  vat_b2b_enabled tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (id_cnt),
  KEY iso2_cnt (iso2_cnt),
  KEY iso3_cnt (iso3_cnt)
) $collate;
CREATE TABLE ec_customfield (
  customfield_id int(11) NOT NULL AUTO_INCREMENT,
  table_name varchar(30) NOT NULL DEFAULT '',
  field_name varchar(255) NOT NULL DEFAULT '',
  field_label varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (customfield_id),
  UNIQUE KEY customfield_id (customfield_id)
) $collate;
CREATE TABLE ec_customfielddata (
  customfielddata_id int(11) NOT NULL AUTO_INCREMENT,
  customfield_id int(11) DEFAULT NULL,
  table_id int(11) NOT NULL,
  data blob NOT NULL,
  PRIMARY KEY  (customfielddata_id)
) $collate;
CREATE TABLE ec_download (
  download_id varchar($max_index_length) NOT NULL DEFAULT '',
  date_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  download_count int(11) NOT NULL DEFAULT '0',
  order_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  download_file_name text NULL,
  is_amazon_download tinyint(1) NOT NULL DEFAULT '0',
  amazon_key varchar(1024) NOT NULL DEFAULT '',
  PRIMARY KEY  (download_id),
  KEY download_order_id (order_id),
  KEY download_product_id (product_id)
) $collate;
CREATE TABLE ec_email_log (
  log_id int(11) NOT NULL AUTO_INCREMENT,
  created_at datetime DEFAULT NULL,
  email_type varchar(40) NOT NULL DEFAULT '',
  order_id int(11) NOT NULL DEFAULT '0',
  to_email varchar(255) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  transport varchar(30) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT '',
  error_text text,
  attempts int(11) NOT NULL DEFAULT '1',
  queue_id int(11) NOT NULL DEFAULT '0',
  body_hash varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY  (log_id),
  KEY created_at (created_at),
  KEY order_id (order_id),
  KEY status (status),
  KEY to_email (to_email(100))
) $collate;
CREATE TABLE ec_email_queue (
  queue_id int(11) NOT NULL AUTO_INCREMENT,
  created_at datetime DEFAULT NULL,
  email_type varchar(40) NOT NULL DEFAULT '',
  order_id int(11) NOT NULL DEFAULT '0',
  channel varchar(20) NOT NULL DEFAULT 'order',
  to_email varchar(255) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  message longtext,
  headers text,
  attempts int(11) NOT NULL DEFAULT '0',
  next_attempt datetime DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  last_error text,
  PRIMARY KEY  (queue_id),
  KEY status_next (status, next_attempt),
  KEY order_id (order_id)
) $collate;
CREATE TABLE ec_fee (
  fee_id int(11) NOT NULL AUTO_INCREMENT,
  fee_label varchar(512)  NOT NULL DEFAULT '',
  fee_admin_description text,
  fee_country text,
  fee_state text,
  fee_zip text,
  fee_city text,
  fee_category text,
  fee_role text,
  fee_zone text,
  fee_payment_type varchar(255) DEFAULT '',
  fee_type int(11) NOT NULL DEFAULT '1',
  fee_rate float(15,3) NOT NULL DEFAULT '0.000',
  fee_price float(15,3) NOT NULL DEFAULT '0.000',
  fee_min float(15,3) NOT NULL DEFAULT '0.000',
  fee_max float(15,3) NOT NULL DEFAULT '-1.000',
  fee_basis varchar(32) NOT NULL DEFAULT 'subtotal',
  PRIMARY KEY  (fee_id)
) $collate;
CREATE TABLE ec_giftcard (
  giftcard_id varchar(20) NOT NULL DEFAULT '',
  amount float(15,3) NOT NULL DEFAULT '0.000',
  message text,
  PRIMARY KEY  (giftcard_id),
  UNIQUE KEY giftcard_id (giftcard_id)
) $collate;
CREATE TABLE ec_inventory_log (
	log_id bigint(20) NOT NULL AUTO_INCREMENT,
	product_id int(11) NOT NULL DEFAULT '0',
	optionitemquantity_id int(11) NOT NULL DEFAULT '0',
	location_id int(11) NOT NULL DEFAULT '0',
	delta int(11) NOT NULL DEFAULT '0',
	new_quantity int(11) NOT NULL DEFAULT '0',
	reason varchar(40) NOT NULL DEFAULT '',
	source varchar(40) NOT NULL DEFAULT '',
	note text NULL,
	user_id bigint(20) NOT NULL DEFAULT '0',
	created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (log_id),
	KEY product_id (product_id),
	KEY optionitemquantity_id (optionitemquantity_id),
	KEY created (created)
) $collate;
CREATE TABLE ec_live_rate_cache (
  live_rate_cache_id int(11) NOT NULL AUTO_INCREMENT,
  ec_cart_id varchar(255) NOT NULL DEFAULT '',
  rate_data text,
  created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (live_rate_cache_id),
  KEY ec_cart_id (ec_cart_id(191)),
  KEY created (created)
) $collate;
CREATE TABLE ec_location (
  location_id int(11) NOT NULL AUTO_INCREMENT,
  location_label varchar(255) DEFAULT '',
  address_line_1 varchar(255) NOT NULL DEFAULT '',
  address_line_2 varchar(255) NOT NULL DEFAULT '',
  city varchar(255) NOT NULL DEFAULT '',
  state varchar(255) NOT NULL DEFAULT '',
  country varchar(255) NOT NULL DEFAULT '',
  zip varchar(255) NOT NULL DEFAULT '',
  phone varchar(255) NOT NULL DEFAULT '',
  email varchar(255) NOT NULL DEFAULT '',
  latitude decimal(9,6) DEFAULT NULL,
  longitude decimal(9,6) DEFAULT NULL,
  hours_note text NULL,
  PRIMARY KEY  (location_id),
  UNIQUE KEY location_id (location_id)
) $collate;
CREATE TABLE ec_location_to_product (
  location_product_id int(11) NOT NULL AUTO_INCREMENT,
  location_id int(11) NOT NULL DEFAULT 0,
  product_id int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (location_product_id),
  UNIQUE KEY location_product_id (location_product_id),
  KEY location_id (location_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_location_to_schedule (
  location_schedule_id int(11) NOT NULL AUTO_INCREMENT,
  location_id int(11) NOT NULL DEFAULT 0,
  schedule_id int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (location_schedule_id),
  UNIQUE KEY location_schedule_id (location_schedule_id),
  KEY location_id (location_id),
  KEY product_id (schedule_id)
) $collate;
CREATE TABLE ec_manufacturer (
  manufacturer_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  clicks int(11) NOT NULL DEFAULT '0',
  post_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (manufacturer_id)
) $collate;
CREATE TABLE ec_menulevel1 (
  menulevel1_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  menu_order int(11) NOT NULL DEFAULT '0',
  clicks int(11) NOT NULL DEFAULT '0',
  seo_keywords varchar(255) NOT NULL DEFAULT '',
  seo_description blob NULL,
  banner_image varchar(255) NOT NULL DEFAULT '',
  post_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (menulevel1_id),
  UNIQUE KEY menu1_menulevel1_id (menulevel1_id)
) $collate;
CREATE TABLE ec_menulevel2 (
  menulevel2_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  menulevel1_id int(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  menu_order int(11) NOT NULL DEFAULT '0',
  clicks int(11) NOT NULL DEFAULT '0',
  seo_keywords varchar(255) NOT NULL DEFAULT '',
  seo_description blob NULL,
  banner_image varchar(255) NOT NULL DEFAULT '',
  post_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (menulevel2_id),
  UNIQUE KEY menu2_menulevel2_id (menulevel2_id),
  KEY menu2_menulevel1_id (menulevel1_id)
) $collate;
CREATE TABLE ec_menulevel3 (
  menulevel3_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  menulevel2_id int(11) NOT NULL DEFAULT '0',
  name varchar(255) NOT NULL DEFAULT '',
  menu_order int(11) NOT NULL DEFAULT '0',
  clicks int(11) NOT NULL DEFAULT '0',
  seo_keywords varchar(255) NOT NULL DEFAULT '',
  seo_description blob NULL,
  banner_image varchar(255) NOT NULL DEFAULT '',
  post_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (menulevel3_id),
  UNIQUE KEY menu3_menulevel3_id (menulevel3_id),
  KEY menu3_menulevel2_id (menulevel2_id)
) $collate;
CREATE TABLE ec_offer (
  offer_id int(11) NOT NULL AUTO_INCREMENT,
  offer_name varchar(255) NOT NULL DEFAULT '',
  offer_label varchar(255) NOT NULL DEFAULT '',
  offer_description text,
  offer_status varchar(20) NOT NULL DEFAULT 'draft',
  trigger_type varchar(20) NOT NULL DEFAULT 'code',
  action_type varchar(40) NOT NULL DEFAULT 'item_discount',
  action_config longtext,
  start_date datetime DEFAULT NULL,
  end_date datetime DEFAULT NULL,
  schedule_config longtext,
  is_exclusive tinyint(1) NOT NULL DEFAULT '0',
  combine_item_discounts tinyint(1) NOT NULL DEFAULT '0',
  combine_cart_discounts tinyint(1) NOT NULL DEFAULT '0',
  combine_shipping_discounts tinyint(1) NOT NULL DEFAULT '1',
  priority int(11) NOT NULL DEFAULT '10',
  apply_limit int(11) NOT NULL DEFAULT '0',
  max_discount_amount float(15,3) NOT NULL DEFAULT '0.000',
  min_item_price_floor float(15,3) NOT NULL DEFAULT '0.000',
  max_redemptions int(11) NOT NULL DEFAULT '0',
  times_redeemed int(11) NOT NULL DEFAULT '0',
  max_redemptions_per_customer int(11) NOT NULL DEFAULT '0',
  max_redemptions_per_day int(11) NOT NULL DEFAULT '0',
  applies_to_subscriptions tinyint(1) NOT NULL DEFAULT '0',
  applies_to_sale_items tinyint(1) NOT NULL DEFAULT '1',
  discount_base varchar(20) NOT NULL DEFAULT 'unit_price',
  include_modifier_prices tinyint(1) NOT NULL DEFAULT '0',
  display_config longtext,
  legacy_promocode_id varchar($max_index_length) NOT NULL DEFAULT '',
  legacy_promotion_id int(11) NOT NULL DEFAULT '0',
  created_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  modified_date timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (offer_id),
  UNIQUE KEY offer_offer_id (offer_id),
  KEY offer_status_trigger (offer_status,trigger_type),
  KEY offer_dates (start_date,end_date),
  KEY offer_legacy_promotion (legacy_promotion_id)
) $collate;
CREATE TABLE ec_offer_code (
  offer_code_id int(11) NOT NULL AUTO_INCREMENT,
  offer_id int(11) NOT NULL DEFAULT '0',
  code varchar($max_index_length) NOT NULL DEFAULT '',
  max_redemptions int(11) NOT NULL DEFAULT '0',
  times_redeemed int(11) NOT NULL DEFAULT '0',
  assigned_email varchar(255) NOT NULL DEFAULT '',
  is_active tinyint(1) NOT NULL DEFAULT '1',
  created_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (offer_code_id),
  UNIQUE KEY offer_code_code (code),
  KEY offer_code_offer_id (offer_id)
) $collate;
CREATE TABLE ec_offer_target (
  offer_target_id int(11) NOT NULL AUTO_INCREMENT,
  offer_id int(11) NOT NULL DEFAULT '0',
  target_side varchar(10) NOT NULL DEFAULT 'both',
  target_mode varchar(10) NOT NULL DEFAULT 'include',
  entity_type varchar(20) NOT NULL DEFAULT 'all',
  entity_id int(11) NOT NULL DEFAULT '0',
  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
  entity_config longtext,
  PRIMARY KEY  (offer_target_id),
  KEY offer_target_offer_id (offer_id),
  KEY offer_target_entity (entity_type,entity_id)
) $collate;
CREATE TABLE ec_offer_condition (
  offer_condition_id int(11) NOT NULL AUTO_INCREMENT,
  offer_id int(11) NOT NULL DEFAULT '0',
  condition_group int(11) NOT NULL DEFAULT '1',
  condition_type varchar(40) NOT NULL DEFAULT '',
  condition_value longtext,
  PRIMARY KEY  (offer_condition_id),
  KEY offer_condition_offer_id (offer_id)
) $collate;
CREATE TABLE ec_offer_exclusion (
  offer_exclusion_id int(11) NOT NULL AUTO_INCREMENT,
  offer_id int(11) NOT NULL DEFAULT '0',
  excluded_offer_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (offer_exclusion_id),
  KEY offer_exclusion_offer_id (offer_id),
  KEY offer_exclusion_excluded_id (excluded_offer_id)
) $collate;
CREATE TABLE ec_offer_redemption (
  offer_redemption_id int(11) NOT NULL AUTO_INCREMENT,
  offer_id int(11) NOT NULL DEFAULT '0',
  offer_code_id int(11) NOT NULL DEFAULT '0',
  code varchar($max_index_length) NOT NULL DEFAULT '',
  order_id int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  email varchar(255) NOT NULL DEFAULT '',
  discount_amount float(15,3) NOT NULL DEFAULT '0.000',
  redemption_status varchar(20) NOT NULL DEFAULT 'completed',
  redemption_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (offer_redemption_id),
  KEY offer_redemption_offer_id (offer_id),
  KEY offer_redemption_order_id (order_id),
  KEY offer_redemption_user_id (user_id),
  KEY offer_redemption_offer_email (offer_id,email(100))
) $collate;
CREATE TABLE ec_option (
  option_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  option_name varchar(128) NOT NULL DEFAULT '',
  option_label text,
  option_type varchar(20) NOT NULL DEFAULT 'combo',
  option_required tinyint(1) NOT NULL DEFAULT '1',
  option_error_text text,
  option_meta text,
  square_id varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (option_id),
  UNIQUE KEY option_option_id (option_id) 
) $collate;
CREATE TABLE ec_option_to_product (
  option_to_product_id int(11) NOT NULL AUTO_INCREMENT,
  option_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  role_label varchar(20) NOT NULL DEFAULT 'all',
  option_order int(11) NOT NULL DEFAULT '0',
  conditional_logic text,
  stripe_price_id text,
  PRIMARY KEY  (option_to_product_id),
  UNIQUE KEY option_to_product_id (option_to_product_id),
  KEY option_id (option_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_optionitem (
  optionitem_id int(11) NOT NULL AUTO_INCREMENT,
  option_id int(11) NOT NULL DEFAULT '0',
  optionitem_name text,
  optionitem_enable_custom_price_label tinyint(1) NOT NULL DEFAULT '0',
  optionitem_custom_price_label text,
  optionitem_price float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_onetime float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_override float(15,3) NOT NULL DEFAULT '-1.000',
  optionitem_price_multiplier float(15,3) NOT NULL DEFAULT '0',
  optionitem_price_per_character float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_weight float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_weight_onetime float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_weight_override float(15,3) NOT NULL DEFAULT '-1.000',
  optionitem_weight_multiplier float(15,3) NOT NULL DEFAULT '0',
  optionitem_order int(11) NOT NULL DEFAULT '1',
  optionitem_icon varchar(255) NOT NULL DEFAULT '',
  optionitem_initial_value varchar(255) NOT NULL DEFAULT '',
  optionitem_model_number varchar(255) NOT NULL DEFAULT '',
  optionitem_allow_download tinyint(1) NOT NULL DEFAULT '1',
  optionitem_disallow_shipping tinyint(1) NOT NULL DEFAULT '0',
  optionitem_initially_selected tinyint(1) NOT NULL DEFAULT '0',
  optionitem_download_override_file text,
  optionitem_download_addition_file text,
  square_id varchar(255) NOT NULL DEFAULT '',
  stripe_plan_id varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (optionitem_id),
  KEY option_id (option_id)
) $collate;
CREATE TABLE ec_optionitemimage (
  optionitemimage_id int(11) NOT NULL AUTO_INCREMENT,
  optionitem_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  product_images text NULL,
  image1 text NULL,
  image2 text NULL,
  image3 text NULL,
  image4 text NULL,
  image5 text NULL,
  PRIMARY KEY  (optionitemimage_id),
  KEY optionitem_id (optionitem_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_optionitemquantity (
  optionitemquantity_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(17) NOT NULL DEFAULT '0',
  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
  quantity int(11) NOT NULL DEFAULT '0',
  sku varchar(255) NOT NULL DEFAULT '',
  price float(15,3) NOT NULL DEFAULT '-1.000',
  is_enabled tinyint(1) NOT NULL DEFAULT '1',
  is_stock_tracking_enabled tinyint(1) NOT NULL DEFAULT '1',
  square_id varchar(255) NOT NULL DEFAULT '',
  google_merchant text NULL,
  reorder_point int(11) NOT NULL DEFAULT '-1',
  PRIMARY KEY  (optionitemquantity_id),
  UNIQUE KEY optionitemquantity_id (optionitemquantity_id),
  KEY product_id (product_id),
  KEY optionitem_id_1 (optionitem_id_1),
  KEY optionitem_id_2 (optionitem_id_2),
  KEY optionitem_id_3 (optionitem_id_3),
  KEY optionitem_id_4 (optionitem_id_4),
  KEY optionitem_id_5 (optionitem_id_5)
) $collate;
CREATE TABLE ec_order (
  order_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  user_email varchar(255) NOT NULL DEFAULT '',
  user_level varchar(255) NOT NULL DEFAULT 'shopper',
  last_updated datetime NULL,
  order_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  orderstatus_id int(11) NOT NULL DEFAULT '5',
  order_weight float(15,3) NOT NULL DEFAULT '0.000',
  sub_total float(15,3) NOT NULL DEFAULT '0.000',
  tax_total float(15,3) NOT NULL DEFAULT '0.000',
  shipping_total float(15,3) NOT NULL DEFAULT '0.000',
  discount_total float(15,3) NOT NULL DEFAULT '0.000',
  vat_total float(15,3) NOT NULL DEFAULT '0.000',
  vat_rate float(15,3) NOT NULL DEFAULT '0.000',
  duty_total float(15,3) NOT NULL DEFAULT '0.000',
  gst_total float(15,3) NOT NULL DEFAULT '0.000',
  gst_rate float(15,3) NOT NULL DEFAULT '0.000',
  pst_total float(15,3) NOT NULL DEFAULT '0.000',
  pst_rate float(15,3) NOT NULL DEFAULT '0.000',
  hst_total float(15,3) NOT NULL DEFAULT '0.000',
  hst_rate float(15,3) NOT NULL DEFAULT '0.000',
  tip_total float(15,3) NOT NULL DEFAULT '0.000',
  grand_total float(15,3) NOT NULL DEFAULT '0.000',
  refund_total float(15,3) NOT NULL DEFAULT '0.000',
  shipping_refund_total float(15,3) NOT NULL DEFAULT '0.000',
  tax_refund_total float(15,3) NOT NULL DEFAULT '0.000',
  promo_code varchar(255) NOT NULL DEFAULT '',
  promo_code_message varchar(1024) NOT NULL DEFAULT '',
  offer_discount_total float(15,3) NOT NULL DEFAULT '0.000',
  applied_offers longtext,
  giftcard_id varchar(20) NOT NULL DEFAULT '',
  use_expedited_shipping tinyint(1) NOT NULL DEFAULT '0',
  shipping_method varchar(255) NOT NULL DEFAULT '',
  shipping_carrier varchar(255) NOT NULL DEFAULT '',
  shipping_service_code varchar(255) NOT NULL DEFAULT '',
  tracking_number varchar(255) NOT NULL DEFAULT '',
  billing_first_name varchar(255) NOT NULL DEFAULT '',
  billing_last_name varchar(255) NOT NULL DEFAULT '',
  billing_address_line_1 varchar(255) NOT NULL DEFAULT '',
  billing_address_line_2 varchar(255) NOT NULL DEFAULT '',
  billing_city varchar(255) NOT NULL DEFAULT '',
  billing_state varchar(255) NOT NULL DEFAULT '',
  billing_country varchar(255) NOT NULL DEFAULT '',
  billing_zip varchar(255) NOT NULL DEFAULT '',
  billing_phone varchar(255) NOT NULL DEFAULT '',
  shipping_first_name varchar(255) NOT NULL DEFAULT '',
  shipping_last_name varchar(255) NOT NULL DEFAULT '',
  shipping_address_line_1 varchar(255) NOT NULL DEFAULT '',
  shipping_address_line_2 varchar(255) NOT NULL DEFAULT '',
  shipping_city varchar(255) NOT NULL DEFAULT '',
  shipping_state varchar(255) NOT NULL DEFAULT '',
  shipping_country varchar(255) NOT NULL DEFAULT '',
  shipping_zip varchar(255) NOT NULL DEFAULT '',
  shipping_phone varchar(255) NOT NULL DEFAULT '',
  vat_registration_number varchar(255) NOT NULL DEFAULT '',
  payment_method varchar(255) NOT NULL DEFAULT '',
  paypal_email_id varchar(255) NOT NULL DEFAULT '',
  paypal_transaction_id varchar(255) NOT NULL DEFAULT '',
  paypal_payer_id varchar(255) NOT NULL DEFAULT '',
  order_viewed tinyint(1) NOT NULL DEFAULT '0',
  order_notes text,
  order_customer_notes blob,
  txn_id varchar(50) NOT NULL DEFAULT '',
  payment_txn_id varchar(50) NOT NULL DEFAULT '',
  edit_sequence varchar(50) NOT NULL DEFAULT '',
  quickbooks_status varchar(255) NOT NULL DEFAULT 'Not Queued',
  credit_memo_txn_id varchar(255) NOT NULL DEFAULT '',
  card_holder_name varchar(255) NOT NULL DEFAULT '',
  creditcard_digits varchar(4) NOT NULL DEFAULT '',
  cc_exp_month varchar(2) NOT NULL DEFAULT '',
  cc_exp_year varchar(4) NOT NULL DEFAULT '',
  fraktjakt_order_id varchar(20) NOT NULL DEFAULT '',
  fraktjakt_shipment_id varchar(20) DEFAULT '',
  stripe_charge_id varchar(255) NOT NULL DEFAULT '',
  nets_transaction_id varchar(255) NOT NULL DEFAULT '',
  subscription_id int(11) NOT NULL DEFAULT '0',
  order_gateway varchar(64) NOT NULL DEFAULT '',
  affirm_charge_id varchar(100) NOT NULL DEFAULT '',
  billing_company_name varchar(255) NOT NULL DEFAULT '',
  shipping_company_name varchar(255) NOT NULL DEFAULT '',
  guest_key varchar(255) NOT NULL DEFAULT '',
  agreed_to_terms tinyint(1) NOT NULL DEFAULT '0',
  order_ip_address varchar(255) NOT NULL DEFAULT '',
  gateway_transaction_id varchar(255) NOT NULL DEFAULT '',
  success_page_shown tinyint(1) NOT NULL DEFAULT 0,
  email_other varchar(255) NOT NULL DEFAULT '',
  includes_preorder_items tinyint(1) NOT NULL DEFAULT 0,
  includes_restaurant_type tinyint(1) NOT NULL DEFAULT 0,
  pickup_date datetime NULL,
  pickup_asap tinyint(1) NOT NULL DEFAULT 1,
  pickup_time datetime NULL,
  location_id int(11) NOT NULL DEFAULT 0,
  converted_cart_id varchar(100) NOT NULL DEFAULT '',
  cart_link_id int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (order_id),
  UNIQUE KEY order_id (order_id),
  KEY user_id (user_id),
  KEY giftcard_id (giftcard_id),
  KEY order_cart_link (cart_link_id),
  KEY order_order_date (order_date),
  KEY order_orderstatus_id (orderstatus_id),
  KEY order_order_viewed (order_viewed),
  KEY order_user_email (user_email($max_index_length)),
  KEY order_shipping_country (shipping_country($max_index_length)),
  KEY order_billing_country (billing_country($max_index_length))
) $collate;
CREATE TABLE ec_order_fee (
  order_fee_id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL DEFAULT '0',
  fee_label varchar(512)  NOT NULL DEFAULT '',
  fee_rate float(15,3) NOT NULL DEFAULT '0.000',
  fee_total float(15,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY  (order_fee_id),
  KEY order_id (order_id)
) $collate;
CREATE TABLE ec_order_log (
  order_log_id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL DEFAULT '0',
  order_log_key varchar(100) NOT NULL DEFAULT '',
  order_log_timestamp timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (order_log_id),
  UNIQUE KEY order_log_id (order_log_id),
  KEY order_id (order_id)
) $collate;
CREATE TABLE ec_order_log_meta (
  order_log_meta_id int(11) NOT NULL AUTO_INCREMENT,
  order_log_id int(11) NOT NULL DEFAULT '0',
  order_id int(11) NOT NULL DEFAULT '0',
  order_log_meta_key varchar(100) NOT NULL DEFAULT '',
  order_log_meta_value text,
  PRIMARY KEY  (order_log_meta_id),
  UNIQUE KEY order_log_meta_id (order_log_meta_id),
  KEY order_log_id (order_log_id),
  KEY order_id (order_id)
) $collate;
CREATE TABLE ec_order_option (
  order_option_id int(11) NOT NULL AUTO_INCREMENT,
  orderdetail_id int(11) NOT NULL DEFAULT '0',
  option_name varchar(255) NOT NULL DEFAULT '',
  optionitem_name text,
  option_type varchar(20) NOT NULL DEFAULT 'combo',
  option_value text NOT NULL,
  option_price_change varchar(255) NOT NULL DEFAULT '',
  optionitem_price float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_price_onetime float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_price_override float(15,3) NOT NULL DEFAULT '-1.00',
  optionitem_price_multiplier float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_price_per_character float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_weight float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_weight_onetime float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_weight_override float(15,3) NOT NULL DEFAULT '-1.00',
  optionitem_weight_multiplier float(15,3) NOT NULL DEFAULT '0.00',
  optionitem_disallow_shipping tinyint(1) NOT NULL DEFAULT '0',
  optionitem_allow_download tinyint(1) NOT NULL DEFAULT '1',
  optionitem_enable_custom_price_label tinyint(1) NOT NULL DEFAULT '0',
  optionitem_custom_price_label text,
  option_label text,
  option_to_product_id int(11) NOT NULL DEFAULT '0',
  option_order int(11) NOT NULL DEFAULT '0',
  download_override_file text,
  download_addition_file text,
  PRIMARY KEY  (order_option_id),
  UNIQUE KEY order_option_id (order_option_id),
  KEY orderdetail_id (orderdetail_id) 
) $collate;
CREATE TABLE ec_order_tag (
  tag_id int(11) NOT NULL AUTO_INCREMENT,
  tag_label varchar(100) NOT NULL DEFAULT '',
  tag_color varchar(20) NOT NULL DEFAULT '#6a737d',
  PRIMARY KEY  (tag_id)
) $collate;
CREATE TABLE ec_order_tag_item (
  order_id int(11) NOT NULL,
  tag_id int(11) NOT NULL,
  KEY order_id (order_id),
  KEY tag_id (tag_id)
) $collate;
CREATE TABLE ec_orderdetail (
  orderdetail_id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  title varchar(255) DEFAULT NULL,
  model_number varchar(255) NOT NULL,
  order_date datetime NOT NULL,
  unit_price float(15,3) NOT NULL DEFAULT '0.000',
  unit_discount_promotion float(15,3) NOT NULL DEFAULT '0.000',
  unit_discount_coupon float(15,3) NOT NULL DEFAULT '0.000',
  total_price float(15,3) NOT NULL DEFAULT '0.000',
  total_discount_promotion float(15,3) NOT NULL DEFAULT '0.000',
  total_discount_coupon float(15,3) NOT NULL DEFAULT '0.000',
  quantity int(11) NOT NULL DEFAULT '0',
  refunded_quantity int(11) NOT NULL DEFAULT '0',
  image1 text NOT NULL,
  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
  optionitem_name_1 text NULL,
  optionitem_name_2 text NULL,
  optionitem_name_3 text NULL,
  optionitem_name_4 text NULL,
  optionitem_name_5 text NULL,
  optionitem_label_1 text NULL,
  optionitem_label_2 text NULL,
  optionitem_label_3 text NULL,
  optionitem_label_4 text NULL,
  optionitem_label_5 text NULL,
  optionitem_price_1 float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_2 float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_3 float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_4 float(15,3) NOT NULL DEFAULT '0.000',
  optionitem_price_5 float(15,3) NOT NULL DEFAULT '0.000',
  use_advanced_optionset tinyint(1) NOT NULL DEFAULT '0',
  use_both_option_types tinyint(1) NOT NULL DEFAULT '0',
  giftcard_id varchar(20) NOT NULL DEFAULT '',
  shipper_id int(11) DEFAULT '0',
  shipper_first_name varchar(255) NOT NULL DEFAULT '',
  shipper_last_name varchar(255) NOT NULL DEFAULT '',
  gift_card_message text NULL,
  gift_card_from_name varchar(255) NULL,
  gift_card_to_name varchar(255) NULL,
  is_download tinyint(1) NOT NULL DEFAULT '0',
  is_giftcard tinyint(1) NOT NULL DEFAULT '0',
  is_taxable tinyint(1) NOT NULL DEFAULT '1',
  is_shippable tinyint(1) NOT NULL DEFAULT '1',
  exclude_shippable_calculation tinyint(1) NOT NULL DEFAULT '0',
  download_file_name text NULL,
  download_key text NULL,
  maximum_downloads_allowed int(11) NOT NULL DEFAULT '0',
  download_timelimit_seconds int(11) DEFAULT '0',
  is_amazon_download tinyint(1) NOT NULL DEFAULT '0',
  amazon_key varchar(1024) NOT NULL DEFAULT '',
  is_deconetwork tinyint(1) NOT NULL DEFAULT '0',
  deconetwork_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_name varchar(255) NOT NULL DEFAULT '',
  deconetwork_product_code varchar(64) NOT NULL DEFAULT '',
  deconetwork_options varchar(255) NOT NULL DEFAULT '',
  deconetwork_color_code varchar(64) NOT NULL DEFAULT '',
  deconetwork_product_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_image_link varchar(255) NOT NULL DEFAULT '',
  gift_card_email varchar(255) NOT NULL DEFAULT '',
  include_code tinyint(1) NOT NULL DEFAULT '0',
  subscription_signup_fee float(15,3) NOT NULL DEFAULT '0.000',
  stock_adjusted tinyint(1) NOT NULL DEFAULT '0',
  bundle_group_key varchar(64) NOT NULL DEFAULT '',
  bundle_product_id int(11) NOT NULL DEFAULT '0',
  is_free_gift tinyint(1) NOT NULL DEFAULT '0',
  applied_offers longtext,
  PRIMARY KEY  (orderdetail_id),
  UNIQUE KEY orderdetail_id (orderdetail_id),
  KEY orderdetail_order_id (order_id),
  KEY orderdetail_product_id (product_id),
  KEY orderdetail_giftcard_id (giftcard_id),
  KEY idx_order_product (order_id,product_id),
  KEY orderdetail_product_stock (product_id,stock_adjusted)
) $collate;
CREATE TABLE ec_orderstatus (
  status_id int(11) NOT NULL AUTO_INCREMENT,
  order_status varchar(255) NOT NULL DEFAULT '',
  is_approved tinyint(1) DEFAULT '0',
  is_archieved tinyint(1) DEFAULT '0',
  color_code varchar(40) NOT NULL DEFAULT '#FFFFFF',
  PRIMARY KEY  (status_id),
  UNIQUE KEY orderstatus_status_id (status_id)
) $collate;
CREATE TABLE ec_pageoption (
  pageoption_id int(11) NOT NULL AUTO_INCREMENT,
  post_id int(11) NOT NULL DEFAULT '0',
  option_type varchar(155) NOT NULL DEFAULT '',
  option_value text NOT NULL,
  PRIMARY KEY  (pageoption_id),
  UNIQUE KEY pageoption_id (pageoption_id)
) $collate;
CREATE TABLE ec_perpage (
  perpage_id int(11) NOT NULL AUTO_INCREMENT,
  perpage int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (perpage_id),
  UNIQUE KEY perpageid (perpage_id)
) $collate;
CREATE TABLE ec_pricepoint (
  pricepoint_id int(11) NOT NULL AUTO_INCREMENT,
  is_less_than tinyint(1) NOT NULL DEFAULT 0,
  is_greater_than tinyint(1) NOT NULL DEFAULT 0,
  low_point float(15,3) NOT NULL DEFAULT '0.000',
  high_point float(15,3) DEFAULT '0.000',
  pricepoint_order int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (pricepoint_id),
  UNIQUE KEY pricepoint_pricepoint_id (pricepoint_id)
) $collate;
CREATE TABLE ec_pricetier (
  pricetier_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL DEFAULT '0',
  price float(15,3) NOT NULL DEFAULT '0.000',
  quantity int(11) NOT NULL DEFAULT '10',
  PRIMARY KEY  (pricetier_id),
  UNIQUE KEY pricetier_id (pricetier_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_product (
  product_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  model_number varchar(255) NOT NULL DEFAULT '',
  post_id int(11) NOT NULL DEFAULT '0',
  activate_in_store tinyint(1) NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  description text NULL,
  specifications text NULL,
  order_completed_note text NULL,
  order_completed_email_note text NULL,
  order_completed_details_note text NULL,
  price float(15,3) NOT NULL DEFAULT '0.000',
  list_price float(15,3) NOT NULL DEFAULT '0.000',
  product_cost float(15,3) NOT NULL DEFAULT '0.000',
  vat_rate float(15,3) NOT NULL DEFAULT '0.000',
  handling_price float(15,3) NOT NULL DEFAULT '0.000',
  handling_price_each float(15,3) NOT NULL DEFAULT '0.000',
  stock_quantity int(7) NOT NULL DEFAULT '0',
  min_purchase_quantity int(11) NOT NULL DEFAULT '0',
  max_purchase_quantity int(11) NOT NULL DEFAULT '0',
  weight float(15,3) NOT NULL DEFAULT '0.000',
  width DOUBLE(15,3) NOT NULL DEFAULT '1.000',
  height DOUBLE(15,3) NOT NULL DEFAULT '1.000',
  length DOUBLE(15,3) NOT NULL DEFAULT '1.000',
  seo_description text NULL,
  seo_keywords varchar(255) NOT NULL DEFAULT '',
  use_specifications tinyint(1) NOT NULL DEFAULT 0,
  use_customer_reviews tinyint(1) NOT NULL DEFAULT 0,
  manufacturer_id int(11) NOT NULL DEFAULT '0',
  download_file_name text NULL,
  product_images text NULL,
  image1 text NULL,
  image2 text NULL,
  image3 text NULL,
  image4 text NULL,
  image5 text NULL,
  option_id_1 int(11) NOT NULL DEFAULT '0',
  option_id_2 int(11) NOT NULL DEFAULT '0',
  option_id_3 int(11) NOT NULL DEFAULT '0',
  option_id_4 int(11) NOT NULL DEFAULT '0',
  option_id_5 int(11) NOT NULL DEFAULT '0',
  use_advanced_optionset tinyint(1) NOT NULL DEFAULT 0,
  use_both_option_types tinyint(1) NOT NULL DEFAULT '0',
  menulevel1_id_1 int(11) NOT NULL DEFAULT '0',
  menulevel1_id_2 int(11) NOT NULL DEFAULT '0',
  menulevel1_id_3 int(11) NOT NULL DEFAULT '0',
  menulevel2_id_1 int(11) NOT NULL DEFAULT '0',
  menulevel2_id_2 int(11) NOT NULL DEFAULT '0',
  menulevel2_id_3 int(11) NOT NULL DEFAULT '0',
  menulevel3_id_1 int(11) NOT NULL DEFAULT '0',
  menulevel3_id_2 int(11) NOT NULL DEFAULT '0',
  menulevel3_id_3 int(11) NOT NULL DEFAULT '0',
  featured_product_id_1 int(11) NOT NULL DEFAULT '0',
  featured_product_id_2 int(11) NOT NULL DEFAULT '0',
  featured_product_id_3 int(11) NOT NULL DEFAULT '0',
  featured_product_id_4 int(11) NOT NULL DEFAULT '0',
  is_giftcard tinyint(1) NOT NULL DEFAULT 0,
  is_download tinyint(1) NOT NULL DEFAULT 0,
  is_donation tinyint(1) NOT NULL DEFAULT 0,
  is_special tinyint(1) NOT NULL DEFAULT 0,
  is_taxable tinyint(1) NOT NULL DEFAULT 1,
  is_shippable tinyint(1) NOT NULL DEFAULT 1,
  exclude_shippable_calculation tinyint(1) NOT NULL DEFAULT 0,
  is_subscription_item tinyint(1) NOT NULL DEFAULT 0,
  is_preorder tinyint(1) NOT NULL DEFAULT 0,
  role_id tinyint(1) NOT NULL DEFAULT 0,
  added_to_db_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  show_on_startup tinyint(1) NOT NULL DEFAULT 0,
  use_optionitem_images tinyint(1) NOT NULL DEFAULT 0,
  use_optionitem_quantity_tracking tinyint(1) NOT NULL DEFAULT 0,
  views int(11) NOT NULL DEFAULT '0',
  last_viewed datetime NULL,
  show_stock_quantity tinyint(1) NOT NULL DEFAULT 1,
  maximum_downloads_allowed int(11) NOT NULL DEFAULT '0',
  download_timelimit_seconds int(11) NOT NULL DEFAULT '0',
  list_id varchar(50) NOT NULL DEFAULT '',
  edit_sequence varchar(55) NOT NULL DEFAULT '',
  quickbooks_status varchar(255) NOT NULL DEFAULT 'Not Queued',
  income_account_ref varchar(255) NOT NULL DEFAULT 'Online Sales',
  cogs_account_ref varchar(255) NOT NULL DEFAULT 'Cost of Goods Sold',
  asset_account_ref varchar(255) NOT NULL DEFAULT 'Inventory Asset',
  quickbooks_parent_name varchar(255) NOT NULL DEFAULT '',
  quickbooks_parent_list_id varchar(255) NOT NULL DEFAULT '',
  subscription_bill_length int(11) NOT NULL DEFAULT '1',
  subscription_bill_period varchar(20) NOT NULL DEFAULT 'M',
  subscription_bill_duration int(11) NOT NULL DEFAULT '0',
  subscription_shipping_recurring tinyint(1) NOT NULL DEFAULT '0',
  subscription_recurring_email tinyint(1) NOT NULL DEFAULT '1',
  trial_period_days int(11) NOT NULL DEFAULT '0',
  stripe_plan_added tinyint(1) NOT NULL DEFAULT 0,
  subscription_plan_id int(11) NOT NULL DEFAULT '0',
  allow_multiple_subscription_purchases tinyint(1) NOT NULL DEFAULT 1,
  membership_page varchar(255) NOT NULL DEFAULT '',
  is_amazon_download tinyint(1) NOT NULL DEFAULT 0,
  amazon_key varchar(1024) NOT NULL DEFAULT '',
  catalog_mode tinyint(1) NOT NULL DEFAULT 0,
  catalog_mode_phrase varchar(1024) DEFAULT NULL,
  inquiry_mode tinyint(1) NOT NULL DEFAULT 0,
  inquiry_url varchar(1024) DEFAULT NULL,
  is_deconetwork tinyint(1) NOT NULL DEFAULT 0,
  deconetwork_mode varchar(64) NOT NULL DEFAULT 'designer',
  deconetwork_product_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_size_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_color_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_design_id varchar(64) NOT NULL DEFAULT '',
  short_description text NULL,
  display_type int(11) NOT NULL DEFAULT '1',
  image_hover_type int(11) NOT NULL DEFAULT '3',
  tag_type int(11) NOT NULL DEFAULT '0',
  tag_bg_color varchar(20) NOT NULL DEFAULT '',
  tag_text_color varchar(20) NOT NULL DEFAULT '',
  tag_text varchar(255) NOT NULL DEFAULT '',
  image_effect_type varchar(20) NOT NULL DEFAULT 'none',
  include_code tinyint(1) NOT NULL DEFAULT '0',
  TIC varchar(128) NOT NULL DEFAULT '00000',
  subscription_signup_fee float(15,3) NOT NULL DEFAULT '0.000',
  subscription_unique_id int(11) NOT NULL DEFAULT '0',
  subscription_prorate tinyint(1) NOT NULL DEFAULT '1',
  allow_backorders tinyint(1) NOT NULL DEFAULT '0',
  backorder_fill_date varchar(255) NOT NULL DEFAULT '',
  shipping_class_id int(11) NOT NULL DEFAULT '0',
  show_custom_price_range tinyint(1) NOT NULL DEFAULT '0',
  price_range_low float(15,3) NOT NULL DEFAULT '0.000',
  price_range_high float(15,3) NOT NULL DEFAULT '0.000',
  square_id varchar(255) NOT NULL DEFAULT '',
  square_variation_id varchar(255) NOT NULL DEFAULT '',
  login_for_pricing tinyint(1) NOT NULL DEFAULT '0',
  login_for_pricing_user_level varchar(255) NOT NULL DEFAULT '',
  login_for_pricing_label varchar(255) NOT NULL DEFAULT '',
  sort_position int(11) NOT NULL DEFAULT '0',
  shopify_id varchar(255) NOT NULL DEFAULT '',
  ship_to_billing tinyint(1) NOT NULL DEFAULT '0',
  shipping_restriction tinyint(1) NOT NULL DEFAULT '0',
  enable_price_label int(11) NOT NULL DEFAULT 0,
  replace_price_label int(11) NOT NULL DEFAULT 0,
  custom_price_label varchar(512) NOT NULL DEFAULT '',
  stripe_product_id varchar(255) DEFAULT '',
  stripe_product_id_sandbox varchar(255) DEFAULT '',
  stripe_default_price_id varchar(255) DEFAULT '',
  stripe_default_price_id_sandbox varchar(255) DEFAULT '',
  is_preorder_type tinyint(1) NOT NULL DEFAULT 0,
  is_restaurant_type tinyint(1) NOT NULL DEFAULT 0,
  pickup_locations text NULL,
  is_bundle tinyint(1) NOT NULL DEFAULT '0',
  reorder_point int(11) NOT NULL DEFAULT '-1',
  PRIMARY KEY  (product_id),
  UNIQUE KEY product_product_id (product_id),
  KEY product_model_number (model_number($max_index_length)),
  KEY product_menulevel1_id_1 (menulevel1_id_1,menulevel2_id_1,menulevel3_id_1),
  KEY product_menulevel1_id_2 (menulevel1_id_2,menulevel2_id_2,menulevel3_id_2),
  KEY product_menulevel1_id_3 (menulevel1_id_3,menulevel2_id_3,menulevel3_id_3),
  KEY product_manufacturer_id (manufacturer_id),
  KEY product_option_id_1 (option_id_1),
  KEY product_option_id_2 (option_id_2),
  KEY product_option_id_3 (option_id_3),
  KEY product_option_id_4 (option_id_4),
  KEY product_option_id_5 (option_id_5),
  KEY idx_storefront_default (activate_in_store, role_id, sort_position),
  KEY idx_post_id (post_id),
  KEY product_active_price (activate_in_store,price)
) $collate;
CREATE TABLE ec_product_bundle (
  product_bundle_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL DEFAULT '0',
  pricing_mode varchar(20) NOT NULL DEFAULT 'fixed_price',
  discount_amount float(15,3) NOT NULL DEFAULT '0.000',
  discount_percentage float(15,3) NOT NULL DEFAULT '0.000',
  display_mode varchar(20) NOT NULL DEFAULT 'single_line',
  allow_component_edit tinyint(1) NOT NULL DEFAULT '0',
  stock_mode varchar(20) NOT NULL DEFAULT 'component',
  PRIMARY KEY  (product_bundle_id),
  UNIQUE KEY product_bundle_product_id (product_id)
) $collate;
CREATE TABLE ec_product_bundle_item (
  product_bundle_item_id int(11) NOT NULL AUTO_INCREMENT,
  product_bundle_id int(11) NOT NULL DEFAULT '0',
  component_product_id int(11) NOT NULL DEFAULT '0',
  quantity int(11) NOT NULL DEFAULT '1',
  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
  customer_selects_options tinyint(1) NOT NULL DEFAULT '0',
  price_allocation float(15,3) NOT NULL DEFAULT '0.000',
  sort_order int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (product_bundle_item_id),
  KEY product_bundle_item_bundle_id (product_bundle_id),
  KEY product_bundle_item_component (component_product_id)
) $collate;
CREATE TABLE ec_product_google_attributes (
  product_google_attribute_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL DEFAULT '0',
  attribute_value text,
  PRIMARY KEY  (product_google_attribute_id),
  UNIQUE KEY product_google_attribute_id (product_google_attribute_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_product_subscriber (
  product_subscriber_id int(11) NOT NULL AUTO_INCREMENT,
  email varchar(1024) NOT NULL DEFAULT '',
  product_id int(11) NOT NULL DEFAULT '0',
  status varchar(100) NOT NULL DEFAULT 'subscribed',
  last_notified datetime DEFAULT NULL,
  PRIMARY KEY  (product_subscriber_id),
  UNIQUE KEY product_subscriber_id (product_subscriber_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_promocode (
  promocode_id varchar($max_index_length) NOT NULL DEFAULT '',
  is_dollar_based tinyint(1) NOT NULL DEFAULT '0',
  is_percentage_based tinyint(1) NOT NULL DEFAULT '0',
  is_shipping_based tinyint(1) NOT NULL DEFAULT '0',
  is_free_item_based tinyint(1) NOT NULL DEFAULT '0',
  is_for_me_based tinyint(1) NOT NULL DEFAULT '0',
  is_bogo_based tinyint(1) NOT NULL DEFAULT '0',
  by_manufacturer_id tinyint(1) NOT NULL DEFAULT '0',
  by_category_id tinyint(1) NOT NULL DEFAULT '0',
  by_product_id tinyint(1) NOT NULL DEFAULT '0',
  by_all_products int(11) NOT NULL DEFAULT '0',
  promo_dollar float(15,3) NOT NULL DEFAULT '0.000',
  promo_percentage float(15,3) NOT NULL DEFAULT '0.000',
  promo_shipping float(15,3) NOT NULL DEFAULT '0.000',
  promo_free_item float(15,3) NOT NULL DEFAULT '0.000',
  promo_for_me float(15,3) NOT NULL DEFAULT '0.000',
  promo_bogo_dollar float(15,3) NOT NULL DEFAULT '0.000',
  promo_bogo_percentage float(15,3) NOT NULL DEFAULT '0.000',
  manufacturer_id int(11) NOT NULL DEFAULT '0',
  category_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  message blob NOT NULL,
  max_redemptions int(11) NOT NULL DEFAULT '999',
  times_redeemed int(11) NOT NULL DEFAULT '0',
  expiration_date datetime DEFAULT NULL,
  duration varchar(20) NOT NULL DEFAULT 'forever',
  duration_in_months int(11) NOT NULL DEFAULT '1',
  minimum_required int(11) NOT NULL DEFAULT '0',
  apply_to_shipping tinyint(1) NOT NULL DEFAULT '0',
  first_order_only tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (promocode_id),
  KEY promo_manufacturer_id (manufacturer_id),
  KEY promo_product_id (product_id)
) $collate;
CREATE TABLE ec_promotion (
  promotion_id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  type int(11) NOT NULL DEFAULT '0',
  start_date datetime NULL,
  end_date datetime NULL,
  product_id_1 int(11) NOT NULL DEFAULT '0',
  product_id_2 int(11) NOT NULL DEFAULT '0',
  product_id_3 int(11) NOT NULL DEFAULT '0',
  manufacturer_id_1 int(11) NOT NULL DEFAULT '0',
  manufacturer_id_2 int(11) NOT NULL DEFAULT '0',
  manufacturer_id_3 int(11) NOT NULL DEFAULT '0',
  category_id_1 int(11) NOT NULL DEFAULT '0',
  category_id_2 int(11) NOT NULL DEFAULT '0',
  category_id_3 int(11) NOT NULL DEFAULT '0',
  price1 float(15,3) NOT NULL DEFAULT '0.000',
  price2 float(15,3) NOT NULL DEFAULT '0.000',
  price3 float(15,3) NOT NULL DEFAULT '0.000',
  percentage1 float(15,3) NOT NULL DEFAULT '0.000',
  percentage2 float(15,3) NOT NULL DEFAULT '0.000',
  percentage3 float(15,3) NOT NULL DEFAULT '0.000',
  number1 int(11) NOT NULL DEFAULT '0',
  number2 int(11) NOT NULL DEFAULT '0',
  number3 int(11) NOT NULL DEFAULT '0',
  promo_limit int(11) NOT NULL DEFAULT '3',
  PRIMARY KEY  (promotion_id),
  UNIQUE KEY promotion_promotion_id (promotion_id),
  KEY promotion_product_id_1 (product_id_1),
  KEY promotion_product_id_2 (product_id_2),
  KEY promotion_product_id_3 (product_id_3),
  KEY promotion_manufacturer_id_1 (manufacturer_id_1),
  KEY promotion_manufacturer_id_2 (manufacturer_id_2),
  KEY promotion_manufacturer_id_3 (manufacturer_id_3),
  KEY promotion_category_id_1 (category_id_1),
  KEY promotion_category_id_2 (category_id_2),
  KEY promotion_category_id_3 (category_id_3)
) $collate;
CREATE TABLE ec_response (
  response_id int(11) NOT NULL AUTO_INCREMENT,
  is_error tinyint(1) NOT NULL DEFAULT '0',
  processor varchar(255) DEFAULT NULL,
  order_id int(11) DEFAULT NULL,
  response_time timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  response_text text,
  PRIMARY KEY  (response_id),
  KEY order_id (order_id),
  KEY response_response_time (response_time),
  KEY response_error_time (is_error,response_time),
  KEY response_processor (processor($max_index_length))
) $collate;
CREATE TABLE ec_review (
  review_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  approved tinyint(1) NOT NULL DEFAULT '0',
  rating int(2) NOT NULL DEFAULT '0',
  title varchar(255) NOT NULL DEFAULT '',
  description mediumblob NOT NULL,
  date_submitted timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewer_name varchar(255) NOT NULL DEFAULT '',
  reply_text text,
  reply_date datetime DEFAULT NULL,
  reply_user_id int(11) NOT NULL DEFAULT '0',
  verified tinyint(1) NOT NULL DEFAULT '0',
  request_id int(11) NOT NULL DEFAULT '0',
  reviewer_email varchar(255) NOT NULL DEFAULT '',
  held_reason varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (review_id),
  UNIQUE KEY review_id (review_id),
  KEY product_id (product_id),
  KEY user_id (user_id),
  KEY idx_product_approved (product_id, approved)
) $collate;
CREATE TABLE ec_review_request (
  request_id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL DEFAULT '0',
  product_id int(11) NOT NULL DEFAULT '0',
  user_id int(11) NOT NULL DEFAULT '0',
  email varchar(255) NOT NULL DEFAULT '',
  first_name varchar(255) NOT NULL DEFAULT '',
  token varchar(64) NOT NULL DEFAULT '',
  scheduled_at datetime DEFAULT NULL,
  sent_at datetime DEFAULT NULL,
  reminded_at datetime DEFAULT NULL,
  opened_at datetime DEFAULT NULL,
  reviewed_at datetime DEFAULT NULL,
  review_id int(11) NOT NULL DEFAULT '0',
  unsubscribed tinyint(1) NOT NULL DEFAULT '0',
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (request_id),
  UNIQUE KEY token (token),
  KEY order_id (order_id),
  KEY product_id (product_id),
  KEY email_product (email(100), product_id),
  KEY scheduled_at (scheduled_at)
) $collate;
CREATE TABLE ec_role (
  role_id int(11) NOT NULL AUTO_INCREMENT,
  role_label varchar($max_index_length) NOT NULL DEFAULT '',
  admin_access tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (role_id),
  UNIQUE KEY role_id (role_id),
  KEY role_label (role_label)
) $collate;
CREATE TABLE ec_roleaccess (
  roleaccess_id int(11) NOT NULL AUTO_INCREMENT,
  role_label varchar($max_index_length) NOT NULL DEFAULT '',
  admin_panel varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (roleaccess_id),
  UNIQUE KEY roleaccess_id (roleaccess_id),
  KEY role_label (role_label)
) $collate;
CREATE TABLE ec_roleprice (
  roleprice_id int(11) NOT NULL AUTO_INCREMENT,
  product_id int(11) NOT NULL DEFAULT '0',
  role_label varchar($max_index_length) NOT NULL DEFAULT '',
  role_price float(15,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY  (roleprice_id),
  UNIQUE KEY roleprice_id (roleprice_id),
  KEY product_id (product_id),
  KEY role_label (role_label),
  KEY idx_product_role (product_id, role_label(100))
) $collate;
CREATE TABLE ec_schedule (
  schedule_id int(11) NOT NULL AUTO_INCREMENT,
  schedule_label varchar(255) DEFAULT '',
  day_of_week varchar(20) NOT NULL DEFAULT '',
  is_holiday tinyint(1) NOT NULL DEFAULT 1,
  holiday_date date DEFAULT NULL,
  apply_to_retail tinyint(1) NOT NULL DEFAULT 0,
  apply_to_preorder tinyint(1) NOT NULL DEFAULT 0,
  apply_to_restaurant tinyint(1) NOT NULL DEFAULT 0,
  retail_start varchar(32) NOT NULL DEFAULT '',
  retail_end varchar(32) NOT NULL DEFAULT '',
  preorder_start varchar(32) NOT NULL DEFAULT '',
  preorder_end varchar(32) NOT NULL DEFAULT '',
  preorder_open_time varchar(32) NOT NULL DEFAULT '',
  preorder_close_time varchar(32) NOT NULL DEFAULT '',
  restaurant_start varchar(32) NOT NULL DEFAULT '',
  restaurant_end varchar(32) NOT NULL DEFAULT '',
  retail_closed tinyint(1) NOT NULL DEFAULT 0,
  preorder_closed tinyint(1) NOT NULL DEFAULT 0,
  restaurant_closed tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (schedule_id),
  UNIQUE KEY schedule_id (schedule_id)
) $collate;
CREATE TABLE ec_setting (
  setting_id int(11) NOT NULL AUTO_INCREMENT,
  site_url varchar(255) NOT NULL DEFAULT '',
  reg_code varchar(255) NOT NULL DEFAULT '',
  storeversion varchar(20) NOT NULL DEFAULT '',
  storetype varchar(20) NOT NULL DEFAULT 'wordpress',
  storepage varchar(255) NOT NULL DEFAULT 'store',
  cartpage varchar(255) NOT NULL DEFAULT 'cart',
  accountpage varchar(255) NOT NULL DEFAULT 'account',
  timezone varchar(255) NOT NULL DEFAULT 'Europe/London',
  shipping_method varchar(255) NOT NULL DEFAULT 'method',
  shipping_expedite_rate float(11,2) NOT NULL DEFAULT '0.00',
  shipping_handling_rate float(11,2) NOT NULL DEFAULT '0.00',
  ups_access_license_number varchar(255) NOT NULL DEFAULT '',
  ups_user_id varchar(255) NOT NULL DEFAULT '',
  ups_password varchar(255) NOT NULL DEFAULT '',
  ups_ship_from_zip varchar(20) NOT NULL DEFAULT '',
  ups_shipper_number varchar(20) NOT NULL DEFAULT '',
  ups_country_code varchar(9) NOT NULL DEFAULT 'US',
  ups_weight_type varchar(19) NOT NULL DEFAULT 'LBS',
  ups_conversion_rate float(9,3) NOT NULL DEFAULT '1.000',
  usps_user_name varchar(255) NOT NULL DEFAULT '',
  usps_ship_from_zip varchar(20) NOT NULL DEFAULT '',
  fedex_key varchar(255) NOT NULL DEFAULT '',
  fedex_account_number varchar(255) NOT NULL DEFAULT '',
  fedex_meter_number varchar(255) NOT NULL DEFAULT '',
  fedex_password varchar(255) NOT NULL DEFAULT '',
  fedex_ship_from_zip varchar(255) NOT NULL DEFAULT '',
  fedex_weight_units varchar(20) NOT NULL DEFAULT 'LB',
  fedex_country_code varchar(20) NOT NULL DEFAULT 'US',
  fedex_conversion_rate float(9,3) NOT NULL DEFAULT '1.000',
  fedex_test_account tinyint(1) NOT NULL DEFAULT '0',
  auspost_api_key varchar(255) NOT NULL DEFAULT '',
  auspost_ship_from_zip varchar(55) NOT NULL DEFAULT '',
  dhl_site_id varchar(155) NOT NULL DEFAULT '',
  dhl_password varchar(155) NOT NULL DEFAULT '',
  dhl_ship_from_country varchar(25) NOT NULL DEFAULT 'US',
  dhl_ship_from_zip varchar(64) NOT NULL DEFAULT '',
  dhl_weight_unit varchar(20) NOT NULL DEFAULT 'LB',
  dhl_test_mode tinyint(1) NOT NULL DEFAULT '0',
  fraktjakt_customer_id varchar(64) NOT NULL DEFAULT '',
  fraktjakt_login_key varchar(64) NOT NULL DEFAULT '',
  fraktjakt_conversion_rate DOUBLE(15,3) NOT NULL DEFAULT '1.000',
  fraktjakt_test_mode tinyint(1) NOT NULL DEFAULT '0',
  fraktjakt_address varchar(120) NOT NULL DEFAULT '',
  fraktjakt_city varchar(55) NOT NULL DEFAULT '',
  fraktjakt_state varchar(2) NOT NULL DEFAULT '',
  fraktjakt_zip varchar(20) NOT NULL DEFAULT '',
  fraktjakt_country varchar(2) NOT NULL DEFAULT '',
  ups_ship_from_state varchar(2) NOT NULL DEFAULT '',
  ups_negotiated_rates tinyint(1) NOT NULL DEFAULT '0',
  canadapost_username varchar(255) NOT NULL DEFAULT '',
  canadapost_password varchar(255) NOT NULL DEFAULT '',
  canadapost_customer_number varchar(255) NOT NULL DEFAULT '',
  canadapost_contract_id varchar(255) NOT NULL DEFAULT '',
  canadapost_test_mode tinyint(1) NOT NULL DEFAULT '0',
  canadapost_ship_from_zip varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY  (setting_id)
) $collate;
CREATE TABLE ec_shipping_class (
  shipping_class_id int(11) NOT NULL AUTO_INCREMENT,
  class_name varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (shipping_class_id)
) $collate;
CREATE TABLE ec_shipping_class_to_rate (
  shipping_class_to_rate_id int(11) NOT NULL AUTO_INCREMENT,
  shipping_class_id int(11) NOT NULL DEFAULT '0',
  shipping_rate_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (shipping_class_to_rate_id)
) $collate;
CREATE TABLE ec_shippingrate (
  shippingrate_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  zone_id int(11) NOT NULL DEFAULT '0',
  is_price_based tinyint(1) NOT NULL DEFAULT '0',
  is_weight_based tinyint(1) NOT NULL DEFAULT '0',
  is_method_based tinyint(1) NOT NULL DEFAULT '0',
  is_quantity_based tinyint(1) NOT NULL DEFAULT '0',
  is_percentage_based tinyint(1) NOT NULL DEFAULT '0',
  is_ups_based tinyint(1) NOT NULL DEFAULT '0',
  is_usps_based tinyint(1) NOT NULL DEFAULT '0',
  is_fedex_based tinyint(1) NOT NULL DEFAULT '0',
  is_auspost_based tinyint(1) NOT NULL DEFAULT '0',
  is_dhl_based tinyint(1) NOT NULL DEFAULT '0',
  is_canadapost_based tinyint(1) NOT NULL DEFAULT '0',
  trigger_rate float(15,3) NOT NULL DEFAULT '0.000',
  shipping_rate float(15,3) NOT NULL DEFAULT '0.000',
  shipping_label varchar(255) NOT NULL DEFAULT '',
  shipping_order int(11) NOT NULL DEFAULT '0',
  shipping_code varchar(255) NOT NULL DEFAULT '',
  shipping_override_rate float(11,3) NULL,
  free_shipping_at float(15,3) NOT NULL DEFAULT '-1.000',
  PRIMARY KEY  (shippingrate_id),
  UNIQUE KEY shippingrate_id (shippingrate_id),
  KEY zone_id (zone_id)
) $collate;
CREATE TABLE ec_state (
  id_sta int(11) NOT NULL AUTO_INCREMENT,
  idcnt_sta int(11) NOT NULL DEFAULT '0',
  code_sta varchar($max_index_length) NOT NULL DEFAULT '',
  name_sta varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT '0',
  group_sta varchar(255) NOT NULL DEFAULT '',
  ship_to_active tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY  (id_sta),
  KEY idcnt_sta (idcnt_sta),
  KEY code_sta (code_sta)
) $collate;
CREATE TABLE ec_subscriber (
  subscriber_id int(11) NOT NULL AUTO_INCREMENT,
  email text NULL,
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (subscriber_id),
  UNIQUE KEY subscriber_email (email($max_index_length))
) $collate;
CREATE TABLE ec_subscription (
  subscription_id int(11) NOT NULL AUTO_INCREMENT,
  subscription_type varchar(255) NOT NULL DEFAULT 'paypal',
  subscription_status varchar(255) NOT NULL DEFAULT 'Active',
  title text NULL,
  user_id int(11) NOT NULL DEFAULT '0',
  email text NULL,
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  user_country varchar(255) NOT NULL DEFAULT 'US',
  product_id int(11) NOT NULL DEFAULT '0',
  model_number varchar(510) NOT NULL DEFAULT '',
  price double(21,3) NOT NULL DEFAULT '0.000',
  payment_length int(11) NOT NULL DEFAULT '1',
  payment_period varchar(255) NOT NULL DEFAULT '',
  payment_duration int(11) NOT NULL DEFAULT '0',
  start_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_payment_date varchar(255) NOT NULL DEFAULT '',
  next_payment_date varchar(255) NOT NULL DEFAULT '',
  number_payments_completed int(11) NOT NULL DEFAULT '1',
  paypal_txn_id varchar(255) NOT NULL DEFAULT '',
  paypal_txn_type varchar(255) NOT NULL DEFAULT '',
  paypal_subscr_id varchar(255) NOT NULL DEFAULT '',
  paypal_username varchar(255) NOT NULL DEFAULT '',
  paypal_password varchar(255) NOT NULL DEFAULT '',
  stripe_subscription_id varchar(255) NOT NULL DEFAULT '',
  quantity int(11) NOT NULL DEFAULT '1',
  num_failed_payment int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (subscription_id),
  UNIQUE KEY subscription_id (subscription_id),
  KEY user_id (user_id),
  KEY product_id (product_id)
) $collate;
CREATE TABLE ec_subscription_plan (
  subscription_plan_id int(11) NOT NULL AUTO_INCREMENT,
  plan_title varchar(255) NOT NULL DEFAULT '',
  can_downgrade tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (subscription_plan_id)
) $collate;
CREATE TABLE ec_taxrate (
  taxrate_id int(11) NOT NULL AUTO_INCREMENT,
  tax_by_state tinyint(1) NOT NULL DEFAULT '0',
  tax_by_country tinyint(1) NOT NULL DEFAULT '0',
  tax_by_duty tinyint(1) NOT NULL DEFAULT '0',
  tax_by_vat tinyint(1) NOT NULL DEFAULT '0',
  tax_by_single_vat tinyint(1) NOT NULL DEFAULT '0',
  tax_by_all tinyint(1) NOT NULL DEFAULT '0',
  state_rate float(15,3) NOT NULL DEFAULT '0.000',
  country_rate float(15,3) NOT NULL DEFAULT '0.000',
  duty_rate float(15,3) NOT NULL DEFAULT '0.000',
  vat_rate float(15,3) NOT NULL DEFAULT '0.000',
  vat_added tinyint(1) NOT NULL DEFAULT '0',
  vat_included tinyint(1) NOT NULL DEFAULT '0',
  all_rate float(15,3) NOT NULL DEFAULT '0.000',
  state_code varchar(50) NOT NULL DEFAULT '',
  country_code varchar(50) NOT NULL DEFAULT '',
  vat_country_code varchar(50) NOT NULL DEFAULT '',
  duty_exempt_country_code varchar(50) NOT NULL DEFAULT '',
  stripe_taxrate_id varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (taxrate_id),
  UNIQUE KEY taxrate_id (taxrate_id),
  KEY state_code (state_code),
  KEY country_code (country_code),
  KEY vat_country_code (vat_country_code),
  KEY duty_exempt_country_code (duty_exempt_country_code)
) $collate;
CREATE TABLE ec_tempcart (
  tempcart_id int(11) NOT NULL AUTO_INCREMENT,
  session_id varchar(100) DEFAULT NULL,
  product_id int(11) NOT NULL DEFAULT '0',
  quantity int(11) DEFAULT '0',
  grid_quantity int(11) DEFAULT '0',
  gift_card_message blob,
  gift_card_from_name varchar(255) DEFAULT NULL,
  gift_card_to_name varchar(255) DEFAULT NULL,
  optionitem_id_1 int(11) NOT NULL DEFAULT '0',
  optionitem_id_2 int(11) NOT NULL DEFAULT '0',
  optionitem_id_3 int(11) NOT NULL DEFAULT '0',
  optionitem_id_4 int(11) NOT NULL DEFAULT '0',
  optionitem_id_5 int(11) NOT NULL DEFAULT '0',
  donation_price float(15,3) NOT NULL DEFAULT '0.000',
  last_changed_date timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_deconetwork tinyint(1) NOT NULL DEFAULT '0',
  deconetwork_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_name varchar(255) NOT NULL DEFAULT '',
  deconetwork_product_code varchar(64) NOT NULL DEFAULT '',
  deconetwork_options varchar(255) NOT NULL DEFAULT '',
  deconetwork_edit_link varchar(255) NOT NULL DEFAULT '',
  deconetwork_color_code varchar(64) NOT NULL DEFAULT '',
  deconetwork_product_id varchar(64) NOT NULL DEFAULT '',
  deconetwork_image_link varchar(255) NOT NULL DEFAULT '',
  deconetwork_discount float(15,3) NOT NULL DEFAULT '0.000',
  deconetwork_tax float(15,3) NOT NULL DEFAULT '0.000',
  deconetwork_total float(15,3) NOT NULL DEFAULT '0.000',
  deconetwork_version int(11) NOT NULL DEFAULT '1',
  gift_card_email varchar(255) NOT NULL DEFAULT '',
  abandoned_cart_email_sent int(11) NOT NULL DEFAULT '0',
  hide_from_admin tinyint(1) NOT NULL DEFAULT '0',
  bundle_group_key varchar(64) NOT NULL DEFAULT '',
  bundle_product_id int(11) NOT NULL DEFAULT '0',
  free_gift_offer_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (tempcart_id),
  UNIQUE KEY tempcart_tempcart_id (tempcart_id),
  KEY tempcart_bundle_group (bundle_group_key),
  KEY tempcart_session_id (session_id),
  KEY tempcart_product_id (product_id),
  KEY tempcart_optionitem_id_1 (optionitem_id_1),
  KEY tempcart_optionitem_id_2 (optionitem_id_2),
  KEY tempcart_optionitem_id_3 (optionitem_id_3),
  KEY tempcart_optionitem_id_4 (optionitem_id_4),
  KEY tempcart_optionitem_id_5 (optionitem_id_5),
  KEY tempcart_last_changed_date (last_changed_date)
) $collate;
CREATE TABLE ec_tempcart_data (
  tempcart_data_id int(11) NOT NULL AUTO_INCREMENT,
  tempcart_time timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  session_id varchar(100) NOT NULL DEFAULT '',
  user_id varchar(255) NOT NULL DEFAULT '',
  email varchar(255) NOT NULL DEFAULT '',
  username varchar(255) NOT NULL DEFAULT '',
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  tip_amount float(15,3) NOT NULL DEFAULT '0.000',
  tip_rate varchar(10) NOT NULL DEFAULT '0.000',
  coupon_code varchar(255) NOT NULL DEFAULT '',
  giftcard varchar(255) NOT NULL DEFAULT '',
  billing_first_name varchar(255) NOT NULL DEFAULT '',
  billing_last_name varchar(255) NOT NULL DEFAULT '',
  billing_company_name varchar(255) NOT NULL DEFAULT '',
  billing_address_line_1 varchar(255) NOT NULL DEFAULT '',
  billing_address_line_2 varchar(255) NOT NULL DEFAULT '',
  billing_city varchar(255) NOT NULL DEFAULT '',
  billing_state varchar(255) NOT NULL DEFAULT '',
  billing_zip varchar(255) NOT NULL DEFAULT '',
  billing_country varchar(255) NOT NULL DEFAULT '',
  billing_phone varchar(255) NOT NULL DEFAULT '',
  shipping_selector varchar(255) NOT NULL DEFAULT '',
  shipping_first_name varchar(255) NOT NULL DEFAULT '',
  shipping_last_name varchar(255) NOT NULL DEFAULT '',
  shipping_company_name varchar(255) NOT NULL DEFAULT '',
  shipping_address_line_2 varchar(255) NOT NULL DEFAULT '',
  shipping_address_line_1 varchar(255) NOT NULL DEFAULT '',
  shipping_city varchar(255) NOT NULL DEFAULT '',
  shipping_state varchar(255) NOT NULL DEFAULT '',
  shipping_zip varchar(255) NOT NULL DEFAULT '',
  shipping_country varchar(255) NOT NULL DEFAULT '',
  shipping_phone varchar(255) NOT NULL DEFAULT '',
  create_account varchar(255) NOT NULL DEFAULT '',
  order_notes text,
  shipping_method varchar(255) NOT NULL DEFAULT '',
  estimate_shipping_zip varchar(255) NOT NULL DEFAULT '',
  expedited_shipping varchar(255) NOT NULL DEFAULT '',
  estimate_shipping_country varchar(255) NOT NULL DEFAULT '',
  is_guest varchar(255) NOT NULL DEFAULT '',
  guest_key varchar(255) NOT NULL DEFAULT '',
  subscription_option1 text,
  subscription_option2 text,
  subscription_option3 text,
  subscription_option4 text,
  subscription_option5 text,
  subscription_advanced_option text,
  subscription_quantity varchar(255) NOT NULL DEFAULT '',
  convert_to varchar(255) NOT NULL DEFAULT '',
  translate_to varchar(255) NOT NULL DEFAULT '',
  taxcloud_tax_amount varchar(255) NOT NULL DEFAULT '',
  taxcloud_address_last_verified text,
  taxcloud_address_verified tinyint(1) NOT NULL DEFAULT '0',
  taxjar_tax_amount varchar(255) NOT NULL DEFAULT '',
  taxjar_address_verified tinyint(1) NOT NULL DEFAULT '0',
  perpage varchar(255) NOT NULL DEFAULT '',
  vat_registration_number varchar(255) NOT NULL DEFAULT '',
  card_error varchar(255) NOT NULL DEFAULT '',
  payment_type varchar(255) NOT NULL DEFAULT '',
  payment_method varchar(255) NOT NULL DEFAULT '',
  stripe_paymentintent_id varchar(255) NOT NULL DEFAULT '',
  stripe_pi_client_secret varchar(255) NOT NULL DEFAULT '',
  amazon_session_id varchar(255) NOT NULL DEFAULT '',
  amazon_buyer_id varchar(255) NOT NULL DEFAULT '',
  amazon_payment_selection varchar(255) NOT NULL DEFAULT '',
  email_other varchar(255) NOT NULL DEFAULT '',
  stripe_last_pi_data text,
  pickup_date varchar(32) NOT NULL DEFAULT '',
  pickup_asap tinyint(1) NOT NULL DEFAULT 1,
  pickup_time varchar(32) NOT NULL DEFAULT '',
  pickup_location int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (tempcart_data_id)
) $collate;
CREATE TABLE ec_tempcart_offer (
  tempcart_offer_id int(11) NOT NULL AUTO_INCREMENT,
  session_id varchar(100) NOT NULL DEFAULT '',
  offer_id int(11) NOT NULL DEFAULT '0',
  offer_code_id int(11) NOT NULL DEFAULT '0',
  code varchar($max_index_length) NOT NULL DEFAULT '',
  applied_date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (tempcart_offer_id),
  KEY tempcart_offer_session_id (session_id),
  KEY tempcart_offer_offer_id (offer_id)
) $collate;
CREATE TABLE ec_tempcart_optionitem (
  tempcart_optionitem_id int(11) NOT NULL AUTO_INCREMENT,
  tempcart_id int(11) NOT NULL DEFAULT '0',
  option_id int(11) NOT NULL DEFAULT '0',
  optionitem_id int(11) NOT NULL DEFAULT '0',
  optionitem_value text NOT NULL,
  session_id varchar(100) NOT NULL DEFAULT '',
  optionitem_model_number text NULL,
  PRIMARY KEY  (tempcart_optionitem_id),
  UNIQUE KEY tempcart_optionitem_id (tempcart_optionitem_id),
  KEY tempcart_id (tempcart_id),
  KEY option_id (option_id),
  KEY optionitem_id (optionitem_id),
  KEY session_id (session_id)
) $collate;
CREATE TABLE ec_timezone (
  timezone_id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  identifier varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (timezone_id),
  UNIQUE KEY timezone_id (timezone_id)
) $collate;
CREATE TABLE ec_user (
  user_id int(11) NOT NULL AUTO_INCREMENT,
  is_demo_item tinyint(1) NOT NULL DEFAULT '0',
  email varchar(255) NOT NULL DEFAULT '',
  password varchar(255) NOT NULL DEFAULT '',
  password_admin_v1 varchar(32) NOT NULL DEFAULT '',
  list_id varchar(255) NOT NULL DEFAULT '',
  edit_sequence varchar(255) NOT NULL DEFAULT '',
  quickbooks_status varchar(255) NOT NULL DEFAULT 'Not Queued',
  first_name varchar(255) NOT NULL DEFAULT '',
  last_name varchar(255) NOT NULL DEFAULT '',
  default_billing_address_id int(11) NOT NULL DEFAULT '0',
  default_shipping_address_id int(11) NOT NULL DEFAULT '0',
  user_level varchar(255) NOT NULL DEFAULT 'shopper',
  is_subscriber tinyint(1) NOT NULL DEFAULT '0',
  realauth_registered tinyint(1) NOT NULL DEFAULT '0',
  stripe_customer_id varchar(255) NOT NULL DEFAULT '',
  default_card_type varchar(255) NOT NULL DEFAULT '',
  default_card_last4 varchar(255) NOT NULL DEFAULT '',
  exclude_tax tinyint(1) NOT NULL DEFAULT '0',
  exclude_shipping tinyint(1) NOT NULL DEFAULT '0',
  user_notes text,
  vat_registration_number varchar(255) NOT NULL DEFAULT '',
  email_other varchar(255) NOT NULL DEFAULT '',
  allow_shipping_bypass tinyint(1) NOT NULL DEFAULT 0,
  is_stripe_test_user tinyint(1) NOT NULL DEFAULT 0,
  lifetime_spend float(15,3) NOT NULL DEFAULT '0.000',
  completed_order_count int(11) NOT NULL DEFAULT '0',
  last_order_date datetime DEFAULT NULL,
  history_aggregates_built tinyint(1) NOT NULL DEFAULT 0,
  date_created timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  last_login datetime DEFAULT NULL,
  PRIMARY KEY  (user_id),
  UNIQUE KEY user_user_id (user_id),
  UNIQUE KEY user_email (email($max_index_length)),
  KEY user_password (password($max_index_length)),
  KEY user_default_billing_address_id (default_billing_address_id),
  KEY user_default_shipping_address_id (default_shipping_address_id),
  KEY user_user_level (user_level($max_index_length)),
  KEY user_date_created (date_created),
  KEY user_last_order_date (last_order_date)
) $collate;
CREATE TABLE ec_user_activity (
  activity_id bigint(20) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL DEFAULT '0',
  activity_type varchar(50) NOT NULL DEFAULT '',
  activity_date datetime DEFAULT NULL,
  object_type varchar(50) NOT NULL DEFAULT '',
  object_id bigint(20) NOT NULL DEFAULT '0',
  meta text,
  actor_type varchar(20) NOT NULL DEFAULT '',
  actor_id bigint(20) NOT NULL DEFAULT '0',
  ip_address varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY  (activity_id),
  KEY user_activity_user_date (user_id,activity_date),
  KEY user_activity_type (activity_type)
) $collate;
CREATE TABLE ec_user_note (
  note_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL DEFAULT '0',
  wp_user_id int(11) NOT NULL DEFAULT '0',
  note text,
  created datetime DEFAULT NULL,
  pinned tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY  (note_id),
  KEY user_note_user (user_id)
) $collate;
CREATE TABLE ec_user_tag (
  tag_id int(11) NOT NULL AUTO_INCREMENT,
  tag_label varchar(100) NOT NULL DEFAULT '',
  tag_color varchar(7) NOT NULL DEFAULT '#6b7280',
  PRIMARY KEY  (tag_id),
  UNIQUE KEY user_tag_label (tag_label)
) $collate;
CREATE TABLE ec_user_to_tag (
  user_to_tag_id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL DEFAULT '0',
  tag_id int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY  (user_to_tag_id),
  UNIQUE KEY user_to_tag_pair (user_id,tag_id),
  KEY user_to_tag_tag (tag_id)
) $collate;
CREATE TABLE ec_webhook (
  webhook_id varchar($max_index_length) NOT NULL,
  webhook_type varchar(128) NOT NULL DEFAULT '',
  webhook_data blob,
  PRIMARY KEY  (webhook_id),
  UNIQUE KEY webhook_id (webhook_id)
) $collate;
CREATE TABLE ec_zone (
  zone_id int(11) NOT NULL AUTO_INCREMENT,
  zone_name varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (zone_id)
) $collate;
CREATE TABLE ec_zone_to_location (
  zone_to_location_id int(11) NOT NULL AUTO_INCREMENT,
  zone_id int(11) NOT NULL DEFAULT '0',
  iso2_cnt varchar(20) NOT NULL DEFAULT '',
  code_sta varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY  (zone_to_location_id)
) $collate;";
		/* dbDelta treats an empty line inside a CREATE TABLE body as an index definition and
		   emits "Undefined array key index_type / index_name / index_columns / column_name"
		   warnings. Strip blank lines and trailing whitespace so a stray newline in the
		   schema can never reach it. */
		$schema = implode( "\n", array_filter( array_map( 'rtrim', explode( "\n", $schema ) ), 'strlen' ) );
		return $schema;
	}

	public function install_base_data( ){
		global $wpdb;
		$wpdb->insert( 
			"ec_setting",
			array( 
				"setting_id" => "1",
				"site_url" => "",
				"reg_code" => "",
				"storeversion" => "1.0.0",
				"storetype" => "wordpress",
				"storepage" => "6",
				"cartpage" => "7",
				"accountpage" => "8",
				"timezone" => "America/Los_Angeles",
				"shipping_method" => "price",
				"shipping_expedite_rate" => "0",
				"shipping_handling_rate" => "0",
				"ups_access_license_number" => "",
				"ups_user_id" => "",
				"ups_password" => "",
				"ups_ship_from_zip" => "",
				"ups_shipper_number" => "",
				"ups_country_code" => "",
				"ups_weight_type" => "",
				"ups_conversion_rate" => "1.000",
				"usps_user_name" => "",
				"usps_ship_from_zip" => "",
				"fedex_key" => "",
				"fedex_account_number" => "",
				"fedex_meter_number" => "",
				"fedex_password" => "",
				"fedex_ship_from_zip" => "",
				"fedex_weight_units" => "LB",
				"fedex_country_code" => "US",
				"fedex_conversion_rate" => "1.000",
				"fedex_test_account" => "0",
				"auspost_api_key" => "",
				"auspost_ship_from_zip" => "",
				"dhl_site_id" => "",
				"dhl_password" => "",
				"dhl_ship_from_country" => "",
				"dhl_ship_from_zip" => "",
				"dhl_weight_unit" => "",
				"dhl_test_mode" => "0",
				"fraktjakt_customer_id" => "",
				"fraktjakt_login_key" => "",
				"fraktjakt_conversion_rate" => "1.000",
				"fraktjakt_test_mode" => "0"
			)
		);

		$wpdb->insert( 
			"ec_shippingrate",
			array( 
				"shippingrate_id" => "51",
				"is_price_based" => "1",
				"is_weight_based" => "0",
				"is_method_based" => "0",
				"is_ups_based" => "0",
				"is_usps_based" => "0",
				"is_fedex_based" => "0",
				"trigger_rate" => "0",
				"shipping_rate" => "5",
				"shipping_label" => "",
				"shipping_order" => "0",
				"shipping_code" => "",
				"shipping_override_rate" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "1",
				"schedule_label" => "Sunday",
				"day_of_week" => "SUN",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "2",
				"schedule_label" => "Monday",
				"day_of_week" => "MON",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "3",
				"schedule_label" => "Tuesday",
				"day_of_week" => "TUE",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "4",
				"schedule_label" => "Wednesday",
				"day_of_week" => "WED",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "5",
				"schedule_label" => "Thursday",
				"day_of_week" => "THU",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "6",
				"schedule_label" => "Friday",
				"day_of_week" => "FRI",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);

		$wpdb->insert( 
			"ec_schedule",
			array( 
				"schedule_id" => "7",
				"schedule_label" => "Saturday",
				"day_of_week" => "SAT",
				"is_holiday" => "0",
				"apply_to_retail" => "0",
				"apply_to_preorder" => "1",
				"apply_to_restaurant" => "1",
				"retail_start" => "0",
				"retail_end" => "0",
				"preorder_start" => "02:00:00:00",
				"preorder_end" => "00:03:00:00",
				"preorder_open_time" => "08:00",
				"preorder_close_time" => "17:00",
				"restaurant_start" => "08:00",
				"restaurant_end" => "21:00",
				"retail_closed" => "0",
				"preorder_closed" => "0",
				"restaurant_closed" => "0"
			)
		);


		/* Countries and regions come from inc/classes/core/ec_default_countries_states.php ( shared with the
		   "Restore default countries & regions" admin action ). A fresh store ships everywhere, as it always has. */
		$this->restore_default_countries_and_states( 1 );

		$wpdb->insert(
			"ec_orderstatus",
			array(
				"status_id" => "1",
				"order_status" => "Status Not Found",
				"is_approved" => "0",
				"color_code" => "#999999",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "2",
				"order_status" => "Order Shipped",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "3",
				"order_status" => "Order Confirmed",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "4",
				"order_status" => "Order on Hold",
				"is_approved" => "0",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "5",
				"order_status" => "Order Started",
				"is_approved" => "0",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "6",
				"order_status" => "Card Approved",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "7",
				"order_status" => "Card Denied",
				"is_approved" => "0",
				"color_code" => "#FF3030",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "8",
				"order_status" => "Third Party Pending",
				"is_approved" => "0",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "9",
				"order_status" => "Third Party Error",
				"is_approved" => "0",
				"color_code" => "#FF3030",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "10",
				"order_status" => "Third Party Approved",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "11",
				"order_status" => "Ready for Pickup",
				"is_approved" => "1",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "12",
				"order_status" => "Pending Approval",
				"is_approved" => "0",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "14",
				"order_status" => "Direct Deposit Pending",
				"is_approved" => "0",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "15",
				"order_status" => "Direct Deposit Received",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "16",
				"order_status" => "Refunded Order",
				"is_approved" => "0",
				"color_code" => "#FF3030",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "17",
				"order_status" => "Partial Refund",
				"is_approved" => "1",
				"color_code" => "#DD9933",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "18",
				"order_status" => "Order Picked Up",
				"is_approved" => "1",
				"color_code" => "#81D742",
			)
		);

		$wpdb->insert( 
			"ec_orderstatus",
			array(
				"status_id" => "19",
				"order_status" => "Order Cancelled",
				"is_approved" => "0",
				"color_code" => "#FF3030",
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "1",
				"name" => "(GMT-12:00) International Date Line West",
				"identifier" => "Pacific/Wake"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "2",
				"name" => "(GMT-11:00) Midway Island",
				"identifier" => "Pacific/Apia"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "3",
				"name" => "(GMT-11:00) Samoa",
				"identifier" => "Pacific/Apia"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "4",
				"name" => "(GMT-10:00) Hawaii",
				"identifier" => "Pacific/Honolulu"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "5",
				"name" => "(GMT-09:00) Alaska",
				"identifier" => "America/Anchorage"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "6",
				"name" => "(GMT-08:00) Pacific Time (US & Canada) Tijuana",
				"identifier" => "America/Los_Angeles"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "7",
				"name" => "(GMT-07:00) Arizona",
				"identifier" => "America/Phoenix"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "8",
				"name" => "(GMT-07:00) Chihuahua",
				"identifier" => "America/Chihuahua"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "9",
				"name" => "(GMT-07:00) La Paz",
				"identifier" => "America/Chihuahua"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "10",
				"name" => "(GMT-07:00) Mazatlan",
				"identifier" => "America/Chihuahua"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "11",
				"name" => "(GMT-07:00) Mountain Time (US & Canada)",
				"identifier" => "America/Denver"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "12",
				"name" => "(GMT-06:00) Central America",
				"identifier" => "America/Managua"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "13",
				"name" => "(GMT-06:00) Central Time (US & Canada)",
				"identifier" => "America/Chicago"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "14",
				"name" => "(GMT-06:00) Guadalajara",
				"identifier" => "America/Mexico_City"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "15",
				"name" => "(GMT-06:00) Mexico City",
				"identifier" => "America/Mexico_City"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "16",
				"name" => "(GMT-06:00) Monterrey",
				"identifier" => "America/Mexico_City"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "17",
				"name" => "(GMT-06:00) Saskatchewan",
				"identifier" => "America/Regina"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "18",
				"name" => "(GMT-05:00) Bogota",
				"identifier" => "America/Bogota"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "19",
				"name" => "(GMT-05:00) Eastern Time (US & Canada)",
				"identifier" => "America/New_York"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "20",
				"name" => "(GMT-05:00) Indiana (East)",
				"identifier" => "America/Indiana/Indianapolis"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "21",
				"name" => "(GMT-05:00) Lima",
				"identifier" => "America/Bogota"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "22",
				"name" => "(GMT-05:00) Quito",
				"identifier" => "America/Bogota"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "23",
				"name" => "(GMT-04:00) Atlantic Time (Canada)",
				"identifier" => "America/Halifax"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "24",
				"name" => "(GMT-04:00) Caracas",
				"identifier" => "America/Caracas"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "25",
				"name" => "(GMT-04:00) La Paz",
				"identifier" => "America/Caracas"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "26",
				"name" => "(GMT-04:00) Santiago",
				"identifier" => "America/Santiago"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "27",
				"name" => "(GMT-03:30) Newfoundland",
				"identifier" => "America/St_Johns"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "28",
				"name" => "(GMT-03:00) Brasilia",
				"identifier" => "America/Sao_Paulo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "29",
				"name" => "(GMT-03:00) Buenos Aires",
				"identifier" => "America/Argentina/Buenos_Aires"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "30",
				"name" => "(GMT-03:00) Georgetown",
				"identifier" => "America/Argentina/Buenos_Aires"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "31",
				"name" => "(GMT-03:00) Greenland",
				"identifier" => "America/Godthab"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "32",
				"name" => "(GMT-02:00) Mid-Atlantic",
				"identifier" => "America/Noronha"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "33",
				"name" => "(GMT-01:00) Azores",
				"identifier" => "Atlantic/Azores"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "34",
				"name" => "(GMT-01:00) Cape Verde Is.",
				"identifier" => "Atlantic/Cape_Verde"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "35",
				"name" => "(GMT) Casablanca",
				"identifier" => "Africa/Casablanca"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "36",
				"name" => "(GMT) Edinburgh",
				"identifier" => "Europe/London"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "37",
				"name" => "(GMT) Greenwich Mean Time : Dublin",
				"identifier" => "Europe/London"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "38",
				"name" => "(GMT) Lisbon",
				"identifier" => "Europe/London"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "39",
				"name" => "(GMT) London",
				"identifier" => "Europe/London"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "40",
				"name" => "(GMT) Monrovia",
				"identifier" => "Africa/Casablanca"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "41",
				"name" => "(GMT+01:00) Amsterdam",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "42",
				"name" => "(GMT+01:00) Belgrade",
				"identifier" => "Europe/Belgrade"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "43",
				"name" => "(GMT+01:00) Berlin",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "44",
				"name" => "(GMT+01:00) Bern",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "45",
				"name" => "(GMT+01:00) Bratislava",
				"identifier" => "Europe/Belgrade"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "46",
				"name" => "(GMT+01:00) Brussels",
				"identifier" => "Europe/Paris"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "47",
				"name" => "(GMT+01:00) Budapest",
				"identifier" => "Europe/Belgrade"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "48",
				"name" => "(GMT+01:00) Copenhagen",
				"identifier" => "Europe/Paris"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "49",
				"name" => "(GMT+01:00) Ljubljana",
				"identifier" => "Europe/Belgrade"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "50",
				"name" => "(GMT+01:00) Madrid",
				"identifier" => "Europe/Paris"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "51",
				"name" => "(GMT+01:00) Paris",
				"identifier" => "Europe/Paris"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "52",
				"name" => "(GMT+01:00) Prague",
				"identifier" => "Europe/Belgrade"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "53",
				"name" => "(GMT+01:00) Rome",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "54",
				"name" => "(GMT+01:00) Sarajevo",
				"identifier" => "Europe/Sarajevo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "55",
				"name" => "(GMT+01:00) Skopje",
				"identifier" => "Europe/Sarajevo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "56",
				"name" => "(GMT+01:00) Stockholm",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "57",
				"name" => "(GMT+01:00) Vienna",
				"identifier" => "Europe/Berlin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "58",
				"name" => "(GMT+01:00) Warsaw",
				"identifier" => "Europe/Sarajevo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "59",
				"name" => "(GMT+01:00) West Central Africa",
				"identifier" => "Africa/Lagos"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "60",
				"name" => "(GMT+01:00) Zagreb",
				"identifier" => "Europe/Sarajevo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "61",
				"name" => "(GMT+02:00) Athens",
				"identifier" => "Europe/Istanbul"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "62",
				"name" => "(GMT+02:00) Bucharest",
				"identifier" => "Europe/Bucharest"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "63",
				"name" => "(GMT+02:00) Cairo",
				"identifier" => "Africa/Cairo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "64",
				"name" => "(GMT+02:00) Harare",
				"identifier" => "Africa/Johannesburg"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "65",
				"name" => "(GMT+02:00) Helsinki",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "66",
				"name" => "(GMT+02:00) Istanbul",
				"identifier" => "Europe/Istanbul"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "67",
				"name" => "(GMT+02:00) Jerusalem",
				"identifier" => "Asia/Jerusalem"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "68",
				"name" => "(GMT+02:00) Kyiv",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "69",
				"name" => "(GMT+02:00) Minsk",
				"identifier" => "Europe/Istanbul"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "70",
				"name" => "(GMT+02:00) Pretoria",
				"identifier" => "Africa/Johannesburg"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "71",
				"name" => "(GMT+02:00) Riga",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "72",
				"name" => "(GMT+02:00) Sofia",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "73",
				"name" => "(GMT+02:00) Tallinn",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "74",
				"name" => "(GMT+02:00) Vilnius",
				"identifier" => "Europe/Helsinki"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "75",
				"name" => "(GMT+03:00) Baghdad",
				"identifier" => "Asia/Baghdad"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "76",
				"name" => "(GMT+03:00) Kuwait",
				"identifier" => "Asia/Riyadh"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "77",
				"name" => "(GMT+03:00) Moscow",
				"identifier" => "Europe/Moscow"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "78",
				"name" => "(GMT+03:00) Nairobi",
				"identifier" => "Africa/Nairobi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "79",
				"name" => "(GMT+03:00) Riyadh",
				"identifier" => "Asia/Riyadh"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "80",
				"name" => "(GMT+03:00) St. Petersburg",
				"identifier" => "Europe/Moscow"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "81",
				"name" => "(GMT+03:00) Volgograd",
				"identifier" => "Europe/Moscow"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "82",
				"name" => "(GMT+03:30) Tehran",
				"identifier" => "Asia/Tehran"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "83",
				"name" => "(GMT+04:00) Abu Dhabi",
				"identifier" => "Asia/Muscat"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "84",
				"name" => "(GMT+04:00) Baku",
				"identifier" => "Asia/Tbilisi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "85",
				"name" => "(GMT+04:00) Muscat",
				"identifier" => "Asia/Muscat"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "86",
				"name" => "(GMT+04:00) Tbilisi",
				"identifier" => "Asia/Tbilisi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "87",
				"name" => "(GMT+04:00) Yerevan",
				"identifier" => "Asia/Tbilisi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "88",
				"name" => "(GMT+04:30) Kabul",
				"identifier" => "Asia/Kabul"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "89",
				"name" => "(GMT+05:00) Ekaterinburg",
				"identifier" => "Asia/Yekaterinburg"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "90",
				"name" => "(GMT+05:00) Islamabad",
				"identifier" => "Asia/Karachi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "91",
				"name" => "(GMT+05:00) Karachi",
				"identifier" => "Asia/Karachi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "92",
				"name" => "(GMT+05:00) Tashkent",
				"identifier" => "Asia/Karachi"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "93",
				"name" => "(GMT+05:30) Chennai",
				"identifier" => "Asia/Calcutta"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "94",
				"name" => "(GMT+05:30) Kolkata",
				"identifier" => "Asia/Calcutta"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "95",
				"name" => "(GMT+05:30) Mumbai",
				"identifier" => "Asia/Calcutta"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "96",
				"name" => "(GMT+05:30) New Delhi",
				"identifier" => "Asia/Calcutta"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "97",
				"name" => "(GMT+05:45) Kathmandu",
				"identifier" => "Asia/Katmandu"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "98",
				"name" => "(GMT+06:00) Almaty",
				"identifier" => "Asia/Novosibirsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "99",
				"name" => "(GMT+06:00) Astana",
				"identifier" => "Asia/Dhaka"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "100",
				"name" => "(GMT+06:00) Dhaka",
				"identifier" => "Asia/Dhaka"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "101",
				"name" => "(GMT+06:00) Novosibirsk",
				"identifier" => "Asia/Novosibirsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "102",
				"name" => "(GMT+06:00) Sri Jayawardenepura",
				"identifier" => "Asia/Colombo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "103",
				"name" => "(GMT+06:30) Rangoon",
				"identifier" => "Asia/Rangoon"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "104",
				"name" => "(GMT+07:00) Bangkok",
				"identifier" => "Asia/Bangkok"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "105",
				"name" => "(GMT+07:00) Hanoi",
				"identifier" => "Asia/Bangkok"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "106",
				"name" => "(GMT+07:00) Jakarta",
				"identifier" => "Asia/Bangkok"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "107",
				"name" => "(GMT+07:00) Krasnoyarsk",
				"identifier" => "Asia/Krasnoyarsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "108",
				"name" => "(GMT+08:00) Beijing",
				"identifier" => "Asia/Hong_Kong"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "109",
				"name" => "(GMT+08:00) Chongqing",
				"identifier" => "Asia/Hong_Kong"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "110",
				"name" => "(GMT+08:00) Hong Kong",
				"identifier" => "Asia/Hong_Kong"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "111",
				"name" => "(GMT+08:00) Irkutsk",
				"identifier" => "Asia/Irkutsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "112",
				"name" => "(GMT+08:00) Kuala Lumpur",
				"identifier" => "Asia/Singapore"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "113",
				"name" => "(GMT+08:00) Perth",
				"identifier" => "Australia/Perth"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "114",
				"name" => "(GMT+08:00) Singapore",
				"identifier" => "Asia/Singapore"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "115",
				"name" => "(GMT+08:00) Taipei",
				"identifier" => "Asia/Taipei"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "116",
				"name" => "(GMT+08:00) Ulaan Bataar",
				"identifier" => "Asia/Irkutsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "117",
				"name" => "(GMT+08:00) Urumqi",
				"identifier" => "Asia/Hong_Kong"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "118",
				"name" => "(GMT+09:00) Osaka",
				"identifier" => "Asia/Tokyo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "119",
				"name" => "(GMT+09:00) Sapporo",
				"identifier" => "Asia/Tokyo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "120",
				"name" => "(GMT+09:00) Seoul",
				"identifier" => "Asia/Seoul"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "121",
				"name" => "(GMT+09:00) Tokyo",
				"identifier" => "Asia/Tokyo"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "122",
				"name" => "(GMT+09:00) Yakutsk",
				"identifier" => "Asia/Yakutsk"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "123",
				"name" => "(GMT+09:30) Adelaide",
				"identifier" => "Australia/Adelaide"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "124",
				"name" => "(GMT+09:30) Darwin",
				"identifier" => "Australia/Darwin"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "125",
				"name" => "(GMT+10:00) Brisbane",
				"identifier" => "Australia/Brisbane"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "126",
				"name" => "(GMT+10:00) Canberra",
				"identifier" => "Australia/Sydney"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "127",
				"name" => "(GMT+10:00) Guam",
				"identifier" => "Pacific/Guam"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "128",
				"name" => "(GMT+10:00) Hobart",
				"identifier" => "Australia/Hobart"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "129",
				"name" => "(GMT+10:00) Melbourne",
				"identifier" => "Australia/Sydney"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "130",
				"name" => "(GMT+10:00) Port Moresby",
				"identifier" => "Pacific/Guam"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "131",
				"name" => "(GMT+10:00) Sydney",
				"identifier" => "Australia/Sydney"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "132",
				"name" => "(GMT+10:00) Vladivostok",
				"identifier" => "Asia/Vladivostok"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "133",
				"name" => "(GMT+11:00) Magadan",
				"identifier" => "Asia/Magadan"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "134",
				"name" => "(GMT+11:00) New Caledonia",
				"identifier" => "Asia/Magadan"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "135",
				"name" => "(GMT+11:00) Solomon Is.",
				"identifier" => "Asia/Magadan"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "136",
				"name" => "(GMT+12:00) Auckland",
				"identifier" => "Pacific/Auckland"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "137",
				"name" => "(GMT+12:00) Fiji",
				"identifier" => "Pacific/Fiji"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "138",
				"name" => "(GMT+12:00) Kamchatka",
				"identifier" => "Pacific/Fiji"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "139",
				"name" => "(GMT+12:00) Marshall Is.",
				"identifier" => "Pacific/Fiji"
			)
		);

		$wpdb->insert( 
			"ec_timezone",
			array(
				"timezone_id" => "140",
				"name" => "(GMT+12:00) Wellington",
				"identifier" => "Pacific/Auckland"
			)
		);

		$wpdb->insert( 
			"ec_perpage",
			array(
				"perpage_id" => "1",
				"perpage" => "50"
			)
		);

		$wpdb->insert( 
			"ec_perpage",
			array(
				"perpage_id" => "2",
				"perpage" => "25"
			)
		);

		$wpdb->insert( 
			"ec_perpage",
			array(
				"perpage_id" => "3",
				"perpage" => "10"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "1",
				"is_less_than" => "1",
				"is_greater_than" => "0",
				"low_point" => "0",
				"high_point" => "10",
				"pricepoint_order" => "0"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "2",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "25",
				"high_point" => "49.99",
				"pricepoint_order" => "4"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "3",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "50",
				"high_point" => "99.99",
				"pricepoint_order" => "5"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "4",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "100",
				"high_point" => "299.99",
				"pricepoint_order" => "6"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "5",
				"is_less_than" => "0",
				"is_greater_than" => "2",
				"low_point" => "299.99",
				"high_point" => "0",
				"pricepoint_order" => "7"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "6",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "10",
				"high_point" => "14.99",
				"pricepoint_order" => "1"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "7",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "15",
				"high_point" => "19.99",
				"pricepoint_order" => "2"
			)
		);

		$wpdb->insert( 
			"ec_pricepoint",
			array(
				"pricepoint_id" => "8",
				"is_less_than" => "0",
				"is_greater_than" => "0",
				"low_point" => "20",
				"high_point" => "24.99",
				"pricepoint_order" => "3"
			)
		);

		$wpdb->insert( 
			"ec_role",
			array(
				"role_id" => "1",
				"role_label" => "admin",
				"admin_access" => "1"
			)
		);

		$wpdb->insert( 
			"ec_role",
			array(
				"role_id" => "2",
				"role_label" => "shopper",
				"admin_access" => "0"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "1",
				"zone_name" => "North America"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "2",
				"zone_name" => "South America"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "3",
				"zone_name" => "Europe"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "4",
				"zone_name" => "Africa"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "5",
				"zone_name" => "Asia"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "6",
				"zone_name" => "Australia"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "7",
				"zone_name" => "Oceania"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "8",
				"zone_name" => "Lower 48 States"
			)
		);

		$wpdb->insert( 
			"ec_zone",
			array(
				"zone_id" => "9",
				"zone_name" => "Alaska and Hawaii"
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "1",
				"zone_id" => "1",
				"iso2_cnt" => "AI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "2",
				"zone_id" => "1",
				"iso2_cnt" => "AQ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "3",
				"zone_id" => "1",
				"iso2_cnt" => "AW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "4",
				"zone_id" => "1",
				"iso2_cnt" => "BS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "5",
				"zone_id" => "1",
				"iso2_cnt" => "BB",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "6",
				"zone_id" => "1",
				"iso2_cnt" => "BM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "7",
				"zone_id" => "1",
				"iso2_cnt" => "BZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "8",
				"zone_id" => "1",
				"iso2_cnt" => "CA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "9",
				"zone_id" => "1",
				"iso2_cnt" => "KY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "10",
				"zone_id" => "1",
				"iso2_cnt" => "CR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "11",
				"zone_id" => "1",
				"iso2_cnt" => "CU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "12",
				"zone_id" => "1",
				"iso2_cnt" => "DM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "13",
				"zone_id" => "1",
				"iso2_cnt" => "DO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "14",
				"zone_id" => "1",
				"iso2_cnt" => "SV",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "15",
				"zone_id" => "1",
				"iso2_cnt" => "GL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "16",
				"zone_id" => "1",
				"iso2_cnt" => "GD",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "17",
				"zone_id" => "1",
				"iso2_cnt" => "GP",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "18",
				"zone_id" => "1",
				"iso2_cnt" => "GT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "19",
				"zone_id" => "1",
				"iso2_cnt" => "HT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "20",
				"zone_id" => "1",
				"iso2_cnt" => "HN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "21",
				"zone_id" => "1",
				"iso2_cnt" => "JM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "22",
				"zone_id" => "1",
				"iso2_cnt" => "MQ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "23",
				"zone_id" => "1",
				"iso2_cnt" => "MX",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "24",
				"zone_id" => "1",
				"iso2_cnt" => "MS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "25",
				"zone_id" => "1",
				"iso2_cnt" => "NI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "26",
				"zone_id" => "1",
				"iso2_cnt" => "PA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "27",
				"zone_id" => "1",
				"iso2_cnt" => "PR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "28",
				"zone_id" => "1",
				"iso2_cnt" => "KN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "29",
				"zone_id" => "1",
				"iso2_cnt" => "LC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "30",
				"zone_id" => "1",
				"iso2_cnt" => "TT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "31",
				"zone_id" => "1",
				"iso2_cnt" => "TC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "32",
				"zone_id" => "1",
				"iso2_cnt" => "US",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "33",
				"zone_id" => "1",
				"iso2_cnt" => "VI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "34",
				"zone_id" => "2",
				"iso2_cnt" => "AR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "35",
				"zone_id" => "2",
				"iso2_cnt" => "BO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "36",
				"zone_id" => "2",
				"iso2_cnt" => "BR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "37",
				"zone_id" => "2",
				"iso2_cnt" => "CL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "38",
				"zone_id" => "2",
				"iso2_cnt" => "CO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "39",
				"zone_id" => "2",
				"iso2_cnt" => "EC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "40",
				"zone_id" => "2",
				"iso2_cnt" => "GF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "41",
				"zone_id" => "2",
				"iso2_cnt" => "GY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "42",
				"zone_id" => "2",
				"iso2_cnt" => "PY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "43",
				"zone_id" => "2",
				"iso2_cnt" => "PE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "44",
				"zone_id" => "2",
				"iso2_cnt" => "SR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "45",
				"zone_id" => "2",
				"iso2_cnt" => "UY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "46",
				"zone_id" => "2",
				"iso2_cnt" => "VE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "47",
				"zone_id" => "6",
				"iso2_cnt" => "AU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "48",
				"zone_id" => "7",
				"iso2_cnt" => "AS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "49",
				"zone_id" => "7",
				"iso2_cnt" => "AU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "50",
				"zone_id" => "7",
				"iso2_cnt" => "CK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "51",
				"zone_id" => "7",
				"iso2_cnt" => "FJ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "52",
				"zone_id" => "7",
				"iso2_cnt" => "PF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "53",
				"zone_id" => "7",
				"iso2_cnt" => "GU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "54",
				"zone_id" => "7",
				"iso2_cnt" => "KI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "55",
				"zone_id" => "7",
				"iso2_cnt" => "MH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "56",
				"zone_id" => "7",
				"iso2_cnt" => "NR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "57",
				"zone_id" => "7",
				"iso2_cnt" => "NC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "58",
				"zone_id" => "7",
				"iso2_cnt" => "NZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "59",
				"zone_id" => "7",
				"iso2_cnt" => "NU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "60",
				"zone_id" => "7",
				"iso2_cnt" => "NF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "61",
				"zone_id" => "7",
				"iso2_cnt" => "PW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "62",
				"zone_id" => "7",
				"iso2_cnt" => "PG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "63",
				"zone_id" => "7",
				"iso2_cnt" => "PN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "64",
				"zone_id" => "7",
				"iso2_cnt" => "WS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "65",
				"zone_id" => "7",
				"iso2_cnt" => "SB",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "66",
				"zone_id" => "7",
				"iso2_cnt" => "TK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "67",
				"zone_id" => "7",
				"iso2_cnt" => "TO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "68",
				"zone_id" => "7",
				"iso2_cnt" => "TV",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "69",
				"zone_id" => "7",
				"iso2_cnt" => "VU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "70",
				"zone_id" => "7",
				"iso2_cnt" => "WF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "71",
				"zone_id" => "3",
				"iso2_cnt" => "AL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "72",
				"zone_id" => "3",
				"iso2_cnt" => "AD",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "73",
				"zone_id" => "3",
				"iso2_cnt" => "AT",
				"code_sta" => "",
			)

		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "74",
				"zone_id" => "3",
				"iso2_cnt" => "BY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "75",
				"zone_id" => "3",
				"iso2_cnt" => "BE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "76",
				"zone_id" => "3",
				"iso2_cnt" => "BG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "77",
				"zone_id" => "3",
				"iso2_cnt" => "HR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "78",
				"zone_id" => "3",
				"iso2_cnt" => "CZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "79",
				"zone_id" => "3",
				"iso2_cnt" => "DK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "80",
				"zone_id" => "3",
				"iso2_cnt" => "EE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "81",
				"zone_id" => "3",
				"iso2_cnt" => "FO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "82",
				"zone_id" => "3",
				"iso2_cnt" => "FI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "83",
				"zone_id" => "3",
				"iso2_cnt" => "FR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "84",
				"zone_id" => "3",
				"iso2_cnt" => "DE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "257",
				"zone_id" => "3",
				"iso2_cnt" => "DC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "85",
				"zone_id" => "3",
				"iso2_cnt" => "GI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "86",
				"zone_id" => "3",
				"iso2_cnt" => "GR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "87",
				"zone_id" => "3",
				"iso2_cnt" => "HU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "88",
				"zone_id" => "3",
				"iso2_cnt" => "IS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "89",
				"zone_id" => "3",
				"iso2_cnt" => "IE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "90",
				"zone_id" => "3",
				"iso2_cnt" => "IT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "91",
				"zone_id" => "3",
				"iso2_cnt" => "LV",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "92",
				"zone_id" => "3",
				"iso2_cnt" => "LI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "93",
				"zone_id" => "3",
				"iso2_cnt" => "LT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "94",
				"zone_id" => "3",
				"iso2_cnt" => "LU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "95",
				"zone_id" => "3",
				"iso2_cnt" => "MT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "96",
				"zone_id" => "3",
				"iso2_cnt" => "MC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "97",
				"zone_id" => "3",
				"iso2_cnt" => "NL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "98",
				"zone_id" => "3",
				"iso2_cnt" => "NO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "99",
				"zone_id" => "3",
				"iso2_cnt" => "PL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "100",
				"zone_id" => "3",
				"iso2_cnt" => "PT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "101",
				"zone_id" => "3",
				"iso2_cnt" => "RO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "102",
				"zone_id" => "3",
				"iso2_cnt" => "RU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "103",
				"zone_id" => "3",
				"iso2_cnt" => "SM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "104",
				"zone_id" => "3",
				"iso2_cnt" => "SI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "105",
				"zone_id" => "3",
				"iso2_cnt" => "ES",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "106",
				"zone_id" => "3",
				"iso2_cnt" => "SE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "107",
				"zone_id" => "3",
				"iso2_cnt" => "CH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "108",
				"zone_id" => "3",
				"iso2_cnt" => "UA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "109",
				"zone_id" => "3",
				"iso2_cnt" => "GB",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "110",
				"zone_id" => "4",
				"iso2_cnt" => "DZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "111",
				"zone_id" => "4",
				"iso2_cnt" => "AO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "112",
				"zone_id" => "4",
				"iso2_cnt" => "BJ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "113",
				"zone_id" => "4",
				"iso2_cnt" => "BW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "114",
				"zone_id" => "4",
				"iso2_cnt" => "BF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "115",
				"zone_id" => "4",
				"iso2_cnt" => "BI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "116",
				"zone_id" => "4",
				"iso2_cnt" => "CM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "117",
				"zone_id" => "4",
				"iso2_cnt" => "CV",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "118",
				"zone_id" => "4",
				"iso2_cnt" => "TD",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "119",
				"zone_id" => "4",
				"iso2_cnt" => "KM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "120",
				"zone_id" => "4",
				"iso2_cnt" => "CG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "121",
				"zone_id" => "4",
				"iso2_cnt" => "CI",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "122",
				"zone_id" => "4",
				"iso2_cnt" => "DJ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "123",
				"zone_id" => "4",
				"iso2_cnt" => "EG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "124",
				"zone_id" => "4",
				"iso2_cnt" => "GQ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "125",
				"zone_id" => "4",
				"iso2_cnt" => "ER",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "126",
				"zone_id" => "4",
				"iso2_cnt" => "ET",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "127",
				"zone_id" => "4",
				"iso2_cnt" => "GA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "128",
				"zone_id" => "4",
				"iso2_cnt" => "GM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "129",
				"zone_id" => "4",
				"iso2_cnt" => "GH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "130",
				"zone_id" => "4",
				"iso2_cnt" => "GN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "131",
				"zone_id" => "4",
				"iso2_cnt" => "GW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "132",
				"zone_id" => "4",
				"iso2_cnt" => "KE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "133",
				"zone_id" => "4",
				"iso2_cnt" => "LS",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "134",
				"zone_id" => "4",
				"iso2_cnt" => "LR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "135",
				"zone_id" => "4",
				"iso2_cnt" => "MG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "136",
				"zone_id" => "4",
				"iso2_cnt" => "MW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "137",
				"zone_id" => "4",
				"iso2_cnt" => "ML",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "138",
				"zone_id" => "4",
				"iso2_cnt" => "MR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "139",
				"zone_id" => "4",
				"iso2_cnt" => "MU",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "140",
				"zone_id" => "4",
				"iso2_cnt" => "YT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "141",
				"zone_id" => "4",
				"iso2_cnt" => "MA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "142",
				"zone_id" => "4",
				"iso2_cnt" => "MZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "143",
				"zone_id" => "4",
				"iso2_cnt" => "NA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "144",
				"zone_id" => "4",
				"iso2_cnt" => "NE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "145",
				"zone_id" => "4",
				"iso2_cnt" => "NG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "146",
				"zone_id" => "4",
				"iso2_cnt" => "RE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "147",
				"zone_id" => "4",
				"iso2_cnt" => "RW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "148",
				"zone_id" => "4",
				"iso2_cnt" => "ST",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "149",
				"zone_id" => "4",
				"iso2_cnt" => "SN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "150",
				"zone_id" => "4",
				"iso2_cnt" => "SC",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "151",
				"zone_id" => "4",
				"iso2_cnt" => "SL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "152",
				"zone_id" => "4",
				"iso2_cnt" => "SO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "153",
				"zone_id" => "4",
				"iso2_cnt" => "ZA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "154",
				"zone_id" => "4",
				"iso2_cnt" => "SD",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "155",
				"zone_id" => "4",
				"iso2_cnt" => "SZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "156",
				"zone_id" => "4",
				"iso2_cnt" => "TG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "157",
				"zone_id" => "4",
				"iso2_cnt" => "TN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "158",
				"zone_id" => "4",
				"iso2_cnt" => "UG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "159",
				"zone_id" => "4",
				"iso2_cnt" => "ZM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "160",
				"zone_id" => "4",
				"iso2_cnt" => "ZW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "161",
				"zone_id" => "5",
				"iso2_cnt" => "AF",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "162",
				"zone_id" => "5",
				"iso2_cnt" => "AM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "163",
				"zone_id" => "5",
				"iso2_cnt" => "AZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "164",
				"zone_id" => "5",
				"iso2_cnt" => "BH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "165",
				"zone_id" => "5",
				"iso2_cnt" => "BD",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "166",
				"zone_id" => "5",
				"iso2_cnt" => "BT",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "167",
				"zone_id" => "5",
				"iso2_cnt" => "BN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "168",
				"zone_id" => "5",
				"iso2_cnt" => "KH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "169",
				"zone_id" => "5",
				"iso2_cnt" => "CN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "170",
				"zone_id" => "5",
				"iso2_cnt" => "CX",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "171",
				"zone_id" => "5",
				"iso2_cnt" => "CY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "172",
				"zone_id" => "5",
				"iso2_cnt" => "TP",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "173",
				"zone_id" => "5",
				"iso2_cnt" => "GE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "174",
				"zone_id" => "5",
				"iso2_cnt" => "HK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "175",
				"zone_id" => "5",
				"iso2_cnt" => "IN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "176",
				"zone_id" => "5",
				"iso2_cnt" => "ID",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "177",
				"zone_id" => "5",
				"iso2_cnt" => "IQ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "178",
				"zone_id" => "5",
				"iso2_cnt" => "IL",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "179",
				"zone_id" => "5",
				"iso2_cnt" => "JP",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "180",
				"zone_id" => "5",
				"iso2_cnt" => "JO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "181",
				"zone_id" => "5",
				"iso2_cnt" => "KZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "182",
				"zone_id" => "5",
				"iso2_cnt" => "KW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "183",
				"zone_id" => "5",
				"iso2_cnt" => "KG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "184",
				"zone_id" => "5",
				"iso2_cnt" => "LB",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "185",
				"zone_id" => "5",
				"iso2_cnt" => "MO",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "186",
				"zone_id" => "5",
				"iso2_cnt" => "MY",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "187",
				"zone_id" => "5",
				"iso2_cnt" => "MV",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "188",
				"zone_id" => "5",
				"iso2_cnt" => "MN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "189",
				"zone_id" => "5",
				"iso2_cnt" => "MM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "190",
				"zone_id" => "5",
				"iso2_cnt" => "NP",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "191",
				"zone_id" => "5",
				"iso2_cnt" => "OM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "192",
				"zone_id" => "5",
				"iso2_cnt" => "PK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "193",
				"zone_id" => "5",
				"iso2_cnt" => "PH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "194",
				"zone_id" => "5",
				"iso2_cnt" => "QA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "195",
				"zone_id" => "5",
				"iso2_cnt" => "SA",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "196",
				"zone_id" => "5",
				"iso2_cnt" => "SG",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "197",
				"zone_id" => "5",
				"iso2_cnt" => "LK",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "198",
				"zone_id" => "5",
				"iso2_cnt" => "TW",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "199",
				"zone_id" => "5",
				"iso2_cnt" => "TJ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "200",
				"zone_id" => "5",
				"iso2_cnt" => "TH",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "201",
				"zone_id" => "5",
				"iso2_cnt" => "TR",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "202",
				"zone_id" => "5",
				"iso2_cnt" => "TM",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "203",
				"zone_id" => "5",
				"iso2_cnt" => "AE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "204",
				"zone_id" => "5",
				"iso2_cnt" => "UZ",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "205",
				"zone_id" => "5",
				"iso2_cnt" => "VN",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "206",
				"zone_id" => "5",
				"iso2_cnt" => "YE",
				"code_sta" => "",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "207",
				"zone_id" => "9",
				"iso2_cnt" => "US",
				"code_sta" => "HI",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "208",
				"zone_id" => "9",
				"iso2_cnt" => "US",
				"code_sta" => "AK",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "209",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "AL",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "210",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "AZ",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "211",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "AR",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "212",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "CA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "213",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "CO",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "214",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "CT",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "215",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "DE",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "258",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "DC",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "216",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "FL",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "217",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "GA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "218",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "ID",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "219",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "IL",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "220",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "IN",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "221",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "IA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "222",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "KS",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "223",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "KY",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "224",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "LA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "225",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "ME",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "226",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MD",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "227",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "228",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MI",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "229",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MN",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "230",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MS",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "231",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MO",
			)
		);


		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "232",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "MT",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "233",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NE",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "234",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NV",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "235",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NH",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "236",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NJ",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "237",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NM",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "238",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NY",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "239",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "NC",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "240",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "ND",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "241",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "OH",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "242",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "OK",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "243",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "OR",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "244",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "PA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "245",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "RI",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "246",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "SC",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "247",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "SD",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "248",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "TN",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "249",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "TX",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "250",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "UT",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "251",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "VT",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "252",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "VA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "253",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "WA",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "254",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "WV",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "255",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "WI",
			)
		);

		$wpdb->insert( 
			"ec_zone_to_location",
			array(
				"zone_to_location_id" => "256",
				"zone_id" => "8",
				"iso2_cnt" => "US",
				"code_sta" => "WY"
			)
		);

	}

	/**
	 * Insert any default country or region that is missing. Existing rows are never changed or removed:
	 * countries match on iso2_cnt, regions on country + code_sta. Used by the fresh-install seed
	 * ( ship-to on ) and by the admin "Restore default countries & regions" action ( ship-to off ).
	 * Never called from the plugin update path.
	 *
	 * @since 6.0.0 $dry_run counts what would be added without writing; the result also carries the size of
	 *              the default set and any rows the database refused ( previously skipped silently ).
	 *
	 * @param int  $ship_to_active ship_to_active value for the rows this call inserts ( 0 or 1 ).
	 * @param bool $dry_run        Count only; nothing is inserted.
	 * @return array countries_added, states_added, countries_failed, states_failed, last_error,
	 *               default_countries, default_states, default_region_countries.
	 */
	public function restore_default_countries_and_states( $ship_to_active = 0, $dry_run = false ) {
		global $wpdb;
		$ship_to_active = $ship_to_active ? 1 : 0;
		$result = array(
			'countries_added'          => 0,
			'states_added'             => 0,
			'countries_failed'         => 0,
			'states_failed'            => 0,
			'last_error'               => '',
			'default_countries'        => 0,
			'default_states'           => 0,
			'default_region_countries' => 0,
		);

		$data_file = EC_PLUGIN_DIRECTORY . '/inc/classes/core/ec_default_countries_states.php';
		if ( ! file_exists( $data_file ) ) {
			return $result;
		}
		$defaults = include $data_file;
		if ( ! is_array( $defaults ) || empty( $defaults['countries'] ) ) {
			return $result;
		}
		$defaults_states = ( ! empty( $defaults['states'] ) && is_array( $defaults['states'] ) ) ? $defaults['states'] : array();
		$result['default_countries'] = count( $defaults['countries'] );
		$result['default_states']    = count( $defaults_states );
		$region_countries = array();
		foreach ( $defaults_states as $state ) {
			$region_countries[ strtoupper( $state['iso2_cnt'] ) ] = true;
		}
		$result['default_region_countries'] = count( $region_countries );

		$existing_countries = $wpdb->get_results( 'SELECT id_cnt, iso2_cnt FROM ec_country' );
		$iso2_to_id = array();
		foreach ( $existing_countries as $row ) {
			$iso2_to_id[ strtoupper( trim( $row->iso2_cnt ) ) ] = (int) $row->id_cnt;
		}

		foreach ( $defaults['countries'] as $country ) {
			$iso2 = strtoupper( $country['iso2_cnt'] );
			if ( isset( $iso2_to_id[ $iso2 ] ) ) {
				continue;
			}
			if ( $dry_run ) {
				$iso2_to_id[ $iso2 ] = 'new-' . $iso2;
				++$result['countries_added'];
				continue;
			}
			$row = array(
				'name_cnt'       => $country['name_cnt'],
				'iso2_cnt'       => $country['iso2_cnt'],
				'iso3_cnt'       => $country['iso3_cnt'],
				'sort_order'     => (int) $country['sort_order'],
				'ship_to_active' => $ship_to_active,
			);
			$inserted = $this->insert_default_location_row( 'ec_country', $row, 'name_cnt' );
			if ( $inserted ) {
				$iso2_to_id[ $iso2 ] = (int) $wpdb->insert_id;
				++$result['countries_added'];
				do_action( 'wpeasycart_country_added', (int) $wpdb->insert_id );
			} else {
				++$result['countries_failed'];
				$result['last_error'] = $wpdb->last_error;
			}
		}

		if ( empty( $defaults_states ) ) {
			return $result;
		}

		$existing_states = $wpdb->get_results( 'SELECT idcnt_sta, code_sta FROM ec_state' );
		$state_key_set = array();
		foreach ( $existing_states as $row ) {
			$state_key_set[ (int) $row->idcnt_sta . '|' . strtoupper( trim( $row->code_sta ) ) ] = true;
		}

		foreach ( $defaults_states as $state ) {
			$iso2 = strtoupper( $state['iso2_cnt'] );
			if ( ! isset( $iso2_to_id[ $iso2 ] ) ) {
				continue;
			}
			$idcnt = $iso2_to_id[ $iso2 ];
			$key   = $idcnt . '|' . strtoupper( $state['code_sta'] );
			if ( isset( $state_key_set[ $key ] ) ) {
				continue;
			}
			if ( $dry_run ) {
				$state_key_set[ $key ] = true;
				++$result['states_added'];
				continue;
			}
			$row = array(
				'idcnt_sta'      => $idcnt,
				'code_sta'       => $state['code_sta'],
				'name_sta'       => $state['name_sta'],
				'sort_order'     => (int) $state['sort_order'],
				'group_sta'      => $state['group_sta'],
				'ship_to_active' => $ship_to_active,
			);
			$inserted = $this->insert_default_location_row( 'ec_state', $row, 'name_sta' );
			if ( $inserted ) {
				$state_key_set[ $key ] = true;
				++$result['states_added'];
				do_action( 'wpeasycart_state_added', (int) $wpdb->insert_id );
			} else {
				++$result['states_failed'];
				$result['last_error'] = $wpdb->last_error;
			}
		}

		return $result;
	}

	/**
	 * Insert one default country/region row. wpdb refuses a whole row when a value cannot be stored in the
	 * column's character set ( e.g. "Manawatū-Whanganui" in an older latin1 ec_state table ), so on failure the
	 * name is retried without accents rather than losing the region.
	 *
	 * @since 6.0.0
	 *
	 * @param string $table      ec_country or ec_state.
	 * @param array  $row        Column => value.
	 * @param string $name_field The display-name column that may carry accents.
	 * @return int|false Rows inserted, or false.
	 */
	private function insert_default_location_row( $table, $row, $name_field ) {
		global $wpdb;
		$inserted = $wpdb->insert( $table, $row );
		if ( ! $inserted && function_exists( 'remove_accents' ) && preg_match( '/[^\x20-\x7e]/', $row[ $name_field ] ) ) {
			$row[ $name_field ] = remove_accents( $row[ $name_field ] );
			$inserted = $wpdb->insert( $table, $row );
		}
		return $inserted;
	}

}
?>