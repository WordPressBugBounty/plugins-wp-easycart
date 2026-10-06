<?php
/**
 * WP EasyCart — the import framework ( 6.0.3 ).
 *
 * One engine for every store the merchant moves from. A source ( wp_easycart_import_source: WooCommerce on this site,
 * Square, and anything registered through the filter wp_easycart_import_sources ) says what it found ( detect() ), what can
 * come across ( entities(), fields() ) and does the work in phases ( run() ). This class runs one import at a time:
 *
 * - The job ( option wp_easycart_import_job ) holds the source, the merchant's choices, the phases with their progress, the
 *   cursor and the counts. step() works for a few seconds under a MySQL named lock and saves after every slice, so a page
 *   that stops answering loses nothing; the open Import page drives it and WP-Cron ( wp_easycart_import_tick ) finishes it
 *   when the tab is closed. Pause, resume, cancel.
 * - A trial ( "Try 10" ) imports a few products first. A full run that follows a kept trial remembers it ( trial_run ).
 * - ec_import_map ( EC_UPGRADE_DB 120 ) keeps one row per source item: the EasyCart id it became, the run, whether that run
 *   created it, the result ( imported | updated | skipped | failed ), a note, and the item's old address. A rerun finds
 *   what was imported; undo() removes only what a run created ( and its trial ), through the normal delete paths.
 * - Old addresses ( WooCommerce products and categories ) answer with a 301 to the EasyCart page while option
 *   wp_easycart_import_redirects is on ( template_redirect, only on a 404 ).
 * - History: option wp_easycart_import_runs, the last 20 runs.
 *
 * Hooks: wp_easycart_import_sources ( filter: id => class name or object ), wp_easycart_import_started and
 * wp_easycart_import_finished ( the job ), wp_easycart_import_undone ( run id ).
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_import' ) ) :

	/**
	 * The import engine.
	 */
	class wp_easycart_import {

		const DB_VERSION       = 120;
		const JOB_OPTION       = 'wp_easycart_import_job';
		const RUNS_OPTION      = 'wp_easycart_import_runs';
		const SEQ_OPTION       = 'wp_easycart_import_run_seq';
		const REDIRECTS_OPTION = 'wp_easycart_import_redirects';
		const BASES_OPTION     = 'wp_easycart_import_redirect_bases';
		const LOCK             = 'wpec_import';
		const CRON             = 'wp_easycart_import_tick';
		const BUDGET           = 12;
		const TRIAL            = 10;

		/**
		 * Registered sources, id => wp_easycart_import_source.
		 *
		 * @var array|null
		 */
		private static $sources = null;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( self::CRON, array( __CLASS__, 'cron_tick' ) );
			add_action( 'template_redirect', array( __CLASS__, 'redirect_old_address' ), 1 );
		}

		/**
		 * The import table exists ( EC_UPGRADE_DB 120 ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= self::DB_VERSION;
		}

		// ---- Sources ----.

		/**
		 * Every source, id => object.
		 *
		 * @return wp_easycart_import_source[]
		 */
		public static function sources() {
			if ( null === self::$sources ) {
				/**
				 * The import sources: id => class name ( or an object ) extending wp_easycart_import_source.
				 *
				 * @since 6.0.3
				 * @param array $sources Sources.
				 */
				$classes       = apply_filters(
					'wp_easycart_import_sources',
					array(
						'woocommerce' => 'wp_easycart_import_woocommerce',
						'square'      => 'wp_easycart_import_square',
					)
				);
				self::$sources = array();
				foreach ( (array) $classes as $class ) {
					$source = is_object( $class ) ? $class : ( ( is_string( $class ) && class_exists( $class ) ) ? new $class() : null );
					if ( $source instanceof wp_easycart_import_source ) {
						self::$sources[ $source->id() ] = $source;
					}
				}
			}
			return self::$sources;
		}

		/**
		 * One source.
		 *
		 * @param string $id Source id.
		 * @return wp_easycart_import_source|null
		 */
		public static function source( $id ) {
			$sources = self::sources();
			return isset( $sources[ (string) $id ] ) ? $sources[ (string) $id ] : null;
		}

		/**
		 * Forget the sources read in this request ( a filter added later, tests ).
		 */
		public static function forget_sources() {
			self::$sources = null;
		}

		// ---- The map ----.

		/**
		 * The map row of a source item.
		 *
		 * @param string $source    Source id.
		 * @param string $site      Source site ( wp_easycart_import_source::site() ).
		 * @param string $entity    Entity ( product, category, brand, review … ).
		 * @param string $source_id The item's id at the source.
		 * @return object|null
		 */
		public static function map_row( $source, $site, $entity, $source_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return null;
			}
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_import_map WHERE source = %s AND source_site = %s AND entity = %s AND source_id = %s', (string) $source, (string) $site, (string) $entity, (string) $source_id ) );
		}

		/**
		 * The EasyCart id a source item became ( 0 = none ).
		 *
		 * @param string $source    Source id.
		 * @param string $site      Source site.
		 * @param string $entity    Entity.
		 * @param string $source_id The item's id at the source.
		 * @return int
		 */
		public static function map_target( $source, $site, $entity, $source_id ) {
			$row = self::map_row( $source, $site, $entity, $source_id );
			return $row ? (int) $row->target_id : 0;
		}

		/**
		 * Write a source item's map row.
		 *
		 * @param string $source    Source id.
		 * @param string $site      Source site.
		 * @param string $entity    Entity.
		 * @param string $source_id The item's id at the source.
		 * @param array  $args      target_id, run_id, created ( this run made the target ), status, label, message, hash,
		 *                          path ( old address, null = keep ).
		 * @return int Map row id.
		 */
		public static function map_set( $source, $site, $entity, $source_id, $args ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$args = array_merge(
				array(
					'target_id' => 0,
					'run_id'    => 0,
					'created'   => 0,
					'status'    => '',
					'label'     => '',
					'message'   => '',
					'hash'      => '',
					'path'      => null,
				),
				(array) $args
			);
			$row  = array(
				'target_id'   => (int) $args['target_id'],
				'run_id'      => (int) $args['run_id'],
				'created'     => empty( $args['created'] ) ? 0 : 1,
				'status'      => substr( sanitize_key( (string) $args['status'] ), 0, 20 ),
				'label'       => substr( wp_strip_all_tags( (string) $args['label'] ), 0, 255 ),
				'message'     => (string) $args['message'],
				'source_hash' => substr( (string) $args['hash'], 0, 32 ),
				'updated_at'  => current_time( 'mysql', true ),
			);
			if ( null !== $args['path'] ) {
				$row['source_path'] = substr( trim( (string) $args['path'], '/' ), 0, 255 );
			}
			$existing = self::map_row( $source, $site, $entity, $source_id );
			if ( $existing ) {
				$wpdb->update( 'ec_import_map', $row, array( 'map_id' => (int) $existing->map_id ) );
				return (int) $existing->map_id;
			}
			$row = array_merge(
				array(
					'source'      => substr( (string) $source, 0, 20 ),
					'source_site' => substr( (string) $site, 0, 40 ),
					'entity'      => substr( (string) $entity, 0, 20 ),
					'source_id'   => substr( (string) $source_id, 0, 100 ),
				),
				$row
			);
			$wpdb->insert( 'ec_import_map', $row );
			return (int) $wpdb->insert_id;
		}

		// ---- Runs and the job ----.

		/**
		 * The current ( or last ) job.
		 *
		 * @return array|null
		 */
		public static function job() {
			$job = get_option( self::JOB_OPTION );
			return ( is_array( $job ) && ! empty( $job['id'] ) ) ? $job : null;
		}

		/**
		 * Keep the job.
		 *
		 * @param array $job Job.
		 */
		private static function save_job( $job ) {
			$job['updated'] = time();
			update_option( self::JOB_OPTION, $job, false );
		}

		/**
		 * A job that is running or paused.
		 *
		 * @param array|null $job Job ( null = the current one ).
		 * @return bool
		 */
		public static function is_open( $job = null ) {
			$job = ( null === $job ) ? self::job() : $job;
			return is_array( $job ) && in_array( $job['status'], array( 'running', 'paused' ), true );
		}

		/**
		 * The last runs, newest first, run id => summary.
		 *
		 * @return array
		 */
		public static function runs() {
			$runs = get_option( self::RUNS_OPTION, array() );
			return is_array( $runs ) ? $runs : array();
		}

		/**
		 * One run's summary.
		 *
		 * @param int $run_id Run.
		 * @return array|null
		 */
		public static function run( $run_id ) {
			$runs = self::runs();
			return isset( $runs[ (int) $run_id ] ) ? $runs[ (int) $run_id ] : null;
		}

		/**
		 * What the history keeps of a job.
		 *
		 * @param array $job Job.
		 * @return array
		 */
		public static function summary( $job ) {
			return array(
				'id'        => (int) $job['id'],
				'source'    => (string) $job['source'],
				'label'     => (string) $job['label'],
				'trial'     => ! empty( $job['trial'] ),
				'trial_run' => isset( $job['trial_run'] ) ? (int) $job['trial_run'] : 0,
				'status'    => (string) $job['status'],
				'counts'    => isset( $job['counts'] ) ? (array) $job['counts'] : array(),
				'entities'  => isset( $job['settings']['entities'] ) ? (array) $job['settings']['entities'] : array(),
				'started'   => (int) $job['started'],
				'finished'  => isset( $job['finished'] ) ? (int) $job['finished'] : 0,
				'error'     => isset( $job['error'] ) ? (string) $job['error'] : '',
			);
		}

		/**
		 * Keep a job's summary in the history ( the last 20 runs ).
		 *
		 * @param array $job Job.
		 */
		private static function save_run( $job ) {
			$runs                     = self::runs();
			$runs[ (int) $job['id'] ] = self::summary( $job );
			krsort( $runs );
			update_option( self::RUNS_OPTION, array_slice( $runs, 0, 20, true ), false );
		}

		/**
		 * Mark a run in the history.
		 *
		 * @param int    $run_id Run.
		 * @param string $status Status.
		 */
		private static function mark_run( $run_id, $status ) {
			$runs = self::runs();
			if ( isset( $runs[ (int) $run_id ] ) ) {
				$runs[ (int) $run_id ]['status'] = $status;
				update_option( self::RUNS_OPTION, $runs, false );
			}
		}

		/**
		 * Start an import ( or a trial of a few products ).
		 *
		 * @param string $source_id Source.
		 * @param array  $choices   entities => list, fields => key => value.
		 * @param bool   $trial     Try a few products first.
		 * @return array|WP_Error The job.
		 */
		public static function start( $source_id, $choices, $trial = false ) {
			if ( ! self::ready() ) {
				return new WP_Error( 'wp_easycart_import_update', __( 'Finish the WP EasyCart database update first ( Store Status ), then import.', 'wp-easycart' ) );
			}
			$current = self::job();
			if ( self::is_open( $current ) ) {
				return new WP_Error( 'wp_easycart_import_busy', __( 'An import is already running. Let it finish, or stop it first.', 'wp-easycart' ) );
			}
			$source = self::source( $source_id );
			if ( ! $source ) {
				return new WP_Error( 'wp_easycart_import_source', __( 'That import source is not available.', 'wp-easycart' ) );
			}
			$detect = $source->detect();
			if ( empty( $detect['available'] ) ) {
				return new WP_Error( 'wp_easycart_import_unavailable', ! empty( $detect['status'] ) ? (string) $detect['status'] : __( 'There is nothing to import from this source.', 'wp-easycart' ) );
			}
			$settings = $source->clean_settings( $choices, $detect );
			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
			$run_id = (int) get_option( self::SEQ_OPTION, 0 ) + 1;
			update_option( self::SEQ_OPTION, $run_id, false );
			$phases = array();
			foreach ( $source->phases( $settings, (bool) $trial ) as $phase ) {
				$phases[] = array(
					'key'   => (string) $phase,
					'label' => $source->phase_label( $phase ),
					'done'  => 0,
					'total' => (int) $source->phase_total( $phase, $settings, (bool) $trial ),
					'state' => 'waiting',
				);
			}
			$job = array(
				'id'        => $run_id,
				'source'    => $source->id(),
				'site'      => $source->site(),
				'label'     => $source->label(),
				'settings'  => $settings,
				'trial'     => (bool) $trial,
				'trial_run' => 0,
				'phases'    => $phases,
				'phase'     => 0,
				'cursor'    => null,
				'status'    => 'running',
				'counts'    => array(),
				'state'     => array(),
				'started'   => time(),
				'updated'   => time(),
				'finished'  => 0,
				'heartbeat' => time(),
				'error'     => '',
				'user'      => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			);
			/* A full run right after a kept trial of the same source: undoing the run removes the trial's products too. */
			if ( ! $trial && $current && ! empty( $current['trial'] ) && 'done' === $current['status'] && $current['source'] === $job['source'] ) {
				$job['trial_run'] = (int) $current['id'];
			}
			self::save_job( $job );
			self::save_run( $job );
			self::schedule();
			/**
			 * An import started.
			 *
			 * @since 6.0.3
			 * @param array $job The job.
			 */
			do_action( 'wp_easycart_import_started', $job );
			return $job;
		}

		/**
		 * Work on the job for a few seconds.
		 *
		 * @param float|null $budget       Seconds ( null = BUDGET ).
		 * @param bool       $from_browser The Import page asked ( WP-Cron waits while it does ).
		 * @return array|null The job.
		 * @throws Exception A source that is no longer registered ( caught here: the job pauses with the message ).
		 */
		public static function step( $budget = null, $from_browser = true ) {
			$job = self::job();
			if ( ! $job || 'running' !== $job['status'] ) {
				return $job;
			}
			if ( $from_browser ) {
				$job['heartbeat'] = time();
			}
			if ( ! self::lock() ) {
				if ( $from_browser ) {
					self::save_job( $job );
				}
				$job['busy'] = true;
				return $job;
			}
			$source   = self::source( $job['source'] );
			$deadline = microtime( true ) + ( null === $budget ? self::BUDGET : max( 1, (float) $budget ) );
			$deferred = function_exists( 'wp_defer_term_counting' ) ? wp_defer_term_counting( true ) : false;
			try {
				if ( ! $source ) {
					throw new Exception( __( 'That import source is not available any more.', 'wp-easycart' ) );
				}
				if ( function_exists( 'wp_raise_memory_limit' ) ) {
					wp_raise_memory_limit( 'admin' );
				}
				$phase_count = count( $job['phases'] );
				$first       = true; /* a slice always does something, however little time is left */
				while ( ( $first || microtime( true ) < $deadline ) && $job['phase'] < $phase_count ) {
					$first  = false;
					$index                            = (int) $job['phase'];
					$phase                            = $job['phases'][ $index ]['key'];
					$job['phases'][ $index ]['state'] = 'running';
					$result                           = (array) $source->run( $phase, $job['cursor'], $job, $deadline );
					$job['cursor']                    = array_key_exists( 'cursor', $result ) ? $result['cursor'] : null;
					$job['phases'][ $index ]['done'] += isset( $result['count'] ) ? (int) $result['count'] : 0;
					if ( isset( $result['total'] ) ) {
						$job['phases'][ $index ]['total'] = (int) $result['total'];
					}
					if ( ! empty( $result['error'] ) ) {
						$job['status'] = 'paused';
						$job['error']  = (string) $result['error'];
						self::save_job( $job );
						break;
					}
					if ( ! empty( $result['done'] ) ) {
						$job['phases'][ $index ]['state'] = 'done';
						$job['phases'][ $index ]['total'] = max( (int) $job['phases'][ $index ]['total'], (int) $job['phases'][ $index ]['done'] );
						$job['phase']                     = $index + 1;
						$job['cursor']                    = null;
					}
					self::save_job( $job ); /* every slice: a request that dies keeps what was done */
				}
				if ( 'running' === $job['status'] && $job['phase'] >= $phase_count ) {
					$source->finish( $job );
					$job['status']   = 'done';
					$job['finished'] = time();
					self::save_job( $job );
					wp_clear_scheduled_hook( self::CRON );
					/**
					 * An import finished.
					 *
					 * @since 6.0.3
					 * @param array $job The job.
					 */
					do_action( 'wp_easycart_import_finished', $job );
				}
			} catch ( \Throwable $e ) {
				$job['status'] = 'paused';
				$job['error']  = $e->getMessage();
				self::save_job( $job );
			}
			if ( function_exists( 'wp_defer_term_counting' ) ) {
				wp_defer_term_counting( $deferred );
			}
			self::save_run( $job );
			self::unlock();
			return $job;
		}

		/**
		 * Pause, resume, cancel or dismiss the job.
		 *
		 * @param string $action pause | resume | cancel | dismiss.
		 * @return array|null The job.
		 */
		public static function control( $action ) {
			$job = self::job();
			if ( ! $job ) {
				return null;
			}
			if ( 'pause' === $action && 'running' === $job['status'] ) {
				$job['status'] = 'paused';
			} elseif ( 'resume' === $action && 'paused' === $job['status'] ) {
				$job['status']    = 'running';
				$job['error']     = '';
				$job['heartbeat'] = time();
				self::schedule();
			} elseif ( 'cancel' === $action && self::is_open( $job ) ) {
				$job['status']   = 'cancelled';
				$job['finished'] = time();
				wp_clear_scheduled_hook( self::CRON );
			} elseif ( 'dismiss' === $action && ! self::is_open( $job ) ) {
				delete_option( self::JOB_OPTION );
				return null;
			} else {
				return $job;
			}
			self::save_job( $job );
			self::save_run( $job );
			return $job;
		}

		/**
		 * Keep WP-Cron coming back while a job runs ( a closed tab still finishes ).
		 */
		public static function schedule() {
			if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_single_event( time() + 60, self::CRON );
			}
		}

		/**
		 * WP-Cron: work on a running job the Import page is no longer driving.
		 */
		public static function cron_tick() {
			$job = self::job();
			if ( ! $job || 'running' !== $job['status'] ) {
				return;
			}
			if ( time() - (int) $job['heartbeat'] >= 45 ) {
				self::step( self::BUDGET * 2, false );
			}
			$job = self::job();
			if ( $job && 'running' === $job['status'] ) {
				wp_schedule_single_event( time() + 60, self::CRON );
			}
		}

		/**
		 * Take the import lock ( one slice at a time ).
		 *
		 * @return bool
		 */
		private static function lock() {
			global $wpdb;
			return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', self::LOCK ) );
		}

		/**
		 * Release the import lock.
		 */
		private static function unlock() {
			global $wpdb;
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::LOCK ) );
		}

		// ---- Results ----.

		/**
		 * A run's map rows ( its report ).
		 *
		 * @param int    $run_id Run.
		 * @param string $filter all | notes ( rows with a note, or failed ).
		 * @param int    $offset Offset.
		 * @param int    $limit  Rows ( 0 = all ).
		 * @return array
		 */
		public static function results( $run_id, $filter = 'all', $offset = 0, $limit = 100 ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			$where = $wpdb->prepare( 'run_id = %d', (int) $run_id );
			if ( 'notes' === $filter ) {
				$where .= " AND ( status = 'failed' OR ( message IS NOT NULL AND message != '' ) )";
			}
			$sql = 'SELECT map_id, source, entity, source_id, target_id, created, status, label, message, source_path FROM ec_import_map WHERE ' . $where . " ORDER BY ( status = 'failed' ) DESC, map_id ASC";
			if ( $limit > 0 ) {
				$sql .= $wpdb->prepare( ' LIMIT %d, %d', max( 0, (int) $offset ), (int) $limit );
			}
			return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from prepared parts.
		}

		/**
		 * How many map rows a run has, by status.
		 *
		 * @param int $run_id Run.
		 * @return array status => count, plus notes and created.
		 */
		public static function result_counts( $run_id ) {
			global $wpdb;
			$out = array(
				'imported' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'failed'   => 0,
				'notes'    => 0,
				'created'  => 0,
			);
			if ( ! self::ready() ) {
				return $out;
			}
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n, SUM( created ) AS made, SUM( CASE WHEN message IS NOT NULL AND message != '' THEN 1 ELSE 0 END ) AS notes FROM ec_import_map WHERE run_id = %d GROUP BY status", (int) $run_id ) ) as $row ) {
				if ( isset( $out[ $row->status ] ) ) {
					$out[ $row->status ] = (int) $row->n;
				}
				$out['notes']   += (int) $row->notes;
				$out['created'] += (int) $row->made;
			}
			return $out;
		}

		// ---- Undo ----.

		/**
		 * Remove what a run created ( and the trial a full run followed ), a slice at a time. Only rows the run created are
		 * touched; products a later run updated, and anything the store already had, stay.
		 *
		 * @param int   $run_id Run.
		 * @param float $budget Seconds.
		 * @return array done, removed, left.
		 */
		public static function undo( $run_id, $budget = 10 ) {
			global $wpdb;
			$run_id = (int) $run_id;
			$out    = array(
				'done'    => true,
				'removed' => 0,
				'left'    => 0,
			);
			if ( ! self::ready() || $run_id <= 0 ) {
				return $out;
			}
			$job = self::job();
			if ( $job && (int) $job['id'] === $run_id && self::is_open( $job ) ) {
				self::control( 'cancel' );
			}
			$runs = array( $run_id );
			$run  = self::run( $run_id );
			if ( $run && ! empty( $run['trial_run'] ) ) {
				$runs[] = (int) $run['trial_run'];
			}
			$in       = implode( ',', array_map( 'intval', $runs ) );
			$deadline = microtime( true ) + max( 1, (float) $budget );
			$order    = "FIELD( entity, 'review', 'variation', 'product', 'option', 'tag', 'category', 'brand' )";
			do {
				$rows = (array) $wpdb->get_results( 'SELECT map_id, entity, target_id FROM ec_import_map WHERE run_id IN ( ' . $in . ' ) AND created = 1 ORDER BY ' . $order . ', map_id DESC LIMIT 25' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) run ids and a fixed ORDER BY.
				foreach ( $rows as $row ) {
					self::delete_target( $row->entity, (int) $row->target_id );
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_import_map WHERE map_id = %d', (int) $row->map_id ) );
					++$out['removed'];
				}
			} while ( $rows && microtime( true ) < $deadline );
			$out['left'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_import_map WHERE run_id IN ( ' . $in . ' ) AND created = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- (int) run ids.
			$out['done'] = ( 0 === $out['left'] );
			if ( $out['done'] ) {
				foreach ( $runs as $id ) {
					self::mark_run( $id, 'undone' );
				}
				if ( $job && in_array( (int) $job['id'], $runs, true ) ) {
					delete_option( self::JOB_OPTION );
				}
				/**
				 * An import was removed.
				 *
				 * @since 6.0.3
				 * @param int $run_id Run.
				 */
				do_action( 'wp_easycart_import_undone', $run_id );
			}
			return $out;
		}

		/**
		 * Delete what a map row points at.
		 *
		 * @param string $entity    Entity.
		 * @param int    $target_id EasyCart id.
		 */
		private static function delete_target( $entity, $target_id ) {
			global $wpdb;
			if ( $target_id <= 0 ) {
				return;
			}
			switch ( $entity ) {
				case 'product':
					self::delete_product( $target_id );
					break;
				case 'category':
				case 'tag':
					$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_category WHERE category_id = %d', $target_id ) );
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_categoryitem WHERE category_id = %d', $target_id ) );
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_category SET parent_id = 0 WHERE parent_id = %d', $target_id ) );
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_category WHERE category_id = %d', $target_id ) );
					if ( $post_id && function_exists( 'wp_delete_post' ) ) {
						wp_delete_post( $post_id, true );
					}
					wp_cache_delete( 'wpeasycart-all-categories', 'wpeasycart-categories' );
					wp_cache_delete( 'wpeasycart-all-categories' );
					break;
				case 'brand':
					/* A manufacturer goes only when no product uses it any more. */
					if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE manufacturer_id = %d', $target_id ) ) ) {
						$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', $target_id ) );
						$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_manufacturer WHERE manufacturer_id = %d', $target_id ) );
						if ( $post_id && function_exists( 'wp_delete_post' ) ) {
							wp_delete_post( $post_id, true );
						}
					}
					break;
				case 'option':
					/* A shared option set ( Square modifiers ) goes only when no product uses it any more. */
					$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option_to_product WHERE option_id = %d', $target_id ) ) + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE option_id_1 = %d OR option_id_2 = %d OR option_id_3 = %d OR option_id_4 = %d OR option_id_5 = %d', $target_id, $target_id, $target_id, $target_id, $target_id ) );
					if ( ! $used ) {
						$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitem WHERE option_id = %d', $target_id ) );
						$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_option WHERE option_id = %d', $target_id ) );
					}
					break;
				case 'review':
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_review WHERE review_id = %d', $target_id ) );
					break;
			}
		}

		/**
		 * Delete a product the way the product list does ( its page, rows, own option sets through wpeasycart_product_deleted ).
		 *
		 * @param int $product_id Product.
		 * @return bool
		 */
		public static function delete_product( $product_id ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$product    = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, post_id FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $product ) {
				return false;
			}
			do_action( 'wpeasycart_product_deleting', $product_id );
			if ( (int) $product->post_id && function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( (int) $product->post_id, true );
			}
			foreach ( array( 'ec_product', 'ec_optionitemimage', 'ec_pricetier', 'ec_roleprice', 'ec_optionitemquantity', 'ec_option_to_product', 'ec_review', 'ec_categoryitem' ) as $table ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE product_id = %d', $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
			}
			wp_cache_delete( 'wpeasycart-all-categories' );
			do_action( 'wpeasycart_product_deleted', $product_id );
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
			return true;
		}

		// ---- Old addresses ----.

		/**
		 * Old addresses redirect to their new pages.
		 *
		 * @return bool
		 */
		public static function redirects_on() {
			return '0' !== (string) get_option( self::REDIRECTS_OPTION, '1' );
		}

		/**
		 * The first path parts of a source's old addresses ( product/, product-category/ … ): a slug alone redirects only
		 * under one of them. Sources add theirs when a run finishes.
		 *
		 * @param string[] $bases Bases to add.
		 * @return string[]
		 */
		public static function redirect_bases( $bases = array() ) {
			$stored = get_option( self::BASES_OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();
			if ( $bases ) {
				foreach ( (array) $bases as $base ) {
					$base = trim( strtolower( (string) $base ), '/' );
					if ( '' !== $base && ! in_array( $base, $stored, true ) ) {
						$stored[] = $base;
					}
				}
				update_option( self::BASES_OPTION, array_slice( $stored, 0, 20 ), false );
			}
			return $stored;
		}

		/**
		 * Action template_redirect: a 404 at an imported item's old address goes to its new page ( 301 ).
		 */
		public static function redirect_old_address() {
			$url = self::old_address_url();
			if ( '' !== $url ) {
				wp_safe_redirect( $url, 301, 'WP EasyCart' );
				exit;
			}
		}

		/**
		 * The new page for the 404 this request is ( '' = none, or redirects are off ).
		 *
		 * @return string
		 */
		public static function old_address_url() {
			if ( ! function_exists( 'is_404' ) || ! is_404() || ! self::ready() || ! self::redirects_on() ) {
				return '';
			}
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against stored paths, never printed.
			$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
			$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			$path = trim( rawurldecode( $path ), '/' );
			if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
				$path = trim( substr( $path, strlen( $home ) ), '/' );
			}
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a public address lookup; values are only matched against stored ids.
			$query = array(
				'product'     => isset( $_GET['product'] ) ? sanitize_title( wp_unslash( $_GET['product'] ) ) : '',
				'product_cat' => isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( $_GET['product_cat'] ) ) : '',
				'p'           => isset( $_GET['p'] ) ? (int) $_GET['p'] : 0,
			);
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return self::find_redirect( $path, $query );
		}

		/**
		 * The new page for an old address.
		 *
		 * @param string $path  Path below the site's home, without slashes at the ends.
		 * @param array  $query product ( slug ), product_cat ( slug ), p ( the old post id ).
		 * @return string URL, '' when nothing matches.
		 */
		public static function find_redirect( $path, $query = array() ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return '';
			}
			$path  = strtolower( trim( (string) $path, '/' ) );
			$query = array_merge(
				array(
					'product'     => '',
					'product_cat' => '',
					'p'           => 0,
				),
				(array) $query
			);
			$row   = null;
			if ( '' !== $path && strlen( $path ) <= 255 ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT entity, target_id FROM ec_import_map WHERE source_path = %s AND target_id > 0 AND entity IN ( 'product', 'category', 'tag' ) ORDER BY map_id DESC LIMIT 1", $path ) );
				if ( ! $row ) {
					/* A slug alone ( a product address with its category in it, an old category path ), only under a known base. */
					$parts = explode( '/', $path );
					$slug  = end( $parts );
					if ( count( $parts ) > 1 && in_array( $parts[0], self::redirect_bases(), true ) && '' !== $slug ) {
						$row = $wpdb->get_row( $wpdb->prepare( "SELECT entity, target_id FROM ec_import_map WHERE ( source_path LIKE %s ) AND target_id > 0 AND entity IN ( 'product', 'category', 'tag' ) ORDER BY ( entity = 'product' ) DESC, map_id DESC LIMIT 1", '%/' . $wpdb->esc_like( $slug ) ) );
					}
				}
			}
			if ( ! $row && '' !== $query['product'] ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT entity, target_id FROM ec_import_map WHERE entity = 'product' AND target_id > 0 AND ( source_path = %s OR source_path LIKE %s ) ORDER BY map_id DESC LIMIT 1", $query['product'], '%/' . $wpdb->esc_like( $query['product'] ) ) );
			}
			if ( ! $row && '' !== $query['product_cat'] ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT entity, target_id FROM ec_import_map WHERE entity = 'category' AND target_id > 0 AND ( source_path = %s OR source_path LIKE %s ) ORDER BY map_id DESC LIMIT 1", $query['product_cat'], '%/' . $wpdb->esc_like( $query['product_cat'] ) ) );
			}
			if ( ! $row && (int) $query['p'] > 0 ) {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT entity, target_id FROM ec_import_map WHERE source = 'woocommerce' AND entity = 'product' AND source_id = %s AND target_id > 0 LIMIT 1", (string) (int) $query['p'] ) );
			}
			return $row ? self::target_url( $row->entity, (int) $row->target_id ) : '';
		}

		/**
		 * The page of an imported product or category.
		 *
		 * @param string $entity    product | category | tag.
		 * @param int    $target_id EasyCart id.
		 * @return string
		 */
		public static function target_url( $entity, $target_id ) {
			global $wpdb;
			if ( 'product' === $entity ) {
				$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_product WHERE product_id = %d', (int) $target_id ) );
			} else {
				$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_category WHERE category_id = %d', (int) $target_id ) );
			}
			if ( $post_id <= 0 ) {
				return '';
			}
			$link = function_exists( 'wp_easycart_store_post_link' ) ? wp_easycart_store_post_link( $post_id ) : get_permalink( $post_id );
			return is_string( $link ) ? $link : '';
		}

		/**
		 * Every old address with its new page ( the redirects CSV for Redirection, Yoast or Rank Math ).
		 *
		 * @return array list of array( old path, new URL ).
		 */
		public static function redirect_rows() {
			global $wpdb;
			$out = array();
			if ( ! self::ready() ) {
				return $out;
			}
			$rows = (array) $wpdb->get_results( "SELECT entity, target_id, source_path FROM ec_import_map WHERE source_path != '' AND target_id > 0 AND entity IN ( 'product', 'category', 'tag' ) ORDER BY map_id ASC" );
			foreach ( $rows as $row ) {
				$url = self::target_url( $row->entity, (int) $row->target_id );
				if ( '' !== $url ) {
					$out[] = array( '/' . $row->source_path . '/', $url );
				}
			}
			return $out;
		}
	}

	wp_easycart_import::init();

endif;

require_once __DIR__ . '/class-wp-easycart-import-source.php';
require_once __DIR__ . '/class-wp-easycart-import-woocommerce.php';
require_once __DIR__ . '/class-wp-easycart-import-square.php';
