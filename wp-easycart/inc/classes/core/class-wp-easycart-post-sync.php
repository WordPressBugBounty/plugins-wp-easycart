<?php
/**
 * WP_EasyCart_Post_Sync
 *
 * Single choke point for all ec_store post mutations. Proves ownership of a
 * post before updating or deleting it, self-heals drifted links, and logs
 * every anomaly. Nothing outside this class should call wp_insert_post,
 * wp_update_post, or wp_delete_post for ec_store posts.
 *
 * Ownership model (two-way link):
 *   Forward:  ec_* table row stores post_id (existing behavior).
 *   Backward: postmeta _wpec_entity_type + _wpec_entity_id on the post (new).
 *   Legacy fallback: the [ec_store ...] shortcode fingerprint in post_content,
 *   used only until meta is backfilled. NOTE: menu fingerprints are ambiguous
 *   (a historical bug wrote submenuid="N" on level-3 updates), so fingerprint
 *   matches for menus additionally require that no other entity claims the post.
 *
 * Write modes: verification is identical everywhere, but the physical write
 * has two paths. Normal requests use wp_insert_post/wp_update_post so other
 * plugins' hooks run as usual. amfphp requests (mobile app API, detected via
 * WPEASYCART_ACCESSING_AMFPHP, overridable with the
 * wpeasycart_post_sync_direct_writes filter) write directly to the posts
 * table with $wpdb and flush caches, so no third-party save_post /
 * wp_insert_post hooks can inject output into the binary AMF response or
 * fail mid-request. Deletes always use wp_delete_post for proper meta/term
 * cleanup (matching the plugin's historical behavior in all contexts).
 *
 * @since 6.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Post_Sync' ) ) :

class WP_EasyCart_Post_Sync {

	const META_TYPE   = '_wpec_entity_type';
	const META_ID     = '_wpec_entity_id';
	const LOG_OPTION  = 'wpec_post_sync_log';
	const LOG_MAX     = 300;

	/** Verification result codes. */
	const VALID          = 'valid';           // meta backlink matches
	const VALID_LEGACY   = 'valid_legacy';    // fingerprint matches; meta backfilled
	const MISSING        = 'missing';         // no post at that ID
	const INVALID_ID     = 'invalid_id';      // 0 / negative / non-numeric
	const WRONG_TYPE     = 'wrong_type';      // post exists but is not ec_store
	const WRONG_OWNER    = 'wrong_owner';     // ec_store post owned by another entity
	const UNPROVEN       = 'unproven';        // ec_store post, no meta, fingerprint mismatch

	private static $instance;

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Keep ec_* tables consistent when an ec_store post is deleted by
		// anything else (wp-admin, another plugin, WP-CLI).
		add_action( 'before_delete_post', array( $this, 'on_external_post_delete' ), 10, 2 );
	}

	/**
	 * Entity registry. Central map of everything the verifier needs.
	 *
	 * shortcode_attrs: attribute names accepted as a legacy fingerprint, in
	 * priority order. Value is derived via fingerprint_value.
	 */
	public function registry() {
		return apply_filters( 'wpeasycart_post_sync_registry', array(
			'product' => array(
				'table'             => 'ec_product',
				'pk'                => 'product_id',
				'shortcode_attrs'   => array( 'modelnumber' ),
				'fingerprint_field' => 'model_number', // string, not the PK
			),
			'category' => array(
				'table'             => 'ec_category',
				'pk'                => 'category_id',
				'shortcode_attrs'   => array( 'groupid' ),
				'fingerprint_field' => 'category_id',
			),
			'manufacturer' => array(
				'table'             => 'ec_manufacturer',
				'pk'                => 'manufacturer_id',
				'shortcode_attrs'   => array( 'manufacturerid' ),
				'fingerprint_field' => 'manufacturer_id',
			),
			'menulevel1' => array(
				'table'             => 'ec_menulevel1',
				'pk'                => 'menulevel1_id',
				'shortcode_attrs'   => array( 'menuid' ),
				'fingerprint_field' => 'menulevel1_id',
				'ambiguous'         => true,
			),
			'menulevel2' => array(
				'table'             => 'ec_menulevel2',
				'pk'                => 'menulevel2_id',
				'shortcode_attrs'   => array( 'submenuid' ),
				'fingerprint_field' => 'menulevel2_id',
				'ambiguous'         => true,
			),
			'menulevel3' => array(
				'table'             => 'ec_menulevel3',
				'pk'                => 'menulevel3_id',
				// subsubmenuid is correct; submenuid appears on level-3 posts
				// touched by the historical update bug in the menus admin.
				'shortcode_attrs'   => array( 'subsubmenuid', 'submenuid' ),
				'fingerprint_field' => 'menulevel3_id',
				'ambiguous'         => true,
			),
		) );
	}

	/* ---------------------------------------------------------------------
	 * Public API — the only four methods call sites should ever use.
	 * ------------------------------------------------------------------- */

	/**
	 * Create the ec_store post for an entity. Returns post ID or 0.
	 * Never trusts wp_insert_post blindly; writes the backlink meta and the
	 * forward post_id atomically-ish (rolls the post back if the row write fails).
	 */
	public function insert( $type, $entity_id, array $postarr ) {
		$reg = $this->entity( $type );
		if ( ! $reg || $entity_id <= 0 ) {
			return 0;
		}

		unset( $postarr['ID'] ); // an insert is an insert
		$postarr['post_type'] = 'ec_store';

		if ( $this->use_direct_writes() ) {
			$post_id = $this->direct_insert( $postarr );
			if ( ! $post_id ) {
				$this->log( 'insert_failed', $type, $entity_id, 0, 'direct insert failed' );
				return 0;
			}
		} else {
			$post_id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$this->log( 'insert_failed', $type, $entity_id, 0, is_wp_error( $post_id ) ? $post_id->get_error_message() : 'returned 0' );
				return 0;
			}
		}

		$this->write_backlink_meta( $post_id, $type, $entity_id );

		if ( ! $this->write_forward_link( $type, $entity_id, $post_id ) ) {
			// Row vanished / write failed: don't leave an orphan behind.
			wp_delete_post( $post_id, true );
			$this->log( 'insert_rollback', $type, $entity_id, $post_id, 'forward link write failed' );
			return 0;
		}

		return (int) $post_id;
	}

	/**
	 * Update an entity's post. Verifies ownership first; on failure it
	 * resolves (relink or recreate) instead of writing to the stored ID.
	 * Returns the final, verified post ID (which may differ from $post_id), or 0.
	 */
	public function update( $type, $entity_id, $post_id, array $postarr ) {
		$resolved = $this->resolve( $type, $entity_id, $post_id, $postarr );
		if ( ! $resolved ) {
			return 0;
		}

		if ( $this->use_direct_writes() ) {
			if ( ! $this->direct_update( $resolved, $postarr ) ) {
				$this->log( 'update_failed', $type, $entity_id, $resolved, 'direct update failed' );
				return 0;
			}
			return (int) $resolved;
		}

		$postarr['ID']        = $resolved;
		$postarr['post_type'] = 'ec_store';

		$result = wp_update_post( $postarr, true );
		if ( is_wp_error( $result ) ) {
			$this->log( 'update_failed', $type, $entity_id, $resolved, $result->get_error_message() );
			return 0;
		}
		return (int) $resolved;
	}

	/**
	 * Convenience for status-only flips (publish <-> private) so call sites
	 * don't rebuild the whole post just to change visibility.
	 */
	public function set_status( $type, $entity_id, $post_id, $status ) {
		$verify = $this->verify( $type, $entity_id, $post_id );
		if ( self::VALID !== $verify && self::VALID_LEGACY !== $verify ) {
			$this->log( 'status_blocked', $type, $entity_id, $post_id, $verify );
			return 0;
		}
		if ( $this->use_direct_writes() ) {
			return $this->direct_update( $post_id, array( 'post_status' => $status ) ) ? (int) $post_id : 0;
		}
		$result = wp_update_post( array( 'ID' => (int) $post_id, 'post_status' => $status ), true );
		return is_wp_error( $result ) ? 0 : (int) $post_id;
	}


	/**
	 * Attach ownership to a freshly created post whose entity row was
	 * created *after* the post (demo data, importers that batch-insert rows).
	 * Writes the backlink meta only; the caller has already written post_id
	 * into the row. No verification — for brand-new posts only.
	 */
	public function tag_post( $post_id, $type, $entity_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! $this->entity( $type ) || (int) $entity_id <= 0 ) {
			return false;
		}
		$this->write_backlink_meta( $post_id, $type, $entity_id );
		return true;
	}

	/**
	 * Delete an entity's post. Only deletes on proven ownership; otherwise
	 * unlinks the row and leaves the post alone. Returns true if a post was
	 * deleted, false if the delete was skipped (row is unlinked either way).
	 */
	public function delete( $type, $entity_id, $post_id ) {
		$verify = $this->verify( $type, $entity_id, $post_id );

		// Unlink first so no code path can act on the stale pointer again.
		$this->write_forward_link( $type, $entity_id, 0 );

		if ( self::VALID === $verify || self::VALID_LEGACY === $verify ) {
			$force = apply_filters( 'wpeasycart_post_sync_force_delete', true, $type, $entity_id, $post_id );
			wp_delete_post( (int) $post_id, $force );
			return true;
		}

		if ( self::MISSING !== $verify && self::INVALID_ID !== $verify ) {
			$this->log( 'delete_blocked', $type, $entity_id, $post_id, $verify );
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Verification & resolution
	 * ------------------------------------------------------------------- */

	/**
	 * The verification ladder. Read-only except that a successful legacy
	 * fingerprint match backfills the meta (upgrading the link to strict).
	 */
	public function verify( $type, $entity_id, $post_id ) {
		$reg       = $this->entity( $type );
		$post_id   = (int) $post_id;
		$entity_id = (int) $entity_id;

		if ( ! $reg || $entity_id <= 0 ) {
			return self::INVALID_ID;
		}
		if ( $post_id <= 0 ) {
			return self::INVALID_ID; // also blocks wp_update_post's insert-on-empty-ID behavior
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return self::MISSING;
		}
		if ( 'ec_store' !== $post->post_type ) {
			return self::WRONG_TYPE; // hard stop; never touch regardless of anything else
		}

		$meta_type = get_post_meta( $post_id, self::META_TYPE, true );
		$meta_id   = (int) get_post_meta( $post_id, self::META_ID, true );

		if ( $meta_type ) {
			return ( $meta_type === $type && $meta_id === $entity_id ) ? self::VALID : self::WRONG_OWNER;
		}

		// Legacy post: no meta yet. Try the shortcode fingerprint.
		if ( $this->fingerprint_matches( $reg, $entity_id, $post->post_content ) ) {
			// Menu fingerprints are ambiguous across levels: require that no
			// *other* entity's forward link claims this post before trusting it.
			if ( ! empty( $reg['ambiguous'] ) ) {
				$claimant = $this->find_claimant( $post_id );
				if ( $claimant && ( $claimant['type'] !== $type || $claimant['entity_id'] !== $entity_id ) ) {
					return self::WRONG_OWNER;
				}
			}
			$this->write_backlink_meta( $post_id, $type, $entity_id );
			return self::VALID_LEGACY;
		}

		return self::UNPROVEN;
	}

	/**
	 * Produce a post ID that is safe to write to, repairing the link if
	 * needed. Order: verify stored ID -> search by meta -> search by
	 * fingerprint -> recreate. Never returns an ID it hasn't proven.
	 */
	public function resolve( $type, $entity_id, $post_id, array $postarr = array() ) {
		$verify = $this->verify( $type, $entity_id, $post_id );

		if ( self::VALID === $verify || self::VALID_LEGACY === $verify ) {
			return (int) $post_id;
		}

		$this->log( 'link_broken', $type, $entity_id, $post_id, $verify );

		// 1) Strict recovery: an ec_store post already tagged as this entity.
		$found = $this->find_post_by_meta( $type, $entity_id );

		// 2) Legacy recovery: fingerprint search, but only accept a candidate
		//    no other entity claims (protects against menu-level ambiguity and
		//    duplicate-claim situations).
		if ( ! $found ) {
			$found = $this->find_post_by_fingerprint( $type, $entity_id );
		}

		if ( $found ) {
			$this->write_backlink_meta( $found, $type, $entity_id );
			$this->write_forward_link( $type, $entity_id, $found );
			$this->log( 'link_repaired', $type, $entity_id, $found, 'relinked existing post' );
			return $found;
		}

		// 3) Recreate. Only possible when the caller gave us content to write.
		if ( $postarr ) {
			$new_id = $this->insert( $type, $entity_id, $postarr );
			if ( $new_id ) {
				$this->log( 'link_recreated', $type, $entity_id, $new_id, 'inserted replacement post' );
			}
			return $new_id;
		}

		return 0;
	}

	/* ---------------------------------------------------------------------
	 * Audit / backfill
	 * ------------------------------------------------------------------- */

	/**
	 * Full health check across all entity tables plus an orphan scan.
	 * $apply = false is a dry run (report only). Designed to be run from the
	 * Store Status page and once from the db_manager version-update list.
	 */
	public function audit( $apply = false ) {
		global $wpdb;
		$report = array(
			'checked' => 0, 'valid' => 0, 'backfilled' => 0, 'repaired' => 0,
			'broken' => array(), 'duplicates' => array(), 'orphans' => array(),
		);
		$claims = array(); // post_id => array of "type:entity_id"

		foreach ( $this->registry() as $type => $reg ) {
			$rows = $wpdb->get_results( "SELECT {$reg['pk']} AS entity_id, post_id FROM {$reg['table']}" );
			if ( ! is_array( $rows ) ) {
				continue;
			}
			foreach ( $rows as $row ) {
				$report['checked']++;
				$eid = (int) $row->entity_id;
				$pid = (int) $row->post_id;
				if ( $pid > 0 ) {
					$claims[ $pid ][] = $type . ':' . $eid;
				}
				$verify = $this->verify( $type, $eid, $pid );
				if ( self::VALID === $verify ) {
					$report['valid']++;
				} elseif ( self::VALID_LEGACY === $verify ) {
					$report['valid']++;
					$report['backfilled']++;
				} else {
					if ( $apply ) {
						$fixed = $this->resolve( $type, $eid, $pid ); // relink only; no recreate without content
						if ( $fixed ) {
							$report['repaired']++;
							continue;
						}
					}
					$report['broken'][] = array( 'type' => $type, 'entity_id' => $eid, 'post_id' => $pid, 'status' => $verify );
				}
			}
		}

		foreach ( $claims as $pid => $owners ) {
			if ( count( $owners ) > 1 ) {
				$report['duplicates'][] = array( 'post_id' => $pid, 'claimed_by' => $owners );
			}
		}

		// Orphan scan: ec_store posts nobody claims.
		$ids = get_posts( array(
			'post_type' => 'ec_store', 'post_status' => 'any',
			'numberposts' => -1, 'fields' => 'ids',
		) );
		foreach ( $ids as $pid ) {
			if ( empty( $claims[ $pid ] ) ) {
				$report['orphans'][] = (int) $pid;
			}
		}

		return $report;
	}

	/**
	 * before_delete_post: an ec_store post is being removed by someone else.
	 * Zero out any ec_* rows pointing at it so no stale pointer survives to
	 * become dangerous after ID reuse.
	 */
	public function on_external_post_delete( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( ! $post || 'ec_store' !== $post->post_type ) {
			return;
		}
		$claimant = $this->find_claimant( (int) $post_id );
		if ( $claimant ) {
			$this->write_forward_link( $claimant['type'], $claimant['entity_id'], 0 );
			$this->log( 'external_delete_unlinked', $claimant['type'], $claimant['entity_id'], $post_id, 'ec_store post deleted outside sync' );
		}
	}

	/* ---------------------------------------------------------------------
	 * Direct (hook-free) write path for amfphp requests
	 * ------------------------------------------------------------------- */

	/**
	 * Whether to bypass wp_insert_post/wp_update_post and write to the posts
	 * table directly. Defaults to true only for amfphp (mobile app) requests,
	 * where third-party post hooks can corrupt the binary AMF response.
	 */
	private function use_direct_writes() {
		return (bool) apply_filters( 'wpeasycart_post_sync_direct_writes', defined( 'WPEASYCART_ACCESSING_AMFPHP' ) );
	}

	/**
	 * Hook-free insert. Fills every column so it works under MySQL strict
	 * mode (the legacy raw INSERT relied on implicit defaults).
	 */
	private function direct_insert( array $postarr ) {
		global $wpdb;
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', 1 );
		$data = array(
			'post_author'           => get_current_user_id(),
			'post_date'             => $now,
			'post_date_gmt'         => $now_gmt,
			'post_content'          => isset( $postarr['post_content'] ) ? $postarr['post_content'] : '',
			'post_title'            => isset( $postarr['post_title'] ) ? $postarr['post_title'] : '',
			'post_excerpt'          => isset( $postarr['post_excerpt'] ) ? $postarr['post_excerpt'] : '',
			'post_status'           => isset( $postarr['post_status'] ) ? $postarr['post_status'] : 'publish',
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'post_name'             => isset( $postarr['post_name'] ) ? $postarr['post_name'] : sanitize_title( isset( $postarr['post_title'] ) ? $postarr['post_title'] : '' ),
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => $now,
			'post_modified_gmt'     => $now_gmt,
			'post_content_filtered' => '',
			'post_parent'           => 0,
			'guid'                  => isset( $postarr['guid'] ) ? $postarr['guid'] : '',
			'menu_order'            => 0,
			'post_type'             => 'ec_store',
			'post_mime_type'        => '',
			'comment_count'         => 0,
		);
		if ( ! $wpdb->insert( $wpdb->posts, $data ) ) {
			return 0;
		}
		$post_id = (int) $wpdb->insert_id;
		if ( '' === $data['guid'] ) {
			// Mirror core's default guid shape without triggering permalink hooks.
			$wpdb->update( $wpdb->posts, array( 'guid' => home_url( '?post_type=ec_store&p=' . $post_id ) ), array( 'ID' => $post_id ) );
		}
		clean_post_cache( $post_id );
		return $post_id;
	}

	/** Hook-free update of a verified post. Only writes supplied fields. */
	private function direct_update( $post_id, array $postarr ) {
		global $wpdb;
		$data = array();
		foreach ( array( 'post_content', 'post_title', 'post_name', 'post_status', 'post_excerpt', 'guid' ) as $field ) {
			if ( isset( $postarr[ $field ] ) ) {
				$data[ $field ] = $postarr[ $field ];
			}
		}
		$data['post_modified']     = current_time( 'mysql' );
		$data['post_modified_gmt'] = current_time( 'mysql', 1 );
		$result = $wpdb->update( $wpdb->posts, $data, array( 'ID' => (int) $post_id ) );
		clean_post_cache( (int) $post_id );
		return false !== $result;
	}

	/**
	 * Write the ownership backlink. Raw upsert in direct mode so not even
	 * postmeta hooks fire during an amfphp request.
	 */
	private function write_backlink_meta( $post_id, $type, $entity_id ) {
		if ( ! $this->use_direct_writes() ) {
			update_post_meta( $post_id, self::META_TYPE, $type );
			update_post_meta( $post_id, self::META_ID, (int) $entity_id );
			return;
		}
		global $wpdb;
		foreach ( array( self::META_TYPE => $type, self::META_ID => (int) $entity_id ) as $key => $value ) {
			$meta_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				(int) $post_id, $key
			) );
			if ( $meta_id ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $value ), array( 'meta_id' => (int) $meta_id ) );
			} else {
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $post_id, 'meta_key' => $key, 'meta_value' => $value ) );
			}
		}
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------- */

	private function entity( $type ) {
		$registry = $this->registry();
		return isset( $registry[ $type ] ) ? $registry[ $type ] : null;
	}

	private function write_forward_link( $type, $entity_id, $post_id ) {
		global $wpdb;
		$reg = $this->entity( $type );
		if ( ! $reg ) {
			return false;
		}
		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$reg['table']} SET post_id = %d WHERE {$reg['pk']} = %d",
			(int) $post_id, (int) $entity_id
		) );
		return false !== $result && $wpdb->rows_affected >= 0 && $this->row_exists( $reg, $entity_id );
	}

	private function row_exists( $reg, $entity_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$reg['table']} WHERE {$reg['pk']} = %d", (int) $entity_id
		) );
	}

	private function expected_fingerprint_value( $reg, $entity_id ) {
		global $wpdb;
		if ( $reg['fingerprint_field'] === $reg['pk'] ) {
			return (string) (int) $entity_id;
		}
		return (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT {$reg['fingerprint_field']} FROM {$reg['table']} WHERE {$reg['pk']} = %d", (int) $entity_id
		) );
	}

	private function fingerprint_matches( $reg, $entity_id, $post_content ) {
		$value = $this->expected_fingerprint_value( $reg, $entity_id );
		if ( '' === $value ) {
			return false;
		}
		foreach ( $reg['shortcode_attrs'] as $attr ) {
			if ( preg_match(
				'/\[ec_store\s+[^\]]*' . preg_quote( $attr, '/' ) . '\s*=\s*["\']' . preg_quote( $value, '/' ) . '["\']/i',
				(string) $post_content
			) ) {
				return true;
			}
		}
		return false;
	}

	private function find_post_by_meta( $type, $entity_id ) {
		$ids = get_posts( array(
			'post_type'   => 'ec_store',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array(
				array( 'key' => self::META_TYPE, 'value' => $type ),
				array( 'key' => self::META_ID, 'value' => (int) $entity_id ),
			),
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	private function find_post_by_fingerprint( $type, $entity_id ) {
		global $wpdb;
		$reg   = $this->entity( $type );
		$value = $this->expected_fingerprint_value( $reg, $entity_id );
		if ( '' === $value ) {
			return 0;
		}
		foreach ( $reg['shortcode_attrs'] as $attr ) {
			$like       = '%' . $wpdb->esc_like( $attr . '="' . $value . '"' ) . '%';
			$candidates = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ec_store' AND post_content LIKE %s",
				$like
			) );
			foreach ( $candidates as $pid ) {
				$pid      = (int) $pid;
				$claimant = $this->find_claimant( $pid );
				if ( ! $claimant || ( $claimant['type'] === $type && $claimant['entity_id'] === (int) $entity_id ) ) {
					return $pid;
				}
			}
		}
		return 0;
	}

	/** Reverse lookup: which entity's forward link points at this post? */
	private function find_claimant( $post_id ) {
		global $wpdb;
		foreach ( $this->registry() as $type => $reg ) {
			$eid = $wpdb->get_var( $wpdb->prepare(
				"SELECT {$reg['pk']} FROM {$reg['table']} WHERE post_id = %d LIMIT 1", (int) $post_id
			) );
			if ( $eid ) {
				return array( 'type' => $type, 'entity_id' => (int) $eid );
			}
		}
		return null;
	}

	private function log( $event, $type, $entity_id, $post_id, $detail = '' ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log[] = array(
			'time'      => current_time( 'mysql' ),
			'event'     => $event,
			'type'      => $type,
			'entity_id' => (int) $entity_id,
			'post_id'   => (int) $post_id,
			'detail'    => $detail,
		);
		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, -self::LOG_MAX );
		}
		update_option( self::LOG_OPTION, $log, false );
		do_action( 'wpeasycart_post_sync_event', $event, $type, $entity_id, $post_id, $detail );
	}
}

endif; // class_exists

if ( ! function_exists( 'wp_easycart_post_sync' ) ) {
	function wp_easycart_post_sync() {
		return WP_EasyCart_Post_Sync::instance();
	}
}