<?php
/**
 * WP EasyCart — an import source ( 6.0.3 ).
 *
 * A source tells the Import page what it found and does the work in phases for wp_easycart_import:
 *
 * - detect( $fresh ): array( available ( something to import ), connected, status ( one line ), counts ( key => number ),
 *   checks ( list of array( level ok | warn | bad | info, title, text ) ), action ( optional array( label, url ) ) ).
 * - entities( $detect ): key => array( label, desc, count, available, note, default, requires ( other keys ) ).
 * - fields( $detect ): the merchant's choices, key => array( type select | toggle, label, desc, options, default ).
 * - phases( $settings, $trial ) and run( $phase, $cursor, &$job, $deadline ): run() does what it can before $deadline and
 *   answers array( cursor, done, count, total?, error? ). An error pauses the job with that message.
 * - finish( &$job ) once every phase is done.
 *
 * record() writes an item's map row and counts it in the job ( counts: entity => status => n, notes ).
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_import_source' ) ) :

	/**
	 * Base class of an import source.
	 */
	abstract class wp_easycart_import_source {

		/**
		 * Source id ( woocommerce, square … ).
		 *
		 * @return string
		 */
		abstract public function id();

		/**
		 * Name shown to the merchant.
		 *
		 * @return string
		 */
		abstract public function label();

		/**
		 * One line under the name.
		 *
		 * @return string
		 */
		public function description() {
			return '';
		}

		/**
		 * Which store at the source the map rows belong to ( 'local' for this site; a Square account and mode ).
		 *
		 * @return string
		 */
		public function site() {
			return 'local';
		}

		/**
		 * What the source holds.
		 *
		 * @param bool $fresh Ask again instead of using a kept answer.
		 * @return array
		 */
		abstract public function detect( $fresh = false );

		/**
		 * What can come across.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		abstract public function entities( $detect );

		/**
		 * The merchant's choices.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		public function fields( $detect ) {
			return array();
		}

		/**
		 * Check the merchant's choices against what the source offers.
		 *
		 * @param array $choices entities => list, fields => key => value.
		 * @param array $detect  From detect().
		 * @return array|WP_Error entities, fields.
		 */
		public function clean_settings( $choices, $detect ) {
			$choices  = (array) $choices;
			$entities = $this->entities( $detect );
			$picked   = isset( $choices['entities'] ) ? array_map( 'sanitize_key', (array) $choices['entities'] ) : array();
			$out      = array(
				'entities' => array(),
				'fields'   => array(),
			);
			foreach ( $entities as $key => $entity ) {
				if ( in_array( $key, $picked, true ) && ! empty( $entity['available'] ) ) {
					$out['entities'][] = $key;
				}
			}
			foreach ( $out['entities'] as $key ) {
				foreach ( isset( $entities[ $key ]['requires'] ) ? (array) $entities[ $key ]['requires'] : array() as $need ) {
					if ( isset( $entities[ $need ] ) && ! empty( $entities[ $need ]['available'] ) && ! in_array( $need, $out['entities'], true ) ) {
						$out['entities'][] = $need;
					}
				}
			}
			if ( ! $out['entities'] ) {
				return new WP_Error( 'wp_easycart_import_nothing', __( 'Choose at least one thing to import.', 'wp-easycart' ) );
			}
			$given = isset( $choices['fields'] ) ? (array) $choices['fields'] : array();
			foreach ( $this->fields( $detect ) as $key => $field ) {
				$value = array_key_exists( $key, $given ) ? $given[ $key ] : $field['default'];
				if ( 'toggle' === $field['type'] ) {
					$value = ( empty( $value ) || '0' === (string) $value ) ? '0' : '1';
				} else {
					$value = sanitize_key( (string) $value );
					if ( ! isset( $field['options'][ $value ] ) ) {
						$value = (string) $field['default'];
					}
				}
				$out['fields'][ $key ] = $value;
			}
			return $out;
		}

		/**
		 * The merchant chose this entity.
		 *
		 * @param array  $settings From clean_settings().
		 * @param string $entity   Entity.
		 * @return bool
		 */
		protected function wants( $settings, $entity ) {
			return isset( $settings['entities'] ) && in_array( $entity, (array) $settings['entities'], true );
		}

		/**
		 * A choice's value.
		 *
		 * @param array  $settings From clean_settings().
		 * @param string $key      Field.
		 * @param string $fallback Value when it was not offered.
		 * @return string
		 */
		protected function field( $settings, $key, $fallback = '' ) {
			return isset( $settings['fields'][ $key ] ) ? (string) $settings['fields'][ $key ] : (string) $fallback;
		}

		/**
		 * The phases of a run, in order.
		 *
		 * @param array $settings From clean_settings().
		 * @param bool  $trial    A trial of a few products.
		 * @return string[]
		 */
		abstract public function phases( $settings, $trial );

		/**
		 * A phase's name.
		 *
		 * @param string $phase Phase.
		 * @return string
		 */
		public function phase_label( $phase ) {
			return ucfirst( (string) $phase );
		}

		/**
		 * How many items a phase has ( 0 = not known yet ).
		 *
		 * @param string $phase    Phase.
		 * @param array  $settings Settings.
		 * @param bool   $trial    Trial.
		 * @return int
		 */
		public function phase_total( $phase, $settings, $trial ) {
			return 0;
		}

		/**
		 * Work on a phase until $deadline.
		 *
		 * @param string $phase    Phase.
		 * @param mixed  $cursor   Where the last slice stopped ( null = the start ).
		 * @param array  $job      The job ( counts and state may be changed ).
		 * @param float  $deadline microtime( true ) to stop at.
		 * @return array cursor, done, count, total, error.
		 */
		abstract public function run( $phase, $cursor, &$job, $deadline );

		/**
		 * Every phase is done.
		 *
		 * @param array $job The job.
		 */
		public function finish( &$job ) {
		}

		/**
		 * Write an item's map row and count it.
		 *
		 * A skipped item that is already in the map keeps its row ( the run that made it, its old address ). An updated
		 * item belongs to this run from now on, but this run did not create it, so undoing this run keeps it.
		 *
		 * @param array  $job       The job.
		 * @param string $entity    Entity.
		 * @param string $source_id The item's id at the source.
		 * @param string $status    imported | updated | skipped | failed.
		 * @param array  $args      target_id, label, message, hash, path; created defaults to true for imported.
		 */
		protected function record( &$job, $entity, $source_id, $status, $args = array() ) {
			$args = (array) $args;
			if ( ! isset( $job['counts'][ $entity ] ) ) {
				$job['counts'][ $entity ] = array(
					'imported' => 0,
					'updated'  => 0,
					'skipped'  => 0,
					'failed'   => 0,
					'notes'    => 0,
				);
			}
			if ( isset( $job['counts'][ $entity ][ $status ] ) ) {
				++$job['counts'][ $entity ][ $status ];
			}
			if ( ! empty( $args['message'] ) ) {
				++$job['counts'][ $entity ]['notes'];
			}
			$existing = wp_easycart_import::map_row( $this->id(), $job['site'], $entity, $source_id );
			if ( 'skipped' === $status && $existing ) {
				return;
			}
			$args['status']  = $status;
			$args['run_id']  = (int) $job['id'];
			$args['created'] = isset( $args['created'] ) ? (bool) $args['created'] : ( 'imported' === $status );
			if ( 'failed' === $status && $existing && (int) $existing->target_id > 0 ) {
				/* A failed update keeps the item it already became. */
				$args['target_id'] = (int) $existing->target_id;
				$args['created']   = false;
				$args['run_id']    = (int) $existing->run_id;
			}
			wp_easycart_import::map_set( $this->id(), $job['site'], $entity, $source_id, $args );
		}

		/**
		 * Time is left in this slice.
		 *
		 * @param float $deadline microtime( true ) to stop at.
		 * @return bool
		 */
		protected function time_left( $deadline ) {
			return microtime( true ) < (float) $deadline;
		}

		/**
		 * How many products a trial has imported so far.
		 *
		 * @param array $job The job.
		 * @return int
		 */
		protected function trial_count( $job ) {
			if ( empty( $job['counts']['product'] ) ) {
				return 0;
			}
			$counts = $job['counts']['product'];
			return (int) $counts['imported'] + (int) $counts['updated'] + (int) $counts['failed'] + (int) $counts['skipped'];
		}
	}

endif;
