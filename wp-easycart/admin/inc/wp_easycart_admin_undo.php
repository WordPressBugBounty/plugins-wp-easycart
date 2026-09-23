<?php
/**
 * WP EasyCart Admin — one shared 15-minute undo for list deletions.
 *
 * The reviews, roles, subscribers, gift cards and locations lists each grew their own snapshot-and-restore.
 * Orders and products delete through page loads rather than AJAX, so they get the same safety net here:
 * the delete stores every row it is about to remove, redirects with ?undo=<key>, and the list prints a bar
 * offering to put it all back. Nothing is kept after 15 minutes.
 *
 * A feature registers a restore callback on 'wp_easycart_admin_undo_restore_<kind>':
 * it receives the snapshot and returns a message, or a WP_Error.
 *
 * @since 6.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_undo' ) ) :

	final class wp_easycart_admin_undo {

		const NONCE = 'wp-easycart-ecv2-undo';

		/** Option name prefix. Deliberately not a transient: see store(). */
		const PREFIX = 'ec_undo_';

		/** How long a deletion can be taken back. */
		public static function ttl() {
			return (int) apply_filters( 'wp_easycart_admin_undo_ttl', 15 * MINUTE_IN_SECONDS );
		}

		/**
		 * Keep a snapshot and hand back the key that names it.
		 *
		 * 6.0.1: this used a transient and the Undo kept reporting that the deletion could no longer be taken
		 * back. On a site with a persistent object cache a transient lives in the cache, and the admin calls
		 * wp_cache_flush() in dozens of places, so the snapshot was often gone before the merchant clicked.
		 * A non-autoloaded option survives that, which is why the safe-delete trash uses one too. Expiry is
		 * held in the row and swept on the next write.
		 *
		 * @param string $kind     What was deleted ( 'order', 'product' … ); names the restore hook.
		 * @param mixed  $snapshot Whatever that kind's restore callback needs.
		 * @param string $message  Optional sentence for the Undo bar ( "Deleted 3 subscribers." ). @since 6.0.1
		 * @return string
		 */
		public static function store( $kind, $snapshot, $message = '' ) {
			$kind = sanitize_key( $kind );
			$key  = $kind . '_' . time() . '_' . wp_rand( 100, 999 );
			self::prune();
			add_option(
				self::PREFIX . $key,
				array( 'kind' => $kind, 'snapshot' => $snapshot, 'expires' => time() + self::ttl(), 'message' => (string) $message ),
				'',
				'no'
			);
			return $key;
		}

		/** Strip a key down to what store() can produce. */
		private static function clean_key( $key ) {
			return preg_replace( '/[^a-z0-9_]/', '', (string) $key );
		}

		/** The entry behind a key, or false when it has expired or been used. Does not remove it. */
		private static function entry( $key ) {
			$key = self::clean_key( $key );
			if ( '' === $key ) {
				return false;
			}
			$entry = get_option( self::PREFIX . $key );
			if ( ! $entry || ! is_array( $entry ) || empty( $entry['kind'] ) ) {
				return false;
			}
			if ( ! empty( $entry['expires'] ) && (int) $entry['expires'] < time() ) {
				delete_option( self::PREFIX . $key );
				return false;
			}
			return $entry;
		}

		/** The snapshot behind a key, removed as it is handed over. False when it has expired or been used. */
		public static function take( $key ) {
			$entry = self::entry( $key );
			if ( ! $entry ) {
				return false;
			}
			delete_option( self::PREFIX . self::clean_key( $key ) );
			return $entry;
		}

		/** Is this key still good? ( The bar is only printed while it is. ) */
		public static function exists( $key ) {
			return (bool) self::entry( $key );
		}

		/**
		 * What a key would put back ( 'order', 'product', 'account', 'subscriber' … ), or '' when it has expired.
		 * Does not use the key up. @since 6.0.1
		 *
		 * @param string $key From store().
		 * @return string
		 */
		public static function kind( $key ) {
			$entry = self::entry( $key );
			return $entry ? (string) $entry['kind'] : '';
		}

		/** Drop snapshots whose time is up, so the options table does not collect them. */
		public static function prune() {
			global $wpdb;
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s LIMIT 200", $wpdb->esc_like( self::PREFIX ) . '%' ) );
			foreach ( (array) $names as $name ) {
				$entry = get_option( $name );
				if ( ! is_array( $entry ) || empty( $entry['expires'] ) || (int) $entry['expires'] < time() ) {
					delete_option( $name );
				}
			}
		}
		/**
		 * The bar above a list after a delete: what happened, and a button to put it back.
		 * Prints nothing unless the page was reached with a live ?undo= key, or ( 6.0.1 ) a live ?trash= id from
		 * the safe-delete engine ( wp_easycart_admin_safe_delete ), whose deletes stay undoable for 30 days.
		 *
		 * The bar stays until the merchant dismisses it or leaves the page. A toast was tried for AJAX deletes and
		 * faded out before anyone could reach its Undo, so a list that deletes over AJAX reloads onto this bar.
		 *
		 * @param string $message Sentence for the bar; a default is used when empty.
		 */
		public static function maybe_print_bar( $message = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: the page the Undo button sent us back to.
			if ( isset( $_GET['restored'] ) ) {
				echo '<div class="ecv2-wrap ecv2-undo-strip ecv2-undo-strip-done"><span class="dashicons dashicons-yes-alt ecv2-undo-strip-icon" aria-hidden="true"></span><span class="ecv2-undo-strip-text">' . esc_html__( 'Put back.', 'wp-easycart' ) . '</span></div>';
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which snapshot this page may offer back.
			$key = isset( $_GET['undo'] ) ? sanitize_text_field( wp_unslash( $_GET['undo'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which Recently deleted entry this page may offer back.
			$trash = isset( $_GET['trash'] ) ? sanitize_key( wp_unslash( $_GET['trash'] ) ) : '';
			$attrs = '';
			$note  = '';
			$entry = ( '' !== $key ) ? self::entry( $key ) : false;
			if ( $entry ) {
				$attrs = ' data-key="' . esc_attr( $key ) . '" data-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '"';
				$note  = __( 'You can put this back for the next 15 minutes.', 'wp-easycart' );
				if ( ! empty( $entry['message'] ) ) {
					$message = $entry['message'];
				}
			} else if ( '' !== $trash && class_exists( 'wp_easycart_admin_safe_delete' ) ) {
				$entry = wp_easycart_admin_safe_delete()->trash_entry( $trash );
				if ( ! $entry ) {
					return;
				}
				$attrs = ' data-trash="' . esc_attr( $trash ) . '" data-nonce="' . esc_attr( wp_create_nonce( wp_easycart_admin_safe_delete::NONCE ) ) . '"';
				if ( ! empty( $entry['message'] ) ) {
					$message = $entry['message'];
				}
				/* translators: %d: number of days a deletion can be undone. */
				$note = sprintf( __( 'You can put this back now, or for %d days from Store Status › Recently deleted.', 'wp-easycart' ), wp_easycart_admin_safe_delete::RETENTION_DAYS );
			} else {
				return;
			}
			if ( '' === $message ) {
				$message = __( 'Deleted.', 'wp-easycart' );
			}
			?>
			<div class="ecv2-wrap ecv2-undo-strip" id="ecv2_undo_strip"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is esc_attr()'d above. ?>>
				<span class="dashicons dashicons-undo ecv2-undo-strip-icon" aria-hidden="true"></span>
				<span class="ecv2-undo-strip-text"><?php echo esc_html( $message ); ?> <span><?php echo esc_html( $note ); ?></span></span>
				<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" id="ecv2_undo_strip_go"><?php esc_html_e( 'Undo', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-undo-strip-x" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-easycart' ); ?>" onclick="jQuery( '#ecv2_undo_strip' ).slideUp( 140 );">&times;</button>
			</div>
			<script>
			jQuery( function( $ ) {
				$( '#ecv2_undo_strip_go' ).on( 'click', function() {
					var $bar = $( '#ecv2_undo_strip' ), $btn = $( this ), label = $btn.text(), trash = $bar.data( 'trash' );
					var data = trash ? { action: 'ecv2_delete_restore', trash_id: trash, nonce: $bar.data( 'nonce' ) } : { action: 'ecv2_undo_restore', key: $bar.data( 'key' ), nonce: $bar.data( 'nonce' ) };
					$btn.prop( 'disabled', true );
					$.post( ( window.ajaxurl || ( window.wpeasycart_admin_ajax_object && wpeasycart_admin_ajax_object.ajax_url ) ), data ).done( function( r ) {
						if ( r && r.success ) { window.location.href = window.location.href.replace( /([?&])(undo|trash)=[^&#]*/, '$1restored=1' ); return; }
						$btn.prop( 'disabled', false ).text( label );
						if ( window.ecv2_toast ) { ecv2_toast( ( r && r.data && r.data.message ) || '', 'error' ); }
					} ).fail( function() { $btn.prop( 'disabled', false ).text( label ); } );
				} );
			} );
			</script>
			<?php
		}
	}

endif;

add_action( 'wp_ajax_ecv2_undo_restore', 'ecv2_undo_restore' );
/**
 * Put back whatever a key stands for. The kind's own callback does the work.
 *
 * @since 6.0.1
 */
function ecv2_undo_restore() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) && ! current_user_can( 'wpec_products' ) && ! current_user_can( 'wpec_users' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( wp_easycart_admin_undo::NONCE, 'nonce' );
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	/* 6.0.1: the capability that matches what was deleted, not just any of the list capabilities above. */
	$caps = apply_filters( 'wp_easycart_admin_undo_capabilities', array( 'order' => 'wpec_orders', 'product' => 'wpec_products', 'account' => 'wpec_users', 'subscriber' => 'wpec_users' ) );
	$kind = wp_easycart_admin_undo::kind( $key );
	if ( '' !== $kind && isset( $caps[ $kind ] ) && ! current_user_can( 'manage_options' ) && ! current_user_can( $caps[ $kind ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	$entry = wp_easycart_admin_undo::take( $key );
	if ( ! $entry ) {
		wp_send_json_error( array( 'message' => __( 'This deletion can no longer be undone.', 'wp-easycart' ) ) );
	}
	$result = apply_filters( 'wp_easycart_admin_undo_restore_' . $entry['kind'], null, $entry['snapshot'] );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	if ( null === $result ) {
		wp_send_json_error( array( 'message' => __( 'Nothing here knows how to put that back.', 'wp-easycart' ) ) );
	}
	wp_send_json_success( array( 'message' => is_string( $result ) ? $result : __( 'Restored.', 'wp-easycart' ) ) );
}
